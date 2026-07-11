<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handler to delete shipping reference (hide order)
 */
function corrarfo_handle_delete_shipping_reference()
{
    if (
        !current_user_can('manage_woocommerce') ||
        !isset($_GET['action'], $_GET['order_id'], $_GET['_wpnonce']) ||
        $_GET['action'] !== 'delete_reference'
    ) {
        return;
    }

    $order_id = absint($_GET['order_id']);

    $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

    if (!wp_verify_nonce($nonce, 'delete_reference_' . $order_id)) {
        wp_die(esc_html__('Invalid nonce. Unauthorized action.', 'correo-argentino-for-woocommerce'));
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_die(esc_html__('Order not found.', 'correo-argentino-for-woocommerce'));
    }

    // Mark as hidden
    $order->update_meta_data('_corrarfo_oculto', '1');
    $order->add_order_note(__('The shipping reference was manually deleted, and the order was hidden from the Correo Argentino list.', 'correo-argentino-for-woocommerce'));
    $order->save();

    wp_safe_redirect(
        add_query_arg(['page' => 'correoargentino-orders', 'deleted' => '1'], admin_url('admin.php'))
    );
    exit;
}
add_action('admin_init', 'corrarfo_handle_delete_shipping_reference');

/**
 * Handler to restore all hidden orders
 */
function corrarfo_handle_restore_hidden_orders()
{
    if (
        !isset($_GET['action'], $_GET['_wpnonce']) ||
        $_GET['action'] !== 'restore_hidden_orders'
    ) {
        return;
    }

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'restore_hidden_orders')) {
        wp_die(esc_html__('Invalid nonce. Unauthorized action.', 'correo-argentino-for-woocommerce'));
    }

    corrarfo_restore_hidden_orders();
}

add_action('admin_init', 'corrarfo_handle_restore_hidden_orders');

/**
 * Restores all hidden orders by removing the _corrarfo_oculto meta.
 * Adds a restoration note to each affected order.
 */
function corrarfo_restore_hidden_orders()
{
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('Unauthorized', 'correo-argentino-for-woocommerce'));
    }

    $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
    if (!wp_verify_nonce($nonce, 'restore_hidden_orders')) {
        wp_die(esc_html__('Invalid nonce', 'correo-argentino-for-woocommerce'));
    }

    $meta_key = '_corrarfo_oculto';

    global $wpdb;

    // Detect HPOS vs Legacy
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $exists_hpos = (bool) $wpdb->get_var(
        $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'wc_orders_meta'))
    );

    // 1. Identify IDs from both potential tables for maximum compatibility
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $ids_legacy = $wpdb->get_col(
        $wpdb->prepare('SELECT post_id FROM %i WHERE meta_key = %s', $wpdb->postmeta, $meta_key)
    );

    $ids_hpos = array();
    if ($exists_hpos) {
        $hpos_table = $wpdb->prefix . 'wc_orders_meta';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $ids_hpos = $wpdb->get_col(
            $wpdb->prepare('SELECT order_id FROM %i WHERE meta_key = %s', $hpos_table, $meta_key)
        );
    }

    $all_ids = array_map('absint', array_unique(array_merge($ids_legacy, $ids_hpos)));

    if (!empty($all_ids)) {
        foreach ($all_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $order->add_order_note(__('The order was manually restored and is now visible again in the Correo Argentino list.', 'correo-argentino-for-woocommerce'));
                // Use object methods to ensure it's cleared from the correct current store
                $order->delete_meta_data($meta_key);
                $order->save();
            }
        }
    }

    // 2. Final mass delete (belt and suspenders)
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
    $wpdb->delete($wpdb->postmeta, array('meta_key' => $meta_key));
    if ($exists_hpos) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        $wpdb->delete($wpdb->prefix . 'wc_orders_meta', array('meta_key' => $meta_key));
    }

    wp_safe_redirect(
        add_query_arg(
            array(
                'page' => 'correoargentino-orders',
                'restored' => '1',
            ),
            admin_url('admin.php')
        )
    );
    exit;
}


