<?php
/**
 * Caracool Vinos — Widget de Elementor «Tres toques»
 * ─────────────────────────────────────────────────────────────────────
 * Pinta lo mismo que [caracool_vinos_toques]. Solo sale si Caracool lo da
 * en Bodega (en el editor, un aviso lo dice).
 *
 *   Contenido  cómo se llama lo de aquí y dónde se parten los precios.
 *   Estilo     los colores de la casa, enlazados de fábrica a los colores
 *              globales del Kit; los colores del vino; las letras.
 *
 * Como en el explorador, ningún control estila nada directamente: cada uno
 * escribe un token (--cv-tinta, --cv-vino-tinto…) en el bloque, y la hoja
 * cuelga de esos tokens. Si no se toca nada, manda el Kit.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;

class Caracool_Vinos_Widget_Toques extends \Elementor\Widget_Base {

	public function get_name() {
		return 'caracool-vinos-toques';
	}

	public function get_title() {
		return 'Tres toques';
	}

	public function get_icon() {
		return 'eicon-form-vertical';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'vinos', 'vino', 'carta', 'tres toques', 'elegir', 'preguntas', 'caracool' );
	}

	public function get_style_depends() {
		return array( 'cv-toques' );
	}

	public function get_script_depends() {
		return array( 'cv-toques' );
	}

	/** Un control de color que escribe un token en el bloque. */
	private function token( $id, $rotulo, $token, $global = null, $reserva = '', $desc = '' ) {
		$args = array(
			'label'     => $rotulo,
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .cv-toques' => '--cv-' . $token . ': {{VALUE}};' ),
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
		$this->start_controls_section( 'contenido', array( 'label' => 'Tres toques' ) );

		$this->add_control(
			'nota',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => 'Tres preguntas (el tipo, de aquí o de fuera y hasta cuánto) y salen cuatro vinos en grande, con el resto debajo. Los vinos salen de la carta publicada en <b>Caracool → Vinos</b>; los precios y «Mis vinos» se ven si allí están encendidos. Sin precios, la pregunta del precio no sale.',
				'content_classes' => 'elementor-descriptor',
			)
		);

		$this->add_control(
			'aqui',
			array(
				'label'       => 'Lo de aquí',
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => 'De aquí',
				'description' => 'Cómo se llama la opción de las zonas que van delante en Caracool → Vinos → Ajustes (por ejemplo, «De Murcia»).',
			)
		);

		$this->add_control(
			'tramos',
			array(
				'label'       => 'Tramos de precio',
				'type'        => Controls_Manager::TEXT,
				'default'     => '40, 70, 150',
				'description' => 'Dónde se parten los precios, en euros y separados por comas. Con 40, 70 y 150 salen «Hasta 40 €», «40 a 70 €», «70 a 150 €» y «Más de 150 €».',
			)
		);

		$this->end_controls_section();

		// ── Estilo: los colores de la casa ────────────────────────────
		$this->start_controls_section( 'colores', array( 'label' => 'Colores de la casa', 'tab' => Controls_Manager::TAB_STYLE ) );

		$this->add_control(
			'nota_colores',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => 'De fábrica van enlazados a los colores globales del Kit (el icono del globo), los mismos que usa el explorador. Si un color se desenlaza, vale solo para este bloque.',
				'content_classes' => 'elementor-descriptor',
			)
		);
		$this->token( 'color_tinta', 'Tinta', 'tinta', Global_Colors::COLOR_SECONDARY, '', 'Las preguntas, los nombres de los vinos y los precios.' );
		$this->token( 'color_texto', 'Texto', 'texto', Global_Colors::COLOR_TEXT, '', 'Bodegas, zonas, cuentas y notas.' );
		$this->token( 'color_acento', 'Acento', 'rojo', Global_Colors::COLOR_PRIMARY, '', 'La palabra en cursiva de cada pregunta, los pasos, el corazón y el botón lleno.' );
		$this->token( 'color_realce', 'Realce', 'naranja', Global_Colors::COLOR_ACCENT, '', 'El botón lleno al pasar por encima, y el tono de los generosos.' );
		$this->token( 'color_fondo', 'Fondo', 'crema', null, '', 'El fondo del bloque. Si no se toca, el crema de la casa.' );
		$this->token( 'color_papel', 'Papel', 'crema2', null, '', 'Las barras de precio y las etiquetas pequeñas.' );
		$this->token( 'color_borde', 'Bordes', 'borde', null, '', 'Filetes, fichas y botones.' );

		$this->end_controls_section();

		// ── Estilo: los colores del vino ──────────────────────────────
		$this->start_controls_section( 'colores_vino', array( 'label' => 'Colores del vino', 'tab' => Controls_Manager::TAB_STYLE ) );

		$this->add_control(
			'nota_vino',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => 'El color del vino en la copa, el mismo que en el explorador. Las baldosas de cada tipo lo mezclan con la tinta o el crema de la casa.',
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
				'raw'             => 'Si no se elige nada, las preguntas y los nombres llevan la tipografía Principal del Kit y el texto, la de Texto. Los tamaños los pone el bloque.',
				'content_classes' => 'elementor-descriptor',
			)
		);
		$this->add_control(
			'letra_titulares',
			array(
				'label'     => 'Preguntas y nombres',
				'type'      => Controls_Manager::FONT,
				'default'   => '',
				'selectors' => array( '{{WRAPPER}} .cv-toques' => '--cv-didona: "{{VALUE}}", Georgia, serif;' ),
			)
		);
		$this->add_control(
			'letra_texto',
			array(
				'label'     => 'Texto',
				'type'      => Controls_Manager::FONT,
				'default'   => '',
				'selectors' => array( '{{WRAPPER}} .cv-toques' => '--cv-sans: "{{VALUE}}", system-ui, sans-serif;' ),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo Caracool_Vinos_Toques::html( // phpcs:ignore WordPress.Security.EscapeOutput
			array(
				'aqui'   => isset( $s['aqui'] ) ? $s['aqui'] : '',
				'tramos' => isset( $s['tramos'] ) ? $s['tramos'] : '',
			)
		);
	}
}
