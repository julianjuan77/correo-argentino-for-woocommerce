<?php
if (!defined('ABSPATH')) {
    exit;
}

use Corrarfo\Lib\Utils;
use Corrarfo\Orders\Metabox;
use Corrarfo\Orders\Label;
use Corrarfo\Orders\Branch;
use Corrarfo\Service\ServiceFactory;

/**
 * Remove Woocommerce Select2 - Woocommerce 3.2.1+
 */
function corrarfo_dequeue_select2()
{
    if (class_exists('WooCommerce') && (is_cart() || is_checkout())) {
        wp_dequeue_style('select2');
        wp_deregister_style('select2');

        wp_dequeue_script('selectWoo');
        wp_deregister_script('selectWoo');
    }
}

add_action('woocommerce_review_order_before_cart_contents', 'corrarfo_validate_order', 10);
add_action('woocommerce_after_checkout_validation', 'corrarfo_validate_order', 10);
add_filter('woocommerce_shipping_methods', function ($methods) {
    if (defined('CORRARFO_PLUGIN_ID')) {
        $methods[CORRARFO_PLUGIN_ID] = 'Corrarfo_Shipping_Method';
    } else {
        $methods['correoargentino_shipping_method'] = 'Corrarfo_Shipping_Method';
    }
    return $methods;
});
add_action('woocommerce_shipping_init', 'corrarfo_shipping_method_init');
function corrarfo_shipping_method_init()
{
    if (!class_exists('Corrarfo_Shipping_Method')) {
        require_once CORRARFO_PLUGIN_DIR . 'Classes/correoargentino-shipping-method.php';
    }
}

add_action('add_meta_boxes', [Metabox::class, 'create']);
add_action('rest_api_init', [Label::class, 'create']);
add_action('woocommerce_after_shipping_rate', [Branch::class, 'showBranches']);
add_action('woocommerce_review_order_before_payment', function () {
    echo "
        <div class='correo_argentino_review_order_section'>
            <table class='shop_table'>
                <tbody>
                    <tr>
                        <td>
                            <address id='correo_argentino_review_order_section_address' ></address>
                        </td>
                    </tr>
                </tbody>
            </table>        
        </div>
    ";
});

add_action('wp_ajax_corrarfo_update_province', 'corrarfo_update_province');
add_action('wp_ajax_nopriv_corrarfo_update_province', 'corrarfo_update_province');

add_action('wp_ajax_corrarfo_process_orders', 'corrarfo_process_orders');

add_action("wp_footer", "corrarfo_add_select2_scripts");

add_action('wp_footer', 'corrarfo_choose_branch_handler');


add_action('woocommerce_new_order', 'corrarfo_new_order_handler');

add_filter('woocommerce_checkout_fields', 'corrarfo_checkout_fields_trigger_refresh', 9999);

function corrarfo_checkout_fields_trigger_refresh($fields)
{
    $fields['shipping']['shipping_state']['class'][] = 'update_totals_on_change';

    return $fields;
}

add_filter('woocommerce_default_address_fields', 'corrarfo_override_default_address_fields');

function corrarfo_override_default_address_fields($fields)
{
    if (Utils::getCurrentServiceType() === CORRARFO_MI_CORREO) {
        $fields['address_1']['label'] = 'Calle';
        $fields['address_1']['placeholder'] = 'Calle';
    }
    return $fields;
}


