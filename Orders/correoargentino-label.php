<?php
namespace Corrarfo\Orders;

defined('ABSPATH') || exit;

/**
 * WooCommerce Order Metabox's base Class
 */
class Label
{
    /**
     *
     * @return void
     */
    public static function create($arg)
    {
        register_rest_route(
            'correoagentino',
            '/labels',
            array(
                'methods' =>  'POST',
                'callback' => [__CLASS__, 'content'],
                'permission_callback' => '__return_true'
            )
        );
    }


    /**
     * @param ...$arg
     */
    public static function content(...$arg)
    {
        /* if (!isset($_GET['tracking'])) {
            die('ERROR');
        }

        $tracking = $_GET['tracking'];
        $service = Corrarfo_Service::getInstanceWithLogin();
        $label = $service->label($tracking);*/
    }
}

// Backward compatibility alias
if (!class_exists('CorreoArgentinoLabel')) {
    class_alias(Label::class, 'CorreoArgentinoLabel');
}
