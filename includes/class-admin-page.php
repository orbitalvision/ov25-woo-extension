<?php
/**
 * OV25 Admin Page
 *
 * @package Ov25WooExtension
 */

defined( 'ABSPATH' ) || exit;

class OV25_Admin_Page {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	public static function register_menu() {
		$icon_svg = 'data:image/svg+xml;base64,' . base64_encode(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none">'
			. '<defs><linearGradient id="ov25g" x1="0%" y1="0%" x2="100%" y2="0%">'
			. '<stop offset="0%" stop-color="#26E8FE"/><stop offset="50%" stop-color="#808AFF"/><stop offset="100%" stop-color="#A41EFE"/>'
			. '</linearGradient></defs>'
			. '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z" fill="url(#ov25g)"/>'
			. '<path d="M12 6c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6-2.69-6-6-6zm0 10c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4z" fill="url(#ov25g)"/>'
			. '</svg>'
		);

		add_menu_page(
			__( 'OV25', 'ov25-woo-extension' ),
			__( 'OV25', 'ov25-woo-extension' ),
			'manage_woocommerce',
			'ov25',
			array( __CLASS__, 'render_page' ),
			$icon_svg,
			56
		);
	}

	public static function render_page() {
		echo '<div class="wrap"><div id="ov25-admin-root"></div></div>';
	}

	public static function enqueue_scripts( $hook ) {
		if ( 'toplevel_page_ov25' !== $hook ) {
			return;
		}

		$asset_file = plugin_dir_path( MAIN_PLUGIN_FILE ) . 'build/admin.asset.php';
		$asset      = array( 'dependencies' => array(), 'version' => '1.0.0' );
		if ( file_exists( $asset_file ) ) {
			$asset = include $asset_file;
		}

		$js_file = plugin_dir_path( MAIN_PLUGIN_FILE ) . 'build/admin.js';
		if ( ! file_exists( $js_file ) ) {
			return;
		}

		wp_enqueue_script(
			'ov25-admin',
			plugins_url( 'build/admin.js', MAIN_PLUGIN_FILE ),
			$asset['dependencies'] ?? array(),
			$asset['version'] ?? '1.0.0',
			true
		);

		wp_localize_script( 'ov25-admin', 'ov25Admin', array(
			'restBase'           => esc_url_raw( get_rest_url( null, 'ov25/v1/' ) ),
			'nonce'              => wp_create_nonce( 'wp_rest' ),
			'apiKey'             => get_option( 'ov25_api_key', '' ),
			'privateApiKey'      => get_option( 'ov25_private_api_key', '' ),
			'configuratorConfig' => json_decode( get_option( 'ov25_configurator_config', '{}' ), true ),
			'version'            => defined( 'OV25_VERSION' ) ? OV25_VERSION : '0.3.47',
			'settings'           => array(
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
			),
		) );

		$css_file = plugin_dir_path( MAIN_PLUGIN_FILE ) . 'build/admin.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'ov25-admin-styles',
				plugins_url( 'build/admin.css', MAIN_PLUGIN_FILE ),
				array(),
				filemtime( $css_file )
			);
		}
	}
}
