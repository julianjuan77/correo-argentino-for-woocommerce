<?php
if (!defined('ABSPATH')) {
    exit;
}

use Corrarfo\Orders\Metabox;
use Corrarfo\Orders\Label;
use Corrarfo\Orders\Order;
use Corrarfo\Orders\Branch;


add_action('woocommerce_review_order_before_cart_contents', 'corrarfo_validate_order', 10);
add_action('woocommerce_after_checkout_validation', 'corrarfo_validate_order', 10);
add_action('add_meta_boxes', [Metabox::class, 'create']);
add_action('rest_api_init', [Label::class, 'create']);
add_action('admin_menu', [Order::class, 'build_admin_menu']);
add_action('woocommerce_after_shipping_rate', [Branch::class, 'showBranches']);
add_action("wp_enqueue_scripts", "corrarfo_add_select2_scripts");
add_action('wp_footer', 'corrarfo_choose_branch_handler');


add_action('woocommerce_new_order', 'corrarfo_new_order_handler');
// Payment complete

//add_action('woocommerce_order_status_completed', 'corrarfo_on_payment_complete_handler', 10);
// When order is cancelled we need to cancel the shipping as well
add_action('woocommerce_order_status_cancelled', 'corrarfo_on_order_cancelled_handler', 10);

add_action('woocommerce_update_options_shipping_' . CORRARFO_PLUGIN_ID, 'corrarfo_on_settings_updated_handler');

add_action('admin_enqueue_scripts', 'corrarfo_enqueue_admin_scripts');

// Keep this to ensure the request works properly
add_filter('http_request_timeout', function ($timeout) {
    return 10;
});

function corrarfo_set_curl_to_follow(&$handle)
{
    // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
    curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
}

add_action('http_api_curl', 'corrarfo_set_curl_to_follow');

// Billing and shipping addresses fields
add_filter('woocommerce_default_address_fields', 'corrarfo_filter_default_address_fields', 20, 1);
function corrarfo_filter_default_address_fields($address_fields)
{
    unset($address_fields['address_2']);
    $address_fields['address_2']['required'] = true;
    $address_fields['address_2']['priority'] = 60;
    $address_fields['address_2']['placeholder'] = CORRARFO_STREET_NUMBER;
    $address_fields['address_2']['label'] = CORRARFO_STREET_NUMBER;

    return $address_fields;
}

add_filter('woocommerce_billing_fields', 'corrarfo_filter_billing_fields', 20, 1);
function corrarfo_filter_billing_fields($billing_fields)
{
    $billing_fields['billing_address_2']['required'] = true;
    $billing_fields['billing_address_2']['placeholder'] = CORRARFO_STREET_NUMBER;
    $billing_fields['billing_address_2']['label'] = CORRARFO_STREET_NUMBER;
    return $billing_fields;
}



