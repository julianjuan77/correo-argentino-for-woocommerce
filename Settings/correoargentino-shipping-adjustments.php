<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders the surcharge/discount adjustment block.
 */
function corrarfo_render_shipping_surcharge_settings()
{
    ?>
    <form method="post" action="options.php">
        <?php
        settings_fields('corrarfo_shipping_settings');
        do_settings_sections('corrarfo_shipping_settings');
        submit_button();
        ?>
    </form>
    <?php
}

add_action('admin_init', function () {
    register_setting('corrarfo_shipping_settings', 'corrarfo_shipping_enable', [
        'sanitize_callback' => function ($value) {
            return $value ? 1 : 0;
        }
    ]);

    register_setting('corrarfo_shipping_settings', 'corrarfo_shipping_type', [
        'sanitize_callback' => function ($value) {
            return in_array($value, ['surcharge', 'discount'], true) ? $value : 'surcharge';
        }
    ]);

    register_setting('corrarfo_shipping_settings', 'corrarfo_shipping_percentage', [
        'sanitize_callback' => function ($value) {
            if (!is_scalar($value)) {
                return '0.00';
            }
            $value = wp_unslash((string) $value);
            $normalized = str_replace(',', '.', trim($value));
            if (!is_numeric($normalized)) {
                return '0.00';
            }
            $float = (float) $normalized;
            // Percentage should be between 0 and 100
            $float = max(0, min(100, $float));
            return number_format($float, 2, '.', '');
        }
    ]);

    register_setting('corrarfo_shipping_settings', 'corrarfo_shipping_min_total', [
        'sanitize_callback' => function ($value) {
            if (!is_scalar($value)) {
                return '0.00';
            }
            $value = wp_unslash((string) $value);
            $normalized = str_replace(',', '.', trim($value));
            if (!is_numeric($normalized)) {
                return '0.00';
            }
            $float = (float) $normalized;
            // Minimum total should be non-negative
            $float = max(0, $float);
            return number_format($float, 2, '.', '');
        }
    ]);

    add_settings_section(
        'corrarfo_shipping_section',
        __('Shipping Surcharge / Discount Adjustments', 'correo-argentino-for-woocommerce'),
        'corrarfo_render_shipping_surcharge_settings_description',
        'corrarfo_shipping_settings'
    );

    function corrarfo_render_shipping_surcharge_settings_description()
    {
        echo '<p>' . esc_html__('Configure a percentage surcharge or discount on the base shipping cost returned by MiCorreo. The adjustment will only apply if the indicated minimum amount is reached.', 'correo-argentino-for-woocommerce') . '</p>';
    }

    add_settings_field(
        'corrarfo_shipping_enable',
        __('Enable adjustment', 'correo-argentino-for-woocommerce'),
        function () {
            $value = get_option('corrarfo_shipping_enable', false);
            echo "<input type='checkbox' id='corrarfo_shipping_enable' name='corrarfo_shipping_enable' value='1' " . checked(1, $value, false) . " />";
        },
        'corrarfo_shipping_settings',
        'corrarfo_shipping_section'
    );

    add_settings_field(
        'corrarfo_shipping_type',
        __('Adjustment Type', 'correo-argentino-for-woocommerce'),
        function () {
            $value = get_option('corrarfo_shipping_type', 'surcharge');

            echo '<select id="corrarfo_shipping_type" name="corrarfo_shipping_type">';
            echo '<option value="surcharge" ' . selected($value, 'surcharge', false) . '>' . esc_html__('Surcharge', 'correo-argentino-for-woocommerce') . '</option>';
            echo '<option value="discount" ' . selected($value, 'discount', false) . '>' . esc_html__('Discount', 'correo-argentino-for-woocommerce') . '</option>';
            echo '</select>';
        },

        'corrarfo_shipping_settings',
        'corrarfo_shipping_section'
    );

    add_settings_field(
        'corrarfo_shipping_percentage',
        __('Percentage (%)', 'correo-argentino-for-woocommerce'),
        function () {
            $value = number_format((float) get_option('corrarfo_shipping_percentage', 0), 2, '.', '');
            echo "<input type='number' id='corrarfo_shipping_percentage' step='0.10' inputmode='decimal' name='corrarfo_shipping_percentage' value='" . esc_attr($value) . "' min='0' max='100' />";
        },
        'corrarfo_shipping_settings',
        'corrarfo_shipping_section'
    );

    add_settings_field(
        'corrarfo_shipping_min_total',
        __('Minimum order amount', 'correo-argentino-for-woocommerce'),
        function () {
            $value = number_format((float) get_option('corrarfo_shipping_min_total', 0), 2, '.', '');
            echo "<input type='number' id='corrarfo_shipping_min_total' step='0.10' inputmode='decimal' name='corrarfo_shipping_min_total' value='" . esc_attr($value) . "' min='0' />";
        },
        'corrarfo_shipping_settings',
        'corrarfo_shipping_section'
    );
});

/**
 * Admin enqueue: surcharge/discount adjustments.
 * Load the JS only on the plugin settings screen.
 */
add_action('admin_enqueue_scripts', function ($hook) {
    // Read-only reading of $_GET (nonce required).
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $section = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : '';

    $match = ($page === 'corrarfo_shipping_adjustments');

    if (!$match && $page === 'wc-settings' && $tab === 'shipping' && $section === CORRARFO_PLUGIN_ID) {
        $match = true;
    }

    if (!$match && function_exists('get_current_screen')) {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'corrarfo_shipping_adjustments') !== false) {
            $match = true;
        }
    }

    if (!$match) {
        return;
    }

    wp_enqueue_script(
        'ca-admin-shipping-adjustments',
        CORRARFO_PLUGIN_URL . 'js/admin-shipping-adjustments.js',
        array('jquery'),
        (defined('CORRARFO_VERSION') ? CORRARFO_VERSION : time()),
        true
    );
}, 20);




