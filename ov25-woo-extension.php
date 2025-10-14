<?php
/**
 * Plugin Name: OV25
 * Description: Show off your product catalogue in 3D, with the worlds most advanced product configurator. Inifinite variations, infinite possibilities.
 * Version: 0.3.7
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

	// Ensure Swatches page exists and flush permalinks so /swatches works immediately
	if ( function_exists( 'ov25_ensure_swatches_page_exists' ) ) {
		ov25_ensure_swatches_page_exists();
	}
	if ( function_exists( 'flush_rewrite_rules' ) ) {
		flush_rewrite_rules();
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
		public $version = '0.3.7';

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

// Ensure Swatches page exists even if WooCommerce is inactive in this environment
add_action( 'init', 'ov25_ensure_swatches_page_exists' );

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

		$swatches_hook_file = __DIR__ . '/includes/class-swatches-hook.php';
		if ( file_exists( $swatches_hook_file ) ) {
			include_once $swatches_hook_file;
			if ( class_exists( 'OV25_Swatches_Hook' ) ) {
				OV25_Swatches_Hook::init();
			}
		}

		// Load Swatches API and Page
		$swatch_api_file = __DIR__ . '/includes/class-swatch-api.php';
		if ( file_exists( $swatch_api_file ) ) {
			include_once $swatch_api_file;
			if ( class_exists( 'OV25_Swatch_API' ) ) {
				OV25_Swatch_API::init();
			}
		}

		$swatch_page_file = __DIR__ . '/includes/class-swatch-page.php';
		if ( file_exists( $swatch_page_file ) ) {
			include_once $swatch_page_file;
			if ( class_exists( 'OV25_Swatch_Page' ) ) {
				OV25_Swatch_Page::init();
			}
		}

		// Create swatch product on plugin activation
		add_action( 'init', 'ov25_ensure_swatch_product_exists' );

		// Ensure a public Swatches page exists at /swatches/
		add_action( 'init', 'ov25_ensure_swatches_page_exists' );

		// If permalinks are plain, make /swatches redirect to the page_id URL
		add_action( 'template_redirect', 'ov25_swatches_plain_permalink_redirect' );

		// AJAX handler for creating swatch-only cart
		add_action( 'wp_ajax_ov25_create_swatch_cart', 'ov25_ajax_create_swatch_cart' );
		add_action( 'wp_ajax_nopriv_ov25_create_swatch_cart', 'ov25_ajax_create_swatch_cart' );

		// Auto-restore original cart if user navigates away from Checkout (preserve main cart)
		add_action( 'template_redirect', 'ov25_maybe_restore_original_cart_on_navigation' );

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
					'showOptional' => get_option( 'ov25_show_optional', 'no' ) === 'yes',
					'hideAr' => get_option( 'ov25_hide_ar', 'no' ) === 'yes',
					'images' => function_exists( 'wc_get_product' ) ? ov25_get_product_images() : array(),
					'gallerySelector' => get_option( 'ov25_gallery_selector', '' ),
					'variantsSelector' => get_option( 'ov25_variants_selector', '' ),
					'swatchesSelector' => get_option( 'ov25_swatches_selector', '' ),
					'priceSelector' => get_option( 'ov25_price_selector', '' ),
					'customCSS' => get_option( 'ov25_custom_css', '' ),
					'swatchProductId' => ov25_get_swatch_product_id(),
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
		
				/* Register dummy style for price skeleton animations */
				wp_register_style( 'ov25-dummy', false );
				wp_enqueue_style( 'ov25-dummy' );
		
				/* Inject price skeleton styles */
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
			} catch ( Exception $e ) {
				error_log( 'OV25 Woo Extension: Error adding inline styles - ' . $e->getMessage() );
			}
		}, 18 );

		/* 1. keep the cart-item fields that have already been  added */
		add_filter( 'woocommerce_add_cart_item_data', function ( $item, $product_id ) {
			try {
				foreach ( [ 'cfg_price', 'cfg_payload', 'cfg_sku', 'cfg_skumap', 'ov25-thumbnail' ] as $key ) {
					if ( isset( $_POST[ $key ] ) ) {
						$item[ $key ] = wc_clean( wp_unslash( $_POST[ $key ] ) );
					}
				}
				
				// Check if this is an OV25 product and try to generate thumbnail
				$product = wc_get_product( $product_id );
				if ( $product && $product->get_meta( '_ov25_product_id', true ) && empty( $item['ov25-thumbnail'] ) ) {
					// Add a flag to indicate we need a thumbnail
					$item['ov25-needs-thumbnail'] = true;
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
				if ( ! empty( $values['cfg_skumap'] ) ) {
					$sku_map = json_decode( $values['cfg_skumap'], true );
					if ( is_array( $sku_map ) ) {
						foreach ( $sku_map as $key => $value ) {
							if ( ! in_array( $key, [ 'Ranges', 'Products', 'Range', 'Product' ], true ) ) {
								$item->add_meta_data( $key, $value, true );
							}
						}
					}
				}
        
				// Add swatch data to order
				if ( ! empty( $values['swatch_name'] ) ) {
					$item->add_meta_data( $values['swatch_option'], $values['swatch_name'], true );
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
				
				// Display individual skuMap items (excluding Range and Product)
				if ( ! empty( $cart_item['cfg_skumap'] ) ) {
					$sku_map = json_decode( $cart_item['cfg_skumap'], true );
					if ( is_array( $sku_map ) ) {
						foreach ( $sku_map as $key => $value ) {
							if ( ! in_array( $key, [ 'Range', 'Product', 'Ranges', 'Products' ], true ) ) {
								$item_data[] = array(
									'key'     => esc_html( $key ),
									'value'   => esc_html( $value ),
									'display' => '',
								);
							}
						}
					}
				}
				
				// Display swatch information as a single line: swatch_option: swatch_name
				if ( ! empty( $cart_item['swatch_name'] ) || ! empty( $cart_item['swatch_option'] ) ) {
					$item_data[] = array(
						'key'     => esc_html( $cart_item['swatch_option'] ?? __( 'Swatch', 'ov25-woo-extension' ) ),
						'value'   => esc_html( $cart_item['swatch_name'] ?? '' ),
						'display' => '',
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
				
				// Handle swatch pricing
				if ( ! empty( $cart_item['swatch_price'] ) ) {
					$swatch_price = floatval( $cart_item['swatch_price'] );
					
					if ( $swatch_price > 0 ) {
						$args = array( 'price' => $swatch_price );
						
						if ( WC()->cart->display_prices_including_tax() ) {
							$product_price = wc_get_price_including_tax( $cart_item['data'], $args );
						} else {
							$product_price = wc_get_price_excluding_tax( $cart_item['data'], $args );
						}
						
						return wc_price( $product_price );
					} else {
						return __( 'Free', 'ov25-woo-extension' );
					}
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
		
					// Handle configurator products
					if ( ! empty( $ci['cfg_price'] ) ) {
						$price_major = $ci['cfg_price'] / 100;   // e.g. 120000 → 1200.00
		
						$product = $ci['data'];                 // WC_Product object
		
						/* 1. set the runtime price Woo uses for totals */
						$product->set_price( $price_major );
		
						/* 2. align regular & sale prices so Woo doesn't think it's a discount */
						$product->set_regular_price( $price_major );
						$product->set_sale_price( '' );         // clear any sale flag
					}
					
					// Handle swatch products
					if ( ! empty( $ci['swatch_price'] ) ) {
						$swatch_price = floatval( $ci['swatch_price'] );
						$product = $ci['data'];
						
						/* 1. set the runtime price Woo uses for totals */
						$product->set_price( $swatch_price );
						
						/* 2. align regular & sale prices so Woo doesn't think it's a discount */
						$product->set_regular_price( $swatch_price );
						$product->set_sale_price( '' );         // clear any sale flag
					}
				}
			} catch ( Exception $e ) {
				error_log( 'OV25 Woo Extension: Error in before calculate totals - ' . $e->getMessage() );
			}
		}, 1000 );

		// Classic templates (cart.php, mini-cart widget, e-mails, etc.)
		// Fires after any other thumbnail filters so our override "wins"
		function ov25_override_classic_thumb( $thumb, $item, $key = null ) {
			try {
				if ( empty( $item['ov25-thumbnail'] ) ) {
					return $thumb;
				}
        $src = esc_url( $item['ov25-thumbnail'] );
        $alt = '';
        if ( isset( $item['data'] ) && is_object( $item['data'] ) && method_exists( $item['data'], 'get_name' ) ) {
            $alt = esc_attr( $item['data']->get_name() );
        }

				return "<img src='{$src}' alt='{$alt}' />";
			} catch ( Exception $e ) {
				error_log( 'OV25 Woo Extension: Error in classic thumbnail override - ' . $e->getMessage() );
				return $thumb;
			}
		}
		add_filter( 'woocommerce_cart_item_thumbnail', 'ov25_override_classic_thumb', 100, 3 );
		add_filter( 'woocommerce_checkout_item_thumbnail', 'ov25_override_classic_thumb', 100, 3 );
		add_filter( 'woocommerce_order_item_thumbnail', 'ov25_override_classic_thumb', 100, 3 );

		// Cart & Checkout Blocks (Store API) – WooCommerce ≥ 9.6
		// Swaps the image object in the API response
		add_filter(
			'woocommerce_store_api_cart_item_images',
			function ( $images, $cart_item, $cart_item_key ) {
				try {
					if ( empty( $cart_item['ov25-thumbnail'] ) ) {
						return $images; // nothing to do
					}

					$src = esc_url( $cart_item['ov25-thumbnail'] );

					return [
						(object) [
							'id'        => 0,
							'src'       => $src,
							'thumbnail' => $src,
							'srcset'    => '',
							'sizes'     => '',
							'name'      => '',
							'alt'       => '',
						],
					];
				} catch ( Exception $e ) {
					error_log( 'OV25 Woo Extension: Error in store API cart item images - ' . $e->getMessage() );
					return $images;
				}
			},
			100,   // run after anything else
			3
		);

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

/**
 * Ensure swatch product exists for cart operations.
 */
function ov25_ensure_swatch_product_exists() {
	// Check if swatch product already exists
	$swatch_product_id = get_option( 'ov25_swatch_product_id' );
	
	if ( $swatch_product_id && wc_get_product( $swatch_product_id ) ) {
		return $swatch_product_id;
	}
	
	// Create swatch product if it doesn't exist
	$product = new WC_Product_Simple();
	$product->set_name( 'Swatch' );
	$product->set_status( 'private' ); // Hidden from catalog
	$product->set_catalog_visibility( 'hidden' );
	$product->set_price( 0 );
	$product->set_regular_price( 0 );
    // Swatches need delivery, so product must not be virtual
    $product->set_virtual( false );
	$product->set_meta_data( '_ov25_swatch_product', 'yes' );
	
	$product_id = $product->save();
	
	if ( $product_id ) {
		update_option( 'ov25_swatch_product_id', $product_id );
		return $product_id;
	}
	
	return false;
}

/** Get the swatch product ID. */
function ov25_get_swatch_product_id() {
    return get_option( 'ov25_swatch_product_id' );
}

/**
 * Ensure a Swatches page exists at the configured slug containing the shortcode.
 */
function ov25_ensure_swatches_page_exists() {
    try {
        // Check if swatches page is enabled
        $show_swatches_page = get_option( 'ov25_show_swatches_page', 'no' );
        $page_slug = sanitize_title( get_option( 'ov25_swatches_page_slug', 'swatches' ) );
        $page_title = sanitize_text_field( get_option( 'ov25_swatches_page_title', 'Swatches' ) );
        $show_in_nav = get_option( 'ov25_swatches_show_in_nav', 'no' );
        
        $page_id = (int) get_option( 'ov25_swatches_page_id', 0 );
        
        if ( $show_swatches_page === 'yes' ) {
            // Swatches page should be visible
            $needs_create = true;
            $needs_update = false;

            if ( $page_id > 0 ) {
                $page = get_post( $page_id );
                if ( $page && $page->post_status !== 'trash' ) {
                    // Check if the page slug, title, and visibility match the current settings
                    $current_visibility = $show_in_nav === 'yes' ? 'publish' : 'private';
                    if ( $page->post_name === $page_slug && $page->post_title === $page_title && $page->post_status === $current_visibility ) {
                        $needs_create = false;
                    } else {
                        // Slug, title, or visibility changed, update the existing page
                        $update_data = [ 'ID' => $page_id ];
                        if ( $page->post_name !== $page_slug ) {
                            $update_data['post_name'] = $page_slug;
                        }
                        if ( $page->post_title !== $page_title ) {
                            $update_data['post_title'] = $page_title;
                        }
                        if ( $page->post_status !== $current_visibility ) {
                            $update_data['post_status'] = $current_visibility;
                        }
                        wp_update_post( $update_data );
                        $needs_create = false;
                    }
                }
            }

            if ( $needs_create ) {
                // Check if a page with this slug already exists
                $existing = get_page_by_path( $page_slug, OBJECT, 'page' );
                if ( $existing && $existing->post_status !== 'trash' ) {
                    update_option( 'ov25_swatches_page_id', $existing->ID );
                    return;
                }

                $page_status = $show_in_nav === 'yes' ? 'publish' : 'private';
                $page_id = wp_insert_post( [
                    'post_title'   => $page_title,
                    'post_name'    => $page_slug,
                    'post_status'  => $page_status,
                    'post_type'    => 'page',
                    'post_content' => '[ov25_swatches]',
                ] );

                if ( $page_id && ! is_wp_error( $page_id ) ) {
                    update_option( 'ov25_swatches_page_id', $page_id );
                }
            }
        } else {
            // Swatches page should be hidden - move to trash if it exists
            if ( $page_id > 0 ) {
                $page = get_post( $page_id );
                if ( $page && $page->post_status !== 'trash' ) {
                    wp_trash_post( $page_id );
                }
            }
        }
    } catch ( Exception $e ) {
        error_log( 'OV25 Woo Extension: Error ensuring Swatches page - ' . $e->getMessage() );
    }
}

/**
 * If permalinks are set to Plain (/?page_id=), redirect the swatches page URL to the page's canonical link.
 */
function ov25_swatches_plain_permalink_redirect() {
    if ( ! function_exists( 'get_option' ) ) return;
    
    // Check if swatches page is enabled
    $show_swatches_page = get_option( 'ov25_show_swatches_page', 'no' );
    if ( $show_swatches_page !== 'yes' ) return;
    
    $structure = get_option( 'permalink_structure' );
    if ( ! empty( $structure ) ) return; // Pretty permalinks enabled

    $page_slug = sanitize_title( get_option( 'ov25_swatches_page_slug', 'swatches' ) );
    
    // Only act on exact /{page_slug} (with or without trailing slash)
    $req_uri = isset( $_SERVER['REQUEST_URI'] ) ? strtok( $_SERVER['REQUEST_URI'], '?' ) : '';
    if ( ! in_array( rtrim( $req_uri, '/' ), [ '/' . $page_slug ], true ) ) return;

    $page_id = (int) get_option( 'ov25_swatches_page_id', 0 );
    if ( $page_id <= 0 ) return;
    $link = get_permalink( $page_id );
    if ( ! $link ) return;

    // If permalink structure is plain, get_permalink will already be ?page_id=...
    wp_safe_redirect( $link, 301 );
    exit;
}

/**
 * AJAX handler for creating swatch-only cart.
 */
function ov25_ajax_create_swatch_cart() {
	try {
		// Get swatch data from request
		$swatch_data = isset( $_POST['swatch_data'] ) ? json_decode( wp_unslash( $_POST['swatch_data'] ), true ) : null;
		
		if ( ! $swatch_data ) {
			wp_send_json_error( 'No swatch data provided' );
			return;
		}

		// Store current cart contents to restore later
		$current_cart = WC()->cart->get_cart();
		WC()->session->set( 'ov25_original_cart', $current_cart );
		
		// Clear the main cart
		WC()->cart->empty_cart();
		
        // Add swatches to the empty cart
        $swatches = $swatch_data['swatches'];
        $rules = $swatch_data['rules'];
        // Resolve swatch product id on the server (create if missing)
        $product_id = ov25_ensure_swatch_product_exists();
		
		foreach ( $swatches as $index => $swatch ) {
			$is_free = $index < $rules['freeSwatchLimit'];
			$swatch_price = $is_free ? 0 : $rules['pricePerSwatch'];
			
			$cart_item_data = array(
				'swatch_manufacturer_id' => $swatch['manufacturerId'],
				'swatch_name' => $swatch['name'],
				'swatch_option' => $swatch['option'],
				'swatch_total_count' => count( $swatches ),
				'swatch_price' => $swatch_price,
				'ov25-thumbnail' => $swatch['thumbnail']['miniThumbnails']['small'],
			);
			
			WC()->cart->add_to_cart( $product_id, 1, 0, array(), $cart_item_data );
		}
		
		// Mark this as a swatch-only cart
		WC()->session->set( 'ov25_swatch_only_cart', true );
		
        // Return the correct checkout URL (respects permalinks/settings)
        $checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' );
        wp_send_json_success( array( 'message' => 'Swatch-only cart created successfully', 'checkout_url' => $checkout_url ) );
		
	} catch ( Exception $e ) {
		error_log( 'OV25 Woo Extension: Error creating swatch-only cart - ' . $e->getMessage() );
		wp_send_json_error( 'Failed to create swatch-only cart: ' . $e->getMessage() );
	}
}

/**
 * If the session indicates we’re in a swatch-only flow and the user visits any
 * non-checkout/non-order-pay page, restore the original cart and clear flags.
 */
function ov25_maybe_restore_original_cart_on_navigation() {
    // Only act if we had swapped carts
    if ( ! WC()->session || ! WC()->session->get( 'ov25_swatch_only_cart' ) ) {
        return;
    }

    // If we are on checkout or pay-for-order, do nothing
    if ( function_exists( 'is_checkout' ) && is_checkout() ) {
        return;
    }
    if ( isset( $_GET['pay_for_order'] ) || isset( $_GET['order-pay'] ) ) { // order pay endpoints
        return;
    }

    // Restore (always clear current cart; re-add original items if any)
    $original_cart = WC()->session->get( 'ov25_original_cart' );
    WC()->cart->empty_cart();
    if ( is_array( $original_cart ) ) {
        foreach ( $original_cart as $item ) {
            $product_id   = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
            $quantity     = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
            $variation_id = isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0;
            $variation    = isset( $item['variation'] ) && is_array( $item['variation'] ) ? $item['variation'] : array();
            $cart_item_data = ov25_extract_cart_item_custom_data( $item );
            WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation, $cart_item_data );
        }
    }
    WC()->session->set( 'ov25_swatch_only_cart', false );
    WC()->session->set( 'ov25_original_cart', null );
}

/**
 * Extract custom cart item data from a saved cart line for re-adding to cart.
 * Copies OV25-specific keys and any non-reserved custom keys.
 */
function ov25_extract_cart_item_custom_data( $item ) {
    if ( ! is_array( $item ) ) {
        return array();
    }

    $reserved_keys = array(
        'key', 'product_id', 'variation_id', 'variation', 'quantity', 'data', 'data_hash',
        'line_total', 'line_tax', 'line_subtotal', 'line_subtotal_tax', 'line_tax_data'
    );

    $cart_item_data = array();

    foreach ( $item as $key => $value ) {
        // Always copy OV25/swatches and cfg_* fields
        if ( strpos( $key, 'cfg_' ) === 0 || strpos( $key, 'swatch_' ) === 0 || strpos( $key, 'ov25-' ) === 0 ) {
            $cart_item_data[ $key ] = $value;
            continue;
        }
        // Copy any other non-reserved scalar/array custom fields
        if ( ! in_array( $key, $reserved_keys, true ) ) {
            $cart_item_data[ $key ] = $value;
        }
    }

    return $cart_item_data;
}





