<?php
/**
 * Registers WooCommerce hooks, enqueues, and cart/checkout integrations.
 *
 * @package extension
 */

defined( 'ABSPATH' ) || exit;

/**
 * Classic cart/checkout/order templates: OV25 thumbnail override when present.
 *
 * @param string               $thumb Default thumbnail HTML.
 * @param array<string, mixed> $item  Cart or order line.
 * @param string|null          $key   Cart item key (unused).
 * @return string
 */
function ov25_override_classic_thumb( $thumb, $item, $key = null ) {
	unset( $key );
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

/**
 * Initialize the plugin.
 *
 * @since 0.1.0
 */
function ov25_woo_extension_init() {
	try {
		load_plugin_textdomain( 'ov25_woo_extension', false, plugin_basename( dirname( MAIN_PLUGIN_FILE ) ) . '/languages' );

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', 'ov25_woo_extension_missing_wc_notice' );
			return;
		}

		ov25_woo_extension::instance();

		add_filter( 'body_class', 'ov25_product_body_class', 20 );

		$admin_page_file = dirname( MAIN_PLUGIN_FILE ) . '/includes/class-admin-page.php';
		if ( file_exists( $admin_page_file ) ) {
			include_once $admin_page_file;
			if ( class_exists( 'OV25_Admin_Page' ) ) {
				OV25_Admin_Page::init();
			}
		}

		$admin_api_file = dirname( MAIN_PLUGIN_FILE ) . '/includes/class-admin-api.php';
		if ( file_exists( $admin_api_file ) ) {
			include_once $admin_api_file;
			if ( class_exists( 'OV25_Admin_API' ) ) {
				OV25_Admin_API::init();
			}
		}

		ov25_maybe_migrate_legacy_config();

		$product_field_file = dirname( MAIN_PLUGIN_FILE ) . '/includes/class-product-field.php';
		if ( file_exists( $product_field_file ) ) {
			include_once $product_field_file;
			if ( class_exists( 'OV25_Product_Field' ) ) {
				OV25_Product_Field::init();
			}
		}

		$gallery_hooks_file = dirname( MAIN_PLUGIN_FILE ) . '/includes/class-gallery-hooks.php';
		if ( file_exists( $gallery_hooks_file ) ) {
			include_once $gallery_hooks_file;
			if ( class_exists( 'OV25_Gallery_Hooks' ) ) {
				OV25_Gallery_Hooks::init();
			}
		}

		$price_hook_file = dirname( MAIN_PLUGIN_FILE ) . '/includes/class-price-hook.php';
		if ( file_exists( $price_hook_file ) ) {
			include_once $price_hook_file;
			if ( class_exists( 'OV25_Price_Hook' ) ) {
				OV25_Price_Hook::init();
			}
		}

		$variant_hook_file = dirname( MAIN_PLUGIN_FILE ) . '/includes/class-variant-hook.php';
		if ( file_exists( $variant_hook_file ) ) {
			include_once $variant_hook_file;
			if ( class_exists( 'OV25_Variant_Hook' ) ) {
				OV25_Variant_Hook::init();
			}
		}

		$swatches_hook_file = dirname( MAIN_PLUGIN_FILE ) . '/includes/class-swatches-hook.php';
		if ( file_exists( $swatches_hook_file ) ) {
			include_once $swatches_hook_file;
			if ( class_exists( 'OV25_Swatches_Hook' ) ) {
				OV25_Swatches_Hook::init();
			}
		}

		$ajax_cart_file = dirname( MAIN_PLUGIN_FILE ) . '/includes/class-ov25-ajax-cart.php';
		if ( file_exists( $ajax_cart_file ) ) {
			require_once $ajax_cart_file;
			if ( class_exists( 'OV25_Ajax_Cart' ) ) {
				OV25_Ajax_Cart::init();
			}
		}

		$swatch_api_file = dirname( MAIN_PLUGIN_FILE ) . '/includes/class-swatch-api.php';
		if ( file_exists( $swatch_api_file ) ) {
			include_once $swatch_api_file;
			if ( class_exists( 'OV25_Swatch_API' ) ) {
				OV25_Swatch_API::init();
			}
		}

		$swatch_page_file = dirname( MAIN_PLUGIN_FILE ) . '/includes/class-swatch-page.php';
		if ( file_exists( $swatch_page_file ) ) {
			include_once $swatch_page_file;
			if ( class_exists( 'OV25_Swatch_Page' ) ) {
				OV25_Swatch_Page::init();
			}
		}

		add_action( 'init', 'ov25_ensure_swatch_product_exists' );
		add_action( 'init', 'ov25_ensure_swatches_page_exists' );
		add_action( 'template_redirect', 'ov25_swatches_plain_permalink_redirect' );

		$loop_button_file = dirname( MAIN_PLUGIN_FILE ) . '/includes/class-loop-button-hook.php';
		if ( file_exists( $loop_button_file ) ) {
			include_once $loop_button_file;
			if ( class_exists( 'OV25_Loop_Button_Hook' ) ) {
				OV25_Loop_Button_Hook::init();
			}
		}

		add_action(
			'wp_enqueue_scripts',
			function () {
				try {
					if ( ! is_product() ) {
						return;
					}

					$product = wc_get_product();
					if ( ! $product ) {
						return;
					}

					$ov25_product_id = $product->get_meta( '_ov25_product_id', true );
					if ( empty( $ov25_product_id ) ) {
						return;
					}

					$disable_cart_form_hiding = get_option( 'ov25_disable_cart_form_hiding', 'no' ) === 'yes';
					if ( ! $disable_cart_form_hiding ) {
						wp_register_style( 'ov25-native-atc-hide', false );
						wp_enqueue_style( 'ov25-native-atc-hide' );
						wp_add_inline_style( 'ov25-native-atc-hide', ov25_get_hide_native_add_to_cart_css() );
					}

					$asset_file = plugin_dir_path( MAIN_PLUGIN_FILE ) . 'build/frontend.asset.php';
					$asset      = array( 'dependencies' => array(), 'version' => '1.0.0' );

					if ( file_exists( $asset_file ) ) {
						$asset = include $asset_file;
					}

					$js_file = plugin_dir_path( MAIN_PLUGIN_FILE ) . 'build/frontend.js';
					if ( file_exists( $js_file ) ) {
						wp_enqueue_script(
							'ov25-frontend',
							plugins_url( 'build/frontend.js', MAIN_PLUGIN_FILE ),
							$asset['dependencies'] ?? array(),
							$asset['version'] ?? filemtime( $js_file ),
							true
						);

						$use_custom = $product->get_meta( '_ov25_use_custom_config', true );

						wp_localize_script(
							'ov25-frontend',
							'ov25Settings',
							array(
								'images'                    => function_exists( 'wc_get_product' ) ? ov25_get_product_images() : array(),
								'gallerySelector'           => get_option( 'ov25_gallery_selector', '' ),
								'variantsSelector'          => get_option( 'ov25_variants_selector', '' ),
								'configureButtonSelector'   => get_option( 'ov25_configure_button_selector', '' ),
								'swatchesSelector'          => get_option( 'ov25_swatches_selector', '' ),
								'priceSelector'             => get_option( 'ov25_price_selector', '' ),
								'customCSS'                 => get_option( 'ov25_custom_css', '' ),
								'swatchProductId'           => ov25_get_swatch_product_id(),
								'restBase'                  => esc_url_raw( get_rest_url() ),
								'createSwatchCartUrl'       => esc_url_raw( get_rest_url( null, 'ov25/v1/create-swatch-cart' ) ),
								'useSimpleConfigureButton'  => get_option( 'ov25_use_simple_configure_button', 'no' ) === 'yes',
								'configuratorConfig'        => ov25_get_storefront_configurator_config(),
								'productConfig'             => ( $use_custom === 'yes' )
									? json_decode( $product->get_meta( '_ov25_configurator_config', true ) ?: '{}', true )
									: null,
								'useCustomConfig'           => $use_custom === 'yes',
								'ajaxUrl'                   => admin_url( 'admin-ajax.php' ),
								'addToCartNonce'            => wp_create_nonce( 'ov25_add_to_cart' ),
								'wcProductId'               => (int) $product->get_id(),
								'disableCartFormHiding'     => $disable_cart_form_hiding,
							)
						);
					}

					$css_file = plugin_dir_path( MAIN_PLUGIN_FILE ) . 'build/frontend.css';
					if ( file_exists( $css_file ) ) {
						wp_enqueue_style(
							'ov25-frontend-styles',
							plugins_url( 'build/frontend.css', MAIN_PLUGIN_FILE ),
							array(),
							filemtime( $css_file )
						);
					}
				} catch ( Exception $e ) {
					error_log( 'OV25 Woo Extension: Error enqueuing frontend scripts - ' . $e->getMessage() );
				}
			},
			20
		);

		add_action(
			'wp_enqueue_scripts',
			function () {
				try {
					if ( ! is_product() ) {
						return;
					}

					wp_register_style( 'ov25-dummy', false );
					wp_enqueue_style( 'ov25-dummy' );

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
			},
			18
		);

		add_action(
			'wp_enqueue_scripts',
			function () {
				try {
					if ( ! function_exists( 'WC' ) ) {
						return;
					}
					wp_register_style( 'ov25-wc-blocks-line-details', false );
					wp_enqueue_style( 'ov25-wc-blocks-line-details' );
					wp_add_inline_style( 'ov25-wc-blocks-line-details', ov25_wc_blocks_product_details_newlines_css() );
				} catch ( Exception $e ) {
					error_log( 'OV25 Woo Extension: blocks line-details CSS - ' . $e->getMessage() );
				}
			},
			30
		);

		add_filter(
			'woocommerce_add_to_cart_redirect',
			function ( $url ) {
				try {
					if ( ! empty( $_POST['ov25_redirect_checkout'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['ov25_redirect_checkout'] ) ) ) {
						if ( function_exists( 'wc_get_checkout_url' ) ) {
							return wc_get_checkout_url();
						}
					}
				} catch ( Exception $e ) {
					error_log( 'OV25 Woo Extension: add_to_cart_redirect - ' . $e->getMessage() );
				}
				return $url;
			},
			99
		);

		add_filter(
			'woocommerce_add_cart_item_data',
			function ( $item, $product_id ) {
				try {
					foreach ( array( 'cfg_price', 'cfg_payload', 'cfg_sku', 'cfg_skumap', 'cfg_commerce_mode', 'ov25-thumbnail' ) as $key ) {
						if ( isset( $_POST[ $key ] ) ) {
							$item[ $key ] = wc_clean( wp_unslash( $_POST[ $key ] ) );
						}
					}

					$product = wc_get_product( $product_id );
					if ( $product && $product->get_meta( '_ov25_product_id', true ) && empty( $item['ov25-thumbnail'] ) ) {
						$item['ov25-needs-thumbnail'] = true;
					}

					return $item;
				} catch ( Exception $e ) {
					error_log( 'OV25 Woo Extension: Error in add cart item data - ' . $e->getMessage() );
					return $item;
				}
			},
			10,
			2
		);

		add_action(
			'woocommerce_checkout_create_order_line_item',
			function ( $item, $cart_item_key, $values ) {
				unset( $cart_item_key );
				try {
					$part_label = '';
					if ( ! empty( $values['ov25_multi_line_group'] ) && ! empty( $values['cfg_skumap'] ) && function_exists( 'ov25_part_label_from_cfg_skumap' ) ) {
						$part_label = ov25_part_label_from_cfg_skumap( $values['cfg_skumap'] );
					}
					if ( '' !== $part_label ) {
						$item->add_meta_data( 'PART', $part_label, true );
					}

					if ( ! empty( $values['cfg_sku'] ) ) {
						$item->add_meta_data( 'SKU', ov25_trim_trailing_separator( $values['cfg_sku'] ), true );
					}
					if ( ! empty( $values['cfg_skumap'] ) ) {
						$sku_map = json_decode( $values['cfg_skumap'], true );
						if ( is_array( $sku_map ) ) {
							foreach ( $sku_map as $key => $value ) {
								if ( ! in_array( $key, array( 'Range', 'Product', 'Ranges', 'Products' ), true ) ) {
									$item->add_meta_data( $key, ov25_trim_trailing_separator( $value ), true );
								}
							}
						}
					}

					if ( ! empty( $values['swatch_name'] ) ) {
						$item->add_meta_data( $values['swatch_option'], $values['swatch_name'], true );
					}
					if ( ! empty( $values['swatch_sku'] ) ) {
						$item->add_meta_data( 'SKU', $values['swatch_sku'], true );
					}
				} catch ( Exception $e ) {
					error_log( 'OV25 Woo Extension: Error in checkout create order line item (SKU) - ' . $e->getMessage() );
				}
			},
			10,
			3
		);

		add_filter(
			'woocommerce_get_item_data',
			function ( $item_data, $cart_item ) {
				try {
					if ( ! empty( $cart_item['ov25_multi_line_group'] ) && ! empty( $cart_item['cfg_skumap'] ) && function_exists( 'ov25_part_label_from_cfg_skumap' ) ) {
						$part_label = ov25_part_label_from_cfg_skumap( $cart_item['cfg_skumap'] );
						if ( '' !== $part_label ) {
							$item_data[] = array(
								'key'     => __( 'PART', 'ov25-woo-extension' ),
								'value'   => esc_html( $part_label ),
								'display' => '',
							);
						}
					}

					if ( ! empty( $cart_item['cfg_sku'] ) ) {
						$item_data[] = array(
							'key'     => __( 'SKU', 'ov25-woo-extension' ),
							'value'   => esc_html( ov25_trim_trailing_separator( $cart_item['cfg_sku'] ) ),
							'display' => '',
						);
					}

					if ( ! empty( $cart_item['cfg_skumap'] ) ) {
						$sku_map = json_decode( $cart_item['cfg_skumap'], true );
						if ( is_array( $sku_map ) ) {
							foreach ( $sku_map as $key => $value ) {
								if ( ! in_array( $key, array( 'Range', 'Product', 'Ranges', 'Products' ), true ) ) {
									$item_data[] = array(
										'key'     => esc_html( $key ),
										'value'   => esc_html( ov25_trim_trailing_separator( $value ) ),
										'display' => '',
									);
								}
							}
						}
					}

					if ( ! empty( $cart_item['swatch_name'] ) || ! empty( $cart_item['swatch_option'] ) ) {
						$item_data[] = array(
							'key'     => esc_html( $cart_item['swatch_option'] ?? __( 'Swatch', 'ov25-woo-extension' ) ),
							'value'   => esc_html( $cart_item['swatch_name'] ?? '' ),
							'display' => '',
						);
					}

					if ( ! empty( $cart_item['swatch_sku'] ) ) {
						$item_data[] = array(
							'key'     => esc_html( __( 'SKU', 'ov25-woo-extension' ) ),
							'value'   => esc_html( ov25_trim_trailing_separator( $cart_item['swatch_sku'] ) ),
							'display' => '',
						);
					}

					return $item_data;
				} catch ( Exception $e ) {
					error_log( 'OV25 Woo Extension: Error in get item data - ' . $e->getMessage() );
					return $item_data;
				}
			},
			10,
			2
		);

		add_filter(
			'woocommerce_cart_item_price',
			function ( $price_html, $cart_item, $cart_item_key ) {
				try {
					if ( ! empty( $cart_item['cfg_price'] ) ) {
						$minor       = ov25_parse_amount_to_minor_units( $cart_item['cfg_price'] );
						$price_major = $minor / 100;

						$args = array( 'price' => $price_major );

						if ( WC()->cart->display_prices_including_tax() ) {
							$product_price = wc_get_price_including_tax( $cart_item['data'], $args );
						} else {
							$product_price = wc_get_price_excluding_tax( $cart_item['data'], $args );
						}

						return wc_price( $product_price );
					}

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
			},
			10,
			3
		);

		add_action(
			'woocommerce_before_calculate_totals',
			function ( $cart ) {
				try {
					foreach ( $cart->get_cart() as $cart_item_key => $ci ) {

						if ( ! empty( $ci['cfg_price'] ) ) {
							$minor = ov25_parse_amount_to_minor_units( $ci['cfg_price'] );
							if ( $minor <= 0 ) {
								continue;
							}
							$price_major = $minor / 100;

							$product = isset( $ci['data'] ) && is_a( $ci['data'], 'WC_Product' ) ? $ci['data'] : null;
							if ( ! $product ) {
								continue;
							}
							if ( isset( $cart->cart_contents[ $cart_item_key ] ) ) {
								$cart->cart_contents[ $cart_item_key ]['data'] = clone $product;
								$product                                       = $cart->cart_contents[ $cart_item_key ]['data'];
							}

							$product->set_price( $price_major );

							$product->set_regular_price( (string) $price_major );
							$product->set_sale_price( '' );
						}

						if ( ! empty( $ci['swatch_price'] ) ) {
							$swatch_price = floatval( $ci['swatch_price'] );
							$product      = isset( $cart->cart_contents[ $cart_item_key ]['data'] )
								? $cart->cart_contents[ $cart_item_key ]['data']
								: $ci['data'];

							$product->set_price( $swatch_price );
							$product->set_regular_price( $swatch_price );
							$product->set_sale_price( '' );
						}
					}
				} catch ( Exception $e ) {
					error_log( 'OV25 Woo Extension: Error in before calculate totals - ' . $e->getMessage() );
				}
			},
			1000
		);

		add_filter( 'woocommerce_cart_item_thumbnail', 'ov25_override_classic_thumb', 100, 3 );
		add_filter( 'woocommerce_checkout_item_thumbnail', 'ov25_override_classic_thumb', 100, 3 );
		add_filter( 'woocommerce_order_item_thumbnail', 'ov25_override_classic_thumb', 100, 3 );

		add_filter(
			'woocommerce_store_api_cart_item_images',
			function ( $images, $cart_item, $cart_item_key ) {
				try {
					if ( empty( $cart_item['ov25-thumbnail'] ) ) {
						return $images;
					}

					$src = esc_url( $cart_item['ov25-thumbnail'] );

					return array(
						(object) array(
							'id'        => 0,
							'src'       => $src,
							'thumbnail' => $src,
							'srcset'    => '',
							'sizes'     => '',
							'name'      => '',
							'alt'       => '',
						),
					);
				} catch ( Exception $e ) {
					error_log( 'OV25 Woo Extension: Error in store API cart item images - ' . $e->getMessage() );
					return $images;
				}
			},
			100,
			3
		);
	} catch ( Exception $e ) {
		error_log( 'OV25 Woo Extension: Error in plugin initialization - ' . $e->getMessage() );
	}
}
