/*
 * Caracool Vinos — el explorador
 * ─────────────────────────────────────────────────────────────────────
 * Generado desde el laboratorio (labs/vinos/vinos.tpl.html) con a-plugin.py.
 * No se edita a mano: se afina el laboratorio y se vuelve a generar.
 *
 * Cada bloque .cv-explorador lleva dentro sus datos en un
 * <script type="application/json" data-cv-datos>, que pone el plugin:
 *   vinos       la carta, un objeto por vino
 *   region      zonas que van delante en la lista, en mayúsculas
 *   precios, ubicacion, favoritos   qué se ve (true/false)
 *   nombre      cómo se llama la casa al compartir
 *   corazones   dónde se cuentan los corazones de la gente (o null)
 *   toques      «Ayúdame a elegir»: {aqui, tramos}, o null si no sale;
 *               lo pinta assets/cv-toques.js (window.CaracoolToques)
 *   mundo, finos   URLs de la geometría del mundo (110m y 50m)
 * Van dentro del bloque y no en un script aparte para que el editor de
 * Elementor, que vuelve a pintar el widget por AJAX, los tenga siempre.
 *
 * Pide d3-array, d3-geo y topojson-client, que van en assets/lib.
 */
(function () {
  'use strict';

  var geometrias = {}; // una descarga por URL aunque haya varios bloques
  function traerMundo(cfg) {
    var k = cfg.mundo + '|' + cfg.finos;
    if (!geometrias[k]) {
      geometrias[k] = Promise.all([fetch(cfg.mundo).then(function (r) { return r.json(); }), fetch(cfg.finos).then(function (r) { return r.json(); })]);
    }
    return geometrias[k];
  }

  function datosDe(raiz) {
    var s = raiz.querySelector('script[data-cv-datos]');
    if (!s) { return null; }
    try { return JSON.parse(s.textContent); } catch (e) { return null; }
  }

  function iniciar(raiz) {
    if (!raiz || raiz.dataset.cvListo) { return; }
    var cfg = datosDe(raiz);
    if (!cfg || !cfg.vinos) { return; }
    raiz.dataset.cvListo = '1';
    traerMundo(cfg).then(function (g) { montar(raiz, g[0], g[1], cfg); }).catch(function (e) {
      raiz.dataset.cvListo = '';
      if (window.console) { console.error('Caracool Vinos: no se ha podido cargar el mundo', e); }
    });
  }

  function arrancar() {
    Array.prototype.forEach.call(document.querySelectorAll('.cv-explorador[data-cv-root]'), iniciar);
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', arrancar); } else { arrancar(); }

  // En el editor de Elementor el widget se vuelve a pintar al tocar cualquier
  // ajuste: cada vez que aparece, se monta otra vez.
  function engancharElementor() {
    if (window.elementorFrontend && window.elementorFrontend.hooks) {
      window.elementorFrontend.hooks.addAction('frontend/element_ready/caracool-vinos.default', function ($scope) {
        var r = $scope[0].querySelector('.cv-explorador[data-cv-root]'); if (r) { iniciar(r); }
      });
      return true;
    }
    return false;
  }
  if (!engancharElementor() && window.jQuery) { window.jQuery(window).on('elementor/frontend/init', engancharElementor); }

  function montar(raiz, MUNDO, FINOS, CFG) {



  /* ── Los datos ─────────────────────────────────────────────────────── */
  var VINOS = CFG.vinos.map(function (v, i) { v.i = i; return v; });
  var reducido = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // ISO de tres letras → id numérico de Natural Earth, que es lo que
  // llevan los países del atlas. Solo hacen falta los que tengan vinos,
  // pero cuesta lo mismo dejar unos cuantos más para el día que entren.
  var ISO = { ESP:'724', FRA:'250', ITA:'380', DEU:'276', AUT:'040', GRC:'300', HRV:'191', AUS:'036', USA:'840',
              PRT:'620', CHL:'152', ARG:'032', NZL:'554', ZAF:'710', HUN:'348', SVN:'705', GEO:'268', LBN:'422',
              CHE:'756', GBR:'826', CAN:'124', URY:'858', MEX:'484', BGR:'100', ROU:'642', MDA:'498', ISR:'376', MAR:'504' };
  var NUM = {}; Object.keys(ISO).forEach(function (k) { NUM[ISO[k]] = k; });

  // Por país: nombre en castellano y cuántos vinos hay
  var PAISES = {};
  VINOS.forEach(function (v) {
    if (!v.iso) { return; }
    if (!PAISES[v.iso]) { PAISES[v.iso] = { nombre: v.pais, n: 0, lat: 0, lon: 0 }; }
    PAISES[v.iso].n++;
  });
  // el centro de cada país: la media de sus zonas, que es donde de verdad hay vinos
  Object.keys(PAISES).forEach(function (iso) {
    var zs = {}; VINOS.forEach(function (v) { if (v.iso === iso && v.zona) { zs[v.zona] = [v.lat, v.lon]; } });
    var k = Object.keys(zs), la = 0, lo = 0;
    k.forEach(function (z) { la += zs[z][0]; lo += zs[z][1]; });
    PAISES[iso].lat = la / k.length; PAISES[iso].lon = lo / k.length;
    PAISES[iso].zonas = k.map(function (z) { return { zona: z, lat: zs[z][0], lon: zs[z][1] }; });
  });

  /* ── El globo ──────────────────────────────────────────────────────── */
  var svg = document.getElementById('cv-globo');
  var NS = 'http://www.w3.org/2000/svg';
  var W = 600, H = 600, R = 282;
  var proy = d3.geoOrthographic().scale(R).translate([300, 300]).clipAngle(90).rotate([-3, -40]);
  function diseno() { return raiz.dataset.diseno || 'columnas'; }
  function modo() { return raiz.dataset.modo || 'mapa'; }
  function hayGlobo() { return modo() === 'mapa'; }
  /* El tamaño del globo. En «columnas» el lienzo es un cuadrado de 600 y el
     globo casi lo llena. En «mundo» el lienzo es la pantalla entera: el globo
     llena el alto y se corre a la izquierda para dejar sitio a la lista, y lo
     que se sale del marco al acercarse se recorta. El zoom (k) se conserva
     al cambiar de tamaño. */
  function medir() {
    var k = proy.scale() / R;
    var caja = svg.getBoundingClientRect();
    if (diseno() === 'mundo' && caja.width > 0) {
      W = Math.round(caja.width); H = Math.round(caja.height);
      var movil = window.innerWidth <= 900;
      R = movil ? Math.min(H * 0.46, W * 0.44) : Math.min(H * 0.47, W * 0.30);
      proy.translate(movil ? [W / 2, H * 0.5] : [W * 0.36, H * 0.52]);
    } else {
      W = 600; H = 600; R = 282;
      proy.translate([300, 300]);
    }
    svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
    proy.scale(R * k);
  }
  /* La cabecera de la web (en El Churra, el header de Elementor: fijo, 126
     px, z-index 99). Lo que se clava al bajar (los filtros en modo lista,
     el globo en «columnas») tiene que clavarse debajo de ella, no debajo
     de su sombra. Se mide de verdad, no se da por hecho: si no hay
     cabecera fija, o no está arriba, vale 0 y todo queda como en el
     laboratorio. Un ResizeObserver la vuelve a medir si cambia de alto. */
  var cabeceraEl = null;
  function buscarCabecera() {
    var cands = document.querySelectorAll('.elementor-location-header, header, [data-elementor-type="header"]');
    for (var i = 0; i < cands.length; i++) {
      var cs = getComputedStyle(cands[i]);
      if ((cs.position === 'fixed' || cs.position === 'sticky') && cands[i].getBoundingClientRect().top < 8) { return cands[i]; }
    }
    return null;
  }
  function medirCabecera() {
    if (!cabeceraEl) { cabeceraEl = buscarCabecera(); }
    var alto = 0, fija = false;
    if (cabeceraEl) {
      var c = cabeceraEl.getBoundingClientRect(), cs = getComputedStyle(cabeceraEl);
      alto = cs.position === 'fixed' ? Math.max(0, Math.round(c.bottom)) : Math.round(c.height);
      // por encima de la capa de «Ayúdame a elegir» (z-index 40)
      fija = cs.position === 'fixed' && (parseInt(cs.zIndex, 10) || 0) > 40;
    }
    raiz.style.setProperty('--cv-cabecera', alto + 'px');
    if (fija) { raiz.setAttribute('data-cab', 'fija'); } else { raiz.removeAttribute('data-cab'); }
  }
  medirCabecera();
  /* La cabecera de Caracool Churra se pone en tinta o en crema según el fondo
     que ve debajo, y solo vuelve a mirar al hacer scroll o cambiar de
     tamaño. De noche, o con la capa abierta, el fondo cambia sin scroll: se
     le avisa con un scroll, y el degradado de la escena, que ella no sabe
     leer, se lo dice la clase ch-oscuro (la que esa cabecera documenta). */
  function avisarCabecera() { try { window.dispatchEvent(new Event('scroll')); } catch (e) {} }
  if (window.ResizeObserver && cabeceraEl) { new ResizeObserver(function () { medirCabecera(); }).observe(cabeceraEl); }
  /* cuando los filtros están clavados (solo si son sticky: modo lista o
     «columnas»), la clase .clavado les pone el tapón de arriba */
  var filtrosEl = document.getElementById('cv-filtros');
  function vigilarClavado() {
    if (!filtrosEl) { return; }
    var pegado = false;
    if (getComputedStyle(filtrosEl).position === 'sticky' && window.scrollY > 0) {
      var cab = parseFloat(getComputedStyle(raiz).getPropertyValue('--cv-cabecera')) || 0;
      pegado = filtrosEl.getBoundingClientRect().top <= cab + 1;
    }
    filtrosEl.classList.toggle('clavado', pegado);
  }
  window.addEventListener('scroll', vigilarClavado, { passive: true });
  vigilarClavado();
  medir();
  var ruta = d3.geoPath(proy);
  // El mundo va a 1:110M, que basta para verlo entero y girar ligero. Los
  // países con vinos y sus vecinos por tierra se cambian por su versión a
  // 1:50M: al acercarse a España quince veces, la costa de 110M es un
  // polígono de doce lados y la frontera con Portugal no casaría.
  var finos = {};
  (FINOS && FINOS.features || []).forEach(function (f) { finos[String(f.id)] = f; });
  var paises = topojson.feature(MUNDO, MUNDO.objects.countries).features.map(function (f) {
    return finos[String(f.id)] ? finos[String(f.id)] : f;
  });
  // La malla se afina al acercarse: a 10° solo hay una línea en pantalla.
  var mallas = { 10: d3.geoGraticule10(), 5: d3.geoGraticule().step([5, 5]), 2: d3.geoGraticule().step([2, 2]) };
  function malla() { var k = proy.scale() / R; return k > 7 ? mallas[2] : (k > 3 ? mallas[5] : mallas[10]); }

  function el(tag, cls) { var e = document.createElementNS(NS, tag); if (cls) { e.setAttribute('class', cls); } return e; }

  var esfera = el('path', 'esfera'); svg.appendChild(esfera);
  var gMalla = el('path', 'malla'); svg.appendChild(gMalla);
  var gPaises = el('g'); svg.appendChild(gPaises);
  var capaPais = {};
  paises.forEach(function (f) {
    var p = el('path', 'pais');
    p.setAttribute('data-id', f.id);
    if (NUM[f.id] && PAISES[NUM[f.id]]) { p.classList.add('con-vinos'); }
    gPaises.appendChild(p);
    capaPais[f.id] = { el: p, f: f };
  });
  var gZonas = el('g'); svg.appendChild(gZonas);
  var aro = el('circle', 'aro'); aro.setAttribute('r', 6); svg.appendChild(aro);
  var punto = el('circle', 'punto'); punto.setAttribute('r', 4.5); punto.style.display = 'none'; svg.appendChild(punto);
  var rotulo = el('text', 'rotulo'); rotulo.style.display = 'none'; svg.appendChild(rotulo);
  var rotuloTexto = '';

  var puntoLL = null; // [lon, lat] del vino elegido
  var paisCerca = null; // el país al que se ha acercado el globo: enseña sus zonas

  // Cuánto acercarse a cada país: lo que haga falta para que llene la
  // vista, con un tope para que siga pareciendo un globo y no un plano.
  // Se mide sobre la geometría del atlas, no sobre las zonas.
  // Cuánto acercarse a cada país: lo bastante para que se salga un poco del
  // marco por los lados —es lo que pidió Ángel: España enorme, con trozos
  // fuera— sin pasarse con los pequeños. Se mide sobre la geometría, en
  // grados corregidos por la latitud, y 600 px de marco valen unos 135° de
  // «grados-marco» a escala 1.
  // Se mide solo el trozo grande del país: las Canarias, Alaska o la
  // Guayana francesa están en la geometría y, si contaran, España saldría
  // la mitad de grande de lo que debe.
  function trozoGrande(f) {
    var g = f.geometry;
    if (g.type !== 'MultiPolygon') { return f; }
    var mejor = null, area = -1;
    g.coordinates.forEach(function (poli) {
      var a = d3.geoArea({ type: 'Polygon', coordinates: poli });
      if (a > area) { area = a; mejor = poli; }
    });
    return { type: 'Feature', geometry: { type: 'Polygon', coordinates: mejor } };
  }
  function zoomDe(iso) {
    var f = capaPais[ISO[iso]] && capaPais[ISO[iso]].f;
    if (!f) { return 4; }
    var b = d3.geoBounds(trozoGrande(f)), dl = Math.abs(b[1][0] - b[0][0]), dt = Math.abs(b[1][1] - b[0][1]);
    if (dl > 180) { dl = 360 - dl; }
    var latMedia = (b[0][1] + b[1][1]) / 2 * Math.PI / 180;
    var span = Math.max(dl * Math.cos(latMedia), dt);
    return Math.max(2.4, Math.min(16, 170 / span));
  }
  // A una zona se llega mucho más cerca que a un país: una D.O. cabe en
  // unos cinco grados de marco, una comarca grande (Borgoña, Toscana) en
  // unos ocho. Si la carta no dice más que el país, se queda en el país.
  function zoomZona(v) {
    if (v.lon == null) { return 1; }
    if (v.zona_tipo === 'do') { return 24; }
    if (v.zona_tipo === 'region') { return 15; }
    return zoomDe(v.iso);
  }
  // Adónde mirar: entre el centro del país y la zona del vino, tirando
  // hacia la zona, para que el punto quede siempre a la vista y el país
  // no se vaya entero por un lado.
  function centroDe(iso, lon, lat) {
    var p = PAISES[iso];
    if (!p || lon == null) { return [p ? p.lon : lon, p ? p.lat : lat]; }
    return [p.lon + (lon - p.lon) * 0.6, p.lat + (lat - p.lat) * 0.6];
  }
  function detras(ll) { return d3.geoDistance(ll, [-proy.rotate()[0], -proy.rotate()[1]]) >= Math.PI / 2 - 0.02; }

  function pintar() {
    // muy cerca, la malla o la esfera pueden quedarse enteras fuera de la vista
    esfera.setAttribute('d', ruta({ type: 'Sphere' }) || 'M0 0');
    gMalla.setAttribute('d', ruta(malla()) || 'M0 0');
    paises.forEach(function (f) {
      var d = ruta(f);
      capaPais[f.id].el.setAttribute('d', d || 'M0 0');
    });
    // las zonas del país al que nos hemos acercado: puntos pequeños y huecos
    var hijos = gZonas.children, zonas = (paisCerca && PAISES[paisCerca]) ? PAISES[paisCerca].zonas : [];
    while (hijos.length > zonas.length) { gZonas.removeChild(gZonas.lastChild); }
    while (hijos.length < zonas.length) { var c = el('circle', 'zona'); c.setAttribute('r', 3); gZonas.appendChild(c); }
    var kz = proy.scale() / R, rz = kz > 6 ? 4.5 : 3;
    zonas.forEach(function (z, i) {
      var ll = [z.lon, z.lat], p = proy(ll), oc = detras(ll) || !p || (puntoLL && puntoLL[0] === ll[0] && puntoLL[1] === ll[1]);
      hijos[i].style.display = oc ? 'none' : '';
      hijos[i].dataset.zona = z.zona;
      if (!oc) { hijos[i].setAttribute('cx', p[0]); hijos[i].setAttribute('cy', p[1]); hijos[i].setAttribute('r', rz); }
    });
    punto.setAttribute('r', kz > 6 ? 6 : 4.5);
    if (puntoLL) {
      var visible = !detras(puntoLL);
      var xy = proy(puntoLL);
      if (visible && xy) {
        punto.style.display = '';
        punto.setAttribute('cx', xy[0]); punto.setAttribute('cy', xy[1]);
        aro.setAttribute('cx', xy[0]); aro.setAttribute('cy', xy[1]);
        // el nombre de la zona, solo cuando estamos cerca
        if (kz > 8 && rotuloTexto) {
          rotulo.style.display = '';
          rotulo.textContent = rotuloTexto;
          rotulo.setAttribute('x', xy[0] + 14); rotulo.setAttribute('y', xy[1] - 12);
        } else { rotulo.style.display = 'none'; }
      } else {
        punto.style.display = 'none'; rotulo.style.display = 'none';
      }
    } else {
      punto.style.display = 'none'; rotulo.style.display = 'none';
    }
    svg.classList.toggle('cerca', kz > 8);
    botonesZoom();
  }

  // ── girar hasta un sitio, con la curva de la casa ──
  var giro = null;
  function curva(t) { return 1 - Math.pow(1 - t, 4); } // sale rápido y frena largo
  function girarA(lon, lat, ms, zoom) {
    var desde = proy.rotate(), escDesde = proy.scale(), escHasta = R * (zoom || 1);
    // un poco por debajo del centro de la vista, para que el país no quede tapado por el texto de arriba
    var alza = 8 / (zoom || 1);
    // sin globo a la vista (modo lista) se deja puesto, sin girar: cuando
    // vuelva el mapa estará mirando adonde toca
    if (reducido || !hayGlobo()) { proy.rotate([-lon, -(lat - alza)]).scale(escHasta); pintar(); return; }
    var inter = d3.geoInterpolate([-desde[0], -desde[1]], [lon, lat - alza]);
    var t0 = null, dur = ms || 1100;
    if (giro) { cancelAnimationFrame(giro); }
    function paso(ts) {
      if (!t0) { t0 = ts; }
      var t = Math.min(1, (ts - t0) / dur), k = curva(t);
      var ll = inter(k);
      proy.rotate([-ll[0], -ll[1]]).scale(escDesde + (escHasta - escDesde) * k);
      pintar();
      if (t < 1) { giro = requestAnimationFrame(paso); } else { giro = null; latir(); }
    }
    giro = requestAnimationFrame(paso);
  }
  // volver a ver el mundo entero, sin cambiar de sitio
  function alejar() {
    var r = proy.rotate();
    if (Math.abs(proy.scale() - R) < 1) { return; }
    girarA(-r[0], -r[1] + 8, 900, 1);
  }

  // Los botones de más y menos. Cada paso multiplica o divide por 1,6 y se
  // queda entre el mundo entero (1) y lo más cerca que llega el zoom a una
  // zona (24). Se acerca sobre lo que haya en el centro, sin girar.
  var ZOOM_MIN = 1, ZOOM_MAX = 24, ZOOM_PASO = 1.6;
  var btnMas = document.getElementById('cv-zoom-mas'), btnMenos = document.getElementById('cv-zoom-menos');
  function zoomA(k) {
    k = Math.max(ZOOM_MIN, Math.min(ZOOM_MAX, k));
    var r = proy.rotate();
    girarA(-r[0], -r[1] + 8 / (proy.scale() / R), 650, k);
    quieto = performance.now();
  }
  btnMas.addEventListener('click', function () { zoomA(proy.scale() / R * ZOOM_PASO); });
  btnMenos.addEventListener('click', function () { zoomA(proy.scale() / R / ZOOM_PASO); });
  function botonesZoom() {
    var k = proy.scale() / R;
    btnMas.disabled = k >= ZOOM_MAX - 0.01;
    btnMenos.disabled = k <= ZOOM_MIN + 0.01;
  }
  function latir() {
    if (!puntoLL) { return; }
    aro.classList.remove('late'); void aro.getBoundingClientRect(); aro.classList.add('late');
  }

  // ── arrastrar para girar ──
  var arr = null, quieto = 0;
  svg.addEventListener('pointerdown', function (e) {
    var p = e.target.closest ? e.target.closest('.pais') : null;
    var zc = e.target.closest ? e.target.closest('.zona') : null;
    arr = { x: e.clientX, y: e.clientY, r: proy.rotate(), pais: p, zona: zc, movido: false };
    svg.classList.add('arrastrando'); try { svg.setPointerCapture(e.pointerId); } catch (x) {}
    if (giro) { cancelAnimationFrame(giro); giro = null; }
  });
  svg.addEventListener('pointermove', function (e) {
    if (!arr) { return; }
    if (Math.abs(e.clientX - arr.x) > 4 || Math.abs(e.clientY - arr.y) > 4) { arr.movido = true; }
    var k = 0.45 * (282 / proy.scale()) * (W / svg.getBoundingClientRect().width);
    proy.rotate([arr.r[0] + (e.clientX - arr.x) * k, Math.max(-70, Math.min(70, arr.r[1] - (e.clientY - arr.y) * k))]);
    pintar(); quieto = performance.now();
  });
  function soltar(e) {
    var fue = arr; arr = null; svg.classList.remove('arrastrando');
    // Con el puntero capturado, el «click» se dispara en el svg y no en el
    // país, así que un toque sin arrastre se resuelve aquí.
    if (e && e.type === 'pointerup' && fue && !fue.movido) {
      if (fue.zona) { pincharZona(fue.zona); } else if (fue.pais) { pincharPais(fue.pais); }
    }
  }
  svg.addEventListener('pointerup', soltar);
  svg.addEventListener('pointercancel', soltar);

  // ── deriva: cuando nadie toca nada, el mundo gira despacio ──
  var deriva = !reducido;
  function derivar(ts) {
    if (deriva && !arr && !giro && !vinoActivo && !paisCerca && proy.scale() / R < 1.05 && performance.now() - quieto > 2500) {
      var r = proy.rotate(); proy.rotate([r[0] + 0.035, r[1]]); pintar();
    }
    requestAnimationFrame(derivar);
  }
  requestAnimationFrame(derivar);

  // ── pinchar y pasar por los países ──
  var tip = document.getElementById('cv-tip');
  gPaises.addEventListener('pointermove', function (e) {
    var p = e.target.closest('.pais');
    var iso = p && NUM[p.dataset.id], info = iso && PAISES[iso];
    // los países sin vinos no dicen nada: no hay nada que contar de ellos
    if (!info) { tip.classList.remove('ver'); return; }
    tip.innerHTML = '<b>' + info.nombre + '</b> · ' + info.n + (info.n === 1 ? ' vino' : ' vinos');
    var m = svg.getBoundingClientRect();
    tip.style.left = (e.clientX - m.left) + 'px'; tip.style.top = (e.clientY - m.top) + 'px';
    tip.classList.add('ver');
  });
  gPaises.addEventListener('pointerleave', function () { tip.classList.remove('ver'); });

  // Los puntos de las zonas: al pasar, qué zona es y qué hay; al pinchar,
  // te quedas con sus vinos (o, si solo hay uno, se abre directamente).
  function vinosDeZona(z) { return VINOS.filter(function (v) { return v.zona === z; }); }
  gZonas.addEventListener('pointermove', function (e) {
    var c = e.target.closest('.zona'); if (!c) { tip.classList.remove('ver'); return; }
    var z = c.dataset.zona, vs = vinosDeZona(z);
    var nombres = vs.slice(0, 3).map(function (v) { return v.nombre; }).join(', ') + (vs.length > 3 ? '…' : '');
    tip.innerHTML = '<b>' + legible(z) + '</b> · ' + vs.length + (vs.length === 1 ? ' vino' : ' vinos') + '<small>' + nombres + '</small>';
    var m = svg.getBoundingClientRect();
    tip.style.left = (e.clientX - m.left) + 'px'; tip.style.top = (e.clientY - m.top) + 'px';
    tip.classList.add('ver');
    e.stopPropagation();
  });
  gZonas.addEventListener('pointerleave', function () { tip.classList.remove('ver'); });
  function pincharZona(c) {
    var z = c.dataset.zona, vs = vinosDeZona(z);
    if (!vs.length) { return; }
    tip.classList.remove('ver');
    if (vs.length === 1) { elegir(vs[0]); return; }
    vaciarFiltro();
    filtro.zona = z;
    cerrarFicha(false);
    paisCerca = vs[0].iso; puntoLL = [vs[0].lon, vs[0].lat]; rotuloTexto = legible(z);
    girarA(vs[0].lon, vs[0].lat, 1000, zoomZona(vs[0]));
    aplicar();
    document.getElementById('cv-panel').scrollIntoView({ behavior: reducido ? 'auto' : 'smooth', block: 'start' });
  }
  function pincharPais(p) {
    var iso = NUM[p.dataset.id];
    if (!iso || !PAISES[iso]) { return; }
    filtro.pais = (filtro.pais === iso) ? '' : iso;
    cerrarFicha(false);
    if (filtro.pais) { paisCerca = iso; girarA(PAISES[iso].lon, PAISES[iso].lat, 1000, zoomDe(iso)); } else { paisCerca = null; alejar(); }
    aplicar();
  }

  function marcarPaises() {
    if (red) { red.actualizar(); }
    Object.keys(capaPais).forEach(function (id) {
      var e = capaPais[id].el, iso = NUM[id];
      e.classList.toggle('activo', !!(vinoActivo && iso && vinoActivo.iso === iso));
      e.classList.toggle('filtro', !!(!vinoActivo && filtro.pais && iso === filtro.pais));
    });
  }

  /* ── Favoritos ─────────────────────────────────────────────────────── */
  var CORAZON = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20.4 4.9 13.3a4.4 4.4 0 0 1 6.2-6.2l.9.9.9-.9a4.4 4.4 0 0 1 6.2 6.2Z"/></svg>';
  var CLAVE_FAVS = 'caracool-vinos-mis';
  var favs = {};
  try { (JSON.parse(localStorage.getItem(CLAVE_FAVS) || '[]')).forEach(function (k) { favs[k] = 1; }); } catch (e) {}
  function guardarFavs() { try { localStorage.setItem(CLAVE_FAVS, JSON.stringify(Object.keys(favs))); } catch (e) {} }
  function esFav(v) { return !!favs[v.llave]; }
  function cuantosFavs() { return VINOS.filter(esFav).length; }
  var CLAVE_AVISO_MIS = 'caracool-vinos-mis-aviso';

  /* ── Los corazones de la gente ─────────────────────────────────────
     Cuántas personas han guardado cada vino: la web lo cuenta sin saber
     quién. La cuenta llega aparte, por el REST, para que ninguna caché de
     página la congele, y solo trae los vinos que llegan al mínimo. Cada
     corazón que se pone o se quita avisa a la web; los que alguien tenía
     guardados de antes se cuentan una vez (CLAVE_CONTADOS). Sin memoria en
     el navegador no se avisa de nada: el corazón no duraría y la cuenta
     quedaría mal. En el laboratorio no hay CFG: no se cuenta nada. */
  var memoria = (function () { try { localStorage.setItem('caracool-vinos-t', '1'); localStorage.removeItem('caracool-vinos-t'); return true; } catch (e) { return false; } }());
  var CZ = (typeof CFG !== 'undefined' && CFG.favoritos && CFG.corazones && CFG.corazones.url && window.fetch) ? CFG.corazones : null;
  var gente = {};
  var CLAVE_CONTADOS = 'caracool-vinos-mis-contados';
  function personas(v) { return gente[v.llave] || 0; }
  function seVe(v) { return !!CZ && personas(v) >= CZ.minimo; }
  function contados() { try { return localStorage.getItem(CLAVE_CONTADOS) === '1'; } catch (e) { return false; } }
  function avisarGente(llaves, sentido) {
    if (!CZ || !memoria || !llaves.length) { return Promise.resolve(false); }
    return fetch(CZ.url + 'corazon', { method: 'POST', keepalive: true, credentials: 'omit', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ llaves: llaves.slice(0, 200), sentido: sentido }) })
      .then(function (r) { return r.ok; }, function () { return false; });
  }
  // los de antes: una vez, con los que haya guardados ahora
  // (si en la página también está «Tres toques», lo hace uno de los dos: window.__cvContando)
  function contarLosDeAntes() {
    if (!CZ || !memoria || contados() || window.__cvContando) { return; }
    window.__cvContando = true;
    var mias = Object.keys(favs);
    var hecho = function () { try { localStorage.setItem(CLAVE_CONTADOS, '1'); } catch (e) {} };
    if (!mias.length) { hecho(); return; }
    avisarGente(mias, 1).then(function (ok) { if (ok) { hecho(); } else { window.__cvContando = false; } });
  }
  // un cambio en mis vinos: se cuenta aquí al momento y se avisa a la web
  // (si los de antes aún no han entrado, ya entrarán con ellos)
  function moverGente(llaves, sentido) {
    if (!llaves.length) { return; }
    // a «Tres toques», si está en la página, para que pinte igual sus corazones
    try { window.dispatchEvent(new CustomEvent('caracool-vinos-mis', { detail: { de: raiz, llaves: llaves, sentido: sentido } })); } catch (e) {}
    if (!CZ) { return; }
    llaves.forEach(function (k) { gente[k] = Math.max(0, (gente[k] || 0) + sentido); });
    if (contados()) { avisarGente(llaves, sentido); }
  }
  // y al revés: lo que cambie «Tres toques» en la misma página
  window.addEventListener('caracool-vinos-mis', function (e) {
    var d = e.detail || {};
    if (d.de === raiz) { return; }
    favs = {}; try { (JSON.parse(localStorage.getItem(CLAVE_FAVS) || '[]')).forEach(function (k) { favs[k] = 1; }); } catch (x) {}
    if (CZ && d.llaves && d.sentido) { d.llaves.forEach(function (k) { gente[k] = Math.max(0, (gente[k] || 0) + d.sentido); }); }
    Array.prototype.forEach.call(raiz.querySelectorAll('.corazon[data-llave]'), function (b) { var v = porLlave[b.dataset.llave]; if (v) { b.classList.toggle('on', esFav(v)); numeroCorazon(b, v); } });
    chipMis(); chipGente();
    if (filtro.mis === 'mis' || filtro.top) { aplicar(); }
  });
  function numeroCorazon(b, v) {
    var n = b.querySelector('.corazon__n'), ve = seVe(v);
    b.classList.toggle('con-n', ve);
    if (n) { n.textContent = ve ? personas(v) : ''; }
    var t = esFav(v) ? 'Quitar de mis vinos' : 'Guardar en mis vinos';
    if (ve) { t += ' · ' + personas(v) + ' personas lo han guardado'; }
    b.title = t; b.setAttribute('aria-label', t);
  }
  function pintarGente() {
    Array.prototype.forEach.call(raiz.querySelectorAll('.corazon[data-llave]'), function (b) {
      var v = porLlave[b.dataset.llave]; if (v) { numeroCorazon(b, v); }
    });
    chipGente();
    if (filtro.top) { aplicar(); }
  }
  var porLlave = {}; VINOS.forEach(function (v) { if (v.llave && !porLlave[v.llave]) { porLlave[v.llave] = v; } });
  function traerGente() {
    if (!CZ) { return; }
    // una sola petición aunque en la página esté también «Tres toques»
    if (!window.__cvGente) { window.__cvGente = fetch(CZ.url + 'corazones', { credentials: 'omit', cache: 'no-store' }).then(function (r) { return r.ok ? r.json() : null; }, function () { return null; }); }
    window.__cvGente
      .then(function (d) {
        if (!d || !d.n) { return; }
        if (d.minimo) { CZ.minimo = +d.minimo; }
        if (d.lista) { CZ.lista = +d.lista; }
        Object.keys(d.n).forEach(function (k) { gente[k] = Math.max(+d.n[k] || 0, gente[k] || 0); });
        pintarGente();
      });
  }

  function alternarFav(v, boton) {
    if (favs[v.llave]) { delete favs[v.llave]; } else { favs[v.llave] = 1; }
    guardarFavs();
    moverGente([v.llave], esFav(v) ? 1 : -1);
    // todos los corazones de ese vino, estén donde estén
    Array.prototype.forEach.call(document.querySelectorAll('.corazon[data-llave="' + v.llave + '"]'), function (b) {
      b.classList.toggle('on', esFav(v));
      numeroCorazon(b, v);
      if (b === boton && esFav(v)) { b.classList.remove('late'); void b.getBoundingClientRect(); b.classList.add('late'); }
    });
    chipGente();
    chipMis();
    if (filtro.mis === 'mis' && !esFav(v)) { aplicar(); }
    if (esFav(v)) {
      // el botón late para señalar dónde queda
      avisar('Guardado en Mis vinos');
      chipMisEl.classList.remove('late'); void chipMisEl.getBoundingClientRect(); chipMisEl.classList.add('late');
    } else {
      avisar('Quitado de Mis vinos');
    }
  }
  function botonCorazon(v) {
    var b = document.createElement('button'); b.type = 'button'; b.className = 'corazon' + (esFav(v) ? ' on' : '');
    b.dataset.llave = v.llave; b.innerHTML = CORAZON + '<small class="corazon__n"></small>';
    numeroCorazon(b, v);
    b.addEventListener('click', function (e) { e.stopPropagation(); alternarFav(v, b); });
    return b;
  }
  var avisoTempo = null;
  function avisar(t, ms) {
    var a = document.getElementById('cv-aviso'); a.textContent = t; a.classList.add('ver');
    clearTimeout(avisoTempo); avisoTempo = setTimeout(function () { a.classList.remove('ver'); }, ms || 1800);
  }

  // Una selección compartida llega en la dirección: #mis=llave,llave,llave
  var compartida = null;
  (function () {
    var m = /[#&]mis=([^&]+)/.exec(location.hash || '');
    if (!m) { return; }
    var claves = {}; decodeURIComponent(m[1]).split(',').forEach(function (k) { if (k) { claves[k] = 1; } });
    var hay = VINOS.filter(function (v) { return claves[v.llave]; });
    if (hay.length) { compartida = claves; }
  }());
  function enlaceDe(claves) {
    return location.href.split('#')[0] + '#mis=' + encodeURIComponent(claves.join(','));
  }
  function compartir(claves, que) {
    var url = enlaceDe(claves);
    var texto = que + ' de la carta de vinos de ' + (CFG.nombre || '');
    if (navigator.share) {
      navigator.share({ title: (CFG.nombre || 'Vinos') + ' · vinos', text: texto, url: url }).catch(function () {});
      return;
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(function () { avisar('Enlace copiado'); }, function () { avisar('No se ha podido copiar'); });
    } else {
      window.prompt('Copia este enlace', url);
    }
  }

  /* ── Filtros ───────────────────────────────────────────────────────── */
  var filtro = { tipo: '', uva: '', bodega: '', zona: '', precio: '', parker: '', pais: '', texto: '', mis: compartida ? 'compartida' : '', top: false };
  // el desplegable de Parker solo existe si algún vino trae puntos
  var hayParker = VINOS.some(function (v) { return v.parker; });
  document.getElementById('cv-f-parker').closest('.campo').classList.toggle('oculto', !hayParker);
  var TIPOS = [
    ['', 'Todos'], ['tinto', 'Tintos'], ['blanco', 'Blancos'], ['champagne', 'Champagne'],
    ['espumoso', 'Espumosos'], ['rosado', 'Rosados'], ['magnum', 'Mágnum']
  ];
  /* la gota: el color del vino en la copa (tokens --vino-*); el mágnum,
     que es formato y no color, lleva una botella */
  function gota(t) {
    if (t === 'magnum') { return '<svg class="gota gota--botella" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 2.5h4v4.2c1.9 1 2.7 2.6 2.7 4.8v9a1 1 0 0 1-1 1H8.3a1 1 0 0 1-1-1v-9c0-2.2.8-3.8 2.7-4.8Z"/></svg>'; }
    return '<i class="gota gota--' + (t || 'todos') + '" aria-hidden="true"></i>';
  }
  var filaTipos = document.getElementById('cv-fila-tipos');
  var filaActivos = document.getElementById('cv-fila-activos');
  var hayMagnum = VINOS.some(function (v) { return v.formato === 'magnum'; });
  TIPOS.forEach(function (t) {
    if (t[0] === 'magnum' && !hayMagnum) { return; }
    var b = document.createElement('button'); b.type = 'button'; b.className = 'chip' + (t[0] === '' ? ' on' : '');
    b.dataset.v = t[0]; b.innerHTML = gota(t[0]) + '<span>' + t[1] + '</span><small></small>';
    b.addEventListener('click', function () { filtro.tipo = t[0]; cerrarFicha(false); aplicar(); });
    filaTipos.appendChild(b);
  });
  // el botón de «Mis vinos»: fijo al final de la fila de tipos, siempre a la vista
  var chipMisEl = document.createElement('button'); chipMisEl.type = 'button'; chipMisEl.className = 'mis-boton';
  chipMisEl.innerHTML = CORAZON + 'Mis vinos<small></small>';
  chipMisEl.title = 'Los vinos que has guardado'; chipMisEl.setAttribute('aria-pressed', 'false');
  chipMisEl.addEventListener('click', function () {
    filtro.mis = (filtro.mis === 'mis') ? '' : 'mis';
    if (filtro.mis === 'mis') { filtro.top = false; }
    if (filtro.mis === 'mis' && compartida) { compartida = null; if (location.hash) { history.replaceState(null, '', location.pathname + location.search); } }
    cerrarFicha(false); aplicar();
    if (filtro.mis === 'mis' && window.innerWidth <= 900) { document.getElementById('cv-panel').scrollIntoView({ behavior: reducido ? 'auto' : 'smooth', block: 'start' }); }
  });
  document.getElementById('cv-mis-sitio').appendChild(chipMisEl);
  // «Más guardados»: delante de Mis vinos, solo cuando hay unos cuantos vinos con la cifra a la vista
  var PODIO = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.5 20.5v-6h4.5v6Z"/><path d="M9.75 20.5V8h4.5v12.5Z"/><path d="M15 20.5v-9h4.5v9Z"/></svg>';
  var chipGenteEl = document.createElement('button'); chipGenteEl.type = 'button'; chipGenteEl.className = 'mis-boton gente-boton oculto';
  chipGenteEl.innerHTML = PODIO + 'Más guardados';
  chipGenteEl.title = 'Los vinos que más personas han guardado'; chipGenteEl.setAttribute('aria-pressed', 'false');
  chipGenteEl.addEventListener('click', function () {
    filtro.top = !filtro.top;
    if (filtro.top && filtro.mis === 'mis') { filtro.mis = ''; }
    cerrarFicha(false); aplicar();
    if (filtro.top && window.innerWidth <= 900) { document.getElementById('cv-panel').scrollIntoView({ behavior: reducido ? 'auto' : 'smooth', block: 'start' }); }
  });
  // va detrás en el marcado (el primer .mis-boton sigue siendo Mis vinos) y delante a la vista (order:-1)
  document.getElementById('cv-mis-sitio').appendChild(chipGenteEl);
  function chipGente() {
    var hay = !!CZ && VINOS.filter(seVe).length >= (CZ.lista || 3);
    if (!hay && filtro.top) { filtro.top = false; }
    chipGenteEl.classList.toggle('oculto', !hay);
    chipGenteEl.classList.toggle('on', !!filtro.top);
    chipGenteEl.setAttribute('aria-pressed', filtro.top ? 'true' : 'false');
  }
  function chipMis() {
    var n = cuantosFavs();
    chipMisEl.classList.toggle('oculto', !CFG.favoritos);
    chipMisEl.classList.toggle('on', filtro.mis === 'mis');
    chipMisEl.setAttribute('aria-pressed', filtro.mis === 'mis' ? 'true' : 'false');
    chipMisEl.classList.toggle('hay', n > 0);
    chipMisEl.querySelector('small').textContent = n;
  }
  /* El conmutador del visitante: con el mapa o solo la lista. Va al final
     de la fila de los desplegables. Se recuerda en el navegador de esa
     persona. Con «lista» el globo se apaga en los dos diseños y la lista
     queda en columna, como una carta de siempre. */
  var modoEl = document.getElementById('cv-modo');
  var CLAVE_MODO = 'caracool-vinos-modo';
  /* La web lleva Lenis (scroll suave, del plugin de la cabecera): captura
     la rueda en toda la página y la aplica al scroll de la ventana, que con
     el mapa fijo no existe. Con «data-lenis-prevent» en la escena, Lenis
     deja pasar la rueda y la lista se mueve sola; el resto de la página
     sigue con su scroll suave. Solo con el mapa fijo (escritorio). */
  function marcarEscena() {
    var fija = diseno() === 'mundo' && modo() !== 'lista' && window.innerWidth > 900;
    var esc = raiz.querySelector('.escena');
    if (fija) { esc.setAttribute('data-lenis-prevent', ''); } else { esc.removeAttribute('data-lenis-prevent'); }
  }
  function setModo(m, recordar) {
    if (recordar) { try { localStorage.setItem(CLAVE_MODO, m); } catch (e) {} }
    var cambio = function () {
      raiz.dataset.modo = m;
      marcarEscena();
      Array.prototype.forEach.call(modoEl.querySelectorAll('button'), function (b) { var on = b.dataset.modo === m; b.classList.toggle('on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
      if (m === 'mapa') { medir(); pintar(); }
      if (red) { if (m === 'red') { red.mostrar(); } else { red.parar(); } }
    };
    // animado solo cuando lo pide el visitante (no al arrancar), si el
    // navegador sabe y no hay movimiento reducido
    if (recordar && raiz.dataset.modo !== m && !reducido && document.startViewTransition) {
      // la lista vuelve arriba antes de la foto, para que el viaje sea limpio
      if (m === 'lista') { window.scrollTo(0, 0); }
      document.startViewTransition(cambio);
    } else {
      cambio();
    }
  }
  modoEl.addEventListener('click', function (e) { var b = e.target.closest('button'); if (b) { setModo(b.dataset.modo, true); } });
  var modoGuardado = ''; try { modoGuardado = localStorage.getItem(CLAVE_MODO) || ''; } catch (e) {}
  // las vistas que hay las decide Caracool en Bodega (el plugin quita los
  // botones que no da): se arranca en la guardada si sigue estando, y si no,
  // en la primera que haya (Lista va siempre)
  var hayRed = !!modoEl.querySelector('[data-modo="red"]');
  var hayMapa = !!modoEl.querySelector('[data-modo="mapa"]');
  var modoInicial = (modoGuardado === 'lista' || (modoGuardado === 'red' && hayRed) || (modoGuardado === 'mapa' && hayMapa)) ? modoGuardado : (hayMapa ? 'mapa' : (hayRed ? 'red' : 'lista'));
  setModo(modoInicial, false);
  /* De día o de noche: la casa decide cómo se abre (Ajustes o widget) y el
     visitante lo cambia con el sol y la luna, si la casa los enseña. Se
     recuerda en su navegador, como el modo; en el editor de Elementor no,
     para que se vea siempre lo que dice el widget. El globo sigue a los
     tokens solo; la red (canvas) se repinta. */
  var dianocheEl = document.getElementById('cv-dianoche');
  var CLAVE_ESCENA = 'caracool-vinos-escena';
  function setEscena(e, recordar) {
    if (recordar) { try { localStorage.setItem(CLAVE_ESCENA, e); } catch (x) {} }
    var cambio = function () {
      raiz.dataset.escena = e;
      raiz.classList.toggle('ch-oscuro', e === 'noche');
      avisarCabecera();
      if (dianocheEl) { Array.prototype.forEach.call(dianocheEl.querySelectorAll('button'), function (b) { var on = b.dataset.e === e; b.classList.toggle('on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); }); }
      if (red) { red.repintar(); }
    };
    if (recordar && raiz.dataset.escena !== e && !reducido && document.startViewTransition) {
      // mientras dura la transición, el navegador da la raíz de la página como
      // lo que hay bajo cualquier punto, y la cabecera de la web leería mal
      // su fondo: se le avisa también cuando acaba
      var vt = document.startViewTransition(cambio);
      if (vt && vt.finished) { vt.finished.then(avisarCabecera, avisarCabecera); }
    } else { cambio(); }
  }
  if (dianocheEl) {
    dianocheEl.addEventListener('click', function (e) { var b = e.target.closest('button'); if (b) { setEscena(b.dataset.e, true); } });
    var escenaGuardada = '';
    if (!document.body.classList.contains('elementor-editor-active')) { try { escenaGuardada = localStorage.getItem(CLAVE_ESCENA) || ''; } catch (e) {} }
    setEscena(escenaGuardada === 'noche' || escenaGuardada === 'dia' ? escenaGuardada : (raiz.dataset.escena === 'noche' ? 'noche' : 'dia'), false);
  }
  var tempoMedida = null;
  window.addEventListener('resize', function () { clearTimeout(tempoMedida); tempoMedida = setTimeout(function () { medirCabecera(); marcarEscena(); if (hayGlobo()) { medir(); pintar(); } }, 120); });

  function deTipo(v, t) {
    if (!t) { return true; }
    if (t === 'magnum') { return v.formato === 'magnum'; }
    if (t === 'champagne') { return v.tipo.indexOf('champagne') === 0; }
    if (t === 'espumoso') { return v.tipo.indexOf('espumoso') === 0; }
    if (t === 'rosado') { return v.tipo === 'rosado' || v.tipo.indexOf('rosado') > -1; }
    return v.tipo === t;
  }

  /* El desplegable nativo va encima del campo, transparente: se abre el de
     siempre (y en el móvil, la rueda del sistema), pero lo que se ve es el
     campo, con su icono y el valor elegido sin la cuenta. */
  function pintarCampos() {
    Array.prototype.forEach.call(raiz.querySelectorAll('.filtros .campo'), function (c) {
      var s = c.querySelector('select'), o = s.options[s.selectedIndex];
      var t = (s.value && o) ? o.textContent.split(' · ')[0] : c.getAttribute('data-todo');
      c.querySelector('.campo__val').textContent = t;
      c.title = s.value ? t : '';
      c.classList.toggle('on', !!s.value);
      c.querySelector('.campo__quitar').hidden = !s.value;
    });
    var q = document.getElementById('cv-f-texto');
    document.getElementById('cv-f-texto-borrar').hidden = !q.value;
  }
  function llenarSelect(id, valores, rotulo) {
    var s = document.getElementById(id);
    var actual = s.value;
    s.innerHTML = '<option value="">' + rotulo + '</option>';
    valores.forEach(function (x) {
      var o = document.createElement('option'); o.value = x[0]; o.textContent = x[1]; s.appendChild(o);
    });
    s.value = actual;
  }
  function contar(lista, campo, multi) {
    var c = {};
    lista.forEach(function (v) {
      var vals = multi ? v[campo] : [v[campo]];
      vals.forEach(function (x) { if (x) { c[x] = (c[x] || 0) + 1; } });
    });
    return c;
  }
  function normal(t) {
    return (t || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  }
  var uvaCanon = {}; // «Monastrelll» y «Monastrell» son la misma uva para el filtro
  VINOS.forEach(function (v) { v.uvas.forEach(function (u) { var k = normal(u).replace(/l{3,}/g, 'll'); if (!uvaCanon[k]) { uvaCanon[k] = u; } }); });
  function uvaClave(u) { return normal(u).replace(/l{3,}/g, 'll'); }

  function pasa(v, salvo) {
    if (filtro.mis === 'mis' && !esFav(v)) { return false; }
    if (filtro.top && !seVe(v)) { return false; }
    if (filtro.mis === 'compartida' && !(compartida && compartida[v.llave])) { return false; }
    if (salvo !== 'tipo' && !deTipo(v, filtro.tipo)) { return false; }
    if (salvo !== 'pais' && filtro.pais && v.iso !== filtro.pais) { return false; }
    if (salvo !== 'zona' && filtro.zona && v.zona !== filtro.zona) { return false; }
    if (salvo !== 'bodega' && filtro.bodega && v.bodega !== filtro.bodega) { return false; }
    if (salvo !== 'uva' && filtro.uva && !v.uvas.some(function (u) { return uvaClave(u) === filtro.uva; })) { return false; }
    if (salvo !== 'precio' && filtro.precio) {
      var r = filtro.precio.split('-'); if (!(v.precio >= +r[0] && v.precio < +r[1])) { return false; }
    }
    if (salvo !== 'parker' && filtro.parker && !(v.parker && v.parker >= +filtro.parker)) { return false; }
    if (salvo !== 'texto' && filtro.texto) {
      var q = normal(filtro.texto);
      var pajar = normal([v.nombre, v.apelacion, v.bodega, v.zona, v.seccion, v.uvas_texto, v.uvas.join(' ')].join(' '));
      if (pajar.indexOf(q) < 0) { return false; }
    }
    return true;
  }

  function aplicar() {
    var lista = VINOS.filter(function (v) { return pasa(v); });

    // los chips de tipo, con cuántos habría de cada uno
    var porTipo = VINOS.filter(function (v) { return pasa(v, 'tipo'); });
    Array.prototype.forEach.call(filaTipos.querySelectorAll('.chip:not(.chip--mis)'), function (b) {
      b.classList.toggle('on', b.dataset.v === filtro.tipo);
      var n = porTipo.filter(function (v) { return deTipo(v, b.dataset.v); }).length;
      b.querySelector('small').textContent = n;
      b.hidden = (n === 0 && b.dataset.v !== '' && b.dataset.v !== filtro.tipo); // sin mágnums no hay chip de mágnum
    });

    // los desplegables se recalculan con lo que queda, para no ofrecer vacíos
    var cu = contar(VINOS.filter(function (v) { return pasa(v, 'uva'); }), 'uvas', true);
    var uvas = {}; Object.keys(cu).forEach(function (u) { var k = uvaClave(u); uvas[k] = (uvas[k] || 0) + cu[u]; });
    llenarSelect('cv-f-uva', Object.keys(uvas).sort(function (a, b) { return uvas[b] - uvas[a] || a.localeCompare(b); }).map(function (k) { return [k, uvaCanon[k] + ' · ' + uvas[k]]; }), 'Uva');
    var cb = contar(VINOS.filter(function (v) { return pasa(v, 'bodega'); }), 'bodega');
    llenarSelect('cv-f-bodega', Object.keys(cb).sort(function (a, b) { return cb[b] - cb[a] || a.localeCompare(b); }).map(function (k) { return [k, k + ' · ' + cb[k]]; }), 'Bodega');
    var cz = contar(VINOS.filter(function (v) { return pasa(v, 'zona'); }), 'zona');
    var zonas = Object.keys(cz).map(function (z) { var v = VINOS.filter(function (x) { return x.zona === z; })[0]; return [z, v.pais]; });
    zonas.sort(function (a, b) {
      var va = VINOS.filter(function (x) { return x.zona === a[0]; })[0], vb = VINOS.filter(function (x) { return x.zona === b[0]; })[0];
      var ka = ordenZona(va), kb = ordenZona(vb);
      return ka < kb ? -1 : (ka > kb ? 1 : 0);
    });
    llenarSelect('cv-f-zona', zonas.map(function (z) { return [z[0], legible(z[0]) + ' · ' + z[1] + ' · ' + cz[z[0]]]; }), 'Zona');

    // los desplegables enseñan lo que filtra, venga de donde venga (del
    // propio desplegable, de la ficha o del globo), y en negro
    var sync = { 'cv-f-uva': 'uva', 'cv-f-bodega': 'bodega', 'cv-f-zona': 'zona', 'cv-f-precio': 'precio', 'cv-f-parker': 'parker' };
    Object.keys(sync).forEach(function (id) {
      var s = document.getElementById(id), v = filtro[sync[id]] || '';
      if (v && !Array.prototype.some.call(s.options, function (o) { return o.value === v; })) {
        var o = document.createElement('option'); o.value = v;
        o.textContent = (id === 'f-uva' ? (uvaCanon[v] || v) : (id === 'f-zona' ? legible(v) : v)) + ' · 0';
        s.appendChild(o);
      }
      s.value = v; s.classList.toggle('on', !!s.value);
    });
    document.getElementById('cv-f-texto').classList.toggle('on', !!filtro.texto);
    pintarCampos();
    chipMis();
    chipGente();

    pintarActivos();

    pintarLista(lista);
    marcarPaises();
    pieGlobo(lista);
  }

  /* Lo que está filtrando, en una fila de chips con su aspa. Es la salida
     de cualquier filtro, también de los que no tienen desplegable (el país
     que se pincha en el globo) y de los que pone la ficha. */
  function pintarActivos() {
    var chips = [];
    if (filtro.pais && PAISES[filtro.pais]) { chips.push(['pais', 'País', PAISES[filtro.pais].nombre]); }
    // uva, zona, bodega, precio, Parker y la búsqueda ya se ven en su campo,
    // con su aspa: aquí va solo lo que no tiene campo (el país pinchado en
    // el globo, la selección compartida) y «Quitar todos»
    // «mis» no va aquí: el botón de Mis vinos ya enseña el estado y es la salida
    if (filtro.mis === 'compartida') { chips.push(['mis', 'Solo', 'Selección compartida']); }
    filaActivos.innerHTML = '';
    var cuantos = [filtro.pais, filtro.zona, filtro.bodega, filtro.uva, filtro.precio, filtro.parker, filtro.texto, filtro.mis === 'compartida'].filter(Boolean).length;
    filaActivos.classList.toggle('oculto', !chips.length && cuantos < 2);
    chips.forEach(function (c) {
      var b = document.createElement('button'); b.type = 'button'; b.className = 'activo-chip';
      b.innerHTML = '<small>' + c[1] + '</small>' + c[2] + '<i>×</i>';
      b.title = 'Quitar este filtro';
      b.addEventListener('click', function () {
        filtro[c[0]] = '';
        if (c[0] === 'mis') { compartida = null; if (location.hash) { history.replaceState(null, '', location.pathname + location.search); } }
        if (c[0] === 'texto') { document.getElementById('cv-f-texto').value = ''; }
        cerrarFicha(false);
        if (c[0] === 'zona' || c[0] === 'pais') { if (!filtro.pais && !filtro.zona) { paisCerca = null; puntoLL = null; rotuloTexto = ''; alejar(); } }
        aplicar();
      });
      filaActivos.appendChild(b);
    });
    if (cuantos > 1) {
      var t = document.createElement('button'); t.type = 'button'; t.className = 'limpiar'; t.textContent = 'Quitar todos';
      t.addEventListener('click', function () { vaciarFiltro(); cerrarFicha(false); paisCerca = null; puntoLL = null; rotuloTexto = ''; alejar(); aplicar(); });
      filaActivos.appendChild(t);
    }
  }

  function pieGlobo(lista) {
    var rot = document.getElementById('cv-pie-rotulo'), nom = document.getElementById('cv-pie-nombre'), cu = document.getElementById('cv-pie-cuenta');
    if (vinoActivo) {
      rot.textContent = vinoActivo.pais || 'Sin sitio'; nom.textContent = legible(vinoActivo.zona || '');
      var nz = vinoActivo.zona ? VINOS.filter(function (v) { return v.zona === vinoActivo.zona; }).length : 0;
      cu.textContent = nz ? (nz === 1 ? 'El único de la zona' : nz + ' vinos de la zona') : '';
      return;
    }
    if (filtro.pais && PAISES[filtro.pais]) {
      rot.textContent = 'País'; nom.textContent = PAISES[filtro.pais].nombre; cu.textContent = lista.length + (lista.length === 1 ? ' vino' : ' vinos');
      return;
    }
    var ps = {}; lista.forEach(function (v) { if (v.iso) { ps[v.iso] = 1; } });
    var n = Object.keys(ps).length;
    rot.textContent = 'Todo el mundo'; nom.textContent = n === 1 ? PAISES[Object.keys(ps)[0]].nombre : (n + ' países'); cu.textContent = lista.length + (lista.length === 1 ? ' vino' : ' vinos');
  }

  function legible(z) {
    if (!z) { return ''; }
    return z.toLowerCase().replace(/(^|[\s\-'’\/])(\p{L})/gu, function (m, a, b) { return a + b.toUpperCase(); })
      .replace(/\b(De|Del|Di|Du|Da|Y|La|Le|Les|Des|Der|Von|Van|Of|And|Sur|El)\b/g, function (m) { return m.toLowerCase(); })
      .replace(/\bD(['’])(\p{L})/gu, function (m, a, b) { return 'd' + a + b.toUpperCase(); })
      .replace(/^Otras ((?:\p{L}+ ?)+)$/u, function (m, a) { return 'Otras ' + a.toLowerCase(); })
      .replace(/^(\p{L})/u, function (m) { return m.toUpperCase(); })
      .replace(/D\.o\.?/, 'D.O.');
  }
  function anadas(v) { return v.anadas && v.anadas.length ? v.anadas.join(' / ') : ''; }
  /* la ubicación en bodega: el número (tintos) o las letras (el resto) que
     la carta pone delante de cada vino. Va siempre, pero sin llamar */
  function sitio(v) { if (!CFG.ubicacion) { return ''; } var s = v.ref || v.codigo; return s ? '<span class="sitio" title="Ubicación en bodega">' + s + '</span>' : ''; }
  function parkerAnada(v) { return !v.parker_anada ? '' : (v.parker_anada === 'NV' ? 'sin añada' : 'añada ' + v.parker_anada); }
  function parker(v) { return v.parker ? '<em class="rp" title="Puntos Parker (The Wine Advocate)' + (parkerAnada(v) ? ', ' + parkerAnada(v) : '') + '">' + (v.parker_txt || v.parker) + ' RP</em>' : ''; }

  /* ── La lista ──────────────────────────────────────────────────────── */
  var listaEl = document.getElementById('cv-lista');

  /* El orden de la lista. Primero lo de la Región (Jumilla, Yecla, Bullas,
     Murcia), que es lo que la casa quiere delante; después el resto de
     España por orden alfabético de zona; y luego los demás países, también
     en alfabético, con sus zonas en alfabético dentro. Dentro de cada zona,
     los vinos quedan en el orden de la carta, que es el del sumiller. */
  var REGION = {}; (CFG.region || []).forEach(function (z, i) { REGION[z] = i + 1; });
  function ordenZona(v, campo) {
    var s = v[campo || 'zona'] || v.zona || 'zzz';
    var z = normal(s), p = normal(v.pais || 'zzz');
    if (REGION[s]) { return '0-' + REGION[s]; }
    if (v.iso === 'ESP') { return '1-' + z; }
    return '2-' + p + '-' + z;
  }
  function ordenar(lista) {
    return lista.map(function (v, i) { return { v: v, i: i, k: ordenZona(v, 'seccion') + '|' + (v.formato === 'magnum' ? '1' : '0') }; })
      .sort(function (a, b) { return a.k < b.k ? -1 : (a.k > b.k ? 1 : a.i - b.i); })
      .map(function (x) { return x.v; });
  }

  /* La cabecera de la vista de mis vinos: cuántos hay, dónde se guardan y
     qué se puede hacer con ellos. Los vinos van debajo agrupados como en
     la carta, con su precio, su zona y su sitio en bodega. */
  function cabeceraMis(n) {
    var c = document.createElement('div'); c.className = 'mis-cab';
    c.innerHTML = '<div><h3>Mis vinos</h3><p>' + (n ? (n === 1 ? 'Tu favorito de la carta.' : 'Tus ' + n + ' favoritos de la carta.') + ' Para pedirlos hoy o para la próxima vez.' : 'Los que marques con el corazón, para pedirlos hoy o para la próxima vez.') + '</p></div>';
    if (n) {
      var acc = document.createElement('div'); acc.className = 'mis-acciones';
      var c2 = document.createElement('button'); c2.type = 'button'; c2.className = 'compartir'; c2.innerHTML = CORAZON.replace('<svg', '<svg style="width:13px;height:13px"') + 'Compartir';
      c2.addEventListener('click', function () { compartir(Object.keys(favs), 'Mis ' + cuantosFavs() + ' vinos'); });
      var q = document.createElement('button'); q.type = 'button'; q.className = 'limpiar'; q.textContent = 'Quitar todos';
      q.addEventListener('click', function () { var eran = Object.keys(favs); favs = {}; guardarFavs(); moverGente(eran, -1); chipMis(); aplicar(); avisar('Mis vinos, vacío'); });
      acc.appendChild(c2); acc.appendChild(q); c.appendChild(acc);
    }
    return c;
  }
  function cabeceraGente(n) {
    var c = document.createElement('div'); c.className = 'mis-cab';
    c.innerHTML = '<div><h3>Los más guardados</h3><p>' + (n === 1 ? 'El vino' : 'Los ' + n + ' vinos') + ' que más personas tienen en «Mis vinos», de más a menos. La cifra va junto al corazón.</p></div>';
    return c;
  }
  function pintarLista(lista) {
    listaEl.innerHTML = '';
    var agrupar = !filtro.top;
    if (filtro.top) {
      listaEl.appendChild(cabeceraGente(lista.length));
      if (!lista.length) {
        listaEl.insertAdjacentHTML('beforeend', '<p class="vacio">Con esos filtros no queda ninguno de los más guardados.</p>');
        return;
      }
    } else if (filtro.mis === 'mis') {
      listaEl.appendChild(cabeceraMis(cuantosFavs()));
      if (!cuantosFavs()) {
        listaEl.insertAdjacentHTML('beforeend', '<div class="mis-vacio">' + CORAZON + '<b>Todavía no hay ninguno</b>Marca con el corazón los vinos que te gusten y aquí los tendrás, con su precio, cuando vuelvas a la carta.</div>');
        return;
      }
      if (!lista.length) {
        listaEl.insertAdjacentHTML('beforeend', '<p class="vacio">Ninguno de tus vinos entra en esos filtros.</p>');
        return;
      }
    } else if (!lista.length) {
      listaEl.innerHTML = '<p class="vacio">Con esos filtros no queda ningún vino.</p>';
      return;
    }
    lista = ordenar(lista);
    if (filtro.top) { lista = lista.map(function (v, i) { return { v: v, i: i }; }).sort(function (a, b) { return personas(b.v) - personas(a.v) || a.i - b.i; }).map(function (x) { return x.v; }); }
    var frag = document.createDocumentFragment(), ultima = null;
    if (filtro.mis === 'compartida') {
      var aviso = document.createElement('div'); aviso.className = 'compartida';
      aviso.innerHTML = '<b>Selección compartida</b><span>' + lista.length + (lista.length === 1 ? ' vino' : ' vinos') + ' que alguien ha elegido para ti.</span>';
      var g1 = document.createElement('button'); g1.type = 'button'; g1.className = 'compartir'; g1.innerHTML = CORAZON.replace('<svg', '<svg style="width:13px;height:13px"') + 'Guardar como mis vinos';
      var todosYa = lista.every(esFav);
      if (todosYa) { g1.disabled = true; g1.innerHTML = CORAZON.replace('<svg', '<svg style="width:13px;height:13px"') + 'Ya están en mis vinos'; g1.style.opacity = '.5'; }
      g1.addEventListener('click', function () { var nuevas = []; lista.forEach(function (v) { if (!favs[v.llave]) { nuevas.push(v.llave); } favs[v.llave] = 1; }); guardarFavs(); moverGente(nuevas, 1); chipMis(); pintarLista(lista); avisar('Guardados en mis vinos'); });
      aviso.appendChild(g1);
      frag.appendChild(aviso);
    }
    var ultimoGrupo = null;
    lista.forEach(function (v) {
      var clave = (v.seccion || v.zona || '');
      if (agrupar && clave !== ultima) {
        ultima = clave; ultimoGrupo = null;
        var g = document.createElement('div'); g.className = 'grupo';
        g.innerHTML = '<h3>' + (legible(clave) || 'Sin zona') + '</h3><small>' + (v.pais || '') + '</small>';
        frag.appendChild(g);
      }
      // el subrótulo de la carta: la bodega («Bodega Casa Castillo») o el
      // estilo («Grandes Maisons»), tal y como lo agrupa el sumiller
      if (agrupar && (v.grupo || '') !== ultimoGrupo) {
        ultimoGrupo = v.grupo || '';
        if (ultimoGrupo) { var h = document.createElement('h4'); h.className = 'subgrupo'; h.textContent = ultimoGrupo; frag.appendChild(h); }
      }
      var b = document.createElement('button'); b.type = 'button'; b.className = 'vino' + (vinoActivo === v ? ' activo' : '');
      b.dataset.i = v.i;
      b.innerHTML =
        '<span class="vino__nombre">' + v.nombre + (anadas(v) ? '<span>' + anadas(v) + '</span>' : '') + (v.formato === 'magnum' ? '<em>Mágnum</em>' : '') + parker(v) + '</span>' +
        '<span class="vino__precio">' + (v.precio ? v.precio + ' €' : '') + '</span>' +
        '<span class="vino__datos">' + (v.uvas_texto || v.uvas.join(', ')) + (v.bodega ? ' · <b>' + v.bodega + '</b>' : '') + sitio(v) + '</span>';
      b.addEventListener('click', function () { elegir(v); });
      var fila = document.createElement('div'); fila.className = 'fila';
      fila.appendChild(b); if (CFG.favoritos) { fila.appendChild(botonCorazon(v)); }
      frag.appendChild(fila);
    });
    listaEl.appendChild(frag);
  }

  /* ── La ficha ──────────────────────────────────────────────────────── */
  var vinoActivo = null;
  var fichaEl = document.getElementById('cv-ficha');
  /* Con el mapa en escritorio la página no se desplaza: la rueda, caiga
     sobre el globo, el título o los filtros, mueve la lista (o la ficha,
     si está abierta). Sobre la propia lista se deja el scroll nativo. */
  raiz.querySelector('.escena').addEventListener('wheel', function (e) {
    if (diseno() !== 'mundo' || modo() === 'lista' || window.innerWidth <= 900) { return; }
    var caja = listaEl.classList.contains('oculto') ? fichaEl : listaEl;
    var paso = e.deltaMode === 1 ? e.deltaY * 16 : (e.deltaMode === 2 ? e.deltaY * caja.clientHeight : e.deltaY);
    if (caja.contains(e.target)) {
      // sobre la propia lista: el scroll nativo, salvo que esté en un tope
      // (entonces nada: que no se vaya la página)
      var tope = (paso < 0 && caja.scrollTop <= 0) || (paso > 0 && caja.scrollTop + caja.clientHeight >= caja.scrollHeight - 1);
      if (tope) { e.preventDefault(); }
      return;
    }
    caja.scrollTop += paso;
    e.preventDefault();
  }, { passive: false });
  function elegir(v) {
    vinoActivo = v;
    puntoLL = (v.lon != null) ? [v.lon, v.lat] : null;
    marcarPaises();
    paisCerca = v.iso || null; rotuloTexto = legible(v.zona || '');
    if (puntoLL) { girarA(v.lon, v.lat, 1400, zoomZona(v)); } else { pintar(); }
    if (red) { red.encarar(v); }
    pintarFicha(v);
    listaEl.classList.add('oculto'); fichaEl.classList.remove('oculto');
    pieGlobo([]);
    if (window.innerWidth <= 900) { document.getElementById('cv-panel').scrollIntoView({ behavior: reducido ? 'auto' : 'smooth', block: 'start' }); }
  }
  function cerrarFicha(volverALista) {
    var habia = vinoActivo;
    vinoActivo = null; aro.classList.remove('late');
    if (filtro.zona) { var zq = VINOS.filter(function (x) { return x.zona === filtro.zona; })[0]; puntoLL = (zq && zq.lon != null) ? [zq.lon, zq.lat] : null; rotuloTexto = legible(filtro.zona); } else { puntoLL = null; rotuloTexto = ''; }
    fichaEl.classList.add('oculto'); listaEl.classList.remove('oculto');
    if (filtro.pais) { paisCerca = filtro.pais; } else { paisCerca = null; if (habia) { alejar(); } }
    marcarPaises(); pintar();
    if (volverALista !== false) {
      aplicar();
      if (habia) { var b = listaEl.querySelector('[data-i="' + habia.i + '"]'); if (b) { b.scrollIntoView({ block: 'center', behavior: reducido ? 'auto' : 'smooth' }); } }
    }
  }
  function otros(n) { return n === 1 ? 'El otro' : 'Los otros ' + n; }
  function tipoLegible(v) {
    var t = v.tipo.charAt(0).toUpperCase() + v.tipo.slice(1);
    return t + (v.formato === 'magnum' ? ' · Mágnum' : '');
  }
  function pintarFicha(v) {
    var mismaBodega = v.bodega ? VINOS.filter(function (x) { return x.bodega === v.bodega && x !== v; }).length : 0;
    var mismaZona = v.zona ? VINOS.filter(function (x) { return x.zona === v.zona && x !== v; }).length : 0;
    var datos = [];
    if (v.bodega) { datos.push('<b>' + v.bodega + '</b>'); }
    if (v.uvas.length || v.uvas_texto) { datos.push(v.uvas_texto || v.uvas.join(', ')); }
    var lugar = legible(v.zona) + (v.pais ? ', ' + v.pais : '');
    if (v.apelacion && normal(v.apelacion) !== normal(legible(v.zona))) { lugar = v.apelacion + ' · ' + lugar; }
    if (v.zona) { datos.push(lugar); }
    if (v.parker) { datos.push('<b>' + (v.parker_txt || v.parker) + ' puntos Parker</b>' + (parkerAnada(v) ? ' (' + parkerAnada(v) + ')' : '')); }
    fichaEl.innerHTML =
      '<button class="ficha__volver" type="button" id="cv-volver">Volver a la lista</button>' +
      '<p class="ficha__rotulo">' + tipoLegible(v) + (v.anadas.length > 1 ? ' · Añadas ' + anadas(v) : '') + '</p>' +
      '<div class="ficha__cabeza">' +
        '<h2>' + v.nombre + (v.anadas.length === 1 ? '<span>' + v.anadas[0] + '</span>' : '') + '</h2>' +
        '<div class="ficha__derecha">' + (v.precio ? '<div class="ficha__precio">' + v.precio + ' €' + (v.formato === 'magnum' ? '<small>mágnum</small>' : '') + '</div>' : '') + '<span id="cv-ficha-corazon"></span></div>' +
      '</div>' +
      (datos.length ? '<p class="ficha__datos">' + datos.join('<i>·</i>') + '</p>' : '') +
      '<div class="ficha__mas">' +
        (mismaBodega ? '<button type="button" data-mas="bodega">' + otros(mismaBodega) + ' de ' + v.bodega + '</button>' : '') +
        (mismaZona ? '<button type="button" data-mas="zona">' + otros(mismaZona) + ' de ' + legible(v.zona) + '</button>' : '') +
        (v.pais ? '<button type="button" data-mas="pais">Todo lo de ' + v.pais + '</button>' : '') +
      '</div>' +
      ((v.ref || v.codigo) ? '<p class="ficha__nota">Ubicación en bodega ' + sitio(v) + '</p>' : '');
    document.getElementById('cv-volver').addEventListener('click', function () { cerrarFicha(true); });
    if (CFG.favoritos) { document.getElementById('cv-ficha-corazon').replaceWith(botonCorazon(v)); }
    Array.prototype.forEach.call(fichaEl.querySelectorAll('[data-mas]'), function (b) {
      b.addEventListener('click', function () {
        var que = b.dataset.mas;
        vaciarFiltro();
        if (que === 'bodega') { filtro.bodega = v.bodega; }
        if (que === 'zona') { filtro.zona = v.zona; }
        if (que === 'pais') { filtro.pais = v.iso; }
        cerrarFicha(false);
        aplicar();
        if (que === 'pais') { paisCerca = v.iso; girarA(PAISES[v.iso].lon, PAISES[v.iso].lat, 1000, zoomDe(v.iso)); }
        if (que === 'zona') { paisCerca = v.iso; puntoLL = [v.lon, v.lat]; rotuloTexto = legible(v.zona || ''); girarA(v.lon, v.lat, 1100, zoomZona(v)); }
        document.getElementById('cv-panel').scrollIntoView({ behavior: reducido ? 'auto' : 'smooth', block: 'start' });
      });
    });
  }
  function vaciarFiltro() {
    filtro = { tipo: '', uva: '', bodega: '', zona: '', precio: '', pais: '', texto: '', mis: '', top: false };
    document.getElementById('cv-f-texto').value = '';
  }

  /* ── Cables ────────────────────────────────────────────────────────── */
  document.getElementById('cv-f-uva').addEventListener('change', function () { filtro.uva = this.value; cerrarFicha(false); aplicar(); });
  document.getElementById('cv-f-bodega').addEventListener('change', function () { filtro.bodega = this.value; cerrarFicha(false); aplicar(); });
  document.getElementById('cv-f-zona').addEventListener('change', function () {
    filtro.zona = this.value; cerrarFicha(false); aplicar();
    if (filtro.zona) { var z = VINOS.filter(function (v) { return v.zona === filtro.zona; })[0]; if (z && z.lon != null) { paisCerca = z.iso; puntoLL = [z.lon, z.lat]; rotuloTexto = legible(z.zona || ''); girarA(z.lon, z.lat, 1100, zoomZona(z)); } }
    else if (!filtro.pais) { paisCerca = null; alejar(); }
  });
  document.getElementById('cv-f-precio').addEventListener('change', function () { filtro.precio = this.value; cerrarFicha(false); aplicar(); });
  document.getElementById('cv-f-parker').addEventListener('change', function () { filtro.parker = this.value; cerrarFicha(false); aplicar(); });
  var tempo = null;
  document.getElementById('cv-f-texto').addEventListener('input', function () {
    var q = this.value; clearTimeout(tempo);
    tempo = setTimeout(function () { filtro.texto = q.trim(); cerrarFicha(false); aplicar(); }, 160);
  });
  // el aspa de cada campo lo deja en «todas», como elegir la primera opción
  Array.prototype.forEach.call(raiz.querySelectorAll('.filtros .campo__quitar'), function (x) {
    x.addEventListener('click', function (e) {
      e.preventDefault(); e.stopPropagation();
      var s = x.closest('.campo').querySelector('select');
      s.value = ''; s.dispatchEvent(new Event('change'));
    });
  });
  (function () {
    var q = document.getElementById('cv-f-texto'), x = document.getElementById('cv-f-texto-borrar');
    q.addEventListener('input', function () { x.hidden = !q.value; });
    x.addEventListener('click', function () { q.value = ''; x.hidden = true; clearTimeout(tempo); filtro.texto = ''; cerrarFicha(false); aplicar(); q.focus(); });
  }());
  if (!CFG.precios) { raiz.classList.add('sin-precio'); document.getElementById('cv-f-precio').closest('.campo').classList.add('oculto'); }
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && vinoActivo) { cerrarFicha(true); } });

  pintar();
  chipMis();
  aplicar();
  // al volver con vinos guardados, se recuerda una vez por sesión
  /* ── La red ────────────────────────────────────────────────────────────
     La bodega como red: países, zonas, bodegas y uvas, unidos por hilos y
     colocados en 3D por fuerzas, en el propio navegador (medio segundo, y
     se guarda para la próxima visita). Un canvas, sin librerías. Los
     colores salen de los tokens del bloque (los del Kit, o los que ponga
     Elementor), así que de noche o con otro Kit no hay que tocar nada.
     Se engancha a los mismos filtros que el globo: pinchar un punto filtra
     la lista, y lo que filtra la lista se ve en la red. */
  var PREFIJO = '--cv-';
  var UVAS_TINTAS = 'Agiorgitiko,Arcos,Babić,Barbera,Blaufränkisch,Brancellao,Cabernet Franc,Cabernet Sauvignon,Caiño,Callet,Cariñena,Cesanese,Cinsault,Corvina,Croatina,Dolcetto,Fogoneu,Freisa,Gamay,Garnacha,Graciano,Listán Negro,Mantonegro,Mencía,Merlot,Monastrell,Montepulciano,Moristel,Nebbiolo,Negramoll,Pelaverga,Petit Verdot,Pinot Meunier,Pinot Noir,Poulsard,Rondinella,Sangiovese,Sousón,Sumoll,Syrah,Tempranillo,Trepat,Trousseau,Xinomavro,Bobal,Mazuelo,Tinta de Toro,Touriga Nacional,Malbec,Zweigelt,Lagrein,Aglianico,Nero d\'Avola,Primitivo,Negroamaro,Zinfandel,Carignan,Mourvèdre,Tannat,Garnacha Tintorera,Prieto Picudo,Juan García,Rufete,Caíño Tinto,Espadeiro,Merenzao,Bastardo,Mondeuse,Marselan,Pinotage,Spätburgunder,Dornfelder,Frappato,Nerello Mascalese,Sagrantino,Schiava,Teroldego,Grignolino,Ruché,Cannonau,Plavac Mali,Kadarka,Vidadillo,Tinto Fino'.split(',');
  var UVAS_BLANCAS = 'Airén,Albariño,Aligoté,Altesse,Ansonica,Assyrtiko,Biancolella,Bourboulenc,Chardonnay,Chenin Blanc,Falanghina,Fenile,Furmint,Garnacha Blanca,Ginestra,Godello,Gros Manseng,Grüner Veltliner,Hondarribi Zuri,Listán Blanco,Macabeo,Malvasía,Manzoni,Marsanne,Moscatel,Palomino,Parellada,Petit Manseng,Pinot Blanc,Riesling,Ripoli,Roussanne,Sauvignon Blanc,Savagnin,Tempranillo Blanco,Timorasso,Torrontés,Trebbiano d\'Abruzzo,Treixadura,Verdejo,Vijariego,Viognier,Vitovska,Viura,Welschriesling,Xarello,Xarel·lo,Verdicchio,Vermentino,Arneis,Cortese,Fiano,Greco,Garganega,Trebbiano,Friulano,Ribolla Gialla,Pošip,Grk,Merseguera,Pedro Ximénez,Loureiro,Caíño Blanco,Doña Blanca,Colombard,Ugni Blanc,Sylvaner,Silvaner,Muscat,Neuburger,Kerner,Müller-Thurgau,Chasselas,Clairette,Picpoul,Albillo,Malvar,Zalema,Pinot Gris,Pinot Grigio,Gewürztraminer,Sémillon,Semillon,Muscadet,Melon,Jacquère,Malagousia,Moschofilero,Hárslevelű,Moscatel de Alejandría'.split(',');
  var red = null;
  function redModulo() {
    var cont = document.getElementById('cv-red');
    var lienzo = document.getElementById('cv-red-lienzo');
    if (!cont || !lienzo || !lienzo.getContext) { return null; }
    var ctx = lienzo.getContext('2d');
    var tip = document.getElementById('cv-red-tip');
    var formaEl = document.getElementById('cv-red-forma');

    /* — los nodos, a partir de la carta — */
    var N = [], H = [], indice = {};
    function nodo(id, datos) { if (indice[id] !== undefined) { return indice[id]; } datos.id = id; datos.c = 0; indice[id] = N.length; N.push(datos); return indice[id]; }
    var hiloSet = {};
    function hilo(a, b, t) { var k = a + '-' + b; if (hiloSet[k]) { return; } hiloSet[k] = 1; H.push([a, b, t]); }
    var tintas = {}, blancas = {};
    UVAS_TINTAS.forEach(function (u) { tintas[uvaClave(u)] = 1; });
    UVAS_BLANCAS.forEach(function (u) { blancas[uvaClave(u)] = 1; });
    function pielDe(k, nombre) {
      if (tintas[k]) { return 'tinto'; }
      if (blancas[k]) { return 'blanco'; }
      return /blanc|bianc|weiss|verd|gold|moscat|riesling/i.test(nombre) ? 'blanco' : 'tinto';
    }
    function colorTipo(t) { if (t.indexOf('rosado') > -1) { return 'rosado'; } if (t === 'espumoso' || t === 'champagne') { return 'espumoso'; } return t === 'tinto' ? 'tinto' : 'blanco'; }
    var porVino = []; // por vino: {p, z, b, u:[]}
    var dominante = {};
    VINOS.forEach(function (v) {
      if (!v.iso || !PAISES[v.iso]) { porVino.push(null); return; }
      var p = nodo('p:' + v.iso, { t: 'p', n: PAISES[v.iso].nombre, w: 'pais', iso: v.iso });
      var z = v.zona ? nodo('z:' + v.iso + '|' + v.zona, { t: 'z', n: legible(v.zona), zona: v.zona, iso: v.iso }) : -1;
      if (z >= 0) { hilo(p, z, 'pz'); }
      var b = v.bodega ? nodo('b:' + v.bodega, { t: 'b', n: v.bodega, bodega: v.bodega }) : -1;
      if (b >= 0 && z >= 0) { hilo(z, b, 'zb'); }
      var us = [];
      v.uvas.forEach(function (u) {
        var k = uvaClave(u); if (!k) { return; }
        var i = nodo('u:' + k, { t: 'u', n: uvaCanon[k] || u, uva: k, w: null });
        if (N[i].w === null) { N[i].w = pielDe(k, u); }
        if (z >= 0) { hilo(z, i, 'zu'); }
        us.push(i);
      });
      var w = colorTipo(v.tipo || 'tinto');
      [p, z, b].forEach(function (i) { if (i < 0) { return; } N[i].c++; dominante[i] = dominante[i] || {}; dominante[i][w] = (dominante[i][w] || 0) + 1; });
      us.forEach(function (i) { N[i].c++; });
      porVino.push({ p: p, z: z, b: b, u: us, w: w });
    });
    N.forEach(function (d, i) {
      if (d.t === 'z' || d.t === 'b') { var m = '', mc = -1, dm = dominante[i] || {}; Object.keys(dm).forEach(function (k) { if (dm[k] > mc) { mc = dm[k]; m = k; } }); d.w = m || 'tinto'; }
    });
    var n = N.length;
    if (!n) { return null; }
    var vecinos = []; for (var i = 0; i < n; i++) { vecinos.push([]); }
    H.forEach(function (h) { vecinos[h[0]].push(h[1]); vecinos[h[1]].push(h[0]); });
    var padre = []; for (i = 0; i < n; i++) { padre.push(-1); }
    H.forEach(function (h) { if (h[2] === 'pz') { padre[h[1]] = h[0]; } });
    var zonasDe = []; for (i = 0; i < n; i++) { zonasDe.push([]); }
    H.forEach(function (h) { if (h[2] === 'zb' || h[2] === 'zu') { zonasDe[h[1]].push(h[0]); } });
    var vinosDe = []; for (i = 0; i < n; i++) { vinosDe.push([]); }
    porVino.forEach(function (w, k) { if (!w) { return; } vinosDe[w.p].push(k); if (w.z >= 0) { vinosDe[w.z].push(k); } if (w.b >= 0) { vinosDe[w.b].push(k); } w.u.forEach(function (u) { vinosDe[u].push(k); }); });

    /* — la disposición: por fuerzas, en 3D, una vez; luego se guarda — */
    var P = new Float32Array(n * 3), F = new Float32Array(n * 3), masa = new Float32Array(n), esP = new Uint8Array(n);
    var A = new Int32Array(H.length), B = new Int32Array(H.length), L = new Float32Array(H.length);
    var LARGO = { pz: 1.45, zb: 0.5, zu: 1.05 };
    H.forEach(function (h, e) { A[e] = h[0]; B[e] = h[1]; L[e] = LARGO[h[2]]; });
    var semilla = 7;
    function azar() { semilla = (semilla * 1103515245 + 12345) & 0x7fffffff; return semilla / 0x7fffffff; }
    // Con una carta grande (Pura Cepa: mil nodos) la disposición cuesta n²
    // por paso: menos pasos y más milisegundos por cuadro, para que se
    // coloque en unos segundos y no en veinte.
    var PASOS = n > 900 ? 260 : (n > 500 ? 340 : 480), paso = 0, colocada = false;
    var PRESUPUESTO = n > 500 ? 36 : 14;
    var CLAVE_RED = 'caracool-vinos-red';
    var firma = (function () { var h = 0, s = N.map(function (d) { return d.id; }).join('|') + '#2'; for (var i = 0; i < s.length; i++) { h = (h * 31 + s.charCodeAt(i)) | 0; } return String(h); }());
    function arranque() {
      var np = 0; N.forEach(function (d, i) { masa[i] = 1 + Math.sqrt(d.c) * (d.t === 'p' ? 0.9 : d.t === 'z' ? 0.55 : 0.35); esP[i] = d.t === 'p' ? 1 : 0; if (d.t === 'p') { np++; } });
      var k = 0;
      N.forEach(function (d, i) {
        if (d.t !== 'p') { return; }
        var phi = Math.acos(1 - 2 * (k + 0.5) / np), th = Math.PI * (1 + Math.sqrt(5)) * k; k++;
        P[i * 3] = 1.6 * Math.sin(phi) * Math.cos(th); P[i * 3 + 1] = 1.6 * Math.sin(phi) * Math.sin(th); P[i * 3 + 2] = 1.6 * Math.cos(phi);
      });
      var pa = []; for (var i = 0; i < n; i++) { pa.push(-1); }
      H.forEach(function (h) { if (pa[h[1]] < 0) { pa[h[1]] = h[0]; } });
      N.forEach(function (d, i) {
        if (d.t === 'p') { return; }
        var b = pa[i] >= 0 ? pa[i] : -1;
        for (var c = 0; c < 3; c++) { P[i * 3 + c] = (b >= 0 ? P[b * 3 + c] : 0) + (azar() - 0.5) * 0.5; }
      });
    }
    function iteracion() {
      var temp = 0.09 * Math.pow(1 - paso / PASOS, 1.4) + 0.002;
      F.fill(0);
      for (var i = 0; i < n; i++) {
        var xi = P[i * 3], yi = P[i * 3 + 1], zi = P[i * 3 + 2], mi = masa[i];
        for (var j = i + 1; j < n; j++) {
          var dx = xi - P[j * 3], dy = yi - P[j * 3 + 1], dz = zi - P[j * 3 + 2];
          var d2 = dx * dx + dy * dy + dz * dz + 1e-6;
          if (d2 > 10.24) { continue; }
          var dd = Math.sqrt(d2), rep = mi * masa[j] * 0.06 / Math.max(d2, 0.02) / dd;
          F[i * 3] += dx * rep; F[i * 3 + 1] += dy * rep; F[i * 3 + 2] += dz * rep; F[j * 3] -= dx * rep; F[j * 3 + 1] -= dy * rep; F[j * 3 + 2] -= dz * rep;
        }
      }
      for (var e = 0; e < A.length; e++) {
        var a = A[e], b = B[e], ex = P[b * 3] - P[a * 3], ey = P[b * 3 + 1] - P[a * 3 + 1], ez = P[b * 3 + 2] - P[a * 3 + 2];
        var le = Math.sqrt(ex * ex + ey * ey + ez * ez) + 1e-6, fm = (le - L[e]) * 0.9 / le;
        F[a * 3] += ex * fm; F[a * 3 + 1] += ey * fm; F[a * 3 + 2] += ez * fm; F[b * 3] -= ex * fm; F[b * 3 + 1] -= ey * fm; F[b * 3 + 2] -= ez * fm;
      }
      for (i = 0; i < n; i++) {
        var g = esP[i] ? 0.02 : 0.012; F[i * 3] -= P[i * 3] * g; F[i * 3 + 1] -= P[i * 3 + 1] * g; F[i * 3 + 2] -= P[i * 3 + 2] * g;
        var nf = Math.sqrt(F[i * 3] * F[i * 3] + F[i * 3 + 1] * F[i * 3 + 1] + F[i * 3 + 2] * F[i * 3 + 2]) + 1e-9, s = Math.min(nf, temp) / nf;
        P[i * 3] += F[i * 3] * s; P[i * 3 + 1] += F[i * 3 + 1] * s; P[i * 3 + 2] += F[i * 3 + 2] * s;
      }
      paso++;
    }
    function centrar() {
      var cx = 0, cy = 0, cz = 0, i;
      for (i = 0; i < n; i++) { cx += P[i * 3]; cy += P[i * 3 + 1]; cz += P[i * 3 + 2]; }
      cx /= n; cy /= n; cz /= n;
      var m = 1e-6;
      for (i = 0; i < n; i++) { P[i * 3] -= cx; P[i * 3 + 1] -= cy; P[i * 3 + 2] -= cz; m = Math.max(m, Math.abs(P[i * 3]), Math.abs(P[i * 3 + 1]), Math.abs(P[i * 3 + 2])); }
      for (i = 0; i < n * 3; i++) { P[i] /= m; }
    }
    function cargarGuardada() {
      try {
        var g = JSON.parse(localStorage.getItem(CLAVE_RED) || 'null');
        if (g && g.firma === firma && g.p && g.p.length === n * 3) { for (var i = 0; i < n * 3; i++) { P[i] = g.p[i]; } return true; }
      } catch (e) {}
      return false;
    }
    function guardar() {
      try { var arr = []; for (var i = 0; i < n * 3; i++) { arr.push(Math.round(P[i] * 1000) / 1000); } localStorage.setItem(CLAVE_RED, JSON.stringify({ firma: firma, p: arr })); } catch (e) {}
    }
    if (cargarGuardada()) { colocada = true; paso = PASOS; } else { arranque(); }
    // la red se coloca a cachos de unos milisegundos por cuadro: mientras, se ve formarse
    function colocar(presupuesto) {
      var t0 = performance.now();
      while (paso < PASOS && performance.now() - t0 < presupuesto) { iteracion(); }
      if (paso >= PASOS && !colocada) { colocada = true; centrar(); guardar(); if (formaEl) { formaEl.classList.remove('ver'); } }
      else if (!colocada) { centrar(); if (formaEl) { formaEl.classList.add('ver'); } }
    }

    /* — colores: de los tokens del bloque, cada vez que hace falta — */
    var pal = null;
    var sonda = document.createElement('canvas').getContext('2d');
    function rgbDe(s) {
      s = (s || '').trim(); if (!s) { return null; }
      sonda.fillStyle = '#000'; sonda.fillStyle = s; var r = sonda.fillStyle;
      var m = /^#([0-9a-f]{6})$/i.exec(r); if (m) { var v = parseInt(m[1], 16); return [v >> 16, (v >> 8) & 255, v & 255]; }
      m = /^rgba?\(([\d.]+),\s*([\d.]+),\s*([\d.]+)/.exec(r); if (m) { return [+m[1], +m[2], +m[3]]; }
      return null;
    }
    function mezcla(a, b, t) { return [Math.round(a[0] + (b[0] - a[0]) * t), Math.round(a[1] + (b[1] - a[1]) * t), Math.round(a[2] + (b[2] - a[2]) * t)]; }
    function css(c, a) { return a == null ? 'rgb(' + c[0] + ',' + c[1] + ',' + c[2] + ')' : 'rgba(' + c[0] + ',' + c[1] + ',' + c[2] + ',' + a + ')'; }
    function token(nombre, reserva) { var v = getComputedStyle(raiz).getPropertyValue(PREFIJO + nombre); return rgbDe(v) || rgbDe(reserva); }
    function leerPaleta() {
      leerLetras();
      var noche = raiz.dataset.escena === 'noche';
      var tinta = token('tinta', '#241610'), crema = token('crema', '#FAF5EC'), texto = token('texto', '#5C4433');
      var vino = { tinto: token('vino-tinto', '#E0435A'), blanco: token('vino-blanco', '#F2DC8A'), rosado: token('vino-rosado', '#F2A0A6'), espumoso: token('vino-espumoso', '#FBECB4') };
      var blanco = [255, 255, 255], negro = [0, 0, 0];
      var colores = { pais: [mezcla(tinta, blanco, noche ? 0.25 : 0), tinta, noche ? mezcla(tinta, crema, 0.4) : tinta] };
      Object.keys(vino).forEach(function (k) {
        var c = vino[k];
        // de día el color se asienta hacia la tinta; de noche brilla: núcleo claro, cuerpo, halo oscuro
        colores[k] = noche ? [mezcla(c, blanco, 0.55), c, mezcla(c, negro, 0.45)] : [mezcla(c, tinta, 0.45), mezcla(c, tinta, 0.45), mezcla(c, tinta, 0.45)];
      });
      pal = { noche: noche, tinta: tinta, crema: crema, texto: texto, colores: colores, halo: css(crema), hilo: css(tinta), polvoN: noche ? 900 : 320, polvoAlfa: noche ? 0.55 : 0.10 };
      estampas = {};
      pintarLeyenda();
    }
    function claveColor(d) { return d.t === 'p' ? 'pais' : d.w; }
    function colorDe(d) { return pal.colores[claveColor(d)]; }
    var estampas = {};
    function estampa(clave, r, fuerte) {
      var id = clave + '|' + r.toFixed(1) + (fuerte ? 'f' : '');
      var e = estampas[id]; if (e) { return e; }
      var c = pal.colores[clave];
      var R = Math.ceil(r * (fuerte ? 4 : 3.4)) + 2, cv = document.createElement('canvas'); cv.width = cv.height = R * 2;
      var g = cv.getContext('2d'), gr = g.createRadialGradient(R, R, 0, R, R, R);
      gr.addColorStop(0, css(c[0])); gr.addColorStop(Math.min(.5, r / R * .85), css(c[1]));
      gr.addColorStop(Math.min(.9, r / R * 1.9), css(c[2], .3)); gr.addColorStop(1, css(c[2], 0));
      g.fillStyle = gr; g.fillRect(0, 0, R * 2, R * 2);
      return (estampas[id] = { cv: cv, R: R });
    }
    var NOMBRE_COLOR = { tinto: 'Tinto', blanco: 'Blanco', rosado: 'Rosado', espumoso: 'Espumoso' };
    function pintarLeyenda() {
      var el = document.getElementById('cv-red-leyenda'); if (!el) { return; }
      var h = ['<span><i></i>País</span><span><i class="z"></i>Zona</span><span><i class="b"></i>Bodega</span><span><i class="u"></i>Uva</span>'];
      Object.keys(NOMBRE_COLOR).forEach(function (k) { var c = pal.colores[k]; h.push('<span><i class="c" style="background:' + css(c[1]) + ';--halo:' + css(c[2], pal.noche ? 1 : 0) + '"></i>' + NOMBRE_COLOR[k] + '</span>'); });
      el.innerHTML = h.join('');
    }

    /* — cámara — */
    var W = 0, Hh = 0, dpr = 1, cy, sy, cp, sp, S, cx, cyy;
    var yaw = -0.35, pitch = 0.32, zoom = 0.85, vaiven = [0, 0], yawV = 0, pitchV = 0, giroObjetivo = null;
    var F0 = 3.5, ESCALA = 1.4, RADIO = 1.6;
    var mueve = !reducido;
    function medir() {
      var r = lienzo.getBoundingClientRect();
      dpr = Math.min(2, window.devicePixelRatio || 1);
      W = Math.max(1, Math.round(r.width)); Hh = Math.max(1, Math.round(r.height));
      lienzo.width = W * dpr; lienzo.height = Hh * dpr;
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }
    function preparar() {
      var ya = yaw + vaiven[0], pa = pitch + vaiven[1];
      cy = Math.cos(ya); sy = Math.sin(ya); cp = Math.cos(pa); sp = Math.sin(pa);
      var mundo = diseno() === 'mundo', movil = window.innerWidth <= 900;
      // con el panel a la derecha, la red vive en la mitad izquierda, como el globo
      var ancho = (mundo && !movil) ? W * 0.58 : W;
      S = Math.min(ancho, Hh) * (movil ? 0.34 : 0.4) * zoom;
      cx = (mundo && !movil) ? W * 0.35 : W / 2; cyy = (mundo && !movil) ? Hh * 0.55 : Hh / 2;
    }
    var Pp = []; for (i = 0; i < n; i++) { Pp.push([0, 0, 0, 1]); }
    var tmp = [0, 0, 0, 1], pt = [0, 0, 0];
    function proyectar(p, salida) {
      var x = p[0] * cy + p[2] * sy, z = -p[0] * sy + p[2] * cy;
      var y = p[1] * cp - z * sp; z = p[1] * sp + z * cp;
      var e = F0 / (F0 - z);
      salida[0] = cx + x * S * e; salida[1] = cyy - y * S * e; salida[2] = z; salida[3] = e;
      return salida;
    }

    /* — qué se resalta y qué se apaga — */
    var sobre = -1, seleccion = -1, sobreVino = -1, pasan = null;
    function familia(k) {
      var s = {}; if (k < 0) { return s; }
      var d = N[k]; s[k] = 1;
      if (d.t === 'p') { vecinos[k].forEach(function (z) { s[z] = 1; }); }
      else if (d.t === 'z') { vecinos[k].forEach(function (v) { s[v] = 1; }); }
      else { zonasDe[k].forEach(function (z) { s[z] = 1; if (padre[z] >= 0) { s[padre[z]] = 1; } }); }
      return s;
    }
    function familiaVino(w) { var s = {}; if (!w) { return s; } s[w.p] = 1; if (w.z >= 0) { s[w.z] = 1; } if (w.b >= 0) { s[w.b] = 1; } w.u.forEach(function (u) { s[u] = 1; }); return s; }
    function resalteActual() {
      if (vinoActivo && porVino[vinoActivo.i]) { return familiaVino(porVino[vinoActivo.i]); }
      if (sobreVino >= 0 && sobre < 0 && porVino[sobreVino]) { return familiaVino(porVino[sobreVino]); }
      if (sobre >= 0) { return familia(sobre); }
      if (seleccion >= 0) { return familia(seleccion); }
      return null;
    }
    // lo que dicen los filtros: qué nodo está elegido y qué nodos tienen algún vino que pase
    function actualizar() {
      seleccion = -1;
      if (filtro.uva && indice['u:' + filtro.uva] !== undefined) { seleccion = indice['u:' + filtro.uva]; }
      else if (filtro.bodega && indice['b:' + filtro.bodega] !== undefined) { seleccion = indice['b:' + filtro.bodega]; }
      else if (filtro.zona) { var zid = Object.keys(indice).filter(function (k) { return k.indexOf('z:') === 0 && k.split('|')[1] === filtro.zona; })[0]; if (zid) { seleccion = indice[zid]; } }
      else if (filtro.pais && indice['p:' + filtro.pais] !== undefined) { seleccion = indice['p:' + filtro.pais]; }
      var hayFiltro = filtro.tipo || filtro.uva || filtro.bodega || filtro.zona || filtro.pais || filtro.precio || filtro.parker || filtro.texto || filtro.mis || filtro.top;
      pasan = null;
      if (hayFiltro) {
        pasan = {};
        VINOS.forEach(function (v) { var w = porVino[v.i]; if (!w || !pasa(v)) { return; } pasan[w.p] = 1; if (w.z >= 0) { pasan[w.z] = 1; } if (w.b >= 0) { pasan[w.b] = 1; } w.u.forEach(function (u) { pasan[u] = 1; }); });
      }
      if (seleccion >= 0 && activo) { encarar(seleccion); }
      pedir();
    }

    /* — pintar — */
    var activo = false, pendiente = false, ultimo = 0, reloj = 0;
    var deriva = new Float32Array(n * 6);
    (function () { var x = 12345; function az() { x = (x * 1103515245 + 12345) & 0x7fffffff; return x / 0x7fffffff; }
      for (var k = 0; k < n * 6; k += 6) { deriva[k] = az() * 6.2832; deriva[k + 1] = az() * 6.2832; deriva[k + 2] = az() * 6.2832; deriva[k + 3] = 6.2832 / (7000 + az() * 6000); deriva[k + 4] = 6.2832 / (8000 + az() * 7000); deriva[k + 5] = 6.2832 / (9000 + az() * 6000); } }());
    var AMP = 0.007;
    /* — la red respira entera, como un pecho: se hincha y se encoge un 3 %,
       tomar aire tarda menos que soltarlo, y la onda sale del centro hacia
       fuera. Con movimiento reducido (o el botón de pausa) se queda quieta. — */
    var ALIENTO = 0.03, RESPIRACION = 7200;
    var alientoN = new Float32Array(n);
    function aliento(t, r) {
      var x = (t + 700 * Math.sin(t / 17000) - r * 1100) / RESPIRACION; x -= Math.floor(x);
      var y = x < 0.42 ? x / 0.42 : 1 - (x - 0.42) / 0.58;
      return y * y * (3 - 2 * y);
    }
    var orden = []; for (i = 0; i < n; i++) { orden.push(i); }
    var pos = []; for (i = 0; i < n; i++) { pos.push([0, 0, 0]); }
    function pedir() { if (activo && !pendiente) { pendiente = true; requestAnimationFrame(cuadro); } }
    function suav(x) { return x < .5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2; }
    function cuadro(ahora) {
      pendiente = false;
      if (!activo) { return; }
      var dt = Math.min(50, ahora - (ultimo || ahora)); ultimo = ahora;
      var vivo = false;
      if (!colocada) { colocar(PRESUPUESTO); vivo = true; }
      if (giroObjetivo) {
        var dy = giroObjetivo[0] - yaw, dp = giroObjetivo[1] - pitch;
        yaw += dy * Math.min(1, dt / 160); pitch += dp * Math.min(1, dt / 160);
        if (Math.abs(dy) < 0.002 && Math.abs(dp) < 0.002) { giroObjetivo = null; } else { vivo = true; }
      } else if (!arrastre) {
        if (Math.abs(yawV) > 0.00005 || Math.abs(pitchV) > 0.00005) { yaw += yawV * dt; pitch += pitchV * dt; yawV *= 0.92; pitchV *= 0.92; vivo = true; }
        else { yawV = pitchV = 0; }
      }
      pitch = Math.max(-1.2, Math.min(1.2, pitch));
      // el universo nunca se para del todo: la cámara se balancea unos grados y vuelve
      if (mueve) { vaiven[0] = 0.085 * Math.sin(ahora / 4100); vaiven[1] = 0.03 * Math.sin(ahora / 5600 + 1.3); vivo = true; }
      else if (Math.abs(vaiven[0]) + Math.abs(vaiven[1]) > 0.0005) { vaiven[0] *= 0.94; vaiven[1] *= 0.94; vivo = true; }
      else { vaiven[0] = vaiven[1] = 0; }
      reloj = ahora;
      pintarRed();
      if (vivo) { pedir(); }
    }
    function radioDe(d, e) {
      var b;
      if (d.t === 'p') { b = pal.noche ? 4.5 + Math.sqrt(d.c) * 0.42 : 5.5 + Math.sqrt(d.c) * 0.55; }
      else if (d.t === 'z') { b = 2.6 + Math.sqrt(d.c) * 0.35; }
      else if (d.t === 'b') { b = 1.9 + Math.sqrt(d.c) * 0.2; }
      else { b = 2.4 + Math.sqrt(d.c) * 0.22; }
      return b * (0.75 + e * 0.35) * Math.sqrt(zoom);
    }
    function pintarRed() {
      if (!pal) { leerPaleta(); }
      preparar();
      ctx.clearRect(0, 0, W, Hh);
      pintarPolvo();
      var k, j, amp = mueve ? AMP : 0;
      for (k = 0; k < n; k++) {
        var q = pos[k]; j = k * 6;
        var px = P[k * 3], py = P[k * 3 + 1], pz = P[k * 3 + 2];
        var al = mueve ? aliento(reloj, Math.sqrt(px * px + py * py + pz * pz)) : 0.5;
        alientoN[k] = al;
        var es = ESCALA * (1 + (mueve ? ALIENTO : 0) * (al * 2 - 1));
        q[0] = px * es + amp * Math.sin(reloj * deriva[j + 3] + deriva[j]);
        q[1] = py * es + amp * Math.sin(reloj * deriva[j + 4] + deriva[j + 1]);
        q[2] = pz * es + amp * Math.sin(reloj * deriva[j + 5] + deriva[j + 2]);
        proyectar(q, Pp[k]);
      }
      var resalte = resalteActual();
      if (pal.noche) { pintarNebulosas(resalte); }
      pintarHilos(resalte);
      orden.sort(function (a, b) { return Pp[a][2] - Pp[b][2]; });
      var etiquetas = [];
      for (var o = 0; o < n; o++) {
        k = orden[o]; var d = N[k], p = Pp[k], z = p[2];
        var prof = 0.3 + 0.7 * (z + RADIO) / (2 * RADIO);
        var alfa = prof;
        var apagado = (resalte && !resalte[k]) || (pasan && !pasan[k]);
        if (apagado) { alfa *= 0.16; }
        var e = p[3], radio = radioDe(d, e), cl = claveColor(d), col = colorDe(d);
        var chispa = mueve ? (d.w === 'espumoso' ? 0.72 + 0.28 * Math.sin(reloj * deriva[k * 6 + 5] * 6 + deriva[k * 6 + 2]) : 0.9 + 0.1 * Math.sin(reloj * deriva[k * 6 + 4] * 3 + deriva[k * 6 + 1])) : 1;
        // lo resaltado va entero: sin profundidad ni parpadeo
        var marcado = k === sobre || k === seleccion, enFam = !!(resalte && resalte[k]);
        if (marcado || enFam) { alfa = 1; chispa = 1; }
        if (mueve) { radio *= 1 + 0.07 * (alientoN[k] - 0.5); }
        // debajo, un disco del color del fondo: lo que pasa por detrás no se transparenta
        if (marcado || enFam || d.t === 'p') {
          ctx.globalAlpha = (marcado || enFam) ? 1 : Math.min(1, alfa);
          ctx.beginPath(); ctx.arc(p[0], p[1], marcado ? radio + 5.6 : (enFam ? radio + 3.5 : radio + 1.2), 0, 6.2832);
          ctx.fillStyle = css(pal.crema); ctx.fill();
        }
        ctx.globalAlpha = Math.min(1, alfa * chispa);
        if (pal.noche) {
          var es = estampa(cl, radio, d.t === 'p' || k === sobre || k === seleccion);
          ctx.drawImage(es.cv, p[0] - es.R, p[1] - es.R);
          if (d.t === 'u') { ctx.beginPath(); ctx.arc(p[0], p[1], Math.max(1, radio * .55), 0, 6.2832); ctx.fillStyle = css(pal.crema); ctx.globalAlpha = Math.min(1, alfa) * .85; ctx.fill(); }
        } else if (d.t === 'u') {
          ctx.beginPath(); ctx.arc(p[0], p[1], radio, 0, 6.2832); ctx.fillStyle = css(pal.crema); ctx.fill(); ctx.lineWidth = 1.2; ctx.strokeStyle = css(col[1]); ctx.stroke();
        } else {
          ctx.beginPath(); ctx.arc(p[0], p[1], radio, 0, 6.2832); ctx.fillStyle = css(col[1]); ctx.fill();
          if (d.t === 'p' || d.t === 'z') { ctx.lineWidth = 1.5; ctx.strokeStyle = css(pal.crema); ctx.stroke(); }
        }
        if (enFam && !marcado) { ctx.beginPath(); ctx.arc(p[0], p[1], radio + 3, 0, 6.2832); ctx.lineWidth = 1; ctx.strokeStyle = css(pal.tinta); ctx.globalAlpha = 1; ctx.stroke(); }
        if (marcado) { ctx.beginPath(); ctx.arc(p[0], p[1], radio + 5, 0, 6.2832); ctx.lineWidth = 1.3; ctx.strokeStyle = css(pal.tinta); ctx.globalAlpha = 1; ctx.stroke(); }
        if (!apagado || k === sobre) { etiquetas.push([k, alfa, radio]); }
      }
      ctx.globalAlpha = 1;
      pintarEtiquetas(etiquetas, resalte);
      if (sobre >= 0) { tip.style.left = Pp[sobre][0] + 'px'; tip.style.top = (Pp[sobre][1] - radioDe(N[sobre], Pp[sobre][3])) + 'px'; }
    }
    function pintarHilos(resalte) {
      var t = pal.tinta, fuertes = [];
      for (var j = 0; j < H.length; j++) {
        var h = H[j], a = Pp[h[0]], b = Pp[h[1]], z = (a[2] + b[2]) / 2;
        var prof = 0.25 + 0.75 * (z + RADIO) / (2 * RADIO), alfa = 0.22 * prof;
        var res = resalte && resalte[h[0]] && resalte[h[1]];
        if (res) { fuertes.push(j); continue; }
        if (resalte) { alfa *= 0.18; }
        if (pasan && !(pasan[h[0]] && pasan[h[1]])) { alfa *= 0.18; }
        ctx.lineWidth = 0.7; if (pal.noche) { alfa *= 0.75; }
        ctx.globalAlpha = alfa;
        ctx.strokeStyle = h[2] === 'zu' ? css(colorDe(N[h[1]])[1]) : css(t);
        ctx.beginPath(); ctx.moveTo(a[0], a[1]); ctx.lineTo(b[0], b[1]); ctx.stroke();
      }
      // lo resaltado, encima y en tinta llena: de punta a punta, sin medios tonos
      ctx.globalAlpha = 1; ctx.lineWidth = 1.3;
      for (var f = 0; f < fuertes.length; f++) {
        h = H[fuertes[f]]; a = Pp[h[0]]; b = Pp[h[1]];
        ctx.strokeStyle = h[2] === 'zu' ? css(colorDe(N[h[1]])[1]) : css(t);
        ctx.beginPath(); ctx.moveTo(a[0], a[1]); ctx.lineTo(b[0], b[1]); ctx.stroke();
      }
    }
    var nebulosas = null;
    function pintarNebulosas(resalte) {
      if (!nebulosas) { nebulosas = []; N.forEach(function (d, k) { if (d.t === 'z' && d.c >= 5) { nebulosas.push(k); } }); }
      for (var i = 0; i < nebulosas.length; i++) {
        var k = nebulosas[i], d = N[k], p = Pp[k];
        if (p[2] < -1.4) { continue; }
        var R = (30 + Math.sqrt(d.c) * 16) * p[3] * Math.sqrt(zoom), c = colorDe(d)[2];
        var a = 0.16 * (0.5 + 0.5 * (p[2] + RADIO) / (2 * RADIO));
        if (resalte && !resalte[k]) { a *= 0.3; }
        if (pasan && !pasan[k]) { a *= 0.3; }
        var g = ctx.createRadialGradient(p[0], p[1], 0, p[0], p[1], R);
        g.addColorStop(0, css(c, a)); g.addColorStop(0.5, css(c, a * .35)); g.addColorStop(1, css(c, 0));
        ctx.fillStyle = g; ctx.fillRect(p[0] - R, p[1] - R, R * 2, R * 2);
      }
    }
    var POLVO = [];
    (function () { var x = 777; function az() { x = (x * 1103515245 + 12345) & 0x7fffffff; return x / 0x7fffffff; }
      for (var i = 0; i < 900; i++) { var u = az() * 2 - 1, th = az() * 6.2832, rr = Math.sqrt(1 - u * u), R = 1.9 + az() * 1.6, b = az();
        POLVO.push([rr * Math.cos(th) * R, u * R, rr * Math.sin(th) * R, 0.5 + b * b * 1.4, az() * 6.2832, 6.2832 / (2500 + az() * 9000), b, az()]); } }());
    function pintarPolvo() {
      var noche = pal.noche, base = noche ? pal.tinta : pal.texto;
      for (var i = 0; i < pal.polvoN; i++) {
        var q = POLVO[i], d = mueve ? 0.05 * Math.sin(reloj * q[5] * .3 + q[4]) : 0;
        pt[0] = q[0] + d; pt[1] = q[1] - d * .6; pt[2] = q[2] + d * .3;
        proyectar(pt, tmp);
        if (tmp[2] > 2.6 || tmp[0] < -10 || tmp[0] > W + 10 || tmp[1] < -10 || tmp[1] > Hh + 10) { continue; }
        var centelleo = mueve && noche ? 0.7 + 0.3 * Math.sin(reloj * q[5] + q[4]) : 1;
        ctx.globalAlpha = pal.polvoAlfa * (0.35 + 0.65 * Math.max(0, (tmp[2] + 3.5) / 7)) * centelleo * (noche ? 0.4 + q[6] * .8 : 1);
        ctx.fillStyle = noche ? (q[7] < .08 ? css(pal.colores.blanco[1]) : q[7] < .13 ? css(pal.colores.tinto[1]) : css(base)) : css(base);
        ctx.beginPath(); ctx.arc(tmp[0], tmp[1], q[3] * tmp[3] * Math.sqrt(zoom) * (noche ? .9 : 1), 0, 6.2832); ctx.fill();
      }
      ctx.globalAlpha = 1;
    }
    /* — rótulos que no se pisan: cada uno recuerda su lado y se va en fundido si deja de caber — */
    var cajas = [], rot = []; for (i = 0; i < n; i++) { rot.push({ lado: 0, alfa: 0, caja: null }); }
    function cabe(x, y, w, h) {
      if (x < 6 || y < 6 || x + w > W - 6 || y + h > Hh - 6) { return false; }
      for (var i = 0; i < cajas.length; i++) { var c = cajas[i]; if (x < c[0] + c[2] && x + w > c[0] && y < c[1] + c[3] && y + h > c[1]) { return false; } }
      return true;
    }
    function sitio(lado, x, y, r, w, h) {
      if (lado === 0) { return [x + r + 5, y, 'left', [x + r + 3, y - h / 2, w + 4, h]]; }
      if (lado === 1) { return [x - r - 5, y, 'right', [x - r - 7 - w, y - h / 2, w + 4, h]]; }
      if (lado === 2) { return [x, y - r - h * .6, 'center', [x - w / 2 - 2, y - r - h * 1.1, w + 4, h]]; }
      return [x, y + r + h * .6, 'center', [x - w / 2 - 2, y + r + h * .1, w + 4, h]];
    }
    var didona, sans, sans2;
    function leerLetras() {
      var cs = getComputedStyle(raiz);
      var fd = (cs.getPropertyValue(PREFIJO + 'didona') || '').trim() || '"Bodoni Moda",Georgia,serif';
      var fs = (cs.getPropertyValue(PREFIJO + 'sans') || '').trim() || 'Inter,system-ui,sans-serif';
      didona = 'italic 500 %px ' + fd; sans = '500 11.5px ' + fs; sans2 = '400 11px ' + fs;
    }
    function pintarEtiquetas(lista, resalte) {
      cajas.length = 0;
      var mundo = diseno() === 'mundo' && window.innerWidth > 900;
      // lo que tapa la interfaz no es sitio para un rótulo
      cajas.push([0, Hh - 150, 56, 150]); cajas.push([0, Hh - 90, 320, 90]); if (mundo) { cajas.push([0, 0, W * 0.38, 400]); }
      var candidatos = [], i;
      for (i = 0; i < lista.length; i++) {
        var k = lista[i][0], alfa = lista[i][1], radio = lista[i][2], d = N[k], p = Pp[k];
        var res = resalte && resalte[k], marcado = (k === sobre || k === seleccion);
        var mostrar = false, fuente, color, peso, tinte = pal.noche ? colorDe(d)[1] : null;
        if (d.t === 'p') { mostrar = true; fuente = didona.replace('%', String(16 + 4 * Math.min(1, Math.max(0, zoom - .6)))); color = pal.tinta; peso = 1; }
        else if (d.t === 'z') { mostrar = marcado || res || zoom >= 1.6 || (p[2] > -0.3 && d.c >= 3); fuente = sans; color = pal.tinta; peso = 3; }
        else if (d.t === 'u') { mostrar = marcado || res || (zoom >= 2.2 && p[2] > 0.3) || (d.c >= 18 && p[2] > -0.2); fuente = didona.replace('%', '13'); color = tinte || pal.texto; peso = 4; }
        else { mostrar = marcado || res || (zoom >= 2.6 && p[2] > 0.5); fuente = sans2; color = pal.texto; peso = 5; }
        var r = rot[k];
        if (!mostrar) { if (r.alfa > 0.001) { r.alfa *= 0.82; if (r.alfa < 0.02) { r.alfa = 0; } } continue; }
        if (marcado) { peso = 0; } else if (res) { peso = 2; }
        candidatos.push({ k: k, clave: peso * 10 + (r.alfa > 0.3 ? 0 : 5), alfa: Math.min(1, alfa + (marcado || res ? .4 : 0)), radio: radio, fuente: fuente, color: color, c: d.c, z: p[2] });
      }
      candidatos.sort(function (a, b) { return a.clave - b.clave || b.c - a.c || b.z - a.z; });
      var escribir = [];
      for (i = 0; i < candidatos.length; i++) {
        var c = candidatos[i], q = Pp[c.k], r2 = rot[c.k];
        ctx.font = c.fuente;
        var w = ctx.measureText(N[c.k].n).width, h = parseInt(c.fuente.match(/(\d+(?:\.\d+)?)px/)[1], 10) * 1.2, puesto = null;
        for (var l = 0; l < 4; l++) { var lado = (r2.lado + l) % 4, st = sitio(lado, q[0], q[1], c.radio, w, h); if (cabe(st[3][0], st[3][1], st[3][2], st[3][3])) { puesto = st; r2.lado = lado; break; } }
        if (puesto) { cajas.push(puesto[3]); r2.caja = puesto[3]; r2.alfa += (1 - r2.alfa) * 0.14; escribir.push([c, puesto]); }
        else { r2.alfa *= 0.8; if (r2.alfa < 0.02) { r2.alfa = 0; } else if (r2.caja) { cajas.push(r2.caja); escribir.push([c, [r2.caja[0] + 2, r2.caja[1] + h / 2, 'left', r2.caja]]); } }
      }
      ctx.textBaseline = 'middle';
      for (i = 0; i < escribir.length; i++) {
        c = escribir[i][0]; var st2 = escribir[i][1], a = c.alfa * rot[c.k].alfa;
        if (a < 0.02) { continue; }
        ctx.font = c.fuente; ctx.textAlign = st2[2]; ctx.globalAlpha = a;
        ctx.lineWidth = 4; ctx.strokeStyle = pal.halo; ctx.lineJoin = 'round'; ctx.strokeText(N[c.k].n, st2[0], st2[1]);
        ctx.fillStyle = css(c.color); ctx.fillText(N[c.k].n, st2[0], st2[1]);
      }
      ctx.textAlign = 'left'; ctx.globalAlpha = 1;
    }

    /* — ratón y dedo — */
    var arrastre = null;
    function buscar(x, y) {
      var mejor = -1, md = 1e9;
      for (var k = 0; k < n; k++) {
        var p = Pp[k]; if (pasan && !pasan[k]) { continue; }
        var dx = p[0] - x, dy = p[1] - y, dd = dx * dx + dy * dy, r = radioDe(N[k], p[3]) + 7;
        if (dd < r * r && dd - p[2] * 40 < md) { md = dd - p[2] * 40; mejor = k; }
      }
      return mejor;
    }
    function claseDe(d) { return d.t === 'p' ? 'País' : d.t === 'z' ? 'Zona' : d.t === 'b' ? 'Bodega' : 'Uva'; }
    function resumen(k) {
      var d = N[k], ws = vinosDe[k], zonas = {}, paises = {}, bodegas = {}, uvas = {};
      ws.forEach(function (i) { var w = porVino[i]; if (w.z >= 0) { zonas[w.z] = 1; } paises[w.p] = 1; if (w.b >= 0) { bodegas[w.b] = 1; } w.u.forEach(function (u) { uvas[u] = 1; }); });
      var nz = Object.keys(zonas).length, np = Object.keys(paises).length, nb = Object.keys(bodegas).length, nu = Object.keys(uvas).length;
      var partes = [ws.length + (ws.length === 1 ? ' vino' : ' vinos')];
      if (d.t === 'p') { partes.push(nz + (nz === 1 ? ' zona' : ' zonas')); partes.push(nb + (nb === 1 ? ' bodega' : ' bodegas')); }
      else if (d.t === 'z') { partes.push(nb + (nb === 1 ? ' bodega' : ' bodegas')); partes.push(nu + (nu === 1 ? ' uva' : ' uvas')); }
      else if (d.t === 'u') { partes.push(nz + (nz === 1 ? ' zona' : ' zonas')); partes.push(np + (np === 1 ? ' país' : ' países')); }
      else { var w0 = porVino[ws[0]]; partes.push(nz === 1 && w0.z >= 0 ? N[w0.z].n : nz + ' zonas'); partes.push(N[w0.p].n); }
      return partes.join(' · ');
    }
    function escapar(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    lienzo.addEventListener('pointermove', function (ev) {
      var r = lienzo.getBoundingClientRect(), x = ev.clientX - r.left, y = ev.clientY - r.top;
      if (arrastre) {
        var dx = ev.clientX - arrastre.x, dy = ev.clientY - arrastre.y;
        yaw += dx * 0.005; pitch += dy * 0.005; yawV = dx * 0.005 / 16; pitchV = dy * 0.005 / 16;
        arrastre.x = ev.clientX; arrastre.y = ev.clientY; arrastre.movido += Math.abs(dx) + Math.abs(dy);
        pedir(); return;
      }
      if (ev.pointerType === 'touch') { return; }
      var k = buscar(x, y);
      if (k !== sobre) { sobre = k; cont.classList.toggle('sobre', k >= 0); pedir(); }
      if (k >= 0) { var d = N[k]; tip.innerHTML = '<b>' + escapar(d.n) + '</b>' + claseDe(d) + ' · ' + escapar(resumen(k)); tip.classList.add('ver'); }
      else { tip.classList.remove('ver'); }
    });
    lienzo.addEventListener('pointerleave', function () { if (sobre >= 0) { sobre = -1; cont.classList.remove('sobre'); tip.classList.remove('ver'); pedir(); } });
    lienzo.addEventListener('pointerdown', function (ev) {
      arrastre = { x: ev.clientX, y: ev.clientY, movido: 0 };
      cont.classList.add('arrastrando'); try { lienzo.setPointerCapture(ev.pointerId); } catch (e) {}
      giroObjetivo = null; yawV = pitchV = 0;
    });
    lienzo.addEventListener('pointerup', function (ev) {
      cont.classList.remove('arrastrando');
      var a = arrastre; arrastre = null;
      if (!a) { return; }
      if (a.movido < 6) {
        var r = lienzo.getBoundingClientRect(), k = buscar(ev.clientX - r.left, ev.clientY - r.top);
        if (k >= 0) { elegirNodo(k); } else if (seleccion >= 0) { elegirNodo(-1); }
      }
      pedir();
    });
    lienzo.addEventListener('pointercancel', function () { arrastre = null; cont.classList.remove('arrastrando'); });
    // la rueda sobre la red acerca; sobre el panel, sigue moviendo la lista
    lienzo.addEventListener('wheel', function (ev) {
      ev.preventDefault(); ev.stopPropagation();
      zoom = Math.max(0.45, Math.min(3.2, zoom * (ev.deltaY < 0 ? 1.08 : 0.926))); pedir();
    }, { passive: false });
    document.getElementById('cv-red-mas').addEventListener('click', function () { zoom = Math.min(3.2, zoom * 1.25); pedir(); });
    document.getElementById('cv-red-menos').addEventListener('click', function () { zoom = Math.max(0.45, zoom / 1.25); pedir(); });
    var moverBtn = document.getElementById('cv-red-mover');
    moverBtn.classList.toggle('on', mueve); moverBtn.setAttribute('aria-pressed', mueve ? 'true' : 'false');
    moverBtn.addEventListener('click', function () { mueve = !mueve; moverBtn.classList.toggle('on', mueve); moverBtn.setAttribute('aria-pressed', mueve ? 'true' : 'false'); pedir(); });
    // girar hasta que un nodo quede de frente
    function encarar(k) {
      var x = P[k * 3], y = P[k * 3 + 1], z = P[k * 3 + 2];
      var ya = Math.atan2(-x, z), xz = Math.sqrt(x * x + z * z), pi = Math.atan2(y, xz) * 0.9;
      var dy = ya - yaw; dy = Math.atan2(Math.sin(dy), Math.cos(dy));
      giroObjetivo = [yaw + dy, Math.max(-1.1, Math.min(1.1, pi))];
      pedir();
    }
    // pinchar un punto: el filtro correspondiente, y la lista responde
    function elegirNodo(k) {
      cerrarFicha(false);
      var d = k >= 0 ? N[k] : null;
      var ya = seleccion === k;
      filtro.uva = ''; filtro.bodega = ''; filtro.zona = ''; filtro.pais = '';
      if (d && !ya) {
        if (d.t === 'p') { filtro.pais = d.iso; }
        else if (d.t === 'z') { filtro.zona = d.zona; filtro.pais = d.iso; }
        else if (d.t === 'b') { filtro.bodega = d.bodega; }
        else { filtro.uva = d.uva; }
        paisCerca = d.iso || null;
      } else { paisCerca = null; puntoLL = null; rotuloTexto = ''; }
      aplicar();
      if (window.innerWidth <= 900 && d && !ya) { document.getElementById('cv-panel').scrollIntoView({ behavior: reducido ? 'auto' : 'smooth', block: 'start' }); }
    }
    // pasar por un vino de la lista enciende su camino en la red
    listaEl.addEventListener('pointerover', function (ev) { var b = ev.target.closest('.vino[data-i]'); if (!b) { return; } var i = +b.dataset.i; if (i !== sobreVino) { sobreVino = i; pedir(); } });
    listaEl.addEventListener('pointerleave', function () { if (sobreVino >= 0) { sobreVino = -1; pedir(); } });

    /* — entrar y salir — */
    var tempo = null;
    function mostrar() {
      activo = true; leerPaleta(); medir(); ultimo = 0; pedir();
      if (vinoActivo && porVino[vinoActivo.i]) { var w = porVino[vinoActivo.i]; encarar(w.b >= 0 ? w.b : (w.z >= 0 ? w.z : w.p)); }
    }
    function parar() { activo = false; tip.classList.remove('ver'); }
    window.addEventListener('resize', function () { if (!activo) { return; } clearTimeout(tempo); tempo = setTimeout(function () { medir(); pedir(); }, 120); });
    return { mostrar: mostrar, parar: parar, actualizar: actualizar, repintar: function () { pal = null; if (activo) { pedir(); } }, encarar: function (v) { var w = porVino[v.i]; if (w && activo) { encarar(w.b >= 0 ? w.b : (w.z >= 0 ? w.z : w.p)); } },
      estado: function () { return { n: n, hilos: H.length, colocada: colocada, paso: paso, zoom: zoom, yaw: yaw, seleccion: seleccion, rotulos: rot.filter(function (r) { return r.alfa > 0.5; }).length, noche: pal ? pal.noche : null }; } };
  }
  red = hayRed ? redModulo() : null; // sin botón de Red, la red ni se monta
  if (red && modo() === 'red') { red.mostrar(); }
  if (red) { red.actualizar(); }

  /* ── Ayúdame a elegir ──────────────────────────────────────────────
     «Tres toques» dentro del explorador. Un botón junto a Mis vinos lo abre
     en una capa encima, debajo de la cabecera de la web, con los vinos que
     ya tiene el explorador (no se descargan otra vez). Se cierra con la X,
     con Esc o con «atrás» del navegador, y deja las respuestas puestas
     para la próxima vez. Con #elegir en la dirección, la página se abre ya
     con la capa: sirve para enlazarla desde la portada o la carta. Cada
     vino del resultado lleva a su ficha aquí («Verlo en el mapa»).
     Sale si la casa tiene Tres toques (lo da Caracool en Bodega) y el
     widget no lo ha quitado. */
  var TOQUES = (CFG.toques && window.CaracoolToques) ? CFG.toques : null;
  var capaEl = document.getElementById('cv-elegir');
  var BRUJULA = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5a8.5 8.5 0 1 1 0 17a8.5 8.5 0 1 1 0-17Z"/><path d="m15.2 8.8-2 4.4-4.4 2 2-4.4Z"/></svg>';
  var capa = { lista: null, abierta: false, empuje: false, antes: null, deriva: false, html: '', ver: null, lenis: null };
  var elegirEl = null;
  function textoVer() { return modo() === 'lista' ? 'Ver su ficha' : (modo() === 'red' ? 'Verlo en la red' : 'Verlo en el mapa'); }
  function montarCapa() {
    if (capa.lista) { capa.lista.repintar(); return; }
    var conCFG = typeof CFG !== 'undefined';
    var zonas = Object.keys(REGION).sort(function (a, b) { return REGION[a] - REGION[b]; });
    capa.lista = window.CaracoolToques.montar(capaEl.querySelector('.cv-toques'), {
      vinos: window.CaracoolToques.filas(VINOS),
      region: zonas,
      aqui: TOQUES.aqui || '',
      tramos: TOQUES.tramos || [],
      precios: conCFG ? !!CFG.precios : true,
      favoritos: conCFG ? !!CFG.favoritos : true,
      corazones: CZ ? { url: CZ.url, minimo: CZ.minimo, lista: CZ.lista } : null
    }, {
      ver: function (llave) { var v = porLlave[llave]; if (v) { cerrarCapa(v); } },
      verTexto: textoVer,
      subir: function () { if (capaEl.scrollTop > 0) { capaEl.scrollTo({ top: 0, behavior: reducido ? 'auto' : 'smooth' }); } }
    }) || null;
  }
  function abrirCapa(desdeDireccion) {
    if (!elegirEl || capa.abierta) { return; }
    medirCabecera();
    montarCapa();
    if (!capa.lista) { return; }
    capa.abierta = true;
    capa.antes = document.activeElement;
    capaEl.hidden = false; capaEl.scrollTop = 0;
    if (!reducido) { capaEl.classList.remove('entra'); void capaEl.offsetWidth; capaEl.classList.add('entra'); }
    raiz.classList.add('eligiendo');
    elegirEl.setAttribute('aria-expanded', 'true');
    avisarCabecera();
    // la página de detrás, quieta (también el scroll suave de la web, Lenis,
    // si lo lleva: la capa tiene data-lenis-prevent y Lenis se para); el
    // globo y la red, parados
    capa.html = document.documentElement.style.overflow; document.documentElement.style.overflow = 'hidden';
    var L = window.__caracoolLenis;
    capa.lenis = (L && typeof L.stop === 'function' && !L.isStopped) ? L : null;
    if (capa.lenis) { capa.lenis.stop(); }
    capa.deriva = deriva; deriva = false;
    if (red && modo() === 'red') { red.parar(); }
    capa.empuje = false;
    if (!desdeDireccion) { try { history.pushState({ cvElegir: 1 }, '', location.pathname + location.search + '#elegir'); capa.empuje = true; } catch (e) {} }
    var h = capaEl.querySelector('.tq-escena h2');
    if (h) { h.setAttribute('tabindex', '-1'); h.focus({ preventScroll: true }); }
  }
  function ocultarCapa() {
    if (!capa.abierta) { return; }
    capa.abierta = false;
    capaEl.hidden = true; capaEl.classList.remove('entra');
    raiz.classList.remove('eligiendo');
    elegirEl.setAttribute('aria-expanded', 'false');
    avisarCabecera();
    document.documentElement.style.overflow = capa.html;
    if (capa.lenis) { capa.lenis.start(); capa.lenis = null; }
    deriva = capa.deriva;
    if (red && modo() === 'red') { red.mostrar(); }
    var v = capa.ver; capa.ver = null;
    if (v) {
      // «Verlo en el mapa»: su ficha, aquí
      elegir(v);
      var panel = document.getElementById('cv-panel');
      if (window.innerWidth > 900 && panel.getBoundingClientRect().top < 0) { panel.scrollIntoView({ behavior: reducido ? 'auto' : 'smooth', block: 'start' }); }
      var vuelta = document.getElementById('cv-volver'); if (vuelta) { vuelta.focus({ preventScroll: true }); }
    } else if (capa.antes && capa.antes.focus && document.contains(capa.antes)) {
      capa.antes.focus({ preventScroll: true });
    }
  }
  // cerrar: si la abrió el botón, «atrás» (así el botón atrás del móvil
  // también la cierra); si llegó con #elegir en la dirección, se quita
  function cerrarCapa(v) {
    if (!capa.abierta) { return; }
    capa.ver = v || null;
    if (capa.empuje && location.hash === '#elegir') {
      history.back();
      setTimeout(function () { if (capa.abierta) { ocultarCapa(); } }, 600);
    } else {
      ocultarCapa();
      if (location.hash === '#elegir') { try { history.replaceState(null, '', location.pathname + location.search); } catch (e) {} }
    }
  }
  function mirarDireccion() {
    var quiere = location.hash === '#elegir';
    if (quiere && !capa.abierta) { abrirCapa(true); } else if (!quiere && capa.abierta) { ocultarCapa(); }
  }
  if (TOQUES && capaEl) {
    elegirEl = document.createElement('button'); elegirEl.type = 'button'; elegirEl.className = 'mis-boton elegir-boton';
    elegirEl.innerHTML = BRUJULA + '<span>Ayúdame a elegir</span>';
    elegirEl.title = 'Tres preguntas y te propongo unos vinos';
    elegirEl.setAttribute('aria-haspopup', 'dialog'); elegirEl.setAttribute('aria-expanded', 'false');
    elegirEl.addEventListener('click', function () { abrirCapa(false); });
    document.getElementById('cv-mis-sitio').appendChild(elegirEl);
    document.getElementById('cv-elegir-cerrar').addEventListener('click', function () { cerrarCapa(); });
    window.addEventListener('popstate', mirarDireccion);
    window.addEventListener('hashchange', mirarDireccion);
    // Esc cierra la capa (antes que la ficha), y el tabulador no sale de ella
    window.addEventListener('keydown', function (e) {
      if (!capa.abierta) { return; }
      if (e.key === 'Escape') { e.stopPropagation(); e.preventDefault(); cerrarCapa(); return; }
      if (e.key !== 'Tab') { return; }
      var f = Array.prototype.filter.call(capaEl.querySelectorAll('button:not([disabled]),a[href],[tabindex="0"]'), function (x) { return x.offsetParent !== null; });
      if (!f.length) { return; }
      var dentro = capaEl.contains(document.activeElement);
      if (e.shiftKey && (!dentro || document.activeElement === f[0])) { e.preventDefault(); f[f.length - 1].focus(); }
      else if (!e.shiftKey && (!dentro || document.activeElement === f[f.length - 1])) { e.preventDefault(); f[0].focus(); }
    }, true);
    if (location.hash === '#elegir') { abrirCapa(true); }
  } else if (capaEl) {
    capaEl.parentNode.removeChild(capaEl);
  }

  traerGente();
  contarLosDeAntes();

  (function () {
    var n = cuantosFavs();
    if (!n || filtro.mis) { return; }
    var ya = ''; try { ya = sessionStorage.getItem(CLAVE_AVISO_MIS) || ''; sessionStorage.setItem(CLAVE_AVISO_MIS, '1'); } catch (e) {}
    if (ya) { return; }
    setTimeout(function () { avisar(n === 1 ? 'Tienes un vino en Mis vinos' : 'Tienes ' + n + ' vinos en Mis vinos', 3200); chipMisEl.classList.add('late'); }, 1400);
  }());
  }
}());
