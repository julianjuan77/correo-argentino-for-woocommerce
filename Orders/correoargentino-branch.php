<?php
namespace Corrarfo\Orders;

use Corrarfo\Lib\Utils;
use Corrarfo\Service\ServiceFactory;
use WC_Shipping_Rate;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WooCommerce Order Metabox's base Class
 */
class Branch
{

    /**
     * Method called by action woocommerce_after_shipping_rate.
     * 
     */
    public static function showBranches(WC_Shipping_Rate $method)
    {
        $shippingSelectIds = WC()->session->get('chosen_shipping_methods');
        $shippingSelected = reset($shippingSelectIds);

        $isCAShippingMethod = Utils::isCAShippingMethod($shippingSelected);
        if (!$isCAShippingMethod) {
            Utils::unset_wc_chosen_branch_values();
            return;
        }

        $id = $method->get_id();
        $isCAParamMethod = Utils::isCAShippingMethod($id);
        if (!$isCAParamMethod) {
            return;
        }

        $is_branch = Utils::isBranch($shippingSelected);
        if (!$is_branch) {
            Utils::unset_wc_chosen_branch_values();
            return;
        }

        if ( $is_branch && in_array( $id, $shippingSelectIds, true ) && ( is_cart() || is_checkout() ) ) {

            $provinces        = Utils::getProvinces();
            $shipping_address = WC()->customer->get_shipping();
            $shipping_state   = WC()->session->get( CORRARFO_CHOSEN_PROVINCE_CODE ) ?? ( $shipping_address['state'] ?? '' );

            echo '<li>';
            echo '<div id="corrarfo_container_select_provinces">';
            echo '<select class="correoargentino_province_select">';
            printf(
                '<option value="%s">%s</option>',
                esc_attr( CORRARFO_NONE ),
                esc_html( CORRARFO_PLEASE_SELECT_A_DESTINATION_PROVINCE )
            );

            foreach ( $provinces as $code => $name ) {
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr( (string) $code ),
                    selected( (string) $shipping_state, (string) $code, false ),
                    esc_html( (string) $name )
                );
            }

            echo '</select>';
            echo '</div>';
            echo '</li>';

            $arr          = $method->get_meta_data();
            $product_type = isset( $arr[ CORRARFO_CHOSEN_PRODUCT_TYPE ] ) ? (string) $arr[ CORRARFO_CHOSEN_PRODUCT_TYPE ] : '';

            echo '<li>';
            printf(
                '<div id="%s">',
                esc_attr( 'corrarfo_container_select_' . absint( $id ) )
            );
            printf(
                '<select disabled="disabled" id="%s" class="correoargentino_branch_select" name="corrarfo_branch_select" data-product-type="%s"></select>',
                esc_attr( 'corrarfo_branch_select_' . absint( $id ) ),
                esc_attr( $product_type )
            );
            printf(
                '<input type="hidden" name="%s" id="%s" value="%s" />',
                esc_attr( CORRARFO_BRANCH_ZIP_CODE_HIDDEN_FIELD ),
                esc_attr( CORRARFO_BRANCH_ZIP_CODE_HIDDEN_FIELD ),
                esc_attr( CORRARFO_NONE )
            );
            echo '</div>';
            echo '</li>';

            WC()->session->set( CORRARFO_CHOSEN_PRODUCT_TYPE, $product_type );
        }
    }


    /**
     * @return array
     */
    public static function listBranches()
    {
        $provinceCode = WC()->session->get(CORRARFO_CHOSEN_PROVINCE_CODE);
        $product_type = WC()->session->get(CORRARFO_CHOSEN_PRODUCT_TYPE);
    
        $service = (new ServiceFactory())->get();
        $serviceType = Utils::getCurrentServiceType();
        $customerId = Utils::getCustomerId();
    
        $options = $serviceType == CORRARFO_MI_CORREO
            ? [
                "provinceCode" => $provinceCode,
                "customerId"   => $customerId,
            ]
            : [
                "stateId" => $provinceCode,
            ];
    
        $agencies = $service->getBranches($options);
        $results = [];
    
        if (!empty($agencies) && is_array($agencies)) {
            $results[] = [
                "id"                          => CORRARFO_NONE,
                "text"                        => CORRARFO_CHOOSE_DESTINATION_AGENCY,
                "agency_name"                => CORRARFO_NONE,
                "agency_city"                => CORRARFO_NONE,
                "agency_state"               => CORRARFO_NONE,
                "agency_street_name"         => CORRARFO_NONE,
                "agency_street_number"       => CORRARFO_NONE,
                "agency_zip_code"            => CORRARFO_NONE,
                "agency_chosen_product_type" => CORRARFO_NONE,
                "options"                    => $options
            ];
    
            foreach ($agencies as $value) {
                $text = self::getText($value);
                $results[] = self::getResults($value, $text, $product_type);
            }
        }
    
        return $results;
    }
    

    public static function getText($value)
    {
        $serviceType = Utils::getCurrentServiceType();
        $separator = ', ';
        $state = $serviceType == CORRARFO_PAQ_AR ? $value['location']['state_name'] : $value['location']['address']['province'];
        $city = $serviceType == CORRARFO_PAQ_AR ? $value['location']['city_name'] : $value['location']['address']['city'];
        $agency = $serviceType == CORRARFO_PAQ_AR ? $value['agency_name'] : $value['name'];
        $streetName = $serviceType == CORRARFO_PAQ_AR ? $value['location']['street_name'] : $value['location']['address']['streetName'];
        $streetNumber = $serviceType == CORRARFO_PAQ_AR ? $value['location']['street_number'] : $value['location']['address']['streetNumber'];
        $zipCode = $serviceType == CORRARFO_PAQ_AR ? $value['location']['zip_code'] : $value['location']['address']['postalCode'];

        return join(
            $separator,
            array(
                trim($state ?? ''),
                trim($city ?? ''),
                trim($agency ?? ''),
                trim($streetName ?? ''),
                trim($streetNumber ?? ''),
                "(" . trim($zipCode ?? '') . ")"
            ),
        );
    }

    public static function getResults($value, $text, $product_type)
    {
        $serviceType = Utils::getCurrentServiceType();
        $id = $serviceType == CORRARFO_PAQ_AR ? $value['agency_id'] : $value['code'];
        $agencyName = $serviceType == CORRARFO_PAQ_AR ? $value['agency_name'] : $value['name'];
        $agencyCity = $serviceType == CORRARFO_PAQ_AR ? $value['location']['city_name'] : $value['location']['address']['city'];
        $agencyState = $serviceType == CORRARFO_PAQ_AR ? $value['location']['state_name'] : $value['location']['address']['province'];
        $agencyStreetName = $serviceType == CORRARFO_PAQ_AR ? $value['location']['street_name'] : $value['location']['address']['streetName'];
        $agencyStreetNumber = $serviceType == CORRARFO_PAQ_AR ? $value['location']['street_number'] : $value['location']['address']['streetNumber'];
        $agencyZipCode = $serviceType == CORRARFO_PAQ_AR ? $value['location']['zip_code'] : $value['location']['address']['postalCode'];

        return [
            "id" => trim($id),
            "text" => trim($text ?? ''),
            "agency_name" => trim($agencyName ?? ''),
            "agency_city" => trim($agencyCity ?? ''),
            "agency_state" => trim($agencyState ?? ''),
            "agency_zip_code" => trim($agencyZipCode ?? ''),
            "agency_street_name" => trim($agencyStreetName ?? ''),
            "agency_street_number" => trim($agencyStreetNumber ?? ''),
            "agency_chosen_product_type" => $product_type
        ];
    }

    /**
     * @param $branches
     * @return string
     */
    public static function buildOptions($branches)
    {
        $options = '';
        if (!empty($branches)) {
            foreach ($branches as $item) {
                $id = $item['id'];
                $text = $item['text'];
                $agency_name = $item["agency_name"];
                $agency_city = $item["agency_city"];
                $agency_street_name = $item["agency_street_name"];
                $agency_street_number = $item["agency_street_number"];
                $agency_state = $item["agency_state"];
                $agency_zip_code = $item["agency_zip_code"];
                // Fork Julian Juan: escapar todos los valores que vienen de la
                // API en atributos/texto del <option> (defensa contra XSS si la
                // respuesta de agencias contiene comillas o markup).
                $options .= '<option value="' . esc_attr($id) . '" data-branch-name="' . esc_attr($agency_name) . '" data-branch-zip-code="' . esc_attr($agency_zip_code) . '" data-branch-city="' . esc_attr($agency_city) . '"  data-branch-state="' . esc_attr($agency_state) . '" data-branch-street-name="' . esc_attr($agency_street_name) . '" data-branch-street-number="' . esc_attr($agency_street_number) . '">' . esc_html($text) . '</option>';
            }
        }
        return $options;
    }
}

// Backward compatibility alias
if (!class_exists('CorreoArgentinoBranch')) {
    class_alias(Branch::class, 'CorreoArgentinoBranch');
}
