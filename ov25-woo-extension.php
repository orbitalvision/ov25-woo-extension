<?php
/**
 * Plugin Name: OV25
 * Description: Show off your product catalogue in 3D, with the worlds most advanced product configurator. Inifinite variations, infinite possibilities.
 * Version: 1.0.7
 * Author: Orbital Vision
 * Author URI: https://ov25.orbital.vision
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

if ( ! defined( 'OV25_VERSION' ) ) {
	define( 'OV25_VERSION', '0.3.47' );
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

			// GitHub API requires a User-Agent -  missing/invalid one can cause 403 even when not rate-limited.
			add_filter( 'puc_request_info_options-ov25-woo-extension', function ( $options ) {
				if ( ! is_array( $options ) ) {
					return $options;
				}
				$headers = isset( $options['headers'] ) && is_array( $options['headers'] ) ? $options['headers'] : array();
				$options['headers'] = array_merge( $headers, array(
					'User-Agent' => 'OV25-WooExtension-UpdateCheck (WordPress)',
					'Accept'      => 'application/vnd.github.v3+json',
				) );
				return $options;
			} );

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

			// Public plugin: without a token, GitHub often returns 403 (rate limit). Show a soft message instead of API errors.
			if ( ! file_exists( $token_file ) ) {
				add_filter( 'puc_manual_check_message-ov25-woo-extension', function ( $message, $status ) {
					if ( $status !== 'error' ) {
						return $message;
					}
					$url = 'https://github.com/orbitalvision/ov25-woo-extension/releases/latest';
					return sprintf(
						/* translators: 1: opening link tag, 2: closing link tag */
						__( 'Update check is temporarily unavailable. You can download the latest release from %1$sthe plugin\'s GitHub releases page%2$s.', 'ov25-woo-extension' ),
						'<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">',
						'</a>'
					);
				}, 10, 2 );
			}
		}
	} catch ( Exception $e ) {
		error_log( 'OV25 Woo Extension: Error loading update checker - ' . $e->getMessage() );
	}
}

require_once dirname( __FILE__ ) . '/includes/ov25-github-release-fallback.php';

require_once dirname( __FILE__ ) . '/includes/functions/load.php';
require_once dirname( __FILE__ ) . '/includes/ov25-activation.php';
require_once dirname( __FILE__ ) . '/includes/class-ov25-plugin.php';
require_once dirname( __FILE__ ) . '/includes/ov25-plugin-init.php';

// phpcs:disable WordPress.Files.FileName

register_activation_hook( __FILE__, 'ov25_woo_extension_activate' );

add_action( 'plugins_loaded', 'ov25_woo_extension_init', 10 );

// Ensure Swatches page exists even if WooCommerce is inactive in this environment
add_action( 'init', 'ov25_ensure_swatches_page_exists' );

