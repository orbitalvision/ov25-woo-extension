<?php
/**
 * Fallback update check using GitHub /releases/latest redirect.
 * No API key or rate limits; uses HEAD request and Location header.
 *
 * @package extension
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve latest release version from GitHub redirect.
 *
 * @return string|false Version string (e.g. "0.3.43") or false on failure.
 */
function ov25_get_latest_version_from_redirect() {
	$cached = get_transient( 'ov25_latest_version' );
	if ( $cached !== false ) {
		return $cached;
	}

	$response = wp_remote_head(
		'https://github.com/orbitalvision/ov25-woo-extension/releases/latest',
		array(
			'redirection' => 0,
			'timeout'     => 10,
		)
	);

	if ( is_wp_error( $response ) ) {
		return false;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$location = wp_remote_retrieve_header( $response, 'location' );

	if ( $code !== 302 || empty( $location ) ) {
		return false;
	}

	$path = wp_parse_url( $location, PHP_URL_PATH );
	$tag  = $path ? basename( $path ) : '';
	$ver  = preg_match( '/^v?([\d.]+)$/', $tag, $m ) ? $m[1] : false;

	if ( $ver ) {
		set_transient( 'ov25_latest_version', $ver, 12 * HOUR_IN_SECONDS );
	}

	return $ver;
}

/**
 * Inject update into site_transient_update_plugins when PUC has not.
 */
function ov25_inject_redirect_fallback_update( $value ) {
	if ( ! $value || ! isset( $value->response ) ) {
		return $value;
	}

	$plugin_file = plugin_basename( defined( 'MAIN_PLUGIN_FILE' ) ? MAIN_PLUGIN_FILE : __FILE__ );
	if ( isset( $value->response[ $plugin_file ] ) ) {
		return $value;
	}

	if ( ! function_exists( 'get_plugin_data' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$plugin_data = get_plugin_data( MAIN_PLUGIN_FILE, false );
	$current     = $plugin_data['Version'] ?? '0';
	$latest      = ov25_get_latest_version_from_redirect();

	if ( ! $latest || version_compare( $latest, $current, '<=' ) ) {
		return $value;
	}

	$value->response[ $plugin_file ] = (object) array(
		'slug'        => 'ov25-woo-extension',
		'plugin'      => $plugin_file,
		'new_version' => $latest,
		'package'     => 'https://github.com/orbitalvision/ov25-woo-extension/releases/latest/download/ov25-woo-extension.zip',
		'url'         => 'https://github.com/orbitalvision/ov25-woo-extension',
		'icons'       => array(),
		'banners'     => array(),
		'banners_rtl' => array(),
		'tested'      => '',
		'compatibility' => new stdClass(),
	);

	return $value;
}

add_filter( 'site_transient_update_plugins', 'ov25_inject_redirect_fallback_update', 20 );
