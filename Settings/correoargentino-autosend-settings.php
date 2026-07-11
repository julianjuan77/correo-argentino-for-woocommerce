<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Renders the settings section for automatic order sending to MiCorreo.
 */
function corrarfo_render_autosend_settings() {
    $autosend_saved = filter_input( INPUT_GET, 'autosend_saved', FILTER_SANITIZE_NUMBER_INT );
    if ( $autosend_saved ) {
        echo '<div class="updated"><p>' . esc_html__( 'Configuration saved.', 'correo-argentino-for-woocommerce' ) . '</p></div>';
    }

    $enabled        = get_option( 'corrarfo_auto_send_enabled', '' );
    $trigger_status = get_option( 'corrarfo_auto_send_trigger_status', '' );

    $statuses = wc_get_order_statuses();
    ?>
    <h2><?php echo esc_html__( 'Automatic Order Sending', 'correo-argentino-for-woocommerce' ); ?></h2>
    <p><?php echo esc_html__( 'This functionality allows orders to be automatically sent to MiCorreo when they change to a specific status, streamlining shipment management.', 'correo-argentino-for-woocommerce' ); ?></p>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'corrarfo_autosend_settings_verify' ); ?>
        <input type="hidden" name="action" value="corrarfo_autosend_settings_save" />

        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php echo esc_html__( 'Enable adjustment', 'correo-argentino-for-woocommerce' ); ?></th>
                <td>
                    <input
                        type="checkbox"
                        name="corrarfo_auto_send_enabled"
                        id="corrarfo_auto_send_enabled"
                        value="1"
                        <?php checked( $enabled, '1' ); ?>
                        data-toggle-fields="#corrarfo_auto_send_trigger_status"
                    />
                </td>
            </tr>

            <tr valign="top">
                <th scope="row"><?php echo esc_html__( 'Activation Status', 'correo-argentino-for-woocommerce' ); ?></th>
                <td>
                    <select name="corrarfo_auto_send_trigger_status" id="corrarfo_auto_send_trigger_status">
                        <?php foreach ( $statuses as $status_key => $status_label ) :
                            if ( in_array( $status_key, [ 'wc-cancelled', 'wc-failed', 'wc-checkout-draft' ], true ) ) {
                                continue;
                            }
                            $status_value = str_replace( 'wc-', '', $status_key );
                            ?>
                            <option value="<?php echo esc_attr( $status_value ); ?>" <?php selected( $trigger_status, $status_value ); ?>>
                                <?php echo esc_html( $status_label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php echo esc_html__( 'When the order changes to this status, it will be automatically sent to MiCorreo.', 'correo-argentino-for-woocommerce' ); ?></p>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
    <?php
}
