<?php
/*
 * Plugin Name: Correo Argentino for WooCommerce (Fork Julian Juan)
 * Plugin URI: https://github.com/julianjuan77/correo-argentino-for-woocommerce
 * Description: Envíos con Correo Argentino en WooCommerce. Fork NO OFICIAL con correcciones: evita el fatal 500 por token 401, credenciales configurables por wp-config, comportamiento correcto de "otra dirección" por método, fallback de destino a facturación, timeouts y guardas de null, compatibilidad HPOS, reintento de token ante 401 (con fix de zona horaria) y mejoras de UX en envío a sucursal. Basado en la versión oficial 3.0.7 de Correo Argentino.
 * Version: 3.0.7.2
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.9
 * WC tested up to: 9.0
 * Author: Correo Argentino (fork mantenido por Julian Juan / julianjuan77)
 * Author URI: https://www.correoargentino.com.ar/
 * Text Domain: correo-argentino-for-woocommerce
 * Domain Path: /i18n/languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly for security/compliance with WordPress.org
if (!defined('ABSPATH')) {
    exit;
}

// Plugin paths/urls
defined('CORRARFO_PLUGIN_FILE') || define('CORRARFO_PLUGIN_FILE', __FILE__);
defined('CORRARFO_PLUGIN_DIR') || define('CORRARFO_PLUGIN_DIR', plugin_dir_path(__FILE__));
defined('CORRARFO_PLUGIN_URL') || define('CORRARFO_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once CORRARFO_PLUGIN_DIR . 'loader.php';

register_activation_hook(__FILE__, function () {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')), true)) {
        return;
    }

    $settingsInitialValues = [
        'customer_id' => null,
        'first_name' => null,
        'last_name' => null,
        'business_name' => null,
        'email' => null,
        'password' => null,
        'state' => null,
        'state_code' => null,
        'city_name' => null,
        'department' => null,
        'floor' => null,
        'street_name' => null,
        'street_number' => null,
        'zip_code' => null,
        'cellphone' => null,
        'phone' => null,
        'observation' => null,
    ];

    $credentialsInitialValues = [
        "sandbox" => 0,
        "api_key" => null,
        "agreement" => null,
        "auth_hash" => (defined('CORRARFO_USERNAME_MI_CORREO_SANDBOX') && defined('CORRARFO_PASSWORD_MI_CORREO_SANDBOX'))
            ? base64_encode(CORRARFO_USERNAME_MI_CORREO_SANDBOX . ':' . CORRARFO_PASSWORD_MI_CORREO_SANDBOX)
            : null,
        "access_token" => null,
        "expire" => null
    ];

    $roles = ['administrator', 'shop_manager', 'customer'];

    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role && defined('CORRARFO_PLUGIN_ID')) {
            $role->add_cap('manage_' . CORRARFO_PLUGIN_ID);
        }
    }

    add_option(CORRARFO_SETTINGS, serialize($settingsInitialValues));
    add_option(CORRARFO_CREDENTIALS, serialize($credentialsInitialValues));
    add_option(CORRARFO_SERVICE_TYPE, null);
    add_option(CORRARFO_USE_RATES, null);
    add_option(CORRARFO_IS_CONNECTED, 0);
    add_option(CORRARFO_BUSINESS_DETAILS_FILLED, 0);
    // Fork Julian Juan: NO forzar estas opciones en cada activación.
    // - debug_mode 'yes' mostraba avisos internos ("Zona de coincidencia...") a los clientes en el checkout.
    // - ship_to_destination 'shipping' obligaba a "enviar a otra dirección" también en envío a domicilio.
    // El JS del plugin ya marca "otra dirección" solo cuando corresponde (envío a sucursal), así que
    // el default de WooCommerce ('billing') es lo correcto para domicilio. Se respeta cualquier valor ya existente.
    add_option(CORRARFO_WC_SHIPPING_DEBUG_MODE, 'no');
    add_option(CORRARFO_WC_SHIPPING_TO_DESTINATION, 'billing');

    corrarfo_migrate_old_settings();
});

/**
 * Migrates old plugin settings (with 'corrarfo_' prefix) to new settings (with 'corrarfo_' prefix).
 */