add_action('woocommerce_checkout_create_order_shipping_item', 'corrarfo_checkout_create_order_shipping_item', 10, 4);
function corrarfo_checkout_create_order_shipping_item($item, $package_key, $package, $order)
{
    $current_branch = WC()->session->get(CORRARFO_CHOSEN_BRANCH);
    $current_branch_name = WC()->session->get(CORRARFO_CHOSEN_BRANCH_NAME);
    $current_branch_zip_code = WC()->session->get(CORRARFO_CHOSEN_BRANCH_ZIP_CODE);

    $shipping_method_id = $item->get_method_id();

    if ($shipping_method_id == CORRARFO_PLUGIN_ID) {
        $shipping_instance_id = (int) $item->get_instance_id();
        $shipping_method = new Corrarfo_Shipping_Method($shipping_instance_id);
        if (isset($shipping_method->corrarfo_data) && isset($shipping_method->corrarfo_data[$item->get_instance_id()])) {
            $item->update_meta_data('corrarfo_data', $shipping_method->corrarfo_data[$shipping_instance_id]);
        }
    }

    $item->update_meta_data(CORRARFO_CHOSEN_BRANCH, $current_branch, true);
    $item->update_meta_data(CORRARFO_CHOSEN_BRANCH_NAME, $current_branch_name, true);
    $item->update_meta_data(CORRARFO_CHOSEN_BRANCH_ZIP_CODE, $current_branch_zip_code, true);
}

add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values, $order) {
    $p = $item->get_product();
    $qty = max(1, (int) $item->get_quantity());

    $w = (float) $p->get_weight();
    if ($w <= 0)
        $w = (float) get_option('corrarfo_default_weight', 0);
    if ($w <= 0)
        $w = 0.1; // kg mínimo

    $len = (float) $p->get_length();
    if ($len <= 0)
        $len = (float) get_option('corrarfo_default_length', 0);

    $wid = (float) $p->get_width();
    if ($wid <= 0)
        $wid = (float) get_option('corrarfo_default_width', 0);

    $hei = (float) $p->get_height();
    if ($hei <= 0)
        $hei = (float) get_option('corrarfo_default_height', 0);

    $w = (float) wc_get_weight($w, 'kg');
    $len = (float) wc_get_dimension($len, 'cm');
    $wid = (float) wc_get_dimension($wid, 'cm');
    $hei = (float) wc_get_dimension($hei, 'cm');

    $item->update_meta_data('_corrarfo_weight', $w);
    $item->update_meta_data('_corrarfo_length', $len);
    $item->update_meta_data('_corrarfo_width', $wid);
    $item->update_meta_data('_corrarfo_height', $hei);
    $item->update_meta_data('_corrarfo_weight_total', $w * $qty);
}, 10, 4);

add_action('woocommerce_order_status_cancelled', 'corrarfo_on_order_cancelled_handler', 10);

add_action('woocommerce_update_options_shipping_' . CORRARFO_PLUGIN_ID, 'corrarfo_on_settings_updated_handler');
/**
 * @throws Exception
 */
