# Cambios

## 0.5.8 · 28 de septiembre de 2026

- **La esquina de la red, solo con los botones.** Abajo a la izquierda
  quedan el zoom y el botón de movimiento; fuera también la leyenda de
  puntos por colores, que se mezclaba con los puntos de la red. Los
  botones bajan al sitio que dejaba.

## 0.5.7 · 28 de septiembre de 2026

- **El diseño lo decide Caracool desde Bodega.** Lo que se marca en la
  pestaña Diseño del cliente (vistas, diseño, piel del panel y esquinas)
  llega con la carta y manda. En Ajustes, el diseño, la piel y las
  esquinas ya no se tocan: se ve lo que ha decidido Caracool. Si Bodega
  todavía no ha dicho nada, se queda lo de antes.
- **Las vistas.** En Ajustes hay un interruptor para Mapa y otro para Red,
  solo para las que da Bodega; Lista va siempre. El widget y el shortcode
  (`mapa="no"`, `red="si"`) pueden apagar una vista en una página o
  encender una que Bodega dé, nunca una que no dé. Sin Mapa se arranca en
  la Red; con solo Lista no hay botones de vista y la red ni se monta.
- El widget pierde la piel del panel y las esquinas (ahora son de
  Bodega) y gana el botón «Mapa».
- **La esquina de la red**, abajo a la izquierda, se queda con el zoom y
  los puntos por colores: fuera «La bodega como red» y la frase de ayuda.
- Pruebas: el simulador lleva el diseño de punta a punta (Bodega guarda,
  avisa, la web lo recoge y obedece, y vuelve atrás) y `panel.js` mira la
  web sin mapa, con solo Lista y la esquina de la red.

## 0.5.6 · 28 de septiembre de 2026

- **El panel de filtros, nuevo.** Tres tramos separados por un filete: los
  tipos, cada uno con una gota del color del vino; la búsqueda con lupa y
  aspa, y los cinco filtros con su icono; y abajo «Mis vinos» a la
  izquierda y, a la derecha, Mapa · Red · Lista y el sol y la luna.
- **Tres pieles sobre la misma estructura**: Mosaico (de fábrica),
  Píldoras y Ficha. Se eligen en Ajustes, y el widget o el shortcode
  (`panel="…"`) las cambian página a página.
- **Las esquinas de la web.** De fábrica, el panel lleva las esquinas de
  los botones y de los campos de formulario del Kit de Elementor (solo la
  esquina; el color y el relleno siguen siendo del panel). Con «Las de la
  piel», las de cada piel. En el widget se afinan a mano.
- **El sol y la luna.** El visitante cambia de día a noche desde el panel
  y se le recuerda en su navegador (`caracool-vinos-escena`). La casa
  sigue eligiendo con qué escena se abre, y puede quitar los botones en
  Ajustes, en el widget o con `dianoche="no"`. En el editor de Elementor
  no manda lo recordado.
- **Cada filtro enseña lo elegido y tiene su aspa.** La fila de chips de
  debajo ya no los repite: solo lleva el país pinchado en el globo, la
  selección compartida y «Quitar todos». Un filtro que se queda sin
  vinos al combinarlo con otros sigue a la vista.
- La búsqueda dice «Vino, bodega, zona o uva», que es lo que busca.
- Sin precios, desaparece la baldosa de precio entera.
- Pruebas: `pruebas/panel.js`, nueva (las tres pieles, el sol y la luna,
  las esquinas del Kit, sin precios); `puracepa.js` vuelve a filtrar por
  uva.

## 0.5.5 · 28 de septiembre de 2026

- **La red respira.** Toda la red se hincha y se encoge a la vez, un 3 %,
  en una respiración de unos siete segundos: tomar aire tarda menos que
  soltarlo y la onda sale del centro hacia fuera. El vaivén suelto de cada
  punto se reduce a la mitad para que mande el conjunto, y los puntos
  laten un poco con el aire. Con movimiento reducido, o con el botón de
  pausa de la red, se queda quieta.
- **Lo resaltado va entero.** Al pasar por un punto, sus hilos se pintan
  encima de los demás en tinta llena (antes perdían fuerza con la
  profundidad y se veían grises) y sus puntos van sin transparencia ni
  parpadeo. Debajo de cada punto resaltado, y de cada país, hay un disco
  del color del fondo: lo que pasa por detrás ya no se ve a través, ni se
  cuela entre el punto y su aro.

## 0.5.4 · 27 de septiembre de 2026

