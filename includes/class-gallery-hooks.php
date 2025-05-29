<?php
/**
 * OV25 Gallery Hooks
 *
 * @package Ov25WooExtension
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds data‑ov25‑iframe="apikey/productId" to the product‑gallery wrapper.
 */
class OV25_Gallery_Hooks {

	public static function init() {
		if ( is_admin() ) {
			return;
		}

		// Classic template
		add_filter(
			'woocommerce_show_product_images',
			[ __CLASS__, 'inject_attribute_into_gallery_html' ],
			5
		);

		// Block template
		add_filter(
			'render_block_woocommerce/product-image-gallery',
			[ __CLASS__, 'add_attribute_to_gallery_block' ],
			10,
			2
		);
	}

	/* === Classic template ============================================ */

	public static function inject_attribute_into_gallery_html() {
		global $product;

		ob_start();
		woocommerce_show_product_images();
		$html = ob_get_clean();

		if ( ! $product || ! method_exists( $product, 'get_meta' ) ) {
			echo $html;
			return;
		}

		$prod_id = $product->get_meta( '_ov25_product_id', true );
		$api_key = trim( get_option( 'ov25_api_key', '' ) );

		if ( $prod_id && $api_key ) {
			$attr = esc_attr( "{$api_key}/{$prod_id}" );
			$html = preg_replace(
				'/^<(\w+)/',
				sprintf(
					'<$1 data-ov25-iframe="%s" class="ov25-gallery ov25-id-%s"',
					$attr,
					esc_attr( $prod_id )
				),
				$html,
				1
			);
		}
		echo $html;
	}

	/* === Block template ============================================== */

	public static function add_attribute_to_gallery_block( $html, $block ) {
		if ( ! is_product() ) {
			return $html;
		}

		$product  = wc_get_product();
		$prod_id  = $product ? $product->get_meta( '_ov25_product_id', true ) : '';
		$api_key  = trim( get_option( 'ov25_api_key', '' ) );

		if ( $prod_id && $api_key ) {
			$attr = esc_attr( "{$api_key}/{$prod_id}" );
			return preg_replace(
				'/^<(\w+)/',
				sprintf(
					'<$1 data-ov25-iframe="%s" class="ov25-gallery ov25-id-%s"',
					$attr,
					esc_attr( $prod_id )
				),
				$html,
				1
			);
		}
		return $html;
	}
}
