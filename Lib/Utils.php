<?php
namespace Corrarfo\Lib;

use WC_Order;
use WC_Shipping_Method;
use WC_Product;
use WC_Order_Item_Product;
use Corrarfo_Shipping_Method;

if (!defined('ABSPATH')) {
    exit;
}

class Utils
{
    const REQUIRED_SIGN = '<sup>*</sup>';

    static function getProvinces(): array
    {
        $provinces = json_decode(file_get_contents(CORRARFO_PLUGIN_DIR . 'Mock/provinces.json'), true);
        $results = [];
        foreach ($provinces["provincias"] as $item) {
            $code = $item['iso_id'];
            $results[$code] = $item['nombre'];
        }
        asort($results);
        return $results;
    }

    static function getDocumentTypes(): array
    {
        return array("DNI" => "DNI", "CUIT" => "CUIT");
    }

    static function getProductDimensions($product): array
    {
        $weightKg = (float) $product->get_weight();
        $height = (float) $product->get_height();
        $width = (float) $product->get_width();
        $length = (float) $product->get_length();

        $useDefaults = get_option('corrarfo_default_enabled') === '1';

        if ($useDefaults && $weightKg <= 0) {
            $weightKg = (float) get_option('corrarfo_default_weight', 0);
            $height = (float) get_option('corrarfo_default_height', 0);
            $width = (float) get_option('corrarfo_default_width', 0);
            $length = (float) get_option('corrarfo_default_length', 0);
        }

        return [
            "weight" => self::fromKgToGrams($weightKg),
            "height" => ceil($height),
            "width" => ceil($width),
            "length" => ceil($length),
        ];
    }

    static function getAvailableServices(): array
    {
        return array(
            "micorreo" => "PAQ.AR API MiCorreo",
            "paqar" => "PAQ.AR API 2.0"
        );
    }

    /**
     * @param $code
     * @return array |string|null
     */
    public static function normalizeZipCode($code)
    {
        return substr(preg_replace('/\D/', '', $code), 0, 4);
    }

    public static function cleanPhone($phone)
    {
        return preg_replace("/\-/", "", $phone);
    }


    public static function getCurrentServiceType()
    {
        return get_option(CORRARFO_SERVICE_TYPE);
    }

    public static function getUseRates()
    {
        return get_option(CORRARFO_USE_RATES);
    }

    public static function isConnected()
    {
        return get_option(CORRARFO_IS_CONNECTED);
    }

    public static function canListRates($settings = null)
    {

        if (get_option(CORRARFO_IS_CONNECTED) != 1) {
            // component is not login
            return false;
        }

        if (!$settings) {
            $settings = self::getSettings();
        }

        $requiredFields = array(
            'business_name',
            'state',
            'city_name',
            'street_name',
            'street_number',
            'zip_code',
        );

        foreach ($requiredFields as $field) {
            if (empty($settings[$field])) {
                return false;
            }
        }

        return true;
    }

    public static function getSettings()
    {
        $defaults = [
            'customer_id' => null,
            'first_name' => null,
            'last_name' => null,
            'business_name' => null,
            'email' => null,
            'password' => null,
            'state' => null,
            'state_code' => null,
            'city_name' => null,
            'department' => null,
            'floor' => null,
            'street_name' => null,
            'street_number' => null,
            'zip_code' => null,
            'cellphone' => null,
            'phone' => null,
            'observation' => null,
        ];

        $settings = maybe_unserialize(get_option(CORRARFO_SETTINGS));
        $credentials = maybe_unserialize(get_option(CORRARFO_CREDENTIALS));

        if (!is_array($settings)) {
            $settings = [];
        }

        $settings = array_merge($defaults, $settings);

        if (is_array($credentials) && !empty($credentials['customer_id'])) {
            $settings['customer_id'] = $credentials['customer_id'];
        }

        return $settings;
    }


    public static function businessDetailsFilled()
    {
        return get_option(CORRARFO_BUSINESS_DETAILS_FILLED);
    }

    public static function getCredentials()
    {
        return maybe_unserialize(get_option(CORRARFO_CREDENTIALS));
    }