- **Puntos Parker.** Si la carta que llega de Bodega trae puntos para la
  añada de un vino (`parker`, `parker_txt`, `parker_anada`), el vino los
  enseña con una marca «97 RP» junto al nombre y en su ficha, y aparece un
  desplegable «Parker» (96, 94 o 90 o más) junto al de precio. Sin puntos
  en la carta, el desplegable no existe.
- El chip «Mágnum» solo aparece si la carta tiene mágnums.

## 0.5.3 · 27 de septiembre de 2026

- Los textos de Conexión y del módulo de licencia hablan solo de Vinos y
  Bodega: la licencia es una por web y es de Vinos. Nada más cambia.

## 0.5.2 · 27 de septiembre de 2026

- Lleva dentro la clave pública de Bodega (`CARACOOL_BODEGA_CLAVE_PUBLICA`
  en `inc/caracool-licencia.php`): la firma de la licencia se comprueba en
  la propia web antes de llamar a Bodega. Es la primera versión que se
  instala con `bodega.caracool.net` en marcha.

## 0.5.1 · 26 de septiembre de 2026

- La red con cartas grandes. Con la de Pura Cepa (1.886 vinos, unos mil
  puntos) la disposición tardaba veinte segundos: ahora hace menos pasos y
  dedica más milisegundos a cada cuadro cuando hay más de 500 puntos, y se
  coloca en unos tres segundos (la primera vez; después se guarda). La
  lista de 1.886 vinos se pinta en poco más de un segundo.
- La carta que llega de Bodega trae la procedencia de cada uva (`uvas_de`)
  y, aparte, las uvas probables (`uvas_probables`) que Bodega aún no da por
  buenas. La página solo enseña las que van en `uvas`; lo demás se guarda
  para cuando se decida qué hacer con ello.

## 0.5.0 · 26 de septiembre de 2026

- **Cliente ligero de Bodega.** El plugin ya no lee el Word: lo lee y lo
  publica Caracool en `bodega.caracool.net`, para cada cliente, y esta web
  recibe la carta ya leída con su licencia. Fuera `cv-lector.php`,
  `cv-importar.php` y las tablas de zonas y uvas (viven en Bodega). Si
  Bodega no responde, la web sigue con la última carta que recibió; si la
  licencia está revocada, la carta se retira.
- **Conexión.** Pestaña nueva con la licencia (una por web, para todos los
  plugins de la casa; también en `wp-config.php` como `CARACOOL_LICENCIA`) y
  la dirección de Bodega. La pestaña Carta enseña la carta recibida, su
  versión y la última comprobación, con «Comprobar ahora».
- Tres maneras de enterarse de una carta nueva: el aviso de Bodega al
  publicar (`POST /wp-json/caracool/v1/aviso`, firmado con la licencia), la
  comprobación de rutina dos veces al día (con ETag: si no hay nada nuevo,
  no viaja nada) y el botón.
- `inc/caracool-licencia.php`, compartido con los demás plugins de la casa
  como el menú: la licencia, el eco (`GET /wp-json/caracool/v1/eco`), el
  aviso, la huella de los archivos y las peticiones a Bodega.
- Las zonas que van delante (Ajustes) se resuelven contra la carta
  recibida, sin el atlas.

## 0.4.0 · 26 de septiembre de 2026

- **La bodega como red.** Un tercer modo junto a Mapa y Lista: los países,
  las zonas, las bodegas y las uvas de la carta, unidos por hilos y
  colocados en 3D. Las uvas son lo que lo convierte en red y no en árbol:
  la Garnacha ata veinte zonas de cuatro países. Pinchar un punto filtra la
  carta (una uva, una bodega, una zona, un país); lo que filtra la carta se
  ve en la red; pasar por un vino de la lista enciende su camino. Arrastrar
  gira, la rueda acerca, y la cámara se balancea unos grados y vuelve, sin
  perder de vista lo que se mira. Los rótulos no se pisan: cada uno
  recuerda su lado y se va en fundido si deja de caber.
- La red se coloca sola en el navegador, con lo que haya en la carta (unas
  décimas de segundo, y se guarda para la siguiente visita): una zona o
  una bodega nueva en el Word aparece sin tocar nada. Sin librerías.
- **De día o de noche.** La escena de noche pone la bodega a oscuras: crema
  sobre cueva, el globo como la Tierra de noche, y la red como un cielo
  con estrellas del color del vino en la copa (rubí, paja y oro, salmón,
  oro pálido con más chispa) y las uvas por su piel. Se elige en
  Caracool → Vinos y, página a página, en el widget.
