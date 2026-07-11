<?php
namespace Corrarfo\Orders;

use Corrarfo\Lib\Utils;
use Corrarfo\Tables\OrderTable;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Metabox
{
    public static function create($arg)
    {

        $order_types = wc_get_order_types('order-meta-boxes');
        $order_types[] = 'woocommerce_page_wc-orders';
        foreach ($order_types as $type) {
            add_meta_box(
                'correoargentino_metabox',
                __('Correo Argentino', 'correo-argentino-for-woocommerce'),
                array(__CLASS__, 'content'),
                $type,
                'side',
                'high'
            );
        }

    }


    public static function content($post_or_order, $metabox)
    {
        // Fork Julian Juan: bajo HPOS el callback recibe un WC_Order; con el
        // modelo legacy recibe un WP_Post. Aceptar ambos y VALIDAR antes de usar
        // (antes accedía a $post->ID y llamaba $order->get_meta() sobre un posible
        // false → error fatal en PHP 8 al abrir el pedido con HPOS activo).
        if ($post_or_order instanceof \WC_Order) {
            $order = $post_or_order;
        } else {
            $order = wc_get_order(is_object($post_or_order) ? $post_or_order->ID : $post_or_order);
        }

        if (empty($order)) {
            return false;
        }

        $order_id = $order->get_id();
        $currentServiceType = Utils::getCurrentServiceType();
        $chosenServiceType = $order->get_meta(CORRARFO_CHOSEN_SERVICE_TYPE);
        $orderServiceType = Utils::getOrderServiceType($order_id);

        $shippingMethods = $order->get_shipping_methods();

        if (empty($shippingMethods)) {
            echo esc_html__('The order does not have Correo Argentino as the shipping method', 'correo-argentino-for-woocommerce');
            return true;
        }

        $shippingMethod = reset($shippingMethods);

        if ($shippingMethod->get_method_id() !== 'correoargentino_shipping_method') {
            echo esc_html__('The order does not have Correo Argentino as the shipping method.', 'correo-argentino-for-woocommerce');
            return true;
        }

        $tracking = $order->get_meta(CORRARFO_TRACKING_NUMBER);

        if (empty($tracking)) {
            echo $orderServiceType === CORRARFO_PAQ_AR
                ? esc_html__('The order has not been pre-registered yet.', 'correo-argentino-for-woocommerce')
                : esc_html__('The order has not been imported yet.', 'correo-argentino-for-woocommerce');
        }

        if ($order && $order->get_meta('_corrarfo_free_shipping') === 'yes') {
            echo '<p><strong>' . esc_html__('Free shipping Correo Argentino', 'correo-argentino-for-woocommerce') . '</strong></p>';
        }

        $adminUrl = get_admin_url();
        $label = $orderServiceType === CORRARFO_PAQ_AR ? esc_html__("Tracking", 'correo-argentino-for-woocommerce') : esc_html__('Reference', 'correo-argentino-for-woocommerce');
        $actionUrl = $adminUrl . 'admin.php?id=' . $order_id . '&page=correoargentino-orders&action=';

        $isBranch = $order->get_meta(CORRARFO_CHOSEN_BRANCH) !== '';
        if ( $isBranch ) {
            $handle = 'ca-admin-inline';
            if ( ! wp_style_is( $handle, 'enqueued' ) ) {
                wp_register_style( $handle, false, [], defined('CORRARFO_VERSION') ? CORRARFO_VERSION : null );
                wp_enqueue_style( $handle );
            }
            wp_add_inline_style( $handle, '.order_data_column:nth-child(3) h3 a{display:none;}' );
        }

        echo "<div class=''>";

        if (!empty($tracking)) {
            echo '<p>' . esc_html($label) . ': ';
            if ($orderServiceType === CORRARFO_PAQ_AR) {
                $tracking = '<a href="' . CORRARFO_TRACKING_URL . $tracking . '" target="_blank" rel="noopener noreferrer">' . $tracking . '</a>';
            }
            echo wp_kses_post($tracking);
            $customId = $order->get_meta('_corrarfo_prefixed_order_id');
            if (!empty($customId)) {
                echo '<p><strong>' . esc_html__('Prefixed ID sent', 'correo-argentino-for-woocommerce') . ':</strong> ' . esc_html($customId) . '</p>';
            }

            echo "</p>";
        }
        $buttons = "<p>";
        $statusStartsInCancel = OrderTable::statusStartsIn($order->get_status(), 'cancel');
        $statusStartsInCompleted = OrderTable::statusStartsIn($order->get_status(), 'complet');


        if ($statusStartsInCompleted && !$statusStartsInCancel && !empty($tracking) && $orderServiceType == CORRARFO_PAQ_AR) {
            $buttons .= "<a class='button button-primary button-block' href='" . $actionUrl . "label' >" . esc_html__("Ver Rótulo", 'correo-argentino-for-woocommerce') . "</a>&nbsp;";
            $buttons .= "<a class='button' href='" . $actionUrl . "cancel' >" . esc_html__("Cancel", 'correo-argentino-for-woocommerce') . "</a>";
        }
        $buttons .= "</p>";
        echo wp_kses_post($buttons);

        echo "</div>";
    }

}

// Backward compatibility alias
if (!class_exists('CorreoArgentinoMetabox')) {
    class_alias(Metabox::class, 'CorreoArgentinoMetabox');
}