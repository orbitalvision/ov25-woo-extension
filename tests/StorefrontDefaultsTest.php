<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once dirname( __DIR__ ) . '/includes/functions/storefront.php';

/**
 * Storefront configurator default tests.
 */
final class StorefrontDefaultsTest extends TestCase {

	/**
	 * Standard defaults expose the complete false-valued flags contract.
	 */
	public function test_standard_defaults_include_new_false_flags(): void {
		$flags = ov25_default_configurator_config_array()['standard']['flags'];

		$this->assertArrayHasKey( 'disableAddToCart', $flags );
		$this->assertFalse( $flags['disableAddToCart'] );
		$this->assertArrayHasKey( 'disableBuyNow', $flags );
		$this->assertFalse( $flags['disableBuyNow'] );
		$this->assertArrayHasKey( 'hideGestureHint', $flags );
		$this->assertFalse( $flags['hideGestureHint'] );
	}

	/**
	 * Snap2 defaults use the setup modal layout instead of the standard fallback.
	 */
	public function test_snap2_defaults_are_distinct_from_standard(): void {
		$defaults = ov25_default_configurator_config_array();
		$standard = $defaults['standard'];
		$snap2    = $defaults['snap2'];

		$this->assertSame( 'sheet', $standard['configurator']['displayMode']['desktop'] );
		$this->assertSame( 'drawer', $standard['configurator']['displayMode']['mobile'] );
		$this->assertSame( 'modal', $snap2['configurator']['displayMode']['desktop'] );
		$this->assertSame( 'modal', $snap2['configurator']['displayMode']['mobile'] );
		$this->assertSame(
			array(
				'selector' => '[data-ov25-configure-button]',
				'replace'  => false,
			),
			$snap2['selectors']['configureButton']
		);
		$this->assertSame(
			array( 'desktop' => 'right', 'mobile' => 'right' ),
			$snap2['configurator']['variants']['position']
		);
		$this->assertSame(
			array( 'desktop' => 'right', 'mobile' => 'right' ),
			$snap2['configurator']['modules']['position']
		);
	}
}
