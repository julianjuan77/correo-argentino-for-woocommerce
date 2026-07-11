<?php
namespace Corrarfo\Orders;

use Corrarfo\Lib\Utils;
use Corrarfo\Tables\OrderTable;

if (!defined('ABSPATH')) {
    exit;
}

require_once CORRARFO_PLUGIN_DIR . 'Settings/correoargentino-settings-page-wrapper.php';
require_once CORRARFO_PLUGIN_DIR . 'Settings/correoargentino-shipping-adjustments.php';

/**
 * WooCommerce Order Metabox's base Class
 */
class Order
{
    /**
     * Creates Metabox
     *
     * @return void
     */
    public static function build_admin_menu(...$arg)
    {
        $isConnected = Utils::isConnected();
        $currentServiceType = Utils::getCurrentServiceType();
        $loginForm = $currentServiceType == CORRARFO_PAQ_AR ? CORRARFO_LOGIN_PAQ_AR_FORM : CORRARFO_USER_VALIDATE_MI_CORREO_FORM;

        $hook = add_menu_page(
            __('Correo Argentino', 'correo-argentino-for-woocommerce'),
            __('Correo Argentino', 'correo-argentino-for-woocommerce'),
            'manage_woocommerce',
            sanitize_key('correoargentino-orders'),
            [__CLASS__, 'content'],
            CORRARFO_PLUGIN_URL . 'img/logo-icon.svg',
            58
        );

        add_action("load-$hook", [OrderTable::class, 'handle_export_csv']);

        $loginForm = $isConnected ? $loginForm : 'service-selector';
        $settingsApiConnection = 'admin.php?page=wc-settings&tab=shipping&section=correoargentino_shipping_method&form=' . $loginForm;

        add_submenu_page(
            'correoargentino-orders',
            __('API Connection', 'correo-argentino-for-woocommerce'),
            __('API Connection', 'correo-argentino-for-woocommerce'),
            'manage_woocommerce',
            'corrarfo_api_connection',
            function () use ($settingsApiConnection) {
                wp_safe_redirect(admin_url($settingsApiConnection));
                exit;
            }
        );

        if ($isConnected) {
            add_submenu_page(
                'correoargentino-orders',
                __('Shipping Adjustments', 'correo-argentino-for-woocommerce'),
                __('Shipping Adjustments', 'correo-argentino-for-woocommerce'),
                'manage_woocommerce',
                'corrarfo_shipping_adjustments',
                'corrarfo_shipping_adjustments_page'
            );

            $serviceLabel = ($currentServiceType === CORRARFO_PAQ_AR) ? 'PAQ.AR' : 'MiCorreo';
            $menuTitle = __('Dashboard', 'correo-argentino-for-woocommerce') . " $serviceLabel";

            add_submenu_page(
                'correoargentino-orders',
                $menuTitle,
                $menuTitle,
                'manage_woocommerce',
                'corrarfo_behavior_settings',
                'corrarfo_behavior_settings_page'
            );

            add_submenu_page(
                'correoargentino-orders',
                __('Business Data', 'correo-argentino-for-woocommerce'),
                __('Business Data', 'correo-argentino-for-woocommerce'),
                'manage_woocommerce',
                'corrarfo_business_data',
                function () {
                    wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=shipping&section=correoargentino_shipping_method&form=business'));
                    exit;
                }
            );
        }
    }


    /**
     * Prints Metabox Contents
     *
     * @param WC_Post $post
     * @param Metabox $metabox
     * @return void
     */
    public static function content()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Unauthorized.', 'correo-argentino-for-woocommerce'));
        }

        if (!Utils::isConnected()) {
            $currentServiceType = Utils::getCurrentServiceType();
            $loginForm = $currentServiceType == CORRARFO_PAQ_AR ? CORRARFO_LOGIN_PAQ_AR_FORM : CORRARFO_USER_VALIDATE_MI_CORREO_FORM;
            $loginForm = $currentServiceType ? $loginForm : 'service-selector';
            $settingsApiConnection = admin_url('admin.php?page=wc-settings&tab=shipping&section=correoargentino_shipping_method&form=' . $loginForm);

            wp_safe_redirect($settingsApiConnection);
            exit;
        }

        $wp_list_table = new OrderTable();
        $wp_list_table->prepare_items();

        $action_url = remove_query_arg(
            array('export_corrarfo_orders', '_wpnonce', '_wp_http_referer'),
            admin_url('admin.php?page=correoargentino-orders')
        );

        // Read-only reading of $_GET (nonce required).
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $orderby = isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $order_raw = isset($_GET['order']) ? sanitize_key(wp_unslash($_GET['order'])) : '';
        $order = in_array(strtolower($order_raw), array('asc', 'desc'), true) ? strtolower($order_raw) : '';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $post_mime_type = isset($_GET['post_mime_type']) ? sanitize_text_field(wp_unslash($_GET['post_mime_type'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $detached = isset($_GET['detached']) ? absint(wp_unslash($_GET['detached'])) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $s = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

        $extra_args = array();
        if ($orderby) {
            $extra_args['orderby'] = $orderby;
        }
        if ($order) {
            $extra_args['order'] = $order;
        }
        if ($post_mime_type) {
            $extra_args['post_mime_type'] = $post_mime_type;
        }
        if ($detached) {
            $extra_args['detached'] = $detached;
        }
        if ($s !== '') {
            $extra_args['s'] = $s;
        }

        if (!empty($extra_args)) {
            $action_url = add_query_arg($extra_args, $action_url);
        }

        echo '<form method="get" action="' . esc_url($action_url) . '">';
        wp_nonce_field('corrarfo_orders_bulk_action', '_wpnonce', false);
        echo '<input type="hidden" name="page" value="correoargentino-orders" />';
        $wp_list_table->display();
        echo '</form>';
    }
}

// Backward compatibility alias
if (!class_exists('CorreoArgentinoOrder')) {
    class_alias(Order::class, 'CorreoArgentinoOrder');
}
