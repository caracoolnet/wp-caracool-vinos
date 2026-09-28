<?php
/**
 * Menú compartido de los plugins de Caracool.
 *
 * ─────────────────────────────────────────────────────────────────────
 * ARCHIVO COMÚN. Es el mismo, byte a byte, en Caracool Motion, Caracool
 * Carta, Caracool OneStep y Caracool Churra. Si lo tocas, tócalo en todos.
 *
 * Fuente de verdad: github.com/caracoolnet/wp-caracool-shared, carpeta
 * inc/. Antes de publicar una versión nueva de cualquier plugin de la
 * casa, se compara esta copia con la de ese repo y se sustituye si no
 * coincide, para que las cuatro no se desincronicen con el tiempo.
 *
 * No hay dependencia entre plugins: ninguno necesita a los demás y todos
 * funcionan solos. Lo único que comparten es el acuerdo de colgarse de un
 * mismo slug. El primero que carga crea el menú padre y los que vienen
 * detrás se lo encuentran hecho, en cualquier orden y con cualquier
 * combinación instalada.
 *
 * Cómo se usa desde el plugin:
 *
 *   require_once … 'inc/caracool-menu.php';
 *
 *   add_action( 'admin_menu', function () {
 *       add_submenu_page( CARACOOL_MENU_SLUG, 'Churra', 'Churra',
 *           'manage_options', 'caracool-churra', array( $this, 'render' ) );
 *   } );                                   // prioridad 10, por defecto
 *
 *   add_filter( 'caracool_plugins', function ( $l ) {
 *       $l[] = array( 'nombre' => 'Churra', 'pagina' => 'caracool-churra',
 *                     'version' => CARACOOL_CHURRA_VERSION,
 *                     'resumen' => 'Movimiento y cabecera de elchurrarestaurante.com' );
 *       return $l;
 *   } );
 * ─────────────────────────────────────────────────────────────────────
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'CARACOOL_MENU_SLUG' ) ) {
	define( 'CARACOOL_MENU_SLUG', 'caracool' );
}

if ( ! function_exists( 'caracool_menu_padre' ) ) {

	/**
	 * Crea «Caracool» en la barra lateral si no lo ha creado ya otro plugin.
	 *
	 * Va en prioridad 9 a propósito: los submenús se registran en la 10, que
	 * es la de por defecto, así que cuando les toque el padre ya existe.
	 */
	function caracool_menu_padre() {
		global $admin_page_hooks;

		if ( isset( $admin_page_hooks[ CARACOOL_MENU_SLUG ] ) ) {
			return;
		}

		add_menu_page(
			'Caracool',
			'Caracool',
			'manage_options',
			CARACOOL_MENU_SLUG,
			'caracool_menu_portada',
			caracool_menu_icono(),
			58
		);
	}
	add_action( 'admin_menu', 'caracool_menu_padre', 9 );

	/**
	 * La C del logotipo, incrustada.
	 *
	 * Va en el gris de los iconos del menú de WordPress. Un SVG en data URI
	 * no lo recolorea WordPress al pasar por encima ni al estar activo, así
	 * que se queda quieto: es el precio de llevar la marca de la casa en vez
	 * de un dashicon.
	 *
	 * El viewBox es cuadrado (101x101) a propósito, aunque el trazo real de
	 * la C solo ocupa un ~39% del ancho: WordPress escala los iconos de menú
	 * a un ancho fijo de 20px dejando la altura libre ("background-size:
	 * 20px auto"), así que un viewBox mucho más alto que ancho (el trazo
	 * real mide 39x97) se renderiza más alto que el hueco del icono en la
	 * barra lateral (~34px) y se sale por arriba y por abajo. Centrando el
	 * trazo en un lienzo cuadrado, WordPress lo escala a 20x20 y cabe
	 * entero — se ve más pequeña que si ocupara todo el ancho, pero sin
	 * recortes.
	 */
	function caracool_menu_icono() {
		$c = 'M39.23,9.44c0,4.65-.8,7.71-2.13,9.98-1.6-1.2-3.99-2.26-6.92-2.26-7.58,0-14.5,6.92-14.5,35.91,'
			. '0,23.14,2.93,30.06,10.37,30.06,4.12,0,7.85-.67,10.37-2,1.06,2.26,2,5.59,2,10.37,0,3.86-6.12,'
			. '8.25-14.1,8.25-15.16,0-24.34-3.99-24.34-45.49C0,10.64,14.63,2.66,26.6,2.66c11.31,0,12.64,3.86,12.64,6.78';

		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-30.89 0 101 101">'
			. '<path d="' . $c . '" fill="#a7aaad"/></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	/**
	 * La portada del menú: qué plugins de la casa hay puestos en esta web.
	 *
	 * Cada uno se apunta por el filtro `caracool_plugins`. Nadie pregunta por
	 * nadie: quien no esté instalado simplemente no aparece.
	 */
	function caracool_menu_portada() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$lista = apply_filters( 'caracool_plugins', array() );
		?>
		<div class="wrap">
			<h1>Caracool</h1>
			<p>Los plugins de la casa que hay puestos en esta web.</p>
			<table class="widefat striped" style="max-width:860px;margin-top:16px">
				<thead>
					<tr><th>Plugin</th><th>Qué hace</th><th>Versión</th></tr>
				</thead>
				<tbody>
				<?php if ( empty( $lista ) ) : ?>
					<tr><td colspan="3">Ninguno se ha presentado todavía.</td></tr>
				<?php else : ?>
					<?php foreach ( $lista as $p ) : ?>
						<tr>
							<td>
								<strong>
									<?php if ( ! empty( $p['pagina'] ) ) : ?>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $p['pagina'] ) ); ?>"><?php echo esc_html( $p['nombre'] ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $p['nombre'] ); ?>
									<?php endif; ?>
								</strong>
							</td>
							<td><?php echo esc_html( isset( $p['resumen'] ) ? $p['resumen'] : '' ); ?></td>
							<td><?php echo esc_html( isset( $p['version'] ) ? $p['version'] : '' ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
