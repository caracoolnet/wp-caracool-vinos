# Caracool Vinos

La carta de vinos de un restaurante, leída del Word que ya edita el propio
restaurante, y publicada como una página que se filtra por tipo, uva,
bodega, zona y precio, con un globo que gira hasta la zona de cada vino.

Es un plugin de la casa, con el mismo chasis que Caracool Carta y Caracool
Churra: cuelga del menú «Caracool» del escritorio, se actualiza desde las
releases de GitHub y no trae ninguna librería de servidor. En el
navegador sí lleva tres (d3-geo, d3-array y topojson-client) y la
geometría del mundo, y van dentro del plugin, nunca de un CDN.

## Cómo se usa

Desde la 0.5.0 el plugin es un **cliente ligero de Caracool Bodega**
(`bodega.caracool.net`): no lee el Word. Lo lee y lo publica Caracool en
Bodega, para cada cliente, y esta web recibe la carta ya leída con su
licencia.

1. **Caracool → Vinos → Conexión.** Se pega la licencia que Caracool emite
   en Bodega para este dominio (una por web; también puede ir en
   `wp-config.php` como `CARACOOL_LICENCIA`).
2. **La carta llega sola.** Cuando Caracool publica una en Bodega, la web
   recibe un aviso y la recoge al momento; por si acaso, la comprueba dos
   veces al día (con ETag: si no hay nada nuevo, no viaja nada), y en la
   pestaña Carta está «Comprobar ahora». Si Bodega no responde, la web
   sigue con la última carta que recibió.
3. **En la página**, el widget de Elementor **Explorar los vinos** o el
   shortcode `[caracool_vinos]`. El bloque es el globo (o la red) con la
   lista y los filtros. Los colores y las letras salen del Kit del sitio.

**El diseño lo decide Caracool en Bodega** (la pestaña Diseño del
cliente): las vistas que tiene la web (Mapa y Red; Lista va siempre), el
diseño, la piel del panel de filtros y las esquinas. Llega con la carta y
manda; si Bodega todavía no ha dicho nada, se queda lo de antes.

En **Ajustes** se enciende o se apaga cada vista que da Bodega, se elige la
escena (de día o de noche) y si el visitante tiene el sol y la luna para
cambiarla, y qué se ve: los precios, la ubicación en bodega, «Mis
vinos», qué zonas van delante en la lista y los textos del pie y de «sin
carta».

## Los colores: el Kit, el widget y la hoja

La hoja del bloque no lleva ningún color escrito: cada token propio
(`--cv-tinta`, `--cv-crema`, `--cv-texto`, `--cv-borde`, `--cv-rojo`…)
apunta a una variable global del Kit de Elementor (`--e-global-color-*`),
con el valor de El Churra de reserva. Los cuatro colores del sistema
(Principal, Secundario, Texto, Acento) existen en todos los Kits; los
demás (`cremaclara`, `crema`, `arena`, `borde`, `ocremadera`) existen
porque se crearon con ese nombre en el Kit de El Churra, y en otra web
caerían en la reserva. Para eso está la pestaña **Estilo** del widget:

- **Colores de la casa**: Tinta, Texto, Acento, Fondo, Papel, Arena y
  Bordes. Los tres primeros van enlazados de fábrica a Secundario, Texto y
  Principal del Kit (el icono del globo en cada control); cualquiera se
  puede enlazar a otro global o dejar suelto para este bloque.
- **Colores del vino**: Tinto, Blanco, Rosado y Espumoso. No son del Kit,
  son el color del vino en la copa; se pueden afinar.
- **Letras**: titulares y texto. Sin tocar, la tipografía Principal y la
  de Texto del Kit.
- **De noche**: fondo, papel, textos, bordes, arena y acento de la escena
  de noche (el acento, enlazado al Acento del Kit).

Ningún control estila nada: cada uno escribe su token en el bloque
(`{{WRAPPER}} .cv-explorador { --cv-tinta: … }`), y la hoja cuelga de los
tokens. Así no hay pelea con el Estilo del tema, y el shortcode sin
Elementor sigue con la cadena Kit → reserva. Los tintes derivados (el país
al pasar, el velo del panel, la banda de la fila) salen de los tokens con
`color-mix()`.

