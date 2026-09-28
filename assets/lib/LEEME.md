# Librerías del explorador

Van dentro del plugin, nunca de un CDN. Versiones y licencias, al lado:

- d3-array y d3-geo (ISC) — proyección ortográfica del globo, malla, interpolación de giros.
- topojson-client (ISC) — convierte la topología del mundo en polígonos.
- world-atlas 2.0.2 (ISC) — `assets/mundo-110m.json` es su `countries-110m.json`
  tal cual. `assets/finos-50m.json` son los países con vinos y sus vecinos
  sacados de `countries-50m.json`, con Francia recortada a la Francia
  europea. Los dos vienen de Natural Earth, que es de dominio público.

Se regeneran con `labs/vinos/a-plugin.py`.
