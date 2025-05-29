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
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'add_product_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product_field' ) );
		add_action( 'init', array( __CLASS__, 'register_product_meta' ) );
	}
	
	/**
	 * Add the OV25 Product ID field to the product data general tab.
	 */
	public static function add_product_field() {
		woocommerce_wp_text_input( array(
			'id'          => '_ov25_product_id',
			'label'       => __( 'OV25 Product ID', 'ov25-woo-extension' ),
			'placeholder' => __( 'e.g. 97 or range/16', 'ov25-woo-extension' ),
			'desc_tip'    => true,
			'description' => __( 'Optional ID used by the OV25 3D Configurator.', 'ov25-woo-extension' ),
		) );
	}
	
	/**
	 * Save the OV25 Product ID field.
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