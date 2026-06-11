# Roadmap

Current stable version: **1.0.6**

### Shipped in 1.0.6
- Decimal price support — replaced `absint()` with `wc_format_decimal()` so prices like `$19.99` work correctly.

---

## v1.0.7 — Order persistence
Save custom price to order line items so it appears in order history, admin order screen, and emails.

## v1.0.8 — AJAX add-to-cart
Handle AJAX-based add-to-cart so the nonce and custom price are passed correctly without a full page form POST.

---

## v1.1.0 — Price controls
Admin min/max price fields per product with server-side validation on submission.

## v1.1.1 — Suggested price
Admin-configurable default value for the price input instead of the hardcoded `0`.

## v1.1.2 — HPOS compatibility
Declare WooCommerce High-Performance Order Storage compatibility via `FeaturesUtil::declare_compatibility`.

---

## v1.2.0 — Checkout display
Show custom price on checkout review, thank-you page, and order emails.

## v1.2.1 — Configurable label
Per-product button label field ("Custom", "Pay what you want", etc.) replacing the hardcoded string.

## v1.2.2 — Asset cleanup
Move inline `style=""` and `onclick` attributes to enqueued CSS/JS files.

---

## v2.0.0 — Block compatibility
Full support for the WooCommerce block cart and block checkout, which the current form-POST approach does not support.
