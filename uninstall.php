<?php
/**
 * Complete uninstallation of the Correo Argentino plugin.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

if (!defined('CORRARFO_PLUGIN_DIR')) {
	define('CORRARFO_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

require_once CORRARFO_PLUGIN_DIR . 'Lib/Constants.php';

$corrarfo_remove = get_option(CORRARFO_OPTION_REMOVE_ON_UNINSTALL);
if ($corrarfo_remove !== 'yes') {
	return;
}

/**
 * 1) Eliminar opciones registradas del plugin usando la API.
 */
$corrarfo_options = array(
	CORRARFO_OPTION_REMOVE_ON_UNINSTALL,
	CORRARFO_OPTION_AUTOSEND_ENABLED,
	CORRARFO_OPTION_AUTOSEND_TRIGGER,
	CORRARFO_OPTION_AUTOSTATUS_ENABLED,
	CORRARFO_OPTION_AUTOSTATUS_STATUS,
	CORRARFO_OPTION_DEFAULT_ENABLED,
	CORRARFO_OPTION_DEFAULT_HEIGHT,
	CORRARFO_OPTION_DEFAULT_LENGTH,
	CORRARFO_OPTION_DEFAULT_WIDTH,
	CORRARFO_OPTION_DEFAULT_WEIGHT,
	CORRARFO_OPTION_FREE_SHIPPING_ENABLED,
	CORRARFO_OPTION_FREE_SHIPPING_AMOUNT,
	CORRARFO_OPTION_PREFIX_ENABLED,
	CORRARFO_OPTION_PREFIX_VALUE,
	CORRARFO_OPTION_SHIPPING_ENABLE,
	CORRARFO_OPTION_SHIPPING_MIN_TOTAL,
	CORRARFO_OPTION_SHIPPING_PERCENTAGE,
	CORRARFO_OPTION_SHIPPING_TYPE,
);

foreach ($corrarfo_options as $corrarfo_option) {
	delete_option($corrarfo_option);
}

/**
 * 2) Pattern-specific cleaning (no API for LIKE):
 *    - options: woocommerce_correoargentino_shipping_method_*
 *    - postmeta: _correoargentino_*
 *
 *    $wpdb->prepare + esc_like is used and it is documented for PHPCS that this
 *    It occurs in the context of uninstallation and there is no alternative in the API.
 */
global $wpdb;

// Delete shipping method options from the plugin.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM `{$wpdb->options}` WHERE option_name LIKE %s",
		$wpdb->esc_like('woocommerce_correoargentino_shipping_method_') . '%'
	)
);

// Delete order metadata from the plugin (legacy post storage).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM `{$wpdb->postmeta}` WHERE meta_key LIKE %s",
		$wpdb->esc_like('_correoargentino_') . '%'
	)
);

// Delete order metadata from the plugin (HPOS storage, when enabled).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$corrarfo_hpos_table_exists = (bool) $wpdb->get_var(
	$wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'wc_orders_meta'))
);

if ($corrarfo_hpos_table_exists) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			'DELETE FROM %i WHERE meta_key LIKE %s',
			$wpdb->prefix . 'wc_orders_meta',
			$wpdb->esc_like('_correoargentino_') . '%'
		)
	);
}

// 3) Plugin transients/caches.
delete_transient('corrarfo_cached_services');
delete_transient('corrarfo_last_branch_fetch');

