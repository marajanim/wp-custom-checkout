=== WCCP Custom Checkout for WooCommerce ===
Contributors: sinogems
Tags: woocommerce, checkout, checkout fields, woodmart
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.8.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A secure, dynamic classic checkout builder for WooCommerce and WoodMart.

== Description ==

WCCP Custom Checkout provides a responsive two-column classic checkout, configurable policy agreement, native checkout field manager, and HPOS-compatible custom order fields.

It also provides a Billing delivery-area radio field with three editable Bengali choices. The selected server-validated charge replaces WooCommerce's default shipping selector and is added to checkout and the order.

Version 0.3 targets the classic WooCommerce checkout shortcode. Checkout Block pages deliberately retain WooCommerce's native layout and are declared incompatible with this plugin's custom field behavior.

No payment credentials are collected or stored by this plugin. WooCommerce and installed gateways remain responsible for payment processing, totals, tax, shipping, stock, validation, and order creation.

== Installation ==

1. Back up the site and use a staging environment.
2. Install and activate WooCommerce.
3. Upload the wccp-custom-checkout directory or release ZIP and activate it.
4. Confirm the checkout page uses the classic [woocommerce_checkout] shortcode.
5. Open WooCommerce > Custom Checkout.
6. Configure layout, policy links, native fields, and any custom fields.
7. Under Checkout Fields > Billing, configure billing_delivery_area. It is enabled and required by default.
8. Edit the three delivery-area names and charges on the same screen. While this field is enabled, it replaces WooCommerce shipping selection.
9. Select every delivery area and confirm its total before completing test orders with every active gateway.
10. Only after successful tests, remove the old SinoGems checkout privacy/terms filters and sinogems_custom_terms_text function from the child theme. Keep the unrelated WoodMart child stylesheet enqueue function.
11. Clear WordPress, WoodMart, page, object, and CDN caches.

== Settings ==

Layout & Policies controls the custom layout, checkout sections, policy agreement, colors, and opt-in uninstall cleanup.

Checkout Fields lists fields registered by WooCommerce and compatible extensions. Each field can be enabled, required, relabeled, reordered, resized, moved to another section, or reset. Integration-sensitive fields are marked. Disabling or moving address, email, phone, country, state, or postcode fields can break gateways, shipping, tax, or fraud tools; staging tests are mandatory.

Custom Fields supports text, textarea, email, telephone, number, select, radio, checkbox, date, heading, and safe content definitions. Values are saved through WooCommerce order CRUD APIs and can be shown in admin orders, emails, and customer order details.

== Frequently Asked Questions ==

= Does this support Checkout Block? =

Not in version 0.3. The block keeps its native layout. Use the classic checkout shortcode for plugin functionality.

= Is HPOS supported? =

Yes. Custom order data is written and read through WooCommerce order objects, and HPOS compatibility is declared.

= Can I disable every field? =

The manager exposes registered checkout fields, but not security nonces, payment credentials, gateway controls, totals, notices, or Place Order. Some visible fields are required by external integrations; test before production.

= What happens when the plugin is disabled? =

WooCommerce returns to its standard checkout. The plugin never edits WooCommerce or theme files.

= Does uninstall remove order data? =

No. Optional uninstall cleanup removes plugin configuration only. Historical order metadata remains for order integrity.

== Security ==

Settings mutations require manage_woocommerce, POST requests, and purpose-specific nonces. Inputs use explicit allowlists, output is contextually escaped, custom content uses a narrow HTML allowlist, and order values use WooCommerce CRUD APIs. Public checkout validation adds a honeypot, request and field-length limits, active-content rejection, and short rate limiting after suspicious attempts. Rejected payloads and raw IP addresses are not stored by these protections. There are no runtime dependencies, remote scripts, telemetry, uploads, REST routes, or custom SQL queries.

Report vulnerabilities privately to the site/plugin owner. Do not publish exploitable details before a fix is available. See SECURITY.md in the plugin package.

== Changelog ==

