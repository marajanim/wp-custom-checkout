# Custom WooCommerce Checkout Page Plugin — Requirements

## 1. Project summary

Build a standalone WordPress plugin that replaces the current WooCommerce classic checkout presentation with a custom, responsive checkout matching the supplied reference screenshot. It must work with WooCommerce and WoodMart without requiring checkout code in the child theme.

The plugin owns the layout, styles, configurable policy agreement, and current terms-checkbox behavior. WooCommerce remains responsible for validation, totals, shipping, tax, coupons, gateways, order creation, stock, emails, and customer data.

## 2. Goals

- Reproduce the reference design on desktop and provide usable tablet/mobile layouts.
- Preserve standard WooCommerce checkout and order-processing behavior.
- Move the terms/policy customizations from the child theme into this plugin.
- Make policy labels and URLs editable in WordPress admin.
- Remain compatible with WoodMart updates and allow safe fallback to default checkout.

## 3. Functional requirements

### 3.1 Layout

Render:

1. A blue progress banner with Shopping Cart, Checkout (active), and Order Complete.
2. The standard WooCommerce coupon prompt and form.
3. Two columns on desktop:
   - Left: Billing Details, optional shipping address, order notes, Payment Information, agreement, and Place Order.
   - Right: Your Order summary.
4. A logical single-column flow on mobile.

Use the active site's header/footer. Do not recreate or hard-code the SinoGems/WoodMart header in the screenshot.

### 3.2 Billing and shipping

- Render fields supplied through WooCommerce APIs and hooks.
- Preserve required/optional indicators, validation, saved customer values, and values submitted before an error.
- Support “Ship to a different address?” and conditional shipping fields.
- Support order notes when enabled.
- Do not hard-code districts; use WooCommerce data or an installed location extension.
- Third-party custom fields added through standard hooks must remain visible and usable.

### 3.3 Order review

- Show thumbnail, product name, variation/SKU when supplied, quantity, subtotal, discounts, fees, shipping, tax, and total.
- Use WooCommerce currency formatting; never hard-code BDT, the taka symbol, or amounts.
- Refresh totals through WooCommerce checkout AJAX when address, shipping, coupons, or payment data changes.
- Support physical/virtual carts and carts requiring no shipping.
- Preserve cart-empty and checkout-error handling.

### 3.4 Payment

- Display every enabled and available WooCommerce gateway.
- Preserve gateway titles, descriptions, icons, fields, scripts, validation, redirects, and callbacks.
- Refresh gateway content correctly after WooCommerce replaces checkout fragments.
- Do not hard-code Cash on Delivery, SSLCommerz, or other gateway logic.
- Submit through WooCommerce's normal flow and duplicate-order protections.

### 3.5 Terms and policy agreement

Move these current child-theme filters into the plugin:

```php
add_filter( 'woocommerce_get_privacy_policy_text', '__return_empty_string' );
add_filter( 'woocommerce_get_terms_and_conditions_checkbox_text', 'sinogems_custom_terms_text', 999 );
add_filter( 'woocommerce_terms_is_checked_default', '__return_true' );
```

- When enabled, suppress separate privacy text and use configurable agreement text/links.
- Support Terms & Conditions, Privacy Policy, Return & Refund Policy, and Delivery Policy.
- Make every label and URL editable.
- Prefill these current URLs:
  - https://sinogemsbd.com/terms-conditions/
  - https://sinogemsbd.com/privacy-policy-2/
  - https://sinogemsbd.com/return-policy/
  - https://sinogemsbd.com/delivery-policy/
- Open links in a new tab by default and add the noopener and noreferrer relationship values.
- Sanitize settings and safely escape output, allowing only required link markup.
- Keep WooCommerce's required-checkbox validation and error handling.
- Provide a Check agreement by default option. It may default to enabled to reproduce current behavior, with a warning to confirm legal/policy compliance.
- When disabled, restore normal WooCommerce terms/privacy output.

### 3.6 Admin settings

Add settings under WooCommerce > Settings (preferred) or a named WooCommerce submenu:

- Enable custom checkout layout and combined policy agreement.
- Introductory agreement text; labels and URLs for all four links.
- Open links in new tab; check agreement by default.
- Optional primary blue, button blue, background, and border colors.
- Restore defaults with confirmation, nonce, and capability checks.

Only users with the appropriate WooCommerce management capability may edit settings. Validate and sanitize every value.

### 3.7 Dynamic checkout field manager

