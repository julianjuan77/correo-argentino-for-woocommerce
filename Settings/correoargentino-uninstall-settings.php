<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action('admin_init', function () {
    register_setting('correoargentino_settings', 'corrarfo_remove_on_uninstall', [
        'sanitize_callback' => function ($value) {
            return $value === 'yes' ? 'yes' : 'no';
        }
    ]);
});

function corrarfo_render_uninstall_settings() {
    $value = get_option('corrarfo_remove_on_uninstall', 'no');
    ?>
    <h2><?php esc_html_e('Complete Plugin Uninstallation', 'correo-argentino-for-woocommerce'); ?></h2>
    <p><?php esc_html_e('Enable this if you want to delete all plugin data upon uninstallation. This option is useful if you plan to remove the plugin and do not need to retain any settings or data.', 'correo-argentino-for-woocommerce'); ?></p>

    <table class="form-table">
        <tr>
            <th scope="row"><?php esc_html_e('Enable adjustment', 'correo-argentino-for-woocommerce'); ?></th>
            <td>
                <label>
                    <input type="checkbox" id="corrarfo_remove_on_uninstall" name="corrarfo_remove_on_uninstall" value="yes" <?php checked('yes', $value); ?> />
                    <?php esc_html_e('Enable this option to delete all plugin data and settings when the plugin is removed.', 'correo-argentino-for-woocommerce'); ?>
                </label>

                <div id="corrarfo_uninstall_warning" style="margin-top:10px; display: <?php echo ($value === 'yes') ? 'block' : 'none'; ?>; color:#b32d2e;">
                    ⚠️ <?php esc_html_e('You have selected to delete all plugin data. This action is irreversible upon uninstallation.', 'correo-argentino-for-woocommerce'); ?>
                </div>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Enqueue admin JS for the uninstall settings screen.
 * Adjust the $page slug below if your screen uses a different one.
 */
add_action('admin_enqueue_scripts', function ($hook) {
    // Read-only reading of $_GET (nonce required).
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $page  = isset($_GET['page']) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    $match = ( $page === 'corrarfo_uninstall_settings' );

    // Fallback via screen id (covers submenu variations)
    if ( ! $match && function_exists('get_current_screen') ) {
        $screen = get_current_screen();
        if ( $screen && strpos( $screen->id, 'corrarfo_uninstall' ) !== false ) {
            $match = true;
        }
    }

    if ( ! $match ) {
        return;
    }

    // Build URL from plugin root
    $base_url = CORRARFO_PLUGIN_URL;
    $ver      = defined('CORRARFO_VERSION') ? CORRARFO_VERSION : time();

    wp_enqueue_script(
        'ca-admin-uninstall-settings',
        $base_url . 'js/admin-uninstall-settings.js',
        [],
        $ver,
        true
    );
}, 20);