    public static function message($message, $type = 'message', $dismissible = false, $attach = true)
    {
        $classes = 'notice notice-' . esc_attr($type);
        if (!is_admin()) {
            $classes .= ' woocommerce-' . esc_attr($type);
        }
        if ($dismissible) {
            $classes .= ' is-dismissible';
        }
        if ($attach) {
            add_action('admin_notices', function () use ($message, $classes) {
                echo '<div class="' . esc_attr($classes) . '"><p>' . wp_kses_post($message) . '</p></div>';
            });
        } else {
            echo '<div class="' . esc_attr($classes) . '"><p>' . wp_kses_post($message) . '</p></div>';
        }
    }


    public static function unset_wc_chosen_branch_values()
    {
        WC()->session->__unset(CORRARFO_CHOSEN_BRANCH);
        WC()->session->__unset(CORRARFO_CHOSEN_BRANCH_NAME);
        WC()->session->__unset(CORRARFO_CHOSEN_BRANCH_ZIP_CODE);
        WC()->session->__unset(CORRARFO_CHOSEN_BRANCH_STREET_NAME);
        WC()->session->__unset(CORRARFO_CHOSEN_BRANCH_STREET_NUMBER);
        WC()->session->__unset(CORRARFO_CHOSEN_BRANCH_CITY);
        WC()->session->__unset(CORRARFO_CHOSEN_BRANCH_STATE);
        WC()->session->__unset(CORRARFO_CHOSEN_SERVICE_TYPE);
        WC()->session->__unset(CORRARFO_CHOSEN_PRODUCT_TYPE);
        WC()->session->__unset(CORRARFO_CHOSEN_PROVINCE_CODE);
    }

    public static function isCAShippingMethod($chosenShippingMethod)
    {
        return strpos($chosenShippingMethod, CORRARFO_PLUGIN_ID) === 0;
    }

    public static function isBranch($chosenShippingMethod)
    {
        require_once CORRARFO_PLUGIN_DIR . 'Classes/correoargentino-shipping-method.php';
        if (strpos($chosenShippingMethod, CORRARFO_PLUGIN_ID) === 0) {
            $shipping_method_id = (int) substr($chosenShippingMethod, strlen(CORRARFO_PLUGIN_ID) + 1);

            if ($shipping_method_id > 0) {
                $corrarfo_shipping_method = new \Corrarfo_Shipping_Method($shipping_method_id);

                $metodo_envio = $corrarfo_shipping_method->get_option('metodo_envio');

                if ($metodo_envio === 'S') {
                    return true;
                }
            }
        }
        return false;
    }

    public static function fromKgToGrams($kg)
    {
        // Remove thousand separators (,)
        $kg = str_replace(',', '', $kg);

        // Replace the last dot (.) with an empty string if multiple dots are present
        $kg = preg_replace('/\.(\d*)\./', '.$1', $kg);

        // Replace the first dot (.) with an empty string if at the beginning of the number
        $kg = preg_replace('/^(\.)/', '', $kg);

        // Convert the input to a float and then multiply by 1000
        return floatval($kg) * 1000;
    }

    public static function toInteger($num): int
    {
        $num = str_replace(',', '.', $num);

        $result = floatval($num);
        if ($result < 1) {
            $result = ceil($result);
        }

        return (int) $result;
    }

    public static function dimensionToInteger($num): int
    {
        $num = str_replace(',', '.', $num);

        $result = floatval($num);

        $result = ceil($result);

        return (int) $result;
    }

    public static function shouldProcessOrder($order_id)
    {
        $chosenServiceType = self::getOrderServiceType($order_id);
        $currentServiceType = self::getCurrentServiceType();

        return $chosenServiceType === $currentServiceType;
    }

    public static function getOrderServiceType($order_id)
    {
        $order_id = absint($order_id);
        if (!$order_id) {
            return '';
        }

        $val = get_post_meta($order_id, CORRARFO_CHOSEN_SERVICE_TYPE, true);
        if (is_string($val) && $val !== '') {
            return $val;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return '';
        }

        foreach ($order->get_items('shipping') as $item) {
            $v = $item->get_meta(CORRARFO_CHOSEN_SERVICE_TYPE, true);
            if (is_string($v) && $v !== '') {
                return $v;
            }
        }

        foreach ($order->get_items() as $item) {
            $v = $item->get_meta(CORRARFO_CHOSEN_SERVICE_TYPE, true);
            if (is_string($v) && $v !== '') {
                return $v;
            }
        }

        return '';
    }

    public static function getCustomerId(): string
    {
        $credentials = self::getCredentials();

        if (!empty($credentials['customer_id'])) {
            return $credentials['customer_id'];
        }

        if (!empty($credentials['idCliente'])) {
            return $credentials['idCliente'];
        }

        return '';
    }