Provide an admin field manager so the checkout can be configured without PHP changes. List all fields registered through the WooCommerce billing, shipping, account, and order field groups, including fields supplied by compatible plugins.

Each field row must provide:

- Enable/disable toggle controlling whether the field appears and is processed.
- Required/optional toggle controlling both front-end indicators and server-side validation.
- Editable label and placeholder.
- Sort order, section, and row-width setting: full width, left half, or right half.
- Read-only field key/type information so administrators can identify integrations safely.
- Reset-this-field action that restores the WooCommerce default.

The manager must initially cover first name, last name, company, country/region, street address lines, town/city, state/district, postcode, phone, email, account fields, shipping-address fields, and order notes whenever those fields are registered by WooCommerce.

Administrators must also be able to create, edit, and remove plugin-owned custom checkout fields. Supported initial types are text, textarea, email, telephone, number, select, radio, checkbox, date, and heading/content. Custom fields need a unique key, label, options where relevant, section/position, enabled state, required state, and display rules for admin orders, customer emails, and My Account/order details. Saved customer/order values must use WooCommerce order APIs and HPOS-compatible metadata APIs.

Field settings must apply consistently to guest and logged-in checkout, AJAX refreshes, front-end validation, server-side validation, order creation, admin order screens, customer emails, and order detail pages where configured.

Safety requirements:

- Do not assume every field can safely be disabled. Show a clear warning for country, state, address, postcode, email, phone, and fields declared required by a gateway, shipping, tax, fraud, or other extension.
- Where WooCommerce dynamically determines whether address fields are required for a country, preserve that logic unless the administrator explicitly overrides it.
- Never allow disabling hidden security values, checkout nonces, gateway inputs, payment credentials, or other non-checkout-field controls through this manager.
- If a disabled/optional field is required by an active integration at runtime, show an actionable checkout/admin error rather than allowing corrupt or incomplete orders.
- Provide Reset all fields to WooCommerce defaults with confirmation, nonce, and capability protection.
- Preserve unknown third-party field configuration and avoid deleting its saved data when a field is merely disabled.

### 3.8 Dynamic checkout sections and features

Provide enable/disable settings for the progress banner, coupon prompt/form, Billing Details heading, shipping-address section, order notes, product thumbnails, SKU/variation display, sticky order summary, Payment Information heading, combined policy agreement, and selected optional custom content blocks.

Section controls must not suppress functionality needed to calculate or submit an order. Payment methods, order totals, WooCommerce notices, validation errors, the Place Order action, legal consent required by configuration, and security fields cannot be hidden through a generic visual toggle.

The settings UI must use clear grouped tabs or panels, searchable field rows, drag-and-drop ordering with an accessible non-drag alternative, saved-state feedback, and a preview or staging guidance. Changes must take effect without editing templates or PHP and must safely invalidate relevant caches.

## 4. Visual and responsive requirements

- Match the reference's structure and appearance without fixed screenshot dimensions.
- Scope CSS so it cannot leak into unrelated WordPress, WooCommerce, Elementor, or WoodMart pages.
- Inherit the active theme's fonts.
- Use centered content, white cards on light gray, subtle borders/radii, compact labels, and a full-width dark-blue Place Order button.
- Clearly identify Checkout as the active step.
- Do not hide notices, errors, required markers, gateway descriptions, or accessibility text.
- Avoid !important unless a documented WoodMart conflict requires it.
- Prevent layout shift and horizontal scrolling.
- At about 1024 px and wider, use two columns and permit a sticky order summary only when it fits.
- At tablet widths, retain two columns only while readable; otherwise stack.
- At about 767 px and narrower, use one column, full-width controls, and practical 44 by 44 CSS-pixel touch targets.
- Disable sticky behavior when it could obscure content or exceed the viewport.

## 5. Technical requirements

- Deliver an installable plugin with a unique text domain and prefixed or namespaced PHP identifiers.
- Check WooCommerce as a dependency. If inactive, show a safe admin notice and do not run checkout customization.
- Initial target: WooCommerce classic checkout shortcode ([woocommerce_checkout]).
- Detect WooCommerce Checkout Block. Version 1 may use its native fallback layout; document this limitation.
- Prefer hooks/filters. Override the minimum templates only when required and record their WooCommerce template versions.
- Never modify WoodMart, its child theme, WooCommerce core, or another plugin.
- Load assets only on checkout, excluding order-received endpoints where inappropriate.
- Use WordPress/WooCommerce APIs for settings, URLs, nonces, capabilities, escaping, translation, and asset versions.
- Remain compatible with checkout AJAX and replaced fragments; JavaScript handlers must not duplicate.
- Do not collect, transmit, log, or store payment credentials or additional customer data.
- Avoid external runtime/CDN dependencies unless approved.
- Follow WordPress Coding Standards and declare supported PHP, WordPress, and WooCommerce versions.
- Respect WooCommerce guest and logged-in checkout settings.