function corrarfo_migrate_old_settings()
{
    $migration_map = [
        'ca_remove_on_uninstall' => CORRARFO_OPTION_REMOVE_ON_UNINSTALL,
        'ca_auto_send_enabled' => CORRARFO_OPTION_AUTOSEND_ENABLED,
        'ca_auto_send_trigger_status' => CORRARFO_OPTION_AUTOSEND_TRIGGER,
        'ca_autostatus_enabled' => CORRARFO_OPTION_AUTOSTATUS_ENABLED,
        'ca_autostatus_status' => CORRARFO_OPTION_AUTOSTATUS_STATUS,
        'ca_default_enabled' => CORRARFO_OPTION_DEFAULT_ENABLED,
        'ca_default_height' => CORRARFO_OPTION_DEFAULT_HEIGHT,
        'ca_default_length' => CORRARFO_OPTION_DEFAULT_LENGTH,
        'ca_default_width' => CORRARFO_OPTION_DEFAULT_WIDTH,
        'ca_default_weight' => CORRARFO_OPTION_DEFAULT_WEIGHT,
        'ca_free_shipping_enabled' => CORRARFO_OPTION_FREE_SHIPPING_ENABLED,
        'ca_free_shipping_amount' => CORRARFO_OPTION_FREE_SHIPPING_AMOUNT,
        'ca_prefix_enabled' => CORRARFO_OPTION_PREFIX_ENABLED,
        'ca_prefix_value' => CORRARFO_OPTION_PREFIX_VALUE,
        'ca_shipping_enable' => CORRARFO_OPTION_SHIPPING_ENABLE,
        'ca_shipping_min_total' => CORRARFO_OPTION_SHIPPING_MIN_TOTAL,
        'ca_shipping_percentage' => CORRARFO_OPTION_SHIPPING_PERCENTAGE,
        'ca_shipping_type' => CORRARFO_OPTION_SHIPPING_TYPE,
        // Opciones principales (Namespace antiguo vs nuevo si cambió)
        'woocommerce_ca_shipping_method_settings' => CORRARFO_SETTINGS,
        'woocommerce_ca_shipping_method_credentials' => CORRARFO_CREDENTIALS,
        'woocommerce_ca_shipping_method_use_rates' => CORRARFO_USE_RATES,
        'woocommerce_ca_shipping_method_is_connected' => CORRARFO_IS_CONNECTED,
        'woocommerce_ca_shipping_method_business_details_filled' => CORRARFO_BUSINESS_DETAILS_FILLED,
    ];

    foreach ($migration_map as $old_key => $new_constant) {
        $old_value = get_option($old_key);
        if (false !== $old_value) {
            $new_value = get_option($new_constant);
            // Only migrate if the new option doesn't exist or is empty
            if (false === $new_value || '' === $new_value) {
                update_option($new_constant, $old_value);
            }
        }
    }
}


add_action('plugins_loaded', function () {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')), true)) {
        add_action('admin_notices', function () {
            $message = sprintf(
                /* translators: %s: Plugin name */
                esc_html__('The %s plugin requires WooCommerce to be active.', 'correo-argentino-for-woocommerce'),
                '<strong>' . esc_html__('Correo Argentino', 'correo-argentino-for-woocommerce') . '</strong>'
            );
            echo '<div class="notice notice-error"><p>' . wp_kses_post($message) . '</p></div>';
        });
        return;
    }

});






// This deletes all the initial plugin settings
register_deactivation_hook(__FILE__, function () {
    $roles = ['administrator', 'shop_manager', 'customer'];

    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role && defined('CORRARFO_PLUGIN_ID')) {
            $role->remove_cap('manage_' . CORRARFO_PLUGIN_ID);
        }
    }

    delete_option(CORRARFO_SETTINGS);
    delete_option(CORRARFO_CREDENTIALS);
    delete_option(CORRARFO_SERVICE_TYPE);
    delete_option(CORRARFO_USE_RATES);
    delete_option(CORRARFO_IS_CONNECTED);
    delete_option(CORRARFO_BUSINESS_DETAILS_FILLED);
});


// Removed redundant Correo_Argentino_Shipping_Method function.