## El panel de filtros: una estructura, tres pieles

El panel va en tres tramos separados por un filete: arriba los tipos, cada
uno con una gota del color del vino (el champagne con un aro dorado, el
mágnum con una botella); en medio la búsqueda, con lupa y un aspa para
borrar, y los cinco filtros (uva, zona, bodega, precio y Parker), cada uno
con su icono; y abajo «Mis vinos» a la izquierda y, a la derecha, Mapa ·
Red · Lista y el sol y la luna.

Cada filtro es un desplegable nativo puesto encima, transparente: se abre
el del sistema, pero se ve el campo con lo elegido (sin la cuenta) y un
aspa para quitarlo. Por eso la fila de chips de debajo ya no repite los
filtros: solo lleva el país pinchado en el globo, la selección compartida
y «Quitar todos». Si un filtro elegido se queda sin vinos al combinarlo
con otros, el campo lo sigue enseñando.

La estructura es la misma en las tres pieles; cambia el aspecto
(`data-panel` en el bloque):

- **Mosaico** (la de El Churra, de fábrica): los tipos en fichas con la
  cuenta a la derecha, la búsqueda como un campo relleno, los filtros como
  baldosas con el icono grande y el sol y la luna como un interruptor que
  se desliza.
- **Píldoras**: todo en píldoras con su icono y los filtros a tercios.
- **Ficha**: sin cajas; los tipos como pestañas subrayadas con la cuenta
  volada, la búsqueda en la letra de titulares sobre una raya y los
  filtros en celdas con el rótulo en versalitas, como la ficha de una cata.

La elige Caracool en Bodega. (En una web a la que Bodega todavía no ha
mandado diseño, vale lo de antes: Ajustes o `panel="ficha"`.)

**Las esquinas** (también desde Bodega). Con «Las de la web», el plugin lee del
Estilo del tema del Kit la esquina de los botones y la de los campos de
formulario, y las pasa al panel como `--cv-radio-boton` y
`--cv-radio-campo`: Mis vinos, Mapa · Red · Lista y el sol y la luna van
como los botones de la web; la búsqueda, los tipos y los filtros, como sus
campos (si el Kit no define campos, como los botones). Solo la esquina: el
color y el relleno los pone el panel, porque cambian de día a noche. Viajan
en un `<style>` pequeño junto al bloque. Con «Las de la piel», o si el Kit
no dice nada, cada piel lleva las suyas. En El Churra los botones del Kit tienen
esquinas de 2 px, así que el mosaico sale casi recto.

## De día y de noche

De día son los colores de la web. De noche, la escena pone la bodega a
oscuras: cambia los tokens (tinta → crema de la noche, crema → cueva) y
todo lo demás sigue igual; el globo queda como la Tierra de noche con las
luces del vino, y la red, como un cielo con estrellas del color del vino.
Se elige en Ajustes (valor por defecto) y en el widget (esta página), o
con `[caracool_vinos escena="noche"]`.
El visitante la cambia con el sol y la luna del panel, y se le recuerda en
su navegador. Si en Ajustes se apaga «Sol y luna» (o con `dianoche="no"`),
no hay botones y manda siempre la escena de la casa. En el editor de
Elementor no se tiene en cuenta lo recordado, para ver lo que dice el
widget.

## La red

El tercer modo, junto a Mapa y Lista (`red="no"` en el shortcode, o el
interruptor de Ajustes, lo quitan). Países, zonas, bodegas y uvas, unidos
por hilos y colocados en 3D por fuerzas, en el navegador, con lo que haya
en la carta (unas décimas de segundo; se guarda en `localStorage` con una
firma de la carta y se rehace sola cuando cambia). Pinchar un punto pone
el filtro correspondiente (uva, bodega, zona o país) y la lista responde;
los filtros atenúan lo que no encaja; pasar por un vino de la lista
enciende su camino (bodega, zona, país, uvas); abrir una ficha gira la red
hasta su bodega. Arrastrar gira, la rueda acerca, el botón ↻ apaga el
movimiento (y con `prefers-reduced-motion` no arranca). Los colores: cada
punto lleva el del tipo de vino que más hace, las uvas el de su piel
(tinta o blanca; la lista está en el propio script), y en la noche brillan
con halo. Los rótulos no se pisan: cada uno recuerda su lado y se va en
fundido si deja de caber.

