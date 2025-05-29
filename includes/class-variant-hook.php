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

		/* Classic template – add placeholder before the form (for variable products) */
		add_action(
			'woocommerce_before_add_to_cart_form',
			[ __CLASS__, 'render_placeholder_variable' ]
		);

		/* Classic template – add placeholder after price (for simple products) */
		add_action(
			'woocommerce_single_product_summary',
			[ __CLASS__, 'render_placeholder_simple' ],
			15  // After price (which is at priority 10)
		);

		/* Classic template – hide the variations form */
		add_action(
			'woocommerce_before_add_to_cart_button',
			[ __CLASS__, 'hide_variations_form' ]
		);

		/* Block template – replace the entire variation‑form block */
		add_filter(
			'render_block_woocommerce/product-variation-form',
			[ __CLASS__, 'render_block_placeholder' ],
			10,
			2
		);

		/* Block template – add placeholder before add to cart button */
		add_filter(
			'render_block_woocommerce/product-button',
			[ __CLASS__, 'render_block_add_to_cart_placeholder' ],
			10,
			2
		);
	}

	/* ---------- Classic ------------------------------------------------ */
	public static function render_placeholder_variable() {
		$product = wc_get_product();
		if ( ! is_product() || ! $product->get_meta( '_ov25_product_id', true ) ) {
			return;          // keep Woo UI for non‑configurator products
		}

		// Only show for variable products
		if ( ! $product->is_type( 'variable' ) ) {
			return;
		}

		// Only our placeholder; no default dropdown rendered.
		echo '<div data-ov25-variants></div>';
	}

	public static function render_placeholder_simple() {
		$product = wc_get_product();
		if ( ! is_product() || ! $product->get_meta( '_ov25_product_id', true ) ) {
			return;          // keep Woo UI for non‑configurator products
		}

		// Only show for simple products
		if ( ! $product->is_type( 'simple' ) ) {
			return;
		}

		// Only our placeholder; no default dropdown rendered.
		echo '<div data-ov25-variants></div>';
	}

	public static function hide_variations_form() {
		$product = wc_get_product();
		if ( ! is_product() || ! $product->get_meta( '_ov25_product_id', true ) ) {
			return;          // keep Woo UI for non‑configurator products
		}

		/* Hide any native variation UI that might slip through */
		echo '<style>.variations_form{display:none!important}</style>';
	}

	/* ---------- Block -------------------------------------------------- */
	public static function render_block_placeholder( $html, $block ) {
		$product = wc_get_product();
		if ( ! is_product() || ! $product->get_meta( '_ov25_product_id', true ) ) {
			return $html;          // keep Woo UI for non‑configurator products
		}

		// Only our placeholder; no default dropdown rendered.
		return '<div data-ov25-variants></div>';
	}

	public static function render_block_add_to_cart_placeholder( $html, $block ) {
		$product = wc_get_product();
		if ( ! is_product() || ! $product->get_meta( '_ov25_product_id', true ) ) {
			return $html;          // keep Woo UI for non‑configurator products
		}

		// Add our placeholder before the add to cart button
		return '<div data-ov25-variants></div>' . $html;
	}
}
