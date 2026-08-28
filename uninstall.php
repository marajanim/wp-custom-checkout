<?php
/**
 * Uninstall cleanup for WCCP Custom Checkout.
 *
 * @package WCCP_Custom_Checkout
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( 'yes' !== get_option( 'wccp_delete_data_on_uninstall', 'no' ) ) {
	return;
}

delete_option( 'wccp_settings' );
delete_option( 'wccp_field_settings' );
delete_option( 'wccp_custom_fields' );
delete_option( 'wccp_delete_data_on_uninstall' );

// Historical order and customer metadata is deliberately retained.
