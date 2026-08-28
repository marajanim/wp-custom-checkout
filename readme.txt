=== WCCP Custom Checkout for WooCommerce ===
Contributors: sinogems
Tags: woocommerce, checkout, checkout fields, woodmart
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A secure, dynamic classic checkout builder for WooCommerce and WoodMart.

== Description ==

WCCP Custom Checkout provides a responsive two-column classic checkout, configurable policy agreement, native checkout field manager, and HPOS-compatible custom order fields.

Version 0.1 targets the classic WooCommerce checkout shortcode. Checkout Block pages deliberately retain WooCommerce's native layout and are declared incompatible with this plugin's custom field behavior.

No payment credentials are collected or stored by this plugin. WooCommerce and installed gateways remain responsible for payment processing, totals, tax, shipping, stock, validation, and order creation.

== Installation ==

1. Back up the site and use a staging environment.
2. Install and activate WooCommerce.
3. Upload the wccp-custom-checkout directory or release ZIP and activate it.
4. Confirm the checkout page uses the classic [woocommerce_checkout] shortcode.
5. Open WooCommerce > Custom Checkout.
6. Configure layout, policy links, native fields, and any custom fields.
7. Complete test orders with every active gateway and shipping method.
8. Only after successful tests, remove the old SinoGems checkout privacy/terms filters and sinogems_custom_terms_text function from the child theme. Keep the unrelated WoodMart child stylesheet enqueue function.
9. Clear WordPress, WoodMart, page, object, and CDN caches.

== Settings ==

Layout & Policies controls the custom layout, checkout sections, policy agreement, colors, and opt-in uninstall cleanup.

Checkout Fields lists fields registered by WooCommerce and compatible extensions. Each field can be enabled, required, relabeled, reordered, resized, moved to another section, or reset. Integration-sensitive fields are marked. Disabling or moving address, email, phone, country, state, or postcode fields can break gateways, shipping, tax, or fraud tools; staging tests are mandatory.

Custom Fields supports text, textarea, email, telephone, number, select, radio, checkbox, date, heading, and safe content definitions. Values are saved through WooCommerce order CRUD APIs and can be shown in admin orders, emails, and customer order details.

== Frequently Asked Questions ==

= Does this support Checkout Block? =

Not in version 0.1. The block keeps its native layout. Use the classic checkout shortcode for plugin functionality.

= Is HPOS supported? =

Yes. Custom order data is written and read through WooCommerce order objects, and HPOS compatibility is declared.

= Can I disable every field? =

The manager exposes registered checkout fields, but not security nonces, payment credentials, gateway controls, totals, notices, or Place Order. Some visible fields are required by external integrations; test before production.

= What happens when the plugin is disabled? =

WooCommerce returns to its standard checkout. The plugin never edits WooCommerce or theme files.

= Does uninstall remove order data? =

No. Optional uninstall cleanup removes plugin configuration only. Historical order metadata remains for order integrity.

== Security ==

Settings mutations require manage_woocommerce, POST requests, and purpose-specific nonces. Inputs use explicit allowlists, output is contextually escaped, custom content uses a narrow HTML allowlist, and order values use WooCommerce CRUD APIs. There are no runtime dependencies, remote scripts, telemetry, uploads, REST routes, or custom SQL queries.

Report vulnerabilities privately to the site/plugin owner. Do not publish exploitable details before a fix is available. See SECURITY.md in the plugin package.

== Changelog ==

= 0.1.0 =

* Initial secure development release.
* Added classic checkout layout and policy controls.
* Added dynamic native and custom checkout fields.
* Added HPOS-compatible order metadata display.
