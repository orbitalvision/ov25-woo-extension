<?php
/**
 * Plugin Name: Ov25 Woo Extension
 * Version: 0.1.1
 * Author: The WordPress Contributors
 * Author URI: https://woo.com
 * Text Domain: ov25-woo-extension
 * Domain Path: /languages
 * Update URI: https://github.com/orbitalvision/ov25-woo-extension
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package extension
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MAIN_PLUGIN_FILE' ) ) {
	define( 'MAIN_PLUGIN_FILE', __FILE__ );
}

require_once plugin_dir_path( __FILE__ ) . '/vendor/autoload_packages.php';

use Ov25WooExtension\Admin\Setup;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// Initialize Plugin Update Checker for GitHub updates
$ov25UpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/orbitalvision/ov25-woo-extension/', // Replace with your actual GitHub repo
	__FILE__,
	'ov25-woo-extension'
);

// Load GitHub token securely (only if file exists and not in production)
$token_file = plugin_dir_path( __FILE__ ) . 'github-token.php';
if ( file_exists( $token_file ) ) {
	include_once $token_file;
	if ( defined( 'OV25_GITHUB_TOKEN' ) ) {
		$ov25UpdateChecker->setAuthentication( OV25_GITHUB_TOKEN );
	}
}

// Optional: Set branch for stable releases (default is 'main')
// $ov25UpdateChecker->setBranch('stable');

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce fallback notice.
 *
 * @since 0.1.0
 */
function ov25_woo_extension_missing_wc_notice() {
	/* translators: %s WC download URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Ov25 Woo Extension requires WooCommerce to be installed and active. You can download %s here.', 'ov25_woo_extension' ), '<a href="https://woo.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

register_activation_hook( __FILE__, 'ov25_woo_extension_activate' );

/**
 * Activation hook.
 *
 * @since 0.1.0
 */
function ov25_woo_extension_activate() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'ov25_woo_extension_missing_wc_notice' );
		return;
	}
}

if ( ! class_exists( 'ov25_woo_extension' ) ) :
	/**
	 * The ov25_woo_extension class.
	 */
	class ov25_woo_extension {
		/**
		 * This class instance.
		 *
		 * @var \ov25_woo_extension single instance of this class.
		 */
		private static $instance;

		/**
		 * Constructor.
		 */
		public function __construct() {
			if ( is_admin() ) {
				new Setup();
			}
		}

		/**
		 * Cloning is forbidden.
		 */
		public function __clone() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'ov25_woo_extension' ), $this->version );
		}

		/**
		 * Unserializing instances of this class is forbidden.
		 */
		public function __wakeup() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'ov25_woo_extension' ), $this->version );
		}

		/**
		 * Gets the main instance.
		 *
		 * Ensures only one instance can be loaded.
		 *
		 * @return \ov25_woo_extension
		 */
		public static function instance() {

			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}
	}
endif;

add_action( 'plugins_loaded', 'ov25_woo_extension_init', 10 );

/**
 * Initialize the plugin.
 *
 * @since 0.1.0
 */
