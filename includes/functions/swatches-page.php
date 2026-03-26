<?php
/**
 * Swatch product, Swatches page, plain-permalink redirect, cart restore after swatch checkout flow.
 *
 * @package extension
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ensure swatch product exists for cart operations.
 */
function ov25_ensure_swatch_product_exists() {
	$swatch_product_id = get_option( 'ov25_swatch_product_id' );

	if ( $swatch_product_id ) {
		$existing_product = wc_get_product( $swatch_product_id );
		if ( $existing_product ) {
			if ( $existing_product->get_status() !== 'publish' ) {
				$existing_product->set_status( 'publish' );
				$existing_product->set_stock_status( 'instock' );
				$existing_product->set_manage_stock( false );
				$existing_product->save();
			}
			return $swatch_product_id;
		}
	}

	$product = new WC_Product_Simple();
	$product->set_name( 'Swatch' );
	$product->set_status( 'publish' );
	$product->set_catalog_visibility( 'hidden' );
	$product->set_price( 0 );
	$product->set_regular_price( 0 );
	$product->set_stock_status( 'instock' );
	$product->set_manage_stock( false );
	$product->set_virtual( false );
	$product->set_meta_data( '_ov25_swatch_product', 'yes' );

	$product_id = $product->save();

	if ( $product_id ) {
		update_option( 'ov25_swatch_product_id', $product_id );
		return $product_id;
	}

	return false;
}

/**
 * Get the swatch product ID.
 *
 * @return string|false
 */
function ov25_get_swatch_product_id() {
	return get_option( 'ov25_swatch_product_id' );
}

/**
 * Ensure a Swatches page exists at the configured slug containing the shortcode.
 */
function ov25_ensure_swatches_page_exists() {
	try {
		$show_swatches_page = get_option( 'ov25_show_swatches_page', 'no' );
		$page_slug        = sanitize_title( get_option( 'ov25_swatches_page_slug', 'swatches' ) );
		$page_title        = sanitize_text_field( get_option( 'ov25_swatches_page_title', 'Swatches' ) );
		$show_in_nav        = get_option( 'ov25_swatches_show_in_nav', 'no' );
		$test_mode          = get_option( 'ov25_swatches_test_mode', 'no' );

		$page_id = (int) get_option( 'ov25_swatches_page_id', 0 );

		if ( $show_swatches_page === 'yes' ) {
			$needs_create = true;

			if ( $page_id > 0 ) {
				$page = get_post( $page_id );
				if ( $page && $page->post_status !== 'trash' ) {
					$current_visibility = $show_in_nav === 'yes' ? 'publish' : 'private';

					if ( $test_mode === 'yes' ) {
						if ( $page->post_name === $page_slug && $page->post_title === $page_title ) {
							$needs_create = false;
						} else {
							$update_data = array( 'ID' => $page_id );
							if ( $page->post_name !== $page_slug ) {
								$update_data['post_name'] = $page_slug;
							}
							if ( $page->post_title !== $page_title ) {
								$update_data['post_title'] = $page_title;
							}
							wp_update_post( $update_data );
							$needs_create = false;
						}
					} else {
						if ( $page->post_name === $page_slug && $page->post_title === $page_title && $page->post_status === $current_visibility ) {
							$needs_create = false;
						} else {
							$update_data = array( 'ID' => $page_id );
							if ( $page->post_name !== $page_slug ) {
								$update_data['post_name'] = $page_slug;
							}
							if ( $page->post_title !== $page_title ) {
								$update_data['post_title'] = $page_title;
							}
							if ( $page->post_status !== $current_visibility ) {
								$update_data['post_status'] = $current_visibility;
							}
							wp_update_post( $update_data );
							$needs_create = false;
						}
					}
				}
			}

			if ( $needs_create ) {
				$existing = get_page_by_path( $page_slug, OBJECT, 'page' );
				if ( $existing && $existing->post_status !== 'trash' ) {
					update_option( 'ov25_swatches_page_id', $existing->ID );
					return;
				}

				$page_status = $test_mode === 'yes' ? 'draft' : ( $show_in_nav === 'yes' ? 'publish' : 'private' );
				$page_id     = wp_insert_post(
					array(
						'post_title'   => $page_title,
						'post_name'    => $page_slug,
						'post_status'  => $page_status,
						'post_type'    => 'page',
						'post_content' => '[ov25_swatches]',
					)
				);

				if ( $page_id && ! is_wp_error( $page_id ) ) {
					update_option( 'ov25_swatches_page_id', $page_id );
				}
			}
		} else {
			if ( $page_id > 0 ) {
				$page = get_post( $page_id );
				if ( $page && $page->post_status !== 'trash' ) {
					wp_trash_post( $page_id );
				}
			}
		}
	} catch ( Exception $e ) {
		error_log( 'OV25 Woo Extension: Error ensuring Swatches page - ' . $e->getMessage() );
	}
}