    public static function corrarfo_get_order_item_weight_kg(WC_Order_Item_Product $item): float
    {
        $qty = max(1, (int) $item->get_quantity());
        $w = (float) $item->get_meta('_corrarfo_weight', true);
        if ($w <= 0) {
            $p = $item->get_product();
            $w = (float) $p->get_weight();
            if ($w <= 0)
                $w = (float) get_option('corrarfo_default_weight', 0);
            if ($w <= 0)
                $w = 0.1;
            $w = (float) wc_get_weight($w, 'kg');
        }
        return $w * $qty;
    }

    public static function corrarfo_get_order_item_dims_cm(WC_Order_Item_Product $item): array
    {
        $len = (float) $item->get_meta('_corrarfo_length', true);
        $wid = (float) $item->get_meta('_corrarfo_width', true);
        $hei = (float) $item->get_meta('_corrarfo_height', true);

        if ($len <= 0 || $wid <= 0 || $hei <= 0) {
            $p = $item->get_product();
            if ($len <= 0)
                $len = (float) $p->get_length();
            if ($wid <= 0)
                $wid = (float) $p->get_width();
            if ($hei <= 0)
                $hei = (float) $p->get_height();
            if ($len <= 0)
                $len = (float) get_option('corrarfo_default_length', 0);
            if ($wid <= 0)
                $wid = (float) get_option('corrarfo_default_width', 0);
            if ($hei <= 0)
                $hei = (float) get_option('corrarfo_default_height', 0);
            $len = (float) wc_get_dimension($len, 'cm');
            $wid = (float) wc_get_dimension($wid, 'cm');
            $hei = (float) wc_get_dimension($hei, 'cm');
        }
        return [$len, $wid, $hei];
    }

    /** 
     * Returns product dimensions/weight in the same format as getProductDimensions(),
     * But if the default values ​​are DISABLED and the weight is 0, it throws an exception..
     * Return format: ['weight' => int(grams), 'height' => float, 'width' => float, 'length' => float]
     */
    public static function getProductDimensionsStrict(WC_Product $product): array
    {
        $dims = self::getProductDimensions($product);
        $defaultsOn = (get_option('corrarfo_default_enabled') === '1');

        if (!$defaultsOn && (empty($dims['weight']) || (float) $dims['weight'] <= 0)) {
            $name = method_exists($product, 'get_name') ? $product->get_name() : '';
            throw new \RuntimeException(
                sprintf(
                    /* translators: %s: product name */
                    esc_html__('The product %s has no configured weight.', 'correo-argentino-for-woocommerce'),
                    esc_html($name)
                )
            );
        }
        return $dims;
    }

    /**
     * Returns dimensions/weight by first reading the ORDER ITEM'S GOALS (historicity).
     * If there are no goals, it falls to product. With the same strict policy as above.
     * Returns: ['weight' => int(grams), 'height' => float, 'width' => float, 'length' => float]
     */
    public static function getOrderItemDimensionsStrict(WC_Order_Item_Product $item): array
    {
        $w = (float) $item->get_meta('_corrarfo_weight', true);
        $len = (float) $item->get_meta('_corrarfo_length', true);
        $wid = (float) $item->get_meta('_corrarfo_width', true);
        $hei = (float) $item->get_meta('_corrarfo_height', true);

        if ($w > 0 || $len > 0 || $wid > 0 || $hei > 0) {
            return [
                'weight' => (int) round(self::fromKgToGrams($w)),
                'height' => $hei,
                'width' => $wid,
                'length' => $len,
            ];
        }

        $product = $item->get_product();
        return self::getProductDimensionsStrict($product);
    }

    public static function isHPOSActive(): bool
    {
        if (!class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
            return false;
        }
        if (!method_exists('\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled')) {
            return false;
        }
        return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }

    public static function getServiceDataFromShippingMethod($shippingMethod)
    {
        if (!$shippingMethod) {
            return [];
        }

        // Try new key
        $data = $shippingMethod->get_meta('corrarfo_data');

        // If empty, try legacy key
        if (empty($data)) {
            $data = $shippingMethod->get_meta('ca_data');
        }

        return is_array($data) ? $data : [];
    }
}

// Support for old name
if (!class_exists('\Corrarfo_Utils')) {
    class_alias(Utils::class, '\Corrarfo_Utils');
}