- **El widget de Elementor gana la pestaña Estilo.** Colores de la casa
  enlazados de fábrica a los globales del Kit (Tinta → Secundario, Texto →
  Texto, Acento → Principal, y el icono del globo en cada uno para
  cambiarlos); colores del vino, que no son del Kit; letras (titulares y
  texto, del Kit si no se toca); y los colores de la noche. Ningún control
  estila nada: cada uno escribe un token del bloque, así que no hay pelea
  con el Estilo del tema, y el shortcode sigue funcionando sin Elementor.
- Todos los colores que iban escritos a mano en la hoja (tintes del globo
  al pasar, el velo del panel, la banda de la fila) salen ahora de los
  tokens con `color-mix()`, para que otro Kit o la noche los lleven bien.
- Shortcode: `[caracool_vinos escena="noche" red="no"]`.

## 0.3.2 · 22 de septiembre de 2026

- Mapa ↔ Lista, animado: el panel viaja de su sitio flotante a la columna
  centrada (medio segundo, con la curva de la casa) mientras el globo y el
  título se funden; al volver, lo mismo al revés. Va con las transiciones
  de vista del navegador: sin soporte, o con movimiento reducido, el cambio
  es instantáneo como antes. Solo se anima cuando lo pide el visitante, no
  al arrancar con el modo guardado.

## 0.3.1 · 19 de septiembre de 2026

- Con el mapa fijo, la rueda sobre la propia lista no la movía en la web:
  Lenis (el scroll suave del plugin de la cabecera) captura la rueda en
  toda la página y la aplica al scroll de la ventana, que ahí no existe. La
  escena fija lleva ahora `data-lenis-prevent`, Lenis la deja pasar y la
  lista se mueve con la rueda estés donde estés. En los topes de la lista,
  la rueda no hace nada (la página no se va).
- Al pasar por «Quitar todos», por el botón «Mis vinos» y por los demás
  botones, el Kit ponía el texto en blanco (su color de botón al pasar) y
  se perdían. Todas las reglas del bloque llevan ahora la clase de la raíz
  dos veces: pesan más que las del Kit también en :hover y :focus.
- Textos de «Mis vinos» sin explicar dónde se guardan: «Tus 3 favoritos de
  la carta. Para pedirlos hoy o para la próxima vez.» El aviso al guardar es
  «Guardado en Mis vinos».

## 0.3.0 · 19 de septiembre de 2026

- «Mis vinos» pasa a ser un sitio y no un chip más. Un botón fijo junto a
  Mapa / Lista, siempre a la vista, con el corazón (rojo cuando hay algo
  guardado) y la cuenta. Abre su propia vista: cabecera «Mis vinos» con
  cuántos hay y dónde se guardan, los vinos agrupados como en la carta con
  su precio, su zona y su sitio en bodega, y las acciones Compartir y
  Quitar todos. Los chips de tipo y los desplegables siguen funcionando
  dentro (mis tintos, mis vinos de Jumilla). Sin vinos guardados, la vista
  explica cómo se guarda uno.
- La primera vez que se guarda un vino, el aviso dice dónde queda y que se
  queda en ese navegador; el botón late para señalar el sitio. Al volver
  con vinos guardados, un aviso lo recuerda una vez por sesión.
- Fuera el chip «Solo · Mis vinos» de la fila de filtros activos: el botón
  ya enseña el estado y es la salida.

## 0.2.8 · 19 de septiembre de 2026

- La banda de la fila ya no lleva transición: al pasar rápido de un vino a
  otro, o al mover la lista con la rueda bajo el ratón, la fila anterior
  seguía encendida un instante y la banda parecía saltar arriba y volver.
  Ahora sigue al ratón al momento.

## 0.2.7 · 19 de septiembre de 2026

- Con el mapa en escritorio, la página ya no se desplaza y el pie de la
  web se apaga: la escena es la pantalla entera y la rueda, caiga sobre el
  globo, el título o los filtros, mueve la lista de vinos (o la ficha, si
  está abierta). En modo Lista el pie vuelve y la página se desplaza como
  siempre. En móvil no cambia nada.

## 0.2.6 · 19 de septiembre de 2026

- Con el mapa («El mundo», modo Mapa), la escena se queda fija llenando la
  pantalla: al bajar ya no sube y se mete bajo la cabecera, sino que el pie
  de la web sube por encima como un telón. Para eso la hoja pone el pie de
  Elementor (`.elementor-location-footer`) por delante de la escena. En
  móvil todo sigue apilado y desplazándose con normalidad.

## 0.2.5 · 19 de septiembre de 2026

