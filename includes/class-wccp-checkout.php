<?php
/**
 * Classic checkout presentation and policy integration.
 *
 * @package WCCP_Custom_Checkout
 */

defined( 'ABSPATH' ) || exit;

final class WCCP_Checkout {
	/**
	 * Register front-end hooks.
	 */
	public function hooks() {
		add_action( 'wp', array( $this, 'configure_checkout_hooks' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'body_class', array( $this, 'body_classes' ) );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'progress' ), 5 );
		add_filter( 'woocommerce_get_privacy_policy_text', array( $this, 'privacy_text' ), 999, 2 );
		add_filter( 'woocommerce_get_terms_and_conditions_checkbox_text', array( $this, 'agreement_text' ), 999 );
		add_filter( 'woocommerce_terms_is_checked_default', array( $this, 'agreement_checked' ) );
		add_filter( 'woocommerce_checkout_show_terms', array( $this, 'show_terms' ) );
		add_filter( 'woocommerce_cart_item_name', array( $this, 'cart_item_name' ), 20, 3 );
		add_filter( 'woocommerce_checkout_cart_item_quantity', array( $this, 'checkout_cart_item_quantity' ), PHP_INT_MAX, 3 );
		add_filter( 'woocommerce_ship_to_different_address_checked', array( $this, 'shipping_checked' ) );
		add_filter( 'woocommerce_checkout_get_value', array( $this, 'checkout_field_value' ), 999, 2 );
		add_filter( 'woocommerce_persistent_cart_enabled', array( $this, 'persistent_cart_enabled' ), 999 );
		add_filter( 'wp_headers', array( $this, 'private_checkout_headers' ), PHP_INT_MAX );
		add_action( 'template_redirect', array( $this, 'mark_checkout_private' ), PHP_INT_MAX );
	}

	/**
	 * Prevent page caches and reverse proxies from storing customer-specific checkout HTML.
	 */
	public function mark_checkout_private() {
		if ( ! $this->is_checkout_request() ) {
			return;
		}
		if ( class_exists( 'WC_Cache_Helper' ) ) {
			WC_Cache_Helper::set_nocache_constants();
		} elseif ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( headers_sent() ) {
			return;
		}
		if ( function_exists( 'wc_nocache_headers' ) ) {
			wc_nocache_headers();
		} else {
			nocache_headers();
		}
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
		header( 'CDN-Cache-Control: no-store', true );
		header( 'Surrogate-Control: no-store', true );
		header( 'X-Accel-Expires: 0', true );
	}

	/** Add explicit private response headers at the latest WordPress header-filter priority. */
	public function private_checkout_headers( $headers ) {
		if ( ! $this->is_checkout_request() ) {
			return $headers;
		}
		if ( class_exists( 'WC_Cache_Helper' ) ) {
			WC_Cache_Helper::set_nocache_constants();
		}
		$headers['Cache-Control']     = 'private, no-store, no-cache, must-revalidate, max-age=0';
		$headers['Pragma']            = 'no-cache';
		$headers['Expires']           = 'Wed, 11 Jan 1984 05:00:00 GMT';
		$headers['CDN-Cache-Control'] = 'no-store';
		$headers['Surrogate-Control'] = 'no-store';
		$headers['X-Accel-Expires']   = '0';
		$vary = isset( $headers['Vary'] ) ? (string) $headers['Vary'] : '';
		if ( false === stripos( $vary, 'Cookie' ) ) {
			$headers['Vary'] = '' === $vary ? 'Cookie' : $vary . ', Cookie';
		}
		unset( $headers['ETag'], $headers['Last-Modified'] );
		return $headers;
	}

	/**
	 * Optionally prevent saved account/session PII from prefilling a fresh checkout page.
	 */
	public function checkout_field_value( $value, $input ) {
		$settings = WCCP_Defaults::get_settings();
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) && is_scalar( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';
		if ( 'yes' === $settings['prefill_customer_details'] || 'GET' !== $request_method || ! $this->is_classic_checkout_request() ) {
			return $value;
		}

		$private_fields = array(
			'billing_first_name', 'billing_last_name', 'billing_company', 'billing_address_1', 'billing_address_2',
			'billing_city', 'billing_postcode', 'billing_phone', 'billing_email',
			'shipping_first_name', 'shipping_last_name', 'shipping_company', 'shipping_address_1', 'shipping_address_2',
			'shipping_city', 'shipping_postcode',
		);
		return in_array( $input, $private_fields, true ) ? '' : $value;
	}

	/** Keep carts device-session-specific unless the merchant explicitly enables account persistence. */
	public function persistent_cart_enabled( $enabled ) {
		$settings = WCCP_Defaults::get_settings();
		return 'yes' === $settings['share_cart_across_devices'] ? $enabled : false;
	}

	/**
	 * Remove optional standard callbacks after WordPress resolves the request.
	 */
	public function configure_checkout_hooks() {
		if ( ! $this->is_custom_checkout() ) {
			return;
		}
		$settings = WCCP_Defaults::get_settings();
		if ( 'yes' !== $settings['show_coupon'] ) {
			remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
		}
	}

	/**
	 * Enqueue scoped assets only for the classic checkout.
	 */
	public function enqueue_assets() {
		if ( ! $this->is_custom_checkout() ) {
			return;
		}
		$css_path = WCCP_PATH . 'assets/css/frontend.css';
		$js_path  = WCCP_PATH . 'assets/js/frontend.js';
		wp_enqueue_style( 'wccp-checkout', WCCP_URL . 'assets/css/frontend.css', array(), file_exists( $css_path ) ? (string) filemtime( $css_path ) : WCCP_VERSION );
		wp_enqueue_script( 'wccp-checkout', WCCP_URL . 'assets/js/frontend.js', array( 'jquery' ), file_exists( $js_path ) ? (string) filemtime( $js_path ) : WCCP_VERSION, true );

		$settings = WCCP_Defaults::get_settings();
		$font_size = max( 13, min( 22, absint( $settings['font_size'] ) ) );
		$variables = sprintf(
			'.wccp-enabled{--wccp-primary:%1$s;--wccp-button:%2$s;--wccp-background:%3$s;--wccp-border:%4$s;--wccp-font-size:%5$dpx;}',
			esc_attr( $settings['primary_color'] ),
			esc_attr( $settings['button_color'] ),
			esc_attr( $settings['background_color'] ),
			esc_attr( $settings['border_color'] ),
			$font_size
		);
		wp_add_inline_style( 'wccp-checkout', $variables );
		wp_localize_script(
			'wccp-checkout',
			'wccpCheckout',
			array(
				'movePayment'       => true,
				'showBillingHeading'=> 'yes' === $settings['show_billing_heading'],
				'showShipping'      => 'yes' === $settings['show_shipping'],
				'stickySummary'     => 'yes' === $settings['sticky_order_summary'],
				'showPaymentHeading'=> 'yes' === $settings['show_payment_heading'],
				'paymentHeading'    => __( 'Payment Information', 'wccp-custom-checkout' ),
			)
		);
	}

	/**
	 * Add tightly scoped state classes.
	 */
	public function body_classes( $classes ) {
		if ( $this->is_custom_checkout() ) {
			$classes[] = 'wccp-enabled';
			$settings  = WCCP_Defaults::get_settings();
			if ( 'yes' === $settings['sticky_order_summary'] ) {
				$classes[] = 'wccp-sticky-summary';
			}
			if ( 'yes' !== $settings['show_shipping'] ) {
				$classes[] = 'wccp-hide-shipping';
			}
		}
		return array_values( array_unique( $classes ) );
	}

	/**
	 * Render the checkout progress banner.
	 */
	public function progress() {
		$settings = WCCP_Defaults::get_settings();
		if ( ! $this->is_custom_checkout() || 'yes' !== $settings['show_progress'] ) {
			return;
		}
		?>
		<nav class="wccp-progress" aria-label="<?php esc_attr_e( 'Checkout progress', 'wccp-custom-checkout' ); ?>">
			<a href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php esc_html_e( 'Shopping Cart', 'wccp-custom-checkout' ); ?></a>
			<span aria-hidden="true">→</span>
			<span class="is-active" aria-current="step"><?php esc_html_e( 'Checkout', 'wccp-custom-checkout' ); ?></span>
			<span aria-hidden="true">→</span>
			<span><?php esc_html_e( 'Order Complete', 'wccp-custom-checkout' ); ?></span>
		</nav>
		<?php
	}

	/**
	 * Suppress duplicate privacy copy only while combined agreement is active.
	 */
	public function privacy_text( $text, $type ) {
		unset( $type );
		$settings = WCCP_Defaults::get_settings();
		return $this->is_classic_checkout_request() && 'yes' === $settings['enable_agreement'] ? '' : $text;
	}

	/** Ensure the configured combined agreement remains required. */
	public function show_terms( $show ) {
		$settings = WCCP_Defaults::get_settings();
		return $this->is_classic_checkout_request() && 'yes' === $settings['enable_agreement'] ? true : $show;
	}

	/**
	 * Build the configured combined agreement label.
	 */
	public function agreement_text( $text ) {
		$settings = WCCP_Defaults::get_settings();
		if ( ! $this->is_classic_checkout_request() || 'yes' !== $settings['enable_agreement'] ) {
			return $text;
		}

		$links = array();
		foreach ( array( 'terms', 'privacy', 'return', 'delivery' ) as $policy ) {
			$label = $settings[ $policy . '_label' ];
			$url   = $settings[ $policy . '_url' ];
			if ( '' === $label || '' === $url ) {
				continue;
			}
			$target  = 'yes' === $settings['open_links_new_tab'] ? ' target="_blank" rel="noopener noreferrer"' : '';
			$links[] = '<a href="' . esc_url( $url ) . '"' . $target . '>' . esc_html( $label ) . '</a>';
		}

		if ( empty( $links ) ) {
			return $text;
		}
		$last = array_pop( $links );
		$list = empty( $links ) ? $last : implode( ', ', $links ) . ' ' . esc_html__( 'and', 'wccp-custom-checkout' ) . ' ' . $last;
		$html = esc_html( $settings['agreement_intro'] ) . ' ' . $list . '.';
		return wp_kses(
			$html,
			array(
				'a' => array( 'href' => true, 'target' => true, 'rel' => true ),
			)
		);
	}

	/**
	 * Apply the configured checkbox default only to classic checkout.
	 */
	public function agreement_checked( $checked ) {
		$settings = WCCP_Defaults::get_settings();
		if ( $this->is_classic_checkout_request() && 'yes' === $settings['enable_agreement'] ) {
			return 'yes' === $settings['agreement_checked'];
		}
		return $checked;
	}

	/**
	 * Enhance review product names with thumbnail and optional SKU.
	 */
	public function cart_item_name( $name, $cart_item, $cart_item_key ) {
		unset( $cart_item_key );
		if ( ! $this->is_classic_checkout_request() || false !== strpos( $name, 'wccp-order-quantity' ) ) {
			return $name;
		}
		$quantity = isset( $cart_item['quantity'] ) && is_numeric( $cart_item['quantity'] ) ? max( 1, absint( $cart_item['quantity'] ) ) : 1;
		$quantity_html = '<span class="wccp-order-quantity"><span>' . esc_html__( 'Quantity:', 'wccp-custom-checkout' ) . '</span> <strong>' . esc_html( (string) $quantity ) . '</strong></span>';
		$theme = function_exists( 'wp_get_theme' ) ? wp_get_theme() : null;
		$template = $theme && is_callable( array( $theme, 'get_template' ) ) ? strtolower( (string) $theme->get_template() ) : '';
		if ( false !== strpos( $template, 'woodmart' ) ) {
			return $name . $quantity_html;
		}
		$settings = WCCP_Defaults::get_settings();
		$product  = isset( $cart_item['data'] ) && $cart_item['data'] instanceof WC_Product ? $cart_item['data'] : null;
		if ( ! $product ) {
			return $name . $quantity_html;
		}

		$image = '';
		if ( 'yes' === $settings['show_product_images'] ) {
			$image = $product->get_image( array( 64, 64 ), array( 'class' => 'wccp-product-image', 'loading' => 'lazy' ) );
		}
		$meta = '';
		if ( 'yes' === $settings['show_product_meta'] && $product->get_sku() ) {
			$meta = '<small class="wccp-product-sku">' . esc_html__( 'SKU:', 'wccp-custom-checkout' ) . ' ' . esc_html( $product->get_sku() ) . '</small>';
		}
		return '<span class="wccp-product-name">' . wp_kses_post( $image ) . '<span class="wccp-product-copy">' . wp_kses_post( $name ) . $meta . $quantity_html . '</span></span>';
	}

	/**
	 * Remove WoodMart/WooCommerce interactive quantity controls from checkout.
	 *
	 * The cart item name already contains the server-rendered read-only quantity
	 * badge, so checkout does not need a second quantity element.
	 */
	public function checkout_cart_item_quantity( $quantity_html, $cart_item, $cart_item_key ) {
		unset( $cart_item, $cart_item_key );
		return $this->is_classic_checkout_request() ? '' : $quantity_html;
	}

	/**
	 * Force billing-as-shipping when the optional shipping section is hidden.
	 */
	public function shipping_checked( $checked ) {
		$settings = WCCP_Defaults::get_settings();
		return $this->is_classic_checkout_request() && 'yes' !== $settings['show_shipping'] ? false : $checked;
	}

	/**
	 * Determine whether this request is part of classic checkout processing.
	 */
	private function is_checkout_request() {
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}
		$request_action = isset( $_GET['wc-ajax'] ) && is_scalar( $_GET['wc-ajax'] ) ? $_GET['wc-ajax'] : ( isset( $_POST['wc-ajax'] ) && is_scalar( $_POST['wc-ajax'] ) ? $_POST['wc-ajax'] : '' );
		if ( wp_doing_ajax() && '' !== $request_action ) {
			$action = sanitize_key( (string) wp_unslash( $request_action ) );
			return in_array( $action, array( 'update_order_review', 'checkout', 'apply_coupon', 'remove_coupon' ), true );
		}
		return false;
	}

	/** Exclude Checkout Block pages from all classic-checkout behavior. */
	private function is_classic_checkout_request() {
		if ( ! $this->is_checkout_request() ) {
			return false;
		}
		if ( ! wp_doing_ajax() && function_exists( 'has_block' ) ) {
			$post = get_post();
			if ( $post && has_block( 'woocommerce/checkout', $post ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Determine whether custom classic-checkout presentation is active.
	 */
	private function is_custom_checkout() {
		$settings = WCCP_Defaults::get_settings();
		if ( 'yes' !== $settings['enable_layout'] || ! $this->is_classic_checkout_request() ) {
			return false;
		}
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
			return false;
		}
		return true;
	}
}
