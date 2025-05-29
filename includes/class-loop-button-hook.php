<?php
/**
 * OV25 Loop Button Hook
 *
 * Replaces "Add to Cart" button with "View" button for products requiring customization
 * when they appear on non-product pages (shop, category, related products, etc.)
 *
 * @package  Ov25WooExtension
 */

defined( 'ABSPATH' ) || exit;

/**
 * OV25 Loop Button Hook class.
 */
class OV25_Loop_Button_Hook {

	/**
	 * Initialize the class.
	 */
	public static function init() {
		add_filter( 'woocommerce_loop_add_to_cart_link', array( __CLASS__, 'replace_loop_button' ), 10, 2 );
	}
	
	/**
	 * Remove "Add to cart" button in product loops for OV25 products.
	 *
	 * @param string     $button_html The original button HTML.
	 * @param WC_Product $product     The product object.
	 * @return string Modified button HTML or empty string.
	 */
	public static function replace_loop_button( $button_html, $product ) {
		if ( is_product() ) {
			return $button_html;
		}

		$ov25_product_id = get_post_meta( $product->get_id(), '_ov25_product_id', true );
		
		if ( empty( $ov25_product_id ) ) {
			return $button_html;
		}

		return '';
	}
} 