<?php
/**
 * Plugin Name:       WCCP Custom Checkout for WooCommerce
 * Description:       A secure, dynamic classic checkout builder for WooCommerce and WoodMart.
 * Version:           0.7.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            SinoGems
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wccp-custom-checkout
 * Domain Path:       /languages
 * WC requires at least: 8.5
 * WC tested up to:   11.0
 *
 * @package WCCP_Custom_Checkout
 */

defined( 'ABSPATH' ) || exit;

define( 'WCCP_VERSION', '0.7.0' );
define( 'WCCP_FILE', __FILE__ );
define( 'WCCP_PATH', plugin_dir_path( __FILE__ ) );
define( 'WCCP_URL', plugin_dir_url( __FILE__ ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( ! class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, false );
	}
);

require_once WCCP_PATH . 'includes/class-wccp-defaults.php';
require_once WCCP_PATH . 'includes/class-wccp-settings.php';
require_once WCCP_PATH . 'includes/class-wccp-delivery-area.php';
require_once WCCP_PATH . 'includes/class-wccp-fields.php';
require_once WCCP_PATH . 'includes/class-wccp-checkout.php';
require_once WCCP_PATH . 'includes/class-wccp-admin.php';
require_once WCCP_PATH . 'includes/class-wccp-plugin.php';

register_activation_hook( __FILE__, array( 'WCCP_Plugin', 'activate' ) );

WCCP_Plugin::instance();