function corrarfo_on_settings_updated_handler()
{
    $redirectUrlBase = '?page=wc-settings&tab=shipping&section=correoargentino_shipping_method&form=';

    try {
        // Read-only reading (nonce required).
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $form = isset($_GET['form']) ? sanitize_key(wp_unslash($_GET['form'])) : '';

        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        // If POST is selected, validate the nonce in WooCommerce Settings **only once**.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_SERVER['REQUEST_METHOD']) && 'POST' === $_SERVER['REQUEST_METHOD']) {
            check_admin_referer('woocommerce-settings');
        }

        // ===== Selector de servicio =====
        if ($form === CORRARFO_SERVICE_SELECTOR) {
            $serviceType = isset($_POST[CORRARFO_SERVICE_TYPE_FIELD])
                ? sanitize_key(wp_unslash($_POST[CORRARFO_SERVICE_TYPE_FIELD]))
                : '';

            $useRates = isset($_POST[CORRARFO_USE_RATES])
                ? sanitize_key(wp_unslash($_POST[CORRARFO_USE_RATES]))
                : '';

            if ($serviceType !== '') {
                update_option(CORRARFO_SERVICE_TYPE, $serviceType);
                update_option(CORRARFO_USE_RATES, $useRates);

                $service = (new ServiceFactory())->get();
                $response = $service->login();

                if (isset($response['token'])) {
                    $credentials = Utils::getCredentials();
                    $credentials['access_token'] = sanitize_text_field($response['token']);
                    $credentials['expire'] = isset($response['expire']) ? sanitize_text_field($response['expire']) : '';
                    update_option(CORRARFO_CREDENTIALS, maybe_serialize($credentials));
                }

                $formType = ($serviceType === CORRARFO_PAQ_AR)
                    ? CORRARFO_LOGIN_PAQ_AR_FORM
                    : CORRARFO_USER_VALIDATE_MI_CORREO_FORM;

                WC_Admin_Settings::add_message(CORRARFO_SUCCESS_ON_CONNECTING_TO_MI_CORREO);
                wp_safe_redirect($redirectUrlBase . $formType);
                exit;
            }

            WC_Admin_Settings::add_error(CORRARFO_ERROR_ON_CONNECTING_TO_MI_CORREO);
            return false;
        }

        // ===== Credenciales: MiCorreo (validación de usuario) =====
        if ($form === CORRARFO_USER_VALIDATE_MI_CORREO_FORM) {
            if (isset($_POST[CORRARFO_EMAIL_FIELD], $_POST[CORRARFO_PASSWORD_FIELD])) {
                $settings = Utils::getSettings();
                $credentials = Utils::getCredentials();

                $businessDetailsFilled = Utils::businessDetailsFilled();
                $email = sanitize_text_field(wp_unslash($_POST[CORRARFO_EMAIL_FIELD]));
                $password = sanitize_text_field(wp_unslash($_POST[CORRARFO_PASSWORD_FIELD]));

                $credentials = Utils::getCredentials();
                $isSandbox = isset($_POST[CORRARFO_SANDBOX_FIELD]) ? 1 : 0;

                // Update sandbox and auth_hash BEFORE instantiating the service
                $credentials['sandbox'] = $isSandbox;
                $credentials['access_token'] = null;
                $credentials['expire'] = null;

                $username_env = ($isSandbox === 1) ? CORRARFO_USERNAME_MI_CORREO_SANDBOX : CORRARFO_USERNAME_MI_CORREO_PRODUCTION;
                $password_env = ($isSandbox === 1) ? CORRARFO_PASSWORD_MI_CORREO_SANDBOX : CORRARFO_PASSWORD_MI_CORREO_PRODUCTION;
                $credentials['auth_hash'] = base64_encode($username_env . ':' . $password_env);

                update_option(CORRARFO_CREDENTIALS, maybe_serialize($credentials));

                $service = (new ServiceFactory())->get();
                $resp = $service->userValidate($email, $password);

                if (!isset($resp) || (int) ($resp['status'] ?? 0) !== 200) {
                    WC_Admin_Settings::add_error(CORRARFO_ERROR_ON_CONNECTING_TO_MI_CORREO);
                    return false;
                }

                $credentials['idCliente'] = isset($resp['customerId']) ? sanitize_text_field($resp['customerId']) : ($credentials['idCliente'] ?? '');
                $credentials['sandbox'] = isset($_POST[CORRARFO_SANDBOX_FIELD]) ? 1 : 0;

                // Refresh auth_hash based on sandbox environment
                $username = ($credentials['sandbox'] === 1) ? CORRARFO_USERNAME_MI_CORREO_SANDBOX : CORRARFO_USERNAME_MI_CORREO_PRODUCTION;
                $password = ($credentials['sandbox'] === 1) ? CORRARFO_PASSWORD_MI_CORREO_SANDBOX : CORRARFO_PASSWORD_MI_CORREO_PRODUCTION;
                $credentials['auth_hash'] = base64_encode($username . ':' . $password);

                $settings['access_token'] = isset($resp['access_token']) ? sanitize_text_field($resp['access_token']) : '';
                $settings['expire'] = isset($resp['expire']) ? sanitize_text_field($resp['expire']) : '';

                update_option(CORRARFO_CREDENTIALS, maybe_serialize($credentials));
                update_option(CORRARFO_SETTINGS, maybe_serialize($settings));
                update_option(CORRARFO_IS_CONNECTED, 1);

                WC_Admin_Settings::add_message(CORRARFO_SUCCESS_ON_CONNECTING_TO_MI_CORREO);

                if (!$businessDetailsFilled) {
                    wp_safe_redirect($redirectUrlBase . CORRARFO_BUSINESS_DETAILS_FORM . '&&setup=1');
                    exit;
                }

                return true;
            }
        }

        // ===== Credenciales: Paq.Ar =====
        if ($form === CORRARFO_LOGIN_PAQ_AR_FORM) {
            $credentials = Utils::getCredentials();

            if (isset($_POST[CORRARFO_AGREEMENT_FIELD], $_POST[CORRARFO_APIKEY_FIELD])) {
                $businessDetailsFilled = Utils::businessDetailsFilled();

                $agreement = sanitize_text_field(wp_unslash($_POST[CORRARFO_AGREEMENT_FIELD]));
                $apiKey = sanitize_text_field(wp_unslash($_POST[CORRARFO_APIKEY_FIELD]));

                $isSandbox = isset($_POST[CORRARFO_SANDBOX_FIELD]) ? 1 : 0;
                $credentials['agreement'] = $agreement;
                $credentials['api_key'] = $apiKey;
                $credentials['sandbox'] = $isSandbox;

                // Clear old tokens to avoid poisoning the new session
                $credentials['access_token'] = null;
                $credentials['expire'] = null;

                // Refresh auth_hash based on sandbox environment
                $username = ($isSandbox === 1) ? CORRARFO_USERNAME_MI_CORREO_SANDBOX : CORRARFO_USERNAME_MI_CORREO_PRODUCTION;
                $password = ($isSandbox === 1) ? CORRARFO_PASSWORD_MI_CORREO_SANDBOX : CORRARFO_PASSWORD_MI_CORREO_PRODUCTION;
                $credentials['auth_hash'] = base64_encode($username . ':' . $password);

                update_option(CORRARFO_CREDENTIALS, maybe_serialize($credentials));

                $service = (new ServiceFactory())->get();
                $response = $service->login();

                if ($response && (int) ($response['status'] ?? 0) === 204) {
                    update_option(CORRARFO_IS_CONNECTED, 1);
                    WC_Admin_Settings::add_message(CORRARFO_SUCCESS_ON_CONNECTING_TO_PAQ_AR);

                    if (!$businessDetailsFilled) {
                        wp_safe_redirect($redirectUrlBase . CORRARFO_BUSINESS_DETAILS_FORM . '&&setup=1');
                        exit;
                    }

                    return true;
                }

                $msg = isset($response['message']) ? $response['message'] : '';
                WC_Admin_Settings::add_error(CORRARFO_ERROR_ON_CONNECTING_TO_PAQ_AR . ($msg ? " \nError: " . $msg : ''));
                return false;
            }
        }

        // ===== Crear cuenta (MiCorreo) =====
        if ($form === CORRARFO_BUSINESS_MI_CORREO_FORM) {
            $credentials = maybe_unserialize(get_option(CORRARFO_CREDENTIALS));
            $settings = maybe_unserialize(get_option(CORRARFO_SETTINGS));

            if (isset($_POST[CORRARFO_FORM_SUBMIT])) {
                if (empty($credentials['access_token'])) {
                    WC_Admin_Settings::add_error(CORRARFO_NO_ACCESS_TOKEN_MESSAGE);
                }

                $service = (new ServiceFactory())->get();

                $address = [
                    'streetName' => isset($_POST[CORRARFO_STREET_NAME_FIELD]) ? sanitize_text_field(wp_unslash($_POST[CORRARFO_STREET_NAME_FIELD])) : '',
                    'streetNumber' => isset($_POST[CORRARFO_STREET_NUMBER_FIELD]) ? sanitize_text_field(wp_unslash($_POST[CORRARFO_STREET_NUMBER_FIELD])) : '',
                    'floor' => isset($_POST[CORRARFO_FLOOR_FIELD]) ? sanitize_text_field(wp_unslash($_POST[CORRARFO_FLOOR_FIELD])) : '',
                    'apartment' => isset($_POST[CORRARFO_DEPARTMENT_FIELD]) ? sanitize_text_field(wp_unslash($_POST[CORRARFO_DEPARTMENT_FIELD])) : '',
                    'city' => isset($_POST[CORRARFO_CITY_NAME_FIELD]) ? sanitize_text_field(wp_unslash($_POST[CORRARFO_CITY_NAME_FIELD])) : '',
                    'provinceCode' => isset($_POST[CORRARFO_STATE_CODE_FIELD]) ? sanitize_text_field(wp_unslash($_POST[CORRARFO_STATE_CODE_FIELD])) : '',
                    'postalCode' => isset($_POST[CORRARFO_ZIP_CODE_FIELD]) ? Utils::normalizeZipCode(sanitize_text_field(wp_unslash($_POST[CORRARFO_ZIP_CODE_FIELD]))) : '',
                ];

                $body = [
                    'firstName' => isset($_POST[CORRARFO_FIRST_NAME_FIELD]) ? sanitize_text_field(wp_unslash($_POST[CORRARFO_FIRST_NAME_FIELD])) : '',
                    'lastName' => isset($_POST[CORRARFO_LAST_NAME_FIELD]) ? sanitize_text_field(wp_unslash($_POST[CORRARFO_LAST_NAME_FIELD])) : '',
                    'email' => isset($_POST[CORRARFO_EMAIL_FIELD]) ? sanitize_text_field(wp_unslash($_POST[CORRARFO_EMAIL_FIELD])) : '',
                    'password' => isset($_POST[CORRARFO_PASSWORD_FIELD]) ? sanitize_text_field(wp_unslash($_POST[CORRARFO_PASSWORD_FIELD])) : '',
                    'documentType' => isset($_POST[CORRARFO_DOCUMENT_TYPE_FIELD]) ? sanitize_text_field(wp_unslash($_POST[CORRARFO_DOCUMENT_TYPE_FIELD])) : '',
                    'documentId' => isset($_POST[CORRARFO_DOCUMENT_ID_FIELD]) ? sanitize_text_field(wp_unslash($_POST[CORRARFO_DOCUMENT_ID_FIELD])) : '',
                    'phone' => isset($_POST[CORRARFO_PHONE_FIELD]) ? Utils::cleanPhone(sanitize_text_field(wp_unslash($_POST[CORRARFO_PHONE_FIELD]))) : '',
                    'cellPhone' => isset($_POST[CORRARFO_CELLPHONE_FIELD]) ? Utils::cleanPhone(sanitize_text_field(wp_unslash($_POST[CORRARFO_CELLPHONE_FIELD]))) : '',
                    'address' => $address,
                ];

                $response = $service->createAccount($body);

                if ($response && (int) ($response['status'] ?? 0) === 200) {
                    update_option(CORRARFO_SETTINGS, maybe_serialize($settings));
                    WC_Admin_Settings::add_message(CORRARFO_SUCCESS_ON_REGISTER_TO_MI_CORREO);
                    return true;
                }

                $errorMessage = isset($response['body']['message'])
                    ? $response['body']['message']
                    : ($response['message'] ?? __('Unknown error', 'correo-argentino-for-woocommerce'));

                WC_Admin_Settings::add_error($errorMessage);
            }
        }

        // ===== Business details filled =====
        if ($form === CORRARFO_BUSINESS_DETAILS_FORM) {
            if (isset($_POST[CORRARFO_FORM_SUBMIT])) {
                update_option(CORRARFO_BUSINESS_DETAILS_FILLED, 1);
            }
        }
    } catch (\Exception $e) {
        WC_Admin_Settings::add_error('Error: ' . $e->getMessage());
    }
}

