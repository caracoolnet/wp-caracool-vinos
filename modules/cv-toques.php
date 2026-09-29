<?php
/**
 * Caracool Vinos — Tres toques
 * ─────────────────────────────────────────────────────────────────────
 * Un bloque aparte del explorador: tres preguntas (el tipo, hasta cuánto y
 * de aquí o de fuera) y salen cuatro vinos en grande, con el resto de los
 * que encajan justo debajo. Se pone con el shortcode o con el widget de
 * Elementor «Tres toques»:
 *
 *   [caracool_vinos_toques aqui="De Murcia" tramos="40, 70, 150"]
 *
 *   aqui     cómo se llama la opción de las zonas de aquí (las que van
 *            delante en Ajustes); sin nada, «De aquí»
 *   tramos   dónde se parten los precios; sin nada, 40, 70 y 150 €
 *
 * Lo da Caracool en Bodega, cliente a cliente (la casilla «Tres toques» de
 * la pestaña Diseño). Si Bodega todavía no ha dicho nada, se ve.
 *
 * Lo demás sale de la web: la carta vigente, si se enseñan los precios
 * (sin precios, la pregunta del precio no sale), si hay «Mis vinos» (el
 * corazón, el mismo del explorador) y los corazones de la gente. Los
 * colores y las letras son los del Kit, con los mismos tokens que el
 * explorador; las esquinas, las de los botones y campos del Kit si la web
 * las lleva así.
 *
 * Los datos viajan dentro del bloque (un JSON), como en el explorador, y
 * recortados a lo que hace falta: con la carta de Pura Cepa son casi dos
 * mil vinos.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Caracool_Vinos_Toques {

	const SHORTCODE = 'caracool_vinos_toques';
	const TRAMOS    = array( 40, 70, 150 );

	public function __construct() {
		add_shortcode( self::SHORTCODE, array( $this, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'registrar' ) );
		add_action( 'elementor/widgets/register', array( $this, 'widget_elementor' ) );
	}

	public function registrar() {
		$u = CARACOOL_VINOS_URL . 'assets/';
		wp_register_style( 'cv-toques', $u . 'cv-toques.css', array(), CARACOOL_VINOS_VERSION );
		wp_register_script( 'cv-toques', $u . 'cv-toques.js', array(), CARACOOL_VINOS_VERSION, true );
	}

	public function widget_elementor( $widgets ) {
		require_once CARACOOL_VINOS_DIR . 'modules/cv-widget-toques.php';
		$widgets->register( new Caracool_Vinos_Widget_Toques() );
	}

	/** ¿La tiene esta web? La da Caracool en Bodega; sin nada de Bodega, sí. */
	public static function permitido() {
		$b = Caracool_Vinos_Datos::diseno();
		return $b ? ! empty( $b['toques'] ) : true;
	}

	/** «40, 70, 150» → array( 40, 70, 150 ): números enteros, de menor a mayor, sin repetir, hasta seis. */
	public static function tramos( $texto ) {
		$t = array();
		foreach ( preg_split( '/[^0-9]+/', (string) $texto ) as $n ) {
			if ( '' !== $n && (int) $n > 0 ) {
				$t[ (int) $n ] = (int) $n;
			}
		}
		ksort( $t );
		$t = array_slice( array_values( $t ), 0, 6 );
		return $t ? $t : self::TRAMOS;
	}

	/**
	 * Cada vino, en una fila corta:
	 * [llave, nombre, bodega, añadas, precio, uvas, zona, país, iso, tipo, formato, parker]
	 */
	public static function filas( $vinos ) {
		$out = array();
		foreach ( $vinos as $v ) {
			$uvas  = ! empty( $v['uvas_texto'] ) ? $v['uvas_texto'] : ( ! empty( $v['uvas'] ) && is_array( $v['uvas'] ) ? implode( ', ', $v['uvas'] ) : '' );
			$out[] = array(
				isset( $v['llave'] ) ? (string) $v['llave'] : '',
				isset( $v['nombre'] ) ? (string) $v['nombre'] : '',
				isset( $v['bodega'] ) ? (string) $v['bodega'] : '',
				! empty( $v['anadas'] ) && is_array( $v['anadas'] ) ? implode( ' / ', $v['anadas'] ) : '',
				isset( $v['precio'] ) && is_numeric( $v['precio'] ) ? $v['precio'] + 0 : 0,
				(string) $uvas,
				isset( $v['zona'] ) ? (string) $v['zona'] : '',
				isset( $v['pais'] ) ? (string) $v['pais'] : '',
				isset( $v['iso'] ) ? (string) $v['iso'] : '',
				isset( $v['tipo'] ) ? (string) $v['tipo'] : '',
				isset( $v['formato'] ) ? (string) $v['formato'] : '',
				! empty( $v['parker'] ) ? (string) ( ! empty( $v['parker_txt'] ) ? $v['parker_txt'] : $v['parker'] ) : '',
			);
		}
		return $out;
	}

	public static function datos( $carta, $atts ) {
		$a   = Caracool_Vinos::ajustes();
		$cfg = array(
			'vinos'     => self::filas( $carta['vinos'] ),
			'region'    => Caracool_Vinos_Ajustes::region(),
			'aqui'      => isset( $atts['aqui'] ) ? trim( wp_strip_all_tags( (string) $atts['aqui'] ) ) : '',
			'tramos'    => self::tramos( isset( $atts['tramos'] ) ? $atts['tramos'] : '' ),
			'precios'   => 'si' === $a['precios'],
			'favoritos' => 'si' === $a['favoritos'],
			'corazones' => class_exists( 'Caracool_Vinos_Corazones' ) ? Caracool_Vinos_Corazones::config() : null,
			'nombre'    => $a['nombre'],
		);
		// «</» no puede aparecer dentro de un <script>, ni siquiera en JSON
		return str_replace( '</', '<\/', wp_json_encode( $cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}

	/** ¿Se está pintando en el editor de Elementor? (para explicar por qué no sale nada) */
	private static function en_editor() {
		if ( ! class_exists( '\Elementor\Plugin' ) || empty( \Elementor\Plugin::$instance ) ) {
			return false;
		}
		$e = \Elementor\Plugin::$instance;
		return ( ! empty( $e->editor ) && method_exists( $e->editor, 'is_edit_mode' ) && $e->editor->is_edit_mode() )
			|| ( ! empty( $e->preview ) && method_exists( $e->preview, 'is_preview_mode' ) && $e->preview->is_preview_mode() );
	}

	public function shortcode( $atts ) {
		return self::html( is_array( $atts ) ? $atts : array() );
	}

	public static function html( $atts = array() ) {
		$a = Caracool_Vinos::ajustes();
		wp_enqueue_style( 'cv-toques' );
		if ( ! self::permitido() ) {
			return self::en_editor() ? '<div class="cv-toques cv-toques--vacio"><p>Esta web no tiene «Tres toques». Lo da Caracool desde Bodega.</p></div>' : '';
		}
		$carta = Caracool_Vinos_Datos::para_web();
		if ( ! $carta || empty( $carta['vinos'] ) ) {
			return '<div class="cv-toques cv-toques--vacio"><p>' . esc_html( $a['vacio'] ) . '</p></div>';
		}
		wp_enqueue_script( 'cv-toques' );

		// las esquinas del Kit, como en el explorador, si la web las lleva así
		$estilo = '';
		if ( class_exists( 'Caracool_Vinos_Explorador' ) ) {
			$vista = Caracool_Vinos_Explorador::vista();
			if ( 'web' === $vista['esquinas'] ) {
				$e = Caracool_Vinos_Explorador::esquinas_kit();
				$v = ( isset( $e['boton'] ) ? '--cv-radio-boton:' . $e['boton'] . ';' : '' ) . ( isset( $e['campo'] ) ? '--cv-radio-campo:' . $e['campo'] . ';' : '' );
				if ( '' !== $v ) {
					$estilo = '<style>.cv-toques{' . $v . '}</style>';
				}
			}
		}

		return $estilo
			. '<section class="cv-toques" data-cv-toques aria-label="Tres toques: tres preguntas para elegir vino">'
			. '<script type="application/json" data-cv-toques-datos>' . self::datos( $carta, $atts ) . '</script>' // phpcs:ignore WordPress.Security.EscapeOutput
			. '<div class="tq-dentro"><div class="tq-hilo" aria-label="Lo que has elegido"></div><div class="tq-escena" aria-live="polite"></div></div>'
			. '</section>';
	}
}

new Caracool_Vinos_Toques();
