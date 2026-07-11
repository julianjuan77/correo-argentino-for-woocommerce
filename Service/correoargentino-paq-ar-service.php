<?php
namespace Corrarfo\Service;

use Corrarfo\Lib\Utils;
use Exception;
use WC_Order;
use WC_Order_Item_Product;

if (!defined('ABSPATH')) {
    exit;
}

/**
 *
 */
class PaqArService implements ServicePaqArInterface
{

    /**
     * @var
     */
    public $client;


    /**
     * @var string
     */
    public $url;

    /**
     * @var false mixed void
     */
    private $agreement;

    /**
     * @var false mixed void
     */
    private $apiKey;


    /**
     * Corrarfo_Service constructor.
     */
    public function __construct()
    {
        $credentials = (array) maybe_unserialize(get_option(CORRARFO_CREDENTIALS));

        $sandbox_saved = isset($credentials['sandbox']) ? (int) $credentials['sandbox'] : 0;
        $this->url = (1 === $sandbox_saved) ? CORRARFO_PAQ_AR_API_SANDBOX_URL : CORRARFO_PAQ_AR_API_URL;
        $this->agreement = isset($credentials['agreement']) ? $credentials['agreement'] : '';
        $this->apiKey = isset($credentials['api_key']) ? $credentials['api_key'] : '';
    }

    /**
     * @return mixed|null
     */
    public function login()
    {
        $request = wp_remote_get(
            $this->url . '/auth',
            [
                'method' => 'GET',
                'headers' => $this->setHeaders(),
                'timeout' => 20,
            ]
        );
        $response = json_decode(wp_remote_retrieve_body($request), true);
        if (!is_array($response)) {
            $response = [];
        }
        $response['status'] = wp_remote_retrieve_response_code($request);
        $response['message'] = wp_remote_retrieve_response_message($request);

        return $response;
    }

    /**
     * @return array
     */
    public function setHeaders($headers = [])
    {
        $defaultHeaders = [
            "Content-Type" => "application/json",
            "Accept" => "application/json",
            "Connection" => "keep-alive",
            "Authorization" => "Apikey " . $this->apiKey,
            "Agreement" => $this->agreement
        ];

        return array_merge($defaultHeaders, $headers);
    }

    public function getBranches($query = [])
    {
        $defaultQuery = ["pickup_availability" => 1];
        $query = array_merge($defaultQuery, $query);
        
        $provinceCode = isset($query['stateId']) ? $query['stateId'] : 'ALL';
        $cacheKey = 'corrarfo_agencies_paqar_' . $provinceCode;
        
        $cached = get_transient($cacheKey);
        if (false !== $cached && is_array($cached)) {
            return $cached;
        }

        $request = wp_remote_get(
            add_query_arg($query, $this->url . '/agencies'),
            [
                'method' => 'GET',
                'headers' => $this->setHeaders(),
                'timeout' => 20,
            ]
        );

        if (is_wp_error($request) || wp_remote_retrieve_response_code($request) != 200) {
            return false;
        }

        $agencies = json_decode(wp_remote_retrieve_body($request), true);
        
        if (is_array($agencies)) {
            set_transient($cacheKey, $agencies, 12 * HOUR_IN_SECONDS);
        }

        return $agencies;
    }

    public function getRates($postalCode, $deliveryType, $dimensions, $settings = null)
    {
        return [
            "customerId" => "",
            "validTo" => "",
            "rates" => [
                [
                    "deliveredType" => "$deliveryType",
                    "productType" => "CP",
                    "productName" => "Correo Argentino Clasico",
                    "price" => 0,
                    "deliveryTimeMin" => "2",
                    "deliveryTimeMax" => "5"
                ],
                [
                    "deliveredType" => "$deliveryType",
                    "productType" => "EP",
                    "productName" => "Correo Argentino Expreso",
                    "price" => 0,
                    "deliveryTimeMin" => "1",
                    "deliveryTimeMax" => "3"
                ],
            ]
        ];
    }

    /**
     * @param $tracking
     * @return mixed
     */
    public function label($tracking)
    {
        $body = [["trackingNumber" => $tracking]];
        $request = wp_remote_post(
            $this->url . '/labels',
            [
                'method' => 'POST',
                'headers' => $this->setHeaders(),
                'body' => json_encode(($body)),
                'timeout' => 20,
            ]
        );

        if (is_wp_error($request) || wp_remote_retrieve_response_code($request) != 200) {
            return false;
        }

        return json_decode(wp_remote_retrieve_body($request), true);
    }

