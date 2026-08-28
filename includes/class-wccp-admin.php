<?php
/**
 * Secure WooCommerce administration interface.
 *
 * @package WCCP_Custom_Checkout
 */

defined( 'ABSPATH' ) || exit;

final class WCCP_Admin {
	/** @var WCCP_Fields */
	private $fields;

	/** @var string */
	private $page_hook = '';

	public function __construct( WCCP_Fields $fields ) {
		$this->fields = $fields;
	}

	/** Register admin hooks. */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_wccp_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_wccp_reset_settings', array( $this, 'reset_settings' ) );
		add_action( 'admin_post_wccp_save_fields', array( $this, 'save_fields' ) );
		add_action( 'admin_post_wccp_reset_fields', array( $this, 'reset_fields' ) );
		add_action( 'admin_post_wccp_save_custom_field', array( $this, 'save_custom_field' ) );
		add_action( 'admin_post_wccp_delete_custom_field', array( $this, 'delete_custom_field' ) );
	}

	/** Add the WooCommerce submenu. */
	public function menu() {
		$this->page_hook = add_submenu_page(
			'woocommerce',
			__( 'Custom Checkout', 'wccp-custom-checkout' ),
			__( 'Custom Checkout', 'wccp-custom-checkout' ),
			'manage_woocommerce',
			'wccp-custom-checkout',
			array( $this, 'render_page' )
		);
	}

	/** Load assets only on this plugin page. */
	public function assets( $hook ) {
		if ( $hook !== $this->page_hook ) {
			return;
		}
		$css = WCCP_PATH . 'assets/css/admin.css';
		$js  = WCCP_PATH . 'assets/js/admin.js';
		wp_enqueue_style( 'wccp-admin', WCCP_URL . 'assets/css/admin.css', array(), file_exists( $css ) ? (string) filemtime( $css ) : WCCP_VERSION );
		wp_enqueue_script( 'wccp-admin', WCCP_URL . 'assets/js/admin.js', array(), file_exists( $js ) ? (string) filemtime( $js ) : WCCP_VERSION, true );
	}

	/** Reject unauthorized or forged mutations. */
	private function authorize( $nonce_action ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to change checkout settings.', 'wccp-custom-checkout' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $nonce_action );
	}

	/** Redirect back to an allowlisted settings tab. */
	private function redirect( $tab, $status ) {
		$tab = in_array( $tab, array( 'general', 'fields', 'custom' ), true ) ? $tab : 'general';
		$url = add_query_arg(
			array( 'page' => 'wccp-custom-checkout', 'tab' => $tab, 'wccp_status' => sanitize_key( $status ) ),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	/** Save allowlisted general settings. */
	public function save_settings() {
		$this->authorize( 'wccp_save_settings' );
		$raw      = isset( $_POST['settings'] ) ? $_POST['settings'] : array();
		$settings = WCCP_Settings::sanitize_settings( $raw );
		update_option( WCCP_Defaults::SETTINGS_OPTION, $settings, false );
		update_option( WCCP_Defaults::DELETE_OPTION, $settings['delete_on_uninstall'], false );
		$this->redirect( 'general', 'saved' );
	}

	/** Restore general defaults. */
	public function reset_settings() {
		$this->authorize( 'wccp_reset_settings' );
		update_option( WCCP_Defaults::SETTINGS_OPTION, WCCP_Defaults::settings(), false );
		update_option( WCCP_Defaults::DELETE_OPTION, 'no', false );
		$this->redirect( 'general', 'reset' );
	}

	/** Save native field overrides. */
	public function save_fields() {
		$this->authorize( 'wccp_save_fields' );
		$allowed = $this->native_field_keys();
		$raw     = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? $_POST['fields'] : array();
		$raw     = array_intersect_key( $raw, array_flip( $allowed ) );
		$clean   = WCCP_Settings::sanitize_field_settings( $raw );
		$resets  = isset( $_POST['reset_fields'] ) && is_array( $_POST['reset_fields'] ) ? wp_unslash( $_POST['reset_fields'] ) : array();
		foreach ( array_slice( $resets, 0, 150 ) as $reset_key ) {
			$reset_key = is_scalar( $reset_key ) ? sanitize_key( (string) $reset_key ) : '';
			if ( in_array( $reset_key, $allowed, true ) ) {
				unset( $clean[ $reset_key ] );
			}
		}
		update_option( WCCP_Defaults::FIELDS_OPTION, $clean, false );
		if ( in_array( WCCP_Delivery_Area::FIELD_KEY, $resets, true ) ) {
			update_option( WCCP_Defaults::DELIVERY_OPTION, WCCP_Defaults::delivery_areas(), false );
		} elseif ( isset( $_POST['delivery_areas'] ) && is_array( $_POST['delivery_areas'] ) ) {
			update_option( WCCP_Defaults::DELIVERY_OPTION, WCCP_Settings::sanitize_delivery_areas( $_POST['delivery_areas'] ), false );
		}
		$this->redirect( 'fields', 'saved' );
	}

	/** Reset all native field overrides. */
	public function reset_fields() {
		$this->authorize( 'wccp_reset_fields' );
		update_option( WCCP_Defaults::FIELDS_OPTION, array(), false );
		update_option( WCCP_Defaults::DELIVERY_OPTION, WCCP_Defaults::delivery_areas(), false );
		$this->redirect( 'fields', 'reset' );
	}

	/** Return an allowlist of fields currently registered with WooCommerce. */
	private function native_field_keys() {
		$keys = array();
		foreach ( $this->fields->get_registered_fields() as $group ) {
			if ( is_array( $group ) ) {
				$keys = array_merge( $keys, array_keys( $group ) );
			}
		}
		return array_values( array_unique( array_map( 'sanitize_key', $keys ) ) );
	}

	/** Create or update a plugin-owned custom field. */
	public function save_custom_field() {
		$this->authorize( 'wccp_save_custom_field' );
		$raw          = isset( $_POST['custom'] ) ? $_POST['custom'] : array();
		$existing_key = isset( $_POST['existing_key'] ) && is_scalar( $_POST['existing_key'] ) ? sanitize_key( (string) wp_unslash( $_POST['existing_key'] ) ) : '';
		$field        = WCCP_Fields::sanitize_custom_field( $raw, $existing_key );
		if ( is_wp_error( $field ) ) {
			$this->redirect( 'custom', $field->get_error_code() );
		}

		$custom = get_option( WCCP_Defaults::CUSTOM_OPTION, array() );
		$custom = is_array( $custom ) ? $custom : array();
		if ( count( $custom ) >= 50 && ! isset( $custom[ $existing_key ] ) ) {
			$this->redirect( 'custom', 'field_limit' );
		}
		if ( in_array( $field['key'], $this->native_field_keys(), true ) || ( isset( $custom[ $field['key'] ] ) && $field['key'] !== $existing_key ) ) {
			$this->redirect( 'custom', 'duplicate_key' );
		}
		if ( $existing_key && isset( $custom[ $existing_key ] ) && $existing_key !== $field['key'] ) {
			unset( $custom[ $existing_key ] );
		}
		unset( $field['previous_key'] );
		$custom[ $field['key'] ] = $field;
		update_option( WCCP_Defaults::CUSTOM_OPTION, $custom, false );
		$this->redirect( 'custom', 'saved' );
	}

	/** Delete a field definition without deleting historical order data. */
	public function delete_custom_field() {
		$this->authorize( 'wccp_delete_custom_field' );
		$key    = isset( $_POST['field_key'] ) && is_scalar( $_POST['field_key'] ) ? sanitize_key( (string) wp_unslash( $_POST['field_key'] ) ) : '';
		$custom = get_option( WCCP_Defaults::CUSTOM_OPTION, array() );
		$custom = is_array( $custom ) ? $custom : array();
		if ( $key && isset( $custom[ $key ] ) ) {
			unset( $custom[ $key ] );
			update_option( WCCP_Defaults::CUSTOM_OPTION, $custom, false );
		}
		$this->redirect( 'custom', 'deleted' );
	}

	/** Render the settings shell and selected read-only tab. */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to view checkout settings.', 'wccp-custom-checkout' ), '', array( 'response' => 403 ) );
		}
		$tab = isset( $_GET['tab'] ) && is_scalar( $_GET['tab'] ) ? sanitize_key( (string) wp_unslash( $_GET['tab'] ) ) : 'general';
		$tab = in_array( $tab, array( 'general', 'fields', 'custom' ), true ) ? $tab : 'general';
		$base = admin_url( 'admin.php?page=wccp-custom-checkout' );
		?>
		<div class="wrap wccp-admin-wrap">
			<h1><?php esc_html_e( 'WCCP Custom Checkout', 'wccp-custom-checkout' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Configure on staging and complete a test order with every active shipping and payment method before production use.', 'wccp-custom-checkout' ); ?></p>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'Version 1 customizes the classic checkout shortcode. WooCommerce Checkout Block pages safely retain their native layout.', 'wccp-custom-checkout' ); ?></p></div>
			<div class="notice notice-error inline"><p><strong><?php esc_html_e( 'Checkout privacy:', 'wccp-custom-checkout' ); ?></strong> <?php esc_html_e( 'Exclude Cart, Checkout, My Account, and wc-ajax URLs from every WordPress, hosting, reverse-proxy, and CDN full-page cache. Purge all existing cache after changing this plugin.', 'wccp-custom-checkout' ); ?> <a href="https://developer.woocommerce.com/docs/best-practices/performance/configuring-caching-plugins" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'WooCommerce cache guidance', 'wccp-custom-checkout' ); ?></a></p></div>
			<?php $this->render_status_notice(); ?>
			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Custom checkout settings', 'wccp-custom-checkout' ); ?>">
				<a class="nav-tab <?php echo 'general' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'general', $base ) ); ?>"><?php esc_html_e( 'Layout & Policies', 'wccp-custom-checkout' ); ?></a>
				<a class="nav-tab <?php echo 'fields' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'fields', $base ) ); ?>"><?php esc_html_e( 'Checkout Fields', 'wccp-custom-checkout' ); ?></a>
				<a class="nav-tab <?php echo 'custom' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'custom', $base ) ); ?>"><?php esc_html_e( 'Custom Fields', 'wccp-custom-checkout' ); ?></a>
			</nav>
			<?php
			if ( 'fields' === $tab ) {
				$this->render_native_fields();
			} elseif ( 'custom' === $tab ) {
				$this->render_custom_fields();
			} else {
				$this->render_general();
			}
			?>
		</div>
		<?php
	}

	/** Output a hidden fallback and checkbox for a native field. */
	private function field_toggle( $key, $property, $value ) {
		$name = 'fields[' . $key . '][' . $property . ']';
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="no"><input type="checkbox" name="' . esc_attr( $name ) . '" value="yes" ' . checked( $value, 'yes', false ) . '>';
	}

	/** Infer a native field width from WooCommerce classes. */
	private function field_width( $classes ) {
		$classes = is_array( $classes ) ? $classes : array();
		if ( in_array( 'form-row-first', $classes, true ) ) {
			return 'left';
		}
		if ( in_array( 'form-row-last', $classes, true ) ) {
			return 'right';
		}
		return 'full';
	}

	/** Output an accessible width selector. */
	private function width_select( $name, $selected ) {
		echo '<select name="' . esc_attr( $name ) . '">';
		foreach ( array( 'full' => __( 'Full', 'wccp-custom-checkout' ), 'left' => __( 'Left half', 'wccp-custom-checkout' ), 'right' => __( 'Right half', 'wccp-custom-checkout' ) ) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $selected, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	/** Output an allowlisted checkout-section selector. */
	private function section_select( $name, $selected ) {
		echo '<select name="' . esc_attr( $name ) . '">';
		foreach ( array( 'billing', 'shipping', 'account', 'order' ) as $section ) {
			echo '<option value="' . esc_attr( $section ) . '" ' . selected( $selected, $section, false ) . '>' . esc_html( ucfirst( $section ) ) . '</option>';
		}
		echo '</select>';
	}

	/** Render an allowlisted status message. */
	private function render_status_notice() {
		$status = isset( $_GET['wccp_status'] ) && is_scalar( $_GET['wccp_status'] ) ? sanitize_key( (string) wp_unslash( $_GET['wccp_status'] ) ) : '';
		$messages = array(
			'saved'          => __( 'Settings saved.', 'wccp-custom-checkout' ),
			'reset'          => __( 'Defaults restored.', 'wccp-custom-checkout' ),
			'deleted'        => __( 'Custom field removed. Existing order data was retained.', 'wccp-custom-checkout' ),
			'invalid_key'    => __( 'The custom field key is invalid.', 'wccp-custom-checkout' ),
			'invalid_config' => __( 'The custom field configuration is invalid.', 'wccp-custom-checkout' ),
			'missing_label'  => __( 'A custom field label is required.', 'wccp-custom-checkout' ),
			'missing_options'=> __( 'Select and radio fields require options.', 'wccp-custom-checkout' ),
			'duplicate_key'  => __( 'That custom field key is already registered.', 'wccp-custom-checkout' ),
			'field_limit'    => __( 'The maximum of 50 custom fields has been reached.', 'wccp-custom-checkout' ),
		);
		if ( ! isset( $messages[ $status ] ) ) {
			return;
		}
		$is_error = ! in_array( $status, array( 'saved', 'reset', 'deleted' ), true );
		echo '<div class="notice ' . ( $is_error ? 'notice-error' : 'notice-success' ) . ' is-dismissible"><p>' . esc_html( $messages[ $status ] ) . '</p></div>';
	}

	/** Output a settings checkbox row. */
	private function checkbox_row( $settings, $key, $label, $description = '' ) {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<input type="hidden" name="settings[<?php echo esc_attr( $key ); ?>]" value="no">
				<label><input type="checkbox" name="settings[<?php echo esc_attr( $key ); ?>]" value="yes" <?php checked( $settings[ $key ], 'yes' ); ?>> <?php esc_html_e( 'Enabled', 'wccp-custom-checkout' ); ?></label>
				<?php if ( $description ) : ?><p class="description"><?php echo esc_html( $description ); ?></p><?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/** Output a text, URL, or color setting row. */
	private function input_row( $settings, $key, $label, $type = 'text' ) {
		?>
		<tr>
			<th scope="row"><label for="wccp-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td><input class="<?php echo 'color' === $type ? 'wccp-color' : 'regular-text'; ?>" id="wccp-<?php echo esc_attr( $key ); ?>" type="<?php echo esc_attr( $type ); ?>" name="settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $settings[ $key ] ); ?>" maxlength="<?php echo 'url' === $type ? '500' : '200'; ?>"></td>
		</tr>
		<?php
	}

	/** Output a constrained numeric setting row. */
	private function number_row( $settings, $key, $label, $min, $max, $description = '' ) {
		?>
		<tr>
			<th scope="row"><label for="wccp-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input class="small-text" id="wccp-<?php echo esc_attr( $key ); ?>" type="number" name="settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $settings[ $key ] ); ?>" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" step="1" required> px
				<?php if ( $description ) : ?><p class="description"><?php echo esc_html( $description ); ?></p><?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/** Render layout, feature, policy, and color settings. */
	private function render_general() {
		$s = WCCP_Defaults::get_settings();
		?>
		<form class="wccp-settings-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wccp_save_settings">
			<?php wp_nonce_field( 'wccp_save_settings' ); ?>
			<h2><?php esc_html_e( 'Layout and features', 'wccp-custom-checkout' ); ?></h2>
			<table class="form-table" role="presentation"><tbody>
				<?php
				$this->checkbox_row( $s, 'enable_layout', __( 'Custom checkout layout', 'wccp-custom-checkout' ) );
				$this->number_row( $s, 'font_size', __( 'Checkout font size', 'wccp-custom-checkout' ), 13, 22, __( 'Controls checkout labels, inputs, delivery choices, order details, totals, and headings. Default: 15px.', 'wccp-custom-checkout' ) );
				$this->checkbox_row( $s, 'prefill_customer_details', __( 'Prefill saved customer details', 'wccp-custom-checkout' ), __( 'Disabled by default for privacy. Enable only when each shopper uses a private account and should see their saved billing and shipping details.', 'wccp-custom-checkout' ) );
				$this->checkbox_row( $s, 'show_progress', __( 'Checkout progress banner', 'wccp-custom-checkout' ) );
				$this->checkbox_row( $s, 'show_coupon', __( 'Coupon prompt and form', 'wccp-custom-checkout' ) );
				$this->checkbox_row( $s, 'show_billing_heading', __( 'Billing Details heading', 'wccp-custom-checkout' ) );
				$this->checkbox_row( $s, 'show_shipping', __( 'Different shipping address section', 'wccp-custom-checkout' ), __( 'Disable only when the billing address should always be used for shipping.', 'wccp-custom-checkout' ) );
				$this->checkbox_row( $s, 'show_order_notes', __( 'Order notes', 'wccp-custom-checkout' ) );
				$this->checkbox_row( $s, 'show_product_images', __( 'Product thumbnails', 'wccp-custom-checkout' ) );
				$this->checkbox_row( $s, 'show_product_meta', __( 'Product SKU', 'wccp-custom-checkout' ) );
				$this->checkbox_row( $s, 'sticky_order_summary', __( 'Sticky order summary', 'wccp-custom-checkout' ) );
				$this->checkbox_row( $s, 'show_payment_heading', __( 'Payment Information heading', 'wccp-custom-checkout' ) );
				?>
			</tbody></table>

			<h2><?php esc_html_e( 'Policy agreement', 'wccp-custom-checkout' ); ?></h2>
			<table class="form-table" role="presentation"><tbody>
				<?php
				$this->checkbox_row( $s, 'enable_agreement', __( 'Combined required agreement', 'wccp-custom-checkout' ) );
				$this->input_row( $s, 'agreement_intro', __( 'Introductory text', 'wccp-custom-checkout' ) );
				$this->checkbox_row( $s, 'open_links_new_tab', __( 'Open policy links in new tab', 'wccp-custom-checkout' ) );
				$this->checkbox_row( $s, 'agreement_checked', __( 'Check agreement by default', 'wccp-custom-checkout' ), __( 'Confirm that preselection complies with the laws and policies that apply to your store.', 'wccp-custom-checkout' ) );
				$this->input_row( $s, 'terms_label', __( 'Terms label', 'wccp-custom-checkout' ) );
				$this->input_row( $s, 'terms_url', __( 'Terms URL', 'wccp-custom-checkout' ), 'url' );
				$this->input_row( $s, 'privacy_label', __( 'Privacy label', 'wccp-custom-checkout' ) );
				$this->input_row( $s, 'privacy_url', __( 'Privacy URL', 'wccp-custom-checkout' ), 'url' );
				$this->input_row( $s, 'return_label', __( 'Return and refund label', 'wccp-custom-checkout' ) );
				$this->input_row( $s, 'return_url', __( 'Return and refund URL', 'wccp-custom-checkout' ), 'url' );
				$this->input_row( $s, 'delivery_label', __( 'Delivery label', 'wccp-custom-checkout' ) );
				$this->input_row( $s, 'delivery_url', __( 'Delivery URL', 'wccp-custom-checkout' ), 'url' );
				?>
			</tbody></table>

			<h2><?php esc_html_e( 'Colors and data', 'wccp-custom-checkout' ); ?></h2>
			<table class="form-table" role="presentation"><tbody>
				<?php
				$this->input_row( $s, 'primary_color', __( 'Primary blue', 'wccp-custom-checkout' ), 'color' );
				$this->input_row( $s, 'button_color', __( 'Button blue', 'wccp-custom-checkout' ), 'color' );
				$this->input_row( $s, 'background_color', __( 'Page background', 'wccp-custom-checkout' ), 'color' );
				$this->input_row( $s, 'border_color', __( 'Border color', 'wccp-custom-checkout' ), 'color' );
				$this->checkbox_row( $s, 'delete_on_uninstall', __( 'Delete plugin settings on uninstall', 'wccp-custom-checkout' ), __( 'Historical order metadata is always retained. Leave disabled for safe reinstall or rollback.', 'wccp-custom-checkout' ) );
				?>
			</tbody></table>
			<?php submit_button( __( 'Save checkout settings', 'wccp-custom-checkout' ) ); ?>
		</form>
		<form class="wccp-reset-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-wccp-confirm="<?php esc_attr_e( 'Restore all layout and policy settings to defaults?', 'wccp-custom-checkout' ); ?>">
			<input type="hidden" name="action" value="wccp_reset_settings">
			<?php wp_nonce_field( 'wccp_reset_settings' ); ?>
			<?php submit_button( __( 'Restore layout defaults', 'wccp-custom-checkout' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/** Render the dynamic native and third-party field manager. */
	private function render_native_fields() {
		$groups = $this->fields->get_registered_fields();
		$saved  = get_option( WCCP_Defaults::FIELDS_OPTION, array() );
		$saved  = is_array( $saved ) ? $saved : array();
		$critical = array( 'billing_country', 'billing_state', 'billing_address_1', 'billing_postcode', 'billing_email', 'billing_phone', 'shipping_country', 'shipping_state', 'shipping_address_1', 'shipping_postcode' );
		?>
		<div class="wccp-field-manager">
			<p><?php esc_html_e( 'Gateway, shipping, tax, fraud, and address integrations may require particular fields. Test every integration after changing enabled or required states.', 'wccp-custom-checkout' ); ?></p>
			<label class="screen-reader-text" for="wccp-field-search"><?php esc_html_e( 'Search checkout fields', 'wccp-custom-checkout' ); ?></label>
			<input type="search" id="wccp-field-search" class="regular-text" placeholder="<?php esc_attr_e( 'Search fields…', 'wccp-custom-checkout' ); ?>">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wccp_save_fields">
				<?php wp_nonce_field( 'wccp_save_fields' ); ?>
				<?php foreach ( $groups as $group_key => $fields ) : ?>
					<?php if ( ! is_array( $fields ) || empty( $fields ) ) { continue; } ?>
					<h2><?php echo esc_html( ucfirst( sanitize_key( $group_key ) ) ); ?></h2>
					<div class="wccp-table-scroll"><table class="widefat striped wccp-fields-table">
						<thead><tr><th><?php esc_html_e( 'Field', 'wccp-custom-checkout' ); ?></th><th><?php esc_html_e( 'Enabled', 'wccp-custom-checkout' ); ?></th><th><?php esc_html_e( 'Required', 'wccp-custom-checkout' ); ?></th><th><?php esc_html_e( 'Label / placeholder', 'wccp-custom-checkout' ); ?></th><th><?php esc_html_e( 'Order', 'wccp-custom-checkout' ); ?></th><th><?php esc_html_e( 'Section', 'wccp-custom-checkout' ); ?></th><th><?php esc_html_e( 'Width', 'wccp-custom-checkout' ); ?></th><th><?php esc_html_e( 'Reset', 'wccp-custom-checkout' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $fields as $key => $field ) :
							$key = sanitize_key( $key );
							if ( 0 === strpos( $key, 'wccp_' ) ) { continue; }
							$default = array(
								'enabled' => 'yes', 'required' => ! empty( $field['required'] ) ? 'yes' : 'no',
								'label' => isset( $field['label'] ) ? $field['label'] : $key,
								'placeholder' => isset( $field['placeholder'] ) ? $field['placeholder'] : '',
								'priority' => isset( $field['priority'] ) ? absint( $field['priority'] ) : 0,
								'section' => sanitize_key( $group_key ),
								'width' => $this->field_width( isset( $field['class'] ) ? $field['class'] : array() ),
							);
							$config = isset( $saved[ $key ] ) ? wp_parse_args( $saved[ $key ], $default ) : $default;
							?>
							<tr draggable="true" data-wccp-field-row data-wccp-field-key="<?php echo esc_attr( $key ); ?>" data-search="<?php echo esc_attr( strtolower( $key . ' ' . $config['label'] ) ); ?>">
								<td><span class="wccp-drag-handle" aria-hidden="true">⋮⋮</span><strong><?php echo esc_html( $key ); ?></strong><br><code><?php echo esc_html( isset( $field['type'] ) ? $field['type'] : 'text' ); ?></code><?php if ( in_array( $key, $critical, true ) ) : ?><span class="wccp-risk"><?php esc_html_e( 'Integration-sensitive', 'wccp-custom-checkout' ); ?></span><?php endif; ?></td>
								<td><?php $this->field_toggle( $key, 'enabled', $config['enabled'] ); ?></td>
								<td><?php $this->field_toggle( $key, 'required', $config['required'] ); ?></td>
								<td><input type="text" name="fields[<?php echo esc_attr( $key ); ?>][label]" value="<?php echo esc_attr( $config['label'] ); ?>" maxlength="120"><br><input type="text" name="fields[<?php echo esc_attr( $key ); ?>][placeholder]" value="<?php echo esc_attr( $config['placeholder'] ); ?>" maxlength="160" placeholder="<?php esc_attr_e( 'Placeholder', 'wccp-custom-checkout' ); ?>"></td>
								<td><input class="small-text" type="number" min="0" max="9999" name="fields[<?php echo esc_attr( $key ); ?>][priority]" value="<?php echo esc_attr( $config['priority'] ); ?>"></td>
								<td><?php $this->section_select( 'fields[' . $key . '][section]', $config['section'] ); ?></td>
								<td><?php $this->width_select( 'fields[' . $key . '][width]', $config['width'] ); ?></td>
								<td><label><input type="checkbox" name="reset_fields[]" value="<?php echo esc_attr( $key ); ?>"> <?php esc_html_e( 'Default', 'wccp-custom-checkout' ); ?></label></td>
							</tr>
							<?php if ( WCCP_Delivery_Area::FIELD_KEY === $key ) : ?>
							<tr class="wccp-delivery-editor-row" data-wccp-delivery-editor-row><td colspan="8"><?php $this->render_delivery_areas_editor(); ?></td></tr>
							<?php endif; ?>
						<?php endforeach; ?>
						</tbody>
					</table></div>
				<?php endforeach; ?>
				<?php submit_button( __( 'Save field settings', 'wccp-custom-checkout' ) ); ?>
			</form>
		</div>
		<form class="wccp-reset-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-wccp-confirm="<?php esc_attr_e( 'Reset every checkout field to its WooCommerce default?', 'wccp-custom-checkout' ); ?>">
			<input type="hidden" name="action" value="wccp_reset_fields">
			<?php wp_nonce_field( 'wccp_reset_fields' ); ?>
			<?php submit_button( __( 'Reset all checkout fields', 'wccp-custom-checkout' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/** Render editable labels and prices for the fixed, server-validated areas. */
	private function render_delivery_areas_editor() {
		$areas = WCCP_Defaults::get_delivery_areas();
		?>
		<section class="wccp-delivery-editor" aria-labelledby="wccp-delivery-editor-title">
			<h2 id="wccp-delivery-editor-title"><?php esc_html_e( 'Delivery areas and charges', 'wccp-custom-checkout' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Edit the customer-facing area names and charges. These secure server-side charges replace WooCommerce shipping while billing_delivery_area is enabled.', 'wccp-custom-checkout' ); ?></p>
			<div class="wccp-delivery-editor-grid">
			<?php foreach ( $areas as $key => $area ) : ?>
				<div class="wccp-delivery-editor-item">
					<div class="wccp-delivery-editor-field">
						<label for="wccp-delivery-label-<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Area name', 'wccp-custom-checkout' ); ?></label>
						<input id="wccp-delivery-label-<?php echo esc_attr( $key ); ?>" type="text" name="delivery_areas[<?php echo esc_attr( $key ); ?>][label]" value="<?php echo esc_attr( $area['label'] ); ?>" maxlength="120" required>
					</div>
					<div class="wccp-delivery-editor-field">
						<label for="wccp-delivery-cost-<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Charge', 'wccp-custom-checkout' ); ?></label>
						<input id="wccp-delivery-cost-<?php echo esc_attr( $key ); ?>" class="small-text" type="number" min="0" max="1000000" step="0.01" name="delivery_areas[<?php echo esc_attr( $key ); ?>][cost]" value="<?php echo esc_attr( $area['cost'] ); ?>" required>
					</div>
				</div>
			<?php endforeach; ?>
			</div>
			<p class="wccp-delivery-editor-actions"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save delivery areas', 'wccp-custom-checkout' ); ?></button></p>
		</section>
		<?php
	}

	/** Render custom-field list and editor. */
	private function render_custom_fields() {
		$custom = get_option( WCCP_Defaults::CUSTOM_OPTION, array() );
		$custom = is_array( $custom ) ? $custom : array();
		$edit_key = isset( $_GET['edit'] ) && is_scalar( $_GET['edit'] ) ? sanitize_key( (string) wp_unslash( $_GET['edit'] ) ) : '';
		$defaults = array(
			'key' => '', 'label' => '', 'type' => 'text', 'section' => 'order', 'enabled' => 'yes',
			'required' => 'no', 'placeholder' => '', 'priority' => 100, 'width' => 'full',
			'options' => array(), 'content' => '', 'display_admin' => 'yes', 'display_email' => 'yes',
			'display_account' => 'yes', 'save_customer' => 'no',
		);
		$field = $edit_key && isset( $custom[ $edit_key ] ) ? wp_parse_args( $custom[ $edit_key ], $defaults ) : $defaults;
		$base  = admin_url( 'admin.php?page=wccp-custom-checkout&tab=custom' );
		?>
		<h2><?php esc_html_e( 'Custom checkout fields', 'wccp-custom-checkout' ); ?></h2>
		<p><?php esc_html_e( 'Removing a definition stops collecting and displaying the field but never deletes historical order metadata.', 'wccp-custom-checkout' ); ?></p>
		<?php if ( $custom ) : ?>
		<table class="widefat striped wccp-custom-list"><thead><tr><th><?php esc_html_e( 'Key', 'wccp-custom-checkout' ); ?></th><th><?php esc_html_e( 'Label', 'wccp-custom-checkout' ); ?></th><th><?php esc_html_e( 'Type', 'wccp-custom-checkout' ); ?></th><th><?php esc_html_e( 'Section', 'wccp-custom-checkout' ); ?></th><th><?php esc_html_e( 'Actions', 'wccp-custom-checkout' ); ?></th></tr></thead><tbody>
		<?php foreach ( $custom as $item ) : ?>
			<tr><td><code><?php echo esc_html( $item['key'] ); ?></code></td><td><?php echo esc_html( $item['label'] ); ?></td><td><?php echo esc_html( $item['type'] ); ?></td><td><?php echo esc_html( $item['section'] ); ?></td><td>
				<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'edit', $item['key'], $base ) ); ?>"><?php esc_html_e( 'Edit', 'wccp-custom-checkout' ); ?></a>
				<form class="wccp-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-wccp-confirm="<?php esc_attr_e( 'Remove this custom field definition? Historical order data will remain.', 'wccp-custom-checkout' ); ?>">
					<input type="hidden" name="action" value="wccp_delete_custom_field"><input type="hidden" name="field_key" value="<?php echo esc_attr( $item['key'] ); ?>">
					<?php wp_nonce_field( 'wccp_delete_custom_field' ); ?><button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Remove', 'wccp-custom-checkout' ); ?></button>
				</form>
			</td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php endif; ?>

		<h2><?php echo esc_html( $edit_key ? __( 'Edit custom field', 'wccp-custom-checkout' ) : __( 'Add custom field', 'wccp-custom-checkout' ) ); ?></h2>
		<form class="wccp-custom-editor" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wccp_save_custom_field"><input type="hidden" name="existing_key" value="<?php echo esc_attr( $edit_key ); ?>">
			<?php wp_nonce_field( 'wccp_save_custom_field' ); ?>
			<table class="form-table" role="presentation"><tbody>
			<tr><th><label for="wccp-custom-key"><?php esc_html_e( 'Unique key', 'wccp-custom-checkout' ); ?></label></th><td><input id="wccp-custom-key" class="regular-text" type="text" name="custom[key]" value="<?php echo esc_attr( $field['key'] ); ?>" maxlength="50" required><p class="description"><?php esc_html_e( 'Lowercase letters, numbers, and underscores. The wccp_ prefix is added automatically.', 'wccp-custom-checkout' ); ?></p></td></tr>
			<tr><th><label for="wccp-custom-label"><?php esc_html_e( 'Label', 'wccp-custom-checkout' ); ?></label></th><td><input id="wccp-custom-label" class="regular-text" type="text" name="custom[label]" value="<?php echo esc_attr( $field['label'] ); ?>" maxlength="120" required></td></tr>
			<tr><th><label for="wccp-custom-type"><?php esc_html_e( 'Type', 'wccp-custom-checkout' ); ?></label></th><td><select id="wccp-custom-type" name="custom[type]">
			<?php foreach ( array( 'text', 'textarea', 'email', 'tel', 'number', 'select', 'radio', 'checkbox', 'date', 'heading', 'content' ) as $type ) : ?><option value="<?php echo esc_attr( $type ); ?>" <?php selected( $field['type'], $type ); ?>><?php echo esc_html( ucfirst( $type ) ); ?></option><?php endforeach; ?>
			</select></td></tr>
			<tr><th><label for="wccp-custom-section"><?php esc_html_e( 'Section', 'wccp-custom-checkout' ); ?></label></th><td><select id="wccp-custom-section" name="custom[section]">
			<?php foreach ( array( 'billing', 'shipping', 'account', 'order' ) as $section ) : ?><option value="<?php echo esc_attr( $section ); ?>" <?php selected( $field['section'], $section ); ?>><?php echo esc_html( ucfirst( $section ) ); ?></option><?php endforeach; ?>
			</select></td></tr>
			<tr><th><label for="wccp-custom-placeholder"><?php esc_html_e( 'Placeholder', 'wccp-custom-checkout' ); ?></label></th><td><input id="wccp-custom-placeholder" class="regular-text" type="text" name="custom[placeholder]" value="<?php echo esc_attr( $field['placeholder'] ); ?>" maxlength="160"></td></tr>
			<tr><th><label for="wccp-custom-priority"><?php esc_html_e( 'Order', 'wccp-custom-checkout' ); ?></label></th><td><input id="wccp-custom-priority" class="small-text" type="number" min="0" max="9999" name="custom[priority]" value="<?php echo esc_attr( $field['priority'] ); ?>"></td></tr>
			<tr><th><?php esc_html_e( 'Width', 'wccp-custom-checkout' ); ?></th><td><?php $this->width_select( 'custom[width]', $field['width'] ); ?></td></tr>
			<tr><th><label for="wccp-custom-options"><?php esc_html_e( 'Options', 'wccp-custom-checkout' ); ?></label></th><td><textarea id="wccp-custom-options" class="large-text" rows="5" name="custom[options]"><?php echo esc_textarea( implode( "\n", array_values( $field['options'] ) ) ); ?></textarea><p class="description"><?php esc_html_e( 'One option per line; required for select and radio fields.', 'wccp-custom-checkout' ); ?></p></td></tr>
			<tr><th><label for="wccp-custom-content"><?php esc_html_e( 'Content', 'wccp-custom-checkout' ); ?></label></th><td><textarea id="wccp-custom-content" class="large-text" rows="5" name="custom[content]"><?php echo esc_textarea( $field['content'] ); ?></textarea><p class="description"><?php esc_html_e( 'Used by content fields. Only safe basic formatting and links are retained.', 'wccp-custom-checkout' ); ?></p></td></tr>
			<?php
			$toggles = array(
				'enabled' => __( 'Enabled on checkout', 'wccp-custom-checkout' ),
				'required' => __( 'Required', 'wccp-custom-checkout' ),
				'display_admin' => __( 'Show in admin orders', 'wccp-custom-checkout' ),
				'display_email' => __( 'Show in order emails', 'wccp-custom-checkout' ),
				'display_account' => __( 'Show in customer order details', 'wccp-custom-checkout' ),
				'save_customer' => __( 'Save for logged-in customer', 'wccp-custom-checkout' ),
			);
			foreach ( $toggles as $key => $label ) : ?>
			<tr><th><?php echo esc_html( $label ); ?></th><td><input type="hidden" name="custom[<?php echo esc_attr( $key ); ?>]" value="no"><label><input type="checkbox" name="custom[<?php echo esc_attr( $key ); ?>]" value="yes" <?php checked( $field[ $key ], 'yes' ); ?>> <?php esc_html_e( 'Enabled', 'wccp-custom-checkout' ); ?></label></td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<?php submit_button( $edit_key ? __( 'Update custom field', 'wccp-custom-checkout' ) : __( 'Add custom field', 'wccp-custom-checkout' ) ); ?>
			<?php if ( $edit_key ) : ?><a class="button" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Cancel edit', 'wccp-custom-checkout' ); ?></a><?php endif; ?>
		</form>
		<?php
	}
}
