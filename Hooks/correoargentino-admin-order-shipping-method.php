<?php
namespace Corrarfo\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

use WC_Order;

class AdminOrderShippingMethod
{

    public static function init()
    {
        // Displays shipping method(s) on WooCommerce admin order details page
        add_action('woocommerce_admin_order_data_after_shipping_address', [__CLASS__, 'displayShippingMethod'], 10, 1);
    }

    public static function displayShippingMethod($order)
    {
        if (!$order instanceof WC_Order) {
            return;
        }

        $shipping_methods = $order->get_shipping_methods();
        if (!empty($shipping_methods)) {
            echo '<p><strong>' . esc_html__('Shipping method:', 'correo-argentino-for-woocommerce') . '</strong><br>';
            foreach ($shipping_methods as $method) {
                echo esc_html($method->get_name()) . '<br>';
            }
            echo '</p>';
        }

        // Show ID with prefix if exists
        $customId = $order->get_meta('_corrarfo_prefixed_order_id');
        if (!empty($customId)) {
            echo '<p><strong>' . esc_html__('MiCorreo Reference ID:', 'correo-argentino-for-woocommerce') . '</strong> ' . esc_html($customId) . '</p>';
        }

        // Notice of Free Shipping
        $free_shipping = $order->get_meta('_corrarfo_free_shipping');

        if ($free_shipping === 'yes') {
            echo '<div style="margin-top: 10px;">
                <strong>' . esc_html__('Free shipping Correo Argentino', 'correo-argentino-for-woocommerce') . '</strong>
            </div>';
        }

    }
}

// Backward compatibility alias
if (!class_exists('AdminOrderShippingMethod')) {
    class_alias(AdminOrderShippingMethod::class, 'AdminOrderShippingMethod');
}
