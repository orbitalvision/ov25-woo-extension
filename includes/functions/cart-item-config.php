<?php
/**
 * Configurator cart line parsing, labels, and cart-item data normalization.
 *
 * @package extension
 */

defined( 'ABSPATH' ) || exit;

/**
 * Remove trailing slash separators (for example " /") from display labels.
 *
 * @param mixed $value Raw label value.
 * @return string
 */
function ov25_trim_trailing_separator( $value ) {
	$label = trim( (string) $value );
	$label = preg_replace( '#\s*/+\s*$#', '', $label );
	return is_string( $label ) ? trim( $label ) : '';
}

/**
 * True when cfg_skumap JSON decodes to a numeric list of commerce line objects (multi-item configuration).
 *
 * @param mixed $decoded json_decode result.
 * @return bool
 */
function ov25_cfg_skumap_is_commerce_lines_list( $decoded ) {
	if ( ! is_array( $decoded ) || array() === $decoded ) {
		return false;
	}
	$keys     = array_keys( $decoded );
	$expected = range( 0, count( $decoded ) - 1 );
	if ( $keys !== $expected ) {
		return false;
	}
	$first = reset( $decoded );
	return is_array( $first ) && ( isset( $first['skuString'] ) || isset( $first['id'] ) );
}

/**
 * OV25 / cfg_* amounts are integer minor units (pence) end-to-end — no “float pounds” branch.
 *
 * @param mixed $raw Numeric string or number (pence).
 * @return int
 */
function ov25_parse_amount_to_minor_units( $raw ) {
	if ( null === $raw || '' === $raw ) {
		return 0;
	}
	if ( ! is_numeric( $raw ) ) {
		return 0;
	}
	$n = floatval( $raw );
	if ( $n <= 0 ) {
		return 0;
	}
	return (int) round( $n );
}

/**
 * Collapse Snap2 multi payload with a single commerce line into single-product cfg_* shape.
 *
 * @param array<string, string> $item Cart item data.
 * @return array<string, string>
 */
function ov25_maybe_normalize_snap2_cart_item_data( $item ) {
	if ( ! is_array( $item ) ) {
		return $item;
	}
	if ( ( $item['cfg_commerce_mode'] ?? '' ) !== 'multi' ) {
		return $item;
	}
	if ( empty( $item['cfg_sku'] ) || ! is_string( $item['cfg_sku'] ) ) {
		return $item;
	}
	$decoded = json_decode( $item['cfg_sku'], true );
	if ( ! is_array( $decoded ) || ! ov25_cfg_skumap_is_commerce_lines_list( $decoded ) || count( $decoded ) !== 1 ) {
		return $item;
	}
	$line = $decoded[0];
	if ( ! is_array( $line ) ) {
		return $item;
	}
	$sku_string = isset( $line['skuString'] ) ? (string) $line['skuString'] : '';
	$sku_map    = isset( $line['skuMap'] ) && is_array( $line['skuMap'] ) ? $line['skuMap'] : array();
	$item['cfg_sku']           = $sku_string;
	$item['cfg_skumap']        = wp_json_encode( $sku_map );
	$item['cfg_commerce_mode'] = 'single';
	return $item;
}

/**
 * Extract a human-readable PART label from cfg_skumap JSON.
 *
 * Supports both:
 * - flat skuMap objects (Product/Products keys)
 * - commerce line lists where each row has skuMap.Product(s)
 *
 * @param string $cfg_skumap_raw JSON string from cart item data.
 * @return string
 */
function ov25_part_label_from_cfg_skumap( $cfg_skumap_raw ) {
	if ( ! is_string( $cfg_skumap_raw ) || '' === $cfg_skumap_raw ) {
		return '';
	}

	$decoded = json_decode( $cfg_skumap_raw, true );
	if ( ! is_array( $decoded ) || array() === $decoded ) {
		return '';
	}

	$extract_from_map = static function ( array $map ) {
		foreach ( $map as $key => $value ) {
			if ( ! is_string( $key ) || ! is_scalar( $value ) ) {
				continue;
			}
			$lower = strtolower( $key );
			if ( 'product' !== $lower && 'products' !== $lower ) {
				continue;
			}
			$label = ov25_trim_trailing_separator( $value );
			if ( '' !== $label ) {
				return $label;
			}
		}
		return '';
	};

	if ( function_exists( 'ov25_cfg_skumap_is_commerce_lines_list' ) && ov25_cfg_skumap_is_commerce_lines_list( $decoded ) ) {
		$parts = array();
		foreach ( $decoded as $line ) {
			if ( ! is_array( $line ) || empty( $line['skuMap'] ) || ! is_array( $line['skuMap'] ) ) {
				continue;
			}
			$label = $extract_from_map( $line['skuMap'] );
			if ( '' !== $label ) {
				$parts[] = $label;
			}
		}
		$parts = array_values( array_unique( $parts ) );
		return implode( ' / ', $parts );
	}

	return $extract_from_map( $decoded );
}