    /**
     * @param $tracking
     * @return mixed
     */
    public function cancel($tracking)
    {

        $request = wp_remote_post(
            $this->url . '/orders/' . $tracking . '/cancel',
            [
                'method' => 'PATCH',
                'headers' => $this->setHeaders(),
                'timeout' => 20,
            ]
        );

        if (is_wp_error($request) || wp_remote_retrieve_response_code($request) != 200) {
            return false;
        }

        return true;
    }

    /**
     * @param $orderId
     * @return array|mixed
     */
    public function registerOrder($orderId)
    {
        $order = wc_get_order($orderId);
        $shippingItems = $order->get_items('shipping');
        $shippingMethod = reset($shippingItems);
        $corrarfo_data = Utils::getServiceDataFromShippingMethod($shippingMethod);
        $orderData = $order->get_data();
        $branch_id = $order->get_meta(CORRARFO_CHOSEN_BRANCH);
        $branch_province = $order->get_meta(CORRARFO_CHOSEN_PROVINCE_CODE);
        $shipping = $orderData['shipping'];
        $billing = $orderData['billing'];
        $items = $order->get_items();
        $shippingMethod = $order->get_shipping_method();
        $isBranch = stripos($shippingMethod, CORRARFO_BRANCH);
        $dataStore = array_merge(Utils::getSettings(), $corrarfo_data);
        $products = array_values(
            array_map(function (WC_Order_Item_Product $item) {
                $qty = max(1, (int) $item->get_quantity());
                $dims = Utils::getOrderItemDimensionsStrict($item);

                $total = (float) $item->get_total();
                $name = $item->get_name();
                $weightGr = (int) round((float) $dims['weight']) * $qty;

                $depth = Utils::dimensionToInteger(empty($dims['length']) ? 1 : $dims['length']);
                $height = Utils::dimensionToInteger(empty($dims['height']) ? 1 : $dims['height']);
                $width = Utils::dimensionToInteger(empty($dims['width']) ? 1 : $dims['width']);

                return [
                    'declaredValue' => $total,
                    'dimensions' => [
                        'depth' => $depth,
                        'height' => $height,
                        'width' => $width,
                    ],
                    'productCategory' => $name,
                    'productWeight' => $weightGr,
                ];
            }, $items)
        );

        $totalWeight = array_sum(array_column($products, 'productWeight'));
        $totalWeightKg = round($totalWeight / 1000, 2);

        $maxDims = ['depth' => 1, 'height' => 1, 'width' => 1];

        foreach ($products as $p) {
            $maxDims['depth'] = max($maxDims['depth'], $p['dimensions']['depth']);
            $maxDims['height'] = max($maxDims['height'], $p['dimensions']['height']);
            $maxDims['width'] = max($maxDims['width'], $p['dimensions']['width']);
        }

        list($dimensionString, $weightKg) = $this->calculateOrderDimensions($order);
        $order->update_meta_data('_corrarfo_dimensions', $dimensionString);
        $order->update_meta_data('_corrarfo_weight', $weightKg);
        $order->save();

        $streetName = $shipping['address_1'];
        $altura = !empty($shipping['address_2']) ? $shipping['address_2'] : '0';
        $fullAddress = trim("{$streetName} {$altura}");

        if ($isBranch) {
            $branch = $this->getBranch($branch_id, $branch_province);
            $location = $branch['location'];
            $shippingData = [
                "address" => [
                    "cityName" => $location['city_name'] ?? 'CABA',
                    "department" => "",
                    "floor" => "",
                    "state" => "B",
                    "streetName" => $fullAddress,
                    "streetNumber" => 0,
                    "zipCode" => Utils::normalizeZipCode($location['zip_code'] ?? '1407')
                ],
                "areaCodeCellphone" => "54",
                "areaCodePhone" => "54",
                "phoneNumber" => $shipping['phone'],
                "cellphoneNumber" => $shipping['phone'],
                "email" => $billing['email'],
                "name" => $billing['first_name'] . ' ' . $billing['last_name'],
                "observation" => "agency",
            ];
        } else {
            $shippingData = [
                "address" => [
                    "cityName" => $shipping['city'] ?? 'CABA',
                    "department" => "",
                    "floor" => "",
                    "state" => $shipping['state'],
                    "streetName" => $fullAddress,
                    "streetNumber" => 0,
                    "zipCode" => $shipping['postcode']
                ],
                "areaCodeCellphone" => "54",
                "areaCodePhone" => "54",
                "phoneNumber" => $shipping['phone'],
                "cellphoneNumber" => $shipping['phone'],
                "email" => $billing['email'],
                "name" => $billing['first_name'] . ' ' . $billing['last_name'],
                "observation" => $orderData['customer_note'],
            ];
        }
        $cellphone = !empty($dataStore['cellphone']) ? explode('-', $dataStore['cellphone']) : ['', ''];
        $phone = !empty($dataStore['phone']) ? explode('-', $dataStore['phone']) : ['', ''];
        $senderData = [
            "address" => [
                "cityName" => $dataStore['city_name'],
                "department" => $dataStore['department'],
                "floor" => $dataStore['floor'],
                "state" => $dataStore['state'],
                "streetName" => $dataStore['street_name'],
                "streetNumber" => $dataStore['street_number'],
                "zipCode" => Utils::normalizeZipCode($dataStore['zip_code']),
            ],
            "businessName" => $dataStore['business_name'],
            "areaCodeCellphone" => $cellphone[0],
            "cellphoneNumber" => $cellphone[1],
            "areaCodePhone" => $phone[0],
            "phoneNumber" => $phone[1],
            "email" => $dataStore['email'],
            "observation" => $dataStore['observation']
        ];

        $body = [
            "sellerId" => "",
            "trackingNumber" => "",
            "order" => [
                "agencyId" => $isBranch ? $branch_id : null,
                "deliveryType" => $isBranch ? CORRARFO_AGENCY : CORRARFO_HOME_DELIVERY,
                "parcels" => $products,
                "shipmentClientId" => "A0000",
                "serviceType" => "CP",
                "saleDate" => gmdate('Y-m-d\TH:i:sO'),
                "senderData" => $senderData,
                "shippingData" => $shippingData,
            ]
        ];


        $request = wp_remote_post(
            $this->url . '/orders',
            [
                'method' => 'POST',
                'headers' => $this->setHeaders(),
                'body' => json_encode($body),
                'timeout' => 20,
            ]
        );


        if (is_wp_error($request) || wp_remote_retrieve_response_code($request) != 200) {
            $error_body = json_decode(wp_remote_retrieve_body($request));
            if (isset($error_body->message)) {
                $order->update_meta_data(CORRARFO_ORDER_API_ERROR, $error_body->message);
                $order->save();
            }

            return [
                "error" => true,
                "error_body" => wp_remote_retrieve_body($request),
            ];
        }
        $response = json_decode(wp_remote_retrieve_body($request), true);
        if (!is_array($response) || !isset($response['trackingNumber'])) {
            return [
                "error" => true,
                "error_body" => wp_remote_retrieve_body($request),
            ];
        }
        $order->update_meta_data(CORRARFO_TRACKING_NUMBER, $response["trackingNumber"]);
        $order->save();
        return $response;


    }

