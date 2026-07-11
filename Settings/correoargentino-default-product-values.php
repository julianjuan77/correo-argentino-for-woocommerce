<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registration of default values for products without dimensions/weight
 */
    add_action('admin_init', function () {
    // Checkbox to enable default values
    register_setting('corrarfo_default_product_settings', 'corrarfo_default_enabled', [
        'sanitize_callback' => function ($value) {
            return $value ? 1 : 0;
        }
    ]);

    // Numeric fields with limits and sanitization
    $fields = [
        'corrarfo_default_weight' => __('Default weight (kg)', 'correo-argentino-for-woocommerce'),
        'corrarfo_default_height' => __('Default height (cm)', 'correo-argentino-for-woocommerce'),
        'corrarfo_default_width'  => __('Default width (cm)', 'correo-argentino-for-woocommerce'),
        'corrarfo_default_length' => __('Default length (cm)', 'correo-argentino-for-woocommerce'),
    ];

    foreach ($fields as $key => $label) {
        register_setting('corrarfo_default_product_settings', $key, [
            'sanitize_callback' => function ($value) use ($key) {
                if (!is_scalar($value)) {
                    return get_option($key, '');
                }

                $value = wp_unslash((string) $value);
                $normalized = str_replace(',', '.', trim($value));
                if (!is_numeric($normalized)) {
                    return get_option($key, '');
                }

                $float = (float) $normalized;

                if ($key === 'corrarfo_default_weight') {
                    if ($float <= 0 || $float > 50) {
                        add_settings_error($key, 'invalid_weight', __('The weight must be between 0.01 and 50 kg.', 'correo-argentino-for-woocommerce'));
                        return get_option($key, '');
                    }
                } else {
                    // Dimension fields (height, width, length)
                    if ($float <= 0 || $float > 200) {
                        add_settings_error($key, 'invalid_dimension', __('The dimensions must be between 0.01 and 200 cm.', 'correo-argentino-for-woocommerce'));
                        return get_option($key, '');
                    }
                }

                return number_format($float, 2, '.', '');
            }
        ]);
    }

    // Single section
    add_settings_section(
        'corrarfo_defaults_section',
        null,
        null,
        'corrarfo_default_product_settings'
    );

    // Checkbox: enable default values
    add_settings_field(
        'corrarfo_default_enabled',
        __('Enable adjustment', 'correo-argentino-for-woocommerce'),
        function () {
            $enabled = get_option('corrarfo_default_enabled', '');
            echo "<input type='checkbox' name='corrarfo_default_enabled' value='1' " . checked(1, $enabled, false) . " />";
        },
        'corrarfo_default_product_settings',
        'corrarfo_defaults_section'
    );

    foreach ($fields as $key => $label) {
        add_settings_field(
            $key,
            $label,
            function () use ($key) {
                $enabled = get_option('corrarfo_default_enabled', '');
                $value = esc_attr(get_option($key, ''));
                $max = $key === 'corrarfo_default_weight' ? '50' : '200';
                echo sprintf(
                    "<input type='number' step='0.01' min='0.01' max='%s' name='%s' value='%s' %s />",
                    esc_attr($max),
                    esc_attr($key),
                    esc_attr($value),
                    $enabled ? '' : 'disabled'
                );
                            },
            'corrarfo_default_product_settings',
            'corrarfo_defaults_section'
        );
    }
});

/**
 * Render of the "Default Values" block
 */
function corrarfo_render_default_product_values_settings() {
    ?>
    <h2><?php esc_html_e('Default values for products without data', 'correo-argentino-for-woocommerce'); ?></h2>
    <p><?php esc_html_e('The system requires weight and dimensions to process shipments. If a product does not have this data loaded, the default values configured below will be applied to ensure the continuity of rate calculation.', 'correo-argentino-for-woocommerce'); ?></p>
    <form method="post" action="options.php">
        <?php
            settings_fields('corrarfo_default_product_settings');
            do_settings_sections('corrarfo_default_product_settings');
            submit_button();
        ?>
    </form>
    <?php
}

/**
 * Enqueue admin JS to toggle default product fields
 */
add_action('admin_enqueue_scripts', function ($hook) {
    // Read-only reading of $_GET (nonce required).
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $page  = isset($_GET['page']) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    $match = ( $page === 'corrarfo_shipping_adjustments' );

    // Fallback via screen id (covers submenu variations)
    if ( ! $match && function_exists('get_current_screen') ) {
        $screen = get_current_screen();
        if ( $screen && strpos( $screen->id, 'corrarfo_shipping_adjustments' ) !== false ) {
            $match = true;
        }
    }

    if ( ! $match ) {
        return;
    }

    $base_url = CORRARFO_PLUGIN_URL;
    $ver      = defined('CORRARFO_VERSION') ? CORRARFO_VERSION : time();

    wp_enqueue_script(
        'ca-admin-default-product-values',
        $base_url . 'js/admin-default-product-values.js',
        [],
        $ver,
        true
    );
}, 20);


