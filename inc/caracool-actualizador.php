<?php
/**
 * Actualizador compartido de los plugins de Caracool (versión 2).
 *
 * ─────────────────────────────────────────────────────────────────────
 * ARCHIVO COMÚN. Es el mismo, byte a byte, en todos los plugins de la casa
 * (Vinos, Eventos, Motion, OneStep, Nueva Visión, HostPV, Toneles, Bono
 * regalo, Carta, Churra…). Fuente de verdad:
 * github.com/caracoolnet/wp-caracool-shared, carpeta inc/. Antes de publicar
 * una versión de cualquier plugin, se compara esta copia con la de ese repo
 * y se sustituye si no coincide.
 *
 * Qué hace: cada plugin se apunta con su slug y su versión, y el
 * actualizador le pregunta a Caracool Central (updates.caracool.net) si hay
 * una más nueva, con la licencia de la web. Central le dice qué versión hay
 * y le da un enlace al zip; WordPress lo enseña y lo instala como cualquier
 * actualización.
 *
 * Zips firmados: cada release lleva al lado del zip un .sig con la firma
 * Ed25519 de «CCZ1|slug|versión|sha256 del zip», hecha con la clave de
 * publicación de Caracool. Esa clave no está en Central ni en GitHub. Antes
 * de instalar, este archivo baja el zip, calcula su sha256 y comprueba la
 * firma con la clave pública de abajo. Si no cuadra, no se instala nada.
 * Así, aunque alguien entrase en Central o en GitHub, no podría colar un zip
 * en las webs. Una versión sin firma no se ofrece.
 *
 * Mientras la web no tenga licencia, pregunta a GitHub como antes (solo
 * sirve con los repositorios públicos), y también exige la firma.
 *
 * La licencia es una por web y la usan todos los plugins de la casa: va en
 * la opción `caracool_licencia` (la misma que usa Vinos) o en wp-config.php
 * como CARACOOL_LICENCIA. Se pega en el menú Caracool → Licencia.
 *
 * Por qué «V2»: la 1.0.0 se llamaba Caracool_Actualizador y no comprobaba
 * firmas. Mientras una web tenga plugins con las dos, cada una se ocupa de
 * los suyos y la nueva no se queda sin cargar porque otro plugin cargase la
 * antigua antes.
 *
 * Cómo se usa desde el plugin:
 *
 *   require_once … 'inc/caracool-actualizador.php';
 *
 *   caracool_actualizador_v2( array(
 *       'archivo' => __FILE__,                       // el archivo principal
 *       'slug'    => 'eventos',                      // el de Central
 *       'version' => CARACOOL_EVENTOS_VERSION,
 *       'repo'    => 'caracoolnet/wp-caracool-eventos',
 *       'nombre'  => 'Caracool Eventos',
 *       'resumen' => 'Conciertos y eventos…',        // para «Ver detalles»
 *   ) );
 * ─────────────────────────────────────────────────────────────────────
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'CARACOOL_MENU_SLUG' ) ) {
	define( 'CARACOOL_MENU_SLUG', 'caracool' );
}

if ( ! class_exists( 'Caracool_Actualizador_V2' ) ) {

	final class Caracool_Actualizador_V2 {

		const VERSION = '1.1.0';
		const CENTRAL = 'https://updates.caracool.net';
		const PAGINA  = 'caracool-licencia';

		/** Clave pública de publicación (Ed25519, base64). La privada no sale de Caracool. */
		const CLAVE_PUBLICACION = 'uQrVKTcl/KZlEhRsA2K3Jy0yNQdHb3WFiAPr2DQftEo=';

		/** Los plugins apuntados: basename => datos. */
		private static $plugins = array();

		private static $enganchado = false;

		public static function registrar( $p ) {
			if ( empty( $p['archivo'] ) || empty( $p['slug'] ) || empty( $p['version'] ) ) {
				return;
			}
			$base                   = plugin_basename( $p['archivo'] );
			$p['base']              = $base;
			$p['carpeta']           = dirname( $base );
			$p['nombre']            = isset( $p['nombre'] ) ? $p['nombre'] : $p['slug'];
			$p['resumen']           = isset( $p['resumen'] ) ? $p['resumen'] : '';
			$p['repo']              = isset( $p['repo'] ) ? $p['repo'] : '';
			self::$plugins[ $base ] = $p;
			self::enganchar();
		}

		private static function enganchar() {
			if ( self::$enganchado ) {
				return;
			}
			self::$enganchado = true;
			add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'comprobar' ) );
			add_filter( 'plugins_api', array( __CLASS__, 'detalles' ), 20, 3 );
			add_filter( 'upgrader_pre_download', array( __CLASS__, 'bajar_y_comprobar' ), 5, 3 );
			add_action( 'upgrader_process_complete', array( __CLASS__, 'olvidar_todo' ), 10, 0 );
			// Antes que la 1.0.0 (prioridad 20), para que la página «Licencia» sea esta.
			add_action( 'admin_menu', array( __CLASS__, 'menu' ), 19 );
			add_action( 'admin_post_caracool_actualizador_licencia_v2', array( __CLASS__, 'guardar' ) );
		}

		// ── La licencia ─────────────────────────────────────────────────

		public static function licencia() {
			if ( defined( 'CARACOOL_LICENCIA' ) && CARACOOL_LICENCIA ) {
				return trim( CARACOOL_LICENCIA );
			}
			return trim( (string) get_option( 'caracool_licencia', '' ) );
		}

		/** Lo que dice la licencia (sin comprobar la firma; quien la comprueba es Central). */
		public static function licencia_datos( $texto = null ) {
			$p = explode( '.', null === $texto ? self::licencia() : trim( (string) $texto ) );
			if ( 3 !== count( $p ) || 'CB1' !== $p[0] ) {
				return null;
			}
			$json = base64_decode( strtr( $p[1], '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $p[1] ) % 4 ) % 4 ), true );
			$d    = false === $json ? null : json_decode( $json, true );
			return ( is_array( $d ) && ! empty( $d['i'] ) ) ? $d : null;
		}

		/** La licencia recortada, para enseñarla sin que se pueda copiar entera. */
		public static function recortada( $texto ) {
			$texto = (string) $texto;
			return strlen( $texto ) > 24 ? substr( $texto, 0, 10 ) . '…' . substr( $texto, -6 ) : $texto;
		}

		public static function central() {
			if ( defined( 'CARACOOL_CENTRAL_URL' ) && CARACOOL_CENTRAL_URL ) {
				return untrailingslashit( CARACOOL_CENTRAL_URL );
			}
			return self::CENTRAL;
		}

		private static function dominio() {
			$h = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
			return preg_replace( '/^www\./', '', $h );
		}

		// ── La firma de los zips ────────────────────────────────────────

		/**
		 * ¿El archivo es el zip de esa versión, firmado con la clave de
		 * publicación? true, o un texto con el motivo.
		 */
		public static function comprobar_firma( $archivo, $slug, $version, $firma ) {
			if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
				return 'Este servidor no tiene sodium: no se pueden comprobar las firmas.';
			}
			$f = base64_decode( (string) $firma, true );
			if ( false === $f || SODIUM_CRYPTO_SIGN_BYTES !== strlen( $f ) ) {
				return 'El paquete no trae firma.';
			}
			$sha = is_file( $archivo ) ? hash_file( 'sha256', $archivo ) : '';
			if ( ! $sha ) {
				return 'No se ha podido leer el paquete.';
			}
			$mensaje = 'CCZ1|' . $slug . '|' . ltrim( (string) $version, 'vV' ) . '|' . $sha;
			$ok      = sodium_crypto_sign_verify_detached( $f, $mensaje, base64_decode( self::CLAVE_PUBLICACION ) );
			return $ok ? true : 'La firma no corresponde a este paquete.';
		}

		// ── Preguntar ───────────────────────────────────────────────────

		private static function clave( $p ) {
			return 'caracool_act2_' . md5( $p['slug'] . '|' . $p['base'] );
		}

		/**
		 * Lo último de un plugin: array( version, paquete, firma, notas, de )
		 * o array( de, error ) si no hay nada que ofrecer. «de» dice a quién se
		 * ha preguntado (central o github). Se guarda 6 horas; un fallo, 30
		 * minutos.
		 */
		public static function ultima( $p, $fresca = false ) {
			$clave = self::clave( $p );
			if ( ! $fresca ) {
				$c = get_site_transient( $clave );
				if ( is_array( $c ) ) {
					return $c;
				}
			}
			$lic = self::licencia();
			$r   = '' !== $lic ? self::preguntar_central( $p, $lic ) : self::preguntar_github( $p );
			set_site_transient( $clave, $r, ! empty( $r['version'] ) ? 6 * HOUR_IN_SECONDS : 30 * MINUTE_IN_SECONDS );
			return $r;
		}

		private static function preguntar_central( $p, $lic ) {
			$r = wp_remote_get(
				self::central() . '/wp-json/caracool-central/v1/plugin/' . rawurlencode( $p['slug'] ),
				array(
					'timeout' => 10,
					'headers' => array(
						'Authorization'      => 'Bearer ' . $lic,
						'X-Caracool-Dominio' => self::dominio(),
						'X-Caracool-Version' => $p['version'],
						'X-Caracool-Act'     => self::VERSION,
						'Accept'             => 'application/json',
					),
				)
			);
			if ( is_wp_error( $r ) ) {
				return array( 'de' => 'central', 'error' => $r->get_error_message() );
			}
			$codigo = (int) wp_remote_retrieve_response_code( $r );
			$d      = json_decode( wp_remote_retrieve_body( $r ), true );
			if ( 200 !== $codigo || ! is_array( $d ) || empty( $d['version'] ) ) {
				$msg = is_array( $d ) && ! empty( $d['mensaje'] ) ? $d['mensaje'] : 'Central ha contestado ' . $codigo . '.';
				return array( 'de' => 'central', 'error' => $msg, 'codigo' => is_array( $d ) && isset( $d['codigo'] ) ? $d['codigo'] : (string) $codigo );
			}
			return array(
				'de'      => 'central',
				'version' => (string) $d['version'],
				'paquete' => isset( $d['paquete'] ) ? (string) $d['paquete'] : '',
				'firma'   => isset( $d['firma'] ) ? (string) $d['firma'] : '',
				'notas'   => isset( $d['notas'] ) ? (string) $d['notas'] : '',
			);
		}

		/** Sin licencia: la release pública de GitHub, como antes, con su .sig. */
		private static function preguntar_github( $p ) {
			if ( '' === $p['repo'] ) {
				return array( 'de' => 'github', 'error' => 'Sin licencia y sin repositorio.' );
			}
			$cab = array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'Caracool/' . self::VERSION );
			$r   = wp_remote_get( 'https://api.github.com/repos/' . $p['repo'] . '/releases/latest', array( 'timeout' => 8, 'headers' => $cab ) );
			if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
				return array( 'de' => 'github', 'error' => is_wp_error( $r ) ? $r->get_error_message() : 'GitHub ha contestado ' . (int) wp_remote_retrieve_response_code( $r ) . ' (si el repositorio es privado, hace falta la licencia).' );
			}
			$d = json_decode( wp_remote_retrieve_body( $r ), true );
			if ( ! is_array( $d ) || empty( $d['tag_name'] ) ) {
				return array( 'de' => 'github', 'error' => 'GitHub no dice la versión.' );
			}
			$zip    = '';
			$nombre = '';
			$sigs   = array();
			foreach ( isset( $d['assets'] ) ? (array) $d['assets'] : array() as $a ) {
				if ( ! isset( $a['name'], $a['browser_download_url'] ) ) {
					continue;
				}
				$n = strtolower( $a['name'] );
				if ( '' === $zip && '.zip' === substr( $n, -4 ) ) {
					$zip    = $a['browser_download_url'];
					$nombre = $a['name'];
				} elseif ( '.zip.sig' === substr( $n, -8 ) ) {
					$sigs[ $a['name'] ] = $a['browser_download_url'];
				}
			}
			$firma = '';
			if ( $nombre && isset( $sigs[ $nombre . '.sig' ] ) ) {
				$s = wp_remote_get( $sigs[ $nombre . '.sig' ], array( 'timeout' => 8, 'headers' => array( 'User-Agent' => 'Caracool/' . self::VERSION ) ) );
				if ( ! is_wp_error( $s ) && 200 === (int) wp_remote_retrieve_response_code( $s ) ) {
					$firma = trim( (string) wp_remote_retrieve_body( $s ) );
				}
			}
			return array(
				'de'      => 'github',
				'version' => ltrim( (string) $d['tag_name'], 'vV' ),
				'paquete' => $zip,
				'firma'   => $firma,
				'notas'   => isset( $d['body'] ) ? (string) $d['body'] : '',
			);
		}

		// ── Lo que lee WordPress ────────────────────────────────────────

		public static function comprobar( $transient ) {
			if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
				return $transient;
			}
			foreach ( self::$plugins as $base => $p ) {
				if ( ! isset( $transient->checked[ $base ] ) ) {
					continue;
				}
				$u = self::ultima( $p );
				// Sin paquete o sin firma no se ofrece nada.
				if ( empty( $u['version'] ) || empty( $u['paquete'] ) || empty( $u['firma'] ) || version_compare( $u['version'], $p['version'], '<=' ) ) {
					if ( isset( $transient->response[ $base ] ) && ! empty( $transient->response[ $base ]->caracool ) ) {
						unset( $transient->response[ $base ] );
					}
					continue;
				}
				$item                         = new stdClass();
				$item->slug                   = $p['carpeta'];
				$item->plugin                 = $base;
				$item->new_version            = $u['version'];
				$item->url                    = 'https://caracool.net';
				$item->package                = $u['paquete'];
				$item->tested                 = get_bloginfo( 'version' );
				$item->caracool               = true;
				$transient->response[ $base ] = $item;
			}
			return $transient;
		}

		public static function detalles( $res, $action, $args ) {
			if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
				return $res;
			}
			foreach ( self::$plugins as $p ) {
				if ( $p['carpeta'] !== $args->slug ) {
					continue;
				}
				$u              = self::ultima( $p );
				$info           = new stdClass();
				$info->name     = $p['nombre'];
				$info->slug     = $p['carpeta'];
				$info->version  = ! empty( $u['version'] ) ? $u['version'] : $p['version'];
				$info->author   = '<a href="https://caracool.net">Caracool</a>';
				$info->homepage = 'https://caracool.net';
				$info->sections = array(
					'description' => esc_html( $p['resumen'] ),
					'changelog'   => ! empty( $u['notas'] ) ? nl2br( esc_html( $u['notas'] ) ) : 'Sin notas.',
				);
				if ( ! empty( $u['paquete'] ) && ! empty( $u['firma'] ) ) {
					$info->download_link = $u['paquete'];
				}
				return $info;
			}
			return $res;
		}

		/** El plugin al que pertenece un paquete, o null si no es de los nuestros. */
		private static function plugin_del_paquete( $package ) {
			foreach ( self::$plugins as $p ) {
				if ( false !== strpos( $package, '/caracool-central/v1/paquete/' . $p['slug'] . '/' ) ) {
					return $p;
				}
				$u = get_site_transient( self::clave( $p ) );
				if ( is_array( $u ) && ! empty( $u['paquete'] ) && $u['paquete'] === $package ) {
					return $p;
				}
			}
			return null;
		}

		/**
		 * Justo antes de instalar: se baja el zip aquí (con un enlace fresco
		 * de Central, que caduca en una hora), se comprueba la firma y solo
		 * entonces se le da a WordPress. Si la firma no cuadra, se borra el
		 * archivo y WordPress se queda sin nada que instalar.
		 */
		public static function bajar_y_comprobar( $reply, $package, $upgrader = null ) {
			if ( false !== $reply || ! is_string( $package ) || '' === $package ) {
				return $reply;
			}
			$p = self::plugin_del_paquete( $package );
			if ( ! $p ) {
				return $reply;
			}
			$u = self::ultima( $p, true );
			if ( empty( $u['paquete'] ) || empty( $u['version'] ) ) {
				return new WP_Error( 'caracool_sin_paquete', 'Caracool: no hay paquete para ' . $p['nombre'] . ( ! empty( $u['error'] ) ? ' (' . $u['error'] . ')' : '' ) . '.' );
			}
			if ( empty( $u['firma'] ) ) {
				return new WP_Error( 'caracool_sin_firma', 'Caracool: la versión ' . $u['version'] . ' de ' . $p['nombre'] . ' no viene firmada. No se instala.' );
			}
			if ( ! function_exists( 'download_url' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			$archivo = download_url( $u['paquete'], 300 );
			if ( is_wp_error( $archivo ) ) {
				return $archivo;
			}
			$ok = self::comprobar_firma( $archivo, $p['slug'], $u['version'], $u['firma'] );
			if ( true !== $ok ) {
				wp_delete_file( $archivo );
				return new WP_Error( 'caracool_firma', 'Caracool: ' . $ok . ' No se ha instalado ' . $p['nombre'] . ' ' . $u['version'] . '.' );
			}
			return $archivo;
		}

		public static function olvidar_todo() {
			foreach ( self::$plugins as $p ) {
				delete_site_transient( self::clave( $p ) );
			}
		}

		// ── La página «Licencia» del menú Caracool ──────────────────────

		public static function menu() {
			global $submenu;
			if ( isset( $submenu[ CARACOOL_MENU_SLUG ] ) ) {
				foreach ( $submenu[ CARACOOL_MENU_SLUG ] as $s ) {
					if ( isset( $s[2] ) && self::PAGINA === $s[2] ) {
						return;
					}
				}
			}
			add_submenu_page( CARACOOL_MENU_SLUG, 'Licencia de Caracool', 'Licencia', 'manage_options', self::PAGINA, array( __CLASS__, 'pagina' ) );
		}

		public static function guardar() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( 'Sin permisos.' );
			}
			check_admin_referer( 'caracool_actualizador_licencia_v2' );
			$que   = isset( $_POST['que'] ) ? sanitize_key( wp_unslash( $_POST['que'] ) ) : 'guardar';
			$texto = isset( $_POST['licencia'] ) ? trim( preg_replace( '/\s+/', '', sanitize_textarea_field( wp_unslash( $_POST['licencia'] ) ) ) ) : '';
			$msg   = '';
			if ( 'guardar' === $que ) {
				if ( '' === $texto ) {
					$msg = 'error:No has pegado ninguna licencia.';
				} elseif ( ! self::licencia_datos( $texto ) ) {
					$msg = 'error:Eso no parece una licencia de Caracool (empieza por CB1.).';
				} else {
					update_option( 'caracool_licencia', $texto, false );
					$msg = 'ok:Licencia guardada.';
				}
			} elseif ( 'quitar' === $que ) {
				update_option( 'caracool_licencia', '', false );
				$msg = 'ok:Licencia quitada.';
			}
			self::olvidar_todo();
			delete_site_transient( 'update_plugins' );
			foreach ( self::$plugins as $p ) {
				self::ultima( $p, true );
			}
			if ( 'comprobar' === $que ) {
				$msg = 'ok:Comprobado ahora.';
			}
			wp_safe_redirect( add_query_arg( 'caracool_msg', rawurlencode( $msg ), admin_url( 'admin.php?page=' . self::PAGINA ) ) );
			exit;
		}

		public static function pagina() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			$msg   = isset( $_GET['caracool_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['caracool_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$lic   = self::licencia();
			$datos = self::licencia_datos();
			$conf  = defined( 'CARACOOL_LICENCIA' ) && CARACOOL_LICENCIA;
			?>
			<div class="wrap">
				<h1>Licencia de Caracool</h1>
				<?php if ( $msg ) : ?>
					<div class="notice <?php echo 0 === strpos( $msg, 'error:' ) ? 'notice-error' : 'notice-success'; ?>"><p><?php echo esc_html( preg_replace( '/^(ok|error):/', '', $msg ) ); ?></p></div>
				<?php endif; ?>
				<p style="max-width:720px">Una licencia por web. La usan todos los plugins de la casa para recibir sus actualizaciones desde Caracool Central. Se pega una sola vez; si la web tiene Caracool Vinos, es la misma que lleva en Vinos → Conexión. Cada actualización viene firmada por Caracool y se comprueba antes de instalarla.</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Ahora</th>
						<td>
							<?php if ( $datos ) : ?>
								<strong><?php echo esc_html( $datos['c'] ); ?></strong><br>
								<?php echo esc_html( implode( ', ', isset( $datos['d'] ) ? (array) $datos['d'] : array() ) ); ?><br>
								<code><?php echo esc_html( self::recortada( $lic ) ); ?></code><br>
								<span style="color:#646970"><?php echo esc_html( ! empty( $datos['x'] ) ? 'Hasta el ' . $datos['x'] : 'Sin caducidad' ); ?><?php echo $conf ? ' · en wp-config.php' : ''; ?></span>
							<?php else : ?>
								Sin licencia: los plugins de la casa preguntan a GitHub, y si su repositorio es privado no recibirán actualizaciones.
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="caracool_actualizador_licencia_v2">
					<?php wp_nonce_field( 'caracool_actualizador_licencia_v2' ); ?>
					<?php if ( ! $conf ) : ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="caracool-licencia"><?php echo $datos ? 'Cambiarla' : 'Licencia'; ?></label></th>
							<td><textarea id="caracool-licencia" name="licencia" rows="4" class="large-text code" placeholder="<?php echo esc_attr( $datos ? 'Pega aquí la licencia nueva para cambiarla' : 'CB1.…' ); ?>" autocomplete="off"></textarea>
							<p class="description">La que da Caracool para esta web. Empieza por <code>CB1.</code> Por seguridad, la guardada no se enseña entera.</p></td>
						</tr>
					</table>
					<?php endif; ?>
					<p>
						<?php if ( ! $conf ) : ?>
							<button type="submit" name="que" value="guardar" class="button button-primary">Guardar la licencia</button>
						<?php endif; ?>
						<button type="submit" name="que" value="comprobar" class="button">Comprobar ahora</button>
						<?php if ( ! $conf && $datos ) : ?>
							<button type="submit" name="que" value="quitar" class="button-link button-link-delete" style="margin-left:12px" onclick="return confirm('¿Quitar la licencia? Los plugins de la casa dejarán de recibir actualizaciones.')">Quitar la licencia</button>
						<?php endif; ?>
					</p>
				</form>

				<h2>Los plugins de la casa en esta web</h2>
				<table class="widefat striped" style="max-width:860px">
					<thead><tr><th>Plugin</th><th>Tiene</th><th>Última</th><th>Pregunta a</th></tr></thead>
					<tbody>
					<?php foreach ( self::$plugins as $p ) : ?>
						<?php $u = self::ultima( $p ); ?>
						<tr>
							<td><strong><?php echo esc_html( $p['nombre'] ); ?></strong></td>
							<td><?php echo esc_html( $p['version'] ); ?></td>
							<td>
								<?php if ( ! empty( $u['version'] ) ) : ?>
									<?php echo esc_html( $u['version'] ); ?><?php echo empty( $u['firma'] ) ? ' <span style="color:#b32d2e">(sin firma: no se instalará)</span>' : ''; ?>
								<?php else : ?>
									<span style="color:#b32d2e"><?php echo esc_html( isset( $u['error'] ) ? $u['error'] : '—' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( isset( $u['de'] ) && 'central' === $u['de'] ? 'Caracool Central' : 'GitHub (sin licencia)' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php
		}
	}
}

if ( ! function_exists( 'caracool_actualizador_v2' ) ) {
	/** Apunta un plugin de la casa al actualizador (con firma). */
	function caracool_actualizador_v2( $plugin ) {
		Caracool_Actualizador_V2::registrar( $plugin );
	}
}