function corrarfo_validate_order($posted)
{
    $packages = WC()->shipping->get_packages();
    $chosen_methods = WC()->session->get('chosen_shipping_methods');

    if (!is_array($chosen_methods)) {
        return;
    }

    foreach ($packages as $i => $package) {
        if (!isset($chosen_methods[$i]) || $chosen_methods[$i] !== CORRARFO_PLUGIN_ID) {
            continue;
        }

        $method = new Corrarfo_Shipping_Method();
        $weight_max = (int) $method->get_option('weight', 0);
        if ($weight_max <= 0) {
            $service_type = Corrarfo_Utils::getCurrentServiceType();
            $weight_grams = ($service_type === CORRARFO_PAQ_AR) ? CORRARFO_MAX_WEIGHT_PAQ_AR : CORRARFO_MAX_WEIGHT_MI_CORREO;
            $weight_max = $weight_grams / 1000;
        }

        $weight_total = 0.0;
        foreach ($package['contents'] as $values) {
            /** @var WC_Product $product */
            $product = isset($values['data']) ? $values['data'] : null;
            $quantity = isset($values['quantity']) ? (int) $values['quantity'] : 1;

            if (!$product instanceof WC_Product) {
                continue;
            }

            $unit_weight = corrarfo_get_product_weight_kg($product);
            $weight_total += $unit_weight * max(1, $quantity);
        }

        if ($weight_max > 0 && $weight_total > $weight_max) {
            $message = sprintf(
                /* translators: 1: actual weight, 2: weight limit, 3: shipping method title */
                __('OOPS, %1$s kg exceeds the maximum weight of %2$s kg for %3$s', 'correo-argentino-for-woocommerce'),
                wc_format_decimal($weight_total, 2),
                wc_format_decimal($weight_max, 2),
                $method->get_title()
            );
            if (!wc_has_notice($message, 'error')) {
                wc_add_notice($message, 'error');
            }
        }
    }
}

/**
 * Helper to get product weight in kg, falling back to defaults if needed.
 *
 * @param WC_Product $product The product object.
 * @return float Weight in kg.
 */
function corrarfo_get_product_weight_kg($product)
{
    if (!$product instanceof WC_Product) {
        return 0.0;
    }

    $weight = (float) $product->get_weight();
    if ($weight <= 0) {
        $weight = (float) get_option('corrarfo_default_weight', 0);
    }
    if ($weight <= 0) {
        $weight = 0.1; // kg mínimo
    }

    return (float) wc_get_weight($weight, 'kg');
}


function corrarfo_add_select2_scripts()
{
    if (is_cart() || is_checkout()) {
        wp_enqueue_style(
            'select2-css',
            CORRARFO_PLUGIN_URL . 'assets/vendor/select2/4.0.13/dist/css/select2.min.css',
            [],
            '4.0.13'
        );
        wp_enqueue_script(
            'select2-js',
            CORRARFO_PLUGIN_URL . 'assets/vendor/select2/4.0.13/dist/js/select2.full.min.js',
            ['jquery'],
            '4.0.13',
            true
        );
        wp_enqueue_style(
            'select2-override-css',
            CORRARFO_PLUGIN_URL . 'css/select2-override.css',
            ['select2-css'],
            '4.0.13'
        );
    }
}


function corrarfo_enqueue_admin_scripts()
{
    if (is_admin()) {
        wp_enqueue_style('validate-css', CORRARFO_PLUGIN_URL . 'css/validate.css', [], time());
        wp_enqueue_script('validate', CORRARFO_PLUGIN_URL . 'assets/js/jquery.validate.min.js', ['jquery'], '1.21.0', true);
        wp_enqueue_script('validate-methods', CORRARFO_PLUGIN_URL . 'assets/js/additional-methods.min.js', ['validate'], '1.21.0', true);
        wp_enqueue_script('masked-input', CORRARFO_PLUGIN_URL . 'js/jquery.mask.min.js', ['jquery'], '1.14.16', true);
        wp_enqueue_script('validate-config-form', CORRARFO_PLUGIN_URL . 'js/validate-config-form.js', ['validate', 'masked-input'], time(), true);
        wp_enqueue_script('state-select2', CORRARFO_PLUGIN_URL . 'js/select2-init.js', ['select2-js'], time(), true);
        wp_enqueue_script('service-selector', CORRARFO_PLUGIN_URL . 'js/service-selector.js', ['select2-js'], time(), true);
    }
}

function corrarfo_get_edit_address_url()
{
    $user_id = get_current_user_id();

    if ($user_id) {
        $endpoint = 'edit-address/shipping/';
        return wc_get_account_endpoint_url($endpoint);
    }

    return false;
}

