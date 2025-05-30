<?php
/**
 * Ov25 Settings Page
 *
 * @package  Ov25WooExtension
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings class
 */
class WC_Settings_Ov25 extends WC_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'ov25';
		$this->label = __( 'OV25', 'ov25-woo-extension' );

		parent::__construct();
	}

	/**
	 * Get settings array
	 *
	 * @return array
	 */
	public function get_settings() {
		$settings = array(
			array(
				'title' => __( 'OV25 Settings', 'ov25-woo-extension' ),
				'type'  => 'title',
				'desc'  => __( 'Configure your OV25 settings below.', 'ov25-woo-extension' ),
				'id'    => 'ov25_api_settings',
			),
			array(
				'title'    => __( 'API Key', 'ov25-woo-extension' ),
				'desc'     => __( 'Enter your Ov25 API Key.', 'ov25-woo-extension' ),
				'id'       => 'ov25_api_key',
				'type'     => 'text',
				'desc_tip' => true,
				'default'  => '',
			),
			array(
				'title'    => __( 'Logo URL', 'ov25-woo-extension' ),
				'desc'     => __( 'Enter the URL for your logo.', 'ov25-woo-extension' ),
				'id'       => 'ov25_logo_url',
				'type'     => 'text',
				'desc_tip' => true,
				'default'  => '',
			),
			array(
				'title'    => __( 'Auto Carousel', 'ov25-woo-extension' ),
				'desc'     => __( 'Enable automatic carousel functionality.', 'ov25-woo-extension' ),
				'id'       => 'ov25_auto_carousel',
				'type'     => 'checkbox',
				'desc_tip' => true,
				'default'  => 'no',
			),
			array(
				'title'    => __( 'Defer 3D', 'ov25-woo-extension' ),
				'desc'     => __( 'Defer 3D rendering for better performance.', 'ov25-woo-extension' ),
				'id'       => 'ov25_defer_3d',
				'type'     => 'checkbox',
				'desc_tip' => true,
				'default'  => 'no',
			),
			array(
				'title'    => __( 'Custom CSS', 'ov25-woo-extension' ),
				'desc'     => __( 'Add custom CSS that will be applied only on OV25 product pages. Use this to customize the appearance of the 3D configurator and product page elements.', 'ov25-woo-extension' ),
				'id'       => 'ov25_custom_css',
				'type'     => 'textarea',
				'desc_tip' => false,
				'default'  => '',
				'css'      => 'width: 100%; height: 200px; font-family: monospace;',
				'placeholder' => __( 'Enter your custom CSS here...', 'ov25-woo-extension' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'ov25_api_settings',
			),
		);

		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings );
	}

	/**
	 * Output the settings
	 */
	public function output() {
		$settings = $this->get_settings();
		WC_Admin_Settings::output_fields( $settings );
	}

	/**
	 * Save settings
	 */
	public function save() {
		$settings = $this->get_settings();
		WC_Admin_Settings::save_fields( $settings );
	}
} 