function ov25_woo_extension_init() {
	load_plugin_textdomain( 'ov25_woo_extension', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'ov25_woo_extension_missing_wc_notice' );
		return;
	}

	ov25_woo_extension::instance();
	
	// Add settings tab
	add_filter( 'woocommerce_get_settings_pages', 'ov25_woo_extension_add_settings' );
	
	// Load product field
	include_once dirname( __FILE__ ) . '/includes/class-product-field.php';

    // Load gallery hooks
	include_once dirname( __FILE__ ) . '/includes/class-gallery-hooks.php';
	OV25_Gallery_Hooks::init();

    //Load price hook
    include_once dirname( __FILE__ ) . '/includes/class-price-hook.php';
    OV25_Price_Hook::init();

    include_once __DIR__ . '/includes/class-variant-hook.php';
    OV25_Variant_Hook::init();

    // Load loop button hook
    include_once dirname( __FILE__ ) . '/includes/class-loop-button-hook.php';
    OV25_Loop_Button_Hook::init();

	
	// Enqueue frontend scripts
	add_action( 'wp_enqueue_scripts', function () {
		if ( ! is_product() ) {
			return;
		}

		// Only load scripts for products with OV25 Product ID
		$product = wc_get_product();
		if ( ! $product ) {
			return;
		}

		$ov25_product_id = $product->get_meta( '_ov25_product_id', true );
		if ( empty( $ov25_product_id ) ) {
			return;
		}

		$asset = include plugin_dir_path( __FILE__ ) . 'build/frontend.asset.php';

		wp_enqueue_script(
			'ov25-frontend',
			plugins_url( 'build/frontend.js', __FILE__ ),
			$asset['dependencies'] ?? [],
			$asset['version'] ?? filemtime( plugin_dir_path( __FILE__ ) . 'build/frontend.js' ),
			true
		);

		// Pass OV25 settings to frontend
		wp_localize_script( 'ov25-frontend', 'ov25Settings', array(
			'logoURL' => get_option( 'ov25_logo_url', '' ),
			'autoCarousel' => get_option( 'ov25_auto_carousel', 'no' ) === 'yes',
			'deferThreeD' => get_option( 'ov25_defer_3d', 'yes' ) === 'yes',
			'images' => function_exists( 'wc_get_product' ) ? ov25_get_product_images() : array(),
		) );

		// Enqueue frontend CSS
		wp_enqueue_style(
			'ov25-frontend-styles',
			plugins_url( 'build/frontend.css', __FILE__ ),
			[],
			filemtime( plugin_dir_path( __FILE__ ) . 'build/frontend.css' )
		);
	}, 20 );

    add_action( 'wp_enqueue_scripts', function () {
        if ( ! is_product() ) {
            return;
        }
    
        /* 1. register Woo's core style as a "handle" we can piggy-back on */
        wp_register_style( 'ov25-dummy', false );
        wp_enqueue_style(  'ov25-dummy' );  // prints a <style id="ov25-dummy-css"> block
    
        /* 2. inject our rules right after */
        $css = '
            @keyframes ov25-flash { 0%,100%{opacity:.35} 50%{opacity:.15} }
            .ov25-price-skeleton{
                display:inline-block;
                min-width:5ch;
                height:1em;
                border-radius:4px;
                background:#e2e2e2;
                animation:ov25-flash 1s linear infinite;
            }
        ';
        wp_add_inline_style( 'ov25-dummy', $css );
    }, 18 );

    add_action( 'woocommerce_checkout_create_order_line_item', function ( $item, $cart_item_key, $values ) {
        if ( ! empty( $values['cfg_payload'] ) ) {
            $item->add_meta_data( 'Configurator Data', $values['cfg_payload'] );
        }
    }, 10, 3 );

    /* 1. keep the cart-item fields that have already been  added */
    add_filter( 'woocommerce_add_cart_item_data', function ( $item, $product_id ) {
        foreach ( [ 'cfg_price', 'cfg_payload', 'cfg_sku' ] as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                $item[ $key ] = wc_clean( wp_unslash( $_POST[ $key ] ) );
            }
        }
        return $item;
    }, 10, 2 );
    

    add_action( 'woocommerce_checkout_create_order_line_item', function ( $item, $cart_item_key, $values ) {
        if ( ! empty( $values['cfg_sku'] ) ) {
            $item->add_meta_data( 'SKU', $values['cfg_sku'], true );
        }
        if ( ! empty( $values['cfg_payload'] ) ) {
            $item->add_meta_data( 'Configurator Data', $values['cfg_payload'], true );
        }
    }, 10, 3 );
    
    // Display Configurator SKU under the product name in mini-cart & cart table
    add_filter( 'woocommerce_get_item_data', function ( $item_data, $cart_item ) {
        if ( ! empty( $cart_item['cfg_sku'] ) ) {
            $item_data[] = array(
                'key'     => __( 'SKU', 'ov25-woo-extension' ),
                'value'   => esc_html( $cart_item['cfg_sku'] ),
                'display' => '', // leave blank so Woo just shows the value
            );
        }
        return $item_data;
    }, 10, 2 );
    
    add_action( 'woocommerce_before_calculate_totals', function ( $cart ) {

        foreach ( $cart->get_cart() as $ci ) {
    
            if ( empty( $ci['cfg_price'] ) ) {
                continue;                         // skip normal products
            }
    
            $price_major = $ci['cfg_price'] / 100;   // e.g. 120000 → 1200.00
    
            $product = $ci['data'];                 // WC_Product object
    
            /* 1. set the runtime price Woo uses for totals */
            $product->set_price( $price_major );
    
            /* 2. align regular & sale prices so Woo doesn't think it's a discount */
            $product->set_regular_price( $price_major );
            $product->set_sale_price( '' );         // clear any sale flag
        }
    }, 1000 );
    

    
    
}

/**
 * Get product images for the current product.
 *
 * @return array Array of image URLs
 */
function ov25_get_product_images() {
	if ( ! is_product() ) {
		return array();
	}

	$product = wc_get_product();
	if ( ! $product ) {
		return array();
	}

	$images = array();
	
	// Get main product image
	$main_image_id = $product->get_image_id();
	if ( $main_image_id ) {
		$main_image_url = wp_get_attachment_image_url( $main_image_id, 'full' );
		if ( $main_image_url ) {
			$images[] = $main_image_url;
		}
	}

	// Get gallery images
	$gallery_image_ids = $product->get_gallery_image_ids();
	foreach ( $gallery_image_ids as $image_id ) {
		$image_url = wp_get_attachment_image_url( $image_id, 'full' );
		if ( $image_url ) {
			$images[] = $image_url;
		}
	}

	return $images;
}

/**
 * Add Ov25 settings tab to WooCommerce.
 */
function ov25_woo_extension_add_settings( $settings ) {
	// Include settings page class
	include_once dirname( __FILE__ ) . '/includes/class-wc-settings-ov25.php';
	
	// Add settings tab
	$settings[] = new WC_Settings_Ov25();
	
	return $settings;
}


