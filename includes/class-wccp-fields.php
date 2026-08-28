<?php
/**
 * Dynamic checkout fields and HPOS-safe order metadata.
 *
 * @package WCCP_Custom_Checkout
 */

defined( 'ABSPATH' ) || exit;

final class WCCP_Fields {
	/** @var array<string, array<string, array<string, mixed>>> */
	private $registered_fields = array();

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_filter( 'woocommerce_checkout_fields', array( $this, 'capture_fields' ), 1 );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'filter_fields' ), 900 );
		add_filter( 'woocommerce_enable_order_notes_field', array( $this, 'order_notes_enabled' ) );
		add_filter( 'woocommerce_form_field_wccp_heading', array( $this, 'render_heading' ), 10, 4 );
		add_filter( 'woocommerce_form_field_wccp_content', array( $this, 'render_content' ), 10, 4 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_custom_fields' ), 20, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_order_values' ), 20, 2 );
		add_action( 'woocommerce_checkout_update_user_meta', array( $this, 'save_customer_values' ), 20, 2 );
		add_filter( 'woocommerce_email_order_meta_fields', array( $this, 'email_fields' ), 20, 3 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'render_admin_values' ) );
		add_action( 'woocommerce_order_details_after_customer_details', array( $this, 'render_customer_values' ) );
	}

	/**
	 * Capture fields before this plugin changes them.
	 *
	 * @param array $fields Checkout fields.
	 * @return array
	 */
	public function capture_fields( $fields ) {
		if ( is_array( $fields ) ) {
			$this->registered_fields = $fields;
		}
		return $fields;
	}

	/**
	 * Return fields registered by WooCommerce and extensions.
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public function get_registered_fields() {
		if ( empty( $this->registered_fields ) && function_exists( 'WC' ) && WC()->checkout() ) {
			WC()->checkout()->get_checkout_fields();
		}
		return $this->registered_fields;
	}

	/**
	 * Apply native overrides and append plugin-owned custom fields.
	 *
	 * @param array $fields Checkout fields.
	 * @return array
	 */
	public function filter_fields( $fields ) {
		$configs = get_option( WCCP_Defaults::FIELDS_OPTION, array() );
		$configs = is_array( $configs ) ? $configs : array();

		$transformed = array( 'billing' => array(), 'shipping' => array(), 'account' => array(), 'order' => array() );
		foreach ( $fields as $group => $group_fields ) {
			if ( ! is_array( $group_fields ) ) {
				continue;
			}
			if ( ! isset( $transformed[ $group ] ) ) {
				$transformed[ $group ] = array();
			}
			foreach ( $group_fields as $key => $field ) {
				$config = isset( $configs[ $key ] ) && is_array( $configs[ $key ] ) ? $configs[ $key ] : array();
				if ( isset( $config['enabled'] ) && 'no' === $config['enabled'] ) {
					continue;
				}
				$target = isset( $config['section'] ) && isset( $transformed[ $config['section'] ] ) ? $config['section'] : $group;
				$transformed[ $target ][ $key ] = $config ? $this->apply_field_config( $field, $config ) : $field;
			}
		}
		$fields = $transformed;

		foreach ( $this->get_custom_fields() as $custom ) {
			if ( 'yes' !== $custom['enabled'] ) {
				continue;
			}
			$group = $custom['section'];
			if ( ! isset( $fields[ $group ] ) ) {
				$fields[ $group ] = array();
			}
			$fields[ $group ][ $custom['key'] ] = $this->custom_checkout_args( $custom );
		}

		return $fields;
	}

	/**
	 * Apply a single native field configuration.
	 *
	 * @param array $field Field definition.
	 * @param array $config Saved configuration.
	 * @return array
	 */
	private function apply_field_config( $field, $config ) {
		$field['required']    = isset( $config['required'] ) && 'yes' === $config['required'];
		$field['label']       = isset( $config['label'] ) && '' !== $config['label'] ? $config['label'] : ( isset( $field['label'] ) ? $field['label'] : '' );
		$field['placeholder'] = isset( $config['placeholder'] ) ? $config['placeholder'] : '';
		$field['priority']    = isset( $config['priority'] ) ? absint( $config['priority'] ) : 0;
		$existing_classes     = isset( $field['class'] ) && is_array( $field['class'] ) ? $field['class'] : array();
		$semantic_classes     = array_diff( $existing_classes, array( 'form-row-first', 'form-row-last', 'form-row-wide' ) );
		$field['class']       = array_values(
			array_unique(
				array_merge( $this->width_classes( isset( $config['width'] ) ? $config['width'] : 'full' ), $semantic_classes )
			)
		);
		return $field;
	}

	/**
	 * Return WooCommerce classes for a field width.
	 *
	 * @param string $width Width key.
	 * @return string[]
	 */
	private function width_classes( $width ) {
		if ( 'left' === $width ) {
			return array( 'form-row-first' );
		}
		if ( 'right' === $width ) {
			return array( 'form-row-last' );
		}
		return array( 'form-row-wide' );
	}

	/**
	 * Respect the global order-notes switch.
	 *
	 * @param bool $enabled Existing value.
	 * @return bool
	 */
	public function order_notes_enabled( $enabled ) {
		$settings = WCCP_Defaults::get_settings();
		return 'yes' === $settings['show_order_notes'] ? $enabled : false;
	}

	/**
	 * Get sanitized custom fields from storage.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_custom_fields() {
		$fields = get_option( WCCP_Defaults::CUSTOM_OPTION, array() );
		return is_array( $fields ) ? array_values( $fields ) : array();
	}

	/**
	 * Validate one custom field definition.
	 *
	 * @param mixed  $raw          Raw field data.
	 * @param string $existing_key Existing key when editing.
	 * @return array|WP_Error
	 */
	public static function sanitize_custom_field( $raw, $existing_key = '' ) {
		$raw = is_array( $raw ) ? wp_unslash( $raw ) : array();
		$key = sanitize_key( self::raw_scalar( $raw, 'key' ) );
		if ( 0 !== strpos( $key, 'wccp_' ) ) {
			$key = 'wccp_' . $key;
		}
		if ( ! preg_match( '/^wccp_[a-z0-9_]{2,45}$/', $key ) ) {
			return new WP_Error( 'invalid_key', __( 'Use 2–45 lowercase letters, numbers, or underscores for the field key.', 'wccp-custom-checkout' ) );
		}

		$types    = array( 'text', 'textarea', 'email', 'tel', 'number', 'select', 'radio', 'checkbox', 'date', 'heading', 'content' );
		$sections = array( 'billing', 'shipping', 'account', 'order' );
		$widths   = array( 'full', 'left', 'right' );
		$type     = sanitize_key( self::raw_scalar( $raw, 'type', 'text' ) );
		$section  = sanitize_key( self::raw_scalar( $raw, 'section', 'order' ) );
		$width    = sanitize_key( self::raw_scalar( $raw, 'width', 'full' ) );
		$label    = WCCP_Settings::limit( sanitize_text_field( self::raw_scalar( $raw, 'label' ) ), 120 );

		if ( ! in_array( $type, $types, true ) || ! in_array( $section, $sections, true ) || ! in_array( $width, $widths, true ) ) {
			return new WP_Error( 'invalid_config', __( 'The field type, section, or width is invalid.', 'wccp-custom-checkout' ) );
		}
		if ( '' === $label ) {
			return new WP_Error( 'missing_label', __( 'A field label is required.', 'wccp-custom-checkout' ) );
		}

		$options_raw = isset( $raw['options'] ) && is_scalar( $raw['options'] ) ? preg_split( '/\r\n|\r|\n/', (string) $raw['options'] ) : array();
		$options     = array();
		foreach ( array_slice( (array) $options_raw, 0, 30 ) as $option ) {
			$option = WCCP_Settings::limit( sanitize_text_field( $option ), 100 );
			if ( '' !== $option ) {
				$options[ sanitize_title( $option ) ] = $option;
			}
		}
		if ( in_array( $type, array( 'select', 'radio' ), true ) && empty( $options ) ) {
			return new WP_Error( 'missing_options', __( 'Select and radio fields require at least one option.', 'wccp-custom-checkout' ) );
		}

		return array(
			'key'             => $key,
			'previous_key'    => sanitize_key( $existing_key ),
			'label'           => $label,
			'type'            => $type,
			'section'         => $section,
			'enabled'         => isset( $raw['enabled'] ) && 'yes' === $raw['enabled'] ? 'yes' : 'no',
			'required'        => isset( $raw['required'] ) && 'yes' === $raw['required'] ? 'yes' : 'no',
			'placeholder'     => WCCP_Settings::limit( sanitize_text_field( self::raw_scalar( $raw, 'placeholder' ) ), 160 ),
			'priority'        => max( 0, min( 9999, absint( self::raw_scalar( $raw, 'priority', '100' ) ) ) ),
			'width'           => $width,
			'options'         => $options,
			'content'         => wp_kses( self::raw_scalar( $raw, 'content' ), self::allowed_content_html() ),
			'display_admin'   => isset( $raw['display_admin'] ) && 'yes' === $raw['display_admin'] ? 'yes' : 'no',
			'display_email'   => isset( $raw['display_email'] ) && 'yes' === $raw['display_email'] ? 'yes' : 'no',
			'display_account' => isset( $raw['display_account'] ) && 'yes' === $raw['display_account'] ? 'yes' : 'no',
			'save_customer'   => isset( $raw['save_customer'] ) && 'yes' === $raw['save_customer'] ? 'yes' : 'no',
		);
	}

	/** Return a scalar request property or a safe default. */
	private static function raw_scalar( $values, $key, $default = '' ) {
		return isset( $values[ $key ] ) && is_scalar( $values[ $key ] ) ? (string) $values[ $key ] : $default;
	}

	/**
	 * Build checkout arguments for a custom field.
	 *
	 * @param array $field Field definition.
	 * @return array
	 */
	private function custom_checkout_args( $field ) {
		$type = $field['type'];
		if ( 'heading' === $type ) {
			$type = 'wccp_heading';
		} elseif ( 'content' === $type ) {
			$type = 'wccp_content';
		}
		$args = array(
			'type'        => $type,
			'label'       => $field['label'],
			'placeholder' => $field['placeholder'],
			'required'    => in_array( $field['type'], array( 'heading', 'content' ), true ) ? false : 'yes' === $field['required'],
			'priority'    => absint( $field['priority'] ),
			'class'       => $this->width_classes( $field['width'] ),
			'options'     => $field['options'],
			'wccp_content'=> $field['content'],
		);
		if ( 'yes' === $field['save_customer'] && get_current_user_id() ) {
			$args['default'] = get_user_meta( get_current_user_id(), $this->meta_key( $field['key'] ), true );
		}
		return $args;
	}

	/**
	 * Render a non-input heading field.
	 */
	public function render_heading( $html, $key, $args, $value ) {
		unset( $html, $value );
		return '<h3 class="wccp-custom-heading" id="' . esc_attr( $key ) . '">' . esc_html( $args['label'] ) . '</h3>';
	}

	/**
	 * Render a sanitized content field.
	 */
	public function render_content( $html, $key, $args, $value ) {
		unset( $html, $value );
		$content = isset( $args['wccp_content'] ) ? $args['wccp_content'] : '';
		return '<div class="wccp-custom-content" id="' . esc_attr( $key ) . '">' . wp_kses( $content, self::allowed_content_html() ) . '</div>';
	}

	/**
	 * Narrow HTML allowlist for custom content.
	 *
	 * @return array
	 */
	private static function allowed_content_html() {
		return array(
			'a'      => array( 'href' => true, 'rel' => true, 'title' => true ),
			'br'     => array(),
			'em'     => array(),
			'strong' => array(),
			'span'   => array( 'class' => true ),
			'p'      => array( 'class' => true ),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
		);
	}

	/**
	 * Reject tampered or oversized custom values server-side.
	 *
	 * @param array    $data   Checkout data.
	 * @param WP_Error $errors Error collection.
	 */
	public function validate_custom_fields( $data, $errors ) {
		foreach ( $this->get_custom_fields() as $field ) {
			if ( 'yes' !== $field['enabled'] || in_array( $field['type'], array( 'heading', 'content' ), true ) ) {
				continue;
			}
			$value = isset( $data[ $field['key'] ] ) ? $data[ $field['key'] ] : '';
			if ( is_array( $value ) || strlen( (string) $value ) > 2000 ) {
				$errors->add( 'wccp_invalid_field', sprintf( __( '%s contains an invalid value.', 'wccp-custom-checkout' ), $field['label'] ) );
				continue;
			}
			if ( in_array( $field['type'], array( 'select', 'radio' ), true ) && '' !== $value && ! array_key_exists( (string) $value, $field['options'] ) ) {
				$errors->add( 'wccp_invalid_choice', sprintf( __( 'Please select a valid option for %s.', 'wccp-custom-checkout' ), $field['label'] ) );
			}
			if ( 'email' === $field['type'] && '' !== $value && ! is_email( $value ) ) {
				$errors->add( 'wccp_invalid_email', sprintf( __( 'Please enter a valid email for %s.', 'wccp-custom-checkout' ), $field['label'] ) );
			}
			if ( 'number' === $field['type'] && '' !== $value && ! is_numeric( $value ) ) {
				$errors->add( 'wccp_invalid_number', sprintf( __( 'Please enter a valid number for %s.', 'wccp-custom-checkout' ), $field['label'] ) );
			}
			if ( 'date' === $field['type'] && '' !== $value && ! $this->is_valid_date( (string) $value ) ) {
				$errors->add( 'wccp_invalid_date', sprintf( __( 'Please enter a valid date for %s.', 'wccp-custom-checkout' ), $field['label'] ) );
			}
			if ( 'tel' === $field['type'] && '' !== $value && ! preg_match( '/^[0-9+().\-\s]{3,50}$/', (string) $value ) ) {
				$errors->add( 'wccp_invalid_phone', sprintf( __( 'Please enter a valid telephone number for %s.', 'wccp-custom-checkout' ), $field['label'] ) );
			}
		}
	}

	/** Validate an ISO-style calendar date without accepting rollover dates. */
	private function is_valid_date( $value ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) ) {
			return false;
		}
		return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] );
	}

	/**
	 * Persist custom field values through the WooCommerce CRUD object.
	 *
	 * @param WC_Order $order Order being created.
	 * @param array    $data  Validated checkout data.
	 */
	public function save_order_values( $order, $data ) {
		foreach ( $this->get_custom_fields() as $field ) {
			if ( 'yes' !== $field['enabled'] || in_array( $field['type'], array( 'heading', 'content' ), true ) ) {
				continue;
			}
			$value = isset( $data[ $field['key'] ] ) ? $this->sanitize_submitted_value( $data[ $field['key'] ], $field ) : '';
			if ( '' !== $value || 'checkbox' === $field['type'] ) {
				$order->update_meta_data( $this->meta_key( $field['key'] ), $value );
			}
		}
	}

	/**
	 * Save explicitly opted-in values to the logged-in customer.
	 *
	 * @param int   $customer_id Customer ID.
	 * @param array $data        Checkout data.
	 */
	public function save_customer_values( $customer_id, $data ) {
		$customer_id = absint( $customer_id );
		if ( ! $customer_id ) {
			return;
		}
		foreach ( $this->get_custom_fields() as $field ) {
			if ( 'yes' !== $field['enabled'] || 'yes' !== $field['save_customer'] || in_array( $field['type'], array( 'heading', 'content' ), true ) ) {
				continue;
			}
			$value = isset( $data[ $field['key'] ] ) ? $this->sanitize_submitted_value( $data[ $field['key'] ], $field ) : '';
			update_user_meta( $customer_id, $this->meta_key( $field['key'] ), $value );
		}
	}

	/**
	 * Sanitize a submitted value according to its declared type.
	 */
	private function sanitize_submitted_value( $value, $field ) {
		if ( is_array( $value ) ) {
			return '';
		}
		if ( 'checkbox' === $field['type'] ) {
			return ! empty( $value ) ? 'yes' : 'no';
		}
		if ( 'textarea' === $field['type'] ) {
			return WCCP_Settings::limit( sanitize_textarea_field( (string) $value ), 2000 );
		}
		if ( 'email' === $field['type'] ) {
			return sanitize_email( (string) $value );
		}
		$value = WCCP_Settings::limit( sanitize_text_field( (string) $value ), 500 );
		if ( in_array( $field['type'], array( 'select', 'radio' ), true ) && ! array_key_exists( $value, $field['options'] ) ) {
			return '';
		}
		return $value;
	}

	/**
	 * Build a private namespaced metadata key.
	 */
	private function meta_key( $field_key ) {
		return '_wccp_' . sanitize_key( $field_key );
	}

	/**
	 * Add configured fields to WooCommerce emails.
	 */
	public function email_fields( $fields, $sent_to_admin, $order ) {
		unset( $sent_to_admin );
		if ( ! $order instanceof WC_Order ) {
			return $fields;
		}
		foreach ( $this->get_custom_fields() as $field ) {
			if ( 'yes' !== $field['display_email'] || in_array( $field['type'], array( 'heading', 'content' ), true ) ) {
				continue;
			}
			$value = $order->get_meta( $this->meta_key( $field['key'] ), true );
			if ( '' !== $value ) {
				$fields[ $this->meta_key( $field['key'] ) ] = array(
					'label' => $field['label'],
					'value' => $this->display_value( $value, $field ),
				);
			}
		}
		return $fields;
	}

	/**
	 * Render selected values in the order administration screen.
	 */
	public function render_admin_values( $order ) {
		if ( ! $order instanceof WC_Order || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		$this->render_values( $order, 'display_admin', 'wccp-admin-order-fields' );
	}

	/**
	 * Render selected values for the customer on their order screen.
	 */
	public function render_customer_values( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) && get_current_user_id() !== (int) $order->get_customer_id() ) {
			return;
		}
		$this->render_values( $order, 'display_account', 'wccp-customer-order-fields' );
	}

	/**
	 * Output an escaped definition list.
	 */
	private function render_values( $order, $visibility_key, $class_name ) {
		$items = array();
		foreach ( $this->get_custom_fields() as $field ) {
			if ( 'yes' !== $field[ $visibility_key ] || in_array( $field['type'], array( 'heading', 'content' ), true ) ) {
				continue;
			}
			$value = $order->get_meta( $this->meta_key( $field['key'] ), true );
			if ( '' !== $value ) {
				$items[] = array( $field['label'], $this->display_value( $value, $field ) );
			}
		}
		if ( empty( $items ) ) {
			return;
		}
		echo '<section class="' . esc_attr( $class_name ) . '"><h3>' . esc_html__( 'Additional checkout information', 'wccp-custom-checkout' ) . '</h3><dl>';
		foreach ( $items as $item ) {
			echo '<dt>' . esc_html( $item[0] ) . '</dt><dd>' . nl2br( esc_html( $item[1] ) ) . '</dd>';
		}
		echo '</dl></section>';
	}

	/**
	 * Convert a stored value to its safe human-readable label.
	 */
	private function display_value( $value, $field ) {
		if ( 'checkbox' === $field['type'] ) {
			return 'yes' === $value ? __( 'Yes', 'wccp-custom-checkout' ) : __( 'No', 'wccp-custom-checkout' );
		}
		if ( in_array( $field['type'], array( 'select', 'radio' ), true ) && isset( $field['options'][ $value ] ) ) {
			return $field['options'][ $value ];
		}
		return WCCP_Settings::limit( sanitize_textarea_field( (string) $value ), 2000 );
	}
}
