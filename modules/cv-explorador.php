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

	// ── La paleta de la web ─────────────────────────────────────────────

	/**
	 * Los colores del Kit de Elementor (los del sistema y los propios), sin
	 * los translúcidos: [ [ 'id' =>, 'titulo' =>, 'rgb' => [r, g, b] ], … ].
	 * Vacío sin Elementor.
	 */
	public static function colores_kit() {
		if ( ! class_exists( 'Elementor\Plugin' ) || empty( \Elementor\Plugin::$instance ) || empty( \Elementor\Plugin::$instance->kits_manager ) ) {
			return array();
		}
		$km    = \Elementor\Plugin::$instance->kits_manager;
		$lista = array();
		foreach ( array( 'system_colors', 'custom_colors' ) as $clave ) {
			$v = null;
			if ( method_exists( $km, 'get_current_settings' ) ) {
				$v = $km->get_current_settings( $clave );
			} elseif ( method_exists( $km, 'get_active_kit_for_frontend' ) ) {
				$kit = $km->get_active_kit_for_frontend();
				$v   = $kit ? $kit->get_settings( $clave ) : null;
			}
			if ( ! is_array( $v ) ) {
				continue;
			}
			foreach ( $v as $c ) {
				if ( ! is_array( $c ) || empty( $c['color'] ) || ! is_string( $c['color'] ) ) {
					continue;
				}
				$rgb = self::rgb_de( $c['color'] );
				if ( ! $rgb ) {
					continue;
				}
				$lista[] = array(
					'id'     => isset( $c['_id'] ) ? (string) $c['_id'] : '',
					'titulo' => isset( $c['title'] ) ? wp_strip_all_tags( (string) $c['title'] ) : '',
					'rgb'    => $rgb,
				);
			}
		}
		return $lista;
	}

	/** Un color en #rgb, #rrggbb o rgb(): [r, g, b]. Null si no es un color o si es translúcido. */
	public static function rgb_de( $t ) {
		$t = strtolower( trim( (string) $t ) );
		if ( preg_match( '/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $t, $m ) ) {
			$h = $m[1];
			if ( strlen( $h ) <= 4 ) {
				$h = preg_replace( '/(.)/', '$1$1', $h );
			}
			if ( 8 === strlen( $h ) && hexdec( substr( $h, 6, 2 ) ) < 250 ) {
				return null;
			}
			return array( hexdec( substr( $h, 0, 2 ) ), hexdec( substr( $h, 2, 2 ) ), hexdec( substr( $h, 4, 2 ) ) );
		}
		if ( preg_match( '/^rgba?\(\s*(\d{1,3})[\s,]+(\d{1,3})[\s,]+(\d{1,3})\s*(?:[,\/]\s*([0-9.]+%?)\s*)?\)$/', $t, $m ) ) {
			if ( isset( $m[4] ) && '' !== $m[4] ) {
				$alfa = '%' === substr( $m[4], -1 ) ? (float) $m[4] / 100 : (float) $m[4];
				if ( $alfa < 0.98 ) {
					return null;
				}
			}
			return array( min( 255, (int) $m[1] ), min( 255, (int) $m[2] ), min( 255, (int) $m[3] ) );
		}
		return null;
	}

	/** La luminosidad relativa de un color (la de WCAG, de 0 a 1). */
	private static function luz( $rgb ) {
		$f = function ( $c ) {
			$c = $c / 255;
			return $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		};
		return 0.2126 * $f( $rgb[0] ) + 0.7152 * $f( $rgb[1] ) + 0.0722 * $f( $rgb[2] );
	}

	/** El contraste entre dos colores (de 1 a 21). */
	private static function contraste( $a, $b ) {
		$la = self::luz( $a );
		$lb = self::luz( $b );
		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
	}

	/** $a con una parte $p (de 0 a 1) de $b. */
	private static function mezcla( $a, $b, $p ) {
		return array(
			(int) round( $a[0] + ( $b[0] - $a[0] ) * $p ),
			(int) round( $a[1] + ( $b[1] - $a[1] ) * $p ),
			(int) round( $a[2] + ( $b[2] - $a[2] ) * $p ),
		);
	}

	private static function hex( $rgb ) {
		return sprintf( '#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2] );
	}

	/** Cuánto color tiene un color (de 0, un gris, a 1) y a qué altura de claridad. */
	private static function viveza( $rgb ) {
		$mx = max( $rgb ) / 255;
		$mn = min( $rgb ) / 255;
		$l  = ( $mx + $mn ) / 2;
		if ( $mx === $mn ) {
			return array( 0, $l );
		}
		$s = ( $mx - $mn ) / ( 1 - abs( 2 * $l - 1 ) );
		return array( $s * ( 1 - abs( 2 * $l - 1 ) ), $l );
	}

	/**
	 * La paleta de esta web, sacada del Kit: el color más claro (el fondo de
	 * día y el texto de noche), el más oscuro (la tinta de día y el fondo de
	 * noche) y un acento (el más vivo). Los tonos de en medio (la tierra del
	 * globo, los países con vinos, los filetes) son mezclas de esos tres, así
	 * el mapa sale con los colores de la web y no con los de otra.
	 *
	 * Vacía (vale lo de la casa de siempre) si el Kit ya lleva los tonos con
	 * nombre de El Churra, si no hay Kit o si el Kit no da dos colores con
	 * contraste suficiente. Ajustes puede fijar uno de los tres.
	 *
	 * @return array nombre de la variable (sin «--cv-k-») => valor.
	 */
	public static function paleta() {
		$a      = Caracool_Vinos::ajustes();
		$manual = array();
		foreach ( array( 'claro', 'oscuro', 'acento' ) as $k ) {
			$m             = isset( $a[ 'color_' . $k ] ) ? $a[ 'color_' . $k ] : 'auto';
			$manual[ $k ] = ( 'auto' === $m ) ? null : self::rgb_de( $m );
		}
		$kit  = self::colores_kit();
		$casa = false;
		foreach ( $kit as $c ) {
			if ( 'cremaclara' === $c['id'] ) {
				$casa = true;
			}
		}
		if ( $casa && ! array_filter( $manual ) ) {
			return array();
		}
		$claro  = $manual['claro'];
		$oscuro = $manual['oscuro'];
		foreach ( $kit as $c ) {
			if ( ! $claro || self::luz( $c['rgb'] ) > self::luz( $claro ) ) {
				if ( ! $manual['claro'] ) {
					$claro = $c['rgb'];
				}
			}
			if ( ! $oscuro || self::luz( $c['rgb'] ) < self::luz( $oscuro ) ) {
				if ( ! $manual['oscuro'] ) {
					$oscuro = $c['rgb'];
				}
			}
		}
		if ( ! $claro || ! $oscuro ) {
			return array();
		}
		if ( ! ( $manual['claro'] && $manual['oscuro'] ) && self::contraste( $claro, $oscuro ) < 4.5 ) {
			return array();
		}
		$acento = $manual['acento'];
		if ( ! $acento ) {
			$mejor = 0.05;
			foreach ( $kit as $c ) {
				list( $v, $l ) = self::viveza( $c['rgb'] );
				if ( $l < 0.2 || $l > 0.85 || $c['rgb'] === $claro || $c['rgb'] === $oscuro ) {
					continue;
				}
				if ( $v > $mejor ) {
					$mejor  = $v;
					$acento = $c['rgb'];
				}
			}
		}
		if ( ! $acento ) {
			// un Kit sin color vivo (todo grises): el acento es la tinta, un poco aclarada
			$acento = self::mezcla( $oscuro, $claro, 0.3 );
		}
		return array(
			'tinta'        => self::hex( $oscuro ),
			'crema'        => self::hex( $claro ),
			'crema2'       => self::hex( self::mezcla( $claro, $oscuro, 0.06 ) ),
			'arena'        => self::hex( self::mezcla( $claro, $acento, 0.22 ) ),
			'texto'        => self::hex( self::mezcla( $oscuro, $claro, 0.22 ) ),
			'borde'        => self::hex( self::mezcla( $claro, $oscuro, 0.16 ) ),
			'rojo'         => self::hex( self::mezcla( $acento, $oscuro, 0.2 ) ),
			'ocre'         => self::hex( self::mezcla( $acento, $oscuro, 0.45 ) ),
			'naranja'      => self::hex( $acento ),
			'noche-fondo'  => self::hex( $oscuro ),
			'noche-papel'  => self::hex( self::mezcla( $oscuro, $claro, 0.05 ) ),
			'noche-texto'  => self::hex( $claro ),
			'noche-suave'  => self::hex( self::mezcla( $claro, $oscuro, 0.32 ) ),
			'noche-borde'  => 'rgba(' . $claro[0] . ',' . $claro[1] . ',' . $claro[2] . ',.14)',
			'noche-arena'  => self::hex( self::mezcla( $oscuro, $claro, 0.12 ) ),
			'noche-acento' => self::hex( $acento ),
		);
	}

	/** La paleta y la letra de la web, como la hoja que las pone en el bloque. Vacía si manda lo de la casa. */
	public static function kit_css() {
		$d = '';
		foreach ( array_merge( self::paleta(), self::letra() ) as $k => $v ) {
			$d .= '--cv-k-' . $k . ':' . $v . ';';
		}
		// (valores calculados aquí: #rrggbb, rgba con números, normal|italic y un peso de tres cifras)
		return '' === $d ? '' : '<style>.cv-explorador{' . $d . '}</style>';
	}

	/** Lo que se está usando, en una frase, para el panel de Ajustes. */
	public static function paleta_texto() {
		$p = self::paleta();
		if ( ! $p ) {
			return 'En esta web van los colores de la casa (el Kit lleva los tonos con nombre de El Churra, o no tiene dos colores con contraste suficiente).';
		}
		return 'Ahora: fondo de día ' . $p['crema'] . ', tinta y fondo de noche ' . $p['tinta'] . ', acento ' . $p['naranja'] . '. La tierra del globo, los países con vinos y los filetes son mezclas de esos tres.';
	}

	// ── La letra de la web ──────────────────────────────────────────────

	/** Un ajuste del Kit de Elementor, o null sin Elementor o sin Kit. */
	private static function kit_ajuste( $clave ) {
		if ( ! class_exists( 'Elementor\Plugin' ) || empty( \Elementor\Plugin::$instance ) || empty( \Elementor\Plugin::$instance->kits_manager ) ) {
			return null;
		}
		$km = \Elementor\Plugin::$instance->kits_manager;
		if ( method_exists( $km, 'get_current_settings' ) ) {
			return $km->get_current_settings( $clave );
		}
		if ( method_exists( $km, 'get_active_kit_for_frontend' ) ) {
			$kit = $km->get_active_kit_for_frontend();
			return $kit ? $kit->get_settings( $clave ) : null;
		}
		return null;
	}

	/** El Kit de El Churra: el que lleva el color con nombre «cremaclara». */
	public static function kit_de_la_casa() {
		foreach ( self::colores_kit() as $c ) {
			if ( 'cremaclara' === $c['id'] ) {
				return true;
			}
		}
		return false;
	}

	/** Un peso del Kit (400, 600, normal, bold) como número de tres cifras, o ''. */
	private static function peso_de( $v ) {
		$v = strtolower( trim( (string) $v ) );
		if ( 'normal' === $v ) {
			return '400';
		}
		if ( 'bold' === $v ) {
			return '700';
		}
		return preg_match( '/^[1-9]00$/', $v ) ? $v : '';
	}

	/** Las tipografías del sistema del Kit por id (primary, secondary, text, accent). */
	private static function tipografias_kit() {
		$lista = self::kit_ajuste( 'system_typography' );
		$por   = array();
		if ( is_array( $lista ) ) {
			foreach ( $lista as $t ) {
				if ( is_array( $t ) && ! empty( $t['_id'] ) ) {
					$por[ (string) $t['_id'] ] = $t;
				}
			}
		}
		return $por;
	}

	/**
	 * La forma de la letra de la web. Las familias ya salen del Kit
	 * (--e-global-typography-*), pero los títulos de El Churra van en
	 * cursiva a peso 500 y el texto a peso 300, y la letra de otra web puede
	 * ser recta y a 400: con otra letra que la de la casa, cursiva y pesos
	 * son los que diga el Kit (los títulos, los de «Principal»; el texto,
	 * los de «Texto»). Vacío (vale lo de la casa, sin tocar nada) si el Kit
	 * es el de El Churra, si no hay Kit o si Ajustes dice «La de El Churra».
	 *
	 * @return array nombre de la variable (sin «--cv-k-») => valor.
	 */
	public static function letra() {
		$a    = Caracool_Vinos::ajustes();
		$modo = isset( $a['letra'] ) ? $a['letra'] : 'auto';
		if ( 'casa' === $modo || ( 'auto' === $modo && self::kit_de_la_casa() ) ) {
			return array();
		}
		$por    = self::tipografias_kit();
		$titulo = isset( $por['primary'] ) ? $por['primary'] : array();
		$texto  = isset( $por['text'] ) ? $por['text'] : array();
		if ( ! $titulo && ! $texto ) {
			return array();
		}
		$estilo = isset( $titulo['typography_font_style'] ) ? strtolower( (string) $titulo['typography_font_style'] ) : '';
		$p_tit  = self::peso_de( isset( $titulo['typography_font_weight'] ) ? $titulo['typography_font_weight'] : '' );
		$p_tex  = self::peso_de( isset( $texto['typography_font_weight'] ) ? $texto['typography_font_weight'] : '' );
		return array(
			'didona-estilo' => in_array( $estilo, array( 'italic', 'oblique' ), true ) ? 'italic' : 'normal',
			'didona-peso'   => '' !== $p_tit ? $p_tit : '400',
			'peso-fino'     => '' !== $p_tex ? $p_tex : '400',
		);
	}

	/** Lo que se está usando, en una frase, para el panel de Ajustes. */
	public static function letra_texto() {
		$l = self::letra();
		if ( ! $l ) {
			return 'En esta web va la letra de la casa: títulos en cursiva y texto ligero, con las familias del Kit.';
		}
		$por   = self::tipografias_kit();
		$f_tit = isset( $por['primary']['typography_font_family'] ) ? wp_strip_all_tags( (string) $por['primary']['typography_font_family'] ) : '';
		$f_tex = isset( $por['text']['typography_font_family'] ) ? wp_strip_all_tags( (string) $por['text']['typography_font_family'] ) : '';
		return 'Ahora: títulos en ' . ( '' !== $f_tit ? $f_tit . ' ' : '' ) . ( 'italic' === $l['didona-estilo'] ? 'cursiva' : 'recta' ) . ' a peso ' . $l['didona-peso'] . ' y texto' . ( '' !== $f_tex ? ' en ' . $f_tex : '' ) . ' a peso ' . $l['peso-fino'] . ', como dice el Kit.';
	}

	// ── Lo que manda ────────────────────────────────────────────────────

	/**
	 * Cómo se ve el bloque. El diseño, la piel del panel y las esquinas los
	 * decide Caracool en Bodega (la pestaña Diseño del cliente) y llegan con
	 * la carta. Las vistas, Bodega las da y la web las enciende o las apaga
	 * en Ajustes; el widget o el shortcode pueden apagarlas en una página, o
	 * encender una que Bodega dé, nunca una que no dé. Lista va siempre. La
	 * escena y el sol y la luna son de la web. El punto delante de cada vino
	 * también lo decide Bodega; si no dice nada, sale solo con el Kit de El
	 * Churra (es suyo). Si Bodega todavía no ha dicho
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
			// el punto delante de cada vino: lo que diga Bodega; si no dice nada, solo con el Kit de El Churra
			'punto'     => ( $b && null !== $b['punto'] ) ? ( 'si' === $b['punto'] ) : self::kit_de_la_casa(),
			'punto_dado' => $b && null !== $b['punto'],
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
		$punto      = $vista['punto'];
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
		$estilo    = self::kit_css() . $estilo;
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
