/*
 * Caracool Vinos · Tres toques ([caracool_vinos_toques] y el widget «Tres toques»)
 * ─────────────────────────────────────────────────────────────────────
 * Tres preguntas y salen cuatro vinos en grande, con el resto debajo:
 *
 *   1. el tipo (tinto, blanco, rosado, burbujas y, si la carta los tiene,
 *      generosos y dulces u otros), con cuántos hay de cada uno;
 *   2. hasta cuánto, por tramos de precio (los del widget; sin precios en
 *      la web, esta pregunta no sale);
 *   3. de aquí (las zonas que van delante en Ajustes), del resto del país
 *      o de fuera.
 *
 * Cada opción dice cuántos vinos quedan si se elige, y las que dejarían la
 * lista vacía salen apagadas. Lo elegido queda arriba para cambiarlo. Los
 * cuatro de arriba son los más guardados por la gente, si hay cifras; lo
 * que falte se reparte por precio, sin repetir vino, bodega ni zona. No es
 * un ranking de la casa: se dice qué se ha hecho.
 *
 * El corazón es el de «Mis vinos»: se guarda en el mismo sitio que en el
 * explorador y cuenta igual para los corazones de la gente. Si en la misma
 * página está el explorador, los dos se avisan (evento caracool-vinos-mis)
 * y comparten la cuenta y el recuento de los guardados de antes.
 *
 * Los datos van dentro del bloque, en <script type="application/json"
 * data-cv-toques-datos>, para que el editor de Elementor los tenga siempre.
 *
 * El explorador también lo lleva dentro, detrás del botón «Ayúdame a
 * elegir»: lo monta él con window.CaracoolToques.montar(), con los vinos
 * que ya tiene (CaracoolToques.filas() los pasa a filas cortas) y tres
 * extras: ver (cada vino lleva «Verlo en el mapa», que vuelve al
 * explorador con su ficha), verTexto (cómo se llama ese enlace) y subir
 * (cómo se vuelve arriba al cambiar de pregunta, dentro de su capa).
 */