## 6. WoodMart and extension compatibility

- Test with the active WoodMart parent/child-theme setup.
- Preserve the theme header, footer, typography, and non-conflicting checkout features.
- Scope selectors to a plugin wrapper/body class with sufficient specificity for WoodMart overrides.
- Support standard-hook extensions for fields, coupons, shipping, tax, currency, translation, analytics, and gateways.
- Third-party hook output must remain visible and usable even if styling is not an exact match.

## 7. Accessibility and localization

- Target WCAG 2.1 AA.
- Preserve labels, descriptions, error associations, keyboard access, logical focus order, and visible focus.
- Meet contrast requirements and never rely only on color for state or errors.
- Make plugin strings translatable and support RTL.
- Use site locale and WooCommerce formatting for addresses, prices, taxes, and dates.

## 8. Security and privacy

- Sanitize input and escape output.
- Protect settings changes with nonces and capability checks.
- Do not weaken WooCommerce nonces, validation, sessions, or payment redirects.
- Do not expose sensitive values in scripts, comments, logs, or notices.
- Do not automatically edit or remove child-theme code.

Security is a release-blocking requirement. No software can promise absolute immunity from attack; the plugin must use defense in depth to prevent common attacks, limit impact, fail safely, support detection, and permit fast remediation.

### 8.1 Secure architecture and authorization

- Deny direct execution of every PHP file where applicable by checking the WordPress bootstrap constant.
- Use a unique namespace/prefix for PHP symbols, options, metadata, AJAX actions, REST routes, script handles, and nonces.
- Perform capability checks on every privileged action, not only when rendering an admin page. Use the least-privileged appropriate WooCommerce/WordPress capability.
- Protect every state-changing admin form, AJAX request, and REST request with a purpose-specific nonce plus authorization. A nonce is not a substitute for capability checks.
- Use POST for mutations. GET requests must never change settings, reset data, import configuration, delete fields, or perform another sensitive action.
- Define an explicit allowlist of editable settings and field properties. Ignore or reject unexpected request keys.
- Never expose a public endpoint that can alter checkout configuration, order metadata, pricing, totals, customer data, or plugin files.
- Do not implement a custom login, role system, session, password store, token format, cryptography scheme, or payment-data handler.
- Do not grant capabilities automatically to untrusted roles. On uninstall, remove any plugin-created capabilities cleanly.
- REST routes must define permission callbacks, strict schemas, argument validation, and minimal responses. Unauthenticated REST access is forbidden unless a documented read-only feature genuinely requires it.

### 8.2 Input, output, database, and file safety

- Treat all browser, database, option, metadata, REST, AJAX, import, shortcode, hook, and third-party values as untrusted.
- Validate against expected type, length, range, format, and allowlisted values before sanitizing and saving.
- Escape at the final output context using the correct WordPress function for HTML text, attributes, URLs, JavaScript, JSON, or textarea content.
- Custom agreement/content HTML must pass a narrowly defined wp_kses allowlist. Never permit script, iframe, object, embed, inline event handlers, unsafe style, data URLs, or javascript URLs.
- Use WordPress database APIs and prepared queries for every value-bearing custom SQL statement. Never concatenate untrusted values into SQL, identifiers, ORDER BY, or LIMIT clauses.
- Prefer WooCommerce CRUD and HPOS-compatible APIs over direct order-table or postmeta queries.
- Never use eval, assert on strings, dynamic include paths, executable uploads, shell commands, variable functions from user input, unsafe unserialize, or preg_replace execution patterns.
- If configuration import/export is later supported, require authorization, nonce verification, strict JSON schema validation, size limits, safe MIME/extension checks, and rejection of executable content. PHP or archive imports are forbidden.
- Do not write PHP, JavaScript, template, or executable files from admin-entered content. Runtime-generated data may only use WordPress-approved writable locations and non-executable formats.

### 8.3 Checkout, browser, and payment safety

