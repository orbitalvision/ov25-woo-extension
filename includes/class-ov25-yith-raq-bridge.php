<?php
/**
 * OV25 × YITH Request a Quote — bridge.
 *
 * Writes OV25 cfg data into each quote row's `variations` array so YITH's
 * request-quote template renders Key: Value rows the same way as variation attributes.
 *
 * Hooks YITH filters (yith_ywraq_add_item_data, etc.) to ensure configurator
 * metadata is preserved in the quote list.
 *
 * @package extension
 */

defined( 'ABSPATH' ) || exit;

/**
 * Capture and display cfg_* metadata for YITH Request a Quote items.
 */
class OV25_YITH_RAQ_Bridge {

	/**
	 * POST keys to copy — keep in sync with OV25_Ajax_Cart::ALLOWED_CART_ITEM_KEYS and
	 * woocommerce_add_cart_item_data in ov25-plugin-init.php.
	 */
	const CFG_KEYS = array(
		'cfg_sku',
		'cfg_skumap',
		'cfg_price',
		'cfg_payload',
		'cfg_commerce_mode',
		'ov25-thumbnail',
	);

	/**
	 * Skumap labels skipped in woocommerce_get_item_data — same exclusion list.
	 */
	const SKUMAP_SKIP = array( 'Range', 'Product', 'Ranges', 'Products' );

	/**
	 * @var array<string, mixed>
	 */
	private static $captured = array();

	/**
	 * Register hooks.
	 */
	public static function init() {
		self::log( 'init() called - using discovered YITH hooks' );

		// Capture data before it's added.
		add_filter( 'ywraq_ajax_add_item_is_valid', array( __CLASS__, 'capture_posted_data' ), 10, 2 );

		// Patch the session after it's updated.
		add_action( 'yith_raq_updated', array( __CLASS__, 'maybe_patch_session' ) );

		// Display data in the quote list view.
		add_filter( 'ywraq_request_quote_view_item_data', array( __CLASS__, 'add_display_rows' ), 10, 3 );
	}

	/**
	 * Captures the POST data into a static property so we can use it in the action hook later.
	 *
	 * @param bool $is_valid   Original validity.
	 * @param int  $product_id Product ID.
	 * @return bool
	 */
	public static function capture_posted_data( $is_valid, $product_id ) {
		if ( $is_valid ) {
			self::log( 'capture_posted_data() called for product ' . $product_id );
			$cfg = array();
			foreach ( self::CFG_KEYS as $key ) {
				if ( isset( $_POST[ $key ] ) && '' !== $_POST[ $key ] ) {
					$cfg[ $key ] = self::sanitize_cfg( $key, wp_unslash( $_POST[ $key ] ) );
				}
			}
			if ( ! empty( $cfg ) ) {
				self::$captured = array(
					'product_id' => $product_id,
					'data'       => $cfg,
				);
				self::log( 'Captured ' . count( $cfg ) . ' keys' );
			}
		}
		return $is_valid;
	}

	/**
	 * Patches the session if we have captured data.
	 */
	public static function maybe_patch_session() {
		if ( empty( self::$captured ) ) {
			return;
		}

		self::log( 'maybe_patch_session() fired' );

		$rq  = YITH_Request_Quote();
		$raq = $rq->get_raq_for_session();

		// We don't have variation_id here easily, so we try simple first.
		$item_key = md5( (string) self::$captured['product_id'] );

		if ( isset( $raq[ $item_key ] ) ) {
			self::log( "Patching item {$item_key} in session" );
			foreach ( self::$captured['data'] as $k => $v ) {
				$raq[ $item_key ][ $k ] = $v;
			}

			// Clean up captured data to avoid double patching.
			self::$captured = array();

			// Update session (avoid infinite loop by removing action).
			remove_action( 'yith_raq_updated', array( __CLASS__, 'maybe_patch_session' ) );
			$rq->set_session( $raq );
			add_action( 'yith_raq_updated', array( __CLASS__, 'maybe_patch_session' ) );
			self::log( 'Session patched successfully' );
		} else {
			self::log( "Item key {$item_key} not found in session keys: " . implode( ', ', array_keys( $raq ) ) );
		}
	}

	/**
	 * Adds the configuration breakdown to the display array.
	 *
	 * @param array      $data     Existing display rows.
	 * @param array      $raq_item Quote item data.
	 * @param WC_Product $_product Product object.
	 * @return array
	 */
	public static function add_display_rows( $data, $raq_item, $_product ) {
		self::log( 'add_display_rows() called' );
		$extra = self::build_variation_rows( $raq_item );
		if ( ! empty( $extra ) ) {
			foreach ( $extra as $label => $value ) {
				$data[] = array(
					'key'   => $label,
					'value' => $value,
				);
			}
		}
		return $data;
	}