- La cabecera de El Churra es transparente, y con los filtros clavados se
  veía la lista pasar por detrás del menú. Clavados, los filtros ponen un
  tapón del color del fondo hasta arriba del todo (`.filtros.clavado::before`,
  con el alto de la cabecera), así que detrás del menú solo queda fondo.

## 0.2.4 · 19 de septiembre de 2026

- Al bajar por la lista (modo Lista, y el globo en «Columnas»), lo que se
  clava se clava debajo de la cabecera fija de la página y no debajo de
  ella: el bloque mide la cabecera al arrancar (`--cv-cabecera`) y la
  vuelve a medir si cambia de alto. Sin cabecera fija, todo como antes.
- Fuera el interruptor de precios del visitante: los precios los decide la
  casa en Ajustes. Con él se va la cabecera de la lista (la cuenta de
  vinos ya la da el chip «Todos», que cambia con cada filtro) y el ajuste
  «Interruptor de precios».

## 0.2.3 · 18 de septiembre de 2026

- En «El mundo», con ratón, la barra de scroll de la lista ocupa sitio y
  el interruptor de precios quedaba 11 px a la derecha de su columna. La
  lista reserva siempre el hueco de la barra y la cabecera lo descuenta.

## 0.2.2 · 18 de septiembre de 2026

- El interruptor de precios pasa a una cabecera de la lista, encima de su
  columna, con la cuenta de vinos a la izquierda. El botón Mapa / Lista se
  va al final de la fila de los desplegables.
- Al pasar por un vino, y con su ficha abierta, se enciende la fila entera
  (del punto al corazón), con un pequeño sangrado a los lados. Antes el
  fondo se quedaba en el botón del vino y no llegaba al corazón.
- En «El mundo», el panel flotante lleva un marco de 10 px y cada pieza
  12 px por dentro, para que esa banda no la recorte el scroll de la lista.

## 0.2.1 · 18 de septiembre de 2026

- En la web, las filas de la lista no partían la línea y la lista salía
  con scroll horizontal: el reset del tema Hello pone `white-space:nowrap`
  a todos los `button`, y cada fila es un botón. El reset del bloque lo
  devuelve a normal.

## 0.2.0 · 18 de septiembre de 2026

- Dos diseños a elegir en Ajustes: **El mundo**, con el globo a pantalla
  completa haciendo de hero y la lista flotando a la derecha (el de
  serie), y **Columnas**, el de antes, debajo del hero de foto. Con «El
  mundo» el plugin pone `cv-diseno-mundo` en el body y apaga el hero de la
  página que lleve la clase `cv-hero-foto`.
- Botón **Mapa / Lista** para el visitante, junto al interruptor de
  precios: apaga el globo y deja la carta sola en una columna. Se recuerda
  en su navegador.
- Rótulo, título y entradilla de la página, editables en Ajustes (solo se
  pintan con «El mundo»).
- El markup del bloque ahora también sale del laboratorio
  (`modules/cv-bloque.php`), como la hoja y el script.
- El contenedor de Elementor donde va el widget tiene que ir a ancho
  completo y sin relleno; la escena se da su propio aire en «columnas».

## 0.1.2 · 18 de septiembre de 2026

- Al acercarse a una zona, el globo se salía de su marco y tapaba la
  página: una regla de la hoja dejaba los SVG del bloque sin recorte. El
  globo vuelve a recortar lo que se sale.

## 0.1.1 · 18 de septiembre de 2026

- El chip de Mágnum (y cualquier botón con `hidden`) se escondía en el
  laboratorio pero no en la web: el reset del tema Hello pone
  `display:inline-block` a todos los `button` y se come el atributo. La
  hoja del bloque vuelve a hacer que `hidden` signifique oculto.

## 0.1.0 · 18 de septiembre de 2026

Primera versión.

- El lector del Word de la carta de vinos, en el formato de septiembre de
  2026 (un vino por línea, precio en euros). Lee los 459 vinos de la carta
  del 9 de septiembre sin perder ninguno. Atlas de 130 zonas y tabla de
  uvas con nombre único.
- Importación en dos pasos: subir y revisar (nuevos, bajas, cambios de
  precio, avisos del lector) y publicar. La carta anterior se guarda.
- El explorador: globo con zoom a la zona, filtros por tipo, uva, bodega,
  zona y precio, búsqueda, ficha, «Mis vinos» y compartir, ubicación en
  bodega discreta. Shortcode `[caracool_vinos]` y widget de Elementor
  «Explorar los vinos». Colores y letras del Kit.
- Ajustes: precios e interruptor, ubicación, «Mis vinos», zonas que van
  delante, textos.
- Chasis de la casa: menú «Caracool» compartido y actualizador desde las
  releases de GitHub.