(function () {
  'use strict';

  var CLAVE_FAVS = 'caracool-vinos-mis';
  var CLAVE_CONTADOS = 'caracool-vinos-mis-contados';
  var CORAZON = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20.4 4.9 13.3a4.4 4.4 0 0 1 6.2-6.2l.9.9.9-.9a4.4 4.4 0 0 1 6.2 6.2Z"/></svg>';
  var quieto = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

  function datosDe(raiz) {
    var s = raiz.querySelector('script[data-cv-toques-datos]');
    if (!s) { return null; }
    try { return JSON.parse(s.textContent); } catch (e) { return null; }
  }
  function iniciar(raiz) {
    if (!raiz || raiz.dataset.cvListo) { return; }
    var cfg = datosDe(raiz);
    if (!cfg || !cfg.vinos) { return; }
    raiz.dataset.cvListo = '1';
    montar(raiz, cfg, {});
  }
  /* Para el explorador: los vinos que ya tiene, en las filas cortas de aquí
     [llave, nombre, bodega, añadas, precio, uvas, zona, país, iso, tipo, formato, parker] */
  function filas(vinos) {
    return (vinos || []).map(function (v) {
      var uvas = v.uvas_texto || (v.uvas && v.uvas.join ? v.uvas.join(', ') : '');
      return [v.llave || '', v.nombre || '', v.bodega || '', v.anadas && v.anadas.join ? v.anadas.join(' / ') : '', +v.precio || 0,
        uvas || '', v.zona || '', v.pais || '', v.iso || '', v.tipo || '', v.formato || '', v.parker ? String(v.parker_txt || v.parker) : ''];
    });
  }
  window.CaracoolToques = {
    filas: filas,
    montar: function (raiz, cfg, opc) {
      if (!raiz || raiz.dataset.cvListo || !cfg || !cfg.vinos) { return false; }
      raiz.dataset.cvListo = '1';
      return montar(raiz, cfg, opc || {});
    }
  };
  function arrancar() { Array.prototype.forEach.call(document.querySelectorAll('.cv-toques[data-cv-toques]'), iniciar); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', arrancar); } else { arrancar(); }
  function engancharElementor() {
    if (window.elementorFrontend && window.elementorFrontend.hooks) {
      window.elementorFrontend.hooks.addAction('frontend/element_ready/caracool-vinos-toques.default', function ($scope) {
        var r = $scope[0].querySelector('.cv-toques[data-cv-toques]'); if (r) { iniciar(r); }
      });
      return true;
    }
    return false;
  }
  if (!engancharElementor() && window.jQuery) { window.jQuery(window).on('elementor/frontend/init', engancharElementor); }

  function el(t, a, h) {
    var e = document.createElement(t);
    Object.keys(a || {}).forEach(function (k) {
      if (k === 'text') { e.textContent = a[k]; } else if (a[k] !== null && a[k] !== undefined && a[k] !== false) { e.setAttribute(k, a[k]); }
    });
    (h || []).forEach(function (c) { if (c) { e.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); } });
    return e;
  }
  function euros(n) { return Number(n).toLocaleString('es-ES') + '\u00a0€'; }
  // «RIBERA DEL DUERO» → «Ribera del Duero» (lo mismo que el explorador)
  function legible(z) {
    if (!z) { return ''; }
    return z.toLowerCase().replace(/(^|[\s\-'’\/])(\p{L})/gu, function (m, a, b) { return a + b.toUpperCase(); })
      .replace(/\b(De|Del|Di|Du|Da|Y|La|Le|Les|Des|Der|Von|Van|Of|And|Sur|El)\b/g, function (m) { return m.toLowerCase(); })
      .replace(/\bD(['’])(\p{L})/gu, function (m, a, b) { return 'd' + a + b.toUpperCase(); })
      .replace(/^Otras ((?:\p{L}+ ?)+)$/u, function (m, a) { return 'Otras ' + a.toLowerCase(); })
      .replace(/^(\p{L})/u, function (m) { return m.toUpperCase(); })
      .replace(/D\.o\.?/, 'D.O.');
  }
  function lista3(nombres) { return nombres.slice(0, 3).join(', ') + (nombres.length > 3 ? '…' : ''); }

  function montar(raiz, CFG, OPC) {
    var escena = raiz.querySelector('.tq-escena');
    var hiloEl = raiz.querySelector('.tq-hilo');

    /* ── Los vinos ───────────────────────────────────────────────── */
    function grupo(t) {
      t = t || '';
      if (/champagne|espumoso|cava/.test(t)) { return 'burbujas'; }
      if (/rosado/.test(t)) { return 'rosado'; }
      if (t === 'tinto' || t === 'blanco') { return t; }
      if (/generoso|dulce/.test(t)) { return 'generoso'; }
      return 'otros';
    }
    var REG = {}; (CFG.region || []).forEach(function (z) { REG[z] = 1; });
    // el país de lo de aquí: el de los vinos de esas zonas (España si no hay)
    var cuentaIso = {}, nombreIso = {};
    CFG.vinos.forEach(function (r) { if (REG[r[6]] && r[8]) { cuentaIso[r[8]] = (cuentaIso[r[8]] || 0) + 1; nombreIso[r[8]] = r[7]; } });
    var paisAqui = Object.keys(cuentaIso).sort(function (a, b) { return cuentaIso[b] - cuentaIso[a]; })[0] || 'ESP';
    var nombrePais = nombreIso[paisAqui] || 'España';
    var VINOS = CFG.vinos.map(function (r, i) {
      var v = { i: i, llave: r[0], nombre: r[1], bodega: r[2], anada: r[3], precio: +r[4] || 0, uvas: r[5], zona: r[6], pais: r[7], iso: r[8], tipo: r[9], formato: r[10], parker: r[11] };
      v.grupo = grupo(v.tipo);
      v.origen = REG[v.zona] ? 'aqui' : (v.iso === paisAqui ? 'pais' : 'fuera');
      return v;
    });
    var hayAqui = VINOS.some(function (v) { return v.origen === 'aqui'; });

    /* ── Las preguntas ───────────────────────────────────────────── */
    var TIPOS = [
      { v: 'tinto', t: 'Tinto', q: 'tinto' }, { v: 'blanco', t: 'Blanco', q: 'blanco' }, { v: 'rosado', t: 'Rosado', q: 'rosado' },
      { v: 'burbujas', t: 'Burbujas', q: 'burbujas', sub: 'Champagne, cava y espumosos' },
      { v: 'generoso', t: 'Generosos y dulces', q: 'generosos y dulces' }, { v: 'otros', t: 'Otros', q: 'otros' }
    ].filter(function (o) { return VINOS.some(function (v) { return v.grupo === o.v; }); });
    // «¿Tinto, blanco, rosado o burbujas?» con los que haya; con más de cuatro, más corto
    var tituloTipo = (function () {
      if (TIPOS.length > 4) { return '¿Qué <em>tipo</em> de vino?'; }
      var n = TIPOS.map(function (o, i) { return i === 0 ? '<em>' + o.t + '</em>' : o.q; });
      return '¿' + (n.length === 1 ? n[0] : n.slice(0, -1).join(', ') + ' o ' + n[n.length - 1]) + '?';
    }());
    var PASOS = [{ clave: 'tipo', titulo: tituloTipo, opciones: TIPOS }];
    if (CFG.precios) {
      var t = (CFG.tramos && CFG.tramos.length ? CFG.tramos : [40, 70, 150]).slice().sort(function (a, b) { return a - b; });
      var ops = [{ v: '0-' + t[0], t: 'Hasta ' + euros(t[0]), a: 0, b: t[0] }];
      for (var k = 1; k < t.length; k++) { ops.push({ v: t[k - 1] + '-' + t[k], t: t[k - 1] + ' a ' + euros(t[k]), a: t[k - 1], b: t[k] }); }
      ops.push({ v: t[t.length - 1] + '-', t: 'Más de ' + euros(t[t.length - 1]), a: t[t.length - 1], b: Infinity });
      ops.push({ v: '', t: 'Me da igual', chip: 'Cualquier precio', igual: true });
      PASOS.push({ clave: 'precio', titulo: '¿Hasta <em>cuánto</em>?', opciones: ops, precio: true });
    }
    var zonasAqui = []; VINOS.forEach(function (v) { if (v.origen === 'aqui' && zonasAqui.indexOf(legible(v.zona)) < 0) { zonasAqui.push(legible(v.zona)); } });
    var paisesFuera = {}; VINOS.forEach(function (v) { if (v.origen === 'fuera' && v.pais) { paisesFuera[v.pais] = (paisesFuera[v.pais] || 0) + 1; } });
    var fueraNombres = Object.keys(paisesFuera).sort(function (a, b) { return paisesFuera[b] - paisesFuera[a]; });
    var origenes = [];
    if (hayAqui) { origenes.push({ v: 'aqui', t: CFG.aqui || 'De aquí', sub: lista3(zonasAqui) }); }
    origenes.push({ v: 'pais', t: (hayAqui ? 'Del resto de ' : 'De ') + nombrePais });
    origenes.push({ v: 'fuera', t: 'De fuera', sub: fueraNombres.length ? lista3(fueraNombres) : '' });
    origenes.push({ v: '', t: 'Me da igual', chip: 'De donde sea', igual: true });
    PASOS.push({ clave: 'origen', titulo: hayAqui ? '¿Algo de <em>aquí</em> o de fuera?' : '¿De <em>' + nombrePais + '</em> o de fuera?', opciones: origenes });

    var R = {}; PASOS.forEach(function (p) { R[p.clave] = undefined; });
    var paso = 0;
    function opcion(clave, v) { var p = PASOS.filter(function (x) { return x.clave === clave; })[0]; return p ? p.opciones.filter(function (o) { return o.v === v; })[0] : null; }
    function cumple(v) {
      if (R.tipo && v.grupo !== R.tipo) { return false; }
      if (R.precio) { var o = opcion('precio', R.precio); if (o && !(v.precio > o.a && v.precio <= o.b)) { return false; } }
      if (R.origen && v.origen !== R.origen) { return false; }
      return true;
    }
    function cuenta(clave, v) { var g = R[clave]; R[clave] = v; var n = VINOS.filter(cumple).length; R[clave] = g; return n; }

    /* ── Mis vinos y los corazones de la gente ───────────────────── */
    var favs = {};
    function leerFavs() { favs = {}; try { (JSON.parse(localStorage.getItem(CLAVE_FAVS) || '[]')).forEach(function (k) { favs[k] = 1; }); } catch (e) {} }
    function guardarFavs() { try { localStorage.setItem(CLAVE_FAVS, JSON.stringify(Object.keys(favs))); } catch (e) {} }
    leerFavs();
    var memoria = (function () { try { localStorage.setItem('caracool-vinos-t', '1'); localStorage.removeItem('caracool-vinos-t'); return true; } catch (e) { return false; } }());
    var CZ = (CFG.favoritos && CFG.corazones && CFG.corazones.url && window.fetch) ? CFG.corazones : null;
    var gente = {};
    function personas(v) { return gente[v.llave] || 0; }
    function seVe(v) { return !!CZ && personas(v) >= CZ.minimo; }
    function contados() { try { return localStorage.getItem(CLAVE_CONTADOS) === '1'; } catch (e) { return false; } }
    function avisarGente(llaves, sentido) {
      if (!CZ || !memoria || !llaves.length) { return Promise.resolve(false); }
      return fetch(CZ.url + 'corazon', { method: 'POST', keepalive: true, credentials: 'omit', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ llaves: llaves.slice(0, 200), sentido: sentido }) })
        .then(function (r) { return r.ok; }, function () { return false; });
    }
    // los guardados de antes se cuentan una vez (si el explorador está en la página, lo hace uno de los dos)
    function contarLosDeAntes() {
      if (!CZ || !memoria || contados() || window.__cvContando) { return; }
      window.__cvContando = true;
      var mias = Object.keys(favs);
      var hecho = function () { try { localStorage.setItem(CLAVE_CONTADOS, '1'); } catch (e) {} };
      if (!mias.length) { hecho(); return; }
      avisarGente(mias, 1).then(function (ok) { if (ok) { hecho(); } else { window.__cvContando = false; } });
    }
    function traerGente() {
      if (!CZ) { return; }
      if (!window.__cvGente) {
        window.__cvGente = fetch(CZ.url + 'corazones', { credentials: 'omit', cache: 'no-store' }).then(function (r) { return r.ok ? r.json() : null; }, function () { return null; });
      }
      window.__cvGente.then(function (d) {
        if (!d || !d.n) { return; }
        if (d.minimo) { CZ.minimo = +d.minimo; }
        Object.keys(d.n).forEach(function (k) { gente[k] = Math.max(+d.n[k] || 0, gente[k] || 0); });
        if (paso >= PASOS.length) { ir(paso, false, true); }
      });
    }
    function pintarCorazon(b, v) {
      var on = !!favs[v.llave], ve = seVe(v);
      b.classList.toggle('on', on);
      b.innerHTML = CORAZON + (ve ? '<small>' + personas(v) + '</small>' : '');
      var t = (on ? 'Quitar ' : 'Guardar ') + v.nombre + (on ? ' de' : ' en') + ' Mis vinos' + (ve ? ' · ' + personas(v) + ' personas lo han guardado' : '');
      b.setAttribute('aria-label', t); b.title = t; b.setAttribute('aria-pressed', on ? 'true' : 'false');
    }
    function repintarCorazones() {
      Array.prototype.forEach.call(raiz.querySelectorAll('.tq-corazon[data-llave]'), function (b) { var v = porLlave[b.dataset.llave]; if (v) { pintarCorazon(b, v); } });
    }
    var porLlave = {}; VINOS.forEach(function (v) { if (!porLlave[v.llave]) { porLlave[v.llave] = v; } });
    function corazon(v) {
      var b = el('button', { type: 'button', class: 'tq-corazon', 'data-llave': v.llave });
      pintarCorazon(b, v);
      b.addEventListener('click', function () {
        leerFavs(); // por si el explorador ha cambiado algo
        var on = !favs[v.llave];
        if (on) { favs[v.llave] = 1; } else { delete favs[v.llave]; }
        guardarFavs();
        var sentido = on ? 1 : -1;
        if (CZ) { gente[v.llave] = Math.max(0, personas(v) + sentido); if (contados()) { avisarGente([v.llave], sentido); } }
        try { window.dispatchEvent(new CustomEvent('caracool-vinos-mis', { detail: { de: raiz, llaves: [v.llave], sentido: sentido } })); } catch (e) {}
        repintarCorazones();
        if (on) { b.classList.remove('late'); void b.getBoundingClientRect(); b.classList.add('late'); }
      });
      return b;
    }
    // lo que cambie el explorador (u otro Tres toques) en la misma página
    window.addEventListener('caracool-vinos-mis', function (e) {
      var d = e.detail || {};
      if (d.de === raiz) { return; }
      leerFavs();
      if (CZ && d.llaves && d.sentido) { d.llaves.forEach(function (k) { gente[k] = Math.max(0, (gente[k] || 0) + d.sentido); }); }
      repintarCorazones();
    });

    /* ── Las siluetas ────────────────────────────────────────────── */
    function botella(tipo) {
      var ns = 'http://www.w3.org/2000/svg', burb = tipo === 'burbujas';
      var s = document.createElementNS(ns, 'svg'); s.setAttribute('viewBox', '0 0 40 120'); s.setAttribute('aria-hidden', 'true');
      var p = document.createElementNS(ns, 'path'); p.setAttribute('class', 'tq-botella');
      p.setAttribute('d', burb ? 'M16 2h8v8l-1 20c0 5 11 10 11 22v60c0 3-2 5-5 5H11c-3 0-5-2-5-5V52c0-12 11-17 11-22z' : 'M15 4h10v26c0 4 9 9 9 20v62c0 3-2 5-5 5H11c-3 0-5-2-5-5V50c0-11 9-16 9-20z');
      s.appendChild(p);
      if (burb) { var f = document.createElementNS(ns, 'rect'); f.setAttribute('class', 'tq-capsula'); f.setAttribute('x', '14.5'); f.setAttribute('y', '0'); f.setAttribute('width', '11'); f.setAttribute('height', '18'); f.setAttribute('rx', '2'); s.appendChild(f); }
      var e = document.createElementNS(ns, 'rect'); e.setAttribute('class', 'tq-etiqueta'); e.setAttribute('x', '9'); e.setAttribute('y', '68'); e.setAttribute('width', '22'); e.setAttribute('height', '26'); e.setAttribute('rx', '2'); s.appendChild(e);
      return s;
    }

    /* ── Moverse entre pasos ─────────────────────────────────────── */
    function pintarHilo() {
      hiloEl.innerHTML = '';
      PASOS.forEach(function (p, i) {
        if (R[p.clave] === undefined) { return; }
        var o = opcion(p.clave, R[p.clave]); if (!o) { return; }
        var punto = el('i', { class: i === 0 ? 'lleno' : null });
        if (i === 0) { punto.style.setProperty('--tq-c', 'var(--tq-' + o.v + ')'); }
        var b = el('button', { type: 'button', 'aria-label': 'Cambiar: ' + (o.chip || o.t) }, [punto, o.chip || o.t, el('u', { text: 'Cambiar' })]);
        b.addEventListener('click', function () { ir(i, true); });
        hiloEl.appendChild(b);
      });
    }
    function ola(b, ev) {
      if (quieto || !document.body.animate) { return; }
      var caja = raiz.getBoundingClientRect(), r = b.getBoundingClientRect();
      var x = (ev && ev.clientX ? ev.clientX : r.left + r.width / 2) - caja.left, y = (ev && ev.clientY ? ev.clientY : r.top + r.height / 2) - caja.top;
      var o = el('div', { class: 'tq-ola', 'aria-hidden': 'true' });
      o.style.background = getComputedStyle(b).backgroundColor;
      raiz.appendChild(o);
      var hasta = Math.hypot(Math.max(x, caja.width - x), Math.max(y, caja.height - y));
      o.animate([{ clipPath: 'circle(0px at ' + x + 'px ' + y + 'px)' }, { clipPath: 'circle(' + hasta + 'px at ' + x + 'px ' + y + 'px)' }], { duration: 420, easing: 'cubic-bezier(.6,0,.3,1)', fill: 'forwards' });
      o.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 420, delay: 380, easing: 'ease-out', fill: 'forwards' }).onfinish = function () { o.remove(); };
    }
    function ir(n, limpiar, sinFoco) {
      if (limpiar) { for (var i = n; i < PASOS.length; i++) { R[PASOS[i].clave] = undefined; } }
      if (R.tipo) { raiz.setAttribute('data-tipo', R.tipo); } else { raiz.removeAttribute('data-tipo'); }
      var viejo = escena.firstChild, primera = !viejo;
      var pintar = function () {
        paso = n; escena.innerHTML = '';
        escena.appendChild(n < PASOS.length ? vistaPaso(n) : vistaResultado());
        pintarHilo();
        if (primera || sinFoco) { return; }
        var h = escena.querySelector('h2');
        if (h) { h.setAttribute('tabindex', '-1'); h.focus({ preventScroll: true }); }
        if (OPC.subir) { OPC.subir(); } else if (raiz.getBoundingClientRect().top < 0) { raiz.scrollIntoView({ behavior: quieto ? 'auto' : 'smooth', block: 'start' }); }
      };
      if (viejo && !quieto && !sinFoco) { viejo.classList.add('sale'); setTimeout(pintar, 170); } else { pintar(); }
    }

    function vistaPaso(n) {
      var p = PASOS[n];
      var d = el('section', { class: 'tq-paso' });
      var pasos = el('div', { class: 'tq-pasos', 'aria-label': 'Paso ' + (n + 1) + ' de ' + PASOS.length }, ['Paso ' + (n + 1) + ' de ' + PASOS.length + ' ']);
      PASOS.forEach(function (x, i) { pasos.appendChild(el('span', { class: i <= n ? 'on' : null })); });
      d.appendChild(pasos);
      var h = el('h2', { class: 'tq-pregunta' }); h.innerHTML = p.titulo; d.appendChild(h);
      var g = el('div', { class: 'tq-opciones' });
      var cols = p.opciones.length === 6 ? 3 : Math.min(p.opciones.length, 5);
      g.style.setProperty('--tq-cols', cols);
      var ns = p.opciones.map(function (o) { return cuenta(p.clave, o.v); });
      var maxP = p.precio ? p.opciones[p.opciones.length - 2].a * 2 : 1;
      p.opciones.forEach(function (o, i) {
        var k = ns[i];
        var cls = 'tq-op' + (p.clave === 'tipo' ? ' tq-op--' + o.v : '') + (p.precio && !o.igual ? ' tq-op--precio' : '') + (o.igual ? ' tq-op--igual' : '');
        var b = el('button', { type: 'button', class: cls, 'aria-label': o.t + ', ' + k + (k === 1 ? ' vino' : ' vinos') });
        b.style.animationDelay = (i * 45) + 'ms';
        if (p.clave === 'tipo') {
          var fig = el('span', { class: 'tq-fig', 'aria-hidden': 'true' }); fig.appendChild(botella(o.v)); b.appendChild(fig);
          if (o.v === 'burbujas' && !quieto) {
            for (var j = 0; j < 7; j++) { var bb = el('span', { class: 'tq-burbuja', 'aria-hidden': 'true' }); bb.style.left = (18 + j * 11) + '%'; bb.style.animationDelay = (j * 0.45) + 's'; bb.style.width = bb.style.height = (5 + (j % 3) * 3) + 'px'; b.appendChild(bb); }
          }
        }
        if (p.precio && !o.igual) {
          var barra = el('span', { class: 'tq-barra', 'aria-hidden': 'true' }), dentro = el('i');
          var a = Math.min(o.a, maxP), z = Math.min(o.b, maxP);
          dentro.style.left = (a / maxP * 100) + '%'; dentro.style.width = ((z - a) / maxP * 100) + '%';
          barra.appendChild(dentro); b.appendChild(barra);
        }
        b.appendChild(el('b', { text: o.t }));
        b.appendChild(el('small', {}, [o.sub ? el('span', { text: o.sub }) : null, el('span', { text: k + (k === 1 ? ' vino' : ' vinos') })]));
        b.disabled = k === 0;
        b.addEventListener('click', function (ev) { R[p.clave] = o.v; if (p.clave === 'tipo') { ola(b, ev); } ir(n + 1); });
        g.appendChild(b);
      });
      d.appendChild(g);
      if (n > 0) {
        var atras = el('button', { type: 'button', class: 'tq-boton tq-atras', text: '← Atrás' });
        atras.addEventListener('click', function () { ir(n - 1, true); });
        d.appendChild(atras);
      }
      return d;
    }

    // k de la lista repartidos por precio: el más cercano a cada tramo, sin
    // repetir vino, bodega ni zona si se puede
    function repartir(lista, k) {
      var orden = lista.slice().sort(function (a, b) { return a.precio - b.precio || a.nombre.localeCompare(b.nombre, 'es'); });
      if (orden.length <= k) { return orden; }
      var n = orden.length - 1, sel = [], usado = { i: {}, zona: {}, bodega: {}, nombre: {} };
      var marcas = []; for (var j = 0; j < k; j++) { marcas.push(k === 1 ? n / 2 : n * j / (k - 1)); }
      marcas.forEach(function (m) {
        var mejor = null, coste = Infinity;
        orden.forEach(function (v, i) {
          if (usado.i[v.i]) { return; }
          var c = Math.abs(i - m) / n + (usado.nombre[v.nombre] ? 2 : 0) + (usado.bodega[v.bodega] ? 0.8 : 0) + (usado.zona[v.zona] ? 0.5 : 0);
          if (c < coste) { coste = c; mejor = v; }
        });
        sel.push(mejor);
        usado.i[mejor.i] = usado.nombre[mejor.nombre] = usado.bodega[mejor.bodega] = usado.zona[mejor.zona] = 1;
      });
      return sel.sort(function (a, b) { return a.precio - b.precio; });
    }
    // los cuatro de arriba: primero los más guardados (si hay cifras) y lo que falte, repartido
    function elegir(lista) {
      var top = lista.filter(seVe).sort(function (a, b) { return personas(b) - personas(a) || a.precio - b.precio; }).slice(0, 4);
      if (top.length >= 4 || top.length === lista.length) { return { vinos: top, porGente: top.length }; }
      var resto = lista.filter(function (v) { return top.indexOf(v) < 0; });
      return { vinos: top.concat(repartir(resto, 4 - top.length)), porGente: top.length };
    }

    function vistaResultado() {
      var lista = VINOS.filter(cumple);
      var d = el('section', { class: 'tq-paso' });
      var h = el('h2', { class: 'tq-pregunta' });
      h.innerHTML = lista.length ? (lista.length === 1 ? 'Solo hay <em>uno</em>' : lista.length <= 4 ? 'Son <em>estos ' + lista.length + '</em>' : 'Cuatro para <em>empezar</em>') : 'No queda <em>ninguno</em>';
      d.appendChild(h);
      if (!lista.length) {
        d.appendChild(el('div', { class: 'tq-nada', text: 'Con esas respuestas no sale ningún vino de la carta. Cambia alguna de las de arriba.' }));
        return d;
      }
      var eleccion = elegir(lista), elegidos = eleccion.vinos, ya = {};
      var sub;
      if (lista.length <= 4) { sub = lista.length === 1 ? 'Es el único que encaja.' : 'Son todos los que encajan.'; }
      else if (eleccion.porGente >= 4) { sub = 'Los cuatro que más gente ha guardado de los ' + lista.length + ' que encajan. El resto, justo debajo.'; }
      else if (eleccion.porGente) { sub = 'Primero, ' + (eleccion.porGente === 1 ? 'el que más gente ha guardado' : 'los ' + eleccion.porGente + ' que más gente ha guardado') + '; después, otros ' + (CFG.precios ? 'repartidos por precio' : 'de zonas distintas') + '. El resto, justo debajo.'; }
      else { sub = 'Cuatro de los ' + lista.length + ' que encajan, ' + (CFG.precios ? 'repartidos de menos a más precio' : 'de zonas distintas') + '. El resto, justo debajo.'; }
      var otra = el('button', { type: 'button', class: 'tq-boton', text: 'Empezar otra vez' });
      otra.addEventListener('click', function () { ir(0, true); });
      d.appendChild(el('div', { class: 'tq-cab' }, [el('p', { text: sub }), otra]));
      var g = el('div', { class: 'tq-destacados' });
      elegidos.forEach(function (v, i) { ya[v.i] = 1; g.appendChild(ficha(v, i)); });
      d.appendChild(g);
      var resto = lista.filter(function (v) { return !ya[v.i]; }).sort(function (a, b) { return (CFG.precios ? a.precio - b.precio : 0) || a.nombre.localeCompare(b.nombre, 'es'); });
      if (resto.length) {
        d.appendChild(el('h3', { class: 'tq-resto-t', text: resto.length === 1 ? 'Y uno más' : 'Y los otros ' + resto.length }));
        d.appendChild(el('p', { class: 'tq-resto-nota', text: CFG.precios ? 'De menos a más precio.' : 'Por orden alfabético.' }));
        var ul = el('ul', { class: 'tq-resto' });
        resto.forEach(function (v) { ul.appendChild(fila(v)); });
        d.appendChild(ul);
        var otra2 = el('button', { type: 'button', class: 'tq-boton tq-boton--lleno', text: 'Empezar otra vez' });
        otra2.addEventListener('click', function () { ir(0, true); });
        d.appendChild(el('div', { class: 'tq-acciones' }, [otra2]));
      }
      return d;
    }
    function formato(v) { var f = (v.formato || '').toLowerCase(); return !f || f === 'botella' ? '' : (f === 'magnum' ? 'Mágnum' : v.formato); }
    // dentro del explorador: volver a él con la ficha de ese vino
    function textoVer() { return typeof OPC.verTexto === 'function' ? OPC.verTexto() : (OPC.verTexto || 'Verlo en el mapa'); }
    function botonVer(v) {
      var b = el('button', { type: 'button', class: 'tq-ver', text: textoVer() });
      b.addEventListener('click', function () { OPC.ver(v.llave); });
      return b;
    }
    function ficha(v, i) {
      var t = el('article', { class: 'tq-ficha tq-ficha--' + v.grupo });
      t.style.animationDelay = (i * 70) + 'ms';
      t.appendChild(el('p', { class: 'tq-zona', text: [legible(v.zona), v.pais].filter(Boolean).join(' · ') }));
      var h = el('h3', { text: v.nombre });
      if (v.parker) { h.appendChild(el('span', { class: 'tq-rp', text: v.parker + ' RP', title: 'Puntos Parker (The Wine Advocate)' })); }
      t.appendChild(h);
      if (v.bodega) { t.appendChild(el('p', { class: 'tq-bodega', text: v.bodega })); }
      var meta = [v.anada, v.uvas, formato(v)].filter(Boolean).join(' · ');
      if (meta) { t.appendChild(el('p', { class: 'tq-meta', text: meta })); }
      var pie = el('div', { class: 'tq-pie' }, [CFG.precios && v.precio ? el('span', { class: 'tq-precio', text: euros(v.precio) }) : null, CFG.favoritos ? corazon(v) : null]);
      if (pie.childNodes.length) { t.appendChild(pie); }
      if (OPC.ver) { t.appendChild(botonVer(v)); }
      return t;
    }
    function fila(v) {
      var quien = [el('b', { text: v.nombre }), el('small', { text: [v.bodega, [legible(v.zona), v.pais].filter(Boolean).join(' · '), v.anada, formato(v)].filter(Boolean).join(' · ') })];
      // dentro del explorador, el nombre entero lleva a su ficha
      var nombre = OPC.ver ? el('button', { type: 'button', class: 'tq-fila-ver', title: textoVer() }, quien) : el('span', {}, quien);
      if (OPC.ver) { nombre.addEventListener('click', function () { OPC.ver(v.llave); }); }
      return el('li', {}, [
        nombre,
        CFG.precios && v.precio ? el('span', { class: 'tq-precio', text: euros(v.precio) }) : el('span'),
        CFG.favoritos ? corazon(v) : el('span')
      ]);
    }

    ir(0);
    traerGente();
    contarLosDeAntes();
    // para quien lo monta: volver a pintar lo que se ve (sin animación ni foco)
    return { repintar: function () { ir(paso, false, true); } };
  }
}());
