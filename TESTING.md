# Manual test checklist

Use a staging copy with WordPress debugging enabled and production-like WoodMart, WooCommerce, shipping, tax, coupon, cache, and payment settings.

## Installation and rollback

- Activate with WooCommerce active; verify no warning or fatal error.
- Deactivate WooCommerce; verify an authorized admin sees a dependency notice and checkout customization does not run.
- Deactivate WCCP; verify native checkout returns and orders/data remain intact.
- Reinstall with delete-on-uninstall disabled; verify settings remain.
- On a disposable site, enable delete-on-uninstall and verify configuration is removed but order metadata remains.
- Verify a Checkout Block page retains its native layout.

## Permissions and request security

- Verify only manage_woocommerce users can open the page or mutate settings.
- Submit every admin action with missing/invalid nonce and confirm no state changes.
- Try GET requests for save/reset/delete actions and confirm no state changes.
- Submit unexpected keys, arrays instead of scalars, oversized text, unsafe HTML/URLs, duplicate custom keys, and more than 50 custom fields; confirm safe rejection/sanitization.
- Put script, event-handler, javascript URL, SQL, and path traversal payloads in labels, content, options, placeholders, checkout values, products, and order data; confirm they render as safe text or are removed.

## Checkout behavior

- Test guest and logged-in checkout with physical, virtual, mixed, free, sale, and coupon-discounted carts.
- Test every active payment gateway and shipping method, including success, failure, cancellation, and redirect return.
- Change address/country/state, shipping, gateway, and coupons; verify AJAX totals/fragments and payment fields remain functional.
- Test taxes, fees, discounts, stock changes, duplicate submissions, empty cart, and validation errors.
- Verify the agreement is present/required when enabled, all policy links are correct, and native privacy output returns when disabled.
- Verify desktop, tablet, mobile, keyboard-only navigation, focus visibility, zoom, screen-reader labels, RTL, and no horizontal overflow.

## Field manager

- For each native field, test enabled/disabled, required/optional, label, placeholder, order, width, section, single reset, and reset all.
- Retest gateway/shipping/tax/fraud integrations after changing any integration-sensitive field.
- Test every custom type, required validation, choice tampering, maximum length, customer prefill, HPOS order save, admin display, email display, and customer order display.
- Verify a customer cannot view another customer's custom order values.
- Remove a custom field and confirm historical order metadata is retained.

## Release evidence

- PHP lint: pass for every PHP file.
- JavaScript syntax check: pass for every JavaScript file.
- Secret and dangerous-pattern scan: reviewed with no unexplained findings.
- No PHP warning/notice/deprecation, browser console error, failed checkout AJAX call, sensitive log entry, or unexpected outbound request.
- Build ZIP inventory contains only intended production files.
- Record SHA-256 checksum, version, date, source revision if available, tester, WordPress/WooCommerce/PHP versions, theme version, gateways, and test result.
