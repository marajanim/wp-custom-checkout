# Security policy

## Supported release

The current 0.6.x line receives security fixes during active development. Keep WordPress, WooCommerce, WoodMart, payment gateways, and this plugin updated. WooCommerce states that only its latest release is considered fully secure.

## Reporting

Report suspected vulnerabilities privately to the plugin/site owner. Include affected version, required role, reproduction steps, impact, and a minimal proof of concept. Do not include customer records, payment data, live credentials, or weaponized public details.

Suggested response targets:

- Critical: acknowledge within 24 hours; mitigation or rollback decision immediately.
- High: acknowledge within 2 business days.
- Medium/low: acknowledge within 5 business days.

## Threat model and trust boundaries

- Untrusted: all request, checkout, option, metadata, hook, product, customer, and third-party extension values.
- Privileged: users with manage_woocommerce can change plugin configuration.
- Authoritative: WooCommerce calculates totals, stock, tax, shipping, gateway availability, payment state, and orders.
- Out of scope: payment gateway internals, WordPress/WooCommerce/theme vulnerabilities, server compromise, stolen administrator sessions, and Checkout Block customization.

## Security controls

- POST-only configuration mutations with capability and purpose-specific nonce checks.
- Allowlisted setting keys, field properties, types, sections, widths, protocols, lengths, counts, and select/radio choices.
- Contextual escaping and narrow safe-HTML allowlists.
- WooCommerce order CRUD APIs for HPOS compatibility; no custom SQL.
- No uploads, eval-like execution, shell calls, dynamic includes, public REST/AJAX mutation endpoints, remote scripts, telemetry, or runtime dependencies.
- No storage or logging of payment credentials.
- Safe deactivation and opt-in configuration cleanup; order metadata is never removed automatically.

## Data map

- wccp_settings: layout and policy configuration; no secrets.
- wccp_field_settings: native field presentation/validation overrides; no customer data.
- wccp_custom_fields: custom field definitions; no submitted customer values.
- _wccp_wccp_FIELDKEY order metadata: sanitized custom checkout values.
- _wccp_wccp_FIELDKEY user metadata: only for fields explicitly configured to save to a logged-in customer.

Visibility of custom order values is independently configurable for admin orders, order emails, and customer order details. The order-details renderer verifies ownership or WooCommerce management capability.

## Release process

Before release, run PHP lint, JavaScript syntax checks, secret/pattern scans, package inventory, manual capability/nonce review, and a complete staging checkout test. Review every change touching authorization, request handling, rendering, order data, redirects, dependencies, or payment layout. Record the package SHA-256 checksum and retain the prior ZIP for rollback.

Security fixes ship separately from feature work when necessary. If exploitation is suspected, disable the custom layout or deactivate the plugin, preserve relevant redacted logs, rotate any unrelated exposed credentials, update affected components, and retest checkout before reactivation.
