<?php
/**
 * OV25 Product Metabox
 *
 * Registers a dedicated OV25 metabox on the WooCommerce product edit page
 * with a React-powered product selector that fetches available products
 * from the OV25 API.
 *
 * @package  Ov25WooExtension
 */

defined( 'ABSPATH' ) || exit;


class OV25_Product_Field {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_metabox' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product_field' ) );
		add_action( 'init', array( __CLASS__, 'register_product_meta' ) );
	}

	/**
	 * Register a standalone OV25 metabox on the product edit screen.
	 */
	public static function register_metabox() {
		add_meta_box(
			'ov25-product-settings',
			__( 'OV25 Configurator', 'ov25-woo-extension' ),
			array( __CLASS__, 'render_metabox' ),
			'product',
			'normal',
			'high'
		);
	}

	/**
	 * Render the metabox contents: hidden inputs for save, plus a React mount point.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public static function render_metabox( $post ) {
		$product_id    = get_post_meta( $post->ID, '_ov25_product_id', true );
		$use_custom    = get_post_meta( $post->ID, '_ov25_use_custom_config', true );
		$custom_config = get_post_meta( $post->ID, '_ov25_configurator_config', true );

		// Hidden fields for standard form submission
		echo '<input type="hidden" name="_ov25_product_id" id="_ov25_product_id" value="' . esc_attr( $product_id ) . '" />';
		echo '<input type="hidden" name="_ov25_use_custom_config" value="' . esc_attr( $use_custom ?: 'no' ) . '" />';
		echo '<input type="hidden" name="_ov25_configurator_config" value="' . esc_attr( $custom_config ?: '{}' ) . '" />';

		// React mount point
		echo '<div id="ov25-product-field-root"'
			. ' data-product-id="' . esc_attr( $post->ID ) . '"'
			. ' data-current-link="' . esc_attr( $product_id ) . '"'
			. ' data-use-custom-config="' . esc_attr( $use_custom ?: 'no' ) . '"'
			. ' data-custom-config="' . esc_attr( $custom_config ?: '{}' ) . '"'
			. '></div>';

		// Fallback for when JS is disabled
		echo '<noscript>';
		echo '<p><label for="_ov25_product_id_noscript">' . esc_html__( 'OV25 Product ID', 'ov25-woo-extension' ) . '</label></p>';
		echo '<input type="text" id="_ov25_product_id_noscript" name="_ov25_product_id" value="' . esc_attr( $product_id ) . '" style="width:100%;" placeholder="e.g. 97, range/16, snap2/16" />';
		echo '</noscript>';
	}

	/**
	 * Save the OV25 product fields when the product is saved.
	 *
	 * @param WC_Product $product Product object.
	 */
	public static function save_product_field( $product ) {
		if ( isset( $_POST['_ov25_product_id'] ) ) {
			$product->update_meta_data(
				'_ov25_product_id',
				sanitize_text_field( wp_unslash( $_POST['_ov25_product_id'] ) )
			);
		}

		if ( isset( $_POST['_ov25_use_custom_config'] ) ) {
			$product->update_meta_data(
				'_ov25_use_custom_config',
				sanitize_text_field( wp_unslash( $_POST['_ov25_use_custom_config'] ) )
			);
		}

		if ( isset( $_POST['_ov25_configurator_config'] ) ) {
			$raw     = wp_unslash( $_POST['_ov25_configurator_config'] );
			$decoded = json_decode( $raw, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				$product->update_meta_data( '_ov25_configurator_config', wp_json_encode( $decoded ) );
			}
			// If decode fails (truncated hidden field, bad POST, etc.), keep existing meta — do not overwrite with {}.
		}
	}

	/**
	 * Register product meta for REST API access.
	 */
	public static function register_product_meta() {
		register_post_meta(
			'product',
			'_ov25_product_id',
			array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => array(
					'schema' => array(
						'description' => __( 'OV25 system product ID', 'ov25-woo-extension' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
					),
				),
				'auth_callback' => function() {
					return current_user_can( 'edit_products' );
				},
			)
		);
	}
}
