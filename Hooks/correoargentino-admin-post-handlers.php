<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the saving of the automatic sending configuration form.
 */
add_action('admin_post_corrarfo_autosend_settings_save', 'corrarfo_handle_autosend_settings_form');

function corrarfo_handle_autosend_settings_form()
{
    if (isset($_POST['_wpnonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
    } else {
        $nonce = '';
    }

    if (!current_user_can('manage_woocommerce') || !wp_verify_nonce($nonce, 'corrarfo_autosend_settings_verify')) {
        wp_die(esc_html__('Unauthorized.', 'correo-argentino-for-woocommerce'));
    }

    $enabled = isset($_POST['corrarfo_auto_send_enabled']) ? '1' : '';
    update_option('corrarfo_auto_send_enabled', $enabled);

    if (isset($_POST['corrarfo_auto_send_trigger_status'])) {
        $status = sanitize_text_field(wp_unslash($_POST['corrarfo_auto_send_trigger_status']));
        update_option('corrarfo_auto_send_trigger_status', $status);
    }

    wp_safe_redirect(admin_url('admin.php?page=corrarfo_behavior_settings&autosend_saved=1'));
    exit;
}



