<?php
if (!defined('ABSPATH')) {
    exit;
}


if (is_admin()) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * PSR-4 Autoloader for Corrarfo namespace.
 */
spl_autoload_register(function ($class) {
    $prefix = 'Corrarfo\\';
    $base_dir = __DIR__ . '/';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

require_once CORRARFO_PLUGIN_DIR . 'Lib/Constants.php';
require_once CORRARFO_PLUGIN_DIR . 'Lib/Utils.php';
require_once CORRARFO_PLUGIN_DIR . 'Settings/correoargentino-settings-form.php';
require_once CORRARFO_PLUGIN_DIR . 'Service/correoargentino-service.interface.php';
require_once CORRARFO_PLUGIN_DIR . 'Service/correoargentino-service-factory.php';
require_once CORRARFO_PLUGIN_DIR . 'Service/correoargentino-paq-ar-service.php';
require_once CORRARFO_PLUGIN_DIR . 'Service/correoargentino-mi-correo-service.php';
require_once CORRARFO_PLUGIN_DIR . 'Orders/correoargentino-label.php';
require_once CORRARFO_PLUGIN_DIR . 'Orders/correoargentino-branch.php';
require_once CORRARFO_PLUGIN_DIR . 'correoargentino-hooks.php';
require_once CORRARFO_PLUGIN_DIR . 'Hooks/correoargentino-order-hooks.php';
require_once CORRARFO_PLUGIN_DIR . 'Hooks/correoargentino-order-summary-hooks.php';
require_once CORRARFO_PLUGIN_DIR . 'Hooks/correoargentino-order-autosend-hook.php';
require_once CORRARFO_PLUGIN_DIR . 'Settings/correoargentino-order-prefix-settings.php';
require_once CORRARFO_PLUGIN_DIR . 'Helper/correoargentino-order-prefix-helper.php';
require_once CORRARFO_PLUGIN_DIR . 'Hooks/correoargentino-postcode-validation.php';
// Fork Julian Juan: destino de envío → fallback a facturación (envío a domicilio).
require_once CORRARFO_PLUGIN_DIR . 'Hooks/correoargentino-shipping-destination-fallback.php';

if (is_admin()) {
    require_once CORRARFO_PLUGIN_DIR . 'Orders/correoargentino-metabox.php';
    require_once CORRARFO_PLUGIN_DIR . 'Orders/correoargentino-orders.php';
    require_once CORRARFO_PLUGIN_DIR . 'Helper/correoargentino-list-table.php';
    require_once CORRARFO_PLUGIN_DIR . 'Tables/correoargentino-order-table.php';
    require_once CORRARFO_PLUGIN_DIR . 'Hooks/correoargentino-admin-order-shipping-method.php';
    require_once CORRARFO_PLUGIN_DIR . 'Helper/correoargentino-order-actions-handler.php';
    require_once CORRARFO_PLUGIN_DIR . 'Hooks/correoargentino-admin-hooks.php';
    require_once CORRARFO_PLUGIN_DIR . 'Hooks/correoargentino-admin-post-handlers.php';

    \Corrarfo\Hooks\AdminOrderShippingMethod::init();
    \Corrarfo\Hooks\AdminHooks::init();
}

if (function_exists('corrarfo_dequeue_select2') && !has_action('wp_enqueue_scripts', 'corrarfo_dequeue_select2')) {
    add_action('wp_enqueue_scripts', 'corrarfo_dequeue_select2', 100);
}

add_action('admin_enqueue_scripts', function () {
    wp_enqueue_script(
        'corrarfo-toggle-fields',
        CORRARFO_PLUGIN_URL . 'js/admin-toggle-fields.js',
        [],
        '1.0',
        true
    );
});

add_action('wp_enqueue_scripts', function () {
    if (is_checkout() || is_account_page() || is_cart()) {
        wp_enqueue_script(
            'corrarfo-validate-postcode',
            CORRARFO_PLUGIN_URL . 'js/validate-postcode.js',
            [],
            '1.0',
            true
        );
    }
});
