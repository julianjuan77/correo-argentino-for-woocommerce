<?php
namespace Corrarfo\Service;

if (!defined('ABSPATH')) {
    exit;
}

interface ServiceInterface
{
    public function login();
    public function setHeaders($headers = []);
    public function registerOrder($orderId);
}

interface ServiceMiCorreoInterface extends ServiceInterface
{
    public function getRates($postalCode, $deliveryType, $dimensions, $settings = null);
}

interface ServicePaqArInterface extends ServiceInterface
{
    public function cancel($tracking);
    public function label($tracking);
    public function getRates($postalCode, $deliveryType, $dimensions, $settings = null);
}

// Backward compatibility aliases
if (!interface_exists('Corrarfo_ServiceInterface')) {
    class_alias(ServiceInterface::class, 'Corrarfo_ServiceInterface');
}
if (!interface_exists('Corrarfo_ServiceMiCorreoInterface')) {
    class_alias(ServiceMiCorreoInterface::class, 'Corrarfo_ServiceMiCorreoInterface');
}
if (!interface_exists('Corrarfo_ServicePaqArInterface')) {
    class_alias(ServicePaqArInterface::class, 'Corrarfo_ServicePaqArInterface');
}