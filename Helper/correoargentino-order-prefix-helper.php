<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Returns the order ID with the applied prefix if enabled.
 *
 * @param int $orderId Real WooCommerce order ID.
 * @return string Modified ID (e.g., TIEN1-1234) or the original ID if not enabled.
 */
function corrarfo_get_modified_order_id($orderId) {
    $enabled = get_option('corrarfo_prefix_enabled', 'no');
    $prefix  = get_option('corrarfo_prefix_value', '');

    if ($enabled === 'yes' && !empty($prefix)) {
        return strtoupper($prefix) . '-' . $orderId;
    }

    return (string) $orderId;
}

/**
 * Saves the prefixed ID as metadata in the order.
 *
 * @param WC_Order $order WooCommerce order instance.
 * @return void
 */
function corrarfo_save_prefixed_order_id_meta($order) {
    $customId = corrarfo_get_modified_order_id($order->get_id());
    $order->update_meta_data('_corrarfo_prefixed_order_id', $customId);
    $order->add_order_note(
        sprintf(
            // translators: %s is the custom prefixed ID sent to the MiCorreo API.
            __('Prefixed ID sent to Mail: %s', 'correo-argentino-for-woocommerce'),
            $customId
        ),
        false
    );    
    
    $order->save();
}


