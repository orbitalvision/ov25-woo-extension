<?php
/**
 * Loads OV25 procedural helpers (cart config, storefront, swatches, legacy migration).
 *
 * @package extension
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/cart-item-config.php';
require_once __DIR__ . '/storefront.php';
require_once __DIR__ . '/swatches-page.php';
require_once __DIR__ . '/legacy-config.php';
