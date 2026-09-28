<?php
/**
 * Plugin Name:  Caracool Vinos
 * Plugin URI:   https://github.com/caracoolnet/wp-caracool-vinos
 * Description:  La carta de vinos de un restaurante, publicada como una página que se filtra por tipo, uva, bodega, zona y precio, con un globo que gira hasta la zona de cada vino y una red de países, zonas, bodegas y uvas. La carta la lee y la publica Caracool en Bodega; esta web la recibe con su licencia.
 * Version:           0.5.8
 * Author:       Caracool
 * Author URI:   https://caracool.net
 * License:      GPL-2.0-or-later
 * Text Domain:  caracool-vinos
 *
 * ARQUITECTURA
 * ─────────────────────────────────────────────────────────────────────
 * Este archivo es deliberadamente delgado, como en Caracool Carta. No
 * sabe pintar un globo: dibuja la página de ajustes, cuelga el plugin del
 * menú compartido de Caracool, mira si hay versión nueva en GitHub y carga
 * los módulos que existan en /modules.
 *
 * Desde la 0.5.0 es un cliente ligero de Bodega (bodega.caracool.net): el
 * Word lo lee y lo publica Caracool allí, y esta web recibe la carta ya
 * leída con su licencia. Sin Bodega, la web sigue con la última carta que
 * recibió; sin licencia en vigor, no recibe ninguna nueva.
 *
 *   caracool_vinos_settings_panels      → cada módulo cuelga aquí sus pestañas
 *   caracool_vinos_formularios_sueltos  → formularios con archivo, fuera del de ajustes
 *   caracool_vinos_defaults             → cada módulo declara sus ajustes
 *   caracool_vinos_sanear               → cada módulo limpia los suyos al guardar
 *
 * Los módulos:
 *
 *   cv-datos.php       la carta vigente y la anterior, tal y como llegan de Bodega
 *   cv-conexion.php    recoger la carta de Bodega: aviso, rutina y botón; la licencia
 *   cv-ajustes.php     lo que se puede tocar: precios, ubicación, textos, escena
 *   cv-explorador.php  la página: shortcode, widget de Elementor, CSS y JS
 *
 *   inc/caracool-licencia.php  la licencia, el eco, el aviso y las peticiones a Bodega
 *
 * En el navegador hacen falta tres librerías (d3-geo, d3-array y topojson)
 * y la geometría del mundo: van dentro del plugin, en /assets, nunca de un CDN.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CARACOOL_VINOS_VERSION', '0.5.8' );
define( 'CARACOOL_VINOS_FILE', __FILE__ );
define( 'CARACOOL_VINOS_DIR', plugin_dir_path( __FILE__ ) );
define( 'CARACOOL_VINOS_URL', plugin_dir_url( __FILE__ ) );
define( 'CARACOOL_VINOS_REPO', 'caracoolnet/wp-caracool-vinos' );

require_once CARACOOL_VINOS_DIR . 'inc/caracool-menu.php';
require_once CARACOOL_VINOS_DIR . 'inc/caracool-licencia.php';

final class Caracool_Vinos {

	const OPCION = 'caracool_vinos_ajustes';

	public function __construct() {
		foreach ( array( 'cv-datos.php', 'cv-conexion.php', 'cv-ajustes.php', 'cv-explorador.php' ) as $modulo ) {
			$ruta = CARACOOL_VINOS_DIR . 'modules/' . $modulo;
			if ( file_exists( $ruta ) ) {
				require_once $ruta;
			}
		}

		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_caracool_vinos_save', array( $this, 'guardar' ) );

		add_filter( 'caracool_plugins', array( $this, 'presentarse' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'enlace_ajustes' ) );

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'update_details' ), 10, 3 );
	}

	public static function ajustes() {
		return wp_parse_args(
			get_option( self::OPCION, array() ),
			apply_filters( 'caracool_vinos_defaults', array() )
		);
	}

	public function enlace_ajustes( $enlaces ) {
		array_unshift( $enlaces, '<a href="' . esc_url( admin_url( 'admin.php?page=caracool-vinos' ) ) . '">Ajustes</a>' );
		return $enlaces;
	}

	// ── Menú y guardado ─────────────────────────────────────────────────

	public function menu() {
		add_submenu_page(
			CARACOOL_MENU_SLUG,
			'Caracool Vinos',
			'Vinos',
			'manage_options',
			'caracool-vinos',
			array( $this, 'render_settings_page' )
		);
	}

	public function presentarse( $lista ) {
		$lista[] = array(
			'nombre'  => 'Vinos',
			'pagina'  => 'caracool-vinos',
			'version' => CARACOOL_VINOS_VERSION,
			'resumen' => 'La carta de vinos, con filtros, globo y red; la publica Caracool en Bodega',
		);
		return $lista;
	}

	public function guardar() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sin permisos.' );
		}
		check_admin_referer( 'caracool_vinos_save' );

		$bruto  = isset( $_POST['cv'] ) && is_array( $_POST['cv'] ) ? wp_unslash( $_POST['cv'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$limpio = apply_filters( 'caracool_vinos_sanear', array(), $bruto );

		update_option( self::OPCION, $limpio, false );
		do_action( 'caracool_vinos_ajustes_guardados', $limpio );

		wp_safe_redirect( add_query_arg( 'guardado', '1', admin_url( 'admin.php?page=caracool-vinos#cv-ajustes' ) ) );
		exit;
	}

	// ── Página de ajustes ───────────────────────────────────────────────

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$guardado = isset( $_GET['guardado'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$ok       = isset( $_GET['cv_ok'] ) ? sanitize_text_field( wp_unslash( $_GET['cv_ok'] ) ) : ( isset( $_GET['cc_ok'] ) ? sanitize_text_field( wp_unslash( $_GET['cc_ok'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$error    = isset( $_GET['cv_error'] ) ? sanitize_text_field( wp_unslash( $_GET['cv_error'] ) ) : ( isset( $_GET['cc_error'] ) ? sanitize_text_field( wp_unslash( $_GET['cc_error'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification

		$carta = class_exists( 'Caracool_Vinos_Datos' ) ? Caracool_Vinos_Datos::actual() : null;
		?>
		<div class="cc-wrap">
			<style><?php echo self::estilos_admin(); // phpcs:ignore WordPress.Security.EscapeOutput ?></style>

			<header class="cc-head">
				<div class="cc-brand">
					<?php echo self::logo_svg(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<div class="cc-brand-txt">
						<h1>Vinos <span class="cc-badge cc-badge-ver">v<?php echo esc_html( CARACOOL_VINOS_VERSION ); ?></span></h1>
						<p>La carta de vinos, con filtros, globo y red; la publica Caracool en Bodega</p>
					</div>
				</div>
				<?php if ( $carta && ! empty( $carta['vinos'] ) ) : ?>
					<span class="cc-chip"><i></i>Carta del <?php echo esc_html( Caracool_Vinos_Datos::fecha_legible( $carta['fecha'] ) ); ?> · <?php echo count( $carta['vinos'] ); ?> vinos</span>
				<?php else : ?>
					<span class="cc-chip cc-chip-ojo"><i></i>Sin carta publicada</span>
				<?php endif; ?>
			</header>

			<?php if ( $guardado ) : ?>
				<div class="cc-aviso">Configuración guardada.</div>
			<?php endif; ?>
			<?php if ( $ok ) : ?>
				<div class="cc-aviso"><?php echo esc_html( $ok ); ?></div>
			<?php endif; ?>
			<?php if ( $error ) : ?>
				<div class="cc-aviso cc-aviso-malo"><?php echo esc_html( $error ); ?></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="cv-ajustes-form">
				<input type="hidden" name="action" value="caracool_vinos_save">
				<?php wp_nonce_field( 'caracool_vinos_save' ); ?>

				<nav class="cc-tabs" id="cc-tabs" aria-label="Secciones"></nav>

				<?php do_action( 'caracool_vinos_settings_panels' ); ?>
			</form>

			<?php
			/**
			 * Formularios sueltos, fuera del de ajustes.
			 *
			 * Un <form> dentro de otro <form> es HTML inválido: el navegador
			 * descarta el de dentro y su botón acaba enviando el de fuera, que
			 * no lleva enctype. Resultado: el archivo no llega nunca. Por eso
			 * los módulos que necesiten su propio formulario lo cuelgan aquí,
			 * y en su tarjeta usan el atributo form="…" para apuntar a él.
			 */
			do_action( 'caracool_vinos_formularios_sueltos' );
			?>

			<footer class="cc-pie">Hecho con <span class="cc-corazon">&#10084;</span> por Caracool</footer>
		</div>
		<script>
		(function(){
			// Una pestaña por módulo. Cada módulo pinta su <section class="cc-modulo" data-titulo="…">;
			// aquí solo se construye la barra. El botón de guardar vive en la pestaña de ajustes.
			var nav=document.getElementById('cc-tabs'),secs=Array.prototype.slice.call(document.querySelectorAll('.cc-modulo'));
			if(!nav||secs.length<2){return;}
			var clave='ccVinosTab',activa=null;
			try{activa=localStorage.getItem(clave);}catch(e){}
			if(location.hash&&document.getElementById(location.hash.slice(1))){activa=location.hash.slice(1);}
			if(!activa||!document.getElementById(activa)){activa=secs[0].id;}
			function ir(id){
				secs.forEach(function(s){s.hidden=(s.id!==id);});
				Array.prototype.forEach.call(nav.children,function(b){b.classList.toggle('activa',b.dataset.id===id);b.setAttribute('aria-selected',b.dataset.id===id?'true':'false');});
				try{localStorage.setItem(clave,id);}catch(e){}
			}
			secs.forEach(function(s){
				var b=document.createElement('button');b.type='button';b.className='cc-tab';b.dataset.id=s.id;b.setAttribute('role','tab');
				b.textContent=s.dataset.titulo||s.id.replace(/^cv-/,'');
				if(s.dataset.aviso){var i=document.createElement('i');i.className='cc-tab-punto';i.title=s.dataset.aviso;b.appendChild(i);}
				b.addEventListener('click',function(){ir(s.id);history.replaceState(null,'','#'+s.id);});
				nav.appendChild(b);
			});
			ir(activa);
		})();
		</script>
		<?php
	}

	// ── Sistema visual compartido de los plugins de Caracool ────────────

	private static function estilos_admin() {
		// Mismos valores que Caracool Motion y Carta: el negro manda en lo
		// interactivo (pestaña activa, interruptores, botón) y la terracota se
		// reserva para los iconos de las tarjetas.
		return '
		.cc-wrap{--cc-acento:#c1502e;--cc-acento-ink:#7a3319;--cc-acento-suave:#f4e3dc;
			--cc-ok:#2f7a4f;--cc-ok-suave:#e3f1e8;--cc-ojo:#8a5a12;--cc-ojo-suave:#fbf0dc;
			--cc-malo:#a3271a;--cc-malo-suave:#fbe6e2;
			--cc-tinta:#1c1b19;--cc-tinta-suave:#6b6660;--cc-tinta-tenue:#a29c93;
			--cc-panel:#ffffff;--cc-fondo:#f6f5f2;--cc-linea:#e7e4de;
			--cc-r-grande:16px;--cc-r-medio:10px;--cc-r-chico:7px;
			--cc-sombra:0 1px 2px rgba(28,27,25,.04), 0 8px 24px -12px rgba(28,27,25,.12);
			max-width:1040px;margin:20px 20px 40px 0;color:var(--cc-tinta);
			font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;}
		.cc-wrap *{box-sizing:border-box;}
		.cc-head{display:flex;align-items:center;justify-content:space-between;gap:16px;
			padding:6px 4px 18px;margin-bottom:6px;}
		.cc-brand{display:flex;align-items:center;gap:16px;}
		.cc-logo{height:30px;width:auto;display:block;flex:none;}
		.cc-brand-txt{border-left:1px solid var(--cc-linea);padding-left:16px;}
		.cc-brand h1{margin:0;font-size:19px;line-height:1.2;display:flex;align-items:center;gap:10px;}
		.cc-brand p{margin:3px 0 0;font-size:12.5px;color:var(--cc-tinta-suave);}
		.cc-badge{display:inline-block;font-size:11px;font-weight:600;letter-spacing:.03em;
			padding:3px 9px;border-radius:var(--cc-r-chico);}
		.cc-badge-ver{background:var(--cc-fondo);color:var(--cc-tinta-suave);}
		.cc-chip{display:inline-flex;align-items:center;gap:7px;background:var(--cc-ok-suave);
			color:var(--cc-ok);font-size:12px;font-weight:600;padding:6px 13px;border-radius:999px;}
		.cc-chip i{width:6px;height:6px;border-radius:50%;background:currentColor;display:block;}
		.cc-chip-ojo{background:var(--cc-ojo-suave);color:var(--cc-ojo);}
		.cc-aviso{background:var(--cc-ok-suave);color:var(--cc-ok);border-radius:var(--cc-r-medio);
			padding:12px 16px;margin-bottom:16px;font-size:13.5px;}
		.cc-aviso-malo{background:var(--cc-malo-suave);color:var(--cc-malo);}
		.cc-aviso-ojo{background:var(--cc-ojo-suave);color:var(--cc-ojo);}
		.cc-modulo{display:block;}
		.cc-modulo[hidden]{display:none;}
		.cc-tabs{display:flex;flex-wrap:wrap;gap:4px;margin:0 0 18px;border-bottom:1px solid var(--cc-linea);}
		.cc-tab{background:none;border:0;border-bottom:2px solid transparent;margin-bottom:-1px;
			padding:10px 14px;font-size:13.5px;font-weight:600;color:var(--cc-tinta-suave);cursor:pointer;
			display:inline-flex;align-items:center;gap:8px;}
		.cc-tab:hover{color:var(--cc-tinta);}
		.cc-tab.activa{color:var(--cc-tinta);border-color:var(--cc-tinta);}
		.cc-tab:focus-visible{outline:2px solid var(--cc-tinta);outline-offset:2px;border-radius:4px;}
		.cc-tab-punto{width:7px;height:7px;border-radius:50%;background:var(--cc-acento);display:inline-block;}
		.cc-card{background:var(--cc-panel);border-radius:var(--cc-r-grande);box-shadow:var(--cc-sombra);
			padding:22px 24px;margin-bottom:16px;}
		.cc-card-head{display:flex;align-items:center;gap:12px;margin-bottom:6px;}
		.cc-card-icon{width:34px;height:34px;border-radius:50%;background:var(--cc-acento-suave);
			color:var(--cc-acento);display:flex;align-items:center;justify-content:center;flex:none;}
		.cc-card-icon svg{width:18px;height:18px;}
		.cc-card h2{margin:0;font-size:16px;}
		.cc-card-desc{margin:0 0 18px;font-size:13.5px;color:var(--cc-tinta-suave);max-width:70ch;}
		.cc-campo{display:grid;grid-template-columns:220px 1fr;gap:14px;align-items:start;
			padding:14px 0;border-top:1px solid var(--cc-linea);}
		.cc-campo:first-of-type{border-top:0;}
		.cc-campo > label{font-size:13.5px;font-weight:600;padding-top:6px;}
		.cc-hint{display:block;margin-top:6px;font-size:12.5px;color:var(--cc-tinta-tenue);max-width:60ch;}
		.cc-hint code{background:var(--cc-fondo);padding:1px 5px;border-radius:4px;}
		.cc-wrap select,.cc-wrap input[type=number],.cc-wrap input[type=text],.cc-wrap input[type=password],.cc-wrap textarea{
			border:1px solid var(--cc-linea);border-radius:var(--cc-r-medio);padding:7px 10px;
			font-size:13.5px;min-width:220px;background:#fff;color:var(--cc-tinta);}
		.cc-wrap textarea{min-height:70px;width:100%;max-width:60ch;font-family:inherit;line-height:1.5;}
		.cc-wrap textarea.cc-mono{font-family:ui-monospace,Menlo,monospace;font-size:12px;max-width:none;word-break:break-all;}
		.cc-wrap input[type=file]{font-size:13.5px;}
		.cc-sw{position:relative;display:inline-block;width:44px;height:25px;flex:none;vertical-align:middle;}
		.cc-sw input{opacity:0;width:0;height:0;}
		.cc-sw span{position:absolute;inset:0;background:#cfd3d8;border-radius:999px;
			transition:background .2s;cursor:pointer;}
		.cc-sw span:before{content:"";position:absolute;width:19px;height:19px;left:3px;top:3px;
			background:#fff;border-radius:50%;transition:transform .2s;}
		.cc-sw input:checked + span{background:var(--cc-tinta);}
		.cc-sw input:checked + span:before{transform:translateX(19px);}
		.cc-sw input:disabled + span{opacity:.45;cursor:not-allowed;}
		.cc-sw input:focus-visible + span{outline:2px solid var(--cc-tinta);outline-offset:2px;}
		.cc-eleccion{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;margin:4px 0 8px;}
		.cc-opcion{display:block;cursor:pointer;}
		.cc-opcion input{position:absolute;opacity:0;width:0;height:0;}
		.cc-opcion-caja{display:block;border:2px solid var(--cc-linea);border-radius:var(--cc-r-grande);padding:12px;
			transition:border-color .15s,box-shadow .15s;}
		.cc-opcion-caja svg{display:block;width:100%;height:auto;border-radius:6px;margin-bottom:10px;}
		.cc-opcion-caja b{display:block;font-size:13.5px;margin-bottom:3px;}
		.cc-opcion-caja small{display:block;font-size:12.5px;color:var(--cc-tinta-tenue);line-height:1.45;}
		.cc-opcion:hover .cc-opcion-caja{border-color:var(--cc-tinta-suave);}
		.cc-opcion input:checked + .cc-opcion-caja{border-color:var(--cc-tinta);box-shadow:0 0 0 1px var(--cc-tinta);}
		.cc-opcion input:focus-visible + .cc-opcion-caja{outline:2px solid var(--cc-tinta);outline-offset:2px;}
		.cc-acciones{margin:22px 0 0;display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
		.cc-btn{display:inline-flex;align-items:center;gap:9px;border:0;border-radius:999px;
			padding:12px 26px;font-size:13.5px;font-weight:600;cursor:pointer;}
		.cc-btn svg{width:15px;height:15px;}
		.cc-btn-primario{background:var(--cc-tinta);color:#fff;}
		.cc-btn-primario:hover{background:#000;}
		.cc-btn-ghost{background:transparent;color:var(--cc-tinta);border:1px solid var(--cc-linea);}
		.cc-btn-ghost:hover{border-color:var(--cc-tinta);}
		.cc-btn-malo{background:transparent;color:var(--cc-malo);border:1px solid var(--cc-malo-suave);}
		.cc-btn-malo:hover{border-color:var(--cc-malo);}
		.cc-pie{margin-top:26px;text-align:center;font-size:12px;color:var(--cc-tinta-tenue);}
		.cc-corazon{color:var(--cc-acento);}
		.cc-tabla{width:100%;border-collapse:collapse;font-size:13.5px;margin-top:8px;}
		.cc-tabla th{text-align:left;font-size:11px;letter-spacing:.08em;text-transform:uppercase;
			color:var(--cc-tinta-tenue);border-bottom:1px solid var(--cc-linea);padding:0 12px 8px 0;}
		.cc-tabla td{padding:10px 12px 10px 0;border-bottom:1px solid var(--cc-linea);vertical-align:top;
			color:var(--cc-tinta-suave);}
		.cc-tabla td code{background:var(--cc-fondo);padding:2px 6px;border-radius:4px;color:var(--cc-tinta);}
		.cc-tabla td.num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap;}
		.cc-tabla td b{color:var(--cc-tinta);font-weight:600;}
		.cc-cifras{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin:6px 0 4px;}
		.cc-cifra{background:var(--cc-fondo);border-radius:var(--cc-r-medio);padding:14px 16px;}
		.cc-cifra b{display:block;font-size:22px;line-height:1.1;font-weight:600;color:var(--cc-tinta);font-variant-numeric:tabular-nums;}
		.cc-cifra span{display:block;margin-top:4px;font-size:12px;color:var(--cc-tinta-suave);}
		.cc-cifra.ok b{color:var(--cc-ok);} .cc-cifra.ojo b{color:var(--cc-ojo);} .cc-cifra.malo b{color:var(--cc-malo);}
		.cc-wrap details{margin-top:12px;border-top:1px solid var(--cc-linea);padding-top:10px;}
		.cc-wrap details summary{cursor:pointer;font-size:13.5px;font-weight:600;color:var(--cc-tinta);padding:4px 0;}
		.cc-wrap details summary small{font-weight:400;color:var(--cc-tinta-tenue);margin-left:6px;}
		.cc-lista{margin:8px 0 0;padding:0;list-style:none;font-size:13px;color:var(--cc-tinta-suave);}
		.cc-lista li{padding:6px 0;border-bottom:1px solid var(--cc-linea);}
		.cc-lista li b{color:var(--cc-tinta);font-weight:600;}
		.cc-sube{color:var(--cc-malo);} .cc-baja{color:var(--cc-ok);}
		@media (max-width:782px){.cc-campo{grid-template-columns:1fr;}}
		';
	}

	/** Logotipo de Caracool incrustado como markup, sin archivo de imagen
	 *  aparte — mismo criterio que el resto de plugins de la agencia. */
	private static function logo_svg() {
		$p = array(
			'M39.23,9.44c0,4.65-.8,7.71-2.13,9.98-1.6-1.2-3.99-2.26-6.92-2.26-7.58,0-14.5,6.92-14.5,35.91,0,23.14,2.93,30.06,10.37,30.06,4.12,0,7.85-.67,10.37-2,1.06,2.26,2,5.59,2,10.37,0,3.86-6.12,8.25-14.1,8.25-15.16,0-24.34-3.99-24.34-45.49C0,10.64,14.63,2.66,26.6,2.66c11.31,0,12.64,3.86,12.64,6.78',
			'M41.9,36.84c0-1.6.27-3.46,1.46-4.39,1.6-1.33,9.58-3.19,23.41-3.19,9.04,0,13.57,4.26,13.57,16.23v6.92c0,22.74-.67,43.09-.67,43.09-4.52,2.66-11.17,4.26-19.15,4.26-9.84,0-18.89-.66-18.89-21.41,0-18.22,7.45-21.94,14.76-21.94,2.53,0,6.65.4,9.04,1.99v-9.84c0-3.46-1.46-4.92-4.39-4.92-5.05,0-12.77,1.2-17.16,2.79-1.86-3.06-2-8.51-2-9.58M65.44,68.36c-.93-.93-2.39-1.06-3.46-1.06-2.93,0-4.65,2.13-4.65,10.64s.67,9.58,3.99,9.58c.93,0,3.06-.27,3.86-1.33,0,0,.27-8.51.27-17.82',
			'M88.31,35.24c6.12-4.26,11.04-5.98,18.49-5.98s8.91,1.33,8.91,5.98c0,2.39-.27,5.98-1.46,9.31-1.86-.93-3.59-1.06-4.92-1.06-1.6,0-4.12.8-5.72,3.06l-.13,48.54q0,2.66-15.16,2.66v-62.51Z',
			'M119.96,36.84c0-1.6.27-3.46,1.46-4.39,1.6-1.33,9.58-3.19,23.41-3.19,9.04,0,13.57,4.26,13.57,16.23v6.92c0,22.74-.67,43.09-.67,43.09-4.52,2.66-11.17,4.26-19.15,4.26-9.84,0-18.88-.66-18.88-21.41,0-18.22,7.45-21.94,14.76-21.94,2.53,0,6.65.4,9.04,1.99v-9.84c0-3.46-1.46-4.92-4.39-4.92-5.05,0-12.77,1.2-17.16,2.79-1.86-3.06-2-8.51-2-9.58M143.51,68.36c-.93-.93-2.39-1.06-3.46-1.06-2.93,0-4.65,2.13-4.65,10.64s.66,9.58,3.99,9.58c.93,0,3.06-.27,3.86-1.33,0,0,.27-8.51.27-17.82',
			'M202.69,36.97c-2.53-1.86-4.65-3.59-9.84-3.59-9.44,0-20.22,3.99-20.22,33.12,0,26.6,6.92,29.39,17.82,29.39,5.19,0,9.44-2.39,12.5-4.92.53.93.8,2.13.8,3.06,0,1.73-6.38,5.99-13.7,5.99-12.9,0-21.81-2.53-21.81-34.05,0-33.12,13.43-36.71,24.21-36.71,5.59,0,11.17,2.79,11.17,4.26,0,1.06-.27,2.53-.93,3.46',
			'M234.21,29.26c13.43,0,20.75,8.11,20.75,35.38,0,23.27-7.98,35.11-21.28,35.11s-21.41-4.92-21.41-34.98c0-24.87,8.25-35.51,21.94-35.51M233.01,95.62c10.64,0,17.42-10.37,17.42-31.39,0-24.47-6.12-30.85-16.23-30.85s-17.55,8.51-17.55,32.18,5.99,30.06,16.36,30.06',
			'M286.88,29.26c13.43,0,20.75,8.11,20.75,35.38,0,23.27-7.98,35.11-21.28,35.11s-21.41-4.92-21.41-34.98c0-24.87,8.25-35.51,21.94-35.51M285.68,95.62c10.64,0,17.42-10.37,17.42-31.39,0-24.47-6.12-30.85-16.23-30.85s-17.55,8.51-17.55,32.18,5.99,30.06,16.36,30.06',
			'M325.71,96.29c0,1.46.27,1.46-4.39,1.46V2.39c0-2.39,1.07-2.39,4.39-2.39v96.29Z',
		);

		$svg = '<svg class="cc-logo" viewBox="0 0 325.72 100.01" role="img" aria-label="Caracool">';
		foreach ( $p as $d ) {
			$svg .= '<path d="' . $d . '" fill="#1a1a1a"/>';
		}
		return $svg . '</svg>';
	}

	// ── Comprobador de actualizaciones contra las releases de GitHub ────

	private function ultima_release() {
		$cache = get_site_transient( 'caracool_vinos_release' );
		if ( is_array( $cache ) ) {
			return $cache;
		}

		$r = wp_remote_get(
			'https://api.github.com/repos/' . CARACOOL_VINOS_REPO . '/releases/latest',
			array(
				'timeout' => 8,
				'headers' => array( 'Accept' => 'application/vnd.github+json' ),
			)
		);

		if ( is_wp_error( $r ) || 200 !== wp_remote_retrieve_response_code( $r ) ) {
			set_site_transient( 'caracool_vinos_release', array(), 30 * MINUTE_IN_SECONDS );
			return array();
		}

		$data = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		set_site_transient( 'caracool_vinos_release', $data, 6 * HOUR_IN_SECONDS );
		return $data;
	}

	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$rel = $this->ultima_release();
		if ( empty( $rel['tag_name'] ) ) {
			return $transient;
		}

		$nueva = ltrim( $rel['tag_name'], 'vV' );
		if ( version_compare( $nueva, CARACOOL_VINOS_VERSION, '<=' ) ) {
			return $transient;
		}

		$zip = '';
		if ( ! empty( $rel['assets'][0]['browser_download_url'] ) ) {
			$zip = $rel['assets'][0]['browser_download_url'];
		} elseif ( ! empty( $rel['zipball_url'] ) ) {
			$zip = $rel['zipball_url'];
		}

		$slug = plugin_basename( CARACOOL_VINOS_FILE );

		$item              = new stdClass();
		$item->slug        = 'caracool-vinos';
		$item->plugin      = $slug;
		$item->new_version = $nueva;
		$item->url         = 'https://github.com/' . CARACOOL_VINOS_REPO;
		$item->package     = $zip;
		$item->tested      = get_bloginfo( 'version' );

		$transient->response[ $slug ] = $item;

		return $transient;
	}

	public function update_details( $res, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || 'caracool-vinos' !== $args->slug ) {
			return $res;
		}

		$rel = $this->ultima_release();
		if ( empty( $rel['tag_name'] ) ) {
			return $res;
		}

		$info               = new stdClass();
		$info->name         = 'Caracool Vinos';
		$info->slug         = 'caracool-vinos';
		$info->version      = ltrim( $rel['tag_name'], 'vV' );
		$info->author       = '<a href="https://caracool.net">Caracool</a>';
		$info->homepage     = 'https://github.com/' . CARACOOL_VINOS_REPO;
		$info->sections     = array(
			'description' => 'La carta de vinos de un restaurante, leída del Word que ya edita el propio restaurante, con filtros y un globo que gira hasta la zona de cada vino.',
			'changelog'   => isset( $rel['body'] ) ? nl2br( esc_html( $rel['body'] ) ) : '',
		);
		$info->download_link = ! empty( $rel['assets'][0]['browser_download_url'] )
			? $rel['assets'][0]['browser_download_url']
			: ( isset( $rel['zipball_url'] ) ? $rel['zipball_url'] : '' );

		return $info;
	}
}

new Caracool_Vinos();

// ── Desactivación ───────────────────────────────────────────────────────

register_deactivation_hook(
	__FILE__,
	function () {
		delete_site_transient( 'caracool_vinos_release' );
		if ( class_exists( 'Caracool_Vinos_Conexion' ) ) {
			Caracool_Vinos_Conexion::desprogramar();
		}
	}
);
