<?php
/**
 * Small PHP renderer for the Hugeicons Core Free SVG set.
 *
 * The bundled SVG path data is from @hugeicons/core-free-icons 4.3.5.
 * See assets/icons/LICENSE-Hugeicons-MIT.txt.
 *
 * @package DixcoverHub\CustomUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DixcoverHub_Custom_UI_Icons {
	/** @var array|null Cached SVG icon data. */
	private static $icon_data = null;

	/** Render one bundled Hugeicons icon as a safe inline SVG. */
	public static function svg( $name, $class = '' ) {
		$icons = self::icons();
		if ( ! isset( $icons[ $name ] ) || ! is_array( $icons[ $name ] ) ) {
			return '';
		}

		$classes = preg_split( '/\s+/', (string) $class );
		$classes = array_filter( array_map( 'sanitize_html_class', (array) $classes ) );
		$class   = implode( ' ', array_unique( $classes ) );
		$svg   = '<svg class="' . esc_attr( $class ) . '" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">';
		$allowed_tags = array( 'path', 'circle', 'rect', 'line', 'ellipse', 'polyline', 'polygon' );
		$allowed_attrs = array( 'd', 'cx', 'cy', 'r', 'x', 'y', 'x1', 'x2', 'y1', 'y2', 'width', 'height', 'rx', 'ry', 'points', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-dasharray', 'stroke-dashoffset', 'fill', 'fill-rule', 'clip-rule', 'transform', 'opacity' );

		foreach ( $icons[ $name ] as $element ) {
			if ( ! isset( $element['tag'], $element['attrs'] ) || ! in_array( $element['tag'], $allowed_tags, true ) || ! is_array( $element['attrs'] ) ) {
				continue;
			}
			$attributes = '';
			foreach ( $element['attrs'] as $attribute => $value ) {
				if ( ! in_array( $attribute, $allowed_attrs, true ) || ! is_scalar( $value ) ) {
					continue;
				}
				$attributes .= ' ' . $attribute . '="' . esc_attr( (string) $value ) . '"';
			}
			$svg .= '<' . $element['tag'] . $attributes . ' />';
		}

		return $svg . '</svg>';
	}

	/** Read the curated data bundle once per request. */
	private static function icons() {
		if ( null === self::$icon_data ) {
			$file = dirname( __DIR__ ) . '/assets/icons/hugeicons-free.json';
			$json = is_readable( $file ) ? file_get_contents( $file ) : false;
			$data = $json ? json_decode( $json, true ) : null;
			self::$icon_data = is_array( $data ) ? $data : array();
		}
		return self::$icon_data;
	}
}