## Los dos diseños

Se eligen en Ajustes y se puede cambiar cuando se quiera.

- **El mundo** (el de serie). El globo llena la pantalla y hace de hero
  de la página; el rótulo, el título y la entradilla van encima, a la
  izquierda, y la lista flota a la derecha con su propio scroll. En
  escritorio la escena es la pantalla entera y la página no se desplaza: el
  pie de la web se apaga y la rueda, caiga donde caiga, mueve la lista. En
  modo Lista el pie vuelve y la página se desplaza como siempre. Para que
  el hero de foto de la página no se vea a la vez, el plugin pone la clase
  `cv-diseno-mundo` en el `body` y la hoja apaga cualquier elemento con la
  clase `cv-hero-foto`: basta con darle esa clase al contenedor del hero
  en Elementor. En móvil se apila: título, globo y lista.
- **Columnas.** Debajo del hero de foto de la página: el globo a la
  izquierda, clavado mientras se baja por la lista, y la lista a la
  derecha. El título lo pone el hero, así que el plugin no pinta ninguno.

En los dos, el visitante tiene **Mapa · Red · Lista** abajo a la derecha
del panel: «Lista» apaga el globo y deja la carta sola, en
una columna, con el panel viajando animado de un sitio al otro (transiciones
de vista; instantáneo sin soporte o con movimiento reducido). Se recuerda
en su navegador. Los precios los decide la casa en
Ajustes; el visitante no tiene interruptor. Al bajar por la lista, los
filtros se quedan clavados debajo de la cabecera fija de la página (el
bloque la mide al arrancar, y si no hay cabecera fija, arriba del todo).

**Mis vinos.** El corazón de cada vino lo guarda en el navegador de esa
persona, sin cuenta. Abajo a la izquierda del panel hay un botón fijo «Mis vinos»,
con el corazón y la cuenta, que abre su vista: cabecera con cuántos hay,
los vinos agrupados como en la carta (precio, zona y sitio en bodega), y
Compartir (un enlace con la selección, que quien lo abre puede guardar
como suya) y Quitar todos. Dentro siguen funcionando los chips de tipo y
los desplegables. Al guardar un vino, el botón late para señalar dónde
queda; al volver con vinos guardados, un aviso lo recuerda una vez por
sesión.

El contenedor de Elementor donde va el widget tiene que ir **a ancho
completo y sin relleno**: en «columnas» la escena se da su propio aire
(1360 px de ancho máximo) y en «mundo» necesita la pantalla entera.

## Lo que hay dentro

```
caracool-vinos.php          el chasis: menú compartido, actualizador, página de ajustes
inc/caracool-menu.php       el menú «Caracool» compartido (copia byte a byte de wp-caracool-shared)
inc/caracool-licencia.php   la licencia, el eco, el aviso y las peticiones a Bodega (compartido, como el menú)
modules/cv-datos.php        la carta vigente y la anterior, tal y como llegan de Bodega
modules/cv-conexion.php     recoger la carta: aviso, rutina y botón; las pestañas Carta y Conexión
modules/cv-ajustes.php      la pestaña Ajustes
modules/cv-explorador.php   shortcode, datos del bloque y la clase del body
modules/cv-bloque.php       el markup del bloque (generado desde el laboratorio, no se edita)
modules/cv-widget-elementor.php
assets/cv-explorador.css    la hoja del bloque, acotada a .cv-explorador y con las variables del Kit
assets/cv-explorador.js     el explorador
assets/mundo-110m.json      el mundo entero, ligero (Natural Earth 1:110M vía world-atlas)
assets/finos-50m.json       los países con vinos y sus vecinos a 1:50M, para el zoom
assets/lib/                 d3-array, d3-geo, topojson-client y sus licencias
pruebas/                    no va en el zip: las pruebas de navegador; el cartón de punta a punta está en caracool-bodega/pruebas
```

