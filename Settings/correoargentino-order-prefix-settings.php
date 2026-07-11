<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function corrarfo_render_order_prefix_settings() {
    $enabled = get_option('corrarfo_prefix_enabled', 'no');
    $prefix  = get_option('corrarfo_prefix_value', '');

    $prefix_saved = filter_input( INPUT_GET, 'prefix_saved', FILTER_SANITIZE_NUMBER_INT );
    if ( $prefix_saved ) {
        echo '<div class="updated notice is-dismissible"><p>' . esc_html__( 'Configuration saved.', 'correo-argentino-for-woocommerce' ) . '</p></div>';
    }
    ?>
    <h2><?php esc_html_e( 'Order Prefix Settings', 'correo-argentino-for-woocommerce' ); ?></h2>
    <p><?php esc_html_e( 'Add a prefix to the order number to facilitate tracking or distinguish orders by store or channel.', 'correo-argentino-for-woocommerce' ); ?></p>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'corrarfo_order_prefix_verify' ); ?>
        <input type="hidden" name="action" value="corrarfo_order_prefix_save" />

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Enable adjustment', 'correo-argentino-for-woocommerce' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="corrarfo_prefix_enabled" value="yes" <?php checked( $enabled, 'yes' ); ?> />
                        <?php esc_html_e( 'Use a prefix for order numbers', 'correo-argentino-for-woocommerce' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Prefix', 'correo-argentino-for-woocommerce' ); ?></th>
                <td>
                    <input
                        type="text"
                        name="corrarfo_prefix_value"
                        value="<?php echo esc_attr( $prefix ); ?>"
                        maxlength="5"
                        pattern="[A-Za-z0-9]{1,5}"
                        style="text-transform: uppercase;"
                        <?php echo ( $enabled === 'yes' ) ? '' : 'disabled'; ?>
                    />
                    <p class="description">
                        <?php esc_html_e( 'For the prefix field letters and numbers are allowed (maximum 5). Example: TIEN1', 'correo-argentino-for-woocommerce' ); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button( __( 'Save Changes', 'correo-argentino-for-woocommerce' ) ); ?>
    </form>
    <?php
}

add_action('admin_post_corrarfo_order_prefix_save', 'corrarfo_save_order_prefix_settings');

function corrarfo_save_order_prefix_settings() {
    if (
        ! current_user_can( 'manage_woocommerce' ) ||
        ! isset( $_POST['_wpnonce'] ) ||
        ! wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
            'corrarfo_order_prefix_verify'
        )
    ) {
        wp_die( esc_html__( 'Unauthorized or session expired.', 'correo-argentino-for-woocommerce' ) );
    }

    $enabled = isset($_POST['corrarfo_prefix_enabled']) ? 'yes' : 'no';
    update_option('corrarfo_prefix_enabled', $enabled);

    if ( isset($_POST['corrarfo_prefix_value']) ) {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $raw_input       = wp_unslash( $_POST['corrarfo_prefix_value'] );
        $sanitized_input = sanitize_text_field( $raw_input );
        $rawPrefix       = strtoupper( trim( $sanitized_input ) );

        if ( preg_match( '/^[A-Z0-9]{1,5}$/', $rawPrefix ) ) {
            update_option( 'corrarfo_prefix_value', $rawPrefix );
        } else {
            update_option( 'corrarfo_prefix_value', '' );
        }
    }

    wp_safe_redirect( admin_url( 'admin.php?page=corrarfo_behavior_settings&prefix_saved=1' ) );
    exit;
}

/**
 * Enqueue admin JS for this screen (enables/disables the prefix input and forces uppercase).
 */
add_action('admin_enqueue_scripts', function ($hook) {
    // Read-only reading of $_GET (nonce required).
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $page = isset($_GET['page']) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    if ( $page !== 'corrarfo_behavior_settings' ) {
        return;
    }

    $base_url = CORRARFO_PLUGIN_URL;
    $ver      = defined('CORRARFO_VERSION') ? CORRARFO_VERSION : time();

    wp_enqueue_script(
        'ca-admin-order-prefix-settings',
        $base_url . 'js/admin-order-prefix-settings.js',
        [],
        $ver,
        true
    );
}, 20);

