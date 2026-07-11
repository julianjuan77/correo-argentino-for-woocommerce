<?php
namespace Corrarfo\Service;

use Corrarfo\Lib\Utils;
use WC_Order_Item_Product;

if (!defined('ABSPATH')) {
    exit;
}

/**
 *
 */
class Service
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
     * @var false|mixed|void
     */
    private $agreement;

    /**
     * @var false|mixed|void
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
        $this->apiKey = isset($credentials['api_key'])
            ? $credentials['api_key']
            : (isset($credentials['apiKey']) ? $credentials['apiKey'] : '');
    }



    /**
     * @return mixed|null
     */
    public function login()
    {
        $credentials = maybe_unserialize(get_option(CORRARFO_CREDENTIALS));

        $request = wp_remote_get(
            $this->url . '/auth',
            [
                'method' => 'GET',
                'headers' => $this->setHeaders(),
                'timeout' => 20,
            ]
        );
        $response = json_decode(wp_remote_retrieve_body($request), true);
        if (wp_remote_retrieve_response_code($request) != 204) {
            update_option(CORRARFO_IS_CONNECTED, 0);
            return $response;
        }
        $credentials["agreement"] = $this->agreement;
        $credentials["apiKey"] = $this->apiKey;
        $data = maybe_serialize($credentials);
        update_option(CORRARFO_IS_CONNECTED, 1);
        update_option(CORRARFO_IS_FIRST_TIME, 0);
        update_option(CORRARFO_CREDENTIALS, $data);
        return $response;
    }

    /**
     * @return array
     */
    private function setHeaders()
    {
        return [
            "Content-Type" => "application/json",
            "Accept" => "application/json",
            "Connection" => "keep-alive",
            "Authorization" => "Apikey " . $this->apiKey,
            "Agreement" => $this->agreement
        ];
    }

    public function getBranches()
    {
        $request = wp_remote_get(
            $this->url . '/agencies',
            [
                'method' => 'GET',
                'headers' => $this->setHeaders(),
                'query' => ["pickup_availability" => 1],
                'timeout' => 20,
            ]
        );

        if (is_wp_error($request) || wp_remote_retrieve_response_code($request) != 200) {
            return false;
        }

        return json_decode(wp_remote_retrieve_body($request), true);

    }

    public function getRates()
    {
        $body = [
            "agreement" => "string",
            "deliveryType" => "string",
            "parcels" => [
                [
                    "declaredValue" => "string",
                    "dimensions" => [
                        "depth" => "string",
                        "height" => "string",
                        "width" => "string"
                    ],
                    "weight" => "string"
                ]
            ],
            "senderData" => [
                "zipCode" => "string"
            ],
            "serviceType" => "string",
            "shippingData" => [
                "zipCode" => "string"
            ]
        ];
        $res = $this->client->post('/rates', ['body' => json_encode($body)]);
        $code = $res->getStatusCode();
        if ($code == 200) {
            return $res->json();
        }
        return $code;
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
     * @param $order_id
     * @return array|mixed
     */
    public function registerOrder($order_id)
    {
        $order = wc_get_order($order_id);
        $orderData = $order->get_data();
        $branch_id = $order->get_meta(CORRARFO_CHOSEN_BRANCH);
        $shipping = $orderData['shipping'];
        $billing = $orderData['billing'];
        $items = $order->get_items();
        $shippingMethod = $order->get_shipping_method();
        $isBranch = stripos($shippingMethod, 'sucursal');
        $dataStore = get_option(CORRARFO_SETTINGS);
        $products = array_values(
            array_map(function (WC_Order_Item_Product $item) {
                $qty = max(1, (int) $item->get_quantity());
                $dims = Utils::getOrderItemDimensionsStrict($item);

                $total = (float) $item->get_total();
                $name = $item->get_name();

                $weightKg = (float) $dims['weight'] / 1000.0;

                return [
                    'declaredValue' => $total,
                    'dimensions' => [
                        'depth' => empty($dims['length']) ? 1 : $dims['length'],
                        'height' => empty($dims['height']) ? 1 : $dims['height'],
                        'width' => empty($dims['width']) ? 1 : $dims['width'],
                    ],
                    'productCategory' => $name,
                    'productWeight' => $weightKg * $qty,
                ];
            }, $items)
        );

        if ($isBranch) {
            $branch = $this->getBranch($branch_id);
            $location = $branch['location'];
            $shippingData = [
                "address" => [
                    "cityName" => $location['city_name'] ?? 'CABA',
                    "department" => "",
                    "floor" => "",
                    "state" => "B", // @todo: check this hardcoded value
                    "streetName" => $location['street_name'] ?? 'Dolores',
                    "streetNumber" => $location['street_number'] ?? '27',
                    "zipCode" => self::normalizeZipCode($location['zip_code'] ?? '1407')
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
                    "streetName" => $shipping['address_1'],
                    "streetNumber" => $shipping['address_2'],
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
        $phone = !empty($dataStore['phone_number']) ? explode('-', $dataStore['phone_number']) : ['', ''];
        $senderData = [
            "address" => [
                "cityName" => $dataStore['city_name'],
                "department" => $dataStore['department'],
                "floor" => $dataStore['floor'],
                "state" => $dataStore['state'],
                "streetName" => $dataStore['street_name'],
                "streetNumber" => $dataStore['street_number'],
                "zipCode" => self::normalizeZipCode($dataStore['zip_code']),
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
                "deliveryType" => $isBranch ? "agency" : "homeDelivery",
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

    public function getBranch($branch_id)
    {
        $agencies = json_decode(file_get_contents(CORRARFO_PLUGIN_DIR . 'Mock/agencies.json'), 'true');

        $find = null;

        for ($i = 0; $i < count($agencies); $i++) {
            $data = $agencies[$i];
            if ($branch_id == $data['agency_id']) {
                $find = $data;
                break;
            }
        }

        return $find;
    }

    /**
     * @param $code
     * @return array|string|null
     */
    public static function normalizeZipCode($code)
    {
        return substr(preg_replace('/\D/', '', $code), 0, 4);
    }


}

// Backward compatibility alias
if (!class_exists('Corrarfo_Service')) {
    class_alias(Service::class, 'Corrarfo_Service');
}