	/**
	 * Directly patches the YITH session with configurator data.
	 */
	public static function patch_session_direct() {
		$action = isset( $_POST['ywraq_action'] ) ? sanitize_text_field( wp_unslash( $_POST['ywraq_action'] ) ) : '';
		if ( 'add_item' !== $action ) {
			return;
		}

		self::log( 'patch_session_direct() fired' );

		$session = self::yith_session();
		if ( ! $session ) {
			self::log( 'No session found' );
			return;
		}

		$raq = $session->get( 'raq', array() );
		self::log( 'Current RAQ session keys: ' . implode( ', ', array_keys( $raq ) ) );

		$product_id   = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( wp_unslash( $_POST['variation_id'] ) ) : 0;
		$item_key     = self::yith_raq_item_key( $product_id, $variation_id );

		self::log( "Looking for item key: {$item_key}" );

		if ( isset( $raq[ $item_key ] ) ) {
			self::log( 'Item found in session! Patching...' );
			$cfg = array();
			foreach ( self::CFG_KEYS as $key ) {
				if ( isset( $_POST[ $key ] ) && '' !== $_POST[ $key ] ) {
					$val                = self::sanitize_cfg( $key, wp_unslash( $_POST[ $key ] ) );
					$raq[ $item_key ][ $key ] = $val;
					$cfg[ $key ]        = $val;
				}
			}

			if ( ! empty( $cfg ) ) {
				$extra = self::build_variation_rows( $cfg );
				if ( ! empty( $extra ) ) {
					if ( ! isset( $raq[ $item_key ]['variations'] ) || ! is_array( $raq[ $item_key ]['variations'] ) ) {
						$raq[ $item_key ]['variations'] = array();
					}
					$raq[ $item_key ]['variations'] = array_merge( $raq[ $item_key ]['variations'], $extra );
				}
			}

			$session->set( 'raq', $raq );
			self::log( 'Session updated.' );
		} else {
			self::log( 'Item NOT found in session.' );
			if ( ! empty( $raq ) ) {
				self::log( 'First item in RAQ: ' . print_r( reset( $raq ), true ) );
			}
		}
	}

	/**
	 * Logs AJAX requests to verify bridge activity.
	 */
	public static function log_ajax() {
		$action = isset( $_POST['ywraq_action'] ) ? sanitize_text_field( wp_unslash( $_POST['ywraq_action'] ) ) : 'none';
		self::log( "AJAX yith_ywraq_action fired. ywraq_action: {$action}" );
	}

	/**
	 * Formats and adds the configuration breakdown to the display array for YITH quote list.
	 *
	 * @param array $data Existing display data rows.
	 * @param array $item Quote item data.
	 * @return array
	 */
	public static function display_item_data( $data, $item ) {
		self::log( 'display_item_data() called' );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		// If the item has configurator metadata, build display rows.
		if ( ! empty( $item['cfg_sku'] ) || ! empty( $item['cfg_skumap'] ) ) {
			$extra = self::build_variation_rows( $item );
			if ( ! empty( $extra ) ) {
				self::log( 'Displaying ' . count( $extra ) . ' extra rows in list' );
				foreach ( $extra as $label => $value ) {
					$data[] = array(
						'key'   => $label,
						'value' => $value,
					);
				}
			}
		}

		return $data;
	}

	/**
	 * Ensures that unique configurations get unique IDs in the RAQ list.
	 *
	 * @param string $id           Original ID (usually md5 of product+variation).
	 * @param int    $product_id   Product ID.
	 * @param int    $variation_id Variation ID.
	 * @param array  $item_data    Item data.
	 * @return string
	 */
	public static function generate_item_id( $id, $product_id, $variation_id, $item_data ) {
		self::log( 'generate_item_id() called for product ' . $product_id );
		if ( ! empty( $item_data['cfg_sku'] ) ) {
			$new_id = md5( (string) $product_id . (string) $variation_id . (string) $item_data['cfg_sku'] );
			self::log( "Custom ID generated: {$new_id}" );
			return $new_id;
		}
		return $id;
	}

	/**
	 * Injects cfg_* metadata into the YITH quote item before it is stored in the session.
	 *
	 * @param array<string, mixed> $item_data  Data for the quote item.
	 * @return array<string, mixed>
	 */
	public static function add_item_data( $item_data ) {
		self::log( 'add_item_data() called' );
		// Capture cfg_* from $_POST.
		$cfg = array();
		foreach ( self::CFG_KEYS as $key ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST[ $key ] ) && '' !== $_POST[ $key ] ) {
				$val               = self::sanitize_cfg( $key, wp_unslash( $_POST[ $key ] ) );
				$item_data[ $key ] = $val;
				$cfg[ $key ]       = $val;
				self::log( "Captured {$key}" );
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing
		}

