<?php
namespace Corrarfo\Tables;

use Corrarfo\Lib\Utils;
use Corrarfo\Service\ServiceFactory;
use WC_Order_Query;
use WP_List_Table;

if (!defined('ABSPATH')) {
    exit;
}

require_once CORRARFO_PLUGIN_DIR . 'Helper/correoargentino-order-actions-helper.php';

class OrderTable extends \WP_List_Table
{
    private $corrarfo_orders;

    /**
     *
     */
    function __construct()
    {
        parent::__construct(array(
            'singular' => 'Item',
            'plural' => 'Items',
            'ajax' => false,
        ));

        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';
        $id = isset($_GET['id']) ? absint(wp_unslash($_GET['id'])) : 0;

        if ($action) {
            if (!current_user_can('manage_woocommerce')) {
                wp_die();
            }

            $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
            $nonce_action = 'corrarfo_orders_action';

            if ($action === 'cancel') {
                $nonce_action = 'corrarfo_cancel_' . $id;
            } elseif ($action === 'restore_hidden_orders') {
                $nonce_action = 'restore_hidden_orders';
            }

            if (!$nonce || !wp_verify_nonce($nonce, $nonce_action)) {
                wp_die();
            }

            $actions = [
                'label' => 'label',
                'cancel' => 'cancel',
                'store' => 'store',
                'delete' => 'delete',
            ];

            $method = $actions[$action] ?? '';
            if ($method && method_exists($this, $method)) {
                $this->$method($id);
            }
        }

        $this->render_page_title();
    }