add_action('admin_enqueue_scripts', 'corrarfo_enqueue_admin_scripts');

// Keep this to ensure the request works properly
add_filter('http_request_timeout', function ($timeout) {
    return 15;
});

add_action('http_api_curl', 'corrarfo_set_curl_to_follow');

function corrarfo_varnish_safe_http_headers()
{
    if (headers_sent()) {
        return;
    }

    header('X-UA-Compatible: IE=edge');
}


add_action('send_headers', 'corrarfo_varnish_safe_http_headers');

add_filter('woocommerce_before_checkout', 'corrarfo_clear_wc_shipping_rates_cache');

function corrarfo_clear_wc_shipping_rates_cache()
{
    $packages = WC()->cart->get_shipping_packages();

    foreach ($packages as $key => $value) {
        $shipping_session = "shipping_for_package_$key";

        unset(WC()->session->$shipping_session);
    }
}

function corrarfo_enforce_update_shipping_methods()
{
    if (is_cart()) {
        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();
    }
}

add_action('woocommerce_before_cart', 'corrarfo_enforce_update_shipping_methods');

function corrarfo_custom_admin_styles()
{
    $handle = 'ca-admin-inline';
    if (!wp_style_is($handle, 'enqueued')) {
        wp_register_style($handle, false, [], defined('CORRARFO_VERSION') ? CORRARFO_VERSION : null);
        wp_enqueue_style($handle);
    }
    wp_add_inline_style($handle, '.wp-list-table .column-column_cb{width:18px;}');
}


