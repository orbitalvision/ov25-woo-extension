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
	}
}


