<?php
/**
 * Caracool Vinos — Los corazones de la gente
 * ─────────────────────────────────────────────────────────────────────
 * Cada persona guarda sus vinos en su navegador («Mis vinos»), sin cuenta.
 * Desde la 0.6.0, además, el corazón avisa a esta web: «este vino, uno
 * más» o «este vino, uno menos». Así la web sabe cuántas personas tienen
 * guardado cada vino sin saber quiénes son:
 *
 *   - no hay cookies ni cuenta y no se guarda la dirección de nadie: solo
 *     un contador por vino;
 *   - para que nadie infle un vino a base de clics, cada dirección tiene un
 *     tope de avisos cada diez minutos. El tope se lleva en un transitorio
 *     cuyo nombre es una huella de la dirección, y caduca solo;
 *   - el navegador ya impide que la misma persona sume dos veces el mismo
 *     vino, porque el corazón es un interruptor. Los que alguien tenía
 *     guardados de antes se cuentan una vez, la primera vez que abre la
 *     página con esta versión.
 *
 * Qué se hace con la cuenta:
 *
 *   - la página enseña la cifra junto al corazón a partir de un mínimo
 *     (MINIMO) y ofrece «Los más guardados» cuando hay unos cuantos vinos
 *     que lo pasan (MINIMO_LISTA);
 *   - el panel de Vinos tiene la pestaña «Corazones», con la lista;
 *   - en cada comprobación con Bodega (dos veces al día, o al avisar
 *     Bodega) se le manda la cuenta si ha cambiado, para que Caracool vea
 *     los más guardados de cada casa y de todas juntas.
 *
 * Los vinos se cuentan por la llave de la carta (nombre, bodega, añada,
 * tipo y formato), que es la misma con la que el navegador guarda «Mis
 * vinos». Junto a la cuenta se apuntan el nombre, la bodega, el tipo y la
 * añada, para que el panel sepa qué vino era aunque salga de la carta y
 * para que Bodega lo encuentre en la bodega común: allí se suma por vino,
 * sin añada, y de todas las casas.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Caracool_Vinos_Corazones {

	const BD           = 1;
	const OPCION_BD    = 'caracool_vinos_corazones_bd';
	const OPCION_ENVIO = 'caracool_vinos_corazones_envio';
	const MINIMO       = 3;   // desde cuántas personas se enseña la cifra
	const MINIMO_LISTA = 3;   // cuántos vinos tienen que llegar para ofrecer «Los más guardados»
	const TOPE         = 60;  // avisos por dirección cada diez minutos
	const POR_AVISO    = 200; // vinos como mucho en un aviso (el primero lleva los que ya estaban guardados)

	public function __construct() {
		// la tabla se comprueba solo donde se usa, nunca al servir una página
		add_action( 'rest_api_init', array( __CLASS__, 'repasar_tabla' ), 1 );
		add_action( 'admin_init', array( __CLASS__, 'repasar_tabla' ) );
		add_action( 'caracool_vinos_comprobado', array( __CLASS__, 'repasar_tabla' ), 5 );
		add_action( 'rest_api_init', array( $this, 'rutas' ) );
		add_action( 'caracool_vinos_comprobado', array( __CLASS__, 'enviar' ) );
		add_action( 'caracool_vinos_settings_panels', array( $this, 'panel' ), 30 );
	}

	// ── La tabla ────────────────────────────────────────────────────────

	public static function tabla() {
		global $wpdb;
		return $wpdb->prefix . 'caracool_vinos_corazones';
	}

	public static function repasar_tabla() {
		static $hecho = false;
		if ( $hecho ) {
			return;
		}
		$hecho = true;
		if ( (int) get_option( self::OPCION_BD, 0 ) < self::BD ) {
			self::crear_tabla();
		}
	}

	public static function crear_tabla() {
		global $wpdb;
		if ( ! function_exists( 'dbDelta' ) ) {
			if ( ! file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
				return;
			}
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		$c = method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';
		dbDelta(
			'CREATE TABLE ' . self::tabla() . " (
				llave varchar(190) NOT NULL,
				nombre varchar(190) NOT NULL DEFAULT '',
				bodega varchar(190) NOT NULL DEFAULT '',
				tipo varchar(40) NOT NULL DEFAULT '',
				anada varchar(40) NOT NULL DEFAULT '',
				n int(11) NOT NULL DEFAULT 0,
				primero datetime NOT NULL,
				ultimo datetime NOT NULL,
				PRIMARY KEY  (llave)
			) $c;"
		);
		update_option( self::OPCION_BD, self::BD, false );
	}

	// ── Leer ────────────────────────────────────────────────────────────

	public static function minimo() {
		return max( 1, (int) apply_filters( 'caracool_vinos_corazones_minimo', self::MINIMO ) );
	}

	public static function activos() {
		$a = Caracool_Vinos::ajustes();
		return isset( $a['favoritos'] ) && 'si' === $a['favoritos'];
	}

	/** Todos los contadores con algo, de más a menos: llave => n. */
	public static function todos() {
		$out = array();
		foreach ( self::filas() as $f ) {
			$out[ (string) $f['llave'] ] = (int) $f['n'];
		}
		return $out;
	}

	/** Las filas con algo, de más a menos, con el vino que eran. */
	public static function filas( $limite = 0 ) {
		global $wpdb;
		$sql   = 'SELECT llave, nombre, bodega, tipo, anada, n FROM ' . self::tabla() . ' WHERE n > 0 ORDER BY n DESC, llave ASC' . ( $limite ? ' LIMIT ' . (int) $limite : '' );
		$filas = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB
		return array_map(
			function ( $f ) {
				$f['n'] = (int) $f['n'];
				return $f;
			},
			(array) $filas
		);
	}

	/** Las llaves de la carta vigente, con su vino. */
	private static function carta_por_llave() {
		$c   = Caracool_Vinos_Datos::actual();
		$out = array();
		if ( $c ) {
			foreach ( $c['vinos'] as $v ) {
				if ( ! empty( $v['llave'] ) && ! isset( $out[ $v['llave'] ] ) ) {
					$out[ $v['llave'] ] = $v;
				}
			}
		}
		return $out;
	}

	/** Lo que ve la página: los vinos de la carta que llegan al mínimo. */
	public static function para_web() {
		if ( ! self::activos() ) {
			return array();
		}
		$en  = self::carta_por_llave();
		$min = self::minimo();
		$out = array();
		foreach ( self::todos() as $llave => $n ) {
			if ( $n >= $min && isset( $en[ $llave ] ) ) {
				$out[ $llave ] = $n;
			}
		}
		return $out;
	}

	/** Lo que el explorador necesita para pedir la cuenta y avisar. */
	public static function config() {
		if ( ! self::activos() ) {
			return null;
		}
		return array(
			'url'    => rest_url( 'caracool-vinos/v1/' ),
			'minimo' => self::minimo(),
			'lista'  => self::MINIMO_LISTA,
		);
	}

	// ── El REST ─────────────────────────────────────────────────────────

	public function rutas() {
		register_rest_route(
			'caracool-vinos/v1',
			'/corazones',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_leer' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'caracool-vinos/v1',
			'/corazon',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_contar' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	private static function respuesta( $datos, $estado = 200 ) {
		$r = new WP_REST_Response( $datos, $estado );
		$r->header( 'Cache-Control', 'no-store' );
		return $r;
	}

	public static function rest_leer( $request ) {
		$n = self::para_web();
		return self::respuesta( array( 'minimo' => self::minimo(), 'lista' => self::MINIMO_LISTA, 'n' => $n ? $n : new stdClass() ) );
	}

	/**
	 * { llaves: [...], sentido: 1 | -1 }. Solo cuentan las llaves de la carta
	 * vigente; lo demás se ignora sin avisar.
	 */
	public static function rest_contar( $request ) {
		if ( ! self::activos() ) {
			return self::respuesta( array( 'codigo' => 'apagado' ), 403 );
		}
		$sentido = (int) $request->get_param( 'sentido' );
		$llaves  = $request->get_param( 'llaves' );
		if ( ( 1 !== $sentido && -1 !== $sentido ) || ! is_array( $llaves ) ) {
			return self::respuesta( array( 'codigo' => 'aviso_invalido' ), 400 );
		}
		$llaves = array_slice( array_values( array_unique( array_filter( $llaves, 'is_string' ) ) ), 0, self::POR_AVISO );
		if ( ! $llaves ) {
			return self::respuesta( array( 'codigo' => 'aviso_invalido' ), 400 );
		}
		if ( ! self::dentro_del_tope() ) {
			return self::respuesta( array( 'codigo' => 'demasiados' ), 429 );
		}
		$en      = self::carta_por_llave();
		$cuantos = 0;
		foreach ( $llaves as $l ) {
			if ( isset( $en[ $l ] ) ) {
				self::sumar( $en[ $l ], $sentido );
				$cuantos++;
			}
		}
		return self::respuesta( array( 'ok' => true, 'contados' => $cuantos ) );
	}

	/**
	 * El tope por dirección. La dirección no se guarda: el nombre del
	 * transitorio es una huella (con la sal de la web) de la dirección y de
	 * la franja de diez minutos, y el transitorio caduca con la franja.
	 */
	private static function dentro_del_tope() {
		$dir = '';
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $k ) {
			if ( ! empty( $_SERVER[ $k ] ) ) { // phpcs:ignore WordPress.Security
				$partes = explode( ',', (string) $_SERVER[ $k ] ); // phpcs:ignore WordPress.Security
				$dir   .= '|' . trim( $partes[0] );
			}
		}
		$franja = (int) floor( time() / ( 10 * MINUTE_IN_SECONDS ) );
		$clave  = 'cv_cz_' . substr( wp_hash( $dir . '|' . $franja ), 0, 24 );
		$n      = (int) get_transient( $clave );
		if ( $n >= self::TOPE ) {
			return false;
		}
		set_transient( $clave, $n + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}

	/** Uno más o uno menos a un vino de la carta, sin bajar de cero. */
	private static function sumar( $v, $sentido ) {
		global $wpdb;
		$t     = self::tabla();
		$ahora = gmdate( 'Y-m-d H:i:s' );
		$datos = array(
			'llave'  => (string) $v['llave'],
			'nombre' => substr( isset( $v['nombre'] ) ? (string) $v['nombre'] : '', 0, 190 ),
			'bodega' => substr( isset( $v['bodega'] ) ? (string) $v['bodega'] : '', 0, 190 ),
			'tipo'   => substr( isset( $v['tipo'] ) ? (string) $v['tipo'] : '', 0, 40 ),
			'anada'  => substr( ! empty( $v['anadas'] ) && is_array( $v['anadas'] ) ? implode( ' / ', $v['anadas'] ) : '', 0, 40 ),
		);
		$subir = function () use ( $wpdb, $t, $datos, $sentido, $ahora ) {
			return $wpdb->query( $wpdb->prepare( "UPDATE {$t} SET n = CASE WHEN n + %d < 0 THEN 0 ELSE n + %d END, ultimo = %s, nombre = %s, bodega = %s, tipo = %s, anada = %s WHERE llave = %s", $sentido, $sentido, $ahora, $datos['nombre'], $datos['bodega'], $datos['tipo'], $datos['anada'], $datos['llave'] ) ); // phpcs:ignore WordPress.DB
		};
		if ( $subir() || $sentido < 0 ) {
			return;
		}
		// la primera vez que alguien guarda este vino
		$callar = method_exists( $wpdb, 'suppress_errors' ) ? $wpdb->suppress_errors( true ) : null;
		try {
			$hecho = $wpdb->insert( $t, array_merge( $datos, array( 'n' => 1, 'primero' => $ahora, 'ultimo' => $ahora ) ) );
		} catch ( Exception $e ) {
			$hecho = false;
		}
		if ( null !== $callar ) {
			$wpdb->suppress_errors( $callar );
		}
		if ( ! $hecho ) {
			$subir(); // otra visita la ha creado a la vez
		}
	}

	// ── A Bodega ────────────────────────────────────────────────────────

	/**
	 * Tras cada comprobación que ha ido bien, manda la cuenta a Bodega si ha
	 * cambiado desde el último envío. Si Bodega no la recoge, se vuelve a
	 * intentar en la siguiente comprobación.
	 */
	public static function enviar( $estado = array() ) {
		if ( empty( $estado['resultado'] ) || ! in_array( $estado['resultado'], array( 'nueva', 'al_dia' ), true ) || ! function_exists( 'caracool_bodega_enviar' ) ) {
			return;
		}
		$filas  = self::filas();
		$huella = md5( wp_json_encode( $filas ) );
		$antes  = self::ultimo_envio();
		if ( ! empty( $antes['ok'] ) && isset( $antes['huella'] ) && $antes['huella'] === $huella ) {
			return;
		}
		$r = caracool_bodega_enviar(
			'corazones/vinos',
			array( 'corazones' => $filas ),
			array( 'version' => CARACOOL_VINOS_VERSION, 'huella' => caracool_huella( CARACOOL_VINOS_DIR ) )
		);
		update_option(
			self::OPCION_ENVIO,
			array(
				'huella' => $huella,
				'ok'     => 200 === (int) $r['codigo'],
				'codigo' => (int) $r['codigo'],
				'vinos'  => count( $filas ),
				'cuando' => time(),
			),
			false
		);
	}

	/** «CAVA» → «Cava»; lo que ya viene escrito con minúsculas se queda. */
	private static function zona( $z ) {
		$z = (string) $z;
		if ( function_exists( 'mb_strtoupper' ) && mb_strtoupper( $z, 'UTF-8' ) === $z ) {
			return mb_convert_case( mb_strtolower( $z, 'UTF-8' ), MB_CASE_TITLE, 'UTF-8' );
		}
		return $z;
	}

	public static function ultimo_envio() {
		$e = get_option( self::OPCION_ENVIO, array() );
		return is_array( $e ) ? $e : array();
	}

	// ── El panel ────────────────────────────────────────────────────────

	public function panel() {
		$todas = self::filas();
		$carta = self::carta_por_llave();
		$min   = self::minimo();
		$total = 0;
		$pasan = 0;
		foreach ( $todas as $f ) {
			$total += $f['n'];
			if ( $f['n'] >= $min && isset( $carta[ $f['llave'] ] ) ) {
				$pasan++;
			}
		}
		$envio = self::ultimo_envio();
		$filas = array_slice( $todas, 0, 50 );
		?>
		<section class="cc-modulo" id="cv-corazones" data-titulo="Corazones">
			<div class="cc-card">
				<div class="cc-card-head">
					<span class="cc-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20.4 4.9 13.3a4.4 4.4 0 0 1 6.2-6.2l.9.9.9-.9a4.4 4.4 0 0 1 6.2 6.2Z"/></svg></span>
					<h2>Los más guardados</h2>
				</div>
				<p class="cc-card-desc">Cuántas personas tienen cada vino en «Mis vinos». Se cuenta sin saber quién: sin cookies, sin cuenta y sin guardar la dirección de nadie. En la página, la cifra sale junto al corazón cuando un vino llega a <?php echo (int) $min; ?> personas, y el botón «Más guardados» aparece cuando hay al menos <?php echo (int) self::MINIMO_LISTA; ?> vinos que llegan.</p>
				<?php if ( ! self::activos() ) : ?>
					<div class="cc-aviso cc-aviso-ojo">«Mis vinos» está apagado en Ajustes: la página no enseña corazones y no se cuenta nada nuevo.</div>
				<?php endif; ?>
				<div class="cc-cifras">
					<div class="cc-cifra"><b><?php echo (int) $total; ?></b><span>corazones</span></div>
					<div class="cc-cifra"><b><?php echo count( $todas ); ?></b><span>vinos guardados</span></div>
					<div class="cc-cifra"><b><?php echo (int) $pasan; ?></b><span>con la cifra a la vista</span></div>
				</div>
				<?php if ( $filas ) : ?>
					<table class="cc-tabla">
						<thead><tr><th>Vino</th><th>Bodega</th><th>Zona</th><th style="text-align:right">Personas</th></tr></thead>
						<tbody>
						<?php foreach ( $filas as $f ) :
							$v = isset( $carta[ $f['llave'] ] ) ? $carta[ $f['llave'] ] : null;
							?>
							<tr>
								<td><b><?php echo esc_html( $v ? $v['nombre'] : $f['nombre'] ); ?></b><?php echo '' !== $f['anada'] ? ' <small>' . esc_html( $f['anada'] ) . '</small>' : ''; ?><?php echo $v ? '' : ' <small>ya no está en la carta</small>'; ?></td>
								<td><?php echo esc_html( $v && isset( $v['bodega'] ) ? $v['bodega'] : $f['bodega'] ); ?></td>
								<td><?php echo esc_html( $v && isset( $v['zona'] ) ? self::zona( $v['zona'] ) : '' ); ?></td>
								<td class="num"><?php echo (int) $f['n']; ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="cc-hint">Todavía nadie ha guardado ningún vino.</p>
				<?php endif; ?>
				<p class="cc-hint">
					<?php
					if ( ! $envio ) {
						echo 'Bodega recibe esta cuenta en cada comprobación de la carta.';
					} elseif ( ! empty( $envio['ok'] ) ) {
						echo 'Bodega recibió la cuenta el ' . esc_html( wp_date( 'j \d\e F \a \l\a\s H:i', (int) $envio['cuando'] ) ) . '.';
					} else {
						echo 'El último envío a Bodega no salió (' . (int) $envio['codigo'] . '). Se repite en la siguiente comprobación.';
					}
					?>
				</p>
			</div>
		</section>
		<?php
	}
}

new Caracool_Vinos_Corazones();
