<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Postal Code Validation - 4 Numeric Digits Only
 */
/**
 * Validation at checkout (frontend)
 */
add_action('woocommerce_checkout_process', 'corrarfo_validate_numeric_postcode_checkout');
function corrarfo_validate_numeric_postcode_checkout()
{
    if (!isset($_SERVER['REQUEST_METHOD']) || 'POST' !== $_SERVER['REQUEST_METHOD']) {
        return;
    }

    $nonce_field = isset($_POST['woocommerce-process-checkout-nonce']) ? 'woocommerce-process-checkout-nonce' : '_wpnonce';
    $nonce = isset($_POST[$nonce_field]) ? sanitize_text_field(wp_unslash($_POST[$nonce_field])) : '';

    $is_valid_nonce = wp_verify_nonce($nonce, 'woocommerce-process_checkout') || wp_verify_nonce($nonce, 'woocommerce-process-checkout');

    if (!$is_valid_nonce) {
        wp_die(esc_html__('Unauthorized.', 'correo-argentino-for-woocommerce'));
    }

    $postcode = isset($_POST['billing_postcode']) ? sanitize_text_field(wp_unslash($_POST['billing_postcode'])) : '';

    if (!preg_match('/^\d{4}$/', $postcode)) {
        wc_add_notice(__('The postal code must contain exactly 4 numbers.', 'correo-argentino-for-woocommerce'), 'error');
    }
}

/**
 * Validation in "My Account" (customer addresses)
 */
add_action('woocommerce_customer_save_address', 'corrarfo_validate_numeric_postcode_myaccount', 10, 2);
function corrarfo_validate_numeric_postcode_myaccount($user_id, $load_address)
{
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    if (!isset($_SERVER['REQUEST_METHOD']) || 'POST' !== $_SERVER['REQUEST_METHOD']) {
        return;
    }

    $nonce_field = isset($_POST['woocommerce-edit-address-nonce']) ? 'woocommerce-edit-address-nonce' : '_wpnonce';
    $nonce = isset($_POST[$nonce_field]) ? sanitize_text_field(wp_unslash($_POST[$nonce_field])) : '';

    $is_valid_nonce = wp_verify_nonce($nonce, 'woocommerce-edit_address') || wp_verify_nonce($nonce, 'woocommerce-edit-address');

    if (!$is_valid_nonce || !current_user_can('edit_user', $user_id)) {
        wp_die(esc_html__('Unauthorized.', 'correo-argentino-for-woocommerce'));
    }

    $key = $load_address . '_postcode';
    $postcode = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';

    if (!preg_match('/^\d{4}$/', $postcode)) {
        wc_add_notice(__('The postal code must contain exactly 4 numbers.', 'correo-argentino-for-woocommerce'), 'error');
    }
}

/**
 * Limit postcode input HTML attributes (frontend forms)
 */
add_filter('woocommerce_default_address_fields', 'corrarfo_limit_postcode_input_html');
function corrarfo_limit_postcode_input_html($fields)
{
    if (isset($fields['postcode'])) {
        $fields['postcode']['maxlength'] = 4;
        $fields['postcode']['custom_attributes']['maxlength'] = 4;
        $fields['postcode']['inputmode'] = 'numeric';
        $fields['postcode']['pattern'] = '\d{4}';
    }
    return $fields;
}

/**
 * Admin: restrict postcode inputs to 4 numeric digits on user-edit/profile screens
 */
add_action('admin_enqueue_scripts', 'corrarfo_enqueue_admin_postcode_script');
function corrarfo_enqueue_admin_postcode_script($hook_suffix)
{
    if ($hook_suffix !== 'user-edit.php' && $hook_suffix !== 'profile.php') {
        return;
    }

    $script_url = CORRARFO_PLUGIN_URL . 'js/admin-postcode-limit.js';

    wp_enqueue_script(
        'ca-admin-postcode-limit',
        $script_url,
        [],
        time(),
        true
    );
}
