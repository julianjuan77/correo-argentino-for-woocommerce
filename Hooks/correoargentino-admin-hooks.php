<?php
namespace Corrarfo\Hooks;

use Corrarfo\Lib\Utils;

if (!defined('ABSPATH')) {
    exit;
}

class AdminHooks
{
    public static function init()
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAdminAssets']);
        add_action('admin_notices', [self::class, 'showRestoredOrdersNotice']);
        add_action('admin_notices', [self::class, 'showDeletedReferenceNotice']);
    }

    public static function enqueueAdminAssets($hook)
    {

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        if ($page !== 'correoargentino-orders') {
            return;
        }

        $base_url = CORRARFO_PLUGIN_URL;

        wp_enqueue_style(
            'ca-admin-actions',
            $base_url . 'css/admin-actions.css',
            [],
            defined('CORRARFO_VERSION') ? CORRARFO_VERSION : '1.0'
        );

        wp_enqueue_script(
            'ca-order-list-actions',
            $base_url . 'js/order-list-actions.js',
            [],
            defined('CORRARFO_VERSION') ? CORRARFO_VERSION : '1.0',
            true
        );

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $form = isset($_GET['form']) ? sanitize_key(wp_unslash($_GET['form'])) : '';

        if (
            class_exists('\Corrarfo_Utils')
            && Utils::isConnected()
            && in_array($form, ['user-validate-mi-correo-form', 'login-paq-ar-form'], true)
        ) {
            if (!wp_style_is('ca-admin-actions', 'enqueued')) {
                wp_enqueue_style('ca-admin-actions');
            }
            wp_add_inline_style('ca-admin-actions', '.woocommerce-save-button{display:none!important;}');
        }
    }

    public static function showRestoredOrdersNotice()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $restored = isset($_GET['restored']) ? sanitize_text_field(wp_unslash($_GET['restored'])) : '';

        if ($restored === '1') {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . esc_html__('All hidden orders have been successfully restored.', 'correo-argentino-for-woocommerce') . '</p>';
            echo '</div>';
        }
    }

    public static function showDeletedReferenceNotice()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $deleted = isset($_GET['deleted']) ? sanitize_text_field(wp_unslash($_GET['deleted'])) : '';

        if ($deleted === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                esc_html__('The shipping reference was successfully deleted.', 'correo-argentino-for-woocommerce') .
                '</p></div>';
        }
    }
}

// Backward compatibility alias
if (!class_exists('AdminHooks')) {
    class_alias(AdminHooks::class, 'AdminHooks');
}
