# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Plugin Overview

**Variable Product Price Option for WooCommerce** — a single-file WooCommerce plugin that lets store admins enable a customer-facing "Custom Price" input on any product. When enabled, customers can enter an arbitrary price before adding to cart.

- Requires WordPress 5.8+, PHP 7.2+, WooCommerce
- Version: 1.0.6
- All plugin logic lives in `variable-product-price-option-wc.php` inside the `HS_WCVPO_Init` class, instantiated at the bottom of the file.

## Linting

phpcs with WordPress Coding Standards is installed via Composer (dev dependency):

```bash
# Check for violations
vendor/bin/phpcs --standard=WordPress variable-product-price-option-wc.php

# Auto-fix what can be fixed
vendor/bin/phpcbf --standard=WordPress variable-product-price-option-wc.php
```

No test suite exists. Manual testing requires a running WordPress + WooCommerce instance.

## Architecture

The plugin operates entirely through WooCommerce/WordPress hooks, registered in `HS_WCVPO_Init::__construct()`:

**Admin side** (product edit screen):
- `woocommerce_product_options_general_product_data` → renders the "Enable Custom Price" checkbox
- `woocommerce_process_product_meta` → verifies the standard WordPress post nonce (`_wpnonce` / `update-post_{$id}`), then saves `_enable_custom_price` post meta (`yes`/`no`)

**Frontend product page:**
- `woocommerce_before_add_to_cart_quantity` → conditionally renders the "Custom" toggle button, hidden `#variable-price` input, and a plugin nonce field (`hs_wcvpo_nonce`) — only when `_enable_custom_price === 'yes'`

**Cart flow:**
- `woocommerce_add_cart_item_data` → verifies `hs_wcvpo_nonce` / `hs_wcvpo_custom_price`, then reads `$_POST['custom-price']` and stores it as `donation_price` / `donation_product_id` in cart item data
- `woocommerce_before_calculate_totals` (priority 1000) → iterates cart items and calls `set_price()` on items that have a stored `donation_price`
- `woocommerce_cart_item_price` → overrides displayed unit price with the custom value
- `woocommerce_cart_item_subtotal` → overrides displayed subtotal (`donation_price × quantity`)
- `woocommerce_add_to_cart_redirect` → redirects back to the product permalink with `?success=yes` after add-to-cart

The custom price is keyed as `donation_price` in cart item data (legacy naming — the field is general-purpose, not donation-specific).

## Security

- **Add-to-cart**: plugin outputs its own nonce (`hs_wcvpo_nonce` / `hs_wcvpo_custom_price`) inside the custom price form and verifies it in `change_cart_item_data` before reading `$_POST`.
- **Product save**: verifies the standard WordPress post-edit nonce (`_wpnonce` / `update-post_{$id}`) in `save_custom_checkbox_field` before writing post meta.
- All `$_POST` values are sanitized with `absint()` / `sanitize_text_field()` + `wp_unslash()` before use.
- Translated strings echoed to the frontend use `esc_html__()`.

## Known limitations (see ROADMAP.md)

- Custom price is not saved to order line items, so order history and emails show the original product price. Fix planned for v1.0.7.
- AJAX add-to-cart is not supported. Fix planned for v1.0.8.