/**
 * This method sets the branch ID through
 */
function corrarfo_choose_branch_handler()
{
    if (is_cart() || is_checkout()) {
        wp_enqueue_script(
            'corrarfo-branch',
            CORRARFO_PLUGIN_URL . 'js/branch.js',
            ['jquery', 'select2-js'],
            time(),
            true
        );

        wp_localize_script('corrarfo-branch', 'corrarfo_branch_cfg', [
            'ajaxUrl' => admin_url('admin-ajax.php', 'relative'),
            'editAddressUrl' => corrarfo_get_edit_address_url(),
            'isCart' => is_cart() ? '1' : '0',
            'nonceBranch' => wp_create_nonce('corrarfo_update_branch'),
            'nonceProvince' => wp_create_nonce('corrarfo_update_province'),
        ]);
    }
}

add_action('wp_ajax_corrarfo_update_branch', 'corrarfo_update_branch');
add_action('wp_ajax_nopriv_corrarfo_update_branch', 'corrarfo_update_branch');

function corrarfo_update_branch()
{
    // 1. Security check: validate Nonce (Fail early)
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'corrarfo_update_branch')) {
        wp_send_json_error(array('message' => 'Invalid or expired security token'), 403);
        wp_die();
    }

    // 2. Permission check: allow admins, customers and guests in checkout flow
    // Security rests on the WordPress Nonce which is generated per-session on the server.
    // If we have a valid WooCommerce session, we consider it a legitimate checkout/cart intent.
    $is_checkout_intent = (function_exists('WC') && WC()->session);
    if (!current_user_can('manage_woocommerce') && !current_user_can('customer') && !$is_checkout_intent) {
        wp_send_json_error(array('message' => 'Unauthorized access level'), 403);
        wp_die();
    }

    $method = isset($_SERVER['REQUEST_METHOD'])
        ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])))
        : '';

    if ($method !== 'POST') {
        wp_send_json_error(array('message' => 'Method not allowed'), 405);
        wp_die();
    }

    $required = [
        'branch_id',
        'branch_name',
        'branch_zip_code',
        'branch_city',
        'branch_state',
        'branch_street_name',
        'branch_street_number',
        'branch_chosen_product_type',
    ];
    foreach ($required as $key) {
        if (!isset($_POST[$key]) || $_POST[$key] === '') {
            wp_send_json_error(['message' => sprintf('Falta el campo: %s', $key)], 400);
        }
    }

    $branch_id = isset($_POST['branch_id']) ? sanitize_text_field(wp_unslash($_POST['branch_id'])) : '';
    $branch_name = isset($_POST['branch_name']) ? sanitize_text_field(wp_unslash($_POST['branch_name'])) : '';
    $branch_zip_code = isset($_POST['branch_zip_code']) ? sanitize_text_field(wp_unslash($_POST['branch_zip_code'])) : '';
    $branch_city = isset($_POST['branch_city']) ? sanitize_text_field(wp_unslash($_POST['branch_city'])) : '';
    $branch_state = isset($_POST['branch_state']) ? sanitize_text_field(wp_unslash($_POST['branch_state'])) : '';
    $branch_street_name = isset($_POST['branch_street_name']) ? sanitize_text_field(wp_unslash($_POST['branch_street_name'])) : '';
    $branch_street_number = isset($_POST['branch_street_number']) ? sanitize_text_field(wp_unslash($_POST['branch_street_number'])) : '';
    $chosen_product_type = isset($_POST['branch_chosen_product_type']) ? sanitize_text_field(wp_unslash($_POST['branch_chosen_product_type'])) : '';

    $current_service_type = '';
    if (class_exists('Corrarfo_Utils') && method_exists('Corrarfo_Utils', 'getCurrentServiceType')) {
        $current_service_type = Corrarfo_Utils::getCurrentServiceType();
    }

    if (function_exists('WC') && WC()->session) {
        WC()->session->set(CORRARFO_CHOSEN_BRANCH, $branch_id);
        WC()->session->set(CORRARFO_CHOSEN_BRANCH_NAME, $branch_name);
        WC()->session->set(CORRARFO_CHOSEN_BRANCH_STREET_NAME, $branch_street_name);
        WC()->session->set(CORRARFO_CHOSEN_BRANCH_STREET_NUMBER, $branch_street_number);
        WC()->session->set(CORRARFO_CHOSEN_BRANCH_CITY, $branch_city);
        WC()->session->set(CORRARFO_CHOSEN_BRANCH_STATE, $branch_state);
        WC()->session->set(CORRARFO_CHOSEN_BRANCH_ZIP_CODE, $branch_zip_code);
        WC()->session->set(CORRARFO_CHOSEN_SERVICE_TYPE, $current_service_type);
        WC()->session->set(CORRARFO_CHOSEN_PRODUCT_TYPE, $chosen_product_type);
    }

    $totals = [
        'shipping_total' => 0,
        'fee_total' => 0,
        'total' => 0,
    ];
    if (function_exists('WC') && WC()->cart) {
        $totals = WC()->cart->get_totals();
    }

    wp_send_json_success([
        'data' => 'ok',
        'branch_id' => $branch_id,
        'branch_name' => $branch_name,
        'branch_city' => $branch_city,
        'branch_state' => $branch_state,
        'branch_zip_code' => $branch_zip_code,
        'branch_street_name' => $branch_street_name,
        'branch_street_number' => $branch_street_number,
        'branch_chosen_product_type' => $chosen_product_type,
        'current_service_type' => $current_service_type,
        'shipping_total' => function_exists('wc_price') ? wc_price($totals['shipping_total']) : $totals['shipping_total'],
        'fee_total' => function_exists('wc_price') ? wc_price($totals['fee_total']) : $totals['fee_total'],
        'cart_total' => function_exists('wc_price') ? wc_price($totals['total']) : $totals['total'],
        'totals' => $totals,
    ]);
}

