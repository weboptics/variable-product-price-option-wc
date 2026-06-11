<?php
/**
 * Plugin Name:       Variable Product Price Option for WooCommerce
 * Plugin URI:        https://github.com/weboptics/variable-product-price-option-wc
 * Description:       This plugin gives the ability to alter price of product in WordPress Woocommerce.
 * Version:           1.0.5
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Author:            WebOptics
 * Author URI:        https://weboptics.co/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       variable-product-price-option-wc
 * Requires Plugins:  woocommerce
 *
 * @package           1.0.5
 */

// disallow direct access.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// exit if accessed directly/.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class. Registers all WooCommerce hooks on instantiation.
 */
class HS_WCVPO_Init {

	/**
	 * Registers all action and filter hooks.
	 */
	public function __construct() {
		add_action( 'woocommerce_before_add_to_cart_quantity', array( $this, 'single_variation_callback' ) );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'change_cart_item_data' ), 10, 3 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'before_calculate_totals_all' ), 1000, 1 );
		add_filter( 'woocommerce_cart_item_price', array( $this, 'filter_cart_item_price' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'show_product_discount_order_summary' ), 10, 3 );
		add_filter( 'woocommerce_add_to_cart_redirect', array( $this, 'custom_add_to_cart_redirect' ), 10, 2 );

		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_custom_checkbox_field' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_custom_checkbox_field' ) );
		// add_action( 'woocommerce_after_single_product_summary', array( $this, 'display_custom_checkbox_field' ), 5 );
	}

	/**
	 * Renders the "Custom" toggle button and hidden price input on the single product page.
	 * Only outputs HTML when the product has `_enable_custom_price` set to "yes".
	 *
	 * Hooked: woocommerce_before_add_to_cart_quantity
	 */
	public function single_variation_callback() {
		global $product;
		$id                  = $product->get_id();
		$enable_custom_price = get_post_meta( $id, '_enable_custom_price', true );
		if ( 'yes' === $enable_custom_price ) {
			// var_dump( $enable_custom_price );
			$jq = "jQuery('#variable-price').toggle();";
			echo '<div class="wc-price-custom" style="padding-bottom: 5px;">';
			echo '<a type="button" class="custom-button" onclick="' . esc_html( $jq ) . '">Custom</a>';
			echo '<div style="display:none;" id="variable-price"><div class="form-group"><input class="woocommerce-Input woocommerce-Input--text input-text" type="number" name="custom-price" value="0" id="custom-price"></div></div>';
			wp_nonce_field( 'hs_wcvpo_custom_price', 'hs_wcvpo_nonce' );
			echo '</div>';
		}
	}

	/**
	 * Captures the custom price from POST and stores it in cart item data.
	 * Uses `donation_price` and `donation_product_id` keys to distinguish the overridden item.
	 * For variable products, `donation_product_id` is set to the variation ID so the correct
	 * variant price is overridden in `before_calculate_totals_all`.
	 *
	 * Hooked: woocommerce_add_cart_item_data (priority 10)
	 *
	 * @param array $cart_item_data Existing cart item data.
	 * @param int   $product_id     Parent product ID.
	 * @param int   $variation_id   Variation ID (0 for simple products).
	 * @return array Modified cart item data.
	 */
	public function change_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		if ( ! isset( $_POST['hs_wcvpo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hs_wcvpo_nonce'] ) ), 'hs_wcvpo_custom_price' ) ) {
			return $cart_item_data;
		}

		$product = wc_get_product( $product_id );

		$custom_price = isset( $_POST['custom-price'] ) ? absint( wp_unslash( $_POST['custom-price'] ) ) : 0;

		if ( $custom_price > 0 ) {
			$cart_item_data['donation_price']      = $custom_price;
			$cart_item_data['donation_product_id'] = ( $product->is_type( 'simple' ) ) ? $product_id : $variation_id;

		} else {
			$cart_item_data['donation_price']      = null;
			$cart_item_data['donation_product_id'] = null;
		}

		return $cart_item_data;
	}

	/**
	 * Applies the stored custom price to matching cart items before totals are calculated.
	 * Runs at priority 1000 to ensure it fires after other price-modifying hooks.
	 *
	 * Hooked: woocommerce_before_calculate_totals (priority 1000)
	 *
	 * @param WC_Cart $cart_obj The WooCommerce cart object.
	 */
	public function before_calculate_totals_all( $cart_obj ) {

		// Iterate through each cart item.
		foreach ( $cart_obj->get_cart() as $key => $value ) {
			$id = $value['data'];

			if ( isset( $value['donation_price'] ) && isset( $value['donation_product_id'] ) && $id->get_id() == $value['donation_product_id'] ) {
				$price = $value['donation_price'];

				$value['data']->set_price( $price );
			}
		}
	}

	/**
	 * Replaces the displayed unit price in the cart with the custom price.
	 *
	 * Hooked: woocommerce_cart_item_price (priority 10)
	 *
	 * @param string $price_html   Default price HTML.
	 * @param array  $cart_item    Cart item data array.
	 * @param string $cart_item_key Unique cart item key.
	 * @return string Price HTML, overridden when a custom price is present.
	 */
	public function filter_cart_item_price( $price_html, $cart_item, $cart_item_key ) {
		if ( isset( $cart_item['donation_price'] ) ) {
			return wc_price( $cart_item['donation_price'] );
		}
		return $price_html;
	}

	/**
	 * Replaces the displayed subtotal in the cart with the custom price multiplied by quantity.
	 *
	 * Hooked: woocommerce_cart_item_subtotal (priority 10)
	 *
	 * @param string $total        Default subtotal HTML.
	 * @param array  $cart_item    Cart item data array.
	 * @param string $cart_item_key Unique cart item key.
	 * @return string Subtotal HTML, overridden when a custom price is present.
	 */
	public function show_product_discount_order_summary( $total, $cart_item, $cart_item_key ) {

		if ( isset( $cart_item['donation_price'] ) ) {

			$total = wc_price( $cart_item['donation_price'] * $cart_item['quantity'] );
		}
		return $total;
	}

	/**
	 * Redirects the customer back to the product page (with `?success=yes`) after adding to cart,
	 * instead of the default cart page.
	 *
	 * Hooked: woocommerce_add_to_cart_redirect (priority 10)
	 *
	 * @param string     $url     Default redirect URL.
	 * @param WC_Product $product The product that was added to the cart.
	 * @return string Modified redirect URL.
	 */
	public function custom_add_to_cart_redirect( $url, $product ) {
		if ( $product && is_a( $product, 'WC_Product' ) ) {
			$url = esc_url( add_query_arg( 'success', 'yes', $product->get_permalink() ) );
		}
		return $url;
	}

	/**
	 * Renders the "Enable Custom Price" checkbox in the General tab of the product edit screen.
	 *
	 * Hooked: woocommerce_product_options_general_product_data
	 */
	public function add_custom_checkbox_field() {
		global $product;

		echo '<div class="custom-field">';
		woocommerce_wp_checkbox(
			array(
				'id'          => '_enable_custom_price',
				'label'       => __( 'Enable Custom Price', 'variable-product-price-option-wc' ),
				'desc_tip'    => 'true',
				'description' => __( 'Check this box to enable a custom price for this product.', 'variable-product-price-option-wc' ),
			)
		);
		echo '</div>';
	}

	/**
	 * Saves the "Enable Custom Price" checkbox value to post meta.
	 *
	 * Hooked: woocommerce_process_product_meta
	 *
	 * @param int $product_id The product post ID being saved.
	 */
	public function save_custom_checkbox_field( $product_id ) {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'update-post_' . $product_id ) ) {
			return;
		}

		$enable_custom_price = isset( $_POST['_enable_custom_price'] ) ? 'yes' : 'no';
		update_post_meta( $product_id, '_enable_custom_price', $enable_custom_price );
	}

	/**
	 * Displays a "Custom Price Enabled: Yes" notice below the product summary when the feature is active.
	 *
	 * Hooked: woocommerce_after_single_product_summary (priority 5)
	 */
	public function display_custom_checkbox_field() {
		global $product;

		if ( ! $product ) {
			return;
		}

		$enable_custom_price = get_post_meta( $product->get_id(), '_enable_custom_price', true );

		if ( 'yes' === $enable_custom_price ) {
			echo '<div class="custom-field-value">';
			echo '<strong>' . esc_html__( 'Custom Price Enabled:', 'variable-product-price-option-wc' ) . '</strong> ' . esc_html__( 'Yes', 'variable-product-price-option-wc' );
			echo '</div>';
		}
	}


}

new HS_WCVPO_Init();
