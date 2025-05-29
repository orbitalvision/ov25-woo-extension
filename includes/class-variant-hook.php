<?php
/**
 * OV25 Variant Hook
 *  – prints <div data-ov25-variants></div> inside the Add‑to‑Cart form
 *  – hides Woo's native variation controls for configurator products
 */

defined( 'ABSPATH' ) || exit;

class OV25_Variant_Hook {

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

		add_action(
			'woocommerce_before_add_to_cart_button',
			[ __CLASS__, 'hide_variations_form' ]
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

		echo '<div data-ov25-variants></div>';
	}

	public static function render_placeholder_simple() {
		$product = wc_get_product();
		if ( ! is_product() || ! $product->get_meta( '_ov25_product_id', true ) ) {
			return;
		}

		if ( ! $product->is_type( 'simple' ) ) {
			return;
		}

		echo '<div data-ov25-variants></div>';
	}

	public static function hide_variations_form() {
		$product = wc_get_product();
		if ( ! is_product() || ! $product->get_meta( '_ov25_product_id', true ) ) {
			return;
		}

		echo '<style>.variations_form{display:none!important}</style>';
	}

	public static function render_block_placeholder( $html, $block ) {
		$product = wc_get_product();
		if ( ! is_product() || ! $product->get_meta( '_ov25_product_id', true ) ) {
			return $html;
		}

		return '<div data-ov25-variants></div>';
	}

	public static function render_block_add_to_cart_placeholder( $html, $block ) {
		$product = wc_get_product();
		if ( ! is_product() || ! $product->get_meta( '_ov25_product_id', true ) ) {
			return $html;
		}

		return '<div data-ov25-variants></div>' . $html;
	}
}
