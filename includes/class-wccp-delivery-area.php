<?php
/**
 * Checkout delivery-area selector and server-side fee calculation.
 *
 * @package WCCP_Custom_Checkout
 */

defined( 'ABSPATH' ) || exit;

final class WCCP_Delivery_Area {
	const FIELD_KEY   = 'billing_delivery_area';
	const SESSION_KEY = 'wccp_delivery_area';

	/** Register the checkout, validation, totals, and order hooks. */
	public function hooks() {
		add_filter( 'woocommerce_checkout_fields', array( $this, 'register_field' ), 0 );
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'update_session' ) );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_fee' ), 20 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_selection' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_order_meta' ), 10, 2 );
		add_filter( 'woocommerce_checkout_get_value', array( $this, 'restore_value' ), 10, 2 );
		add_filter( 'woocommerce_cart_needs_shipping', array( $this, 'disable_default_shipping' ), 999 );
		add_filter( 'woocommerce_cart_needs_shipping_address', array( $this, 'disable_default_shipping' ), 999 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_admin_value' ), 5 );
	}

	/** Add the field early so the plugin's field manager can capture and configure it. */
	public function register_field( $fields ) {
		if ( ! isset( $fields['billing'] ) || ! is_array( $fields['billing'] ) ) {
			$fields['billing'] = array();
		}

		$fields['billing'][ self::FIELD_KEY ] = array(
			'type'        => 'radio',
			'label'       => __( 'ডেলিভারি এলাকা', 'wccp-custom-checkout' ),
			'required'    => true,
			'priority'    => 85,
			'class'       => array( 'form-row-wide', 'wccp-delivery-area-field' ),
			'input_class' => array( 'wccp-delivery-area-option' ),
			'options'     => $this->option_labels(),
		);

		return $fields;
	}

	/** Store an allowlisted selection before WooCommerce recalculates totals. */
	public function update_session( $post_data ) {
		if ( ! is_scalar( $post_data ) || ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		$values = array();
		parse_str( (string) $post_data, $values );
		$value = isset( $values[ self::FIELD_KEY ] ) && is_scalar( $values[ self::FIELD_KEY ] )
			? sanitize_key( wp_unslash( (string) $values[ self::FIELD_KEY ] ) )
			: '';

		WC()->session->set( self::SESSION_KEY, isset( $this->rates()[ $value ] ) ? $value : '' );
	}

	/** Add only the server-defined amount; no client-submitted price is trusted. */
	public function add_fee( $cart ) {
		if ( ( is_admin() && ! wp_doing_ajax() ) || ! $this->is_enabled() || ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		$value = sanitize_key( (string) WC()->session->get( self::SESSION_KEY, '' ) );
		$rates = $this->rates();
		if ( ! isset( $rates[ $value ] ) || ! is_object( $cart ) || ! is_callable( array( $cart, 'add_fee' ) ) ) {
			return;
		}

		$cart->add_fee(
			sprintf( __( 'ডেলিভারি চার্জ (%s)', 'wccp-custom-checkout' ), $rates[ $value ]['area'] ),
			(float) $rates[ $value ]['cost'],
			false
		);
	}

	/** Reject fabricated choices and synchronize the final validated value. */
	public function validate_selection( $data, $errors ) {
		if ( ! $this->is_enabled() ) {
			$this->clear_session();
			return;
		}

		$value = isset( $data[ self::FIELD_KEY ] ) && is_scalar( $data[ self::FIELD_KEY ] )
			? sanitize_key( (string) $data[ self::FIELD_KEY ] )
			: '';
		$rates = $this->rates();

		if ( '' !== $value && ! isset( $rates[ $value ] ) ) {
			$errors->add( 'wccp_invalid_delivery_area', __( 'Please select a valid delivery area.', 'wccp-custom-checkout' ) );
			$this->clear_session();
			return;
		}

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( self::SESSION_KEY, $value );
		}
	}

	/** Persist the selected area through the HPOS-compatible order object. */
	public function save_order_meta( $order, $data ) {
		$value = isset( $data[ self::FIELD_KEY ] ) && is_scalar( $data[ self::FIELD_KEY ] )
			? sanitize_key( (string) $data[ self::FIELD_KEY ] )
			: '';
		$rates = $this->rates();
		if ( $this->is_enabled() && isset( $rates[ $value ] ) ) {
			$order->update_meta_data( '_wccp_delivery_area', $value );
			$order->update_meta_data( '_wccp_delivery_area_label', $rates[ $value ]['area'] );
		}
	}

	/** Keep the chosen radio selected after checkout AJAX refreshes. */
	public function restore_value( $value, $input ) {
		if ( self::FIELD_KEY !== $input || '' !== (string) $value || ! function_exists( 'WC' ) || ! WC()->session ) {
			return $value;
		}
		$stored = sanitize_key( (string) WC()->session->get( self::SESSION_KEY, '' ) );
		return isset( $this->rates()[ $stored ] ) ? $stored : $value;
	}

	/** Replace WooCommerce shipping selection while this delivery field is enabled. */
	public function disable_default_shipping( $needs_shipping ) {
		return $this->is_enabled() ? false : $needs_shipping;
	}

	/** Show the area in the order screen; the charged amount remains in order totals. */
	public function display_admin_value( $order ) {
		if ( ! is_object( $order ) || ! is_callable( array( $order, 'get_meta' ) ) || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		$label = $order->get_meta( '_wccp_delivery_area_label', true );
		if ( is_scalar( $label ) && '' !== (string) $label ) {
			echo '<p><strong>' . esc_html__( 'Delivery area:', 'wccp-custom-checkout' ) . '</strong> ' . esc_html( (string) $label ) . '</p>';
		}
	}

	/** Determine whether the field manager has disabled this field. */
	private function is_enabled() {
		$configs = get_option( WCCP_Defaults::FIELDS_OPTION, array() );
		if ( ! is_array( $configs ) || ! isset( $configs[ self::FIELD_KEY ] ) || ! is_array( $configs[ self::FIELD_KEY ] ) ) {
			return true;
		}
		return ! isset( $configs[ self::FIELD_KEY ]['enabled'] ) || 'no' !== $configs[ self::FIELD_KEY ]['enabled'];
	}

	/** Clear stale checkout state when the field is disabled or invalid. */
	private function clear_session() {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( self::SESSION_KEY, '' );
		}
	}

	/** Return public radio labels including their fixed prices. */
	private function option_labels() {
		$options = array();
		foreach ( $this->rates() as $key => $rate ) {
			$options[ $key ] = $rate['option'];
		}
		return $options;
	}

	/** Central server-side allowlist of valid areas and charge amounts. */
	private function rates() {
		$rates    = array();
		$defaults = WCCP_Defaults::delivery_areas();
		foreach ( WCCP_Defaults::get_delivery_areas() as $key => $area ) {
			$label = isset( $area['label'] ) && is_scalar( $area['label'] ) ? sanitize_text_field( (string) $area['label'] ) : '';
			$cost  = isset( $area['cost'] ) && is_scalar( $area['cost'] ) ? wc_format_decimal( (string) $area['cost'] ) : '';
			$label = '' !== $label ? WCCP_Settings::limit( $label, 120 ) : $defaults[ $key ]['label'];
			$cost  = is_numeric( $cost ) && (float) $cost >= 0 && (float) $cost <= 1000000 ? $cost : $defaults[ $key ]['cost'];
			$display_cost = function_exists( 'wc_format_localized_price' ) ? wc_format_localized_price( $cost ) : $cost;
			$rates[ $key ] = array(
				'area'   => $label,
				'option' => sprintf( __( '%1$s — %2$s টাকা', 'wccp-custom-checkout' ), $label, $display_cost ),
				'cost'   => $cost,
			);
		}
		return $rates;
	}
}