**La hoja, el script y el markup del bloque no se editan a mano.** Salen
del laboratorio (`labs/vinos/vinos.tpl.html`) con `labs/vinos/a-plugin.py`:
se afina el laboratorio, se vuelve a generar y el plugin se lleva lo mismo.
Así hay un solo explorador que mantener.

## Cómo se prueba sin WordPress

```
php pruebas/simular.php "CARTA COMPLETA SEPTIEMBRE 26.docx"   # Bodega publica y Vinos recoge, con un WordPress de cartón
python3 -m http.server 8765 &                                 # desde la carpeta del plugin
node pruebas/probar-web.js                                    # Chromium, Firefox y móvil, con capturas
node pruebas/red.js                                           # la red, de día y de noche
```

`simular.php` llama al de `caracool-bodega/pruebas/`, que carga los dos
plugins en el mismo proceso y deja en `pruebas/salida/` el panel y las
páginas tal y como los pinta el plugin con la carta recibida.

## La licencia y Bodega

`inc/caracool-licencia.php` es un archivo compartido con los demás plugins
de la casa, como el menú: idéntico byte a byte en todos, el primero que
carga define las funciones. Guarda la licencia, dice qué plugins permite
(`caracool_licencia_permite('vinos')`), habla con Bodega
(`caracool_bodega_pedir()`, con la licencia, el dominio, la versión y la
huella de los archivos en las cabeceras) y registra dos rutas REST: el
**eco** (`GET /wp-json/caracool/v1/eco?n=…`, que devuelve el número firmado
con la licencia, para que Bodega compruebe que este dominio la tiene de
verdad) y el **aviso** (`POST /wp-json/caracool/v1/aviso`, firmado, que
dispara la recogida). Si Bodega publica su clave pública en
`CARACOOL_BODEGA_CLAVE_PUBLICA`, la firma de la licencia se verifica
también en local. En dominios de desarrollo (`localhost`, `.test`,
`.local`) no se exige licencia.

## Las uvas y de dónde salen

Bodega manda cada vino con sus uvas y con `uvas_de`, que dice de dónde
salieron: `carta` (el restaurante lo dice), `nombre` (va en el nombre del
vino), `base` (otra carta de la base lo trae), `denominacion` (la
denominación obliga: Barolo es Nebbiolo), `mano` (confirmada en Bodega). Las
que solo se deducen por la zona (Rioja tinto → Tempranillo) no vienen en
`uvas`: vienen en `uvas_probables` hasta que alguien las dé por buenas en
Bodega, y la página no las enseña. Así una web con una carta que no dice
las uvas no enseña una uva equivocada.

## Dónde vive la carta

En una opción de WordPress (`caracool_vinos_carta`), entera, como JSON y tal
y como la mandó Bodega (ya recortada a lo que la página necesita, con su
versión y su ETag): cuatrocientos y pico vinos son unos 200 KB, se leen de
una vez y la página los pinta en el navegador. No hay un post por vino
porque nadie va a editar un vino a mano: el Word es la fuente, y lo que
cambia se cambia allí, en Bodega. La carta anterior se guarda en
`caracool_vinos_anterior`.

## El Word, el lector y el atlas

Viven en Caracool Bodega (`inc/cb-lector-vinos.php`, `inc/cv-zonas.php`,
`inc/cv-uvas.php`): cómo está escrita la carta, qué lee el lector y qué
avisos deja está explicado en su README. Aquí solo llega el resultado.

## Pendiente

- Crear el repo `caracoolnet/wp-caracool-vinos` y su primera release, que
  es de donde lee el actualizador.
- «Novedades»: la carta ya guarda qué vinos entraron en la última
  importación; falta enseñarlo en la página (un chip o una marca en la fila).
- Marcar «la casa recomienda», tres palabras y maridaje por vino, si el
  restaurante quiere rellenarlos.
