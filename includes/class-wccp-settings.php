<?php
/**
 * Settings validation and persistence helpers.
 *
 * @package WCCP_Custom_Checkout
 */

defined( 'ABSPATH' ) || exit;

final class WCCP_Settings {
	/** @var string[] */
	private static $boolean_keys = array(
		'enable_layout', 'prefill_customer_details', 'share_cart_across_devices', 'enable_agreement', 'open_links_new_tab', 'agreement_checked',
		'show_progress', 'show_coupon', 'show_billing_heading', 'show_shipping',
		'show_order_notes', 'show_product_images', 'show_product_meta',
		'sticky_order_summary', 'show_payment_heading', 'delete_on_uninstall',
	);

	/** @var string[] */
	private static $text_keys = array(
		'agreement_intro', 'terms_label', 'privacy_label', 'return_label', 'delivery_label',
	);

	/** @var string[] */
	private static $url_keys = array( 'terms_url', 'privacy_url', 'return_url', 'delivery_url' );

	/** @var string[] */
	private static $color_keys = array( 'primary_color', 'button_color', 'background_color', 'border_color' );

	/**
	 * Sanitize the complete settings form using an allowlist.
	 *
	 * @param mixed $raw Raw request value.
	 * @return array<string, string>
	 */
	public static function sanitize_settings( $raw ) {
		$raw      = is_array( $raw ) ? wp_unslash( $raw ) : array();
		$defaults = WCCP_Defaults::settings();
		$clean    = array();

		foreach ( self::$boolean_keys as $key ) {
			$clean[ $key ] = isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) && 'yes' === $raw[ $key ] ? 'yes' : 'no';
		}

		foreach ( self::$text_keys as $key ) {
			$value         = isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? sanitize_text_field( (string) $raw[ $key ] ) : $defaults[ $key ];
			$clean[ $key ] = self::limit( $value, 200 );
		}

		foreach ( self::$url_keys as $key ) {
			$value         = isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? (string) $raw[ $key ] : '';
			$clean[ $key ] = esc_url_raw( $value, array( 'http', 'https' ) );
		}

		foreach ( self::$color_keys as $key ) {
			$value         = isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? sanitize_hex_color( (string) $raw[ $key ] ) : '';
			$clean[ $key ] = $value ? $value : $defaults[ $key ];
		}

		$font_size = isset( $raw['font_size'] ) && is_scalar( $raw['font_size'] ) ? absint( $raw['font_size'] ) : absint( $defaults['font_size'] );
		$clean['font_size'] = (string) max( 13, min( 22, $font_size ) );

		return $clean;
	}

	/**
	 * Sanitize native checkout field overrides.
	 *
	 * @param mixed $raw Raw field map.
	 * @return array<string, array<string, mixed>>
	 */
	public static function sanitize_field_settings( $raw ) {
		$raw   = is_array( $raw ) ? wp_unslash( $raw ) : array();
		$clean = array();
		$count = 0;

		foreach ( $raw as $field_key => $config ) {
			if ( $count >= 150 || ! is_array( $config ) ) {
				break;
			}

			$field_key = (string) $field_key;
			$key = sanitize_key( $field_key );
			if ( '' === $key || $key !== $field_key ) {
				continue;
			}

			$width   = isset( $config['width'] ) && is_scalar( $config['width'] ) ? sanitize_key( (string) $config['width'] ) : 'full';
			$section = isset( $config['section'] ) && is_scalar( $config['section'] ) ? sanitize_key( (string) $config['section'] ) : '';
			$label   = isset( $config['label'] ) && is_scalar( $config['label'] ) ? (string) $config['label'] : '';
			$holder  = isset( $config['placeholder'] ) && is_scalar( $config['placeholder'] ) ? (string) $config['placeholder'] : '';
			$priority= isset( $config['priority'] ) && is_scalar( $config['priority'] ) ? $config['priority'] : 0;
			$clean[ $key ] = array(
				'enabled'     => isset( $config['enabled'] ) && 'yes' === $config['enabled'] ? 'yes' : 'no',
				'required'    => isset( $config['required'] ) && 'yes' === $config['required'] ? 'yes' : 'no',
				'label'       => self::limit( sanitize_text_field( $label ), 120 ),
				'placeholder' => self::limit( sanitize_text_field( $holder ), 160 ),
				'priority'    => max( 0, min( 9999, absint( $priority ) ) ),
				'width'       => in_array( $width, array( 'full', 'left', 'right' ), true ) ? $width : 'full',
				'section'     => in_array( $section, array( 'billing', 'shipping', 'account', 'order' ), true ) ? $section : '',
			);
			++$count;
		}

		return $clean;
	}

	/** Sanitize the fixed allowlist of editable delivery-area names and costs. */
	public static function sanitize_delivery_areas( $raw ) {
		$raw      = is_array( $raw ) ? wp_unslash( $raw ) : array();
		$defaults = WCCP_Defaults::delivery_areas();
		$clean    = array();

		foreach ( $defaults as $key => $default ) {
			$config = isset( $raw[ $key ] ) && is_array( $raw[ $key ] ) ? $raw[ $key ] : array();
			$label  = isset( $config['label'] ) && is_scalar( $config['label'] ) ? sanitize_text_field( (string) $config['label'] ) : '';
			$cost   = isset( $config['cost'] ) && is_scalar( $config['cost'] ) ? wc_format_decimal( (string) $config['cost'] ) : '';
			$cost   = is_numeric( $cost ) && (float) $cost >= 0 && (float) $cost <= 1000000 ? $cost : $default['cost'];

			$clean[ $key ] = array(
				'label' => '' !== $label ? self::limit( $label, 120 ) : $default['label'],
				'cost'  => $cost,
			);
		}

		return $clean;
	}

	/**
	 * Limit a string in a multibyte-safe way when available.
	 *
	 * @param string $value Value.
	 * @param int    $length Maximum characters.
	 * @return string
	 */
	public static function limit( $value, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}
}
