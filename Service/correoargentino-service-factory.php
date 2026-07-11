<?php
namespace Corrarfo\Service;

use Corrarfo\Lib\Utils;

if (!defined('ABSPATH')) {
    exit;
}

class ServiceFactory
{
    private $serviceType;

    public function __construct()
    {
        $this->serviceType = Utils::getCurrentServiceType();
        if (!isset($this->serviceType)) {
            throw new \Exception("Tipo de servicio indefinido", 1);
        }
    }

    public function get()
    {
        if ($this->serviceType == CORRARFO_MI_CORREO) {
            return new \Corrarfo_MiCorreoService();
        }
        if ($this->serviceType == CORRARFO_PAQ_AR) {
            return new \Corrarfo_PaqArService();
        }
    }
}

// Backward compatibility alias
if (!class_exists('Corrarfo_ServiceFactory')) {
    class_alias(ServiceFactory::class, 'Corrarfo_ServiceFactory');
}