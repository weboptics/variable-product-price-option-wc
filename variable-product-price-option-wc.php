<?php
/**
 * Plugin Name:       Variable Product Price Option for WooCommerce
 * Plugin URI:        https://github.com/webzombies/variable-product-price-option-wc
 * Description:       This plugin gives the ability to alter price of product in wordpress Woocommerce.
 * Version:           1.0.0
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

add_action('woocommerce_before_add_to_cart_quantity', 'woocommerce_single_variation_callback');
function woocommerce_single_variation_callback(){
	$jq="jQuery('#variable-price').toggle();";
	echo '<div class="wc-price-custom" style="padding-bottom: 5px;">';
		echo '<a type="button" class="custom-button" onclick="'.$jq.'">Custom</a>';
		echo '<div style="display:none;" id="variable-price"><div class="form-group"><input class="woocommerce-Input woocommerce-Input--text input-text" type="number" name="custom-price" value="0" id="custom-price"></div></div>';
	echo '</div>';
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


add_filter('woocommerce_add_cart_item_data', 'change_cart_item_data', 10, 3);
add_action('woocommerce_before_calculate_totals', 'before_calculate_totals_all', 1000, 1);

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
add_filter( 'woocommerce_cart_item_price', 'wdpgk_filter_cart_item_price', 10, 3 );
function wdpgk_filter_cart_item_price( $price_html, $cart_item, $cart_item_key ) {
	// var_dump($cart_item);
    if( isset( $cart_item['donation_price'] ) ) {
        return wc_price(  $cart_item['donation_price'] );
    }
    return $price_html;
}

//Display Custom subtotal price
add_filter( 'woocommerce_cart_item_subtotal', 'wdpgk_show_product_discount_order_summary', 10, 3 );
function wdpgk_show_product_discount_order_summary( $total, $cart_item, $cart_item_key ) {
	
	// var_dump($cart_item);
    //Get product object
    if( isset(  $cart_item['donation_price']  ) ) {

        $total= wc_price($cart_item['donation_price']  * $cart_item['quantity']);
    }
    // Return the html
    return $total;
}

add_filter( 'woocommerce_add_to_cart_redirect', 'my_custom_add_to_cart_redirect', 10, 2 );
function my_custom_add_to_cart_redirect( $url, $product ) {
    if ( $product && is_a( $product, 'WC_Product' ) ) {
        $url = esc_url( add_query_arg('success', 'yes', $product->get_permalink() ) );
    }
    return $url;
}