= 0.8.5 =

* Populated the WooCommerce shipping address from billing when the delivery-area fee replaces native shipping.
* Restored billing-address display in the default Orders panel for existing delivery-area orders that have no saved shipping address.

= 0.8.4 =

* Removed duplicate Elementor payment headings outside the real payment panel.
* Hid empty Elementor checkout containers and empty wccp-checkout-sidebar panels.
* Reapplied empty-container cleanup after WooCommerce checkout AJAX refreshes.

= 0.8.3 =

* Preserved semantic classes when administrators change a field's width.
* Restored the delivery-area selector as one bordered group with each radio and label aligned on the same row.
* Added a field-ID styling fallback for WoodMart checkout markup.

= 0.8.2 =

* Suppressed WoodMart's checkout quantity hook output on the server.
* Added broader AJAX and CSS cleanup for theme-generated minus/input/plus controls.

= 0.8.1 =

* Removed the duplicate minus, quantity input, and plus controls from checkout order rows.
* Kept the server-rendered read-only Quantity badge visible after checkout AJAX refreshes.

= 0.8.0 =

* Added server-side bot honeypot validation without storing the trap value.
* Added request-size, field-count, scalar-type, and per-field length limits.
* Rejected browser-executable payloads and control characters from customer-authored checkout fields.
* Added hashed-identity, short-term WooCommerce rate limiting after suspicious attempts.

= 0.7.1 =

* Added a Share logged-in cart across devices setting, disabled by default.
* Disabled WooCommerce account-level persistent cart loading and saving when device-only carts are selected.

= 0.7.0 =

* Added private/no-store checkout response headers for browsers, proxies, CDNs, and supported caching plugins.
* Added a privacy-first setting that disables saved personal-detail prefilling by default on fresh checkout pages.
* Added a prominent administration warning to exclude WooCommerce customer-specific pages from full-page caching.

= 0.6.3 =

* Added a server-rendered Quantity badge sourced directly from each WooCommerce cart item.
* Removed the fragile JavaScript quantity mirror and restored the native input value styling.

= 0.6.2 =

* Added a dedicated visible quantity value between the minus and plus buttons.
* Synchronized the displayed quantity after button clicks, direct changes, and checkout AJAX refreshes.

= 0.6.1 =

* Isolated WoodMart quantity controls from the configurable checkout input font and height.
* Kept the minus button, quantity value, and plus button compact, visible, and aligned.

= 0.6.0 =

* Added a validated 13–22px Checkout font size setting under Layout and features.
* Applied the selected size proportionally to fields, delivery choices, order details, totals, and headings.

= 0.5.2 =

* Increased checkout label, input, option, and heading readability.
* Added clearer input borders, spacing, placeholder contrast, and focus states.

= 0.5.1 =

* Moved editable delivery-area names and charges directly below billing_delivery_area.
* Added an inline Save delivery areas button and kept the editor attached during field sorting and search.

= 0.5.0 =

* Prevented duplicate WoodMart product thumbnails and SKU output in the order table.
* Kept the quantity control aligned inside responsive order rows.
* Consolidated the payment heading and methods into one card and removed empty AJAX layout artifacts.

= 0.4.0 =

* Added secure editing for all three delivery-area names and charges.
* Replaced WooCommerce's default shipping selector while the delivery field is enabled.
* Added a compact, aligned checkout radio design.

= 0.3.0 =

* Added billing_delivery_area directly to the dynamic Checkout Fields manager.
* Added secure AJAX total refresh and server-side delivery fees of 60, 90, or 120.
* Saved the selected delivery area with the order through WooCommerce CRUD APIs.

= 0.2.0 =

* Added a WooCommerce shipping-zone method with three selectable Bengali delivery areas.
* Added editable default rates of 60, 90, and 120 in the store currency.

= 0.1.0 =

* Initial secure development release.
* Added classic checkout layout and policy controls.
* Added dynamic native and custom checkout fields.
* Added HPOS-compatible order metadata display.
