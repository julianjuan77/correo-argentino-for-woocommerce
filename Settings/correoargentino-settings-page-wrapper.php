<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once CORRARFO_PLUGIN_DIR . 'Settings/correoargentino-shipping-adjustments.php';
require_once CORRARFO_PLUGIN_DIR . 'Settings/correoargentino-free-shipping-settings.php';
require_once CORRARFO_PLUGIN_DIR . 'Settings/correoargentino-default-product-values.php';
require_once CORRARFO_PLUGIN_DIR . 'Settings/correoargentino-autosend-settings.php';
require_once CORRARFO_PLUGIN_DIR . 'Settings/correoargentino-uninstall-settings.php';

defined('ABSPATH') || exit;

/**
 * Renders the main "Shipping Adjustments" page, which contains multiple blocks
 * (e.g., surcharge/discount, default values, etc.)
 */
function corrarfo_shipping_adjustments_page()
{
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Shipping Settings', 'correo-argentino-for-woocommerce') . '</h1>';

    // Block: Surcharge / Discount
    if (Corrarfo_Utils::getCurrentServiceType() === CORRARFO_MI_CORREO) {
        if (function_exists('corrarfo_render_shipping_surcharge_settings')) {
            corrarfo_render_shipping_surcharge_settings();
        }
    }

    echo '<hr>';

    // Block: Default values for products without data
    if (function_exists('corrarfo_render_default_product_values_settings')) {
        corrarfo_render_default_product_values_settings();
        echo '<hr>';
    }

    // Block: Free Shipping Settings
    if (Corrarfo_Utils::getCurrentServiceType() === CORRARFO_MI_CORREO) {
        if (function_exists('corrarfo_render_free_shipping_settings')) {
            corrarfo_render_free_shipping_settings();
            echo '<hr>';
        }
    }
    echo '</div>';
}

/**
 * Renders the "General Behavior" page for the plugin settings.
 */
function corrarfo_behavior_settings_page()
{
    $isConnected = Corrarfo_Utils::isConnected();
    $currentServiceType = Corrarfo_Utils::getCurrentServiceType();

    $serviceLabel = $isConnected
        ? (($currentServiceType === CORRARFO_PAQ_AR) ? 'PAQ.AR' : 'MiCorreo')
        : '';

    $pageTitle = __('Dashboard', 'correo-argentino-for-woocommerce') . ($serviceLabel ? " $serviceLabel" : '');

    echo '<div class="wrap">';
    echo '<h1>' . esc_html($pageTitle) . '</h1>';
    if ($isConnected && $currentServiceType === CORRARFO_MI_CORREO) {
        require_once CORRARFO_PLUGIN_DIR . 'Settings/correoargentino-autostatus-settings.php';
        if (function_exists('corrarfo_render_autostatus_settings')) {
            corrarfo_render_autostatus_settings();
        }
        echo '<hr>';
    }

    if ($isConnected && $currentServiceType === CORRARFO_MI_CORREO) {
        require_once CORRARFO_PLUGIN_DIR . 'Settings/correoargentino-autosend-settings.php';
        if (function_exists('corrarfo_render_autosend_settings')) {
            corrarfo_render_autosend_settings();
        }
        echo '<hr>';
    }

    if (Corrarfo_Utils::getCurrentServiceType() === CORRARFO_MI_CORREO) {
        if (function_exists('corrarfo_render_order_prefix_settings')) {
            corrarfo_render_order_prefix_settings();
        }
        echo '<hr>';
    }

    if (function_exists('corrarfo_render_uninstall_settings')) {
        echo '<form method="post" action="options.php">';
        settings_fields('correoargentino_settings');
        corrarfo_render_uninstall_settings();
        submit_button();
        echo '</form>';
        echo '<hr>';
    }


    echo '</div>';
}