add_action('admin_enqueue_scripts', 'corrarfo_custom_admin_styles');

function corrarfo_process_bulk_orders_handler()
{
    if (Utils::getCurrentServiceType() === CORRARFO_MI_CORREO) {
        wp_enqueue_script(
            'ca-admin-bulk-orders',
            CORRARFO_PLUGIN_URL . 'js/admin-bulk-orders.js',
            ['jquery'],
            defined('CORRARFO_VERSION') ? CORRARFO_VERSION : time(),
            true
        );

        wp_localize_script('ca-admin-bulk-orders', 'corrarfo_bulk_cfg', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'maxPerOp' => 10,
            'nonce' => wp_create_nonce('corrarfo_process_orders'),
        ]);
    }
}

add_action('admin_footer', 'corrarfo_process_bulk_orders_handler');

add_action('admin_init', function () {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $form = isset($_GET['form']) ? sanitize_key(wp_unslash($_GET['form'])) : '';

    if (
        empty(Utils::getCurrentServiceType())
        || !Utils::isConnected()
        || ($form === CORRARFO_BUSINESS_DETAILS_FORM)
    ) {
        return;
    }

    if (!Utils::canListRates()) {
        Utils::message(CORRARFO_BUSINESS_DETAILS_NOT_FILLED_MESSAGE, 'warning');
    }
});