function corrarfo_update_province()
{
    // 1. Security check: validate Nonce (Fail early)
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'corrarfo_update_province')) {
        wp_send_json_error(array('message' => 'Invalid or expired security token'), 403);
        wp_die();
    }

    // 2. Permission check: allow admins, customers and guests in checkout flow
    $is_checkout_intent = (function_exists('WC') && WC()->session);
    if (!current_user_can('manage_woocommerce') && !current_user_can('customer') && !$is_checkout_intent) {
        wp_send_json_error(array('message' => 'Unauthorized access level'), 403);
        wp_die();
    }

    $province_code = null;
    $province_list = [];

    if (isset($_POST['province_code'])) {
        $province_code = sanitize_text_field(wp_unslash($_POST['province_code']));

        if (function_exists('WC') && WC()->session) {
            if ($province_code !== WC()->session->get(CORRARFO_CHOSEN_PROVINCE_CODE)) {
                Corrarfo_Utils::unset_wc_chosen_branch_values();
            }

            WC()->session->set(CORRARFO_CHOSEN_PROVINCE_CODE, $province_code);
        }

        $province_list = CorreoArgentinoBranch::listBranches();
    }

    $response = (count($province_list) > 0)
        ? [
            'data' => 'ok',
            'province_code' => $province_code,
            'province_list' => $province_list,
            'branch_id' => (function_exists('WC') && WC()->session) ? WC()->session->get(CORRARFO_CHOSEN_BRANCH) : null,
        ]
        : [
            'data' => 'fail',
            'province_code' => $province_code,
            'province_list' => $province_list,
            'branch_id' => (function_exists('WC') && WC()->session) ? WC()->session->get(CORRARFO_CHOSEN_BRANCH) : null,
        ];

    wp_send_json($response);
    wp_die();
}

