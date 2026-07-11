<?php
/**
 * Fork Julian Juan — Fallback de destino de envío a la dirección de facturación.
 *
 * Problema: con `woocommerce_ship_to_destination = 'billing'` (default de WC), si
 * el cliente NO marca "enviar a una dirección diferente", el destino del envío
 * sale de los campos de envío (`s_*`). El checkout.js de WooCommerce debería
 * copiar los datos de facturación a `s_*` en ese caso, pero algunos temas/checkouts
 * no lo hacen → el paquete queda sin código postal de destino → Correo Argentino
 * no puede cotizar y WooCommerce muestra "Ingresá tu dirección para ver las
 * opciones de envío".
 *
 * Solución (server-side, independiente del JS del navegador): si el destino del
 * paquete no trae código postal, se completa con la dirección de facturación del
 * cliente. Es exactamente el comportamiento "enviar a la dirección de facturación"
 * que espera el usuario para envío a domicilio.
 *
 * No afecta a sucursal: en ese flujo se marca "otra dirección" y los campos de
 * envío se llenan con la dirección de la sucursal, así que el destino YA trae
 * código postal y la guarda `empty(...)` no lo pisa.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('woocommerce_cart_shipping_packages', function ($packages) {
    // Comportamiento opinado: se puede desactivar con
    // add_filter('corrarfo_fill_destination_from_billing', '__return_false');
    if (!apply_filters('corrarfo_fill_destination_from_billing', true)) {
        return $packages;
    }

    if (empty($packages) || !function_exists('WC') || !WC()->customer) {
        return $packages;
    }

    $billing_postcode = WC()->customer->get_billing_postcode();

    // Sin CP de facturación no hay nada que copiar (p. ej. checkout recién cargado).
    if ('' === $billing_postcode || null === $billing_postcode) {
        return $packages;
    }

    $billing = array(
        'country'   => WC()->customer->get_billing_country(),
        'state'     => WC()->customer->get_billing_state(),
        'postcode'  => $billing_postcode,
        'city'      => WC()->customer->get_billing_city(),
        'address'   => WC()->customer->get_billing_address_1(),
        'address_1' => WC()->customer->get_billing_address_1(),
        'address_2' => WC()->customer->get_billing_address_2(),
    );

    foreach ($packages as $i => $package) {
        // Solo cuando el destino del envío no tiene CP (envío a domicilio con
        // "otra dirección" desmarcada). Si ya hay CP (sucursal o envío distinto
        // elegido a propósito), se respeta.
        if (empty($package['destination']['postcode'])) {
            foreach ($billing as $key => $value) {
                if (empty($packages[$i]['destination'][$key]) && '' !== (string) $value) {
                    $packages[$i]['destination'][$key] = $value;
                }
            }
        }
    }

    return $packages;
}, 20);
