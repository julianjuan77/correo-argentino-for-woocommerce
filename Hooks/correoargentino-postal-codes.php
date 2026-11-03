<?php

if (!defined('ABSPATH')) {
    exit;
}

use Corrarfo\Lib\PostalCodes;
use Corrarfo\Lib\PostalCodesRefresher;

/**
 * WP-Cron mensual de refresh del dataset de localidades/CP (ADR-001).
 */
add_filter('cron_schedules', 'corrarfo_add_monthly_cron_schedule');
function corrarfo_add_monthly_cron_schedule($schedules)
{
    if (!isset($schedules['monthly'])) {
        $schedules['monthly'] = [
            'interval' => 30 * DAY_IN_SECONDS,
            'display' => __('Una vez al mes', 'correo-argentino-for-woocommerce'),
        ];
    }
    return $schedules;
}

add_action('init', 'corrarfo_schedule_postal_codes_refresh');
function corrarfo_schedule_postal_codes_refresh()
{
    if (!wp_next_scheduled(CORRARFO_POSTAL_CODES_REFRESH_HOOK)) {
        wp_schedule_event(time(), 'monthly', CORRARFO_POSTAL_CODES_REFRESH_HOOK);
    }
}

add_action(CORRARFO_POSTAL_CODES_REFRESH_HOOK, [PostalCodesRefresher::class, 'refresh']);

/**
 * El select dependiente reemplaza el <input> de Ciudad/CP manipulando el DOM a mano
 * (jQuery), que es el mecanismo del checkout clásico (shortcode `[woocommerce_checkout]`).
 * En el checkout de bloques (`wp:woocommerce/checkout`) los campos son React-controlados:
 * un swap de DOM así queda visualmente puesto pero no se sincroniza con el store interno
 * de WooCommerce (`wc/store/cart`), así que el pedido se mandaría con Ciudad/CP vacíos -se
 * comprobó en vivo contra el checkout de bloques-. Hasta que se implemente la integración
 * nativa con ese store, en bloques se degrada al campo de texto libre actual (sin romper
 * nada, pero sin el select).
 */
function corrarfo_checkout_uses_block_checkout(): bool
{
    if (!function_exists('wc_get_page_id') || !function_exists('has_block')) {
        return false;
    }

    $checkoutPageId = wc_get_page_id('checkout');

    return $checkoutPageId && has_block('woocommerce/checkout', $checkoutPageId);
}

/**
 * Checkout: select de Localidad dependiente de Provincia, con el CP derivado. Sólo en el
 * checkout clásico (ver corrarfo_checkout_uses_block_checkout()).
 */
add_action('wp_enqueue_scripts', 'corrarfo_enqueue_postal_code_select');
function corrarfo_enqueue_postal_code_select()
{
    if (!function_exists('is_checkout') || !is_checkout()) {
        return;
    }

    if (corrarfo_checkout_uses_block_checkout()) {
        return;
    }

    wp_enqueue_script(
        'corrarfo-postal-code-select',
        CORRARFO_PLUGIN_URL . 'js/postal-code-select.js',
        ['jquery', 'select2-js'],
        filemtime(CORRARFO_PLUGIN_DIR . 'js/postal-code-select.js'),
        true
    );

    wp_localize_script('corrarfo-postal-code-select', 'corrarfo_postal_codes_cfg', [
        'ajaxUrl' => admin_url('admin-ajax.php', 'relative'),
        'nonce' => wp_create_nonce(CORRARFO_POSTAL_CODES_NONCE_ACTION),
    ]);
}

add_action('wp_ajax_corrarfo_get_localidades', 'corrarfo_get_localidades');
add_action('wp_ajax_nopriv_corrarfo_get_localidades', 'corrarfo_get_localidades');
function corrarfo_get_localidades()
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), CORRARFO_POSTAL_CODES_NONCE_ACTION)) {
        wp_send_json_error(['message' => 'Invalid or expired security token'], 403);
    }

    $province = isset($_POST['province']) ? strtoupper(sanitize_text_field(wp_unslash($_POST['province']))) : '';

    if ($province === '' || !preg_match('/^[A-Z]$/', $province)) {
        wp_send_json_error(['message' => 'Invalid province'], 400);
    }

    wp_send_json_success(['localidades' => PostalCodes::getLocalidades($province)]);
}
