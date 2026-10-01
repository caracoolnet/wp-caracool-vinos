<?php
/**
 * Caracool Vinos — El explorador en la web
 * ─────────────────────────────────────────────────────────────────────
 * El bloque que ve el visitante: el globo, los filtros, la lista y la
 * ficha. Se coloca con el shortcode [caracool_vinos] o con el widget de
 * Elementor «Explorar los vinos», en la página que cada web elija: en las
 * demás el plugin no lee ni carga nada (ni la carta, ni los ajustes, ni
 * scripts ni hojas). Tiene dos diseños, que se eligen en Ajustes:
 * «columnas» (el globo a la izquierda, debajo del hero de foto de la
 * página) y «mundo» (el globo llena la pantalla y hace de hero, con la
 * lista flotando encima). En «mundo» la hoja apaga cualquier elemento de
 * la página con la clase cv-hero-foto, y lo decide el propio bloque
 * (body:has(.cv-explorador[data-diseno=mundo])), sin tocar el body desde
 * PHP: así el hero de foto de Elementor se queda para «columnas» sin tocar
 * la página al cambiar de diseño.
 *
 * El PHP pinta el esqueleto (modules/cv-bloque.php, generado desde el
 * laboratorio) y deja los datos en un JSON dentro del propio bloque; el
 * JavaScript (assets/cv-explorador.js) hace todo lo demás en el navegador.
 * La geometría del mundo (dos JSON, unos 360 KB) se pide aparte con fetch
 * para que se quede en la caché del navegador entre visitas.
 *
 * Los colores y las letras son los del Kit de Elementor: la hoja usa
 * var(--e-global-color-*) y var(--e-global-typography-*) con el valor de
 * El Churra de reserva. Las esquinas, si Ajustes dice «las de la web»,
 * también: se leen del Estilo del tema del Kit (botones y campos de
 * formulario) y viajan en un <style> pequeño junto al bloque.
 *
 * El panel de filtros tiene tres pieles sobre la misma estructura
 * (mosaico, píldoras, ficha), en data-panel.
 *
 * Si la web tiene «Tres toques», el explorador lleva además el botón
 * «Ayúdame a elegir», que abre las tres preguntas en una capa encima (ver
 * toques()). Con #elegir en la dirección, la página se abre ya con ella.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Caracool_Vinos_Explorador {

	public function __construct() {
		add_shortcode( 'caracool_vinos', array( $this, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'registrar' ) );
		add_action( 'elementor/widgets/register', array( $this, 'widget_elementor' ) );
	}

	// ── Recursos ────────────────────────────────────────────────────────

	public function registrar() {
		$v = CARACOOL_VINOS_VERSION;
		$u = CARACOOL_VINOS_URL . 'assets/';
		wp_register_script( 'cv-d3-array', $u . 'lib/d3-array.min.js', array(), $v, true );
		wp_register_script( 'cv-d3-geo', $u . 'lib/d3-geo.min.js', array( 'cv-d3-array' ), $v, true );
		wp_register_script( 'cv-topojson', $u . 'lib/topojson-client.min.js', array(), $v, true );
		wp_register_script( 'cv-explorador', $u . 'cv-explorador.js', array( 'cv-d3-geo', 'cv-topojson' ), $v, true );
		wp_register_style( 'cv-explorador', $u . 'cv-explorador.css', array(), $v );
	}

	/**
	 * Los datos del bloque, como JSON dentro del propio bloque. No van en un
	 * script aparte porque el editor de Elementor vuelve a pintar el widget
	 * por AJAX y un script encolado no se repite; así viajan con el HTML.
	 */
	private static function datos( $carta, $toques = null ) {
		$a   = Caracool_Vinos::ajustes();
		$cfg = array(
			'vinos'       => $carta['vinos'],
			'fecha'       => $carta['fecha'],
			'nuevos'      => $carta['nuevos'],
			'region'      => Caracool_Vinos_Ajustes::region(),
			'precios'     => 'si' === $a['precios'],
			'ubicacion'   => 'si' === $a['ubicacion'],
			'favoritos'   => 'si' === $a['favoritos'],
			'corazones'   => class_exists( 'Caracool_Vinos_Corazones' ) ? Caracool_Vinos_Corazones::config() : null,
			'toques'      => $toques,
			'nombre'      => $a['nombre'],
			'mundo'       => CARACOOL_VINOS_URL . 'assets/mundo-110m.json?v=' . CARACOOL_VINOS_VERSION,
			'finos'       => CARACOOL_VINOS_URL . 'assets/finos-50m.json?v=' . CARACOOL_VINOS_VERSION,
		);
		// «</» no puede aparecer dentro de un <script>, ni siquiera en JSON
		return str_replace( '</', '<\/', wp_json_encode( $cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}

	// ── Las esquinas de la web ──────────────────────────────────────────

	/**
	 * Las esquinas de los botones y de los campos de formulario del Estilo
	 * del tema del Kit de Elementor. Solo la esquina: ni el color ni el
	 * relleno, que el panel lleva los suyos (cambian de día a noche). Si los
	 * campos no tienen, llevan las de los botones. Vacío sin Elementor o si
	 * el Kit no dice nada: entonces mandan las de la piel.
	 */
	public static function esquinas_kit() {
		$cache = array();
		if ( ! class_exists( 'Elementor\Plugin' ) || empty( \Elementor\Plugin::$instance ) || empty( \Elementor\Plugin::$instance->kits_manager ) ) {
			return $cache;
		}
		$km    = \Elementor\Plugin::$instance->kits_manager;
		$boton = self::esquina_de( $km, 'button_border_radius' );
		$campo = self::esquina_de( $km, 'form_field_border_radius' );
		if ( '' !== $boton ) {
			$cache['boton'] = $boton;
		}
		if ( '' !== $campo ) {
			$cache['campo'] = $campo;
		} elseif ( '' !== $boton ) {
			$cache['campo'] = $boton;
		}
		return $cache;
	}

	/** Una esquina del Kit (la de arriba a la izquierda), como «2px», o ''. */
	private static function esquina_de( $km, $id ) {
		$v = null;
		if ( method_exists( $km, 'get_current_settings' ) ) {
			$v = $km->get_current_settings( $id );
		} elseif ( method_exists( $km, 'get_active_kit_for_frontend' ) ) {
			$kit = $km->get_active_kit_for_frontend();
			$v   = $kit ? $kit->get_settings( $id ) : null;
		}
		if ( ! is_array( $v ) || ! isset( $v['top'] ) || '' === $v['top'] || ! is_numeric( $v['top'] ) ) {
			return '';
		}
		$unidad = ( isset( $v['unit'] ) && in_array( $v['unit'], array( 'px', '%', 'em', 'rem' ), true ) ) ? $v['unit'] : 'px';
		$n      = rtrim( rtrim( sprintf( '%.2f', max( 0, (float) $v['top'] ) ), '0' ), '.' );
		return $n . $unidad;
	}

	/** Lo que hay en el Kit, en una frase, para el panel de Ajustes. */
	public static function esquinas_texto() {
		$e = self::esquinas_kit();
		if ( ! $e ) {
			return 'El Kit de esta web no define esquinas para botones ni campos: con «Las de la web» se quedan las de la piel.';
		}
		$t = 'En esta web, los botones del Kit tienen esquinas de ' . ( isset( $e['boton'] ) ? $e['boton'] : $e['campo'] );
		$t .= ( isset( $e['boton'] ) && $e['campo'] !== $e['boton'] ) ? ' y los campos, de ' . $e['campo'] . '.' : ', y el panel las lleva todas así.';
		return $t;
	}

	// ── Lo que manda ────────────────────────────────────────────────────

	/**
	 * Cómo se ve el bloque. El diseño, la piel del panel y las esquinas los
	 * decide Caracool en Bodega (la pestaña Diseño del cliente) y llegan con
	 * la carta. Las vistas, Bodega las da y la web las enciende o las apaga
	 * en Ajustes; el widget o el shortcode pueden apagarlas en una página, o
	 * encender una que Bodega dé, nunca una que no dé. Lista va siempre. La
	 * escena y el sol y la luna son de la web. Si Bodega todavía no ha dicho
	 * nada (una web que no ha recibido nada desde la 0.5.7), manda lo de
	 * Ajustes, como antes.
	 *   [caracool_vinos escena="noche" mapa="no" red="si" dianoche="no"]
	 */
	public static function vista( $atts = array() ) {
		$atts   = is_array( $atts ) ? $atts : array();
		$a      = Caracool_Vinos::ajustes();
		$b      = Caracool_Vinos_Datos::diseno();
		$pieles = Caracool_Vinos_Ajustes::pieles();
		$si     = function ( $k, $local ) use ( $atts ) {
			return ( isset( $atts[ $k ] ) && '' !== $atts[ $k ] ) ? in_array( $atts[ $k ], array( 'si', 'yes', '1', 'true' ), true ) : ( 'si' === $local );
		};
		$v = array(
			'bodega'    => (bool) $b,
			'diseno'    => $b ? $b['diseno'] : ( 'columnas' === $a['diseno'] ? 'columnas' : 'mundo' ),
			'panel'     => $b ? $b['panel'] : ( isset( $atts['panel'] ) && in_array( $atts['panel'], $pieles, true ) ? $atts['panel'] : ( in_array( $a['panel'], $pieles, true ) ? $a['panel'] : 'mosaico' ) ),
			'esquinas'  => $b ? $b['esquinas'] : ( isset( $atts['esquinas'] ) && in_array( $atts['esquinas'], array( 'web', 'diseno' ), true ) ? $atts['esquinas'] : ( 'diseno' === $a['esquinas'] ? 'diseno' : 'web' ) ),
			'mapa_dado' => $b ? $b['mapa'] : true,
			'red_dada'  => $b ? $b['red'] : true,
			'escena'    => isset( $atts['escena'] ) && in_array( $atts['escena'], array( 'dia', 'noche' ), true ) ? $atts['escena'] : ( 'noche' === $a['escena'] ? 'noche' : 'dia' ),
			'dianoche'  => $si( 'dianoche', $a['dianoche'] ),
		);
		$v['mapa'] = $v['mapa_dado'] && $si( 'mapa', $a['mapa'] );
		$v['red']  = $v['red_dada'] && $si( 'red', $a['red'] );
		$v['modo'] = $v['mapa'] ? 'mapa' : ( $v['red'] ? 'red' : 'lista' );
		return $v;
	}

	/**
	 * «Ayúdame a elegir»: Tres toques dentro del explorador, detrás de un
	 * botón junto a Mis vinos. Sale si la web tiene Tres toques (lo da
	 * Caracool en Bodega, como el bloque suelto) y el widget o el shortcode
	 * no lo quitan. Lo de aquí y los tramos de precio, como en el bloque
	 * suelto. Devuelve lo que viaja al navegador, o null si no sale.
	 *   [caracool_vinos elegir="no"]
	 *   [caracool_vinos aqui="De Murcia" tramos="40, 70, 150"]
	 */
	public static function toques( $atts = array() ) {
		$atts = is_array( $atts ) ? $atts : array();
		if ( ! class_exists( 'Caracool_Vinos_Toques' ) || ! Caracool_Vinos_Toques::permitido() ) {
			return null;
		}
		if ( isset( $atts['elegir'] ) && in_array( strtolower( (string) $atts['elegir'] ), array( 'no', '0', 'false' ), true ) ) {
			return null;
		}
		return array(
			'aqui'   => isset( $atts['aqui'] ) ? trim( wp_strip_all_tags( (string) $atts['aqui'] ) ) : '',
			'tramos' => Caracool_Vinos_Toques::tramos( isset( $atts['tramos'] ) ? $atts['tramos'] : '' ),
		);
	}

	// ── Salida ──────────────────────────────────────────────────────────

	public function shortcode( $atts ) {
		return self::html( is_array( $atts ) ? $atts : array() );
	}

	public static function html( $atts = array() ) {
		$a     = Caracool_Vinos::ajustes();
		$carta = Caracool_Vinos_Datos::para_web();

		wp_enqueue_style( 'cv-explorador' );

		if ( ! $carta || empty( $carta['vinos'] ) ) {
			return '<div class="cv-explorador cv-explorador--vacio"><p>' . esc_html( $a['vacio'] ) . '</p></div>';
		}

		// «Ayúdame a elegir»: la hoja y el script de Tres toques, antes que el explorador
		$toques_cfg = self::toques( $atts );
		$toques     = (bool) $toques_cfg;
		if ( $toques ) {
			wp_enqueue_style( 'cv-toques' );
			wp_enqueue_script( 'cv-toques' );
		}
		wp_enqueue_script( 'cv-explorador' );

		$n      = count( $carta['vinos'] );
		$paises = array();
		foreach ( $carta['vinos'] as $v ) {
			if ( ! empty( $v['iso'] ) ) {
				$paises[ $v['iso'] ] = true;
			}
		}
		$np = count( $paises );

		// lo que lee el bloque (ver vista(): lo que manda Bodega y lo que decide la web)
		$vista      = self::vista( $atts );
		$diseno     = $vista['diseno'];
		$panel      = $vista['panel'];
		$esquinas   = $vista['esquinas'];
		$mapa       = $vista['mapa'];
		$red        = $vista['red'];
		$modo       = $vista['modo'];
		$escena     = $vista['escena'];
		$dianoche   = $vista['dianoche'];
		$estilo     = '';
		if ( 'web' === $esquinas ) {
			$e = self::esquinas_kit();
			$v = ( isset( $e['boton'] ) ? '--cv-radio-boton:' . $e['boton'] . ';' : '' ) . ( isset( $e['campo'] ) ? '--cv-radio-campo:' . $e['campo'] . ';' : '' );
			if ( '' !== $v ) {
				// (valores ya saneados: un número y px, %, em o rem)
				$estilo = '<style>.cv-explorador[data-esquinas=web]{' . $v . '}</style>';
			}
		}
		$precios    = 'si' === $a['precios'];
		$datos      = self::datos( $carta, $toques_cfg );
		$rotulo     = $a['rotulo'];
		$titulo     = $a['titulo'];
		$entradilla = $a['entradilla'];
		// el pie de la lista: el texto de Ajustes («IVA incluido» de fábrica), solo con
		// precios; sin texto, no hay pie. La fecha de la carta no sale en la web.
		$pie        = $precios ? trim( (string) $a['pie'] ) : '';

		ob_start();
		include CARACOOL_VINOS_DIR . 'modules/cv-bloque.php';
		return $estilo . ob_get_clean();
	}

	// ── Elementor ───────────────────────────────────────────────────────

	public function widget_elementor( $widgets ) {
		require_once CARACOOL_VINOS_DIR . 'modules/cv-widget-elementor.php';
		$widgets->register( new Caracool_Vinos_Widget() );
	}
}

new Caracool_Vinos_Explorador();