    public function getBranch($branch_id, $provinceCode = null)
    {
        // If we have a province code, we can check that specific cache first
        if ($provinceCode) {
            $agencies = $this->getBranches(['stateId' => $provinceCode]);
        } else {
            // Fallback: try to fetch all agencies if no province provided (expensive, but better than nothing)
            // or we could search in all existing transients if we wanted to be more complex.
            $agencies = $this->getBranches();
        }

        if (!$agencies || !is_array($agencies)) {
            return null;
        }

        foreach ($agencies as $data) {
            if ($branch_id == $data['agency_id']) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Calcula las dimensiones y peso totales para los productos de una orden.
     *
     * @param WC_Order $order
     * @return array [string $dimensionString, float $weightKg]
     */
    private function calculateOrderDimensions($order)
    {
        $items = $order->get_items();

        $weight = 0;
        $height = 0;
        $restDimensions = [];

        foreach ($items as $item) {
            $product = $item->get_product();
            $quantity = $item->get_quantity();
            $effectiveDimensions = Utils::getOrderItemDimensionsStrict($item);
            $productWeight = $effectiveDimensions['weight'];
            $dimensions = [
                $effectiveDimensions['length'],
                $effectiveDimensions['width'],
                $effectiveDimensions['height']
            ];

            $dimensions = array_map('ceil', $dimensions);
            sort($dimensions);

            $weight += $productWeight * $quantity;
            $height += $dimensions[0] * $quantity;

            for ($i = 0; $i < $quantity; $i++) {
                $restDimensions[] = [$dimensions[1], $dimensions[2]];
            }
        }

        $widths = array_column($restDimensions, 0);
        $lengths = array_column($restDimensions, 1);

        $width = max($widths);
        $length = max($lengths);

        $dimensionString = "{$length}x{$width}x{$height}";
        $weightKg = round($weight / 1000, 2);

        return [$dimensionString, $weightKg];
    }

}

// Backward compatibility alias
if (!class_exists('Corrarfo_PaqArService')) {
    class_alias(PaqArService::class, 'Corrarfo_PaqArService');
}
