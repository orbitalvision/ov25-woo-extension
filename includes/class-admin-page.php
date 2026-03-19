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
		$icon_path = dirname( MAIN_PLUGIN_FILE ) . '/assets/ov-logo.svg';
		$icon_svg  = 'data:image/svg+xml;base64,' . base64_encode( file_get_contents( $icon_path ) );

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
		if ( '' === get_option( 'permalink_structure' ) ) {
			$permalink_url = admin_url( 'options-permalink.php' );
			echo '<div class="notice notice-warning is-dismissible"><p>';
			echo esc_html__( 'OV25 and WooCommerce REST require a non-Plain permalink structure. Please set your permalinks to anything other than Plain.', 'ov25-woo-extension' );
			echo ' <a href="' . esc_url( $permalink_url ) . '">' . esc_html__( 'Go to Permalink Settings', 'ov25-woo-extension' ) . '</a>';
			echo '</p></div>';
		}
		echo '<div class="wrap"><div id="ov25-admin-root"></div></div>';
	}

	public static function enqueue_scripts( $hook ) {
		$is_ov25_page    = 'toplevel_page_ov25' === $hook;
		$is_product_edit = in_array( $hook, array( 'post.php', 'post-new.php' ), true )
			&& isset( $_GET['post'] ) && get_post_type( absint( $_GET['post'] ) ) === 'product'
			|| ( 'post-new.php' === $hook && isset( $_GET['post_type'] ) && $_GET['post_type'] === 'product' );

		if ( ! $is_ov25_page && ! $is_product_edit ) {
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

		$ov25_link_base = defined( 'OV25_APP_URL' ) ? OV25_APP_URL : 'https://woocommerce.ov25.ai';
		wp_localize_script( 'ov25-admin', 'ov25Admin', array(
			'restBase'           => esc_url_raw( get_rest_url( null, 'ov25/v1/' ) ),
			'nonce'              => wp_create_nonce( 'wp_rest' ),
			'apiKey'             => get_option( 'ov25_api_key', '' ),
			'privateApiKey'      => get_option( 'ov25_private_api_key', '' ),
			'orgName'            => get_option( 'ov25_org_name', '' ),
			'ov25LinkBaseUrl'    => esc_url_raw( rtrim( $ov25_link_base, '/' ) ),
			'ov25StoreUrl'      => esc_url_raw( home_url( '/', 'https' ) ),
			'ov25LinkState'     => wp_create_nonce( 'ov25_woo_link' ),
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
