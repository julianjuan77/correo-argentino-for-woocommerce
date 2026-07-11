<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders the selective status change settings.
 */
function corrarfo_render_autostatus_settings() {
    $corrarfo_enabled = get_option('corrarfo_autostatus_enabled', 'no') === 'yes';
    $corrarfo_selected_status = get_option('corrarfo_autostatus_status', '');
    $corrarfo_order_statuses = wc_get_order_statuses();

    $corrarfo_autostatus_saved = filter_input( INPUT_GET, 'corrarfo_autostatus_saved', FILTER_SANITIZE_NUMBER_INT );
    if ( $corrarfo_autostatus_saved ) {
        echo '<div class="updated notice is-dismissible"><p>' . esc_html__( 'Configuration saved.', 'correo-argentino-for-woocommerce' ) . '</p></div>';
    }
    ?>
    <div class="wrap">
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field('corrarfo_autostatus_settings_verify'); ?>
            <input type="hidden" name="action" value="corrarfo_autostatus_save" />

            <h2><?php esc_html_e('Automatic Status Change', 'correo-argentino-for-woocommerce'); ?></h2>
            <p><?php esc_html_e('Configure the automatic status change of orders when sent to MiCorreo.', 'correo-argentino-for-woocommerce'); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label
                            for="corrarfo_autostatus_enabled"><?php esc_html_e('Enable adjustment', 'correo-argentino-for-woocommerce'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" name="corrarfo_autostatus_enabled" id="corrarfo_autostatus_enabled" value="1"
                            data-toggle-fields="#corrarfo_autostatus_status" <?php checked($corrarfo_enabled); ?> />
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label
                            for="corrarfo_autostatus_status"><?php esc_html_e('New order status', 'correo-argentino-for-woocommerce'); ?></label>
                    </th>
                    <td>
                        <select name="corrarfo_autostatus_status" id="corrarfo_autostatus_status">
                            <?php
                            $corrarfo_excluded_statuses = ['wc-cancelled', 'wc-failed', 'wc-checkout-draft']; // keys to be excluded
                            foreach ($corrarfo_order_statuses as $corrarfo_status => $corrarfo_label) {
                                if (in_array($corrarfo_status, $corrarfo_excluded_statuses, true)) {
                                    continue;
                                }
                                ?>
                                <option value="<?php echo esc_attr($corrarfo_status); ?>" <?php selected($corrarfo_selected_status, $corrarfo_status); ?>>
                                    <?php echo esc_html($corrarfo_label); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * Handles the saving of the automatic status change configuration.
 */
add_action('admin_post_corrarfo_autostatus_save', 'corrarfo_save_autostatus_settings');

function corrarfo_save_autostatus_settings() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('Unauthorized.', 'correo-argentino-for-woocommerce'));
    }

    check_admin_referer('corrarfo_autostatus_settings_verify');

    $corrarfo_enabled = isset($_POST['corrarfo_autostatus_enabled']) ? 'yes' : 'no';
    update_option('corrarfo_autostatus_enabled', $corrarfo_enabled);

    if (isset($_POST['corrarfo_autostatus_status'])) {
        $corrarfo_status = sanitize_text_field(wp_unslash($_POST['corrarfo_autostatus_status']));
        update_option('corrarfo_autostatus_status', $corrarfo_status);
    }

    wp_safe_redirect(admin_url('admin.php?page=corrarfo_behavior_settings&corrarfo_autostatus_saved=1'));
    exit;
}