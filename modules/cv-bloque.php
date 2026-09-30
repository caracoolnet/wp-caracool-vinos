<?php
/**
 * Caracool Vinos — el bloque, tal y como lo pinta la web.
 *
 * Generado desde el laboratorio (labs/vinos/vinos.tpl.html) con a-plugin.py:
 * es el mismo markup que el del laboratorio, con los ids con prefijo y los
 * textos por variables. No se edita a mano.
 *
 * Lo incluye Caracool_Vinos_Explorador::html() con estas variables a la vista:
 *   $diseno ('columnas' | 'mundo'), $escena ('dia' | 'noche'), $mapa y $red (bool: las vistas
 *   que da Bodega y enciende la web; Lista va siempre), $modo (con la que arranca),
 *   $panel ('mosaico' | 'pildoras' | 'ficha': la piel del panel), $dianoche (bool: el sol y la luna),
 *   $esquinas ('web': las del Kit | 'diseno': las de la piel),
 *   $toques (bool: «Ayúdame a elegir», la capa con Tres toques),
 *   $precios (bool), $datos (JSON ya escapado), $rotulo, $titulo, $entradilla, $n, $np,
 *   $pie (el texto del pie, sin fecha; vacío, no hay pie)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="cv-explorador<?php echo $precios ? '' : ' sin-precio'; ?>" data-cv-root data-diseno="<?php echo esc_attr( $diseno ); ?>" data-modo="<?php echo esc_attr( $modo ); ?>" data-escena="<?php echo esc_attr( $escena ); ?>" data-panel="<?php echo esc_attr( $panel ); ?>" data-esquinas="<?php echo esc_attr( $esquinas ); ?>">
<script type="application/json" data-cv-datos><?php echo $datos; // phpcs:ignore WordPress.Security.EscapeOutput ?></script>
<div class="escena">

  <?php if ( 'mundo' === $diseno ) : ?>
  <div class="titulo">
    <p class="rotulo"><?php echo esc_html( $rotulo ); ?></p>
    <h1><?php echo esc_html( $titulo ); ?></h1>
    <p class="entradilla"><?php echo esc_html( $entradilla ); ?></p>
  </div>
  <?php endif; ?>

  <aside class="globo" aria-label="Mapa de los vinos">
    <div class="globo__marco">
      <svg id="cv-globo" viewBox="0 0 600 600" role="img" aria-label="Globo terráqueo con los países de la carta"></svg>
      <div class="globo__tip" id="cv-tip"></div>
      <div class="globo__zoom" aria-label="Acercar y alejar">
        <button type="button" id="cv-zoom-mas" title="Acercar" aria-label="Acercar">+</button>
        <button type="button" id="cv-zoom-menos" title="Alejar" aria-label="Alejar">−</button>
      </div>
    </div>
    <div class="globo__pie">
      <div><small id="cv-pie-rotulo">Todo el mundo</small><br><b id="cv-pie-nombre"><?php echo (int) $np; ?> países</b></div>
      <div id="cv-pie-cuenta"><?php echo (int) $n; ?> vinos</div>
    </div>
    <p class="globo__ayuda">Arrastra para girar · pincha un país para quedarte con sus vinos</p>
  </aside>

  <aside class="globo red" id="cv-red" aria-label="La bodega como red">
    <div class="globo__marco">
      <canvas id="cv-red-lienzo" role="img" aria-label="Países, zonas, bodegas y uvas de la carta, unidos en una red"></canvas>
      <div class="globo__tip" id="cv-red-tip"></div>
      <p class="red__forma" id="cv-red-forma">La red se está colocando…</p>
      <div class="globo__zoom" aria-label="Acercar, alejar y movimiento">
        <button type="button" id="cv-red-mas" title="Acercar" aria-label="Acercar">+</button>
        <button type="button" id="cv-red-menos" title="Alejar" aria-label="Alejar">−</button>
        <button type="button" id="cv-red-mover" class="mover on" title="Movimiento" aria-label="Movimiento" aria-pressed="true">↻</button>
      </div>
    </div>
    <!-- abajo a la izquierda, solo el zoom y el botón de movimiento: sin
         título, sin ayuda y sin leyenda (sus puntos se mezclaban con los de la red) -->
  </aside>

  <section class="panel" id="cv-panel">
    <div class="filtros" id="cv-filtros">
      <div class="filtros__tipos" id="cv-fila-tipos" role="group" aria-label="Tipo de vino"></div>
      <div class="filtros__afinar">
        <label class="buscar-caja"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 5 5"/></svg><input class="buscar" id="cv-f-texto" type="search" placeholder="Vino, bodega, zona o uva" autocomplete="off" aria-label="Buscar"><button type="button" class="buscar__borrar" id="cv-f-texto-borrar" aria-label="Borrar la búsqueda" title="Borrar" hidden><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg></button></label>
        <div class="campos">
          <label class="campo campo--uva" data-todo="Todas"><svg class="ico ico--campo" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 7.2V3.2"/><path d="M12 4.8c1.6-1.7 4.2-1.9 5.6-.7-1.4 1.6-3.8 1.9-5.6.7Z"/><circle cx="7.7" cy="9.6" r="2.1"/><circle cx="12" cy="9.6" r="2.1"/><circle cx="16.3" cy="9.6" r="2.1"/><circle cx="9.85" cy="13.5" r="2.1"/><circle cx="14.15" cy="13.5" r="2.1"/><circle cx="12" cy="17.4" r="2.1"/></svg><span class="campo__rot">Uva</span><span class="campo__val">Todas</span><svg class="ico ico--flecha" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg><button type="button" class="campo__quitar" aria-label="Quitar el filtro: uva" title="Quitar" hidden><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg></button><select class="sel" id="cv-f-uva" aria-label="Uva"><option value="">Uva</option></select></label>
          <label class="campo campo--zona" data-todo="Todas"><svg class="ico ico--campo" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-6.5-5.8-6.5-11a6.5 6.5 0 0 1 13 0c0 5.2-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.3"/></svg><span class="campo__rot">Zona</span><span class="campo__val">Todas</span><svg class="ico ico--flecha" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg><button type="button" class="campo__quitar" aria-label="Quitar el filtro: zona" title="Quitar" hidden><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg></button><select class="sel" id="cv-f-zona" aria-label="Zona"><option value="">Zona</option></select></label>
          <label class="campo campo--bodega" data-todo="Todas"><svg class="ico ico--campo" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3.5h10c1.7 2.6 1.7 14.4 0 17H7c-1.7-2.6-1.7-14.4 0-17Z"/><path d="M5.9 8.5h12.2M5.9 15.5h12.2"/></svg><span class="campo__rot">Bodega</span><span class="campo__val">Todas</span><svg class="ico ico--flecha" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg><button type="button" class="campo__quitar" aria-label="Quitar el filtro: bodega" title="Quitar" hidden><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg></button><select class="sel" id="cv-f-bodega" aria-label="Bodega"><option value="">Bodega</option></select></label>
          <label class="campo campo--precio<?php echo $precios ? '' : ' oculto'; ?>" data-todo="Todos"><svg class="ico ico--campo" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.5 6.6a6.6 6.6 0 1 0 0 10.8"/><path d="M4.8 10.2h8.8M4.8 13.8h8.8"/></svg><span class="campo__rot">Precio</span><span class="campo__val">Todos</span><svg class="ico ico--flecha" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg><button type="button" class="campo__quitar" aria-label="Quitar el filtro: precio" title="Quitar" hidden><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg></button><select class="sel" id="cv-f-precio" aria-label="Precio">
            <option value="">Precio</option>
            <option value="0-30">Hasta 30 €</option>
            <option value="30-50">30 a 50 €</option>
            <option value="50-80">50 a 80 €</option>
            <option value="80-150">80 a 150 €</option>
            <option value="150-300">150 a 300 €</option>
            <option value="300-99999">Más de 300 €</option>
          </select></label>
          <label class="campo campo--parker" data-todo="Todos"><svg class="ico ico--campo" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="9" r="5.5"/><circle cx="12" cy="9" r="2"/><path d="m9 13.8-1.5 7.2 4.5-2.3 4.5 2.3-1.5-7.2"/></svg><span class="campo__rot">Parker</span><span class="campo__val">Todos</span><svg class="ico ico--flecha" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg><button type="button" class="campo__quitar" aria-label="Quitar el filtro: parker" title="Quitar" hidden><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg></button><select class="sel" id="cv-f-parker" aria-label="Puntos Parker">
            <option value="">Parker</option>
            <option value="96">96 o más</option>
            <option value="94">94 o más</option>
            <option value="90">90 o más</option>
          </select></label>
        </div>
        <div class="filtros__activos oculto" id="cv-fila-activos"></div>
      </div>
      <div class="filtros__vista">
        <span class="filtros__mis" id="cv-mis-sitio"></span>
        <span class="filtros__cola" id="cv-cola"><span class="modo<?php echo ( $mapa || $red ) ? '' : ' oculto'; ?>" id="cv-modo" role="group" aria-label="Con el mapa, con la red o solo la lista"><?php if ( $mapa ) : ?><button type="button" data-modo="mapa" title="Mapa"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c-3.2 3-3.2 14 0 17M12 3.5c3.2 3 3.2 14 0 17"/></svg><span>Mapa</span></button><?php endif; ?><?php if ( $red ) : ?><button type="button" data-modo="red" title="Red"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="5.5" cy="7" r="2"/><circle cx="18" cy="5.5" r="2"/><circle cx="12" cy="12.5" r="2"/><circle cx="6" cy="18.5" r="2"/><circle cx="18.5" cy="17.5" r="2"/><path d="m7 8.4 3.5 2.8M16.6 7l-3.4 4M10.6 13.9l-3.2 3.2M13.7 13.6l3.2 2.8M7.5 6.8l8.5-1"/></svg><span>Red</span></button><?php endif; ?><button type="button" data-modo="lista" title="Lista"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6.5h11M9 12h11M9 17.5h11"/><circle cx="4.8" cy="6.5" r="1"/><circle cx="4.8" cy="12" r="1"/><circle cx="4.8" cy="17.5" r="1"/></svg><span>Lista</span></button></span><?php if ( $dianoche ) : ?><span class="dianoche" id="cv-dianoche" role="group" aria-label="De día o de noche"><button type="button" data-e="dia" title="De día" aria-label="De día"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2.8v2.4M12 18.8v2.4M2.8 12h2.4M18.8 12h2.4M5.5 5.5l1.7 1.7M16.8 16.8l1.7 1.7M5.5 18.5l1.7-1.7M16.8 7.2l1.7-1.7"/></svg></button><button type="button" data-e="noche" title="De noche" aria-label="De noche"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M19.5 14.6A8 8 0 1 1 9.4 4.5a6.4 6.4 0 0 0 10.1 10.1Z"/></svg></button></span><?php endif; ?></span>
      </div>
    </div>

    <div class="lista" id="cv-lista"><p class="vacio">Cargando la carta…</p></div>
    <div class="ficha oculto" id="cv-ficha"></div>
    <?php if ( '' !== $pie ) : ?><p class="lista__pie"><?php echo esc_html( $pie ); ?></p><?php endif; ?>
  </section>

</div>
<?php if ( $toques ) : ?>
<div class="elegir" id="cv-elegir" role="dialog" aria-modal="true" aria-label="Ayúdame a elegir" data-lenis-prevent hidden>
  <button type="button" class="elegir__cerrar" id="cv-elegir-cerrar" aria-label="Cerrar y volver a la carta" title="Volver a la carta (Esc)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg><span>Volver a la carta</span></button>
  <section class="cv-toques" data-cv-capa aria-label="Tres preguntas para elegir vino">
    <div class="tq-dentro"><div class="tq-hilo" aria-label="Lo que has elegido"></div><div class="tq-escena" aria-live="polite"></div></div>
  </section>
</div>
<?php endif; ?>
<div class="aviso-flotante" id="cv-aviso" role="status" aria-live="polite"></div>
</div>
