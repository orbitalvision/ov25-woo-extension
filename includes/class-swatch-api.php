<?php
/**
 * OV25 Swatches REST API
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'OV25_Swatch_API' ) ) {
	class OV25_Swatch_API {
		public static function init() {
			add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
		}

		public static function register_routes() {
			register_rest_route(
				'ov25/v1',
				'/swatches',
				[
					'methods'             => 'GET',
					'callback'            => [ __CLASS__, 'get_swatches' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'q' => [ 'type' => 'string', 'required' => false ],
					],
				]
			);

			register_rest_route(
				'ov25/v1',
				'/swatch-rules',
				[
					'methods'             => 'GET',
					'callback'            => [ __CLASS__, 'get_swatch_rules' ],
					'permission_callback' => '__return_true',
				]
			);

			register_rest_route(
				'ov25/v1',
				'/create-swatch-cart',
				[
					'methods'             => 'POST',
					'callback'            => [ __CLASS__, 'create_swatch_cart' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'swatch_data' => [
							'required' => true,
							'type'     => 'string',
						],
					],
				]
			);
		}

		/**
		 * Return swatches as JSON.
		 */
		public static function get_swatches( WP_REST_Request $request ) {
			$q = sanitize_text_field( (string) $request->get_param( 'q' ) );
			$sw_key = trim( (string) get_option( 'ov25_private_api_key', '' ) );
			
			if ( empty( $sw_key ) ) {
				return new WP_Error( 'ov25_swatches_no_key', 'API key not configured', [ 'status' => 500 ] );
			}
			
			$org_id = strstr( $sw_key, '-', true );
			if ( ! $org_id ) {
				return new WP_Error( 'ov25_swatches_invalid_key', 'Invalid API key format', [ 'status' => 500 ] );
			}
			
			$base_url = 'https://webhooks.orbital.vision/api/public/swatches';
			$url = add_query_arg( [ 'orgId' => $org_id, 'apiKey' => $sw_key ], $base_url );
			
			if ( $q ) {
				$url = add_query_arg( [ 'q' => rawurlencode( $q ) ], $url );
			}
			
			$response = wp_remote_get( $url, [ 
				'timeout' => 10, 
				'headers' => [ 'Accept' => 'application/json' ] 
			] );
			
			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'ov25_swatches_fetch_failed', $response->get_error_message(), [ 'status' => 500 ] );
			}
			
			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			
			if ( $code !== 200 ) {
				return new WP_Error( 'ov25_swatches_http_' . $code, 'Failed to fetch swatches', [ 'status' => $code ] );
			}
			
			$data = json_decode( $body, true );
			if ( ! is_array( $data ) ) {
				return new WP_Error( 'ov25_swatches_invalid_json', 'Invalid swatches JSON', [ 'status' => 500 ] );
			}
			return rest_ensure_response( [ 'swatches' => array_values( $data ) ] );
		}

		/**
		 * Return swatch rules as JSON.
		 */
		public static function get_swatch_rules( WP_REST_Request $request ) {
			$sw_key = trim( (string) get_option( 'ov25_private_api_key', '' ) );
			
			if ( empty( $sw_key ) ) {
				return new WP_Error( 'ov25_swatch_rules_no_key', 'API key not configured', [ 'status' => 500 ] );
			}
			
			$org_id = strstr( $sw_key, '-', true );
			if ( ! $org_id ) {
				return new WP_Error( 'ov25_swatch_rules_invalid_key', 'Invalid API key format', [ 'status' => 500 ] );
			}
			
			$base_url = 'http://webhooks.orbital.vision/api/public/swatch-rules';
			$url = add_query_arg( [ 'orgId' => $org_id, 'apiKey' => $sw_key ], $base_url );
			
			$response = wp_remote_get( $url, [ 
				'timeout' => 10, 
				'headers' => [ 'Accept' => 'application/json' ] 
			] );
			
			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'ov25_swatch_rules_fetch_failed', $response->get_error_message(), [ 'status' => 500 ] );
			}
			
			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			
			if ( $code !== 200 ) {
				return new WP_Error( 'ov25_swatch_rules_http_' . $code, 'Failed to fetch swatch rules', [ 'status' => $code ] );
			}
			
			$data = json_decode( $body, true );
			if ( ! is_array( $data ) && ! is_object( $data ) ) {
				return new WP_Error( 'ov25_swatch_rules_invalid_json', 'Invalid swatch rules JSON', [ 'status' => 500 ] );
			}

			return rest_ensure_response( $data );
		}

		/**
		 * Create a swatch-only cart.
		 */
		public static function create_swatch_cart( WP_REST_Request $request ) {
			// Verify WooCommerce is loaded
			if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) {
				return new WP_Error( 'woocommerce_not_loaded', 'WooCommerce not available', [ 'status' => 500 ] );
			} 
			
			// Initialize WooCommerce session and cart if not already done
			if ( is_null( WC()->session ) ) {
				WC()->session = new WC_Session_Handler();
				WC()->session->init();
			}

			if ( is_null( WC()->cart ) ) {
				WC()->cart = new WC_Cart();
			}
		
			// Ensure customer is set
			if ( is_null( WC()->customer ) ) {
				WC()->customer = new WC_Customer( get_current_user_id(), true );
			}

			try {
				// Get swatch data from request
				$swatch_data_json = $request->get_param( 'swatch_data' );
				$swatch_data = json_decode( wp_unslash( $swatch_data_json ), true );
				
				if ( ! $swatch_data || ! is_array( $swatch_data ) ) {
					return new WP_Error( 'invalid_swatch_data', 'No swatch data provided', [ 'status' => 400 ] );
				}

				// Store current cart contents to restore later
				$current_cart = WC()->cart->get_cart();
				WC()->session->set( 'ov25_original_cart', $current_cart );
				
				// Clear the main cart
				WC()->cart->empty_cart();
				
				// Add swatches to the empty cart
				$swatches = isset( $swatch_data['swatches'] ) ? $swatch_data['swatches'] : [];
				$rules = isset( $swatch_data['rules'] ) ? $swatch_data['rules'] : [];
				
				// Resolve swatch product id on the server (create if missing)
				$product_id = ov25_ensure_swatch_product_exists();
				
				foreach ( $swatches as $index => $swatch ) {
					$is_free = $index < ( $rules['freeSwatchLimit'] ?? 0 );
					$swatch_price = $is_free ? 0 : ( $rules['pricePerSwatch'] ?? 0 );
					
					$cart_item_data = array(
						'swatch_manufacturer_id' => $swatch['manufacturerId'] ?? '',
						'swatch_name' => $swatch['name'] ?? '',
						'swatch_option' => $swatch['option'] ?? '',
						'swatch_total_count' => count( $swatches ),
						'swatch_price' => $swatch_price,
						'swatch_sku' => $swatch['sku'] ?? '',
						'ov25-thumbnail' => $swatch['thumbnail']['miniThumbnails']['small'] ?? '',
					);
					
					WC()->cart->add_to_cart( $product_id, 1, 0, array(), $cart_item_data );
				}
				
				// Mark this as a swatch-only cart
				WC()->session->set( 'ov25_swatch_only_cart', true );
				
				// Return the correct checkout URL (respects permalinks/settings)
				$checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' );
				
				return rest_ensure_response( [
					'success' => true,
					'message' => 'Swatch-only cart created successfully',
					'checkout_url' => $checkout_url,
				] );
				
			} catch ( Exception $e ) {
				error_log( 'OV25 Woo Extension: Error creating swatch-only cart - ' . $e->getMessage() );
				return new WP_Error( 'swatch_cart_error', 'Failed to create swatch-only cart: ' . $e->getMessage(), [ 'status' => 500 ] );
			}
		}
	}
}