- Keep WooCommerce as the authority for cart contents, prices, discounts, shipping, tax, totals, stock, customer identity, gateway availability, and order status. Never trust matching browser values.
- Recalculate and validate all order-critical values server-side immediately before order creation.
- Do not bypass WooCommerce checkout nonces, session/customer checks, stock validation, gateway validation, webhook verification, or redirect safety.
- Never store, log, inspect, proxy, or transmit raw card numbers, security codes, mobile-banking secrets, gateway passwords, or payment tokens beyond established gateway APIs.
- Do not alter gateway fields or scripts except through documented WooCommerce hooks. Payment iframes/hosted fields must retain their isolation.
- Prevent reflected, stored, and DOM-based XSS in checkout notices, field labels, placeholders, custom options, policy content, product data, and refreshed AJAX fragments.
- JavaScript must not use eval-like APIs, inject untrusted HTML, create code from strings, expose secrets, or trust client-side validation alone. Prefer textContent and safe DOM construction.
- Redirects must use safe WordPress validation functions and trusted/allowlisted destinations. Never redirect directly to an arbitrary request parameter.
- Use HTTPS-aware WordPress/WooCommerce URLs and never introduce mixed-content resources. Do not weaken site security headers or cookie attributes.
- AJAX handlers must return minimal structured responses and safe generic errors; they must not reveal paths, stack traces, SQL, configuration, gateway secrets, nonces for unrelated actions, or customer/order data.
- Apply conservative size/count limits to custom fields, option lists, labels, placeholders, and submitted values to reduce denial-of-service and storage abuse.

### 8.4 Settings, data privacy, and operational safety

- Store configuration in non-autoloaded options when it is not needed on every request; avoid unbounded option/meta growth.
- Never store secrets in ordinary plugin settings. If a future integration requires credentials, use its official secure mechanism, mask display, never return the stored value to the browser, and never include it in exports/logs.
- Follow data minimization: collect only administrator-configured checkout data with a documented business need.
- Document every custom customer/order datum, where it is stored, who can see it, and how it participates in WordPress privacy export/erasure tools where legally and technically appropriate.
- Escape customer data in admin orders, emails, account pages, exports, and logs. Viewing an order must require the correct capability and object-level authorization.
- Deactivation must leave checkout functional. Activation, upgrades, rollback, and uninstall must fail safely without deleting orders or customer data.
- Database migrations must be versioned, idempotent, capability-independent during normal upgrade execution, and protected against partial failure. Back up/retain the previous schema state when practical.
- Destructive reset/uninstall operations require explicit confirmation, capability checks, purpose-specific nonces, exact scope, and documented recovery impact.
- Production errors must be generic for visitors. Detailed diagnostics may be written only through WordPress debug facilities when enabled and must redact personal data, tokens, cookies, nonces, credentials, and payment information.
- Optional security/audit events may record configuration changes, resets, imports, and migration failures with actor ID and time, but must not record checkout secrets or unnecessary personal data. Logs need retention and deletion controls.

### 8.5 Dependencies, updates, and supply-chain security

- Minimize dependencies. Every bundled PHP/JavaScript package must have a documented purpose, compatible license, pinned/locked version, and active maintenance status.
- Commit dependency lockfiles where applicable. Production packages must exclude development tools, tests containing secrets, source maps with sensitive paths, local configuration, VCS metadata, and unnecessary executable files.
- Run automated dependency vulnerability scanning before each release and regularly during maintenance. A known exploitable critical/high vulnerability blocks release.
- Never load executable code from a CDN or remote server at runtime. Updates must come through an authenticated WordPress-compatible update channel with package integrity and authorization protections.
- Release ZIPs must be built reproducibly from reviewed source. Record version, checksum, build time, and source revision; retain a rollback package.
- No credentials, API keys, tokens, private certificates, database exports, customer data, local paths, debug dumps, or environment files may be committed or packaged. Run secret scanning before release.
- Updates must never silently introduce telemetry, remote code execution, advertisements, admin-user creation, capability escalation, or outbound data transfer.
- Display a supported-version warning rather than running risky code on unsupported PHP, WordPress, or WooCommerce versions.

### 8.6 Mandatory security verification and maintenance

- Review code against the current WordPress Plugin Security guidance, WordPress Coding Standards, WooCommerce extension guidance, and OWASP web-application risks before release.
- Require peer review for changes involving authentication, authorization, nonces, REST/AJAX, HTML rendering, SQL, files, imports, redirects, checkout validation, payments, dependencies, or updates.
- Automated tests must cover unauthorized users, missing/invalid nonces, privilege escalation, CSRF, stored/reflected/DOM XSS, SQL injection, unsafe redirects, malicious URLs/HTML, oversized input, duplicate submissions, checkout tampering, and disabled-field bypass attempts.
- Run PHP static analysis, WordPress coding/security checks, JavaScript linting, dependency audit, secret scan, and malicious-file/package inspection in the release pipeline.
- Test with WordPress debugging enabled and verify no warnings, notices, deprecations, sensitive logging, or unexpected outbound network requests.
- Perform manual security review on a staging copy using low-privilege, shop-manager, administrator, guest, and logged-in customer roles.
- Define a vulnerability-reporting contact/process, severity-based response targets, supported release window, security changelog practice, and emergency rollback/patch procedure.
- Security fixes must not wait for feature releases. Notify administrators clearly when an update addresses an exploitable issue without publishing weaponized details before a fix is available.

