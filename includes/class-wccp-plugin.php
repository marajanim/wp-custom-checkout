<?php
/**
 * Plugin lifecycle coordinator.
 *
 * @package WCCP_Custom_Checkout
 */

defined( 'ABSPATH' ) || exit;

final class WCCP_Plugin {
	/** @var WCCP_Plugin|null */
	private static $instance;

	/** @var WCCP_Fields|null */
	private $fields;

	/**
	 * Return the singleton instance.
	 *
	 * @return WCCP_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'boot' ), 20 );
	}

	/**
	 * Start integrations only when WooCommerce is available.
	 */
	public function boot() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_required_notice' ) );
			return;
		}

		$this->fields = new WCCP_Fields();
		$this->fields->hooks();
		add_action( 'woocommerce_shipping_init', array( $this, 'load_shipping_method' ) );
		add_filter( 'woocommerce_shipping_methods', array( $this, 'register_shipping_method' ) );

		$checkout = new WCCP_Checkout();
		$checkout->hooks();

		if ( is_admin() ) {
			$admin = new WCCP_Admin( $this->fields );
			$admin->hooks();
		}
	}

	/** Load the shipping subclass only after WooCommerce initializes shipping. */
	public function load_shipping_method() {
		if ( ! class_exists( 'WCCP_Delivery_Areas_Shipping_Method' ) ) {
			require_once WCCP_PATH . 'includes/class-wccp-delivery-areas-shipping.php';
		}
	}

	/** Register the delivery-area method with WooCommerce shipping zones. */
	public function register_shipping_method( $methods ) {
		$methods['wccp_delivery_areas'] = 'WCCP_Delivery_Areas_Shipping_Method';
		return $methods;
	}

	/**
	 * Seed options without overwriting merchant configuration.
	 */
	public static function activate() {
		add_option( WCCP_Defaults::SETTINGS_OPTION, WCCP_Defaults::settings(), '', 'no' );
		add_option( WCCP_Defaults::FIELDS_OPTION, array(), '', 'no' );
		add_option( WCCP_Defaults::CUSTOM_OPTION, array(), '', 'no' );
		add_option( WCCP_Defaults::DELETE_OPTION, 'no', '', 'no' );
	}

	/**
	 * Explain the missing dependency without exposing environment details.
	 */
	public function woocommerce_required_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'WCCP Custom Checkout requires WooCommerce. Install and activate WooCommerce before configuring this plugin.', 'wccp-custom-checkout' ) . '</p></div>';
	}
}