add_filter('woocommerce_checkout_get_value', 'corrarfo_custom_edit_shipping_default_values', 10, 2);

function corrarfo_custom_edit_shipping_default_values($value, $input)
{
    $chosenShippingMethod = WC()->session->get('chosen_shipping_methods');
    $isBranch = Utils::isBranch($chosenShippingMethod[0]);
    $fields_to_modify = array(
        'shipping_city',
        'shipping_state',
        'shipping_postcode',
        'shipping_company',
        'shipping_address_1',
        'shipping_address_2'
    );

    if (!$isBranch) {
        Utils::unset_wc_chosen_branch_values();

        if (in_array($input, $fields_to_modify) && $input === 'shipping_company') {
            $value = '';
        }
        return $value;
    }

    if (in_array($input, $fields_to_modify)) {
        switch ($input) {
            case 'shipping_postcode':
                // PostalCode
                $value = WC()->session->get(CORRARFO_CHOSEN_BRANCH_ZIP_CODE);
                break;
            case 'shipping_city':
                // City
                $value = WC()->session->get(CORRARFO_CHOSEN_BRANCH_CITY);
                break;
            case 'shipping_state':
                // State
                $value = WC()->session->get(CORRARFO_CHOSEN_PROVINCE_CODE);
                break;
            case 'shipping_address_1':
                // StreetName
                $value = WC()->session->get(CORRARFO_CHOSEN_BRANCH_STREET_NAME) . ' (' . WC()->session->get(CORRARFO_CHOSEN_BRANCH) . ')';
                break;
            case 'shipping_address_2':
                // StreetNumber
                $value = WC()->session->get(CORRARFO_CHOSEN_BRANCH_STREET_NUMBER);
                break;
            case 'shipping_company':
                // AgencyName
                $value = WC()->session->get(CORRARFO_CHOSEN_BRANCH_NAME);
                break;
        }
    }

    return $value;
}

