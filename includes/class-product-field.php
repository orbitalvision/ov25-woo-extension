<?php
/**
 * OV25 Product ID Field
 *
 * @package  Ov25WooExtension
 */

defined( 'ABSPATH' ) || exit;

/**
 * OV25 Product ID Field class.
 */
class OV25_Product_Field {

	/**
	 * Initialize the class.
	 */
	public static function init() {
		// Add field to General tab (works for simple, variable, and other product types)
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'add_product_field' ) );
		
		// Also add to Advanced tab to ensure visibility for all product types
		add_action( 'woocommerce_product_options_advanced', array( __CLASS__, 'add_product_field_advanced' ) );
		
		// Save the field data
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product_field' ) );
		
		// Register meta for REST API
		add_action( 'init', array( __CLASS__, 'register_product_meta' ) );
	}
	
	/**
	 * Add the OV25 Product ID field to the product data general tab.
	 */
	public static function add_product_field() {
		echo '<div class="options_group show_if_simple show_if_variable show_if_grouped show_if_external">';
		woocommerce_wp_text_input( array(
			'id'          => '_ov25_product_id',
			'label'       => __( 'OV25 Product ID', 'ov25-woo-extension' ),
			'placeholder' => __( 'e.g. 97 or range/16', 'ov25-woo-extension' ),
			'desc_tip'    => true,
			'description' => __( 'Optional ID used by the OV25 3D Configurator. Works with all product types including variable products.', 'ov25-woo-extension' ),
		) );
		echo '</div>';
	}
	
	/**
	 * Add the OV25 Product ID field to the advanced tab as a fallback.
	 */
	public static function add_product_field_advanced() {
		// Only show in advanced tab if we're dealing with a product type that might not show general tab
		global $post;
		$product = wc_get_product( $post->ID );
		
		// Skip if product doesn't exist or if it's a simple/variable product (already shown in general tab)
		if ( ! $product || in_array( $product->get_type(), array( 'simple', 'variable' ), true ) ) {
			return;
		}
		
		echo '<div class="options_group">';
		woocommerce_wp_text_input( array(
			'id'          => '_ov25_product_id_advanced',
			'label'       => __( 'OV25 Product ID', 'ov25-woo-extension' ),
			'placeholder' => __( 'e.g. 97 or range/16', 'ov25-woo-extension' ),
			'desc_tip'    => true,
			'description' => __( 'Optional ID used by the OV25 3D Configurator.', 'ov25-woo-extension' ),
			'value'       => get_post_meta( $post->ID, '_ov25_product_id', true ),
		) );
		echo '</div>';
	}
	
	/**
	 * Save the OV25 Product ID field.
	 *
	 * @param WC_Product $product Product object.
	 */
	public static function save_product_field( $product ) {
		// Save from general tab field
		if ( isset( $_POST['_ov25_product_id'] ) ) {
			$product->update_meta_data(
				'_ov25_product_id',
				sanitize_text_field( wp_unslash( $_POST['_ov25_product_id'] ) )
			);
		}
		
		// Save from advanced tab field (fallback)
		if ( isset( $_POST['_ov25_product_id_advanced'] ) ) {
			$product->update_meta_data(
				'_ov25_product_id',
				sanitize_text_field( wp_unslash( $_POST['_ov25_product_id_advanced'] ) )
			);
		}
	}
	
	/**
	 * Register the OV25 Product ID field for the REST API.
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