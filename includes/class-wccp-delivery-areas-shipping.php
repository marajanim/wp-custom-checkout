<?php
/**
 * Zone-based delivery area shipping rates.
 *
 * @package WCCP_Custom_Checkout
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Shipping_Method' ) ) {
	return;
}

final class WCCP_Delivery_Areas_Shipping_Method extends WC_Shipping_Method {
	/**
	 * Configure a shipping-zone method instance.
	 *
	 * @param int $instance_id Shipping-zone instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'wccp_delivery_areas';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'WCCP Delivery Areas', 'wccp-custom-checkout' );
		$this->method_description = __( 'Three selectable delivery rates for Dhaka, nearby areas, and outside Dhaka.', 'wccp-custom-checkout' );
		$this->supports           = array( 'shipping-zones', 'instance-settings', 'instance-settings-modal' );
		$this->init();
	}

	/** Initialize fields, saved settings, and the secure save callback. */
	private function init() {
		$this->init_instance_form_fields();
		$this->init_settings();
		$this->title      = $this->get_option( 'title', __( 'Delivery area', 'wccp-custom-checkout' ) );
		$this->tax_status = $this->get_option( 'tax_status', 'none' );
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/** Define allowlisted settings for each shipping-zone instance. */
	public function init_instance_form_fields() {
		$cost_description = __( 'Enter a non-negative amount in the store currency.', 'wccp-custom-checkout' );
		$this->instance_form_fields = array(
			'title'         => array(
				'title'       => __( 'Method title', 'wccp-custom-checkout' ),
				'type'        => 'text',
				'default'     => __( 'Delivery area', 'wccp-custom-checkout' ),
				'description' => __( 'Shown as the shipping method heading in administration.', 'wccp-custom-checkout' ),
				'desc_tip'    => true,
			),
			'tax_status'    => array(
				'title'   => __( 'Tax status', 'wccp-custom-checkout' ),
				'type'    => 'select',
				'default' => 'none',
				'options' => array(
					'taxable' => __( 'Taxable', 'wccp-custom-checkout' ),
					'none'    => _x( 'None', 'Tax status', 'wccp-custom-checkout' ),
				),
			),
			'dhaka_inside_cost' => $this->cost_field( __( 'Dhaka city cost', 'wccp-custom-checkout' ), '60', $cost_description ),
			'dhaka_nearby_cost' => $this->cost_field( __( 'Nearby Dhaka cost', 'wccp-custom-checkout' ), '90', $cost_description ),
			'outside_dhaka_cost'=> $this->cost_field( __( 'Outside Dhaka cost', 'wccp-custom-checkout' ), '120', $cost_description ),
		);
	}

	/** Build one validated cost setting. */
	private function cost_field( $title, $default, $description ) {
		return array(
			'title'             => $title,
			'type'              => 'text',
			'class'             => 'wc-shipping-modal-price',
			'default'           => $default,
			'description'       => $description,
			'desc_tip'          => true,
			'sanitize_callback' => array( $this, 'sanitize_cost' ),
		);
	}

	/**
	 * Validate a merchant-entered shipping amount.
	 *
	 * @param mixed $value Raw setting value.
	 * @return string
	 * @throws Exception When the cost is invalid.
	 */
	public function sanitize_cost( $value ) {
		if ( ! is_scalar( $value ) ) {
			throw new Exception( esc_html__( 'Shipping cost must be a number.', 'wccp-custom-checkout' ) );
		}
		$value = wp_kses_post( trim( wp_unslash( (string) $value ) ) );
		$value = str_replace( array( get_woocommerce_currency_symbol(), html_entity_decode( get_woocommerce_currency_symbol() ) ), '', $value );
		$value = wc_format_decimal( $value );
		if ( '' === $value || ! is_numeric( $value ) || (float) $value < 0 ) {
			throw new Exception( esc_html__( 'Shipping cost must be a non-negative number.', 'wccp-custom-checkout' ) );
		}
		return $value;
	}

	/** Add the three server-calculated rates for the customer to choose. */
	public function calculate_shipping( $package = array() ) {
		$rates = array(
			'dhaka_inside' => array(
				'label' => __( 'ঢাকার ভিতরে', 'wccp-custom-checkout' ),
				'cost'  => $this->safe_cost( 'dhaka_inside_cost', '60' ),
			),
			'dhaka_nearby' => array(
				'label' => __( 'ঢাকার পার্শ্ববর্তী অঞ্চল (সাভার, গাজীপুর, নারায়ণগঞ্জ, কেরানীগঞ্জ)', 'wccp-custom-checkout' ),
				'cost'  => $this->safe_cost( 'dhaka_nearby_cost', '90' ),
			),
			'outside_dhaka' => array(
				'label' => __( 'ঢাকার বাইরে', 'wccp-custom-checkout' ),
				'cost'  => $this->safe_cost( 'outside_dhaka_cost', '120' ),
			),
		);

		foreach ( $rates as $rate_key => $rate ) {
			$this->add_rate(
				array(
					'id'      => $this->get_rate_id( $rate_key ),
					'label'   => $rate['label'],
					'cost'    => $rate['cost'],
					'package' => $package,
				)
			);
		}
	}

	/** Return a non-negative stored cost or its known-safe default. */
	private function safe_cost( $option, $default ) {
		$value = $this->get_option( $option, $default );
		if ( ! is_scalar( $value ) ) {
			return $default;
		}
		$value = wc_format_decimal( (string) $value );
		return is_numeric( $value ) && (float) $value >= 0 ? $value : $default;
	}
}
