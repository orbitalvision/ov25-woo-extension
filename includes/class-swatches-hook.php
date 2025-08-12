<?php
/**
 * OV25 Swatches Hook
 *  – prints <div data-ov25-swatches></div> inside the Add‑to‑Cart form
 */

defined( 'ABSPATH' ) || exit;

class OV25_Swatches_Hook {

	public static function init() {
		if ( is_admin() ) {
			return;
		}

		add_action(
			'woocommerce_before_add_to_cart_form',
			[ __CLASS__, 'render_placeholder_variable' ]
		);

		add_action(
			'woocommerce_single_product_summary',
			[ __CLASS__, 'render_placeholder_simple' ],
			15
		);

		add_filter(
			'render_block_woocommerce/product-variation-form',
			[ __CLASS__, 'render_block_placeholder' ],
			10,
			2
		);

		add_filter(
			'render_block_woocommerce/product-button',
			[ __CLASS__, 'render_block_add_to_cart_placeholder' ],
			10,
			2
		);
	}

	public static function render_placeholder_variable() {
		$product = wc_get_product();
		if ( ! is_product() || ! $product->get_meta( '_ov25_product_id', true ) ) {
			return;
		}

		if ( ! $product->is_type( 'variable' ) ) {
			return;
		}

		echo '<div data-ov25-swatches></div>';
	}

	public static function render_placeholder_simple() {
		$product = wc_get_product();
		if ( ! is_product() || ! $product->get_meta( '_ov25_product_id', true ) ) {
			return;
		}

		if ( ! $product->is_type( 'simple' ) ) {
			return;
		}

		echo '<div data-ov25-swatches></div>';
	}

	public static function render_block_placeholder( $html, $block ) {
		$product = wc_get_product();
		if ( ! is_product() || ! $product->get_meta( '_ov25_product_id', true ) ) {
			return $html;
		}

		return '<div data-ov25-swatches></div>';
	}

	public static function render_block_add_to_cart_placeholder( $html, $block ) {
		$product = wc_get_product();
		if ( ! is_product() || ! $product->get_meta( '_ov25_product_id', true ) ) {
			return $html;
		}

		return '<div data-ov25-swatches></div>' . $html;
	}
}