/**
 * If permalinks are set to Plain (/?page_id=), redirect the swatches page URL to the page's canonical link.
 */
function ov25_swatches_plain_permalink_redirect() {
	if ( ! function_exists( 'get_option' ) ) {
		return;
	}

	$show_swatches_page = get_option( 'ov25_show_swatches_page', 'no' );
	if ( $show_swatches_page !== 'yes' ) {
		return;
	}

	$structure = get_option( 'permalink_structure' );
	if ( ! empty( $structure ) ) {
		return;
	}

	$page_slug = sanitize_title( get_option( 'ov25_swatches_page_slug', 'swatches' ) );

	$req_uri = isset( $_SERVER['REQUEST_URI'] ) ? strtok( wp_unslash( $_SERVER['REQUEST_URI'] ), '?' ) : '';
	if ( ! in_array( rtrim( $req_uri, '/' ), array( '/' . $page_slug ), true ) ) {
		return;
	}

	$page_id = (int) get_option( 'ov25_swatches_page_id', 0 );
	if ( $page_id <= 0 ) {
		return;
	}
	$link = get_permalink( $page_id );
	if ( ! $link ) {
		return;
	}

	wp_safe_redirect( $link, 301 );
	exit;
}

/**
 * If the session indicates we’re in a swatch-only flow and the user visits any
 * non-checkout/non-order-pay page, restore the original cart and clear flags.
 */
function ov25_maybe_restore_original_cart_on_navigation() {
	if ( ! WC()->session || ! WC()->session->get( 'ov25_swatch_only_cart' ) ) {
		return;
	}

	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		return;
	}
	if ( isset( $_GET['pay_for_order'] ) || isset( $_GET['order-pay'] ) ) {
		return;
	}

	$original_cart = WC()->session->get( 'ov25_original_cart' );
	WC()->cart->empty_cart();
	if ( is_array( $original_cart ) ) {
		foreach ( $original_cart as $item ) {
			$product_id     = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
			$quantity       = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
			$variation_id   = isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0;
			$variation      = isset( $item['variation'] ) && is_array( $item['variation'] ) ? $item['variation'] : array();
			$cart_item_data = ov25_extract_cart_item_custom_data( $item );
			WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation, $cart_item_data );
		}
	}
	WC()->session->set( 'ov25_swatch_only_cart', false );
	WC()->session->set( 'ov25_original_cart', null );
}

/**
 * Extract custom cart item data from a saved cart line for re-adding to cart.
 * Copies OV25-specific keys and any non-reserved custom keys.
 *
 * @param array<string, mixed> $item Saved cart line.
 * @return array<string, mixed>
 */
function ov25_extract_cart_item_custom_data( $item ) {
	if ( ! is_array( $item ) ) {
		return array();
	}

	$reserved_keys = array(
		'key',
		'product_id',
		'variation_id',
		'variation',
		'quantity',
		'data',
		'data_hash',
		'line_total',
		'line_tax',
		'line_subtotal',
		'line_subtotal_tax',
		'line_tax_data',
	);

	$cart_item_data = array();

	foreach ( $item as $key => $value ) {
		if ( strpos( $key, 'cfg_' ) === 0 || strpos( $key, 'swatch_' ) === 0 || strpos( $key, 'ov25-' ) === 0 ) {
			$cart_item_data[ $key ] = $value;
			continue;
		}
		if ( ! in_array( $key, $reserved_keys, true ) ) {
			$cart_item_data[ $key ] = $value;
		}
	}

	return $cart_item_data;
}
