<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Renders the action icons (view details and delete reference) for an order
 */
function corrarfo_render_order_actions($order_id)
{

    $can_delete = corrarfo_can_delete_order($order_id);
    $view_url = get_edit_post_link($order_id);

    $output = '<a href="' . esc_url($view_url) . '" title="' . esc_attr__('View order details', 'correo-argentino-for-woocommerce') . '">🔍</a>';

    if ($can_delete) {
        $delete_url = wp_nonce_url(
            admin_url("admin.php?page=correoargentino-orders&action=delete_reference&order_id={$order_id}"),
            'delete_reference_' . $order_id
        );
        $output .= ' <a href="' . esc_url($delete_url) . '" title="' . esc_attr__('Delete shipping reference', 'correo-argentino-for-woocommerce') . '" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete the reference?', 'correo-argentino-for-woocommerce')) . '\')">🗑</a>';
    } else {
        $output .= ' <span title="' . esc_attr__('Cannot delete: already shipped or invalid status', 'correo-argentino-for-woocommerce') . '" style="opacity: 0.4; cursor: not-allowed;">🗑</span>';
    }

    return $output;
}

/**
 * Checks if the order is eligible for deleting the reference
 */
function corrarfo_can_delete_order($order_id)
{
    $order = wc_get_order($order_id);
    if (!$order) return false;
    $allowed_statuses = ['failed', 'checkout-draft','refunded','cancelled','completed']; // Allowed statuses to delete the reference
    if (!in_array($order->get_status(), $allowed_statuses)) {
        return false;
    }

    $envio_id = $order->get_meta('_corrarfo_envio_id');
    return empty($envio_id);
}
