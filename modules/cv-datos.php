<?php
/**
 * Caracool Vinos — Datos
 * ─────────────────────────────────────────────────────────────────────
 * Dónde vive la carta en esta web. Desde la 0.5.0 no se lee ningún Word
 * aquí: la carta llega ya leída de Bodega, recortada a lo que la página
 * necesita, y se guarda en una opción (cuatrocientos y pico vinos son unos
 * 400 KB de JSON, se leen de una vez y la página los pinta en el
 * navegador). Si Bodega no responde, se queda la última que llegó.
 *
 *   caracool_vinos_carta      la carta vigente, tal y como la mandó Bodega
 *   caracool_vinos_anterior   la de antes, por si hay que mirarla
 *
 * Cada carta trae su número de versión y su ETag: es lo que se le enseña a
 * Bodega para que conteste «304, la que tienes es la buena» sin mandar nada.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Caracool_Vinos_Datos {

	const OPCION_CARTA    = 'caracool_vinos_carta';
	const OPCION_ANTERIOR = 'caracool_vinos_anterior';

	// ── Leer ────────────────────────────────────────────────────────────

	public static function actual() {
		$c = get_option( self::OPCION_CARTA, null );
		return ( is_array( $c ) && isset( $c['vinos'] ) ) ? $c : null;
	}

	public static function anterior() {
		$c = get_option( self::OPCION_ANTERIOR, null );
		return ( is_array( $c ) && isset( $c['vinos'] ) ) ? $c : null;
	}

	/**
	 * Cómo se ve, si Caracool lo ha decidido en Bodega (la pestaña Diseño del
	 * cliente). Llega con la carta; null si Bodega todavía no ha dicho nada.
	 */
	public static function diseno() {
		$c = self::actual();
		if ( ! $c || empty( $c['diseno'] ) || ! is_array( $c['diseno'] ) ) {
			return null;
		}
		$d = $c['diseno'];
		return array(
			'mapa'     => ! empty( $d['mapa'] ),
			'red'      => ! empty( $d['red'] ),
			'diseno'   => ( isset( $d['diseno'] ) && 'columnas' === $d['diseno'] ) ? 'columnas' : 'mundo',
			'panel'    => ( isset( $d['panel'] ) && in_array( $d['panel'], array( 'mosaico', 'pildoras', 'ficha' ), true ) ) ? $d['panel'] : 'mosaico',
			'esquinas' => ( isset( $d['esquinas'] ) && 'diseno' === $d['esquinas'] ) ? 'diseno' : 'web',
		);
	}

	public static function etag() {
		$c = self::actual();
		return $c && ! empty( $c['etag'] ) ? $c['etag'] : '';
	}

	// ── Escribir ────────────────────────────────────────────────────────

	/**
	 * Guarda la carta que ha mandado Bodega. Si es otra versión que la
	 * vigente, la vigente pasa a anterior. Devuelve true si había algo nuevo.
	 */
	public static function recibir( $carta, $etag ) {
		if ( ! is_array( $carta ) || ! isset( $carta['vinos'] ) ) {
			return false;
		}
		$vieja = self::actual();
		$carta['etag']     = $etag;
		$carta['recibida'] = time();
		if ( $vieja && (int) $vieja['version'] === (int) $carta['version'] ) {
			update_option( self::OPCION_CARTA, $carta, false );
			return false;
		}
		if ( $vieja ) {
			update_option( self::OPCION_ANTERIOR, $vieja, false );
		}
		update_option( self::OPCION_CARTA, $carta, false );
		do_action( 'caracool_vinos_publicada', $carta );
		return true;
	}

	/** Cuando Bodega dice que la licencia está revocada: la web se queda sin carta. */
	public static function vaciar() {
		$vieja = self::actual();
		if ( $vieja ) {
			update_option( self::OPCION_ANTERIOR, $vieja, false );
		}
		delete_option( self::OPCION_CARTA );
	}

	// ── Para la web ─────────────────────────────────────────────────────

	/** La carta vigente, que ya viene recortada de Bodega. */
	public static function para_web() {
		$c = self::actual();
		if ( ! $c ) {
			return null;
		}
		return array(
			'fecha'       => $c['fecha'],
			'fecha_texto' => isset( $c['fecha_texto'] ) ? $c['fecha_texto'] : self::fecha_legible( $c['fecha'] ),
			'publicada'   => isset( $c['publicada'] ) ? (int) $c['publicada'] : 0,
			'vinos'       => $c['vinos'],
			'nuevos'      => isset( $c['nuevos'] ) ? array_values( $c['nuevos'] ) : array(),
			'pdf'         => isset( $c['pdf'] ) ? $c['pdf'] : '',
		);
	}

	// ── Utilidades ──────────────────────────────────────────────────────

	/** «2026-09-09» → «9 de septiembre de 2026». */
	public static function fecha_legible( $iso ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $iso, $m ) ) {
			return (string) $iso;
		}
		$meses = array( '', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre' );
		return (int) $m[3] . ' de ' . $meses[ (int) $m[2] ] . ' de ' . $m[1];
	}

	/** Cuenta por un campo: array( valor => n ), de más a menos. */
	public static function contar( $vinos, $campo ) {
		$c = array();
		foreach ( $vinos as $v ) {
			$k = isset( $v[ $campo ] ) && '' !== $v[ $campo ] ? $v[ $campo ] : '(sin)';
			if ( is_array( $k ) ) {
				continue;
			}
			$c[ $k ] = isset( $c[ $k ] ) ? $c[ $k ] + 1 : 1;
		}
		arsort( $c );
		return $c;
	}
}