## 9. Migration from current child-theme code

1. Install and activate the finished plugin on staging.
2. Verify the four policy URLs and agreement settings.
3. Test checkout with every enabled shipping and payment method.
4. Back up and manually remove only the checkout terms/privacy filters and sinogems_custom_terms_text() from child-theme functions.php.
5. Keep the unrelated woodmart_child_enqueue_styles() code shown in the screenshot unless moved for a separate reason.
6. Clear WordPress, WoodMart, cache-plugin, and CDN caches; retest.

The plugin must not remove code automatically. Keeping both implementations active can produce conflicts, so remove the old checkout snippet only after plugin testing succeeds.

## 10. Acceptance criteria

- Custom classic checkout works on desktop, tablet, and mobile without theme edits.
- Disabling the layout or plugin restores a functional default checkout.
- Billing/shipping fields validate and retain values after errors.
- Coupons, shipping, tax, fees, discounts, and totals update through AJAX.
- Product data and WooCommerce-formatted prices are accurate.
- Every enabled gateway renders, validates, and completes or redirects an order.
- The agreement shows enabled links, is required, and errors when unchecked.
- Terms/privacy content is not duplicated after migration.
- Guest and logged-in checkout follow WooCommerce settings.
- Applicable physical, virtual, sale, coupon, and free-order scenarios pass.
- Keyboard checkout, visible focus, and identifiable errors work.
- No PHP warnings/notices, console errors, failed AJAX, or major WoodMart conflicts occur.
- Plugin assets do not load on unrelated pages.
- Only authorized administrators can edit settings; settings survive updates.
- Every registered checkout field can be enabled/disabled and made required/optional where technically safe, with front-end and server-side behavior matching the saved setting.
- Labels, placeholders, widths, sections, and field ordering update the checkout without code edits.
- Plugin-owned custom fields save through HPOS-compatible order APIs and appear only in configured order/email/customer locations.
- Resetting one field or all fields restores WooCommerce defaults without deleting existing order data.
- Checkout remains valid when fields or optional sections are disabled, and dependency conflicts produce actionable warnings.
- Every privileged mutation rejects unauthorized roles and missing, expired, or invalid nonces without changing state.
- Security tests find no known exploitable critical/high issue, secret, malicious package content, unsafe dependency, or unexpected outbound request in the release candidate.
- Tampered prices, totals, field requirements, order identifiers, redirects, and gateway selections are rejected or safely recalculated server-side.
- Malicious field labels, values, policy content, URLs, AJAX/REST input, and order data cannot execute script, inject SQL, access files, escalate privilege, or disclose another customer's data.
- Plugin activation, upgrade, deactivation, rollback, reset, and uninstall fail safely and never delete or corrupt WooCommerce orders.
- The shipped ZIP is traceable to reviewed source and has a recorded checksum, dependency/secret scan, static-analysis result, and security test result.

## 11. Deliverables

- Installable plugin source and ZIP.
- Organized PHP bootstrap/classes, scoped CSS, and minimal checkout JavaScript.
- Admin settings UI for layout and policy configuration.
- Dynamic field-manager UI, section toggles, custom-field builder, ordering controls, and safe reset tools.
- Optional uninstall cleanup; delete settings only when the administrator opts in.
- readme.txt covering installation, configuration, classic-checkout target, Checkout Block limitation, migration, compatibility, and rollback.
- Manual test checklist covering acceptance criteria.
- Security documentation covering threat model, data handling, capabilities/endpoints, dependency inventory, security-test evidence, vulnerability reporting, update/rollback, and incident response.

## 12. Out of scope for version 1

- Rebuilding the WoodMart header, navigation, mini-cart, or footer.
- Building a gateway, shipping method, district database, or courier integration.
- Automatically editing child-theme files.
- Storing payment credentials.
- Perfect visual styling of every third-party checkout extension.
- Native Checkout Block customization; version 1 targets classic checkout.
