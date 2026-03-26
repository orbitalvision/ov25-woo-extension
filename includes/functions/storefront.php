<?php
/**
 * Product page helpers: body class, CSS, gallery URLs, storefront configurator JSON.
 *
 * @package extension
 */

defined( 'ABSPATH' ) || exit;

/**
 * Marks single product pages that use OV25 (for scoped storefront CSS).
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function ov25_product_body_class( $classes ) {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return $classes;
	}
	$product = wc_get_product();
	if ( $product && $product->get_meta( '_ov25_product_id', true ) ) {
		$classes[] = 'ov25-product';
	}
	return $classes;
}

/**
 * Hides WooCommerce / block / theme native add-to-cart controls on OV25 product pages (see body_class ov25-product).
 *
 * @return string Inline CSS.
 */
function ov25_get_hide_native_add_to_cart_css() {
	return implode(
		"\n",
		array(
			'.ov25-product div.product form.cart button[type="submit"]:not(.ov25-replacement-button),',
			'.ov25-product div.product form.cart input[type="submit"]:not(.ov25-replacement-button),',
			'.ov25-product div.product form.cart .single_add_to_cart_button:not(.ov25-replacement-button),',
			'.ov25-product div.product div.summary .single_add_to_cart_button:not(.ov25-replacement-button),',
			'.ov25-product form.cart button[type="submit"]:not(.ov25-replacement-button),',
			'.ov25-product .wp-block-woocommerce-product-button .wc-block-components-product-button__button:not(.ov25-replacement-button){display:none!important;}',
			'.ov25-product .ov25-replacement-button{display:inline-flex!important;}',
		)
	);
}

/**
 * WooCommerce Blocks render cart line meta in ProductDetails with a literal " / " between entries.
 * Hide those separators and use a column layout so ITEM, SKU, etc. appear on their own lines.
 *
 * @return string Inline CSS.
 */
function ov25_wc_blocks_product_details_newlines_css() {
	return implode(
		"\n",
		array(
			'.wc-block-components-product-details{',
			'display:flex;',
			'flex-direction:column;',
			'align-items:flex-start;',
			'gap:.35em;',
			'}',
			'.wc-block-components-product-details>span[aria-hidden="true"]{display:none!important;}',
			'.wc-block-components-product-details [aria-hidden="true"]{display:none!important;}',
		)
	);
}

/**
 * Get product images for the current product.
 *
 * @return array Array of image URLs
 */
function ov25_get_product_images() {
	try {
		if ( ! is_product() ) {
			return array();
		}

		$product = wc_get_product();
		if ( ! $product ) {
			return array();
		}

		$images = array();

		$main_image_id = $product->get_image_id();
		if ( $main_image_id ) {
			$main_image_url = wp_get_attachment_image_url( $main_image_id, 'full' );
			if ( $main_image_url ) {
				$images[] = $main_image_url;
			}
		}

		$gallery_image_ids = $product->get_gallery_image_ids();
		foreach ( $gallery_image_ids as $image_id ) {
			$image_url = wp_get_attachment_image_url( $image_id, 'full' );
			if ( $image_url ) {
				$images[] = $image_url;
			}
		}

		return $images;
	} catch ( Exception $e ) {
		error_log( 'OV25 Woo Extension: Error getting product images - ' . $e->getMessage() );
		return array();
	}
}

/**
 * Default configurator layout block — matches ov25-setup DEFAULT_TYPE_SETTINGS export shape.
 */
function ov25_default_configurator_layout_block() {
	return array(
		'carousel'     => array(
			'desktop'   => 'stacked',
			'mobile'    => 'carousel',
			'maxImages' => array(
				'desktop' => 4,
				'mobile'  => 6,
			),
		),
		'configurator' => array(
			'displayMode'  => array(
				'desktop' => 'sheet',
				'mobile'  => 'drawer',
			),
			'triggerStyle' => array(
				'desktop' => 'single-button',
				'mobile'  => 'single-button',
			),
			'variants'     => array(
				'displayMode'               => array(
					'desktop' => 'tree',
					'mobile'  => 'list',
				),
				'useSimpleVariantsSelector' => true,
			),
		),
		'flags'        => array(
			'hidePricing'  => false,
			'hideAr'       => false,
			'deferThreeD'  => false,
			'showOptional' => false,
			'forceMobile'  => false,
			'autoOpen'     => false,
		),
	);
}

/**
 * Full global configurator config (standard + snap2) when none is stored yet.
 */
function ov25_default_configurator_config_array() {
	$block = ov25_default_configurator_layout_block();
	return array(
		'standard' => $block,
		'snap2'    => $block,
	);
}

/**
 * Config passed to the storefront: stored JSON merged with per-layout defaults.
 */
function ov25_get_storefront_configurator_config() {
	$raw  = get_option( 'ov25_configurator_config', '{}' );
	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		$data = array();
	}
	if ( empty( $data ) ) {
		return ov25_default_configurator_config_array();
	}
	$defaults = ov25_default_configurator_config_array();
	foreach ( array( 'standard', 'snap2' ) as $layout ) {
		if ( empty( $data[ $layout ] ) || ! is_array( $data[ $layout ] ) ) {
			$data[ $layout ] = $defaults[ $layout ];
		}
	}
	return $data;
}
