<?php
/**
 * Default configuration.
 *
 * @package WCCP_Custom_Checkout
 */

defined( 'ABSPATH' ) || exit;

final class WCCP_Defaults {
	const SETTINGS_OPTION = 'wccp_settings';
	const FIELDS_OPTION   = 'wccp_field_settings';
	const CUSTOM_OPTION   = 'wccp_custom_fields';
	const DELIVERY_OPTION = 'wccp_delivery_areas';
	const DELETE_OPTION   = 'wccp_delete_data_on_uninstall';

	/** Return the three editable delivery-area defaults. */
	public static function delivery_areas() {
		return array(
			'dhaka_inside' => array(
				'label' => 'ঢাকার ভিতরে',
				'cost'  => '60',
			),
			'dhaka_nearby' => array(
				'label' => 'ঢাকার পার্শ্ববর্তী অঞ্চল (সাভার, গাজীপুর, নারায়ণগঞ্জ, কেরানীগঞ্জ)',
				'cost'  => '90',
			),
			'outside_dhaka' => array(
				'label' => 'ঢাকার বাইরে',
				'cost'  => '120',
			),
		);
	}

	/** Return saved delivery areas merged only against known area keys. */
	public static function get_delivery_areas() {
		$saved  = get_option( self::DELIVERY_OPTION, array() );
		$saved  = is_array( $saved ) ? $saved : array();
		$areas  = array();
		foreach ( self::delivery_areas() as $key => $default ) {
			$areas[ $key ] = isset( $saved[ $key ] ) && is_array( $saved[ $key ] )
				? wp_parse_args( $saved[ $key ], $default )
				: $default;
		}
		return $areas;
	}

	/**
	 * Return safe plugin defaults.
	 *
	 * @return array<string, string>
	 */
	public static function settings() {
		return array(
			'enable_layout'          => 'yes',
			'enable_agreement'       => 'yes',
			'agreement_intro'        => 'I have read and agree to the',
			'open_links_new_tab'     => 'yes',
			'agreement_checked'      => 'yes',
			'primary_color'          => '#0862bd',
			'button_color'           => '#052779',
			'background_color'       => '#f5f5f5',
			'border_color'           => '#e7e7e7',
			'show_progress'          => 'yes',
			'show_coupon'            => 'yes',
			'show_billing_heading'   => 'yes',
			'show_shipping'          => 'yes',
			'show_order_notes'       => 'yes',
			'show_product_images'    => 'yes',
			'show_product_meta'      => 'yes',
			'sticky_order_summary'   => 'yes',
			'show_payment_heading'   => 'yes',
			'delete_on_uninstall'    => 'no',
			'terms_label'            => 'Terms & Conditions',
			'terms_url'              => 'https://sinogemsbd.com/terms-conditions/',
			'privacy_label'          => 'Privacy Policy',
			'privacy_url'            => 'https://sinogemsbd.com/privacy-policy-2/',
			'return_label'           => 'Return & Refund Policy',
			'return_url'             => 'https://sinogemsbd.com/return-policy/',
			'delivery_label'         => 'Delivery Policy',
			'delivery_url'           => 'https://sinogemsbd.com/delivery-policy/',
		);
	}

	/**
	 * Get merged settings.
	 *
	 * @return array<string, string>
	 */
	public static function get_settings() {
		$saved = get_option( self::SETTINGS_OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::settings() );
	}
}
