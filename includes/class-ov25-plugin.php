<?php
/**
 * Main plugin singleton (minimal bootstrap object).
 *
 * @package extension
 */

defined( 'ABSPATH' ) || exit;

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
		public $version = '1.0.7';

		/**
		 * Constructor.
		 */
		public function __construct() {
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