    public function label($order_id)
    {
        $order = wc_get_order(absint($order_id));
        $service = (new ServiceFactory())->get();
        $response = $service->label($order->get_meta(CORRARFO_TRACKING_NUMBER));

        if (!empty($response[0]['fileBase64'])) {
            $filename = !empty($response[0]['filename']) ? sanitize_file_name($response[0]['filename']) : 'etiqueta.pdf';
            $pdf = base64_decode($response[0]['fileBase64'], true);

            while (ob_get_level()) {
                ob_end_clean();
            }
            nocache_headers();
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($pdf));
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $pdf;
            exit;
        }
    }

    public function unknown()
    {
        Utils::message('Acción desconocida', 'warning', true, false);
    }

    /**
     *
     */
    // Tables/correoargentino-order-table.php
    public function prepare_items()
    {
        global $wpdb;

        $posts_table = esc_sql($wpdb->posts);
        $postmeta_table = esc_sql($wpdb->postmeta);
        $order_items_table = esc_sql($wpdb->prefix . 'woocommerce_order_items');
        $order_itemmeta_table = esc_sql($wpdb->prefix . 'woocommerce_order_itemmeta');

        $columns = $this->get_columns();
        $hidden = array();

        $this->corrarfo_orders = $this->get_corrarfo_orders();
        $this->_column_headers = array($columns, $hidden, $this->get_sortable_columns());

        $per_page = 99999;
        $current_page = $this->get_pagenum();
        $offset = (($current_page - 1) * $per_page);

        $post_type_like = $wpdb->esc_like('shop_order') . '%';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $show_hidden_flag = (isset($_GET['show_hidden']) && '1' === sanitize_text_field(wp_unslash($_GET['show_hidden']))) ? 1 : 0;

        $current_service_type = Utils::getCurrentServiceType();
        $meta_key_service_type = CORRARFO_CHOSEN_SERVICE_TYPE;
        $hidden_meta_key = '_corrarfo_oculto';
        $hidden_meta_val = '1';

        $current_service_type = Utils::getCurrentServiceType();
        $service_values = array($current_service_type);
        /**
         * Accept current and legacy values ​​of the service (PAQ.AR):
         * - actual: 'paqar'
         * - legacy: 'paq.ar'
         */
        if ($current_service_type === 'paqar') {
            $service_values[] = 'paq.ar';
        } elseif ($current_service_type === 'paq.ar') {
            $service_values[] = 'paqar';
        }

        // HPOS Detection
        global $wpdb;
        $hpos_active = Utils::isHPOSActive();
        $meta_table_raw = $hpos_active ? ($wpdb->prefix . 'wc_orders_meta') : $wpdb->postmeta;
        $id_col_meta = $hpos_active ? 'order_id' : 'post_id';

        $in_placeholders = implode(',', array_fill(0, count($service_values), '%s'));

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        $sql_keys = "
            SELECT _order.*
            FROM %i AS _order
            WHERE _order.post_type LIKE %s
            AND _order.post_status != %s

            /* Aceptar service_type tanto en order_itemmeta como en postmeta,
                y soportar valores actuales y legacy (paqar / paq.ar) */
            AND (
                    EXISTS (
                        SELECT 1
                        FROM %i AS _item
                        WHERE _item.order_item_type = 'shipping'
                        AND EXISTS (
                                SELECT 1
                                FROM %i AS _meta
                                WHERE _meta.meta_key = %s
                                AND _meta.meta_value IN ({$in_placeholders})
                                AND _meta.order_item_id = _item.order_item_id
                        )
                        AND _item.order_id = _order.ID
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM %i AS pm2
                        WHERE pm2.%i = _order.ID
                        AND pm2.meta_key = %s
                        AND pm2.meta_value IN ({$in_placeholders})
                    )
            )

            /* ocultos */
            AND ( %d = 1 OR NOT EXISTS (
                        SELECT 1
                        FROM %i AS pm
                        WHERE pm.%i = _order.ID
                        AND pm.meta_key = %s
                ) )

            ORDER BY _order.ID DESC
            LIMIT %d, %d
        ";

        // Build parameters for the markers
        $final_params = [
            $wpdb->posts,
            $post_type_like,
            'trash',
            $wpdb->prefix . 'woocommerce_order_items',
            $wpdb->prefix . 'woocommerce_order_itemmeta',
            $meta_key_service_type,
        ];
        $final_params = array_merge($final_params, $service_values);
        $final_params[] = $meta_table_raw;
        $final_params[] = $id_col_meta;
        $final_params[] = $meta_key_service_type;
        $final_params = array_merge($final_params, $service_values);
        $final_params[] = $show_hidden_flag;
        $final_params[] = $meta_table_raw;
        $final_params[] = $id_col_meta;
        $final_params[] = $hidden_meta_key;
        $final_params[] = absint($offset);
        $final_params[] = absint($per_page);

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $keys = $wpdb->get_results($wpdb->prepare($sql_keys, $final_params), ARRAY_A);
        // phpcs:enable

        if (empty($keys)) {
            $this->items = array();
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $hpos_active = Utils::isHPOSActive();
        $meta_table_raw = $hpos_active ? ($wpdb->prefix . 'wc_orders_meta') : $wpdb->postmeta;
        $address_table_raw = $hpos_active ? ($wpdb->prefix . 'wc_order_addresses') : $wpdb->postmeta;
        $meta_table_safe = esc_sql($meta_table_raw);
        $address_table_safe = esc_sql($address_table_raw);

        $id_col_meta = $hpos_active ? 'order_id' : 'post_id';
        $id_col_addr = $hpos_active ? 'order_id' : 'post_id';

        $ids = array_map('absint', array_column($keys, 'ID'));
        if (empty($ids)) {
            $this->items = array();
            return;
        }
        $in_ids_placeholders = implode(',', array_fill(0, count($ids), '%d'));

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        $customers = $wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE %i IN ($in_ids_placeholders)", array_merge([$meta_table_raw, $id_col_meta], $ids)), ARRAY_A);

        $addresses = $wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE %i IN ($in_ids_placeholders)", array_merge([$address_table_raw, $id_col_addr], $ids)), ARRAY_A);
        // phpcs:enable

        $order_items_table = $wpdb->prefix . 'woocommerce_order_items';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        $wooOrderItems = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT order_id, order_item_type, order_item_name
                FROM %i
                WHERE order_id IN ($in_ids_placeholders)
                AND order_item_type = %s",
                array_merge([$order_items_table], $ids, array('shipping'))
            ),
            OBJECT_K
        );
        // phpcs:enable

        $items = array_map(
            function ($item) use ($wooOrderItems, $customers, $addresses, $hpos_active) {
                $item_id = (int) $item['ID'];
                $item['woo'] = isset($wooOrderItems[$item_id]) ? $wooOrderItems[$item_id] : null;

                $meta = array();

                foreach ($customers as $customer) {
                    $matches_order = false;

                    if ($hpos_active && (int) $customer['order_id'] === $item_id) {
                        $matches_order = true;
                    } elseif (!$hpos_active && (int) $customer['post_id'] === $item_id) {
                        $matches_order = true;
                    }

                    if ($matches_order) {
                        $meta_key = isset($customer['meta_key']) ? $customer['meta_key'] : '';
                        if ($meta_key !== '') {
                            $meta[$meta_key] = $customer['meta_value'];
                        }
                    }
                }

                if ($hpos_active) {
                    foreach ($addresses as $address) {
                        if ((int) $address['order_id'] === $item_id) {
                            $meta['_billing_first_name'] = isset($address['first_name']) ? $address['first_name'] : '';
                            $meta['_billing_last_name'] = isset($address['last_name']) ? $address['last_name'] : '';
                        }
                    }
                }

                $item['meta'] = $meta;
                return $item;
            },
            $keys
        );

        $this->items = $items;

        if (!empty($this->items)) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $orderby = isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $order_key = isset($_GET['order']) ? sanitize_key(wp_unslash($_GET['order'])) : '';
            $order = ($order_key === 'desc') ? 'desc' : 'asc';

            usort(
                $this->items,
                function ($a, $b) use ($orderby, $order) {
                    $valA = $valB = null;

                    switch ($orderby) {
                        case 'ID':
                            $valA = (int) $a['ID'];
                            $valB = (int) $b['ID'];
                            break;

                        case 'post_date':
                            $valA = strtotime($a['post_date']);
                            $valB = strtotime($b['post_date']);
                            break;

                        case 'shipping_type':
                            $valA = isset($a['woo']->order_item_name) ? $a['woo']->order_item_name : '';
                            $valB = isset($b['woo']->order_item_name) ? $b['woo']->order_item_name : '';
                            break;

                        case 'dimensions':
                            $order_obj = wc_get_order((int) $a['ID']);
                            $valA = $order_obj ? $order_obj->get_meta('_corrarfo_dimensions', true) : '';
                            $order_obj = wc_get_order((int) $b['ID']);
                            $valB = $order_obj ? $order_obj->get_meta('_corrarfo_dimensions', true) : '';
                            break;

                        case 'weight':
                            $order_obj = wc_get_order((int) $a['ID']);
                            $valA = $order_obj ? (float) $order_obj->get_meta('_corrarfo_weight', true) : 0;
                            $order_obj = wc_get_order((int) $b['ID']);
                            $valB = $order_obj ? (float) $order_obj->get_meta('_corrarfo_weight', true) : 0;
                            break;

                        case 'status':
                            $orderA = wc_get_order((int) $a['ID']);
                            $orderB = wc_get_order((int) $b['ID']);
                            $valA = $orderA ? $orderA->get_status() : '';
                            $valB = $orderB ? $orderB->get_status() : '';
                            break;

                        case 'agency_name':
                            $valA = isset($a['meta'][CORRARFO_CHOSEN_BRANCH_NAME]) ? $a['meta'][CORRARFO_CHOSEN_BRANCH_NAME] : '';
                            $valB = isset($b['meta'][CORRARFO_CHOSEN_BRANCH_NAME]) ? $b['meta'][CORRARFO_CHOSEN_BRANCH_NAME] : '';
                            break;

                        default:
                            return 0;
                    }

                    $result = (is_numeric($valA) && is_numeric($valB))
                        ? ($valA <=> $valB)
                        : strcmp((string) $valA, (string) $valB);

                    return ('asc' === $order) ? $result : -$result;
                }
            );
        }
    }

    /**
     *
     */
    public function get_columns()
    {
        return array(
            'column_cb' => Utils::getCurrentServiceType() !== CORRARFO_MI_CORREO ? '' : '<input type="checkbox" name="wc-correoargentino-all-orders" class="wc-correoargentino-all-orders" value="all" />',
            'ID' => CORRARFO_ORDER,
            'corrarfo_actions' => '',
            'tracking_number' => $this->get_reference_column_label(),
            'shipping_type' => CORRARFO_SHIPPING_TYPE,
            'dimensions' => __('Dimensions', 'correo-argentino-for-woocommerce'),
            'weight' => __('Weight', 'correo-argentino-for-woocommerce'),
            'status' => CORRARFO_STATUS,
            'agency_name' => CORRARFO_SHIPPING_AGENCY,
            'post_date' => CORRARFO_DATE,
        );
    }

    // Tables/correoargentino-order-table.php
    private function get_corrarfo_orders()
    {
        global $wpdb;

        $post_type_like = $wpdb->esc_like('shop_order') . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                "
            SELECT _order.*
            FROM %i AS _order
            WHERE _order.post_type LIKE %s
            AND _order.post_status != %s
            AND EXISTS (
                    SELECT _item.order_id
                    FROM %i AS _item
                    WHERE _item.order_item_type = 'shipping'
                    AND EXISTS (
                            SELECT 1
                            FROM %i AS _meta
                            WHERE _meta.meta_key   = %s
                            AND _meta.meta_value = %s
                            AND _meta.order_item_id = _item.order_item_id
                    )
                    AND _item.order_id = _order.ID
                )
            ORDER BY _order.ID DESC
            ",
                $wpdb->posts,
                $post_type_like,
                'trash',
                $wpdb->prefix . 'woocommerce_order_items',
                $wpdb->prefix . 'woocommerce_order_itemmeta',
                'method_id',
                CORRARFO_PLUGIN_ID
            ),
            ARRAY_A
        );
    }

    /**
     * Store in Correo Argentino
     */
    public function store($order_id)
    {
        try {
            $service = (new ServiceFactory())->get();
            $response = $service->registerOrder($order_id);
        } catch (\RuntimeException $e) {
            Utils::message(
                sprintf(
                    /* translators: 1: order ID, 2: error message */
                    __('The order could not be imported #%1$d: %2$s', 'correo-argentino-for-woocommerce'),
                    $order_id,
                    $e->getMessage()
                ),
                'error',
                true,
                false
            );
            return;
        } catch (\Throwable $t) {
            Utils::message(
                sprintf(
                    /* translators: 1: order ID, 2: error message */
                    __('Unexpected error importing order #%1$d: %2$s', 'correo-argentino-for-woocommerce'),
                    (int) $order_id,
                    wp_strip_all_tags((string) $t->getMessage())
                ),
                'error',
                true,
                false
            );
            return;
        }

        if (is_wp_error($response)) {
            Utils::message($response->get_error_message(), 'error', true, false);
            return;
        }

        $serviceType = Utils::getCurrentServiceType();
        $this->responseEffect($response, $serviceType);

        if (is_array($response)) {
            if (!empty($response['error']) && !empty($response['error_body'])) {
                $decoded = json_decode((string) $response['error_body'], true);
                $status = isset($decoded['status']) ? $decoded['status'] : (wp_remote_retrieve_response_code($response['error_body']) ?: 'ERR');
                $msg = isset($decoded['message']) ? $decoded['message'] : __('Unknown error', 'correo-argentino-for-woocommerce');
                $message = '<b>Error ' . esc_html($status) . '</b> ' . esc_html($msg);
                Utils::message($message, 'error', true, false);
                return;
            }

            if (isset($response['status']) && (int) $response['status'] !== 200) {
                $status = (int) $response['status'];
                $msg = isset($response['message']) ? $response['message'] : __('Unknown error', 'correo-argentino-for-woocommerce');
                $message = '<b>Error ' . esc_html($status) . '</b> ' . esc_html($msg);
                Utils::message($message, 'error', true, false);
                return;
            }
        }
    }

    public function responseEffect($response, $serviceType)
    {

        if ($serviceType === CORRARFO_PAQ_AR) {
            if (!isset($response['error'])) {
                $adminUrl = esc_url(get_admin_url(null, 'admin.php?page=correoargentino-orders'));
                header("Location: $adminUrl");
                die;
            }
        }

        if ($serviceType === CORRARFO_MI_CORREO) {
            if (isset($response['status']) && $response["status"] == 200) {
                $adminUrl = esc_url(get_admin_url(null, 'admin.php?page=correoargentino-orders'));
                header("Location: $adminUrl");
                die;
            }
        }
    }

    public function cancel($order_id)
    {

        $confirmation = $this->show_cancel_order_confirmation($order_id);

        if ($confirmation) {
            $order = wc_get_order($order_id);
            $service = (new ServiceFactory())->get();
            $response = $service->cancel($order->get_meta(CORRARFO_TRACKING_NUMBER));

            if ($response) {
                $order->update_status('cancelled');
                $order->save();
                $message = sprintf('<p>%s</p>', __('The order was successfully canceled.', 'correo-argentino-for-woocommerce'));
                Utils::message($message, 'success', true, false);
            }
        }
    }

    private function show_cancel_order_confirmation($order_id)
    {
        $nonce_ok = false;

        if (isset($_POST['cancel_order_confirmation_nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['cancel_order_confirmation_nonce']));
            if (wp_verify_nonce($nonce, 'cancel_order_confirmation_nonce') && current_user_can('manage_woocommerce')) {
                $nonce_ok = true;
            }
        }

        if ($nonce_ok && !empty($_POST['confirm_cancel_order'])) {
            return true;
        }

        $message = '<form method="post">'
            . wp_nonce_field('cancel_order_confirmation_nonce', 'cancel_order_confirmation_nonce', true, false)
            . sprintf(
                '%s <code>#%d</code>, %s<br /><br />',
                esc_html__('You are going to cancel the order', 'correo-argentino-for-woocommerce'),
                (int) $order_id,
                esc_html__('Do you wish to continue?', 'correo-argentino-for-woocommerce')
            )
            . '<a href="' . esc_url(admin_url('admin.php?page=correoargentino-orders')) . '" class="button button-secondary">'
            . esc_html__('Discard', 'correo-argentino-for-woocommerce') . '</a>&nbsp;'
            . '<input type="submit" name="confirm_cancel_order" class="button button-primary" value="' . esc_attr__('Accept', 'correo-argentino-for-woocommerce') . '">'
            . '</form>';

        Utils::message($message, 'info', true, false);

        return false;
    }

    /**
     *
     * @throws Exception
     */
    function column_default($item, $column_name)
    {
        $trackingUrl = CORRARFO_TRACKING_URL;

        switch ($column_name) {
            case 'column_cb':
                return $this->column_cb($item);

            case 'tracking_number':
                $order = wc_get_order($item['ID']);
                $tracking = $order->get_meta(CORRARFO_TRACKING_NUMBER);
                $prefixedId = $order->get_meta('_corrarfo_prefixed_order_id');
                $baseId = $order->get_id();
                $extraRef = $prefixedId ? ' / ' . esc_html($prefixedId) : '';

                if ($tracking) {
                    if (Utils::getCurrentServiceType() === CORRARFO_PAQ_AR) {
                        return "<a href='" . $trackingUrl . $tracking . "' target='_blank' rel='noopener noreferrer'>" . esc_html($tracking) . "</a>" . $extraRef;
                    }
                    return esc_html($tracking . $extraRef);
                } elseif ($order->get_meta(CORRARFO_ORDER_API_ERROR)) {
                    return '<label class="error">' . esc_html($order->get_meta(CORRARFO_ORDER_API_ERROR)) . '</label>';
                }

                return esc_html($baseId . $extraRef);

            case 'status':
                if (isset($item['post_status'])) {
                    $order = wc_get_order($item['ID']);
                    $order_status = $order->get_status();
                    $status = esc_html(wc_get_order_status_name($order_status));

                    $output = '<mark class="order-status status-' . esc_attr(strtolower($order_status)) . ' ph-status-' . esc_attr($item['ID']) . '">';
                    $output .= '<span>' . $status . '</span>';
                    $output .= '</mark>';
                    return $output;
                }

                return $this->placeholder('status', $item['ID']);

            case 'shipping_type':
                return $item['woo']->order_item_name;

            case 'post_date':
                $timezone = new \DateTimeZone("America/Buenos_Aires");
                $date = new \DateTime($item['post_date'], $timezone);
                return $date->format("d/m/Y H:i");

            case 'agency_name':
                $branch_id = isset($item['meta'][CORRARFO_CHOSEN_BRANCH]) ? $item['meta'][CORRARFO_CHOSEN_BRANCH] : (isset($item['meta']['_correoargentino_chosen_branch']) ? $item['meta']['_correoargentino_chosen_branch'] : '');
                $branch_name = isset($item['meta'][CORRARFO_CHOSEN_BRANCH_NAME]) ? $item['meta'][CORRARFO_CHOSEN_BRANCH_NAME] : (isset($item['meta']['_correoargentino_chosen_branch_name']) ? $item['meta']['_correoargentino_chosen_branch_name'] : '');
                
                if ($branch_id && $branch_name) {
                    return ucwords(wc_strtolower($branch_name)) . ' <code>' . $branch_id . '</code>';
                }
                return '';

            case 'dimensions':
                $order_obj = wc_get_order($item['ID']);
                $dims = $order_obj ? $order_obj->get_meta('_corrarfo_dimensions', true) : '';
                if (!$dims && $order_obj) {
                    $dims = $order_obj->get_meta('_ca_dimensions', true); // Legacy
                }
                return ($dims) ?: '-';

            case 'weight':
                $order_obj = wc_get_order($item['ID']);
                $weight = $order_obj ? $order_obj->get_meta('_corrarfo_weight', true) : '';
                if ($weight === '' && $order_obj) {
                    $weight = $order_obj->get_meta('_ca_weight', true); // Legacy
                }
                return $weight !== '' ? $weight . ' kg' : '-';

            default:
                return '<pre>' . esc_html(wp_json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
        }
    }


    /**
     * Render the bulk edit checkbox
     *
     * @param array $item
     *
     * @return string
     */
    public function column_cb($item): string
    {
        if (Utils::getCurrentServiceType() !== CORRARFO_MI_CORREO || array_key_exists(CORRARFO_TRACKING_NUMBER, $item['meta']))
            return '';

        return sprintf(
            '<input type="checkbox" name="order_id[]" value="%s" />',
            $item['ID']
        );
    }

    public function placeholder($column_name, $id)
    {
        $class_name = "ph-{$column_name}-{$id}";
        return "<span class=\"$class_name\"></span>";
    }

    /**
     *
     */
    public function column_ID($item): string
    {
        $tracking = isset($item['meta'][CORRARFO_TRACKING_NUMBER]) ? $item['meta'][CORRARFO_TRACKING_NUMBER] : '';
        $serviceType = Utils::getCurrentServiceType();
        $id = (int) $item['ID'];

        // Read-only reading of $_GET (nonce required).
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : 'correoargentino-orders';

        $actions = [];

        $order = wc_get_order($id);

        if (empty($tracking) && !self::statusStartsIn($order->get_status(), 'cancel')) {
            $store_url = wp_nonce_url(
                add_query_arg(['page' => $page, 'action' => 'store', 'id' => $id], admin_url('admin.php')),
                'corrarfo_orders_action'
            );
            $actions['store'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url($store_url),
                $serviceType === CORRARFO_PAQ_AR ? esc_html__('Preimponer', 'correo-argentino-for-woocommerce') : esc_html__('Importar', 'correo-argentino-for-woocommerce')
            );
        } else {
            if (!self::statusStartsIn($order->get_status(), 'cancel') && $serviceType === CORRARFO_PAQ_AR) {
                $label_url = wp_nonce_url(
                    add_query_arg(['page' => $page, 'action' => 'label', 'id' => $id], admin_url('admin.php')),
                    'corrarfo_orders_action'
                );
                $actions['label'] = sprintf(
                    '<a href="%s" target="_blank" rel="noopener">%s</a>',
                    esc_url($label_url),
                    esc_html__('Etiqueta', 'correo-argentino-for-woocommerce')
                );

                $cancel_url = wp_nonce_url(
                    add_query_arg(['page' => $page, 'action' => 'cancel', 'id' => $id], admin_url('admin.php')),
                    'corrarfo_cancel_' . $id
                );
                $actions['cancel'] = sprintf(
                    '<a href="%s">%s</a>',
                    esc_url($cancel_url),
                    esc_html__('Cancelar', 'correo-argentino-for-woocommerce')
                );
            }
        }

        $customer = sprintf(
            '#%d %s %s',
            $id,
            isset($item['meta']['_billing_first_name']) ? esc_html($item['meta']['_billing_first_name']) : '',
            isset($item['meta']['_billing_last_name']) ? esc_html($item['meta']['_billing_last_name']) : ''
        );

        return sprintf('%1$s %2$s', $customer, $this->row_actions($actions));
    }


    public static function statusStartsIn($status, $string): bool
    {
        // Convert both strings to lowercase for case-insensitive comparison
        $status = strtolower($status);
        $string = strtolower($string);

        // Check if $string is present inside $status
        return str_contains($status, $string);
    }


    /**
     * Columns to make sortable.
     *
     * @return array
     */
    public function get_sortable_columns()
    {
        return [
            'ID' => ['ID', false],
            'shipping_type' => ['shipping_type', false],
            'dimensions' => ['dimensions', false],
            'weight' => ['weight', false],
            'status' => ['status', false],
            'agency_name' => ['agency_name', false],
            'post_date' => ['post_date', false],
        ];
    }


    /**
     * Method for name column
     *
     * @param array $item an array of DB data
     *
     * @return string
     */
    public function column_name($item): string
    {
        $title = '<strong>' . esc_html($item['name']) . '</strong>';

        // Read-only reading of $_GET (nonce required).
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : 'correoargentino-orders';

        $delete_url = add_query_arg(
            [
                'page' => $page,
                'action' => 'delete',
                'id' => absint($item['ID']),
            ],
            admin_url('admin.php')
        );

        $delete_url = wp_nonce_url($delete_url, 'corrarfo_orders_action');

        $actions = [
            'delete' => sprintf(
                '<a href="%s">%s</a>',
                esc_url($delete_url),
                esc_html__('Delete', 'correo-argentino-for-woocommerce')
            ),
        ];

        return $title . $this->row_actions($actions);
    }

    public function display_tablenav($which)
    {
        if ($which !== 'top' && $which !== 'bottom') {
            return;
        }

        $is_micorreo = (Utils::getCurrentServiceType() === CORRARFO_MI_CORREO);
        // Read-only reading of $_GET (nonce required).
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $show_hidden = isset($_GET['show_hidden']) ? ('1' === sanitize_text_field(wp_unslash($_GET['show_hidden']))) : false;

        ?>
        <div class="tablenav <?php echo esc_attr($which); ?>">

            <div class="alignleft actions bulkactions">
                <?php if ($which === 'top' && $is_micorreo): ?>
                    <label for="bulk-actions-top" class="screen-reader-text">
                        <?php esc_html_e('Bulk actions', 'correo-argentino-for-woocommerce'); ?>
                    </label>
                    <select name="bulk-actions-top" id="bulk-actions-top" class="bulk-actions-top">
                        <option value="-1"><?php esc_html_e('Bulk actions', 'correo-argentino-for-woocommerce'); ?></option>
                        <option value="importar"><?php esc_html_e('Import', 'correo-argentino-for-woocommerce'); ?></option>
                    </select>
                    <input type="submit" class="button action bulk-actions-button"
                        value="<?php esc_attr_e('Apply', 'correo-argentino-for-woocommerce'); ?>">
                <?php endif; ?>

                <button type="submit" name="export_corrarfo_orders" value="1" class="button action">
                    <?php esc_html_e('Export to CSV', 'correo-argentino-for-woocommerce'); ?>
                </button>
            </div>

            <?php if ($which === 'top'): ?>
                <?php
                $restore_url = admin_url('admin.php?page=correoargentino-orders&action=restore_hidden_orders' . ($show_hidden ? '&show_hidden=1' : ''));
                $restore_url = wp_nonce_url($restore_url, 'restore_hidden_orders');
                ?>
                <div class="alignright actions" style="margin-right:10px;">
                    <label for="show_hidden_orders" style="margin-right:8px;">
                        <input type="checkbox" id="show_hidden_orders" name="show_hidden" value="1" <?php checked($show_hidden); ?>>
                        <?php esc_html_e('Show hidden', 'correo-argentino-for-woocommerce'); ?>
                    </label>

                    <a href="<?php echo esc_url($restore_url); ?>" class="button action ca-restore-hidden-button"
                        onclick="return confirm('<?php echo esc_js(__('Are you sure you want to restore all hidden orders? This action cannot be undone.', 'correo-argentino-for-woocommerce')); ?>');">
                        <?php esc_html_e('Restore hidden orders', 'correo-argentino-for-woocommerce'); ?>
                    </a>
                </div>

                <?php

                $handle = 'ca-order-table-actions';
                $base_url = CORRARFO_PLUGIN_URL;
                $ver = defined('CORRARFO_VERSION') ? CORRARFO_VERSION : null;

                if (!wp_script_is($handle, 'registered')) {
                    wp_register_script(
                        $handle,
                        $base_url . 'js/order-table-actions.js',
                        array(),
                        $ver,
                        true
                    );
                }
                wp_enqueue_script($handle);

                wp_localize_script($handle, 'corrarfo_order_table_cfg', array(
                    'checkboxId' => 'show_hidden_orders',
                ));
                ?>
            <?php endif; ?>

            <?php $this->pagination($which); ?>
            <br class="clear" />
        </div>
        <?php
    }

    public function pagination($which)
    {
        return parent::pagination($which);
    }

    public function render_page_title()
    {
        echo '<h1 class="wp-heading-inline">' . esc_html__('Correo Argentino Orders', 'correo-argentino-for-woocommerce') . '</h1>';
    }

    public function column_corrarfo_actions($item)
    {
        return corrarfo_render_order_actions($item['ID']);
    }

    private function get_reference_column_label()
    {
        $type = Utils::getCurrentServiceType();

        if ($type === CORRARFO_PAQ_AR) {
            return __('Reference', 'correo-argentino-for-woocommerce');
        }

        if ($type === CORRARFO_MI_CORREO) {
            return __('Reference / MiCorreo ID', 'correo-argentino-for-woocommerce');
        }

        return __('Reference', 'correo-argentino-for-woocommerce');
    }

    public static function handle_export_csv()
    {

        // Read-only reading of $_GET (nonce required).
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $export_flag = isset($_GET['export_corrarfo_orders']) ? sanitize_text_field(wp_unslash($_GET['export_corrarfo_orders'])) : '';

        if ($export_flag !== '1') {
            return;
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Unauthorized.', 'correo-argentino-for-woocommerce'));
        }

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'corrarfo_orders_bulk_action')) {
            wp_die(esc_html__('Invalid security token.', 'correo-argentino-for-woocommerce'));
        }

        $table = new self();
        $table->prepare_items();
        $table->export_csv();
    }

    /**
     * Generates and sends the CSV with the visible page
     */
    public function export_csv()
    {

        while (ob_get_level()) {
            ob_end_clean();
        }
        remove_all_actions('admin_notices');

        $filename = 'correoargentino_orders_' . gmdate('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename={$filename}");
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');

        fputcsv($out, [
            'ID',
            'Referencia / MiCorreo ID',
            'Tipo de envío',
            'Dimensiones',
            'Peso',
            'Estado',
            'Sucursal',
            'Fecha',
        ]);

        foreach ($this->items as $row) {

            $order_id = (int) $row['ID'];
            $order = wc_get_order($order_id);

            $tracking = $order->get_meta(CORRARFO_TRACKING_NUMBER);
            $prefixed_id = $order->get_meta('_corrarfo_prefixed_order_id');
            $extra_ref = $prefixed_id ? ' / ' . $prefixed_id : '';

            if ($tracking) {
                $reference = $tracking . $extra_ref;
            } elseif ($order->get_meta(CORRARFO_ORDER_API_ERROR)) {
                $reference = $order->get_meta(CORRARFO_ORDER_API_ERROR);
            } else {
                $reference = $order_id . $extra_ref;
            }

            $dimensions = ($order ? $order->get_meta('_corrarfo_dimensions', true) : '') ?: '-';
            $weight = $order ? $order->get_meta('_corrarfo_weight', true) : '';
            $weight = $weight !== '' ? $weight . ' kg' : '-';
            $agency = $row['meta'][CORRARFO_CHOSEN_BRANCH_NAME] ?? '';
            $status = wc_get_order_status_name($order->get_status());
            $date = $order->get_date_created()
                ? $order->get_date_created()->date_i18n('Y-m-d H:i:s')
                : '';

            fputcsv($out, [
                $order_id,
                $reference,
                $row['woo']->order_item_name ?? '',
                $dimensions,
                $weight,
                $status,
                $agency,
                $date,
            ]);
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose($out);
        exit;
    }

    private function delete(int $id): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die();
        }

        $order = wc_get_order($id);
        if ($order) {
            $order->update_meta_data('_corrarfo_oculto', '1');
            $order->save();
        }

        wp_safe_redirect(remove_query_arg(['action', 'id', '_wpnonce']));
        exit;
    }


}

// Backward compatibility alias
if (!class_exists('CorreoArgentinoOrderTable')) {
    class_alias(OrderTable::class, 'CorreoArgentinoOrderTable');
}
