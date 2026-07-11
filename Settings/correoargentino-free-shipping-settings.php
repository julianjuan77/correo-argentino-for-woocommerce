<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the Free Shipping settings section for MiCorreo
 */
function corrarfo_render_free_shipping_settings() {
    $enabled = get_option('corrarfo_free_shipping_enabled', 'no');
    $amount  = get_option('corrarfo_free_shipping_amount', '');

    $free_shipping_saved = filter_input( INPUT_GET, 'free_shipping_saved', FILTER_SANITIZE_NUMBER_INT );
    if ( $free_shipping_saved ) {
        echo '<div class="updated notice is-dismissible"><p>' . esc_html__( 'Configuration saved.', 'correo-argentino-for-woocommerce' ) . '</p></div>';
    }
    ?>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field('corrarfo_free_shipping_verify'); ?>
        <input type="hidden" name="action" value="corrarfo_free_shipping_save" />

        <h2><?php esc_html_e('Free Shipping Settings', 'correo-argentino-for-woocommerce'); ?></h2>
        <p><?php esc_html_e('Configure free shipping for MiCorreo. If the total order amount reaches or exceeds the defined value, the customer will not be charged for shipping.', 'correo-argentino-for-woocommerce'); ?></p>

        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Enable adjustment', 'correo-argentino-for-woocommerce'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" id="corrarfo_free_shipping_enabled" name="corrarfo_free_shipping_enabled" value="yes" <?php checked($enabled, 'yes'); ?> />
                        <?php esc_html_e('Activate free shipping threshold', 'correo-argentino-for-woocommerce'); ?>
                    </label>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Minimum order amount', 'correo-argentino-for-woocommerce'); ?></th>
                <td>
                    <input type="text" id="corrarfo_free_shipping_amount" name="corrarfo_free_shipping_amount" value="<?php echo esc_attr($amount); ?>" />
                    <p class="description"><?php esc_html_e('Enter the minimum amount (e.g. 3500.50)', 'correo-argentino-for-woocommerce'); ?></p>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
    <?php
}

/**
 * Handles the saving of the free shipping configuration.
 */
add_action('admin_post_corrarfo_free_shipping_save', 'corrarfo_save_free_shipping_settings');

function corrarfo_save_free_shipping_settings() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('Unauthorized.', 'correo-argentino-for-woocommerce'));
    }

    check_admin_referer('corrarfo_free_shipping_verify');

    $new_enabled    = isset($_POST['corrarfo_free_shipping_enabled']) ? 'yes' : 'no';
    $new_amount_raw = isset($_POST['corrarfo_free_shipping_amount']) ? sanitize_text_field(wp_unslash($_POST['corrarfo_free_shipping_amount'])) : '';
    $new_amount     = floatval(str_replace(',', '.', $new_amount_raw));

    if ( $new_amount >= 0 ) {
        update_option('corrarfo_free_shipping_enabled', $new_enabled);
        update_option('corrarfo_free_shipping_amount', $new_amount);
    }

    wp_safe_redirect(admin_url('admin.php?page=corrarfo_shipping_adjustments&free_shipping_saved=1'));
    exit;
}

/**
 * Enqueue admin JS for the free shipping settings screen.
 */
add_action('admin_enqueue_scripts', function ($hook) {
    // Read-only reading of $_GET (nonce required).
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $page = isset($_GET['page']) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

    $match = ( $page === 'corrarfo_shipping_adjustments' );

    if ( ! $match && function_exists('get_current_screen') ) {
        $screen = get_current_screen();
        if ( $screen && ( strpos( $screen->id, 'corrarfo_shipping_adjustments' ) !== false || strpos( $screen->id, 'corrarfo_shipping' ) !== false ) ) {
            $match = true;
        }
    }

    if ( ! $match ) {
        return;
    }

    $base_url = CORRARFO_PLUGIN_URL;
    $ver      = defined('CORRARFO_VERSION') ? CORRARFO_VERSION : time();

    wp_enqueue_script(
        'ca-admin-free-shipping',
        $base_url . 'js/admin-free-shipping.js',
        [],
        $ver,
        true
    );
}, 20);
