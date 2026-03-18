<?php
/**
 * OV25 Admin REST API
 *
 * @package Ov25WooExtension
 */

defined( 'ABSPATH' ) || exit;

class OV25_Admin_API {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		$namespace = 'ov25/v1';

		register_rest_route( $namespace, '/settings', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_settings' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_woocommerce' );
				},
			),
			array(
				'methods'             => 'PUT',
				'callback'            => array( __CLASS__, 'save_settings' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_woocommerce' );
				},
			),
		) );

		register_rest_route( $namespace, '/products-list', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_products_list' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			},
		) );

		register_rest_route( $namespace, '/product-settings/(?P<id>\d+)', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_product_settings' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_products' );
				},
			),
			array(
				'methods'             => 'PUT',
				'callback'            => array( __CLASS__, 'save_product_settings' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_products' );
				},
			),
		) );
	}

	public static function get_settings() {
		return rest_ensure_response( array(
			'apiKey'             => get_option( 'ov25_api_key', '' ),
			'privateApiKey'      => get_option( 'ov25_private_api_key', '' ),
			'configuratorConfig' => json_decode( get_option( 'ov25_configurator_config', '{}' ), true ),
			'logoURL'            => get_option( 'ov25_logo_url', '' ),
			'mobileLogoURL'      => get_option( 'ov25_mobile_logo_url', '' ),
			'gallerySelector'    => get_option( 'ov25_gallery_selector', '' ),
			'variantsSelector'   => get_option( 'ov25_variants_selector', '' ),
			'priceSelector'      => get_option( 'ov25_price_selector', '' ),
			'swatchesSelector'   => get_option( 'ov25_swatches_selector', '' ),
			'configureButtonSelector' => get_option( 'ov25_configure_button_selector', '' ),
			'customCSS'          => get_option( 'ov25_custom_css', '' ),
			'showSwatchesPage'   => get_option( 'ov25_show_swatches_page', 'no' ),
			'swatchesPageSlug'   => get_option( 'ov25_swatches_page_slug', 'swatches' ),
			'swatchesPageTitle'  => get_option( 'ov25_swatches_page_title', 'Swatches' ),
			'swatchesShowInNav'  => get_option( 'ov25_swatches_show_in_nav', 'no' ),
			'swatchesTestMode'   => get_option( 'ov25_swatches_test_mode', 'no' ),
		) );
	}

	public static function save_settings( $request ) {
		$params = $request->get_json_params();

		$text_options = array(
			'apiKey'             => 'ov25_api_key',
			'privateApiKey'      => 'ov25_private_api_key',
			'logoURL'            => 'ov25_logo_url',
			'mobileLogoURL'      => 'ov25_mobile_logo_url',
			'gallerySelector'    => 'ov25_gallery_selector',
			'variantsSelector'   => 'ov25_variants_selector',
			'priceSelector'      => 'ov25_price_selector',
			'swatchesSelector'   => 'ov25_swatches_selector',
			'configureButtonSelector' => 'ov25_configure_button_selector',
			'customCSS'          => 'ov25_custom_css',
			'swatchesPageSlug'   => 'ov25_swatches_page_slug',
			'swatchesPageTitle'  => 'ov25_swatches_page_title',
		);

		foreach ( $text_options as $key => $option_name ) {
			if ( isset( $params[ $key ] ) ) {
				update_option( $option_name, sanitize_text_field( $params[ $key ] ) );
			}
		}

		$yesno_options = array(
			'showSwatchesPage'  => 'ov25_show_swatches_page',
			'swatchesShowInNav' => 'ov25_swatches_show_in_nav',
			'swatchesTestMode'  => 'ov25_swatches_test_mode',
		);

		foreach ( $yesno_options as $key => $option_name ) {
			if ( isset( $params[ $key ] ) ) {
				update_option( $option_name, $params[ $key ] === 'yes' || $params[ $key ] === true ? 'yes' : 'no' );
			}
		}

		if ( isset( $params['configuratorConfig'] ) && is_array( $params['configuratorConfig'] ) ) {
			update_option( 'ov25_configurator_config', wp_json_encode( $params['configuratorConfig'] ) );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Proxy to OV25 products-list API using private API key.
	 */
	public static function get_products_list() {
		$private_key = get_option( 'ov25_private_api_key', '' );

		if ( empty( $private_key ) ) {
			return new WP_Error(
				'missing_api_key',
				__( 'Private API key is not configured.', 'ov25-woo-extension' ),
				array( 'status' => 400 )
			);
		}

		$api_url = 'https://ov25.ai/api/public/products-list?apiKey=' . urlencode( $private_key );

		$response = wp_remote_get( $api_url, array(
			'timeout' => 30,
			'headers' => array(
				'Accept'     => 'application/json',
				'User-Agent' => 'OV25-WooExtension/' . ( defined( 'OV25_VERSION' ) ? OV25_VERSION : '0.3.47' ),
			),
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'api_error',
				$response->get_error_message(),
				array( 'status' => 502 )
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( $status_code !== 200 ) {
			return new WP_Error(
				'api_error',
				__( 'OV25 API returned an error.', 'ov25-woo-extension' ),
				array( 'status' => $status_code )
			);
		}

		$data = json_decode( $body, true );
		return rest_ensure_response( $data );
	}

	public static function get_product_settings( $request ) {
		$product_id = (int) $request->get_param( 'id' );
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			return new WP_Error( 'not_found', __( 'Product not found.', 'ov25-woo-extension' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( array(
			'productId'         => $product->get_meta( '_ov25_product_id', true ),
			'useCustomConfig'   => $product->get_meta( '_ov25_use_custom_config', true ) === 'yes',
			'configuratorConfig' => json_decode( $product->get_meta( '_ov25_configurator_config', true ) ?: '{}', true ),
		) );
	}

	public static function save_product_settings( $request ) {
		$product_id = (int) $request->get_param( 'id' );
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			return new WP_Error( 'not_found', __( 'Product not found.', 'ov25-woo-extension' ), array( 'status' => 404 ) );
		}

		$params = $request->get_json_params();

		if ( isset( $params['productId'] ) ) {
			$product->update_meta_data( '_ov25_product_id', sanitize_text_field( $params['productId'] ) );
		}

		if ( isset( $params['useCustomConfig'] ) ) {
			$product->update_meta_data( '_ov25_use_custom_config', $params['useCustomConfig'] ? 'yes' : 'no' );
		}

		if ( isset( $params['configuratorConfig'] ) && is_array( $params['configuratorConfig'] ) ) {
			$product->update_meta_data( '_ov25_configurator_config', wp_json_encode( $params['configuratorConfig'] ) );
		}

		$product->save();

		return rest_ensure_response( array( 'success' => true ) );
	}
}
