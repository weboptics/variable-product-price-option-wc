<?php
/**
 * Plugin Name:       Variable Product Price Option for WooCommerce
 * Plugin URI:        https://github.com/webzombies/variable-product-price-option-wc
 * Description:       This plugin gives the ability to alter price of product in wordpress Woocommerce.
 * Version:           1.0.2
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Author:            Haseeb Nawaz Awan
 * Author URI:        https://github.com/haseebnawaz298
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       variable-product-price-option-wc
 */

//disallow direct access,
if (!defined('WPINC')) {
    die;
}

// exit if accessed directly
if (! defined('ABSPATH') ) { 
	exit;
}
class HS_WCVPO_Init{

	public function __construct() {
		add_action( 'woocommerce_before_add_to_cart_quantity', array($this,'single_variation_callback'));
		add_filter( 'woocommerce_add_cart_item_data', array($this,'change_cart_item_data'), 10, 3);
		add_action( 'woocommerce_before_calculate_totals', array($this,'before_calculate_totals_all'), 1000, 1);
		add_filter( 'woocommerce_cart_item_price', array($this,'filter_cart_item_price'), 10, 3 );
		add_filter( 'woocommerce_cart_item_subtotal', array($this,'show_product_discount_order_summary'), 10, 3 );
		add_filter( 'woocommerce_add_to_cart_redirect', array($this,'custom_add_to_cart_redirect'), 10, 2 );

		add_action('woocommerce_product_options_general_product_data', array($this,'add_custom_checkbox_field'));
		add_action('woocommerce_process_product_meta', array($this,'save_custom_checkbox_field'));
		add_action('woocommerce_after_single_product_summary', array($this,'display_custom_checkbox_field'), 5);
	}
	function single_variation_callback(){
		global $product;
		$id = $product->get_id();
		$enable_custom_price = get_post_meta($id, '_enable_custom_price', true);
		if('yes'===$enable_custom_price){
			$jq="jQuery('#variable-price').toggle();";
			echo '<div class="wc-price-custom" style="padding-bottom: 5px;">';
			echo '<a type="button" class="custom-button" onclick="'.esc_html( $jq ).'">Custom</a>';
			echo '<div style="display:none;" id="variable-price"><div class="form-group"><input class="woocommerce-Input woocommerce-Input--text input-text" type="number" name="custom-price" value="0" id="custom-price"></div></div>';
			echo '</div>';
		}
	}

	function change_cart_item_data($cart_item_data, $product_id, $variation_id){
		$product = wc_get_product( $product_id );

		if (isset($_POST['custom-price']) && (int) $_POST['custom-price']>0) {
			$cart_item_data['donation_price'] = (int) $_POST['custom-price'];
			$cart_item_data['donation_product_id'] = ($product->is_type('simple')) ? $product_id  :$variation_id;
			
		}else{
			$cart_item_data['donation_price'] =null;
			$cart_item_data['donation_product_id'] =null;
		}
		
		return $cart_item_data;
	}


	function before_calculate_totals_all($cart_obj){
		

		// Iterate through each cart item
		foreach ($cart_obj->get_cart() as $key => $value) {
			$id = $value['data'];
			// var_dump($value);
			
			if (isset($value['donation_price']) && isset($value['donation_product_id']) && $id->get_id() == $value['donation_product_id']) {
				$price = $value['donation_price'];
		
				$value['data']->set_price($price);
				// $value['data']->set_quantity(1);
			}
		}
	}
	//Display custom price
	
	function filter_cart_item_price( $price_html, $cart_item, $cart_item_key ) {
		// var_dump($cart_item);
		if( isset( $cart_item['donation_price'] ) ) {
			return wc_price(  $cart_item['donation_price'] );
		}
		return $price_html;
	}

	//Display Custom subtotal price
	function show_product_discount_order_summary( $total, $cart_item, $cart_item_key ) {
		
		// var_dump($cart_item);
		//Get product object
		if( isset(  $cart_item['donation_price']  ) ) {

			$total= wc_price($cart_item['donation_price']  * $cart_item['quantity']);
		}
		// Return the html
		return $total;
	}

	function custom_add_to_cart_redirect( $url, $product ) {
		if ( $product && is_a( $product, 'WC_Product' ) ) {
			$url = esc_url( add_query_arg('success', 'yes', $product->get_permalink() ) );
		}
		return $url;
	}

	// Add custom checkbox field to product
	function add_custom_checkbox_field() {
		global $product;

		echo '<div class="custom-field">';
		woocommerce_wp_checkbox(
			array(
				'id' => '_enable_custom_price',
				'label' => __('Enable Custom Price', 'woocommerce'),
				'desc_tip' => 'true',
				'description' => __('Check this box to enable a custom price for this product.', 'woocommerce')
			)
		);
		echo '</div>';
	}


	// Save custom checkbox field data
	function save_custom_checkbox_field($product_id) {
		$enable_custom_price = isset($_POST['_enable_custom_price']) ? 'yes' : 'no';
		update_post_meta($product_id, '_enable_custom_price', $enable_custom_price);
	}


	// Display custom checkbox field value on product page
	function display_custom_checkbox_field() {
		global $post;

		$enable_custom_price = get_post_meta($post->ID, '_enable_custom_price', true);

		if ($enable_custom_price === 'yes') {
			echo '<div class="custom-field-value">';
			echo '<strong>' . __('Custom Price Enabled:', 'woocommerce') . '</strong> ' . __('Yes', 'woocommerce');
			echo '</div>';
		}
	}


}

new HS_WCVPO_Init();