add_action('woocommerce_thankyou', 'corrarfo_flush_chosen_branch_data');

function corrarfo_flush_chosen_branch_data($order_id)
{
    if (is_wc_endpoint_url('order-received')) {
        Utils::unset_wc_chosen_branch_values();
        corrarfo_restore_wc_shipping_address($order_id);
    }
}

function corrarfo_update_wc_customer_shipping_address($customer_id)
{
    update_user_meta($customer_id, 'shipping_address_1', WC()->session->get(CORRARFO_CUSTOMER_SHIPPING_ADDRESS_1));
    update_user_meta($customer_id, 'shipping_address_2', WC()->session->get(CORRARFO_CUSTOMER_SHIPPING_ADDRESS_2));
    update_user_meta($customer_id, 'shipping_city', WC()->session->get(CORRARFO_CUSTOMER_SHIPPING_CITY));
    update_user_meta($customer_id, 'shipping_state', WC()->session->get(CORRARFO_CUSTOMER_SHIPPING_STATE));
    update_user_meta($customer_id, 'shipping_postcode', WC()->session->get(CORRARFO_CUSTOMER_SHIPPING_POSTCODE));
    update_user_meta($customer_id, 'shipping_company', WC()->session->get(CORRARFO_CUSTOMER_SHIPPING_COMPANY));

    wp_update_user(array('ID' => $customer_id));
}

function corrarfo_restore_wc_shipping_address($order_id)
{
    $order = wc_get_order($order_id);
    $customer_id = $order->get_customer_id();

    corrarfo_update_wc_customer_shipping_address($customer_id);
}

function corrarfo_set_temp_shipping_address($data)
{
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    if (!function_exists('WC') || !WC()->session) {
        return;
    }

    $user = wp_get_current_user();

    $shipping_address = array(
        'address_1' => get_user_meta($user->ID, 'shipping_address_1', true),
        'address_2' => get_user_meta($user->ID, 'shipping_address_2', true),
        'city' => get_user_meta($user->ID, 'shipping_city', true),
        'state' => get_user_meta($user->ID, 'shipping_state', true),
        'postcode' => get_user_meta($user->ID, 'shipping_postcode', true),
        'company' => get_user_meta($user->ID, 'shipping_company', true),
    );

    WC()->session->set(CORRARFO_CUSTOMER_SHIPPING_ADDRESS_1, $shipping_address['address_1']);
    WC()->session->set(CORRARFO_CUSTOMER_SHIPPING_ADDRESS_2, $shipping_address['address_2']);
    WC()->session->set(CORRARFO_CUSTOMER_SHIPPING_CITY, $shipping_address['city']);
    WC()->session->set(CORRARFO_CUSTOMER_SHIPPING_STATE, $shipping_address['state']);
    WC()->session->set(CORRARFO_CUSTOMER_SHIPPING_POSTCODE, $shipping_address['postcode']);
    WC()->session->set(CORRARFO_CUSTOMER_SHIPPING_COMPANY, $shipping_address['company']);
}


add_action('woocommerce_customer_save_address', 'corrarfo_save_wc_customer_shipping_address', 10, 2);

add_action('woocommerce_before_cart', 'corrarfo_set_temp_shipping_address');

//add_filter('woocommerce_cart_shipping_packages', 'corrarfo_restore_initial_shipping_address');

function corrarfo_initial_shipping_address($packages)
{
    $user = wp_get_current_user();
    $customer_id = (string) $user->ID;

    if (!empty($packages) && is_cart()) {
        corrarfo_update_wc_customer_shipping_address($customer_id);
    }

    return $packages;
}

add_action('woocommerce_customer_save_address', 'corrarfo_save_wc_customer_shipping_address', 10, 2);

function corrarfo_save_wc_customer_shipping_address($customer_id, $load_address)
{
    if ($load_address === 'shipping') {
        corrarfo_set_temp_shipping_address($customer_id);
    }
}

