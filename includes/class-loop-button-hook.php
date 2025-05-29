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
		// Replace loop add to cart button with view button for OV25 products
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
		// Bail out on the single-product page – we WANT the add-to-cart there
		if ( is_product() ) {
			return $button_html;
		}

		// Check if this product has an OV25 Product ID (requires customization)
		$ov25_product_id = get_post_meta( $product->get_id(), '_ov25_product_id', true );
		
		// If no OV25 Product ID is set, keep the default button
		if ( empty( $ov25_product_id ) ) {
			return $button_html;
		}

		// Remove the button for products that require customization
		return '';
	}
}

// Initialize the class
OV25_Loop_Button_Hook::init(); 