function corrarfo_process_orders()
{
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(array('message' => 'Unauthorized capability'), 403);
        wp_die();
    }

    $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
    if (!wp_verify_nonce($nonce, 'corrarfo_process_orders')) {
        wp_send_json_error(array('message' => 'Unauthorized or invalid nonce'), 403);
        wp_die();
    }

    if (!isset($_POST['order_id'])) {
        wp_send_json_error(array('message' => 'Missing order_id'), 400);
        wp_die();
    }

    $orderId = absint(wp_unslash($_POST['order_id']));
    if (!$orderId) {
        wp_send_json_error(array('message' => 'Invalid order_id'), 400);
        wp_die();
    }

    $service = (new Corrarfo_ServiceFactory())->get();
    $response = is_object($service) && method_exists($service, 'registerOrder')
        ? $service->registerOrder($orderId)
        : null;

    $error = is_array($response) && !empty($response['error']);
    $reference = is_array($response) && isset($response['reference']) ? $response['reference'] : null;
    $api_errmsg = is_array($response) && isset($response['api_error_message']) ? $response['api_error_message'] : '';

    if ($error) {
        $err_body = isset($response['error_body']) ? $response['error_body'] : '';
        $decoded_body = is_string($err_body) ? json_decode($err_body) : null;
        $msg = (is_object($decoded_body) && isset($decoded_body->message)) ? $decoded_body->message : 'Unknown error';

        $data = array(
            'status' => 'fail',
            'message' => $msg,
            'reference' => null,
            'order_id' => $orderId,
            'api_error_message' => $api_errmsg,
        );
        wp_send_json(array('data' => $data), 400);
        wp_die();
    }

    $data = array(
        'status' => 'ok',
        'message' => 'Successful operation',
        'reference' => $reference,
        'order_id' => $orderId,
        'api_error_message' => $api_errmsg,
    );

    wp_send_json(array('data' => $data), 201);
    wp_die();
}

/**
 * @param $order_id
 */
function corrarfo_new_order_handler($order_id)
{
    // Fork Julian Juan: al crear un pedido MANUAL desde el admin también se
    // dispara woocommerce_new_order, pero ahí la sesión de WooCommerce no existe
    // (WC()->session es null). Acceder a WC()->session->get() reventaba con un
    // error fatal silencioso. La sucursal solo llega por sesión en el checkout
    // del front, así que en el admin salimos sin hacer nada.
    if (!function_exists('WC') || !WC()->session || !WC()->session->has_session()) {
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    $current_branch = WC()->session->get(CORRARFO_CHOSEN_BRANCH);
    $current_branch_name = WC()->session->get(CORRARFO_CHOSEN_BRANCH_NAME);
    $current_branch_zip_code = WC()->session->get(CORRARFO_CHOSEN_BRANCH_ZIP_CODE);
    $current_province_code = WC()->session->get(CORRARFO_CHOSEN_PROVINCE_CODE);
    $current_service_type = WC()->session->get(CORRARFO_CHOSEN_SERVICE_TYPE);
    $chosen_product_type = WC()->session->get(CORRARFO_CHOSEN_PRODUCT_TYPE);
    
    $order->update_meta_data(CORRARFO_CHOSEN_BRANCH, $current_branch);
    $order->update_meta_data(CORRARFO_CHOSEN_BRANCH_NAME, $current_branch_name);
    $order->update_meta_data(CORRARFO_CHOSEN_BRANCH_ZIP_CODE, $current_branch_zip_code);
    $order->update_meta_data(CORRARFO_CHOSEN_PROVINCE_CODE, $current_province_code);
    $order->update_meta_data(CORRARFO_CHOSEN_SERVICE_TYPE, $current_service_type);
    $order->update_meta_data(CORRARFO_CHOSEN_PRODUCT_TYPE, $chosen_product_type);
    $order->save();
    Corrarfo_Utils::unset_wc_chosen_branch_values();
}

/**
 * @param $order_id
 * @return bool | void
 * @throws Exception
 */

function corrarfo_on_payment_complete_handler($order_id)
{
    if (!Corrarfo_Utils::shouldProcessOrder($order_id)) {
        return;
    }

    $service = (new Corrarfo_ServiceFactory())->get();
    $response = $service->registerOrder($order_id);

    if (isset($response) && $response["error"] === true) {
        $error_body = json_decode($response["error_body"]);
        $corrarfo_error = $error_body->message;
        WC_Admin_Notices::add_custom_notice('corrarfo_error', "Error al preimponer la orden: " . $corrarfo_error);
        return false;
    } else {
        return true;
    }
}

/**
 * @param $order_id
 * @return bool
 */
function corrarfo_on_order_cancelled_handler($order_id)
{
    if (Corrarfo_Utils::getCurrentServiceType() === CORRARFO_MI_CORREO) {
        return true;
    }
    $order = wc_get_order($order_id);
    $tracking = $order->get_meta(CORRARFO_TRACKING_NUMBER);
    if ($tracking) {
        $service = (new Corrarfo_ServiceFactory())->get();
        $result = $service->cancel($tracking);
        if ($result) {
            return true;
        }
        return false;
    }
    return false;
}

// Declare compatibility with WooCommerce High-Performance Order Storage (HPOS / Custom Order Tables)
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});
