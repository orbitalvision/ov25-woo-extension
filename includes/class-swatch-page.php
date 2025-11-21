<?php
/**
 * OV25 Swatches Page (Shortcode + Assets)
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'OV25_Swatch_Page' ) ) {
	class OV25_Swatch_Page {
		public static function init() {
			add_shortcode( 'ov25_swatches', [ __CLASS__, 'render_shortcode' ] );
			add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		}

		public static function render_shortcode( $atts = [] ) {
			$atts = shortcode_atts( [
				'class' => '',
			], $atts );

			// Container for React/Vue/JS app
			return '<div id="ov25-swatches-app" class="' . esc_attr( $atts['class'] ) . '"></div>';
		}

		public static function enqueue_assets() {
			if ( ! is_singular() ) {
				return;
			}

			global $post;
			if ( ! $post || false === has_shortcode( (string) $post->post_content, 'ov25_swatches' ) ) {
				return; // Only enqueue when shortcode is present
			}

			$asset_file = plugin_dir_path( MAIN_PLUGIN_FILE ) . 'build/swatches.asset.php';
			$asset = [ 'dependencies' => [], 'version' => '1.0.0' ];
			if ( file_exists( $asset_file ) ) {
				$asset = include $asset_file;
			}

			$js_file = plugin_dir_path( MAIN_PLUGIN_FILE ) . 'build/swatches.js';
			if ( file_exists( $js_file ) ) {
				wp_enqueue_script(
					'ov25-swatches',
					plugins_url( 'build/swatches.js', MAIN_PLUGIN_FILE ),
					$asset['dependencies'] ?? [],
					$asset['version'] ?? filemtime( $js_file ),
					true
				);

				// Data for frontend page
				wp_localize_script( 'ov25-swatches', 'ov25SwatchesPage', [
					'restBase' => esc_url_raw( get_rest_url() ), // Base REST URL (handles both pretty and plain permalinks)
					'swatchesUrl' => esc_url_raw( get_rest_url( null, 'ov25/v1/swatches' ) ), // Full URL to swatches endpoint
					'swatchRulesUrl' => esc_url_raw( get_rest_url( null, 'ov25/v1/swatch-rules' ) ), // Full URL to swatch-rules endpoint
					'nonce'    => wp_create_nonce( 'wp_rest' ),
					'customCSS' => get_option( 'ov25_custom_css', '' ), // Custom CSS from WooCommerce admin
				] );
			}

			// Enqueue the swatches CSS
			$css_file = plugin_dir_path( MAIN_PLUGIN_FILE ) . 'build/swatches.css';
			if ( file_exists( $css_file ) ) {
				wp_enqueue_style(
					'ov25-swatches',
					plugins_url( 'build/swatches.css', MAIN_PLUGIN_FILE ),
					[],
					filemtime( $css_file )
				);
			}
		}
	}
}




