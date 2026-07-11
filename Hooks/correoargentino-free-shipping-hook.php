<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Applies free shipping if the configuration is enabled and the cart subtotal exceeds the threshold.
 * Should be called from inside the calculate_shipping() method.
 *
 * @param float $currentRate
 * @param float $cartSubtotal
 * @return float
 */
function corrarfo_process_free_shipping($currentRate, $cartSubtotal)
{
    $enabled = get_option('corrarfo_free_shipping_enabled', 'no');
    $minAmount = floatval(get_option('corrarfo_free_shipping_amount'));

    if ($enabled === 'yes' && $cartSubtotal >= $minAmount) {
        return 0;
    }

    return $currentRate;
}

/**
 * Display a message in the cart and checkout indicating the status of free shipping
 */
add_action('woocommerce_before_cart_totals', 'corrarfo_show_free_shipping_message');
add_action('woocommerce_checkout_before_order_review_heading', 'corrarfo_show_free_shipping_message');

function corrarfo_show_free_shipping_message()
{
    $enabled = get_option('corrarfo_free_shipping_enabled', 'no');
    $minAmount = floatval(get_option('corrarfo_free_shipping_amount'));

    if ($enabled !== 'yes' || !$minAmount) {
        return;
    }

    $cartSubtotal = WC()->cart->get_subtotal();

    if ($cartSubtotal >= $minAmount) {
        echo '<div class="ca-free-shipping-message" style="font-weight:bold; margin-bottom:10px;">'
            . esc_html__('Free shipping Correo Argentino', 'correo-argentino-for-woocommerce') .
            '</div>';
    }
}

/**
 * Save to order meta if it had free shipping (historical)
 */
add_action('woocommerce_checkout_create_order', function ($order, $data) {
    $enabled = get_option('corrarfo_free_shipping_enabled', 'no');
    $minAmount = floatval(get_option('corrarfo_free_shipping_amount'));

    if ($enabled === 'yes' && $order->get_subtotal() >= $minAmount) {
        $order->update_meta_data('_corrarfo_free_shipping', 'yes');
    } else {
        $order->update_meta_data('_corrarfo_free_shipping', 'no');
    }
}, 10, 2);

add_action('woocommerce_checkout_update_order_meta', function ($order_id) {
    $order = wc_get_order($order_id);
    if ($order && $order->get_meta('_corrarfo_free_shipping') === 'yes') {
        $order->add_order_note(__('Free shipping Correo Argentino', 'correo-argentino-for-woocommerce'));
    }
});
