<?php
/**
 * Plugin Name: OV25
 * Description: Show off your product catalogue in 3D, with the worlds most advanced product configurator. Inifinite variations, infinite possibilities.
 * Version: .0.1.66
 * Author: Orbital Vision
 * Author URI: https://ov25.orbitalvision.com
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

/*
// Kill switch - Check API endpoint before loading plugin
if ( ! function_exists( 'ov25_check_kill_switch' ) ) {
	function ov25_check_kill_switch() {
		// Only check kill switch in admin to prevent frontend impact
		if ( ! is_admin() ) {
			return true;
		}
		
		// Check transient first to avoid repeated API calls
		$kill_switch_status = get_transient( 'ov25_kill_switch_status' );
		if ( $kill_switch_status !== false ) {
			return $kill_switch_status === 'active';
		}
		
		// API endpoint to check
		$api_url = 'https://webhooks.orbital.vision/api/woo-commerce/kill-switch';
		
		// Make the API request with short timeout
		$response = wp_remote_get( $api_url, array(
			'timeout' => 3,
			'headers' => array(
				'User-Agent' => 'OV25-Plugin/' . '0.1.33',
			),
		) );
		
		// Default to active if API is unreachable (fail-safe)
		$is_active = true;
		
		if ( ! is_wp_error( $response ) ) {
			$status_code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			
			if ( $status_code === 200 ) {
				$data = json_decode( $body, true );
				// Expect response like: {"status": "active"} or {"status": "disabled"}
				if ( isset( $data['status'] ) ) {
					$is_active = ( $data['status'] === 'active' );
				}
			} elseif ( $status_code === 503 || $status_code === 423 ) {
				// 503 Service Unavailable or 423 Locked = kill switch activated
				$is_active = false;
			}
		}
		
		// Cache the result for 5 minutes to avoid repeated API calls
		set_transient( 'ov25_kill_switch_status', $is_active ? 'active' : 'disabled', 5 * 60 );
		
		return $is_active;
	}
}

// Check kill switch and exit early if disabled
if ( ! ov25_check_kill_switch() ) {
	// Log the kill switch activation
	error_log( 'OV25 Woo Extension: Plugin disabled via kill switch' );
	
	// Show admin notice if in admin
	if ( is_admin() ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-warning"><p><strong>OV25 Plugin:</strong> Plugin temporarily disabled for maintenance.</p></div>';
		} );
	}
	
	// Exit early - don't load the rest of the plugin
	return;
}
*/

if ( ! defined( 'MAIN_PLUGIN_FILE' ) ) {
	define( 'MAIN_PLUGIN_FILE', __FILE__ );
}

