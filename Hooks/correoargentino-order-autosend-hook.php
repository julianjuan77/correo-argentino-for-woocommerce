<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Corrarfo\Lib\Utils;
use Corrarfo\Service\MiCorreoService;


// Hook to detect order status change
add_action('woocommerce_order_status_changed', 'corrarfo_handle_auto_send_on_status_change', 10, 3);

/**
 * Automatically sends the order to MiCorreo if the conditions are met.
 *
 * @param int    $order_id
 * @param string $old_status
 * @param string $new_status
 */
function corrarfo_handle_auto_send_on_status_change($order_id, $old_status, $new_status)
{
    if (get_option('corrarfo_auto_send_enabled') !== '1') {
        return;
    }

    // Get configured status
    $configured_status = get_option('corrarfo_auto_send_trigger_status');
    if (!$configured_status || $new_status !== $configured_status) {
        return;
    }

    $currentService = Utils::getCurrentServiceType();
    
    if (!defined('CORRARFO_MI_CORREO') || $currentService !== CORRARFO_MI_CORREO) {
        $order = wc_get_order($order_id);
        if ($order) {
            $order->add_order_note(__('The order could not be sent: unknown service.', 'correo-argentino-for-woocommerce'));
        }
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    if ($order->get_meta('_corrarfo_sent_to_micorreo') === 'yes') {
        return;
    }

    try {
        $service = new MiCorreoService();
        $service->registerOrder($order_id);

        // Mark as sent
        $order->update_meta_data('_corrarfo_sent_to_micorreo', 'yes');
        $order->add_order_note(__('Order automatically sent to MiCorreo.', 'correo-argentino-for-woocommerce'));
        $order->save();
    } catch (Exception $e) {
        // translators: %s is the error message returned by the API
        $order->add_order_note(sprintf(__('Error sending order automatically to MiCorreo: %s', 'correo-argentino-for-woocommerce'), $e->getMessage()));
    }
}
