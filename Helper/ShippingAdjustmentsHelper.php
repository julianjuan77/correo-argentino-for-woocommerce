<?php

namespace Corrarfo\Helper;

if (!defined('ABSPATH')) {
    exit;
}

class ShippingAdjustmentsHelper
{
    /**
     * Applies a percentage surcharge or discount to the base shipping cost
     *
     * @param float \$baseCost Base cost obtained from the API
     * @param float \$cartSubtotal Cart subtotal
     * @return float Adjusted cost
     */
    public static function applyAdjustment(float $baseCost, float $cartSubtotal): float
    {
        $enabled = get_option('corrarfo_shipping_enable');
        $type = get_option('corrarfo_shipping_type', 'surcharge');
        $percentage = floatval(get_option('corrarfo_shipping_percentage', 0));
        $minSubtotal = floatval(get_option('corrarfo_shipping_min_total', 0));

        if (!$enabled || $percentage <= 0 || $cartSubtotal < $minSubtotal) {
            return $baseCost;
        }

        $adjustment = $baseCost * ($percentage / 100);

        if ($type === 'surcharge') {
            $baseCost += $adjustment;
        } elseif ($type === 'discount') {
            $baseCost -= $adjustment;
            if ($baseCost < 0) {
                $baseCost = 0;
            }
        }

        return $baseCost;
    }
}

// Backward compatibility alias
if (!class_exists('ShippingAdjustmentsHelper')) {
    class_alias(ShippingAdjustmentsHelper::class, 'ShippingAdjustmentsHelper');
}