// Load Plugin Update Checker directly
$puc_file = dirname( __FILE__ ) . '/includes/plugin-update-checker/plugin-update-checker.php';
if ( file_exists( $puc_file ) ) {
	try {
		require_once $puc_file;
		
		if ( class_exists( 'YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
			$ov25UpdateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
				'https://github.com/orbitalvision/ov25-woo-extension/',
				__FILE__,
				'ov25-woo-extension'
			);
            $ov25UpdateChecker->getVcsApi()->enableReleaseAssets('/ov25-woo-extension\.zip($|[?&#])/i');
			// Load GitHub token securely (only if file exists and not in production)
			$token_file = dirname( __FILE__ ) . '/github-token.php';
			if ( file_exists( $token_file ) ) {
				try {
					include_once $token_file;
					if ( defined( 'OV25_GITHUB_TOKEN' ) ) {
						$ov25UpdateChecker->setAuthentication( OV25_GITHUB_TOKEN );
					}
				} catch ( Exception $e ) {
					error_log( 'OV25 Woo Extension: Error loading GitHub token - ' . $e->getMessage() );
				}
			}
		}
	} catch ( Exception $e ) {
		error_log( 'OV25 Woo Extension: Error loading update checker - ' . $e->getMessage() );
	}
}

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
		 * Plugin version.
		 *
		 * @var string
		 */
		public $version = '.0.1.66';

		/**
		 * Constructor.
		 */
		public function __construct() {
			try {
				if ( is_admin() ) {
					$setup_file = dirname( __FILE__ ) . '/includes/admin/setup.php';
					if ( file_exists( $setup_file ) ) {
						include_once $setup_file;
						if ( class_exists( 'Ov25WooExtension\Admin\Setup' ) ) {
							new \Ov25WooExtension\Admin\Setup();
						}
					}
				}
			} catch ( Exception $e ) {
				error_log( 'OV25 Woo Extension: Error in constructor - ' . $e->getMessage() );
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
	try {
		load_plugin_textdomain( 'ov25_woo_extension', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', 'ov25_woo_extension_missing_wc_notice' );
			return;
		}

		ov25_woo_extension::instance();
		
		// Add settings tab
		add_filter( 'woocommerce_get_settings_pages', 'ov25_woo_extension_add_settings' );
		
		// Load product field with error handling
		$product_field_file = dirname( __FILE__ ) . '/includes/class-product-field.php';
		if ( file_exists( $product_field_file ) ) {
			include_once $product_field_file;
			if ( class_exists( 'OV25_Product_Field' ) ) {
				OV25_Product_Field::init();
			}
		}

		// Load gallery hooks with error handling
		$gallery_hooks_file = dirname( __FILE__ ) . '/includes/class-gallery-hooks.php';
		if ( file_exists( $gallery_hooks_file ) ) {
			include_once $gallery_hooks_file;
			if ( class_exists( 'OV25_Gallery_Hooks' ) ) {
				OV25_Gallery_Hooks::init();
			}
		}

		// Load price hook with error handling
		$price_hook_file = dirname( __FILE__ ) . '/includes/class-price-hook.php';
		if ( file_exists( $price_hook_file ) ) {
			include_once $price_hook_file;
			if ( class_exists( 'OV25_Price_Hook' ) ) {
				OV25_Price_Hook::init();
			}
		}

		// Load variant hook with error handling
		$variant_hook_file = __DIR__ . '/includes/class-variant-hook.php';
		if ( file_exists( $variant_hook_file ) ) {
			include_once $variant_hook_file;
			if ( class_exists( 'OV25_Variant_Hook' ) ) {
				OV25_Variant_Hook::init();
			}
		}

		// Load loop button hook with error handling
		$loop_button_file = dirname( __FILE__ ) . '/includes/class-loop-button-hook.php';
		if ( file_exists( $loop_button_file ) ) {
			include_once $loop_button_file;
			if ( class_exists( 'OV25_Loop_Button_Hook' ) ) {
				OV25_Loop_Button_Hook::init();
			}
		}

		// Enqueue frontend scripts with error handling
		add_action( 'wp_enqueue_scripts', function () {
			try {
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

				$asset_file = plugin_dir_path( __FILE__ ) . 'build/frontend.asset.php';
				$asset = array( 'dependencies' => array(), 'version' => '1.0.0' );
				
				if ( file_exists( $asset_file ) ) {
					$asset = include $asset_file;
				}

				$js_file = plugin_dir_path( __FILE__ ) . 'build/frontend.js';
				if ( file_exists( $js_file ) ) {
					wp_enqueue_script(
						'ov25-frontend',
						plugins_url( 'build/frontend.js', __FILE__ ),
						$asset['dependencies'] ?? [],
						$asset['version'] ?? filemtime( $js_file ),
						true
					);

					// Pass OV25 settings to frontend
					wp_localize_script( 'ov25-frontend', 'ov25Settings', array(
						'logoURL' => get_option( 'ov25_logo_url', '' ),
						'mobileLogoURL' => get_option( 'ov25_mobile_logo_url', '' ),
						'autoCarousel' => get_option( 'ov25_auto_carousel', 'no' ) === 'yes',
						'deferThreeD' => get_option( 'ov25_defer_3d', 'yes' ) === 'yes',
						'images' => function_exists( 'wc_get_product' ) ? ov25_get_product_images() : array(),
					) );
				}

				// Enqueue frontend CSS
				$css_file = plugin_dir_path( __FILE__ ) . 'build/frontend.css';
				if ( file_exists( $css_file ) ) {
					wp_enqueue_style(
						'ov25-frontend-styles',
						plugins_url( 'build/frontend.css', __FILE__ ),
						[],
						filemtime( $css_file )
					);
				}
			} catch ( Exception $e ) {
				error_log( 'OV25 Woo Extension: Error enqueuing frontend scripts - ' . $e->getMessage() );
			}
		}, 20 );

		add_action( 'wp_enqueue_scripts', function () {
			try {
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
				
				// Add custom CSS from settings
				$custom_css = get_option( 'ov25_custom_css', '' );
				if ( ! empty( trim( $custom_css ) ) ) {
					$css .= "\n/* OV25 Custom CSS */\n" . $custom_css;
				}
				
				wp_add_inline_style( 'ov25-dummy', $css );
			} catch ( Exception $e ) {
				error_log( 'OV25 Woo Extension: Error adding inline styles - ' . $e->getMessage() );
			}
		}, 18 );

		add_action( 'woocommerce_checkout_create_order_line_item', function ( $item, $cart_item_key, $values ) {
			try {
				if ( ! empty( $values['cfg_payload'] ) ) {
					$item->add_meta_data( 'Configurator Data', $values['cfg_payload'] );
				}
			} catch ( Exception $e ) {
				error_log( 'OV25 Woo Extension: Error in checkout create order line item - ' . $e->getMessage() );
			}
		}, 10, 3 );

		/* 1. keep the cart-item fields that have already been  added */
		add_filter( 'woocommerce_add_cart_item_data', function ( $item, $product_id ) {
			try {
				foreach ( [ 'cfg_price', 'cfg_payload', 'cfg_sku' ] as $key ) {
					if ( isset( $_POST[ $key ] ) ) {
						$item[ $key ] = wc_clean( wp_unslash( $_POST[ $key ] ) );
					}
				}
				return $item;
			} catch ( Exception $e ) {
				error_log( 'OV25 Woo Extension: Error in add cart item data - ' . $e->getMessage() );
				return $item;
			}
		}, 10, 2 );
		

		add_action( 'woocommerce_checkout_create_order_line_item', function ( $item, $cart_item_key, $values ) {
			try {
				if ( ! empty( $values['cfg_sku'] ) ) {
					$item->add_meta_data( 'SKU', $values['cfg_sku'], true );
				}
				if ( ! empty( $values['cfg_payload'] ) ) {
					$item->add_meta_data( 'Configurator Data', $values['cfg_payload'], true );
				}
			} catch ( Exception $e ) {
				error_log( 'OV25 Woo Extension: Error in checkout create order line item (SKU) - ' . $e->getMessage() );
			}
		}, 10, 3 );
		
		// Display Configurator SKU under the product name in mini-cart & cart table
		add_filter( 'woocommerce_get_item_data', function ( $item_data, $cart_item ) {
			try {
				if ( ! empty( $cart_item['cfg_sku'] ) ) {
					$item_data[] = array(
						'key'     => __( 'SKU', 'ov25-woo-extension' ),
						'value'   => esc_html( $cart_item['cfg_sku'] ),
						'display' => '', // leave blank so Woo just shows the value
					);
				}
				return $item_data;
			} catch ( Exception $e ) {
				error_log( 'OV25 Woo Extension: Error in get item data - ' . $e->getMessage() );
				return $item_data;
			}
		}, 10, 2 );
		
		// Display custom price in mini cart for OV25 configured products
		add_filter( 'woocommerce_cart_item_price', function ( $price_html, $cart_item, $cart_item_key ) {
			try {
				if ( ! empty( $cart_item['cfg_price'] ) ) {
					$price_major = $cart_item['cfg_price'] / 100;   // e.g. 120000 → 1200.00
					
					$args = array( 'price' => $price_major );
					
					if ( WC()->cart->display_prices_including_tax() ) {
						$product_price = wc_get_price_including_tax( $cart_item['data'], $args );
					} else {
						$product_price = wc_get_price_excluding_tax( $cart_item['data'], $args );
					}
					
					return wc_price( $product_price );
				}
				return $price_html;
			} catch ( Exception $e ) {
				error_log( 'OV25 Woo Extension: Error in cart item price - ' . $e->getMessage() );
				return $price_html;
			}
		}, 10, 3 );
		
		add_action( 'woocommerce_before_calculate_totals', function ( $cart ) {
			try {
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
			} catch ( Exception $e ) {
				error_log( 'OV25 Woo Extension: Error in before calculate totals - ' . $e->getMessage() );
			}
		}, 1000 );

	} catch ( Exception $e ) {
		error_log( 'OV25 Woo Extension: Error in plugin initialization - ' . $e->getMessage() );
	}
}

/**
 * Get product images for the current product.
 *
 * @return array Array of image URLs
 */
function ov25_get_product_images() {
	try {
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
	} catch ( Exception $e ) {
		error_log( 'OV25 Woo Extension: Error getting product images - ' . $e->getMessage() );
		return array();
	}
}

/**
 * Add Ov25 settings tab to WooCommerce.
 */
function ov25_woo_extension_add_settings( $settings ) {
	try {
		// Include settings page class
		$settings_file = dirname( __FILE__ ) . '/includes/class-wc-settings-ov25.php';
		if ( file_exists( $settings_file ) ) {
			include_once $settings_file;
			
			// Add settings tab
			if ( class_exists( 'WC_Settings_Ov25' ) ) {
				$settings[] = new WC_Settings_Ov25();
			}
		}
		
		return $settings;
	} catch ( Exception $e ) {
		error_log( 'OV25 Woo Extension: Error adding settings - ' . $e->getMessage() );
		return $settings;
	}
}