		if ( ! empty( $cfg ) ) {
			if ( function_exists( 'ov25_maybe_normalize_snap2_cart_item_data' ) ) {
				$cfg = ov25_maybe_normalize_snap2_cart_item_data( $cfg );
			}

			// Add formatted rows to 'variations' array which YITH uses for display.
			$extra = self::build_variation_rows( $cfg );
			if ( ! empty( $extra ) ) {
				self::log( 'Adding ' . count( $extra ) . ' variation rows' );
				if ( ! isset( $item_data['variations'] ) || ! is_array( $item_data['variations'] ) ) {
					$item_data['variations'] = array();
				}
				$item_data['variations'] = array_merge( $item_data['variations'], $extra );
			}

			// Also ensure top-level keys match what ov25-plugin-init.php expects.
			foreach ( $cfg as $k => $v ) {
				$item_data[ $k ] = $v;
			}
		} else {
			self::log( 'No cfg_* data found in $_POST' );
		}

		return $item_data;
	}

	/**
	 * Overrides the thumbnail HTML for quote items if an OV25 thumbnail is present.
	 *
	 * @param string               $thumbnail Default thumbnail HTML.
	 * @param array<string, mixed> $item      Quote item data.
	 * @return string
	 */
	public static function override_thumbnail( $thumbnail, $item ) {
		if ( ! empty( $item['ov25-thumbnail'] ) ) {
			$src = esc_url( $item['ov25-thumbnail'] );
			$alt = '';
			return "<img src='{$src}' alt='{$alt}' />";
		}
		return $thumbnail;
	}

	/**
	 * Flat key => value rows for YITH `variations` (template prints each as "Key: Value").
	 *
	 * @param array<string, string> $cfg Sanitized cfg_* values.
	 * @return array<string, string>
	 */
	private static function build_variation_rows( array $cfg ) {
		$rows = array();

		if ( ! empty( $cfg['cfg_skumap'] ) ) {
			$map = json_decode( $cfg['cfg_skumap'], true );
			if ( is_array( $map ) ) {
				foreach ( $map as $label => $value ) {
					if ( in_array( $label, self::SKUMAP_SKIP, true ) ) {
						continue;
					}
					if ( '' !== (string) $label && is_scalar( $value ) && '' !== (string) $value ) {
						$rows[ (string) $label ] = function_exists( 'ov25_trim_trailing_separator' )
							? ov25_trim_trailing_separator( $value )
							: (string) $value;
					}
				}
			}
		}

		if ( ! empty( $cfg['cfg_sku'] ) ) {
			$rows['SKU'] = function_exists( 'ov25_trim_trailing_separator' )
				? ov25_trim_trailing_separator( $cfg['cfg_sku'] )
				: (string) $cfg['cfg_sku'];
		}

		return $rows;
	}

	/**
	 * Session row id YITH uses in `raq_content`: md5( product_id ) for simple lines,
	 * md5( product_id . variation_id ) when a variation id is present.
	 *
	 * @param int $product_id    Product ID.
	 * @param int $variation_id  Variation ID, or 0 when not applicable.
	 * @return string 32-char hex key.
	 */
	private static function yith_raq_item_key( $product_id, $variation_id ) {
		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );
		if ( $variation_id > 0 ) {
			return md5( (string) $product_id . (string) $variation_id );
		}
		return md5( (string) $product_id );
	}

	/**
	 * @return object|null Session object with get/set, or null.
	 */
	private static function yith_session() {
		if ( function_exists( 'YITH_Request_Quote' ) ) {
			$rq = YITH_Request_Quote();
			if ( is_object( $rq ) && isset( $rq->session_class ) && is_object( $rq->session_class ) ) {
				return $rq->session_class;
			}
		}
		if ( function_exists( 'WC' ) && WC()->session ) {
			return WC()->session;
		}
		return null;
	}

	/**
	 * Local debug logging to a file in the plugin directory.
	 *
	 * @param string $message Log message.
	 */
	private static function log( $message ) {
		$log_file = dirname( dirname( __FILE__ ) ) . '/bridge-debug.log';
		$time     = date( 'Y-m-d H:i:s' );
		$entry    = "[{$time}] {$message}\n";
		file_put_contents( $log_file, $entry, FILE_APPEND );
	}

	/**
	 * @param string $key   cfg_* key.
	 * @param mixed  $value Raw POST value.
	 * @return string
	 */
	private static function sanitize_cfg( $key, $value ) {
		if ( 'cfg_skumap' === $key || 'cfg_payload' === $key ) {
			$str = is_string( $value ) ? $value : '';
			if ( '' === $str ) {
				return '';
			}
			$decoded = json_decode( $str, true );
			return ( null !== $decoded ) ? $str : '';
		}
		if ( 'cfg_price' === $key && function_exists( 'wc_format_decimal' ) ) {
			return (string) wc_format_decimal( $value );
		}
		return sanitize_text_field( (string) $value );
	}
}
