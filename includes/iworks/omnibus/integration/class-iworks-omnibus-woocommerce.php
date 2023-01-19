<?php
/*

Copyright 2022-PLUGIN_TILL_YEAR Marcin Pietrzak (marcin@iworks.pl)

this program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

 */
defined( 'ABSPATH' ) || exit;

if ( class_exists( 'iworks_omnibus_integration_woocommerce' ) ) {
	return;
}

include_once dirname( dirname( __FILE__ ) ) . '/class-iworks-omnibus-integration.php';

class iworks_omnibus_integration_woocommerce extends iworks_omnibus_integration {

	public function __construct() {

		add_filter( 'woocommerce_get_settings_pages', array( $this, 'filter_woocommerce_get_settings_pages' ) );

		/**
		 * Show message
		 *
		 * @since 1.2.3
		 */
		add_shortcode( 'omnibus_price_message', array( $this, 'shortcode' ) );
		/**
		 * own action
		 */
		add_action( 'iworks_omnibus_wc_lowest_price_message', array( $this, 'action_get_message' ) );
		add_filter( 'iworks_omnibus_get_name', array( $this, 'get_name' ) );
		add_filter( 'iworks_omnibus_message_template', array( $this, 'filter_iworks_omnibus_message_template_for_admin_list' ) );
		add_filter( 'iworks_omnibus_message_template', array( $this, 'filter_iworks_omnibus_message_template_for_product' ), 10, 3 );
		/**
		 * admin init
		 *
		 * @since 2.1.0
		 */
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );
		/**
		 * maybe save initial data
		 *
		 * @since 2.2.2
		 */
		add_action( 'shutdown', array( $this, 'action_shutdown_maybe_save_product_price' ) );
		/**
		 * WooCommerce
		 *
		 * @since 1.0.0
		 */
		add_action( 'woocommerce_after_product_object_save', array( $this, 'action_woocommerce_save_maybe_save_short' ), 10, 1 );
		add_action( 'woocommerce_after_product_object_save', array( $this, 'action_woocommerce_save_price_history' ), 10, 1 );
		add_action( 'woocommerce_product_options_pricing', array( $this, 'action_woocommerce_product_options_pricing' ) );
		add_action( 'woocommerce_variation_options_pricing', array( $this, 'action_woocommerce_variation_options_pricing' ), 10, 3 );
		add_filter( 'woocommerce_get_sections_products', array( $this, 'filter_woocommerce_get_sections_products' ), 999 );
		add_filter( 'woocommerce_get_settings_products', array( $this, 'filter_woocommerce_get_settings_for_section' ), 10, 2 );
		/**
		 * WooCommerce: exclude meta
		 *
		 * @since 2.0.3
		 */
		add_filter( 'woocommerce_duplicate_product_exclude_meta', array( $this, 'filter_woocommerce_duplicate_product_exclude_meta' ), 10, 2 );
		/**
		 * WooCommerce bind message
		 *
		 * @since 1.1.0
		 */
		$where = get_option( $this->get_name( 'where' ), 'woocommerce_get_price_html' );
		switch ( $where ) {
			case 'do_not_show':
				break;
			case 'woocommerce_after_add_to_cart_button':
			case 'woocommerce_after_add_to_cart_quantity':
			case 'woocommerce_after_single_product_summary':
			case 'woocommerce_before_add_to_cart_button':
			case 'woocommerce_before_add_to_cart_form':
			case 'woocommerce_before_add_to_cart_quantity':
			case 'woocommerce_before_single_product_summary':
			case 'woocommerce_product_meta_end':
			case 'woocommerce_product_meta_start':
			case 'woocommerce_single_product_summary':
				add_action( $where, array( $this, 'action_check_and_add_message' ) );
				break;
			case 'the_content_start':
			case 'the_content_end':
				add_filter( 'the_content', array( $this, 'filter_the_content' ) );
				break;
			default:
				add_filter( 'woocommerce_get_price_html', array( $this, 'filter_woocommerce_get_price_html' ), 10, 2 );
		}
		/**
		 * WooCommerce show in cart
		 *
		 * @since 2.1.5
		 */
		if ( 'yes' === get_option( $this->get_name( 'cart' ), 'no' ) ) {
			add_filter( 'woocommerce_cart_item_price', array( $this, 'filter_woocommerce_cart_item_price' ), 10, 3 );
		}
		/**
		 * Tutor LMS (as relatedo to WooCommerce)
		 *
		 * @since 1.0.1
		 */
		add_filter( 'tutor_course_details_wc_add_to_cart_price', array( $this, 'filter_tutor_course_details_wc_add_to_cart_price' ), 10, 2 );
		/**
		 * YITH WooCommerce Product Bundles
		 *
		 * @since 1.1.0
		 */
		add_action( 'yith_wcpb_after_product_bundle_options_tab', array( $this, 'action_woocommerce_product_options_pricing' ) );
	}

	/**
	 * admin init
	 *
	 * @since 2.1.0
	 */
	public function action_admin_init() {
		add_filter( 'plugin_action_links', array( $this, 'filter_add_link_omnibus_configuration' ), PHP_INT_MAX, 4 );
		add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts_register' ) );
		add_action( 'load-woocommerce_page_wc-settings', array( $this, 'enqueue_scripts' ) );
	}

	public function enqueue_scripts() {
		wp_enqueue_script( $this->get_name( __CLASS__ ) );
	}

	/**
	 * Enqueue scripts for all admin pages.
	 *
	 * @since 2.3.0
	 */
	public function action_admin_enqueue_scripts_register() {
		wp_register_script(
			$this->get_name( __CLASS__ ),
			plugins_url( 'assets/scripts/admin/woocommerce.min.js', dirname( dirname( dirname( __DIR__ ) ) ) ),
			array( 'jquery' ),
			'PLUGIN_VERSION',
		);
	}

	/**
	 * WooCommerce: save price history
	 *
	 * @since 1.0.0
	 */
	public function action_woocommerce_save_price_history( $product ) {
		$price = $this->get_price( $product );
		if ( empty( $price ) ) {
			return;
		}
		$post_id = $product->get_id();
		$this->save_price_history( $post_id, $price );
	}

	/**
	 * WooCommerce: show Omnibus price & date for regular product
	 *
	 * @since 1.0.0
	 */
	public function action_woocommerce_product_options_pricing() {
		global $post_id;
		if ( ! $this->should_it_show_up( $post_id ) ) {
			return;
		}
		$price_lowest = $this->woocommerce_get_lowest_price_in_history( $post_id );
		$this->print_header( 'description' );
		$this->woocommerce_wp_text_input_price( $price_lowest );
		$this->woocommerce_wp_text_input_date( $price_lowest );
		$this->woocommerce_wp_checkbox_short( $post_id );
	}

	/**
	 * WooCommerce: show Omnibus price & date for variable product
	 *
	 * @since 1.0.0
	 */
	public function action_woocommerce_variation_options_pricing( $loop, $variation_data, $variation ) {
		$post_id = $variation->ID;
		if ( ! $this->should_it_show_up( $post_id ) ) {
			return;
		}
		$price_lowest = $this->woocommerce_get_lowest_price_in_history( $post_id );
		echo '</div>';
		echo '<div>';
		$this->print_header( 'form-row form-row-full' );
		$configuration = array(
			'wrapper_class' => 'form-row form-row-first',
		);
		$this->woocommerce_wp_text_input_price( $price_lowest, $configuration );
		$configuration = array(
			'wrapper_class' => 'form-row form-row-last',
		);
		$this->woocommerce_wp_text_input_date( $price_lowest, $configuration );
	}

	/**
	 * helper to decide show it or no
	 */
	private function should_it_show_up( $post_id ) {
		/**
		 * for admin
		 */
		if ( is_admin() ) {
			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				if (
					isset( $_POST['action'] )
					&& 'woocommerce_load_variations' === $_POST['action']
				) {
					if ( 'yes' === get_option( $this->get_name( 'admin_edit' ), 'yes' ) ) {
						return apply_filters( 'iworks_omnibus_show', true );
					}
				}
			} else {
				$screen = get_current_screen();
				if ( 'product' === $screen->id ) {
					if ( 'yes' === get_option( $this->get_name( 'admin_edit' ), 'yes' ) ) {
						return apply_filters( 'iworks_omnibus_show', true );
					}
				}
				if ( 'edit-product' === $screen->id ) {
					if ( 'yes' === get_option( $this->get_name( 'admin_list' ), 'no' ) ) {
						return apply_filters( 'iworks_omnibus_show', true );
					}
				}
			}
			return apply_filters( 'iworks_omnibus_show', false );
		}
		/**
		 * front-end short term good
		 */
		if ( 'yes' === get_option( $this->get_name( 'admin_short' ), 'no' ) ) {
			if ( 'yes' === get_post_meta( $post_id, $this->get_name( 'is_short' ), true ) ) {
				if ( 'no' === get_option( $this->get_name( 'short_message' ), 'no' ) ) {
					return apply_filters( 'iworks_omnibus_show', false );
				}
			}
		}
		/**
		 * front-end on sale
		 */
		if ( 'yes' === get_option( $this->get_name( 'on_sale' ), 'yes' ) ) {
			$product = wc_get_product( $post_id );
			if ( ! $product->is_on_sale() ) {
				return apply_filters( 'iworks_omnibus_show', false );
			}
		}
		/**
		 * single product
		 */
		if ( is_single() && is_main_query() ) {
			if ( is_product() ) {
				global $woocommerce_loop;
				if (
					is_array( $woocommerce_loop )
					&& isset( $woocommerce_loop['name'] )
					&& 'related' === $woocommerce_loop['name']
				) {
					if ( 'no' === get_option( $this->get_name( 'related' ), 'no' ) ) {
						return apply_filters( 'iworks_omnibus_show', false );
					}
				}
				/**
				 * variation
				 */
				$product = wc_get_product( $post_id );
				if ( 'variation' === $product->get_type() ) {
					if ( 'no' === get_option( $this->get_name( 'variation' ), 'yes' ) ) {
						return apply_filters( 'iworks_omnibus_show', false );
					}
					return apply_filters( 'iworks_omnibus_show', true );
				}
			}
			if ( 'yes' === get_option( $this->get_name( 'single' ), 'yes' ) ) {
				return apply_filters( 'iworks_omnibus_show', true );
			}
			return apply_filters( 'iworks_omnibus_show', false );
		}
		/**
		 * shop page
		 */
		if ( is_shop() ) {
			if ( 'yes' === get_option( $this->get_name( 'shop' ), 'no' ) ) {
				return apply_filters( 'iworks_omnibus_show', true );
			}
			return apply_filters( 'iworks_omnibus_show', false );
		}
		/**
		 * Taxonomy Page
		 */
		if ( is_tax() ) {
			if ( 'yes' === get_option( $this->get_name( 'tax' ), 'no' ) ) {
				return apply_filters( 'iworks_omnibus_show', true );
			}
			return apply_filters( 'iworks_omnibus_show', false );
		}
		/**
		 * any loop
		 */
		if ( in_the_loop() ) {
			if ( 'yes' === get_option( $this->get_name( 'loop' ), 'no' ) ) {
				return apply_filters( 'iworks_omnibus_show', true );
			}
			return apply_filters( 'iworks_omnibus_show', false );
		}
		/**
		 * at least add filter
		 */
		$show = 'yes' === get_option( $this->get_name( 'default' ), 'no' );
		return apply_filters( 'iworks_omnibus_show', $show );
	}

	/**
	 * WooCommerce: filter for HTML price
	 *
	 * @since 1.0.0
	 */
	public function filter_woocommerce_get_price_html( $price, $product ) {
		if ( ! is_object( $product ) ) {
			return $price;
		}
		if ( ! $this->should_it_show_up( $product->get_id() ) ) {
			return $price;
		}
		$price_lowest = $this->get_lowest_price( $product );
		if ( empty( $price_lowest ) ) {
			return $price;
		}
		return $this->add_message( $price, $price_lowest, 'wc_price' );
	}

	private function get_lowest_price( $product ) {
		/**
		 * get price
		 *
		 * @since 2.0.2
		 */
		$price = $this->get_price( $product );
		if ( empty( $price ) ) {
			return;
		}
		$product_type = $product->get_type();
		switch ( $product_type ) {
			case 'variable':
				$price_lowest = $this->woocommerce_get_price_html_for_variable( $price, $product );
				return apply_filters( 'iworks_omnibus_integration_woocommerce_price_lowest', $price_lowest, $product );
			case 'variation':
				break;
			default:
				if (
				get_post_type() === $product_type
				|| get_post_type() === 'product'
				) {
					if (
					'no' === get_option( $this->get_name( $product_type ), 'yes' )
					) {
						return $price;
					}
				} else {
					if ( 'courses' === get_post_type() ) {
						if (
						defined( 'TUTOR_VERSION' )
						&& 'no' === get_option( $this->get_name( 'tutor' ), 'yes' )
						) {
							return $price;
						}
					}
				}
		}
		$price_lowest = $this->woocommerce_get_lowest_price_in_history( $product->get_id() );
		return apply_filters( 'iworks_omnibus_integration_woocommerce_price_lowest', $price_lowest, $product );
	}

	/**
	 * Tutor LMS with WooCommerce integration
	 *
	 * @since 1.0.1
	 */
	public function filter_tutor_course_details_wc_add_to_cart_price( $content, $product ) {
		return $this->filter_woocommerce_get_price_html( $content, $product );
	}

	/**
	 * Add section tab to WooCommerce Settings
	 *
	 * @since 1.1.0
	 */
	public function filter_woocommerce_get_sections_products( $sections ) {
		$sections[ $this->meta_name ] = __( 'Omnibus Directive', 'omnibus' );
		return $sections;
	}

	/**
	 * WooCommerce: get lowest price in history
	 *
	 * @since 1.0.0
	 */
	private function woocommerce_get_lowest_price_in_history( $post_id ) {
		$product = wc_get_product( $post_id );
		/**
		 * check is object
		 *
		 * @since 1.2.1
		 */
		if ( ! is_object( $product ) ) {
			return;
		}
		/**
		 * get price
		 *
		 * @since 2.0.2
		 */
		$price  = $this->get_price( $product );
		$lowest = $this->_get_lowest_price_in_history( $price, $post_id );
		if ( is_admin() ) {
			if ( empty( $lowest ) ) {
				$value = get_post_meta( $post_id, $this->get_name() );
				if ( empty( $value ) ) {
					return $lowest;
				}
			}
		} elseif ( 'current' !== get_option( $this->get_name( 'missing' ), 'current' ) ) {
			return $lowest;
		}
		if ( empty( $lowest ) ) {
			$lowest = array(
				'price'     => $price,
				'timestamp' => time(),
			);
		}
		if ( isset( $lowest['price'] ) ) {
			$lowest['qty']                 = 1;
			$lowest['price_including_tax'] = wc_get_price_including_tax( $product, $lowest );
		}
		return $lowest;
	}

	/**
	 * WooCommerce: get price HTML for variable product
	 *
	 * @since 1.0.0
	 */
	private function woocommerce_get_price_html_for_variable( $price, $product ) {
		if ( 'no' === get_option( $this->get_name( 'variable' ), 'yes' ) ) {
			return $price;
		}
		$price_lowest = $this->woocommerce_get_lowest_price_in_history( $product->get_ID() );
		foreach ( $product->get_available_variations() as $variable ) {
			$o = $this->woocommerce_get_lowest_price_in_history( $variable['variation_id'] );
			if ( ! isset( $price_lowest['price'] ) ) {
				$price_lowest = $o;
				continue;
			}
			if ( $o['price'] < $price_lowest['price'] ) {
				$price_lowest = $o;
			}
		}
		return $price_lowest;
	}

	private function woocommerce_wp_text_input_price( $price_lowest, $configuration = array() ) {
		woocommerce_wp_text_input(
			wp_parse_args(
				array(
					'id'                => $this->meta_name . '_price',
					'custom_attributes' => array( 'disabled' => 'disabled' ),
					'value'             => empty( $price_lowest ) ? __( 'no data', 'omnibus' ) : $price_lowest['price'],
					'data_type'         => 'price',
					'label'             => __( 'Price', 'omnibus' ) . ' (' . get_woocommerce_currency_symbol() . ')',
					'desc_tip'          => true,
					'description'       => sprintf(
						__( 'The lowest price in %d days.', 'omnibus' ),
						$this->get_days()
					),
				),
				$configuration
			)
		);
	}

	/**
	 * WooCommerce text field helper
	 *
	 * @since 1.1.0
	 */
	private function woocommerce_wp_text_input_date( $price_lowest, $configuration = array() ) {
		woocommerce_wp_text_input(
			wp_parse_args(
				array(
					'id'                => $this->meta_name . '_date',
					'custom_attributes' => array( 'disabled' => 'disabled' ),
					'value'             => empty( $price_lowest ) ? esc_html__( 'no data', 'omnibus' ) : date_i18n( get_option( 'date_format' ), $price_lowest['timestamp'] ),
					'data_type'         => 'text',
					'label'             => __( 'Date', 'omnibus' ),
					'desc_tip'          => true,
					'description'       => sprintf(
						__( 'The date when lowest price in %d days occurred.', 'omnibus' ),
						$this->get_days()
					),
				),
				$configuration
			)
		);
	}

	/**
	 * WooCommerce: Settings Page
	 *
	 * @since 1.1.0
	 */
	public function filter_woocommerce_get_settings_for_section( $settings, $section_id ) {
		if ( $section_id !== $this->meta_name ) {
			return $settings;
		}
		$settings = array(
			array(
				'title' => __( 'Settings has been moved', 'omnibus' ),
				'id'    => $this->get_name( 'moved' ),
				'type'  => 'title',
				'desc'  => sprintf(
					esc_html__( 'Please visit new %1$ssettings page%2$s.', 'omnibus' ),
					sprintf( '<a href="%s">', remove_query_arg( 'section', add_query_arg( 'tab', 'omnibus' ) ) ),
					'</a>'
				),
			),
			$this->settings_title(),
			array(
				'title'   => __( 'Display minimal price', 'omnibus' ),
				'id'      => $this->get_name( 'on_sale' ),
				'default' => 'yes',
				'type'    => 'radio',
				'options' => array(
					'yes' => esc_html__( 'Only when the product is on sale (strongly advised)', 'omnibus' ),
					'no'  => esc_html__( 'Always', 'omnibus' ),
				),
				'desc'    => esc_html__( 'European Union guidance requires displaying the minimal price if a product is on sale.', 'omnibus' ),
			),
			array(
				'title'   => __( 'No previous price', 'omnibus' ),
				'id'      => $this->get_name( 'missing' ),
				'default' => 'current',
				'type'    => 'radio',
				'options' => array(
					'current' => esc_html__( 'Display current price', 'omnibus' ),
					'no'      => esc_html__( 'Do not display anything', 'omnibus' ),
				),
				'desc'    => esc_html__( 'What do you want to show when no data is available?', 'omnibus' ),
			),
			/**
			 * Show on
			 */
			array(
				'title'         => __( 'Show on', 'omnibus' ),
				'desc'          => __( 'Product single', 'omnibus' ),
				'id'            => $this->get_name( 'product' ),
				'default'       => 'yes',
				'type'          => 'checkbox',
				'checkboxgroup' => 'start',
				'desc_tip'      => __( 'Show or hide on a single product page.', 'omnibus' ),
			),
			array(
				'desc'          => __( 'WooCommerce Shop', 'omnibus' ),
				'id'            => $this->get_name( 'shop' ),
				'default'       => 'no',
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'desc_tip'      => sprintf(
					__( 'Show or hide on the <a href="%s#woocommerce_shop_page_id" target="_blank">Shop Page</a>.', 'omnibus' ),
					add_query_arg(
						array(
							'page' => 'wc-settings',
							'tab'  => 'products',
						),
						admin_url( 'admin.php' )
					)
				),
			),
			array(
				'desc'          => __( 'WooCommerce Cart', 'omnibus' ),
				'id'            => $this->get_name( 'cart' ),
				'default'       => 'no',
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'desc_tip'      => sprintf(
					__( 'Show or hide on the <a href="%s#woocommerce_cart_page_id" target="_blank">Cart Page</a>.', 'omnibus' ),
					add_query_arg(
						array(
							'page' => 'wc-settings',
							'tab'  => 'advanced',
						),
						admin_url( 'admin.php' )
					)
				),
			),
			array(
				'desc'          => __( 'Any loop', 'omnibus' ),
				'id'            => $this->get_name( 'loop' ),
				'default'       => 'no',
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'desc_tip'      => __( 'Show or hide on any product list.', 'omnibus' ),
			),
			array(
				'desc'          => __( 'Taxonomy page', 'omnibus' ),
				'id'            => $this->get_name( 'tax' ),
				'default'       => 'no',
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'desc_tip'      => __( 'Show or hide on any taxonomy (tags, categories, custom taxonomies).', 'omnibus' ),
			),
			array(
				'desc'          => __( 'Related products list', 'omnibus' ),
				'id'            => $this->get_name( 'related' ),
				'default'       => 'no',
				'type'          => 'checkbox',
				'checkboxgroup' => 'end',
				'desc_tip'      => __( 'Show or hide on the related products box.', 'omnibus' ),
			),
			array(
				'title'    => __( 'Default', 'omnibus' ),
				'id'       => $this->get_name( 'default' ),
				'default'  => 'no',
				'type'     => 'checkbox',
				'desc'     => __( 'Display anywhere else', 'omnibus' ),
				'desc_tip' => __( 'Display anywhere else that doesn\'t fit any of the above.', 'omnibus' ),
			),
		);
		if ( 'no' === get_option( 'woocommerce_prices_include_tax', 'no' ) ) {
			$settings[] = array(
				'title'   => __( 'Include tax', 'omnibus' ),
				'id'      => $this->get_name( 'include_tax' ),
				'default' => 'yes',
				'type'    => 'checkbox',
				'desc'    => __( 'Display price with tax', 'omnibus' ),
			);
		}
		$products = array(
			array(
				'desc' => __( 'Simple product', 'omnibus' ),
				'id'   => $this->get_name( 'simple' ),
			),
			array(
				'desc' => __( 'Variable product: global', 'omnibus' ),
				'id'   => $this->get_name( 'variable' ),
			),
			array(
				'desc' => __( 'Variable product: variation', 'omnibus' ),
				'id'   => $this->get_name( 'variation' ),
			),
		);
		/**
		 * Tutor LMS (as relatedo to WooCommerce)
		 *
		 * @since 1.0.1
		 */
		if ( defined( 'TUTOR_VERSION' ) ) {
			$products[] = array(
				'desc' => __( 'Tutor course', 'omnibus' ),
				'id'   => $this->get_name( 'tutor' ),
			);
		}
		/**
		 * YITH WooCommerce Product Bundles
		 *
		 * @since 1.1.0
		 */
		if ( defined( 'YITH_WCPB_VERSION' ) ) {
			$products[] = array(
				'desc' => __( 'YITH Bundle', 'omnibus' ),
				'id'   => $this->get_name( 'yith_bundle' ),
			);
		}
		/**
		 * filter avaialble products list
		 *
		 * @since 1.1.0
		 */
		$products = apply_filters( 'iworks_omnibus_integration_woocommerce_settings', $products );
		/**
		 * add to Settings
		 */
		foreach ( $products as $index => $one ) {
			if ( 0 === $index ) {
				$one['title']         = __( 'Show for type', 'omnibus' );
				$one['checkboxgroup'] = 'start';
			}
			$one = wp_parse_args(
				$one,
				array(
					'default'       => 'yes',
					'type'          => 'checkbox',
					'checkboxgroup' => '',
				)
			);
			if ( ( 1 + $index ) === count( $products ) ) {
				$one['checkboxgroup'] = 'end';
			}
			$settings[] = $one;
		}
		/**
		 * admin
		 */
		$settings[] = array(
			'title'         => __( 'Show on admin on', 'omnibus' ),
			'desc'          => __( 'Products list', 'omnibus' ),
			'id'            => $this->get_name( 'admin_list' ),
			'default'       => 'no',
			'type'          => 'checkbox',
			'checkboxgroup' => 'start',
		);
		$settings[] = array(
			'desc'          => __( 'Product edit', 'omnibus' ),
			'id'            => $this->get_name( 'admin_edit' ),
			'default'       => 'yes',
			'type'          => 'checkbox',
			'checkboxgroup' => 'end',
		);
		$settings[] = $this->settings_days();
		$settings[] = array(
			'title'   => __( 'Where to display', 'omnibus' ),
			'desc'    => __( 'Change if you have only single products.', 'omnibus' ),
			'id'      => $this->get_name( 'where' ),
			'default' => 'woocommerce_get_price_html',
			'type'    => 'select',
			'options' => array(
				'woocommerce_get_price_html'               => esc_html__( 'After the price (recommended)', 'omnibus' ),
				'do_not_show'                              => esc_html__( 'Do not show. I will handle it myself.', 'omnibus' ),
				/** meta */
				'woocommerce_product_meta_start'           => esc_html__( 'Before the product meta data', 'omnibus' ),
				'woocommerce_product_meta_end'             => esc_html__( 'After the product meta data', 'omnibus' ),
				/** product summary */
				'woocommerce_before_single_product_summary' => esc_html__( 'Before the single product summary', 'omnibus' ),
				'woocommerce_after_single_product_summary' => esc_html__( 'After the single product summary', 'omnibus' ),
				/** cart form */
				'woocommerce_before_add_to_cart_form'      => esc_html__( 'Before the add to cart form', 'omnibus' ),
				/** cart button */
				'woocommerce_before_add_to_cart_button'    => esc_html__( 'Before the add to cart button', 'omnibus' ),
				'woocommerce_after_add_to_cart_button'     => esc_html__( 'After the add to cart button', 'omnibus' ),
				/** cart quantity */
				'woocommerce_before_add_to_cart_quantity'  => esc_html__( 'Before the add to cart quantity', 'omnibus' ),
				'woocommerce_after_add_to_cart_quantity'   => esc_html__( 'After the add to cart quantity', 'omnibus' ),
				// 'woocommerce_single_product_summary'        => esc_html__( 'Single product summary', 'omnibus' ),
				/** content */
				'the_content_start'                        => esc_html__( 'At the begining of the content', 'omnibus' ),
				'the_content_end'                          => esc_html__( 'At the end of the content', 'omnibus' ),
			),
		);
		$settings[] = array(
			'type' => 'sectionend',
			'id'   => $this->get_name( 'sectionend' ),
		);
		/**
		 * messages
		 */
		$settings = array_merge( $settings, $this->settings_messages() );
		/**
		 * Reviews
		 *
		 * @since 2.3.0
		 */
		// $settings = array_merge( $settings, $this->settings_review() );
		return $settings;
	}

	/**
	 * run helper
	 *
	 * @since 1.1.0
	 */
	public function run( $context = 'view', $post_id = null ) {
		if ( empty( $post_id ) ) {
			$post_id = get_the_ID();
		}
		$product = wc_get_product( $post_id );
		if ( empty( $product ) ) {
			return;
		}
		$price_lowest = $this->get_lowest_price( $product );
		if ( empty( $price_lowest ) ) {
			return;
		}
		$message = $this->add_message( '', $price_lowest, 'wc_price' );
		if ( 'return' === $context ) {
			return $message;
		}
		echo $message;
	}

	/**
	 * the_content filter
	 *
	 * @since 1.1.0
	 */
	public function filter_the_content( $content ) {
		if ( 'product' !== get_post_type() ) {
			return $content;
		}
		if ( ! $this->should_it_show_up( get_the_ID() ) ) {
			return $content;
		}
		$message = $this->run( 'return' );
		switch ( get_option( $this->get_name( 'where' ), 'woocommerce_get_price_html' ) ) {
			case 'the_content_start':
				$content = $message . $content;
				break;
			case 'the_content_end':
				$content .= $message;
				break;
		}
		return $content;
	}

	/**
	 * get message by id
	 */
	public function action_get_message( $post_id = null ) {
		$this->run( 'view', $post_id );
	}

	/**
	 * shortcode to get message
	 *
	 * @since 1.2.3
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array( 'id' => null ),
			$atts,
			'iworks_omnibus_wc_lowest_price_message'
		);
		if ( empty( $atts['id'] ) ) {
			$atts['id'] = get_the_ID();
		}
		if ( empty( $atts['id'] ) ) {
			return;
		}
		return $this->run( 'return', $atts['id'] );
	}

	/**
	 * get price helper
	 *
	 * @since 2.0.2
	 */
	private function get_price( $product ) {
		/**
		 * check method_exists
		 *
		 * @since 1.2.1
		 */
		if ( ! is_object( $product ) ) {
			return;
		}
		/**
		 * check method_exists
		 *
		 * @since 1.2.1
		 */
		if ( ! method_exists( $product, 'get_sale_price' ) ) {
			return;
		}
		$price = $product->get_sale_price();
		if ( empty( $price ) ) {
			$price = $product->get_price();
		}
		return $price;
	}

	/**
	 * Filter to allow us to exclude meta keys from product duplication..
	 *
	 * @param array $exclude_meta The keys to exclude from the duplicate.
	 * @param array $existing_meta_keys The meta keys that the product already has.
	 *
	 * @since 2.0.3
	 */
	public function filter_woocommerce_duplicate_product_exclude_meta( $meta_to_exclude, $existing_meta_keys = array() ) {
		$meta_to_exclude[] = $this->meta_name;
		$meta_to_exclude[] = $this->last_price_drop_timestamp;
		return $meta_to_exclude;
	}

	/**
	 * Add configuration link to plugin_row_meta.
	 *
	 * @since 2.1.0
	 *
	 */
	public function filter_add_link_omnibus_configuration( $actions, $plugin_file, $plugin_data, $context ) {
		if ( 'woocommerce/woocommerce.php' !== $plugin_file ) {
			return $actions;
		}
		$settings_page_url  = add_query_arg(
			array(
				'page'    => 'wc-settings',
				'tab'     => 'products',
				'section' => $this->get_name(),
			),
			admin_url( 'admin.php' )
		);
		$actions['omnibus'] = sprintf(
			'<a href="%s">%s</a>',
			$settings_page_url,
			__( 'Omnibus', 'omnibus' )
		);
		return $actions;
	}

	/**
	 * WooCommerce show in cart
	 *
	 * @since 2.1.5
	 */
	public function filter_woocommerce_cart_item_price( $price, $cart_item, $cart_item_key ) {
		if ( 'yes' === get_option( $this->get_name( 'on_sale' ), 'yes' ) ) {
			if ( ! $cart_item['data']->is_on_sale() ) {
				return $price;
			}
		}
		$price_lowest = $this->get_lowest_price( $cart_item['data'] );
		if ( empty( $price_lowest ) ) {
			return $price;
		}
		return $this->add_message( $price, $price_lowest, 'wc_price' );
	}

	/**
	 * WooCommerce show at start or end of product meta
	 *
	 * @since 2.1.7
	 */
	public function action_check_and_add_message() {
		if ( ! is_singular( 'product' ) ) {
			return;
		}
		if ( ! is_main_query() ) {
			return;
		}
		if ( ! $this->should_it_show_up( get_the_ID() ) ) {
			return;
		}
		$this->run( get_the_ID() );
	}

	/**
	 * Reviews Settings
	 *
	 * @since 2.3.0
	 */
	private function settings_review() {
		if ( 'yes' !== get_option( 'woocommerce_enable_reviews', 'yes' ) ) {
			return array();
		}
		$settings = array(
			array(
				'title' => __( 'Reviews', 'omnibus' ),
				'type'  => 'title',
				'id'    => $this->get_name( 'reviews' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => $this->get_name( 'reviews_sectionend' ),
			),
		);
		return $settings;
	}

	/**
	 * maybe save product price
	 */
	public function action_shutdown_maybe_save_product_price() {
		if ( ! is_singular( 'product' ) ) {
			return;
		}
		if ( ! empty( get_post_meta( get_the_ID(), $this->get_name() ) ) ) {
			return;
		}
		global $product;
		$data = array(
			'price'     => $this->get_price( $product ),
			'timestamp' => get_the_modified_date( 'U' ),
			'type'      => 'autosaved',
		);
		if ( empty( $data['price'] ) ) {
			return;
		}
		add_post_meta( $product->get_ID(), $this->meta_name, $data );
	}

	public function filter_woocommerce_get_settings_pages( $settings ) {
		$settings[] = include __DIR__ . '/class-iworks-omnibus-integration-woocommerce-settings.php';
		return $settings;
	}

	public function filter_iworks_omnibus_message_template_for_admin_list( $template ) {
		if ( ! is_admin() ) {
			return $template;
		}
		$screen = get_current_screen();
		if ( empty( $screen ) ) {
			return $template;
		}
		if ( ! is_a( $screen, 'WP_Screen' ) ) {
			return $template;
		}
		if ( 'product' !== $screen->post_type ) {
			return $template;
		}
		if ( 'no' === get_option( $this->get_name( 'admin_list_short' ), 'no' ) ) {
			return $template;
		}
		return __( 'OD: {price}', 'omnibus' );
	}

	public function filter_iworks_omnibus_message_template_for_product( $template, $price, $price_lowest ) {
		if ( is_admin() ) {
			return $template;
		}
		/**
		 * short term product
		 */
		$post_id = get_the_ID();
		if ( 'yes' === get_post_meta( $post_id, $this->get_name( 'is_short' ), true ) ) {
			switch ( get_option( $this->get_name( 'short_message' ), 'no' ) ) {
				case 'no':
					return '';
				case 'inform':
					if ( 'yes' == get_option( $this->get_name( 'message_settings' ), 'no' ) ) {
						$v = get_option( $this->get_name( 'message_short' ), false );
						if ( ! empty( $v ) ) {
							return $v;
						}
					}
					return __( 'This is short term product.', 'omnibus' );
			}
		}
		/**
		 * no price
		 */
		if (
			empty( $price_lowest )
			|| (
				isset( $price_lowest['type'] )
				&& 'autosaved' === $price_lowest['type']
			)
		) {
			switch ( get_option( $this->get_name( 'missing' ), 'current' ) ) {
				case 'no':
					return '';
				case 'custom':
					if ( 'yes' == get_option( $this->get_name( 'message_settings' ), 'no' ) ) {
						$v = get_option( $this->get_name( 'message_no_data' ), false );
						if ( ! empty( $v ) ) {
							return $v;
						}
					}
					return __( 'The previous price is not available.', 'omnibus' );
			}
		}
		return $template;
	}

	public function woocommerce_wp_checkbox_short( $post_id ) {
		if ( 'no' === get_option( $this->get_name( 'admin_short' ), 'no' ) ) {
			return;
		}
		woocommerce_wp_checkbox(
			wp_parse_args(
				array(
					'id'          => $this->get_name( 'is_short' ),
					'value'       => get_post_meta( $post_id, $this->get_name( 'is_short' ), true ),
					'label'       => __( 'Hide Omnibus', 'omnibus' ),
					'description' => sprintf(
						__( 'This is a short-term product, keep the message hidden. ', 'omnibus' ),
						$this->get_days()
					),
				),
				$configuration
			)
		);
	}

	public function action_woocommerce_save_maybe_save_short( $product ) {
		$short = filter_input( INPUT_POST, $this->get_name( 'is_short' ) );
		if ( 'yes' === $short ) {
			if ( ! update_post_meta( $product->id, $this->get_name( 'is_short' ), 'yes' ) ) {
				add_post_meta( $product->id, $this->get_name( 'is_short' ), 'yes', true );
			}
		} else {
			delete_post_meta( $product->id, $this->get_name( 'is_short' ) );
		}
	}

}
