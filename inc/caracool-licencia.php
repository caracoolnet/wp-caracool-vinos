<?php
/**
 * La licencia de Caracool y la conexión con Bodega.
 * ─────────────────────────────────────────────────────────────────────
 * Solo Caracool Vinos lleva este módulo: Bodega trata únicamente con
 * Vinos. Las funciones van protegidas con function_exists por si algún
 * día otro plugin lo cargara antes.
 *
 * Qué hace:
 *
 *   - Guarda la licencia de esta web (una por web) y la lee.
 *   - Habla con Bodega (bodega.caracool.net): manda la licencia, el
 *     dominio, la versión y la huella de los archivos, y trae lo que toque
 *     (o lleva lo que toque: los corazones de la gente).
 *   - Contesta al eco: cuando Bodega quiere saber si este dominio tiene de
 *     verdad la licencia, le manda un número y hay que devolverlo firmado.
 *   - Recibe el aviso de Bodega cuando hay algo nuevo que recoger y lo
 *     pasa a Vinos (acción `caracool_aviso`).
 *   - Pinta la tarjeta «Licencia» de la pestaña Conexión.
 *
 * En un dominio de desarrollo (localhost, .test, .local) no se exige
 * licencia: Vinos funciona con lo que tenga guardado.
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'CARACOOL_VINOS_CLI' ) && ! defined( 'CARACOOL_BODEGA_CLI' ) ) {
	exit;
}

if ( ! function_exists( 'caracool_licencia' ) ) {

	if ( ! defined( 'CARACOOL_BODEGA_URL_DEFECTO' ) ) {
		define( 'CARACOOL_BODEGA_URL_DEFECTO', 'https://bodega.caracool.net' );
	}
	// La clave pública de Bodega (bodega.caracool.net, par creado el 27 de
	// septiembre de 2026), para verificar la firma de la licencia sin llamar
	// a nadie. No es secreta. Si Bodega reemite su par de claves, hay que
	// copiar aquí la pública nueva y hacer release. Con ella vacía, la
	// licencia se aceptaría por su forma y quien la verifica de verdad es
	// Bodega en cada llamada.
	if ( ! defined( 'CARACOOL_BODEGA_CLAVE_PUBLICA' ) ) {
		define( 'CARACOOL_BODEGA_CLAVE_PUBLICA', 'UKagOEJpQluG7e6Bj4FoTNmPZFI0Xsj2DXzhvhVZO2I=' );
	}

	/** La URL de Bodega: la de siempre, salvo que wp-config o la opción digan otra (un beta de Bodega). */
	function caracool_bodega_url() {
		if ( defined( 'CARACOOL_BODEGA_URL' ) && CARACOOL_BODEGA_URL ) {
			return untrailingslashit( CARACOOL_BODEGA_URL );
		}
		$o = get_option( 'caracool_bodega_url', '' );
		return untrailingslashit( $o ? $o : CARACOOL_BODEGA_URL_DEFECTO );
	}

	/** El texto de la licencia. En wp-config como CARACOOL_LICENCIA, o en la opción. */
	function caracool_licencia() {
		if ( defined( 'CARACOOL_LICENCIA' ) && CARACOOL_LICENCIA ) {
			return trim( CARACOOL_LICENCIA );
		}
		return trim( (string) get_option( 'caracool_licencia', '' ) );
	}

	function caracool_licencia_b64d( $s ) {
		return base64_decode( strtr( $s, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $s ) % 4 ) % 4 ), true );
	}

	/**
	 * Lo que dice la licencia: array( i, c, d, p, e, x ) o null si no hay o
	 * no tiene forma de licencia. Si hay clave pública y libsodium, además
	 * se comprueba la firma.
	 */
	function caracool_licencia_datos( $texto = null ) {
		$texto  = null === $texto ? caracool_licencia() : trim( (string) $texto );
		$partes = explode( '.', $texto );
		if ( 3 !== count( $partes ) || 'CB1' !== $partes[0] ) {
			return null;
		}
		$json  = caracool_licencia_b64d( $partes[1] );
		$firma = caracool_licencia_b64d( $partes[2] );
		if ( false === $json || false === $firma ) {
			return null;
		}
		if ( CARACOOL_BODEGA_CLAVE_PUBLICA && function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			$pub = base64_decode( CARACOOL_BODEGA_CLAVE_PUBLICA );
			if ( strlen( $firma ) !== SODIUM_CRYPTO_SIGN_BYTES || ! sodium_crypto_sign_verify_detached( $firma, $json, $pub ) ) {
				return null;
			}
		}
		$d = json_decode( $json, true );
		return ( is_array( $d ) && ! empty( $d['i'] ) && isset( $d['p'] ) ) ? $d : null;
	}

	/** «https://www.El-Churra.com/algo» → «el-churra.com». */
	function caracool_host( $texto ) {
		$texto = trim( (string) $texto );
		$h     = false !== strpos( $texto, '://' ) ? wp_parse_url( $texto, PHP_URL_HOST ) : preg_replace( '#/.*$#', '', $texto );
		return preg_replace( '/^www\./', '', strtolower( trim( (string) $h ) ) );
	}

	/** El dominio de esta web, como lo entiende Bodega. */
	function caracool_dominio() {
		return caracool_host( home_url() );
	}

	function caracool_es_local( $host = null ) {
		$host = null === $host ? caracool_dominio() : $host;
		return (bool) preg_match( '/(^|\.)(localhost|test|local|invalid)$|^127\.|^10\.|^192\.168\./', $host );
	}

	function caracool_dominio_en_licencia( $dominios, $host ) {
		foreach ( (array) $dominios as $d ) {
			$d = strtolower( trim( $d ) );
			if ( 0 === strpos( $d, '*.' ) ) {
				$raiz = substr( $d, 2 );
				if ( $host === $raiz || substr( $host, -strlen( '.' . $raiz ) ) === '.' . $raiz ) {
					return true;
				}
			} elseif ( caracool_host( $d ) === $host ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * ¿Puede funcionar este plugin en esta web? Con licencia que lo incluya,
	 * para este dominio y en fecha; o en un dominio de desarrollo. Es la
	 * comprobación local; la que manda es la de Bodega cuando se le pide algo.
	 */
	function caracool_licencia_permite( $plugin ) {
		if ( caracool_es_local() ) {
			return true;
		}
		$d = caracool_licencia_datos();
		if ( ! $d ) {
			return false;
		}
		if ( ! in_array( $plugin, (array) $d['p'], true ) ) {
			return false;
		}
		if ( ! empty( $d['x'] ) && $d['x'] < gmdate( 'Y-m-d' ) ) {
			return false;
		}
		return caracool_dominio_en_licencia( isset( $d['d'] ) ? $d['d'] : array(), caracool_dominio() );
	}

	/** Por qué no funciona, en una frase para el panel. */
	function caracool_licencia_estado( $plugin ) {
		if ( caracool_es_local() ) {
			return array( 'ok' => true, 'texto' => 'Dominio de desarrollo: no hace falta licencia.' );
		}
		$t = caracool_licencia();
		if ( ! $t ) {
			return array( 'ok' => false, 'texto' => 'Sin licencia. Pégala aquí: la emite Caracool en Bodega.' );
		}
		$d = caracool_licencia_datos( $t );
		if ( ! $d ) {
			return array( 'ok' => false, 'texto' => 'La licencia no es válida (no tiene la forma esperada o la firma no cuadra).' );
		}
		if ( ! in_array( $plugin, (array) $d['p'], true ) ) {
			return array( 'ok' => false, 'texto' => 'La licencia de ' . $d['c'] . ' no incluye este plugin.' );
		}
		if ( ! empty( $d['x'] ) && $d['x'] < gmdate( 'Y-m-d' ) ) {
			return array( 'ok' => false, 'texto' => 'La licencia caducó el ' . $d['x'] . '.' );
		}
		if ( ! caracool_dominio_en_licencia( isset( $d['d'] ) ? $d['d'] : array(), caracool_dominio() ) ) {
			return array( 'ok' => false, 'texto' => 'La licencia es para ' . implode( ', ', (array) $d['d'] ) . ', y esta web es ' . caracool_dominio() . '.' );
		}
		return array( 'ok' => true, 'texto' => 'Licencia de ' . $d['c'] . ( ! empty( $d['x'] ) ? ', hasta el ' . $d['x'] : ', sin caducidad' ) . '.' );
	}

	function caracool_licencia_guardar( $texto ) {
		$texto = trim( (string) $texto );
		if ( '' !== $texto && ! caracool_licencia_datos( $texto ) ) {
			return false;
		}
		update_option( 'caracool_licencia', $texto, false );
		delete_option( 'caracool_licencia_estado' );
		return true;
	}

	/** La huella de un plugin: el hash de sus archivos de código, ordenados. Bodega la apunta. */
	function caracool_huella( $dir ) {
		$hashes = array();
		$it     = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			$ruta = $f->getPathname();
			if ( false !== strpos( $ruta, DIRECTORY_SEPARATOR . 'pruebas' . DIRECTORY_SEPARATOR ) ) {
				continue;
			}
			if ( preg_match( '/\.(php|js|css)$/', $ruta ) ) {
				$hashes[ substr( $ruta, strlen( $dir ) ) ] = md5_file( $ruta );
			}
		}
		ksort( $hashes );
		return hash( 'sha256', implode( "\n", array_map( function ( $k, $v ) { return $k . ':' . $v; }, array_keys( $hashes ), $hashes ) ) );
	}

	/**
	 * Una petición a Bodega. Devuelve array( codigo, datos, etag, error ).
	 * codigo 0 = no se ha podido llamar (sin red, Bodega caída).
	 */
	function caracool_bodega_pedir( $ruta, $args = array() ) {
		$args = wp_parse_args( $args, array( 'plugin' => '', 'version' => '', 'huella' => '', 'etag' => '', 'timeout' => 15 ) );
		$cab  = array(
			'Accept'             => 'application/json',
			'Authorization'      => 'Bearer ' . caracool_licencia(),
			'X-Caracool-Dominio' => caracool_dominio(),
			'X-Caracool-Version' => $args['version'],
			'X-Caracool-Huella'  => $args['huella'],
		);
		if ( $args['etag'] ) {
			$cab['If-None-Match'] = $args['etag'];
		}
		$r = wp_remote_get( caracool_bodega_url() . '/wp-json/caracool-bodega/v1/' . ltrim( $ruta, '/' ), array( 'timeout' => $args['timeout'], 'headers' => $cab ) );
		if ( is_wp_error( $r ) ) {
			return array( 'codigo' => 0, 'datos' => null, 'etag' => '', 'error' => $r->get_error_message() );
		}
		$codigo = (int) wp_remote_retrieve_response_code( $r );
		$datos  = json_decode( (string) wp_remote_retrieve_body( $r ), true );
		$etag   = (string) wp_remote_retrieve_header( $r, 'etag' );
		$error  = '';
		if ( $codigo >= 400 ) {
			$error = is_array( $datos ) && ! empty( $datos['codigo'] ) ? $datos['codigo'] : 'http_' . $codigo;
		}
		return array( 'codigo' => $codigo, 'datos' => is_array( $datos ) ? $datos : null, 'etag' => $etag, 'error' => $error );
	}

	/**
	 * Lo mismo, pero mandando datos (POST con JSON): los corazones, por
	 * ahora. Devuelve lo mismo que caracool_bodega_pedir().
	 */
	function caracool_bodega_enviar( $ruta, $datos, $args = array() ) {
		$args = wp_parse_args( $args, array( 'version' => '', 'huella' => '', 'timeout' => 15 ) );
		$r    = wp_remote_post(
			caracool_bodega_url() . '/wp-json/caracool-bodega/v1/' . ltrim( $ruta, '/' ),
			array(
				'timeout' => $args['timeout'],
				'headers' => array(
					'Accept'             => 'application/json',
					'Content-Type'       => 'application/json',
					'Authorization'      => 'Bearer ' . caracool_licencia(),
					'X-Caracool-Dominio' => caracool_dominio(),
					'X-Caracool-Version' => $args['version'],
					'X-Caracool-Huella'  => $args['huella'],
				),
				'body'    => wp_json_encode( $datos ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return array( 'codigo' => 0, 'datos' => null, 'error' => $r->get_error_message() );
		}
		$codigo = (int) wp_remote_retrieve_response_code( $r );
		$datos  = json_decode( (string) wp_remote_retrieve_body( $r ), true );
		$error  = '';
		if ( $codigo >= 400 ) {
			$error = is_array( $datos ) && ! empty( $datos['codigo'] ) ? $datos['codigo'] : 'http_' . $codigo;
		}
		return array( 'codigo' => $codigo, 'datos' => is_array( $datos ) ? $datos : null, 'error' => $error );
	}

	// ── Lo que Bodega le pregunta a esta web ────────────────────────────

	add_action(
		'rest_api_init',
		function () {
			register_rest_route(
				'caracool/v1',
				'/eco',
				array(
					'methods'             => 'GET',
					'permission_callback' => '__return_true',
					'callback'            => function ( $request ) {
						$n = (string) $request->get_param( 'n' );
						$l = caracool_licencia();
						if ( ! $l || ! preg_match( '/^[a-f0-9]{16,64}$/', $n ) ) {
							return new WP_REST_Response( array( 'codigo' => 'sin_licencia' ), 404 );
						}
						return new WP_REST_Response( array( 'r' => hash_hmac( 'sha256', $n, $l ) ), 200 );
					},
				)
			);
			register_rest_route(
				'caracool/v1',
				'/aviso',
				array(
					'methods'             => 'POST',
					'permission_callback' => '__return_true',
					'callback'            => function ( $request ) {
						$l = caracool_licencia();
						$p = sanitize_key( (string) $request->get_param( 'plugin' ) );
						$v = (int) $request->get_param( 'version' );
						$t = (int) $request->get_param( 'ts' );
						$f = (string) $request->get_param( 'firma' );
						if ( ! $l || ! $p || abs( time() - $t ) > 10 * MINUTE_IN_SECONDS || ! hash_equals( hash_hmac( 'sha256', $p . '|' . $v . '|' . $t, $l ), $f ) ) {
							return new WP_REST_Response( array( 'codigo' => 'aviso_invalido' ), 403 );
						}
						do_action( 'caracool_aviso', $p, $v );
						return new WP_REST_Response( array( 'ok' => true ), 200 );
					},
				)
			);
		}
	);

	// ── La tarjeta «Licencia» del panel ─────────────────────────────────

	add_action(
		'admin_post_caracool_licencia',
		function () {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( 'Sin permisos.' );
			}
			check_admin_referer( 'caracool_licencia' );
			$volver = isset( $_POST['volver'] ) ? esc_url_raw( wp_unslash( $_POST['volver'] ) ) : admin_url(); // phpcs:ignore WordPress.Security
			$texto  = isset( $_POST['licencia'] ) ? wp_unslash( $_POST['licencia'] ) : ''; // phpcs:ignore WordPress.Security
			$url    = isset( $_POST['bodega_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['bodega_url'] ) ) ) : ''; // phpcs:ignore WordPress.Security
			if ( $url && $url !== CARACOOL_BODEGA_URL_DEFECTO ) {
				update_option( 'caracool_bodega_url', $url, false );
			} else {
				delete_option( 'caracool_bodega_url' );
			}
			if ( ! caracool_licencia_guardar( $texto ) ) {
				wp_safe_redirect( add_query_arg( 'cc_error', rawurlencode( 'Eso no es una licencia de Caracool: tiene que empezar por CB1 y llevar tres partes.' ), $volver ) );
				exit;
			}
			do_action( 'caracool_licencia_guardada' );
			wp_safe_redirect( add_query_arg( 'cc_ok', rawurlencode( '' === trim( $texto ) ? 'Licencia quitada.' : 'Licencia guardada.' ), $volver ) );
			exit;
		}
	);

	/**
	 * Pinta la tarjeta. $plugin es la clave del plugin que la enseña
	 * ('vinos'), para decir si le permite funcionar; $volver, la URL de su
	 * página de ajustes.
	 */
	function caracool_licencia_tarjeta( $plugin, $volver ) {
		$e = caracool_licencia_estado( $plugin );
		$d = caracool_licencia_datos();
		$fija = defined( 'CARACOOL_LICENCIA' ) && CARACOOL_LICENCIA;
		?>
		<div class="cc-card">
			<div class="cc-card-head">
				<span class="cc-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.6 7.6a5.5 5.5 0 1 1-7.8 7.8 5.5 5.5 0 0 1 7.8-7.8zm0 0L19 3.4"/><path d="M15 7l3 3"/></svg></span>
				<h2>Licencia</h2>
			</div>
			<p class="cc-card-desc">Una por web. La emite Caracool en Bodega para este dominio; sin ella Vinos no recibe nada nuevo. Si Bodega no responde, la web sigue con lo último que recibió.</p>
			<div class="cc-aviso <?php echo $e['ok'] ? '' : 'cc-aviso-ojo'; ?>"><?php echo esc_html( $e['texto'] ); ?></div>
			<?php if ( $d ) : ?>
				<div class="cc-campo">
					<label>Cliente</label>
					<div><?php echo esc_html( $d['c'] ); ?><span class="cc-hint">Dominios: <?php echo esc_html( implode( ', ', (array) $d['d'] ) ); ?> · Plugins: <?php echo esc_html( implode( ', ', (array) $d['p'] ) ); ?> · Emitida el <?php echo esc_html( $d['e'] ); ?><?php echo ! empty( $d['x'] ) ? ' · Caduca el ' . esc_html( $d['x'] ) : ''; ?> · Id <code><?php echo esc_html( $d['i'] ); ?></code></span></div>
				</div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="caracool_licencia">
				<input type="hidden" name="volver" value="<?php echo esc_attr( $volver ); ?>">
				<?php wp_nonce_field( 'caracool_licencia' ); ?>
				<div class="cc-campo">
					<label for="cc-licencia">La licencia</label>
					<div>
						<?php if ( $fija ) : ?>
							<p class="cc-hint" style="margin:6px 0 0">Está en <code>wp-config.php</code> (<code>CARACOOL_LICENCIA</code>); se cambia ahí.</p>
						<?php else : ?>
							<textarea id="cc-licencia" name="licencia" rows="4" class="cc-mono" placeholder="CB1.…" spellcheck="false"><?php echo esc_textarea( caracool_licencia() ); ?></textarea>
							<span class="cc-hint">Pégala entera. Para quitarla, déjalo vacío. También puede ir en <code>wp-config.php</code> como <code>CARACOOL_LICENCIA</code>.</span>
						<?php endif; ?>
					</div>
				</div>
				<div class="cc-campo">
					<label for="cc-bodega-url">Bodega</label>
					<div>
						<input type="text" id="cc-bodega-url" name="bodega_url" value="<?php echo esc_attr( caracool_bodega_url() ); ?>" <?php disabled( defined( 'CARACOOL_BODEGA_URL' ) && CARACOOL_BODEGA_URL ); ?>>
						<span class="cc-hint">Normalmente <?php echo esc_html( CARACOOL_BODEGA_URL_DEFECTO ); ?>. Esta web es <code><?php echo esc_html( caracool_dominio() ); ?></code>.</span>
					</div>
				</div>
				<?php if ( ! $fija ) : ?>
				<div class="cc-acciones"><button type="submit" class="cc-btn cc-btn-primario">Guardar</button></div>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}
}
