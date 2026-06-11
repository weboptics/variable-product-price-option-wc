# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Plugin Overview

**Variable Product Price Option for WooCommerce** — a single-file WooCommerce plugin that lets store admins enable a customer-facing "Custom Price" input on any product. When enabled, customers can enter an arbitrary price before adding to cart.

- Requires WordPress 5.8+, PHP 7.2+, WooCommerce
- Version: 1.0.5
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
- `woocommerce_process_product_meta` → saves `_enable_custom_price` post meta (`yes`/`no`)

**Frontend product page:**
- `woocommerce_before_add_to_cart_quantity` → conditionally renders the "Custom" toggle button and hidden `#variable-price` input (only when `_enable_custom_price === 'yes'`)
- `woocommerce_after_single_product_summary` → shows "Custom Price Enabled: Yes" badge

**Cart flow:**
- `woocommerce_add_cart_item_data` → reads `$_POST['custom-price']` and stores it as `donation_price` / `donation_product_id` in cart item data
- `woocommerce_before_calculate_totals` (priority 1000) → iterates cart items and calls `set_price()` on items that have a stored `donation_price`
- `woocommerce_cart_item_price` → overrides displayed unit price with the custom value
- `woocommerce_cart_item_subtotal` → overrides displayed subtotal (`donation_price × quantity`)
- `woocommerce_add_to_cart_redirect` → redirects back to the product permalink with `?success=yes` after add-to-cart

The custom price is keyed as `donation_price` in cart item data (legacy naming — the field is general-purpose, not donation-specific).
