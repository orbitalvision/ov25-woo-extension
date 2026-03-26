<?php
/**
 * Activation callback and WooCommerce missing notice.
 *
 * @package extension
 */

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce fallback notice.
 *
 * @since 0.1.0
 */
function ov25_woo_extension_missing_wc_notice() {
	/* translators: %s WC download URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Ov25 Woo Extension requires WooCommerce to be installed and active. You can download %s here.', 'ov25_woo_extension' ), '<a href="https://woo.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

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

	if ( function_exists( 'ov25_ensure_swatches_page_exists' ) ) {
		ov25_ensure_swatches_page_exists();
	}
	if ( function_exists( 'flush_rewrite_rules' ) ) {
		flush_rewrite_rules();
	}
}
