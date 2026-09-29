<?php
/**
 * Caracool Vinos — Conexión con Bodega
 * ─────────────────────────────────────────────────────────────────────
 * La carta la lee y la publica Caracool en Bodega; este módulo la recoge.
 * Tres maneras de enterarse de que hay una nueva:
 *
 *   1. El aviso: al publicar, Bodega llama a esta web y la carta se recoge
 *      al momento.
 *   2. La comprobación de rutina: dos veces al día, por si el aviso no
 *      llegó. Con el ETag de la que ya tiene, Bodega contesta 304 y no
 *      viaja nada.
 *   3. El botón «Comprobar ahora» del panel.
 *
 * Si Bodega no responde, no pasa nada: la carta que hay se queda. Si
 * Bodega dice que la licencia está revocada, la carta se retira.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Caracool_Vinos_Conexion {

	const OPCION_ESTADO = 'caracool_vinos_conexion';
	const CRON          = 'caracool_vinos_comprobar';

	public function __construct() {
		add_action( 'caracool_vinos_settings_panels', array( $this, 'panel_carta' ), 10 );
		add_action( 'caracool_vinos_settings_panels', array( $this, 'panel_conexion' ), 15 );
		add_action( 'admin_post_caracool_vinos_comprobar', array( $this, 'comprobar_accion' ) );
		add_action( 'caracool_aviso', array( $this, 'aviso' ), 10, 2 );
		add_action( 'caracool_licencia_guardada', array( $this, 'tras_guardar_licencia' ) );
		add_action( self::CRON, array( __CLASS__, 'comprobar' ) );
		add_action( 'init', array( __CLASS__, 'programar' ) );
	}

	// ── Rutina ──────────────────────────────────────────────────────────

	public static function programar() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 10 * MINUTE_IN_SECONDS, 'twicedaily', self::CRON );
		}
	}

	public static function desprogramar() {
		$t = wp_next_scheduled( self::CRON );
		if ( $t ) {
			wp_unschedule_event( $t, self::CRON );
		}
	}

	public function aviso( $plugin, $version ) {
		if ( 'vinos' === $plugin ) {
			self::comprobar();
		}
	}

	public function tras_guardar_licencia() {
		self::comprobar();
	}

	public static function estado() {
		$e = get_option( self::OPCION_ESTADO, array() );
		return is_array( $e ) ? $e : array();
	}

	private static function apuntar( $datos ) {
		$e = array_merge( self::estado(), $datos, array( 'cuando' => time() ) );
		update_option( self::OPCION_ESTADO, $e, false );
		// tras cada comprobación (los corazones aprovechan para ir a Bodega)
		do_action( 'caracool_vinos_comprobado', $e );
		return $e;
	}

	/**
	 * Pide la carta a Bodega y la guarda si hay una nueva. Devuelve el
	 * estado que se apunta: resultado ('nueva', 'al_dia', 'sin_carta',
	 * 'sin_licencia', 'rechazada', 'sin_respuesta') y un texto para el panel.
	 */
	public static function comprobar() {
		if ( ! caracool_licencia() && ! caracool_es_local() ) {
			return self::apuntar( array( 'resultado' => 'sin_licencia', 'texto' => 'Sin licencia: no se puede pedir la carta.' ) );
		}
		$r = caracool_bodega_pedir(
			'carta/vinos',
			array(
				'plugin'  => 'vinos',
				'version' => CARACOOL_VINOS_VERSION,
				'huella'  => caracool_huella( CARACOOL_VINOS_DIR ),
				'etag'    => Caracool_Vinos_Datos::etag(),
			)
		);
		if ( 0 === $r['codigo'] ) {
			return self::apuntar( array( 'resultado' => 'sin_respuesta', 'texto' => 'Bodega no ha respondido (' . $r['error'] . '). La carta que hay se queda.' ) );
		}
		if ( 304 === $r['codigo'] ) {
			return self::apuntar( array( 'resultado' => 'al_dia', 'texto' => 'La carta está al día.' ) );
		}
		if ( 200 === $r['codigo'] && $r['datos'] && isset( $r['datos']['carta'] ) ) {
			$nueva = Caracool_Vinos_Datos::recibir( $r['datos']['carta'], isset( $r['datos']['etag'] ) ? $r['datos']['etag'] : $r['etag'] );
			$c     = Caracool_Vinos_Datos::actual();
			return self::apuntar( array( 'resultado' => $nueva ? 'nueva' : 'al_dia', 'texto' => ( $nueva ? 'Carta nueva recibida: ' : 'La carta está al día: ' ) . 'la del ' . Caracool_Vinos_Datos::fecha_legible( $c['fecha'] ) . ', ' . count( $c['vinos'] ) . ' vinos (versión ' . (int) $c['version'] . ').' ) );
		}
		if ( 404 === $r['codigo'] ) {
			return self::apuntar( array( 'resultado' => 'sin_carta', 'texto' => 'Bodega todavía no tiene carta publicada para esta web.' ) );
		}
		$textos = array(
			'sin_licencia'         => 'Bodega no ha recibido la licencia.',
			'licencia_invalida'    => 'Bodega no reconoce esta licencia: pega la que te haya dado Caracool.',
			'revocada'             => 'La licencia está revocada. La carta se ha retirado de la web.',
			'caducada'             => 'La licencia ha caducado. Caracool la renueva en Bodega.',
			'plugin_no_incluido'   => 'La licencia no incluye Caracool Vinos.',
			'dominio_no_permitido' => 'La licencia no es para este dominio (' . caracool_dominio() . ').',
			'eco_fallido'          => 'Bodega no ha podido comprobar este dominio (el eco). ¿Está la API REST de la web abierta desde fuera?',
		);
		if ( 'revocada' === $r['error'] ) {
			Caracool_Vinos_Datos::vaciar();
		}
		return self::apuntar( array( 'resultado' => 'rechazada', 'codigo' => $r['error'], 'texto' => isset( $textos[ $r['error'] ] ) ? $textos[ $r['error'] ] : 'Bodega ha contestado ' . $r['codigo'] . ' (' . $r['error'] . ').' ) );
	}

	public function comprobar_accion() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sin permisos.' );
		}
		check_admin_referer( 'caracool_vinos_comprobar' );
		$e = self::comprobar();
		$arg = in_array( $e['resultado'], array( 'nueva', 'al_dia' ), true ) ? 'cv_ok' : 'cv_error';
		wp_safe_redirect( add_query_arg( $arg, rawurlencode( $e['texto'] ), admin_url( 'admin.php?page=caracool-vinos' ) ) . '#cv-carta' );
		exit;
	}

	// ── Pantallas ───────────────────────────────────────────────────────

	public function panel_carta() {
		$c = Caracool_Vinos_Datos::actual();
		$e = self::estado();
		?>
		<section class="cc-modulo" id="cv-carta" data-titulo="Carta">
			<div class="cc-card">
				<div class="cc-card-head">
					<span class="cc-card-icon"><?php echo self::icono( 'copa' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<h2><?php echo $c ? 'Carta del ' . esc_html( Caracool_Vinos_Datos::fecha_legible( $c['fecha'] ) ) : 'Todavía no hay carta'; ?></h2>
				</div>
				<?php if ( $c ) :
					$vinos  = $c['vinos'];
					$paises = Caracool_Vinos_Datos::contar( $vinos, 'pais' );
					$zonas  = Caracool_Vinos_Datos::contar( $vinos, 'zona' );
					$bodeg  = Caracool_Vinos_Datos::contar( $vinos, 'bodega' );
					unset( $paises['(sin)'], $zonas['(sin)'], $bodeg['(sin)'] );
					?>
					<p class="cc-card-desc">Versión <?php echo (int) $c['version']; ?>, publicada en Bodega el <?php echo esc_html( wp_date( 'j \d\e F \d\e Y \a \l\a\s H:i', (int) $c['publicada'] ) ); ?> y recibida aquí el <?php echo esc_html( wp_date( 'j \d\e F \a \l\a\s H:i', (int) $c['recibida'] ) ); ?>.<?php echo ! empty( $c['nuevos'] ) ? ' Trajo ' . count( $c['nuevos'] ) . ' vinos nuevos y ' . count( isset( $c['precio'] ) ? $c['precio'] : array() ) . ' cambios de precio.' : ''; ?><?php echo ! empty( $c['pdf'] ) ? ' <a href="' . esc_url( $c['pdf'] ) . '">PDF de la carta</a>.' : ''; ?></p>
					<div class="cc-cifras">
						<div class="cc-cifra"><b><?php echo count( $vinos ); ?></b><span>vinos</span></div>
						<div class="cc-cifra"><b><?php echo count( $paises ); ?></b><span>países</span></div>
						<div class="cc-cifra"><b><?php echo count( $zonas ); ?></b><span>zonas</span></div>
						<div class="cc-cifra"><b><?php echo count( $bodeg ); ?></b><span>bodegas</span></div>
					</div>
				<?php else : ?>
					<p class="cc-card-desc">La carta la lee y la publica Caracool en Bodega; en cuanto haya una para esta web, llega sola. Mientras, la página enseña el texto de «Si no hay carta» de los ajustes.</p>
				<?php endif; ?>
				<?php if ( $e ) : ?>
					<div class="cc-aviso <?php echo in_array( $e['resultado'], array( 'nueva', 'al_dia' ), true ) ? '' : 'cc-aviso-ojo'; ?>">Última comprobación, <?php echo esc_html( wp_date( 'j \d\e F \a \l\a\s H:i', (int) $e['cuando'] ) ); ?>: <?php echo esc_html( $e['texto'] ); ?></div>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cc-acciones">
					<input type="hidden" name="action" value="caracool_vinos_comprobar">
					<?php wp_nonce_field( 'caracool_vinos_comprobar' ); ?>
					<button type="submit" class="cc-btn cc-btn-primario">Comprobar ahora</button>
					<span class="cc-hint" style="margin:0">Se comprueba sola dos veces al día, y al momento cuando Bodega avisa de una carta nueva.</span>
				</form>
			</div>

			<div class="cc-card">
				<div class="cc-card-head">
					<span class="cc-card-icon"><?php echo self::icono( 'pagina' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<h2>Cómo se pone en la página</h2>
				</div>
				<p class="cc-card-desc">En Elementor, el widget <b>Explorar los vinos</b> (busca «vinos»); en su pestaña Estilo se eligen los colores (enlazados al Kit), las letras y la escena de día o de noche. En cualquier otro sitio, el shortcode <code>[caracool_vinos]</code>.</p>
			</div>
		</section>
		<?php
	}

	public function panel_conexion() {
		?>
		<section class="cc-modulo" id="cv-conexion" data-titulo="Conexión" <?php echo caracool_licencia_permite( 'vinos' ) ? '' : 'data-aviso="Sin licencia en vigor"'; ?>>
			<?php caracool_licencia_tarjeta( 'vinos', admin_url( 'admin.php?page=caracool-vinos' ) . '#cv-conexion' ); ?>
		</section>
		<?php
	}

	private static function icono( $cual ) {
		$svg = array(
			'copa'   => '<path d="M8 22h8"/><path d="M12 15v7"/><path d="M12 15a5 5 0 0 0 5-5c0-2-.5-4-2-8H9c-1.5 4-2 6-2 8a5 5 0 0 0 5 5Z"/>',
			'pagina' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/>',
		);
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $svg[ $cual ] . '</svg>';
	}
}

new Caracool_Vinos_Conexion();