add_action('admin_enqueue_scripts', 'corrarfo_enqueue_admin_styles');

function corrarfo_enqueue_admin_styles()
{
    if (defined('WC_PLUGIN_FILE')) {
        wp_enqueue_style(
            'woocommerce-admin',
            plugins_url('assets/css/admin.css', WC_PLUGIN_FILE),
            [],
            defined('WC_VERSION') ? WC_VERSION : null
        );
    }
}

add_filter('woocommerce_get_cart_url', 'corrarfo_custom_cart_page_url');
function corrarfo_custom_cart_page_url($url)
{
    $new_cart_page_slug = 'cart-2';

    $new_cart_page_id = url_to_postid(home_url('/') . $new_cart_page_slug);

    if ($new_cart_page_id) {
        $new_cart_page_permalink = get_permalink($new_cart_page_id);

        if ($new_cart_page_permalink) {
            $url = $new_cart_page_permalink;
        }
    }

    return $url;
}

add_action('woocommerce_checkout_update_order_review', 'corrarfo_update_order_review', 10, 0);

function corrarfo_update_order_review()
{
    $chosenShippingMethod = WC()->session->get('chosen_shipping_methods');
    $isBranch = Utils::isBranch($chosenShippingMethod[0]);
    if (!$isBranch) {
        Utils::unset_wc_chosen_branch_values();
    }
}



/**
 * Check if branches are selected
 */
add_action('woocommerce_checkout_process', function () {
    $chosenShippingMethod = WC()->session->get('chosen_shipping_methods');
    $isBranch = Utils::isBranch($chosenShippingMethod[0]);
    if (
        $isBranch &&
        (
            (WC()->session->get(CORRARFO_CHOSEN_BRANCH) === null) ||
            (WC()->session->get(CORRARFO_CHOSEN_PROVINCE_CODE) === null)
        )
    ) {
        wc_add_notice(__('Correo Argentino. When selecting delivery to a branch, you must choose a province and a destination branch.', 'correo-argentino-for-woocommerce'), 'error');
        return false;
    }
    return true;
}, 20);


// Checkout: toggle shipping fields readonly/values when branch/province UI is visible
add_action('wp_enqueue_scripts', function () {
    if (!(is_cart() || is_checkout())) {
        return;
    }

    $base_url = CORRARFO_PLUGIN_URL;
    $ver = defined('CORRARFO_VERSION') ? CORRARFO_VERSION : time();

    wp_enqueue_style(
        'woocommerce-correoargentino-css',
        $base_url . 'css/woocommerce-correoargentino.css',
        [],
        $ver
    );

    // Fork Julian Juan: UX del checkout para envío a sucursal (ocultar la
    // sección "otra dirección" + checkbox, autocompletar el destinatario y
    // spinner al cargar las sucursales). Reemplaza al antiguo
    // checkout-shipping-toggle.js.
    wp_enqueue_script(
        'ca-checkout-branch-ux',
        $base_url . 'js/checkout-branch-ux.js',
        ['jquery'],
        $ver,
        true
    );

    // Claves de campo de documento (DNI/CUIT/etc.) a autocompletar en el
    // destinatario. Filtrable para temas/plugins que nombren el campo distinto.
    $document_keys = apply_filters('corrarfo_recipient_document_keys', [
        'dni',
        'documento',
        'document',
        'dni_cuit',
        'cuit',
        'cuil',
        'cuit_cuil',
        'nif',
        'vat',
        'numero_documento',
    ]);

    wp_localize_script('ca-checkout-branch-ux', 'corrarfoBranchUx', [
        'spinnerText'  => __('Cargando sucursales…', 'correo-argentino-for-woocommerce'),
        'documentKeys' => array_values((array) $document_keys),
    ]);
}, 20);


add_action('wp_enqueue_scripts', function () {
    if (is_checkout()) {
        wp_enqueue_script(
            'correoargentino-checkout-interactions',
            CORRARFO_PLUGIN_URL . 'js/checkout-interactions.js',
            ['jquery'],
            '1.0',
            true
        );
    }
});




