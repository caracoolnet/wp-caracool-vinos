<?php
/**
 * Caracool Vinos — Widget de Elementor
 * ─────────────────────────────────────────────────────────────────────
 * Se carga solo cuando Elementor existe. Pinta lo mismo que el shortcode.
 * Lo que se ve (precios, ubicación, Mis vinos, textos) se decide en
 * Caracool → Vinos; aquí se decide cómo se ve en esta página:
 *
 *   Contenido  la escena (día / noche), los botones «Mapa» y «Red» (solo
 *              los que da Bodega) y el sol y la luna, por encima de lo que
 *              diga el panel. El diseño, la piel del panel y las esquinas
 *              los decide Caracool en Bodega.
 *   Estilo     los colores de la casa, enlazados de fábrica a los colores
 *              globales del Kit (el icono del globo en cada control); los
 *              colores del vino, que no son del Kit sino del vino en la
 *              copa; las letras; y los colores de la noche.
 *
 * Ningún control estila nada directamente: cada uno escribe un token
 * (--cv-tinta, --cv-vino-tinto…) en el bloque, y la hoja del plugin cuelga
 * de esos tokens. Así no hay pelea con el Estilo del tema, y si no se toca
 * nada, manda el Kit y, en su defecto, el valor de reserva de la hoja.
 *
 * Declara sus dependencias para que la vista previa del editor cargue la
 * hoja y el script aunque el widget se pinte después del pie de página.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;

class Caracool_Vinos_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'caracool-vinos';
	}

	public function get_title() {
		return 'Explorar los vinos';
	}

	public function get_icon() {
		return 'eicon-globe';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'vinos', 'vino', 'carta', 'bodega', 'mapa', 'globo', 'red', 'caracool' );
	}

	/** En el editor, también Tres toques: el widget se vuelve a pintar por AJAX y así «Ayúdame a elegir» funciona sin guardar. */
	private function en_editor() {
		if ( ! class_exists( '\Elementor\Plugin' ) || empty( \Elementor\Plugin::$instance ) ) {
			return false;
		}
		$e = \Elementor\Plugin::$instance;
		return ( ! empty( $e->editor ) && method_exists( $e->editor, 'is_edit_mode' ) && $e->editor->is_edit_mode() )
			|| ( ! empty( $e->preview ) && method_exists( $e->preview, 'is_preview_mode' ) && $e->preview->is_preview_mode() );
	}

	public function get_style_depends() {
		return $this->en_editor() ? array( 'cv-toques', 'cv-explorador' ) : array( 'cv-explorador' );
	}

	public function get_script_depends() {
		return $this->en_editor() ? array( 'cv-toques', 'cv-explorador' ) : array( 'cv-explorador' );
	}

	/** Un control de color que escribe un token en el bloque. */
	private function token( $id, $rotulo, $token, $global = null, $reserva = '', $desc = '' ) {
		$args = array(
			'label'     => $rotulo,
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .cv-explorador' => '--cv-' . $token . ': {{VALUE}};' ),
		);
		if ( $global ) {
			$args['global'] = array( 'default' => $global );
		}
		if ( '' !== $reserva ) {
			$args['default'] = $reserva;
		}
		if ( '' !== $desc ) {
			$args['description'] = $desc;
		}
		$this->add_control( $id, $args );
	}

	protected function register_controls() {

		// ── Contenido ─────────────────────────────────────────────────
		$this->start_controls_section( 'contenido', array( 'label' => 'Explorar los vinos' ) );

		$this->add_control(
			'nota',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => 'El globo (o la red) a la izquierda y la lista con sus filtros a la derecha. Los vinos salen de la carta publicada en <b>Caracool → Vinos</b>, y ahí se decide también qué se ve (precios, ubicación en bodega, «Mis vinos»). Colócalo en un contenedor a ancho completo, sin relleno lateral: el bloque se ajusta al ancho que le den.',
				'content_classes' => 'elementor-descriptor',
			)
		);

		$this->add_control(
			'escena',
			array(
				'label'       => 'Escena',
				'type'        => Controls_Manager::SELECT,
				'default'     => '',
				'options'     => array(
					''      => 'Como en Caracool → Vinos',
					'dia'   => 'De día (los colores de la web)',
					'noche' => 'De noche (la bodega a oscuras)',
				),
				'description' => 'De noche, la red es un cielo con estrellas del color del vino. Los colores de la noche se afinan en la pestaña Estilo.',
			)
		);

		$this->add_control(
			'red',
			array(
				'label'       => 'Botón «Red»',
				'type'        => Controls_Manager::SELECT,
				'default'     => '',
				'options'     => array(
					''   => 'Como en Caracool → Vinos',
					'si' => 'Sí',
					'no' => 'No',
				),
				'description' => 'Solo si Caracool la da en Bodega; si no la da, no sale.',
			)
		);

		$this->add_control(
			'mapa',
			array(
				'label'       => 'Botón «Mapa»',
				'type'        => Controls_Manager::SELECT,
				'default'     => '',
				'options'     => array(
					''   => 'Como en Caracool → Vinos',
					'si' => 'Sí',
					'no' => 'No',
				),
				'description' => 'El globo. Solo si Caracool lo da en Bodega. Lista va siempre.',
			)
		);

		$this->add_control(
			'dianoche',
			array(
				'label'   => 'Sol y luna',
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''   => 'Como en Caracool → Vinos',
					'si' => 'Sí: el visitante cambia de día a noche',
					'no' => 'No: siempre la escena de arriba',
				),
			)
		);

		$this->end_controls_section();

		// ── Contenido: Ayúdame a elegir ───────────────────────────────
		$this->start_controls_section( 'elegir_seccion', array( 'label' => 'Ayúdame a elegir' ) );

		$tiene = class_exists( 'Caracool_Vinos_Toques' ) && Caracool_Vinos_Toques::permitido();
		$this->add_control(
			'nota_elegir',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => 'Un botón junto a «Mis vinos» que abre «Tres toques» encima del explorador: tres preguntas (el tipo, hasta cuánto y de aquí o de fuera) y salen cuatro vinos, con el resto debajo; cada uno lleva a su ficha en el mapa. Con <b>#elegir</b> al final de la dirección, la página se abre ya con las preguntas (sirve para enlazarlo desde la portada o la carta).<br><br>'
					. ( $tiene ? 'Esta web tiene «Tres toques».' : '<b>Esta web no tiene «Tres toques»</b>: lo da Caracool desde Bodega. Mientras no lo tenga, el botón no sale.' ),
				'content_classes' => 'elementor-descriptor',
			)
		);

		$this->add_control(
			'elegir',
			array(
				'label'        => 'Botón «Ayúdame a elegir»',
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => 'Sí',
				'label_off'    => 'No',
				'return_value' => 'si',
				'default'      => 'si',
			)
		);

		$this->add_control(
			'elegir_aqui',
			array(
				'label'       => 'Lo de aquí',
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => 'De aquí',
				'description' => 'Cómo se llama la opción de las zonas que van delante en Caracool → Vinos → Ajustes (por ejemplo, «De Murcia»).',
				'condition'   => array( 'elegir' => 'si' ),
			)
		);

		$this->add_control(
			'elegir_tramos',
			array(
				'label'       => 'Tramos de precio',
				'type'        => Controls_Manager::TEXT,
				'default'     => '40, 70, 150',
				'description' => 'Dónde se parten los precios, en euros y separados por comas. Con 40, 70 y 150 salen «Hasta 40 €», «40 a 70 €», «70 a 150 €» y «Más de 150 €». Sin precios en la web, esta pregunta no sale.',
				'condition'   => array( 'elegir' => 'si' ),
			)
		);

		$this->end_controls_section();

		// ── Estilo: los colores de la casa ────────────────────────────
		$this->start_controls_section( 'colores', array( 'label' => 'Colores de la casa', 'tab' => Controls_Manager::TAB_STYLE ) );

		$this->add_control(
			'nota_colores',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => 'De fábrica van enlazados a los colores globales del Kit (el icono del globo). Si un color se desenlaza, vale solo para este bloque.',
				'content_classes' => 'elementor-descriptor',
			)
		);
		$this->token( 'color_tinta', 'Tinta', 'tinta', Global_Colors::COLOR_SECONDARY, '', 'Titulares, nombres de vino, botones activos, el globo y los hilos de la red.' );
		$this->token( 'color_texto', 'Texto', 'texto', Global_Colors::COLOR_TEXT, '', 'Bodegas, uvas, notas y rótulos pequeños.' );
		$this->token( 'color_acento', 'Acento', 'rojo', Global_Colors::COLOR_PRIMARY, '', 'El país elegido en el globo, los corazones y «Quitar».' );
		$this->token( 'color_fondo', 'Fondo', 'crema', null, '', 'El fondo de la escena. Si no se toca, el crema de la casa.' );
		$this->token( 'color_papel', 'Papel', 'crema2', null, '', 'Superficies: la tierra del globo, las etiquetas de ubicación.' );
		$this->token( 'color_arena', 'Arena', 'arena', null, '', 'Los países con vinos en el globo.' );
		$this->token( 'color_borde', 'Bordes', 'borde', null, '', 'Filetes, chips y desplegables.' );

		$this->end_controls_section();

		// ── Estilo: los colores del vino ──────────────────────────────
		$this->start_controls_section( 'colores_vino', array( 'label' => 'Colores del vino', 'tab' => Controls_Manager::TAB_STYLE ) );

		$this->add_control(
			'nota_vino',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => 'El color del vino en la copa: rubí para los tintos, paja y oro para los blancos, salmón para los rosados, oro pálido para los espumosos. Las uvas van por su piel. No son del Kit: son un dato. De día se asientan hacia la tinta; de noche brillan tal cual.',
				'content_classes' => 'elementor-descriptor',
			)
		);
		$this->token( 'vino_tinto', 'Tinto', 'vino-tinto', null, '#E0435A' );
		$this->token( 'vino_blanco', 'Blanco', 'vino-blanco', null, '#F2DC8A' );
		$this->token( 'vino_rosado', 'Rosado', 'vino-rosado', null, '#F2A0A6' );
		$this->token( 'vino_espumoso', 'Espumoso', 'vino-espumoso', null, '#FBECB4' );

		$this->end_controls_section();

		// ── Estilo: letras ────────────────────────────────────────────
		$this->start_controls_section( 'letras', array( 'label' => 'Letras', 'tab' => Controls_Manager::TAB_STYLE ) );

		$this->add_control(
			'nota_letras',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => 'Si no se elige nada, los titulares llevan la tipografía Principal del Kit y el texto, la de Texto. Los tamaños los pone el bloque.',
				'content_classes' => 'elementor-descriptor',
			)
		);
		$this->add_control(
			'letra_titulares',
			array(
				'label'     => 'Titulares',
				'type'      => Controls_Manager::FONT,
				'default'   => '',
				'selectors' => array( '{{WRAPPER}} .cv-explorador' => '--cv-didona: "{{VALUE}}", Georgia, serif;' ),
			)
		);
		$this->add_control(
			'letra_texto',
			array(
				'label'     => 'Texto',
				'type'      => Controls_Manager::FONT,
				'default'   => '',
				'selectors' => array( '{{WRAPPER}} .cv-explorador' => '--cv-sans: "{{VALUE}}", system-ui, sans-serif;' ),
			)
		);

		$this->end_controls_section();

		// ── Estilo: la noche ──────────────────────────────────────────
		$this->start_controls_section( 'noche', array( 'label' => 'De noche', 'tab' => Controls_Manager::TAB_STYLE ) );

		$this->add_control(
			'nota_noche',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => 'Solo cuentan con la escena «De noche». La cueva y la crema de la casa de fábrica; el acento, enlazado al color de Acento del Kit.',
				'content_classes' => 'elementor-descriptor',
			)
		);
		$this->token( 'noche_fondo', 'Fondo', 'noche-fondo', null, '#0F0907' );
		$this->token( 'noche_papel', 'Papel', 'noche-papel', null, '#120B09' );
		$this->token( 'noche_texto', 'Texto fuerte', 'noche-texto', null, '#FAF5EC' );
		$this->token( 'noche_suave', 'Texto suave', 'noche-suave', null, '#B9A78F' );
		$this->token( 'noche_borde', 'Bordes', 'noche-borde', null, 'rgba(250,245,236,0.14)' );
		$this->token( 'noche_arena', 'Arena', 'noche-arena', null, '#2A1C16', 'Los países con vinos en el globo, de noche.' );
		$this->token( 'noche_acento', 'Acento', 'noche-acento', Global_Colors::COLOR_ACCENT, '', 'Sobre oscuro, el naranja luz de la casa.' );

		$this->end_controls_section();
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$atts = array();
		if ( ! empty( $s['escena'] ) ) {
			$atts['escena'] = $s['escena'];
		}
		foreach ( array( 'red', 'mapa', 'dianoche' ) as $k ) {
			if ( ! empty( $s[ $k ] ) ) {
				$atts[ $k ] = $s[ $k ];
			}
		}
		// «Ayúdame a elegir»: apagado solo si se apaga aquí (un widget de antes, sin el ajuste, lo lleva)
		$atts['elegir'] = ( isset( $s['elegir'] ) && 'si' !== $s['elegir'] ) ? 'no' : 'si';
		$atts['aqui']   = isset( $s['elegir_aqui'] ) ? $s['elegir_aqui'] : '';
		$atts['tramos'] = isset( $s['elegir_tramos'] ) ? $s['elegir_tramos'] : '';
		echo Caracool_Vinos_Explorador::html( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
