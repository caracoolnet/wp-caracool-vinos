<?php
/**
 * Caracool Vinos — Ajustes
 * ─────────────────────────────────────────────────────────────────────
 * Lo poco que se decide desde el panel. Todo lo demás lo decide el Word.
 *
 *   (diseno, panel y esquinas los decide Caracool en Bodega desde la 0.5.7 y
 *   llegan con la carta; aquí se guardan los de antes, para las webs a las
 *   que Bodega todavía no ha dicho nada)
 *   diseno       cómo se coloca el explorador en la página:
 *                  columnas  el globo a la izquierda y la lista a la derecha,
 *                            debajo del hero de foto de la página
 *                  mundo     el globo llena la pantalla y hace de hero; la
 *                            lista flota encima. El hero de foto se apaga.
 *   escena       con qué escena se abre: de día o de noche
 *   dianoche     si el visitante tiene el sol y la luna para cambiarla
 *   mapa, red    las vistas que se enseñan, de las que da Bodega (Lista va siempre)
 *   panel        la piel del panel de filtros, sobre la misma estructura:
 *                  mosaico   fichas y baldosas con icono (la de El Churra)
 *                  pildoras  todo en píldoras con su icono
 *                  ficha     sin cajas, como una ficha de cata
 *   esquinas     web: las esquinas de los botones y campos del Kit de
 *                Elementor; diseno: las de la piel elegida
 *   rotulo       la línea pequeña encima del título (solo en «mundo»)
 *   titulo       el título de la página (solo en «mundo»; en «columnas» lo pone el hero)
 *   entradilla   la frase de debajo del título (solo en «mundo»)
 *   precios      si la web enseña los precios (lo decide la casa; el visitante no puede cambiarlo)
 *   ubicacion    si se ve la ubicación en bodega (el número o las letras de la carta)
 *   favoritos    el corazón, el botón y la vista «Mis vinos» y «Compartir»
 *   region       qué zonas van delante en la lista, separadas por comas
 *   nombre       cómo se llama la casa al compartir («… de la carta de vinos de El Churra»)
 *   pie          la línea de abajo de la lista
 *   vacio        lo que se lee si todavía no hay carta publicada
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Caracool_Vinos_Ajustes {

	public function __construct() {
		add_filter( 'caracool_vinos_defaults', array( $this, 'defaults' ) );
		add_filter( 'caracool_vinos_sanear', array( $this, 'sanear' ), 10, 2 );
		add_action( 'caracool_vinos_settings_panels', array( $this, 'panel' ), 30 );
	}

	public function defaults( $d ) {
		return array_merge(
			$d,
			array(
				'diseno'      => 'mundo',
				'escena'      => 'dia',
				'dianoche'    => 'si',
				'mapa'        => 'si',
				'panel'       => 'mosaico',
				'esquinas'    => 'web',
				'red'         => 'si',
				'rotulo'      => 'Carta de vinos',
				'titulo'      => 'Explorar los vinos',
				'entradilla'  => 'Elige un vino y el mundo gira hasta su zona. O filtra por tipo, uva, bodega y precio hasta dar con el tuyo.',
				'precios'     => 'si',
				'ubicacion'   => 'si',
				'favoritos'   => 'si',
				'region'      => 'Jumilla, Yecla, Bullas, Murcia',
				'nombre'      => get_bloginfo( 'name' ),
				'pie'         => 'Precios en euros, IVA incluido',
				'vacio'       => 'La carta de vinos se está preparando.',
			)
		);
	}

	public function sanear( $limpio, $bruto ) {
		foreach ( array( 'precios', 'ubicacion', 'favoritos', 'mapa', 'red', 'dianoche' ) as $k ) {
			$limpio[ $k ] = ( isset( $bruto[ $k ] ) && 'si' === $bruto[ $k ] ) ? 'si' : 'no';
		}
		foreach ( array( 'region', 'nombre', 'pie', 'vacio', 'rotulo', 'titulo', 'entradilla' ) as $k ) {
			$limpio[ $k ] = isset( $bruto[ $k ] ) ? sanitize_text_field( $bruto[ $k ] ) : '';
		}
		// el diseño, la piel y las esquinas ya no se tocan aquí (los manda Bodega):
		// si no vienen en el formulario, se quedan como estaban
		$antes            = Caracool_Vinos::ajustes();
		$limpio['diseno'] = isset( $bruto['diseno'] ) ? ( 'columnas' === $bruto['diseno'] ? 'columnas' : 'mundo' ) : $antes['diseno'];
		$limpio['escena'] = ( isset( $bruto['escena'] ) && 'noche' === $bruto['escena'] ) ? 'noche' : 'dia';
		$limpio['panel']    = isset( $bruto['panel'] ) ? ( in_array( $bruto['panel'], self::pieles(), true ) ? $bruto['panel'] : 'mosaico' ) : $antes['panel'];
		$limpio['esquinas'] = isset( $bruto['esquinas'] ) ? ( 'diseno' === $bruto['esquinas'] ? 'diseno' : 'web' ) : $antes['esquinas'];
		return $limpio;
	}

	/** Las pieles del panel de filtros. */
	public static function pieles() {
		return array( 'mosaico', 'pildoras', 'ficha' );
	}

	/**
	 * Las zonas que van delante, en mayúsculas como las escribe la carta. Si
	 * el nombre no coincide con ninguna zona de la carta vigente (acentos,
	 * artículos), se busca la más parecida sin acentos.
	 */
	public static function region() {
		$a     = Caracool_Vinos::ajustes();
		$carta = class_exists( 'Caracool_Vinos_Datos' ) ? Caracool_Vinos_Datos::actual() : null;
		$zonas = array();
		if ( $carta ) {
			foreach ( $carta['vinos'] as $v ) {
				if ( ! empty( $v['zona'] ) ) {
					$zonas[ self::llave( $v['zona'] ) ] = $v['zona'];
				}
			}
		}
		$out = array();
		foreach ( explode( ',', (string) $a['region'] ) as $z ) {
			$z = trim( $z );
			if ( '' !== $z ) {
				$k     = self::llave( $z );
				$out[] = isset( $zonas[ $k ] ) ? $zonas[ $k ] : mb_strtoupper( $z, 'UTF-8' );
			}
		}
		return $out;
	}

	private static function llave( $t ) {
		$t = mb_strtolower( trim( (string) $t ), 'UTF-8' );
		if ( function_exists( 'remove_accents' ) ) {
			$t = remove_accents( $t );
		}
		return preg_replace( '/[^a-z0-9]+/', '', $t );
	}

	public function panel() {
		$a = Caracool_Vinos::ajustes();
		?>
		<section class="cc-modulo" id="cv-ajustes" data-titulo="Ajustes">

			<div class="cc-card">
				<div class="cc-card-head">
					<span class="cc-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg></span>
					<h2>Diseño</h2>
				</div>
				<p class="cc-card-desc">Cómo se ve el explorador. El diseño, la piel del panel de filtros y las esquinas los decide Caracool desde Bodega; aquí se eligen las vistas que se enseñan, el día o la noche y los textos de arriba.</p>

				<?php
				$vista   = Caracool_Vinos_Explorador::vista();
				$pieles  = array( 'mosaico' => 'Mosaico', 'pildoras' => 'Píldoras', 'ficha' => 'Ficha' );
				$resumen = ( 'columnas' === $vista['diseno'] ? 'Columnas' : 'El mundo' ) . ' · panel ' . $pieles[ $vista['panel'] ] . ' · ' . ( 'web' === $vista['esquinas'] ? 'las esquinas de la web' : 'las esquinas de la piel' );
				?>
				<div class="cc-campo">
					<label>Lo decide Caracool</label>
					<div>
						<b><?php echo esc_html( $resumen ); ?></b>
						<span class="cc-hint"><?php echo $vista['bodega'] ? 'Llega de Bodega con la carta. Para cambiarlo, se le pide a Caracool.' : 'Caracool todavía no lo ha fijado desde Bodega, así que se queda como estaba.'; ?><?php echo 'web' === $vista['esquinas'] ? ' ' . esc_html( Caracool_Vinos_Explorador::esquinas_texto() ) : ''; ?></span>
					</div>
				</div>


				<div class="cc-eleccion" style="margin-top:18px">
					<label class="cc-opcion">
						<input type="radio" name="cv[escena]" value="dia" <?php checked( 'dia', $a['escena'] ); ?>>
						<span class="cc-opcion-caja">
							<svg viewBox="0 0 160 100" aria-hidden="true"><rect width="160" height="100" rx="3" fill="#FAF5EC"/><circle cx="52" cy="50" r="30" fill="#E9DCC6"/><circle cx="52" cy="50" r="30" fill="none" stroke="#725841" stroke-opacity=".4"/><rect x="96" y="18" width="52" height="64" rx="2" fill="#FAF5EC" stroke="#E3D8C6"/><rect x="102" y="28" width="40" height="3" rx="1.5" fill="#241610"/><rect x="102" y="38" width="32" height="3" rx="1.5" fill="#241610"/><rect x="102" y="48" width="36" height="3" rx="1.5" fill="#241610"/><rect x="102" y="58" width="28" height="3" rx="1.5" fill="#241610"/></svg>
							<b>De día</b>
							<small>Los colores de la web: tinta sobre crema. El globo y la red van en los colores del Kit.</small>
						</span>
					</label>
					<label class="cc-opcion">
						<input type="radio" name="cv[escena]" value="noche" <?php checked( 'noche', $a['escena'] ); ?>>
						<span class="cc-opcion-caja">
							<svg viewBox="0 0 160 100" aria-hidden="true"><rect width="160" height="100" rx="3" fill="#120B09"/><circle cx="40" cy="44" r="3" fill="#E0435A"/><circle cx="58" cy="60" r="2.5" fill="#F2DC8A"/><circle cx="30" cy="66" r="2" fill="#E0435A"/><circle cx="66" cy="36" r="2" fill="#FAF5EC"/><circle cx="50" cy="30" r="1.5" fill="#F2A0A6"/><circle cx="24" cy="38" r="1.5" fill="#F2DC8A"/><path d="M40 44 58 60M40 44 30 66M40 44 66 36M58 60 50 30" stroke="#FAF5EC" stroke-opacity=".25"/><rect x="96" y="18" width="52" height="64" rx="2" fill="#120B09" stroke="#3A2A22"/><rect x="102" y="28" width="40" height="3" rx="1.5" fill="#FAF5EC"/><rect x="102" y="38" width="32" height="3" rx="1.5" fill="#B9A78F"/><rect x="102" y="48" width="36" height="3" rx="1.5" fill="#FAF5EC"/><rect x="102" y="58" width="28" height="3" rx="1.5" fill="#B9A78F"/></svg>
							<b>De noche</b>
							<small>La bodega a oscuras: crema sobre cueva, y la red como un cielo con estrellas del color del vino.</small>
						</span>
					</label>
				</div>
				<p class="cc-hint">Es el valor por defecto. Desde el widget de Elementor se cambia página a página, en la pestaña Contenido.</p>

				<div class="cc-campo">
					<label for="cv-dianoche">Sol y luna</label>
					<div>
						<label class="cc-sw"><input type="checkbox" id="cv-dianoche" name="cv[dianoche]" value="si" <?php checked( 'si', $a['dianoche'] ); ?>><span></span></label>
						<span class="cc-hint">El visitante cambia de día a noche con el sol y la luna del panel, y se le recuerda en su navegador. Apagado, la escena es siempre la que se elige aquí.</span>
					</div>
				</div>

				<div class="cc-campo">
					<label>Vistas</label>
					<div>
						<?php foreach ( array( 'mapa' => 'Mapa', 'red' => 'Red' ) as $k => $nombre ) : ?>
							<?php if ( $vista[ 'mapa' === $k ? 'mapa_dado' : 'red_dada' ] ) : ?>
								<label style="display:inline-flex;align-items:center;gap:8px;margin:0 22px 6px 0;font-weight:600"><span class="cc-sw"><input type="checkbox" id="cv-<?php echo esc_attr( $k ); ?>" name="cv[<?php echo esc_attr( $k ); ?>]" value="si" <?php checked( 'si', $a[ $k ] ); ?>><span></span></span><?php echo esc_html( $nombre ); ?></label>
							<?php else : ?>
								<input type="hidden" name="cv[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $a[ $k ] ); ?>">
							<?php endif; ?>
						<?php endforeach; ?>
						<span style="display:inline-flex;align-items:center;gap:8px;margin:0 0 6px;font-weight:600;color:var(--cc-tinta-tenue)">Lista, siempre</span>
						<span class="cc-hint">Las que da Caracool. Mapa es el globo; Red, países, zonas, bodegas y uvas unidos por hilos, en 3D. Con solo Lista, la carta va en una columna, sin botones de vista.<?php echo ( $vista['mapa_dado'] && $vista['red_dada'] ) ? '' : ' Para tener ' . ( $vista['mapa_dado'] ? 'Red' : ( $vista['red_dada'] ? 'Mapa' : 'Mapa o Red' ) ) . ', se le pide a Caracool.'; ?></span>
					</div>
				</div>

				<div class="cc-campo">
					<label for="cv-rotulo">Rótulo</label>
					<div>
						<input type="text" id="cv-rotulo" name="cv[rotulo]" value="<?php echo esc_attr( $a['rotulo'] ); ?>">
						<span class="cc-hint">La línea pequeña encima del título. Solo se ve con «El mundo»; con «Columnas» el título lo pone el hero de la página.</span>
					</div>
				</div>

				<div class="cc-campo">
					<label for="cv-titulo">Título</label>
					<div>
						<input type="text" id="cv-titulo" name="cv[titulo]" value="<?php echo esc_attr( $a['titulo'] ); ?>">
					</div>
				</div>

				<div class="cc-campo">
					<label for="cv-entradilla">Entradilla</label>
					<div>
						<textarea id="cv-entradilla" name="cv[entradilla]" rows="2"><?php echo esc_textarea( $a['entradilla'] ); ?></textarea>
					</div>
				</div>
			</div>

			<div class="cc-card">
				<div class="cc-card-head">
					<span class="cc-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
					<h2>Qué se ve</h2>
				</div>
				<p class="cc-card-desc">Lo que la página enseña además de los vinos. El contenido siempre sale del Word; aquí solo se decide qué partes se ven.</p>

				<div class="cc-campo">
					<label for="cv-precios">Precios</label>
					<div>
						<label class="cc-sw"><input type="checkbox" id="cv-precios" name="cv[precios]" value="si" <?php checked( 'si', $a['precios'] ); ?>><span></span></label>
						<span class="cc-hint">Apagado, la web enseña la carta sin precios y sin el filtro de precio. Lo decide la casa: el visitante no tiene interruptor.</span>
					</div>
				</div>

				<div class="cc-campo">
					<label for="cv-ubicacion">Ubicación en bodega</label>
					<div>
						<label class="cc-sw"><input type="checkbox" id="cv-ubicacion" name="cv[ubicacion]" value="si" <?php checked( 'si', $a['ubicacion'] ); ?>><span></span></label>
						<span class="cc-hint">El número o las letras que la carta pone delante de cada vino, pequeño y apagado al final de la fila. Es para la sala.</span>
					</div>
				</div>

				<div class="cc-campo">
					<label for="cv-favoritos">Mis vinos</label>
					<div>
						<label class="cc-sw"><input type="checkbox" id="cv-favoritos" name="cv[favoritos]" value="si" <?php checked( 'si', $a['favoritos'] ); ?>><span></span></label>
						<span class="cc-hint">El corazón en cada vino, el botón y la vista «Mis vinos» y el enlace para compartirla. Se guarda en el navegador de cada persona, sin cuenta.</span>
					</div>
				</div>
			</div>

			<div class="cc-card">
				<div class="cc-card-head">
					<span class="cc-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7V4h16v3"/><path d="M9 20h6"/><path d="M12 4v16"/></svg></span>
					<h2>Textos y orden</h2>
				</div>

				<div class="cc-campo">
					<label for="cv-region">Zonas que van delante</label>
					<div>
						<input type="text" id="cv-region" name="cv[region]" value="<?php echo esc_attr( $a['region'] ); ?>">
						<span class="cc-hint">Separadas por comas y en este orden. Después va el resto de España por orden alfabético y luego los demás países.</span>
					</div>
				</div>

				<div class="cc-campo">
					<label for="cv-nombre">Nombre de la casa</label>
					<div>
						<input type="text" id="cv-nombre" name="cv[nombre]" value="<?php echo esc_attr( $a['nombre'] ); ?>">
						<span class="cc-hint">Para el texto de compartir: «Mis 4 vinos de la carta de vinos de <?php echo esc_html( $a['nombre'] ); ?>».</span>
					</div>
				</div>

				<div class="cc-campo">
					<label for="cv-pie">Pie de la lista</label>
					<div>
						<input type="text" id="cv-pie" name="cv[pie]" value="<?php echo esc_attr( $a['pie'] ); ?>">
						<span class="cc-hint">Sale detrás de «Carta del 9 de septiembre de 2026 · ».</span>
					</div>
				</div>

				<div class="cc-campo">
					<label for="cv-vacio">Si no hay carta</label>
					<div>
						<input type="text" id="cv-vacio" name="cv[vacio]" value="<?php echo esc_attr( $a['vacio'] ); ?>">
						<span class="cc-hint">Lo que lee el visitante mientras no haya ninguna carta publicada.</span>
					</div>
				</div>
			</div>

			<p class="cc-acciones">
				<button type="submit" class="cc-btn cc-btn-primario" form="cv-ajustes-form">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m20 6-11 11-5-5"/></svg>
					Guardar configuración
				</button>
			</p>
		</section>
		<?php
	}
}

new Caracool_Vinos_Ajustes();
