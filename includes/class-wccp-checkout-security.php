<?php
/**
 * Abuse-resistant validation for public checkout submissions.
 *
 * @package WCCP_Custom_Checkout
 */

defined( 'ABSPATH' ) || exit;

final class WCCP_Checkout_Security {
	const HONEYPOT_FIELD    = 'wccp_company_website';
	const MAX_REQUEST_BYTES = 131072;
	const MAX_POST_FIELDS   = 250;
	const RATE_LIMIT_DELAY  = 60;

	/** Register checkout security hooks. */
	public function hooks() {
		add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'render_honeypot' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_checkout' ), 1, 2 );
	}

	/**
	 * Add a non-customer field that automated form fillers commonly complete.
	 *
	 * The value is never stored or reflected back to the browser.
	 */
	public function render_honeypot() {
		?>
		<p class="wccp-honeypot" aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
			<label for="<?php echo esc_attr( self::HONEYPOT_FIELD ); ?>"><?php esc_html_e( 'Leave this field empty', 'wccp-custom-checkout' ); ?></label>
			<input type="text" id="<?php echo esc_attr( self::HONEYPOT_FIELD ); ?>" name="<?php echo esc_attr( self::HONEYPOT_FIELD ); ?>" value="" tabindex="-1" autocomplete="off" inputmode="none" />
		</p>
		<?php
	}

	/**
	 * Reject oversized, automated, or active-content checkout payloads.
	 *
	 * WooCommerce remains responsible for its checkout nonce, field sanitation,
	 * stock, totals, payment validation, and order creation.
	 *
	 * @param array    $data   Sanitized WooCommerce checkout data.
	 * @param WP_Error $errors Checkout validation errors.
	 */
	public function validate_checkout( $data, $errors ) {
		if ( ! is_object( $errors ) || ! is_callable( array( $errors, 'add' ) ) ) {
			return;
		}

		$rate_key = $this->rate_limit_key();
		if ( class_exists( 'WC_Rate_Limiter' ) && WC_Rate_Limiter::retried_too_soon( $rate_key ) ) {
			$this->add_generic_error( $errors );
			return;
		}

		$suspicious = $this->request_is_oversized() || $this->honeypot_was_filled();
		if ( ! $suspicious && is_array( $data ) ) {
			$suspicious = $this->contains_unsafe_checkout_data( $data );
		}

		if ( ! $suspicious ) {
			return;
		}

		if ( class_exists( 'WC_Rate_Limiter' ) ) {
			WC_Rate_Limiter::set_rate_limit( $rate_key, self::RATE_LIMIT_DELAY );
		}
		$this->add_generic_error( $errors );
	}

	/** Check transport-level limits before processing user-controlled values. */
	private function request_is_oversized() {
		$content_length = isset( $_SERVER['CONTENT_LENGTH'] ) && is_scalar( $_SERVER['CONTENT_LENGTH'] ) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
		if ( $content_length > self::MAX_REQUEST_BYTES ) {
			return true;
		}
		return is_array( $_POST ) && count( $_POST ) > self::MAX_POST_FIELDS;
	}

	/** Read the trap field without retaining or logging its untrusted content. */
	private function honeypot_was_filled() {
		if ( ! isset( $_POST[ self::HONEYPOT_FIELD ] ) ) {
			return false;
		}
		$value = $_POST[ self::HONEYPOT_FIELD ];
		return ! is_scalar( $value ) || '' !== trim( (string) wp_unslash( $value ) );
	}

	/** Validate customer-authored fields with conservative type and length limits. */
	private function contains_unsafe_checkout_data( $data ) {
		$limits = array(
			'billing_first_name'  => 100,
			'billing_last_name'   => 100,
			'billing_company'     => 160,
			'billing_country'     => 2,
			'billing_address_1'   => 200,
			'billing_address_2'   => 200,
			'billing_city'        => 100,
			'billing_state'       => 100,
			'billing_postcode'    => 32,
			'billing_phone'       => 50,
			'billing_email'       => 254,
			'shipping_first_name' => 100,
			'shipping_last_name'  => 100,
			'shipping_company'    => 160,
			'shipping_country'    => 2,
			'shipping_address_1'  => 200,
			'shipping_address_2'  => 200,
			'shipping_city'       => 100,
			'shipping_state'      => 100,
			'shipping_postcode'   => 32,
			'order_comments'      => 2000,
		);

		foreach ( $data as $key => $value ) {
			$key = is_string( $key ) ? $key : '';
			$is_known_field = isset( $limits[ $key ] );
			$is_plugin_field = 0 === strpos( $key, 'wccp_' ) || 'billing_delivery_area' === $key;
			if ( ! $is_known_field && ! $is_plugin_field ) {
				continue;
			}
			if ( ! is_scalar( $value ) && null !== $value ) {
				return true;
			}

			$text  = (string) $value;
			$limit = $is_known_field ? $limits[ $key ] : 2000;
			if ( $this->text_length( $text ) > $limit || $this->contains_active_content( $text ) ) {
				return true;
			}
		}
		return false;
	}

	/** Detect control characters and browser-executable content in plain-text fields. */
	private function contains_active_content( $value ) {
		if ( preg_match( '/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x7F]/', $value ) ) {
			return true;
		}
		$patterns = array(
			'/<\\s*\\/?\\s*(?:script|iframe|object|embed|svg|form|style|link|meta)\\b/i',
			'/\\bon[a-z]{3,30}\\s*=/i',
			'/(?:javascript|vbscript)\\s*:/i',
			'/data\\s*:\\s*text\\/html/i',
			'/<\\?php/i',
		);
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $value ) ) {
				return true;
			}
		}
		return false;
	}

	/** Count characters when multibyte support is available. */
	private function text_length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}

	/** Build a non-reversible limiter identifier; never store a raw IP address. */
	private function rate_limit_key() {
		$identity = '';
		$user_id  = function_exists( 'get_current_user_id' ) ? absint( get_current_user_id() ) : 0;
		if ( $user_id ) {
			$identity = 'user:' . $user_id;
		} elseif ( function_exists( 'WC' ) && WC() && WC()->session ) {
			$identity = 'session:' . (string) WC()->session->get_customer_id();
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) && is_scalar( $_SERVER['REMOTE_ADDR'] ) ) {
			$identity = 'ip:' . (string) $_SERVER['REMOTE_ADDR'];
		} else {
			$identity = 'unknown';
		}

		$secret = function_exists( 'wp_salt' ) ? wp_salt( 'nonce' ) : WCCP_FILE;
		return 'wccp_checkout_abuse_' . substr( hash_hmac( 'sha256', $identity, $secret ), 0, 32 );
	}

	/** Add one safe message without reflecting the rejected payload. */
	private function add_generic_error( $errors ) {
		$errors->add(
			'wccp_checkout_security',
			__( 'Checkout security validation failed. Please refresh the page and try again.', 'wccp-custom-checkout' )
		);
	}
}
