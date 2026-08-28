# wp-custom-checkout

A secure, dynamic classic checkout builder for WooCommerce and the WoodMart theme.

## Current development release

Version `0.1.0` includes:

- Responsive custom classic-checkout layout.
- Dynamic WooCommerce field manager.
- Enable/disable and required/optional controls.
- Labels, placeholders, ordering, widths, and checkout sections.
- HPOS-compatible custom checkout fields.
- Configurable terms, privacy, refund, and delivery-policy agreement.
- Capability, nonce, validation, sanitization, and escaping protections.
- Safe fallback when the plugin is disabled.

## Compatibility

- WordPress 6.5 or newer.
- PHP 7.4 or newer.
- WooCommerce 8.5 or newer.
- Classic checkout shortcode: `[woocommerce_checkout]`.
- Checkout Block customization is intentionally unsupported in version 0.1.

## Installation

1. Use a staging copy of the website.
2. Place this repository in `wp-content/plugins/wccp-custom-checkout`.
3. Activate WooCommerce and then activate WCCP Custom Checkout.
4. Open **WooCommerce > Custom Checkout**.
5. Configure the layout, policy links, and checkout fields.
6. Test every enabled payment gateway and shipping method before production use.

Do not remove the existing child-theme checkout snippet until the plugin passes staging tests. See [readme.txt](readme.txt) and [SECURITY.md](SECURITY.md) for additional instructions.

## License

GPL-2.0-or-later.
