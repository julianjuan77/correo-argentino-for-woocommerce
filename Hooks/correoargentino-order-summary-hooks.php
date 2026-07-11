<?php
if (!defined('ABSPATH')) {
    exit;
}

add_filter('woocommerce_get_order_item_totals', 'corrarfo_add_shipping_method_to_row', 10, 3);

function corrarfo_add_shipping_method_to_row($total_rows, $order, $tax_display)
{
    $shipping_method = '';

    foreach ($order->get_shipping_methods() as $shipping_item) {
        $shipping_method = $shipping_item->get_name();
    }

    if (isset($total_rows['shipping']) && $shipping_method) {
        $total_rows['shipping']['label'] = __('Shipping:', 'correo-argentino-for-woocommerce') . ' ' . $shipping_method;
    }

    return $total_rows;
}

