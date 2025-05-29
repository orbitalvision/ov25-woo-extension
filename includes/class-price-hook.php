<?php
/**
 * OV25 Price Hook
 *
 * @package Ov25WooExtension
 */

defined( 'ABSPATH' ) || exit;

/**
 * Replaces the printed price with a skeleton that carries data‑ov25‑price.
 */
class OV25_Price_Hook {

	public static function init() {
		if ( is_admin() ) {
			return;
		}

		/* Classic template ------------------------------------------- */
		add_filter(
			'woocommerce_get_price_html',
			[ __CLASS__, 'replace_price_with_skeleton' ],
			10,
			2   // ($price_html, $product)
		);

		/* Block template --------------------------------------------- */
		add_filter(
			'render_block_woocommerce/product-price',
			[ __CLASS__, 'replace_block_price_with_skeleton' ],
			10,
			2   // ($html, $block)
		);
	}

	/* === Classic template ========================================= */

	public static function replace_price_with_skeleton( $price_html, $product ) {
		return self::should_replace() ? self::skeleton_markup() : $price_html;
	}

	/* === Block template =========================================== */

	public static function replace_block_price_with_skeleton( $html, $block ) {
		return self::should_replace() ? self::skeleton_markup() : $html;
	}

	/* -------------------------------------------------------------- */

	/**
	 * Decide if we're on the single‑product page with an OV25 product.
	 */
	private static function should_replace() {
		if ( ! is_product() ) {
			return false;
		}

		$product = wc_get_product();
		if ( ! $product ) {
			return false;
		}

		// Only replace price for products with OV25 Product ID
		$ov25_product_id = $product->get_meta( '_ov25_product_id', true );
		return ! empty( $ov25_product_id );
	}

	/**
	 * Return the placeholder markup.
	 */
	private static function skeleton_markup() {
		return '<span class="ov25-price-skeleton woocommerce-Price-amount amount" data-ov25-price></span>';
	}
}
