<?php
/**
 * One-time migration from legacy wp_options to JSON configurator config.
 *
 * @package extension
 */

defined( 'ABSPATH' ) || exit;

/**
 * Migrate legacy individual wp_options to the new JSON config format.
 * Runs once per plugin update; old options are kept for backward compat.
 */
function ov25_maybe_migrate_legacy_config() {
	$migrated = get_option( 'ov25_config_migrated', 'no' );
	if ( $migrated === 'yes' ) {
		return;
	}

	$existing = get_option( 'ov25_configurator_config', '' );
	if ( ! empty( $existing ) && $existing !== '{}' ) {
		update_option( 'ov25_config_migrated', 'yes' );
		return;
	}

	$gallery_sel   = get_option( 'ov25_gallery_selector', '' );
	$variants_sel  = get_option( 'ov25_variants_selector', '' );
	$price_sel     = get_option( 'ov25_price_selector', '' );
	$swatches_sel  = get_option( 'ov25_swatches_selector', '' );
	$configure_sel = get_option( 'ov25_configure_button_selector', '' );

	$standard = array(
		'selectors'    => array(),
		'carousel'     => array(
			'desktop' => get_option( 'ov25_auto_carousel', 'no' ) === 'yes' ? 'carousel' : 'stacked',
			'mobile'  => 'carousel',
		),
		'configurator' => array(
			'displayMode' => array(
				'desktop' => get_option( 'ov25_use_inline_variant_controls', 'no' ) === 'yes' ? 'inline' : 'sheet',
				'mobile'  => 'drawer',
			),
		),
		'flags'        => array(
			'deferThreeD'  => get_option( 'ov25_defer_3d', 'yes' ) === 'yes',
			'showOptional' => get_option( 'ov25_show_optional', 'no' ) === 'yes',
			'hideAr'       => get_option( 'ov25_hide_ar', 'no' ) === 'yes',
		),
	);

	if ( $gallery_sel ) {
		$standard['selectors']['gallery'] = array( 'selector' => $gallery_sel, 'replace' => true );
	}
	if ( $variants_sel ) {
		$standard['selectors']['variants'] = $variants_sel;
	}
	if ( $price_sel ) {
		$standard['selectors']['price'] = $price_sel;
	}
	if ( $swatches_sel ) {
		$standard['selectors']['swatches'] = $swatches_sel;
	}
	if ( $configure_sel ) {
		$standard['selectors']['configureButton'] = array( 'selector' => $configure_sel, 'replace' => true );
	}

	$logo_url    = get_option( 'ov25_logo_url', '' );
	$mobile_logo = get_option( 'ov25_mobile_logo_url', '' );
	$custom_css  = get_option( 'ov25_custom_css', '' );
	if ( $logo_url || $mobile_logo || $custom_css ) {
		$standard['branding'] = array();
		if ( $logo_url ) {
			$standard['branding']['logoURL'] = $logo_url;
		}
		if ( $mobile_logo ) {
			$standard['branding']['mobileLogoURL'] = $mobile_logo;
		}
		if ( $custom_css ) {
			$standard['branding']['cssString'] = $custom_css;
		}
	}

	$config = array( 'standard' => $standard, 'snap2' => $standard );
	update_option( 'ov25_configurator_config', wp_json_encode( $config ) );
	update_option( 'ov25_config_migrated', 'yes' );
}
