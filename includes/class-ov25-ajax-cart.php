<?php
/**
 * OV25: AJAX add to cart for configured products (WC()->cart, admin-ajax).
 *
 * @package Ov25WooExtension
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and handles ov25_add_to_cart for logged-in and guest users.
 */
class OV25_Ajax_Cart {

	/**
	 * Cart item meta keys accepted from JSON (matches woocommerce_add_cart_item_data POST keys).
	 */
	const ALLOWED_CART_ITEM_KEYS = array(
		'cfg_price',
		'cfg_sku',
		'cfg_skumap',
		'cfg_commerce_mode',
		'cfg_payload',
		'ov25-thumbnail',
	);

	/**
	 * Hook admin-ajax actions.
	 */
	public static function init() {
		add_action( 'wp_ajax_ov25_add_to_cart', array( __CLASS__, 'handle_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_ov25_add_to_cart', array( __CLASS__, 'handle_add_to_cart' ) );
	}

	/**
	 * Parse JSON body, validate nonce, add line to cart, return redirect URL.
	 */
	public static function handle_add_to_cart() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce cart is unavailable.', 'ov25-woo-extension' ) ) );
		}

		$raw = file_get_contents( 'php://input' );
		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request body.', 'ov25-woo-extension' ) ) );
		}

		$nonce = isset( $data['nonce'] ) ? sanitize_text_field( wp_unslash( $data['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ov25_add_to_cart' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'ov25-woo-extension' ) ) );
		}

		$product_id = isset( $data['product_id'] ) ? absint( $data['product_id'] ) : 0;
		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing product ID.', 'ov25-woo-extension' ) ) );
		}

		$quantity = isset( $data['quantity'] ) ? absint( $data['quantity'] ) : 1;
		if ( $quantity < 1 ) {
			$quantity = 1;
		}

		$variation_id = isset( $data['variation_id'] ) ? absint( $data['variation_id'] ) : 0;
		$variation    = self::sanitize_variation_array( $data['variation'] ?? null );

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'ov25-woo-extension' ) ) );
		}
		if ( ! $product->is_purchasable() ) {
			wp_send_json_error( array( 'message' => __( 'This product cannot be purchased.', 'ov25-woo-extension' ) ) );
		}

		// Variable products require a variation; OV25 often hides the native form so none is posted.
		if ( $product->is_type( 'variable' ) ) {
			$resolved = self::resolve_variable_add_to_cart_args( $product, $variation_id, $variation );
			if ( is_wp_error( $resolved ) ) {
				wp_send_json_error( array( 'message' => $resolved->get_error_message() ) );
			}
			$variation_id = $resolved['variation_id'];
			$variation    = $resolved['variation'];
		} else {
			$variation_id = 0;
			$variation    = array();
		}

		$cart_item_data = array();
		foreach ( self::ALLOWED_CART_ITEM_KEYS as $key ) {
			if ( ! isset( $data[ $key ] ) || '' === $data[ $key ] ) {
				continue;
			}
			$value = $data[ $key ];
			if ( is_int( $value ) || is_float( $value ) ) {
				$value = (string) $value;
			}
			if ( is_string( $value ) ) {
				$cart_item_data[ $key ] = wc_clean( wp_unslash( $value ) );
			}
		}

		if ( function_exists( 'ov25_maybe_normalize_snap2_cart_item_data' ) ) {
			$cart_item_data = ov25_maybe_normalize_snap2_cart_item_data( $cart_item_data );
		}

		$is_buy_now = ! empty( $data['ov25_redirect_checkout'] );

		$passed_validation = apply_filters(
			'woocommerce_add_to_cart_validation',
			true,
			$product_id,
			$quantity,
			$variation_id,
			$variation,
			$cart_item_data
		);

		if ( ! $passed_validation ) {
			$notices = function_exists( 'wc_get_notices' ) ? wc_get_notices( 'error' ) : array();
			if ( function_exists( 'wc_clear_notices' ) ) {
				wc_clear_notices();
			}
			$message = '';
			if ( ! empty( $notices[0]['notice'] ) ) {
				$message = wp_strip_all_tags( $notices[0]['notice'] );
			}
			if ( '' === $message ) {
				$message = __( 'This product could not be added to the cart.', 'ov25-woo-extension' );
			}
			wp_send_json_error( array( 'message' => $message ) );
		}

		if ( function_exists( 'wc_clear_notices' ) ) {
			wc_clear_notices();
		}

		$commerce_lines = self::decode_commerce_lines_list( $cart_item_data['cfg_sku'] ?? '' );
		if (
			( $cart_item_data['cfg_commerce_mode'] ?? '' ) === 'multi'
			&& is_array( $commerce_lines )
			&& count( $commerce_lines ) > 1
		) {
			self::finish_multi_line_add_to_cart(
				$product_id,
				$quantity,
				$variation_id,
				$variation,
				$cart_item_data,
				$commerce_lines,
				$is_buy_now
			);
			return;
		}

		$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation, $cart_item_data );

		if ( ! $cart_item_key ) {
			$message = __( 'Failed to add item to cart.', 'ov25-woo-extension' );
			$notices = function_exists( 'wc_get_notices' ) ? wc_get_notices( 'error' ) : array();
			if ( function_exists( 'wc_clear_notices' ) ) {
				wc_clear_notices();
			}
			if ( ! empty( $notices[0]['notice'] ) ) {
				$message = wp_strip_all_tags( $notices[0]['notice'] );
			}
			wp_send_json_error( array( 'message' => $message ) );
		}

		$response = array();
		if ( $is_buy_now ) {
			$response['redirect_url'] = wc_get_checkout_url();
		}

		wp_send_json_success( $response );
	}

	/**
	 * json_decode cfg_sku when it is a numeric list of Snap2 / multi-commerce line objects.
	 *
	 * @param string $cfg_sku JSON array string.
	 * @return array<int, array<string, mixed>>|null
	 */
	private static function decode_commerce_lines_list( $cfg_sku ) {
		if ( ! is_string( $cfg_sku ) || '' === $cfg_sku ) {
			return null;
		}
		$decoded = json_decode( $cfg_sku, true );
		if ( ! is_array( $decoded ) || array() === $decoded ) {
			return null;
		}
		if ( function_exists( 'ov25_cfg_skumap_is_commerce_lines_list' ) && ov25_cfg_skumap_is_commerce_lines_list( $decoded ) ) {
			return $decoded;
		}
		return null;
	}

	/**
	 * Line total in pence from one `cfg_payload.price.lines[]` row (Snap2: `price` = full line; `subtotal` = per unit).
	 *
	 * @param array<string, mixed> $pl Price line row.
	 * @return int
	 */
	private static function price_line_row_total_pence( array $pl ) {
		if ( ! function_exists( 'ov25_parse_amount_to_minor_units' ) ) {
			return 0;
		}
		$line_total = isset( $pl['price'] ) ? ov25_parse_amount_to_minor_units( $pl['price'] ) : 0;
		if ( $line_total > 0 ) {
			return $line_total;
		}
		$sub = isset( $pl['subtotal'] ) ? ov25_parse_amount_to_minor_units( $pl['subtotal'] ) : 0;
		$q   = isset( $pl['quantity'] ) ? max( 1, (int) $pl['quantity'] ) : 1;
		if ( $sub <= 0 ) {
			return 0;
		}
		return (int) ( $sub * $q );
	}

	/**
	 * If the sum of per-line pence almost matches `cfg_price` (request grand total), nudge the last line for rounding only.
	 * Does not re-scale proportions (that destroyed per-line Snap2 prices when subtotal was misused as line total).
	 *
	 * @param array<int, int> $parts Line totals in pence.
	 * @param int               $cfg_total_minor Request `cfg_price` in pence.
	 * @return array<int, int>
	 */
	private static function reconcile_line_pence_to_cfg_total( array $parts, $cfg_total_minor ) {
		$n = count( $parts );
		if ( $n < 1 ) {
			return $parts;
		}
		$sum   = array_sum( $parts );
		$drift = (int) $cfg_total_minor - $sum;
		$tol   = max( 2, (int) round( 0.005 * max( (int) $cfg_total_minor, $sum ) ) );
		if ( 0 !== $drift && abs( $drift ) <= $tol ) {
			$parts[ $n - 1 ] = max( 0, (int) $parts[ $n - 1 ] + $drift );
		}
		return $parts;
	}

	/**
	 * Per-line line-total pence from `cfg_payload` for each commerce row. Falls back to splitting `cfg_price` only if payload lacks amounts.
	 *
	 * @param array<int, array<string, mixed>> $commerce_lines Decoded cfg_sku lines.
	 * @param array<string, string>            $cart_item_data Base cart item meta from the request.
	 * @return array<int, int> Line-total pence per cart row (before dividing by inner qty for unit cfg_price).
	 */
	private static function allocate_line_prices_minor( array $commerce_lines, array $cart_item_data ) {
		$n   = count( $commerce_lines );
		$out = array_fill( 0, $n, 0 );
		if ( $n < 1 || ! function_exists( 'ov25_parse_amount_to_minor_units' ) ) {
			return $out;
		}
		$total = ov25_parse_amount_to_minor_units( $cart_item_data['cfg_price'] ?? 0 );
		if ( $total <= 0 ) {
			return $out;
		}

		$payload = array();
		$raw_payload = $cart_item_data['cfg_payload'] ?? '';
		if ( is_string( $raw_payload ) && '' !== $raw_payload ) {
			$decoded_payload = json_decode( $raw_payload, true );
			if ( is_array( $decoded_payload ) ) {
				$payload = $decoded_payload;
			}
		}
		$price_lines = array();
		if ( isset( $payload['price']['lines'] ) && is_array( $payload['price']['lines'] ) ) {
			$price_lines = $payload['price']['lines'];
		}

		$try_parts = function ( array $parts ) use ( $n, $total ) {
			if ( count( $parts ) !== $n || array_sum( $parts ) <= 0 ) {
				return null;
			}
			return self::reconcile_line_pence_to_cfg_total( $parts, $total );
		};

		if ( count( $price_lines ) === $n ) {
			$parts = array();
			foreach ( $price_lines as $pl ) {
				if ( ! is_array( $pl ) ) {
					$parts = array();
					break;
				}
				$parts[] = self::price_line_row_total_pence( $pl );
			}
			$ok = $try_parts( $parts );
			if ( is_array( $ok ) ) {
				return $ok;
			}
		}

		$by_id = array();
		foreach ( $price_lines as $pl ) {
			if ( ! is_array( $pl ) || empty( $pl['id'] ) ) {
				continue;
			}
			$pid             = (string) $pl['id'];
			$by_id[ $pid ] = self::price_line_row_total_pence( $pl );
		}
		$parts = array();
		foreach ( $commerce_lines as $line ) {
			if ( ! is_array( $line ) ) {
				$parts = array();
				break;
			}
			$lid = isset( $line['id'] ) ? (string) $line['id'] : '';
			if ( '' === $lid || ! isset( $by_id[ $lid ] ) ) {
				$parts = array();
				break;
			}
			$parts[] = $by_id[ $lid ];
		}
		if ( array() !== $parts && count( $parts ) === $n ) {
			$ok = $try_parts( $parts );
			if ( is_array( $ok ) ) {
				return $ok;
			}
		}

		$base = (int) floor( $total / $n );
		$rem  = $total - $base * $n;
		for ( $i = 0; $i < $n; $i++ ) {
			$out[ $i ] = $base + ( $i < $rem ? 1 : 0 );
		}
		return $out;
	}

	/**
	 * Adds one cart row per multi-commerce line; rolls back on failure.
	 *
	 * @param array<int, array<string, mixed>> $commerce_lines Decoded cfg_sku lines.
	 */
	private static function finish_multi_line_add_to_cart(
		$product_id,
		$quantity,
		$variation_id,
		array $variation,
		array $cart_item_data,
		array $commerce_lines,
		$is_buy_now
	) {
		$line_prices = self::allocate_line_prices_minor( $commerce_lines, $cart_item_data );
		$bundle_id   = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'ov25-', true );
		$added_keys  = array();

		foreach ( $commerce_lines as $i => $line ) {
			if ( ! is_array( $line ) ) {
				continue;
			}
			$sku_string = isset( $line['skuString'] ) ? (string) $line['skuString'] : '';
			$sku_map    = array();
			if ( isset( $line['skuMap'] ) && is_array( $line['skuMap'] ) ) {
				$sku_map = $line['skuMap'];
			}
			$inner_qty = isset( $line['quantity'] ) ? max( 1, (int) $line['quantity'] ) : 1;
			$line_qty  = $inner_qty * max( 1, (int) $quantity );

			$line_cart = $cart_item_data;
			unset( $line_cart['cfg_commerce_mode'] );
			$line_cart['cfg_commerce_mode'] = 'single';
			$line_cart['cfg_sku']           = $sku_string;
			$line_cart['cfg_skumap']        = wp_json_encode( $sku_map );
			if ( isset( $line_prices[ $i ] ) && (int) $line_prices[ $i ] > 0 ) {
				$line_total_minor = (int) $line_prices[ $i ];
				$per_unit_minor   = max( 1, (int) round( $line_total_minor / $inner_qty ) );
				$line_cart['cfg_price'] = (string) $per_unit_minor;
			}
			$line_cart['ov25_multi_line_group'] = $bundle_id;

			if ( function_exists( 'wc_clear_notices' ) ) {
				wc_clear_notices();
			}

			$passed = apply_filters(
				'woocommerce_add_to_cart_validation',
				true,
				$product_id,
				$line_qty,
				$variation_id,
				$variation,
				$line_cart
			);

			if ( ! $passed ) {
				foreach ( $added_keys as $key ) {
					WC()->cart->remove_cart_item( $key );
				}
				$notices = function_exists( 'wc_get_notices' ) ? wc_get_notices( 'error' ) : array();
				if ( function_exists( 'wc_clear_notices' ) ) {
					wc_clear_notices();
				}
				$message = __( 'This product could not be added to the cart.', 'ov25-woo-extension' );
				if ( ! empty( $notices[0]['notice'] ) ) {
					$message = wp_strip_all_tags( $notices[0]['notice'] );
				}
				wp_send_json_error( array( 'message' => $message ) );
			}

			if ( function_exists( 'wc_clear_notices' ) ) {
				wc_clear_notices();
			}

			$key = WC()->cart->add_to_cart( $product_id, $line_qty, $variation_id, $variation, $line_cart );
			if ( ! $key ) {
				foreach ( $added_keys as $ak ) {
					WC()->cart->remove_cart_item( $ak );
				}
				$message = __( 'Failed to add item to cart.', 'ov25-woo-extension' );
				$notices = function_exists( 'wc_get_notices' ) ? wc_get_notices( 'error' ) : array();
				if ( function_exists( 'wc_clear_notices' ) ) {
					wc_clear_notices();
				}
				if ( ! empty( $notices[0]['notice'] ) ) {
					$message = wp_strip_all_tags( $notices[0]['notice'] );
				}
				wp_send_json_error( array( 'message' => $message ) );
			}
			$added_keys[] = $key;
		}

		$response = array();
		if ( $is_buy_now ) {
			$response['redirect_url'] = wc_get_checkout_url();
		}

		wp_send_json_success( $response );
	}

	/**
	 * When the client omits variation data, pick the first purchasable variation so WC()->cart->add_to_cart succeeds.
	 *
	 * @param WC_Product $product Variable product.
	 * @param int         $variation_id Requested variation id (0 = auto).
	 * @param array       $variation Requested attribute map.
	 * @return array{variation_id: int, variation: array<string, string>}|WP_Error
	 */
	private static function resolve_variable_add_to_cart_args( $product, $variation_id, array $variation ) {
		if ( $variation_id > 0 ) {
			$var_product = wc_get_product( $variation_id );
			if ( ! $var_product || ! $var_product->is_type( 'variation' ) ) {
				return new WP_Error( 'ov25_variation', __( 'Invalid variation.', 'ov25-woo-extension' ) );
			}
			if ( (int) $var_product->get_parent_id() !== (int) $product->get_id() ) {
				return new WP_Error( 'ov25_variation', __( 'Variation does not match this product.', 'ov25-woo-extension' ) );
			}
			if ( empty( $variation ) ) {
				$variation = self::variation_attributes_from_variation_product( $var_product );
			}
			return array(
				'variation_id' => $variation_id,
				'variation'    => $variation,
			);
		}

		if ( ! empty( $variation ) ) {
			$data_store = WC_Data_Store::load( 'product' );
			if ( is_callable( array( $data_store, 'find_matching_product_variation' ) ) ) {
				$found = $data_store->find_matching_product_variation( $product, $variation );
				if ( $found ) {
					$matched = wc_get_product( (int) $found );
					$attrs   = $matched ? self::variation_attributes_from_variation_product( $matched ) : $variation;
					return array(
						'variation_id' => (int) $found,
						'variation'    => $attrs,
					);
				}
			}
		}

		$fallback_id = self::find_first_purchasable_variation_id( $product );
		if ( ! $fallback_id ) {
			return new WP_Error(
				'ov25_no_variation',
				__( 'No matching product variation is available for the cart.', 'ov25-woo-extension' )
			);
		}

		$fallback_product = wc_get_product( $fallback_id );
		if ( ! $fallback_product || ! $fallback_product->is_type( 'variation' ) ) {
			return new WP_Error( 'ov25_variation', __( 'Could not load a product variation.', 'ov25-woo-extension' ) );
		}

		return array(
			'variation_id' => $fallback_id,
			'variation'    => self::variation_attributes_from_variation_product( $fallback_product ),
		);
	}

	/**
	 * Attribute keys must match WC_Product_Variation::get_variation_attributes() (e.g. attribute_pa_color).
	 *
	 * @param WC_Product_Variation $variation_product Variation product.
	 * @return array<string, string>
	 */
	private static function variation_attributes_from_variation_product( $variation_product ) {
		$out = array();
		if ( ! $variation_product || ! $variation_product->is_type( 'variation' ) ) {
			return $out;
		}
		foreach ( $variation_product->get_variation_attributes() as $attr_name => $attr_value ) {
			if ( '' === $attr_value && '0' !== $attr_value ) {
				continue;
			}
			$out[ $attr_name ] = $attr_value;
		}
		return $out;
	}

	/**
	 * @param WC_Product $product Variable product.
	 * @return int
	 */
	private static function find_first_purchasable_variation_id( $product ) {
		if ( ! $product->is_type( 'variable' ) ) {
			return 0;
		}
		$ids = $product->get_children();
		if ( ! is_array( $ids ) ) {
			return 0;
		}
		foreach ( $ids as $child_id ) {
			$child = wc_get_product( (int) $child_id );
			if ( ! $child || ! $child->is_type( 'variation' ) ) {
				continue;
			}
			if ( $child->is_purchasable() && $child->is_in_stock() ) {
				return (int) $child_id;
			}
		}
		foreach ( $ids as $child_id ) {
			$child = wc_get_product( (int) $child_id );
			if ( $child && $child->is_type( 'variation' ) && $child->is_purchasable() ) {
				return (int) $child_id;
			}
		}
		return 0;
	}

	/**
	 * @param mixed $raw Variation attributes from JSON (attribute_* => value).
	 * @return array<string, string>
	 */
	private static function sanitize_variation_array( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $attr_key => $attr_val ) {
			if ( is_array( $attr_val ) || is_object( $attr_val ) ) {
				continue;
			}
			$k = wc_clean( wp_unslash( (string) $attr_key ) );
			if ( '' === $k ) {
				continue;
			}
			$out[ $k ] = wc_clean( wp_unslash( (string) $attr_val ) );
		}
		return $out;
	}
}
