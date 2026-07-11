<?php

if (!defined('ABSPATH')) {
    exit;
}

const CORRARFO_PLUGIN_ID = 'correoargentino_shipping_method';
const CORRARFO_IS_BRANCH = '_is_branch';
const CORRARFO_PLUGIN_NAMESPACE = 'woocommerce_' . CORRARFO_PLUGIN_ID;
const CORRARFO_IS_FIRST_TIME = CORRARFO_PLUGIN_NAMESPACE . '_is_first_time';
const CORRARFO_SETTINGS = CORRARFO_PLUGIN_NAMESPACE . '_settings';
const CORRARFO_SERVICE_TYPE = CORRARFO_PLUGIN_NAMESPACE . '_service_type';
const CORRARFO_USE_RATES = CORRARFO_PLUGIN_NAMESPACE . '_use_rates';
const CORRARFO_IS_CONNECTED = CORRARFO_PLUGIN_NAMESPACE . '_is_connected';
const CORRARFO_BUSINESS_DETAILS_FILLED = CORRARFO_PLUGIN_NAMESPACE . '_business_details_filled';
const CORRARFO_AGREEMENT_FIELD = CORRARFO_PLUGIN_NAMESPACE . '_agreement';
const CORRARFO_APIKEY_FIELD = CORRARFO_PLUGIN_NAMESPACE . '_api_key';
const CORRARFO_SANDBOX_FIELD = CORRARFO_PLUGIN_NAMESPACE . '_sandbox';
const CORRARFO_SERVICE_TYPE_FIELD = CORRARFO_PLUGIN_NAMESPACE . '_service_type';
const CORRARFO_CREDENTIALS = CORRARFO_PLUGIN_NAMESPACE . '_credentials';

// Chosen Address Values
const CORRARFO_CHOSEN_BRANCH = '_correoargentino_chosen_branch';
const CORRARFO_CHOSEN_PROVINCE_CODE = '_correoargentino_chosen_province_code';
const CORRARFO_CHOSEN_BRANCH_NAME = '_correoargentino_chosen_branch_name';
const CORRARFO_CHOSEN_BRANCH_STREET_NAME = '_correoargentino_chosen_branch_street_name';
const CORRARFO_CHOSEN_BRANCH_STREET_NUMBER = '_correoargentino_chosen_branch_street_number';
const CORRARFO_CHOSEN_BRANCH_ZIP_CODE = '_correoargentino_chosen_branch_zip_code';
const CORRARFO_CHOSEN_BRANCH_CITY = '_correoargentino_chosen_branch_city';
const CORRARFO_CHOSEN_BRANCH_STATE = '_correoargentino_chosen_branch_state';
const CORRARFO_CHOSEN_PRODUCT_TYPE = '_correoargentino_chosen_product_type';
const CORRARFO_CHOSEN_SERVICE_TYPE = '_correoargentino_chosen_service_type';

// Previous Address Values
const CORRARFO_CUSTOMER_SHIPPING_CITY = '_correoargentino_customer_shipping_city';
const CORRARFO_CUSTOMER_SHIPPING_STATE = '_correoargentino_customer_shipping_state';
const CORRARFO_CUSTOMER_SHIPPING_POSTCODE = '_correoargentino_customer_shipping_postcode';
const CORRARFO_CUSTOMER_SHIPPING_COMPANY = '_correoargentino_customer_shipping_company';
const CORRARFO_CUSTOMER_SHIPPING_ADDRESS_1 = '_correoargentino_customer_shipping_address_1';
const CORRARFO_CUSTOMER_SHIPPING_ADDRESS_2 = '_correoargentino_customer_shipping_address_2';

const CORRARFO_TRACKING_NUMBER = '_correoargentino_tracking_number';
const CORRARFO_ORDER_API_ERROR = '_correoargentino_order_api_error';

const CORRARFO_TRACKING_URL = 'https://www.correoargentino.com.ar/formularios/e-commerce?id=';
const CORRARFO_PAQ_AR_API_URL = 'https://api.correoargentino.com.ar/paqar/v1';
const CORRARFO_PAQ_AR_API_SANDBOX_URL = 'https://apitest.correoargentino.com.ar/paqar/v1';
const CORRARFO_MI_CORREO_API_URL = 'https://api.correoargentino.com.ar/micorreo/v1';
const CORRARFO_MI_CORREO_API_SANDBOX_URL = 'https://apitest.correoargentino.com.ar/micorreo/v1';
const CORRARFO_BUSINESS_DETAILS_FORM_URL = 'admin.php?page=wc-settings&tab=shipping&section=correoargentino_shipping_method&form=business';

/*
 * Credenciales de partner de la API Mi Correo.
 * Fork Julian Juan: se pasan de `const` fijas a `define()` con guardia
 * `defined()`, para poder sobreescribirlas desde wp-config.php cuando las de
 * fábrica del plugin queden inválidas (401). Ej. en wp-config.php:
 *   define( 'CORRARFO_USERNAME_MI_CORREO_PRODUCTION', 'usuario-real' );
 *   define( 'CORRARFO_PASSWORD_MI_CORREO_PRODUCTION', 'clave-real' );
 */
defined('CORRARFO_USERNAME_MI_CORREO_PRODUCTION') || define('CORRARFO_USERNAME_MI_CORREO_PRODUCTION', 'WOOCOMMERCE');
defined('CORRARFO_PASSWORD_MI_CORREO_PRODUCTION') || define('CORRARFO_PASSWORD_MI_CORREO_PRODUCTION', 'Paneles55+');
defined('CORRARFO_USERNAME_MI_CORREO_SANDBOX') || define('CORRARFO_USERNAME_MI_CORREO_SANDBOX', 'WOCOMMERCE-TEST');
defined('CORRARFO_PASSWORD_MI_CORREO_SANDBOX') || define('CORRARFO_PASSWORD_MI_CORREO_SANDBOX', 'Espada-42');
const CORRARFO_USER_VALIDATE_MI_CORREO_FORM = 'user-validate-mi-correo-form';
const CORRARFO_LOGIN_MI_CORREO_FORM = 'login-mi-correo-form';
const CORRARFO_BUSINESS_MI_CORREO_FORM = 'business-mi-correo-form';
const CORRARFO_BUSINESS_DETAILS_FORM = 'business';
const CORRARFO_LOGIN_PAQ_AR_FORM = 'login-paq-ar-form';
const CORRARFO_ERROR_ON_CONNECTING_TO_MI_CORREO = "Ha ocurrido un error al conectarse a MiCorreo. Pruebe nuevamente y verifique que los datos de conexión sean correctos";
const CORRARFO_SUCCESS_ON_CONNECTING_TO_MI_CORREO = "Listo, ya podés operar en MiCorreo";
const CORRARFO_ERROR_ON_CONNECTING_TO_PAQ_AR = "Ha ocurrido un error al conectarse a Paq.Ar. Pruebe nuevamente y verifique que los datos de conexión sean correctos";
const CORRARFO_SUCCESS_ON_CONNECTING_TO_PAQ_AR = "Listo, ya podés operar en Paq.Ar";
const CORRARFO_SUCCESS_ON_REGISTER_TO_MI_CORREO = "Listo, la cuenta ha sido creada";
const CORRARFO_ERROR_ON_REGISTER_TO_MI_CORREO = "Ha ocurrido un error al registrarse a MiCorreo";
const CORRARFO_NO_ACCESS_TOKEN_MESSAGE = 'Necesitas actualizar los datos de tu sesión';
const CORRARFO_BUSINESS_DETAILS_NOT_FILLED_MESSAGE = "Hola, para poder operar con <b>Correo Argentino</b> es necesario que configures todos los datos del comercio requeridos <a href='" . CORRARFO_BUSINESS_DETAILS_FORM_URL . "'>aquí</a>";
const CORRARFO_CHOOSE_DESTINATION_AGENCY = 'Seleccioná la sucursal destino';
// Business Form fields
const CORRARFO_DOCUMENT_TYPE_FIELD = CORRARFO_PLUGIN_NAMESPACE . '_document_type';
const CORRARFO_DOCUMENT_ID_FIELD = CORRARFO_PLUGIN_NAMESPACE . '_document_id';
const CORRARFO_FIRST_NAME_FIELD = CORRARFO_PLUGIN_NAMESPACE . '_first_name';
const CORRARFO_LAST_NAME_FIELD = CORRARFO_PLUGIN_NAMESPACE . '_last_name';
const CORRARFO_EMAIL_FIELD = CORRARFO_PLUGIN_NAMESPACE . '_email';
const CORRARFO_PASSWORD_FIELD = CORRARFO_PLUGIN_NAMESPACE . '_password';
const CORRARFO_STREET_NAME_FIELD = CORRARFO_PLUGIN_NAMESPACE . '_street_name';
const CORRARFO_STREET_NUMBER_FIELD = CORRARFO_PLUGIN_NAMESPACE . '_street_number';
const CORRARFO_FLOOR_FIELD = CORRARFO_PLUGIN_NAMESPACE . '_floor';
const CORRARFO_DEPARTMENT_FIELD = CORRARFO_PLUGIN_NAMESPACE . '_department';
const CORRARFO_CITY_NAME_FIELD = CORRARFO_PLUGIN_NAMESPACE . '_city_name';
const CORRARFO_STATE_CODE_FIELD = CORRARFO_PLUGIN_NAMESPACE . '_state_code';
const CORRARFO_ZIP_CODE_FIELD = CORRARFO_PLUGIN_NAMESPACE . '_zip_code';
const CORRARFO_PHONE_FIELD = CORRARFO_PLUGIN_NAMESPACE . '_phone';
const CORRARFO_CELLPHONE_FIELD = CORRARFO_PLUGIN_NAMESPACE . '_cellphone';
const CORRARFO_CUSTOMER_ID_FIELD = CORRARFO_PLUGIN_NAMESPACE . '_customer_id';
const CORRARFO_BRANCH_ZIP_CODE_HIDDEN_FIELD = CORRARFO_PLUGIN_NAMESPACE . "_branch_zip_code_hidden";
const CORRARFO_FORM_SUBMIT = 'save';
const CORRARFO_SERVICE_SELECTOR = 'service-selector';
const CORRARFO_PLEASE_SELECT_A_DESTINATION_PROVINCE = 'Seleccioná la provincia destino';
const CORRARFO_MAX_WEIGHT_PAQ_AR = 30000; // 30kg
const CORRARFO_MAX_LENGTH_PAQ_AR = 150;   // 150cm
const CORRARFO_MAX_VOLUMETRIC_DIMENSIONS_PAQ_AR = 250; // 250cm
const CORRARFO_MAX_STACKED_HEIGHT_PAQ_AR = 200; // limit sum of smaller sides 200cm
const CORRARFO_MAX_WEIGHT_MI_CORREO = 50000; // 50kg
const CORRARFO_MAX_LENGTH_MI_CORREO = 200;   // 200cm
const CORRARFO_MAX_VOLUMETRIC_DIMENSIONS_MI_CORREO = 300; // 300cm
const CORRARFO_MAX_STACKED_HEIGHT_MI_CORREO = 200; // limit sum of smaller sides 200cm
// Isolated strings
const CORRARFO_STREET_NUMBER = 'Altura, piso y departamento';
const CORRARFO_PAQ_AR = "paqar";
const CORRARFO_ORDER = "Pedido";
const CORRARFO_TRACKING = 'Tracking';
const CORRARFO_SHIPPING_TYPE = 'Tipo de envío';
const CORRARFO_STATUS = 'Estatus';
const CORRARFO_DATE = 'Fecha';
const CORRARFO_MI_CORREO = "micorreo";
const CORRARFO_BRANCH = 'Sucursal';
const CORRARFO_AGENCY = 'agency';
const CORRARFO_HOME = 'Domicilio';
const CORRARFO_SHIPPING_AGENCY = 'Sucursal';
const CORRARFO_HOME_DELIVERY = 'homeDelivery';
const CORRARFO_NONE = 'NONE';
const CORRARFO_CREATE_NEW_ACCOUNT = 'Creá una nueva';
const CORRARFO_DOESNT_HAVE_ACCOUNT_YET = '¿No tenés una aún?';
const CORRARFO_MIN_PRODUCT_WEIGHT = 1;
const CORRARFO_MIN_PRODUCT_HEIGHT = 10;
const CORRARFO_MIN_PRODUCT_WIDTH = 10;
const CORRARFO_MIN_PRODUCT_LENGTH = 10;
const CORRARFO_REFERENCE = 'Referencia';
const CORRARFO_WC_SHIPPING_DEBUG_MODE = 'woocommerce_shipping_debug_mode';
const CORRARFO_WC_SHIPPING_TO_DESTINATION = 'woocommerce_ship_to_destination';
// Hard Uninstall option variables
const CORRARFO_OPTION_REMOVE_ON_UNINSTALL = 'corrarfo_remove_on_uninstall';
const CORRARFO_OPTION_AUTOSEND_ENABLED = 'corrarfo_auto_send_enabled';
const CORRARFO_OPTION_AUTOSEND_TRIGGER = 'corrarfo_auto_send_trigger_status';
const CORRARFO_OPTION_AUTOSTATUS_ENABLED = 'corrarfo_autostatus_enabled';
const CORRARFO_OPTION_AUTOSTATUS_STATUS = 'corrarfo_autostatus_status';
const CORRARFO_OPTION_DEFAULT_ENABLED = 'corrarfo_default_enabled';
const CORRARFO_OPTION_DEFAULT_HEIGHT = 'corrarfo_default_height';
const CORRARFO_OPTION_DEFAULT_LENGTH = 'corrarfo_default_length';
const CORRARFO_OPTION_DEFAULT_WIDTH = 'corrarfo_default_width';
const CORRARFO_OPTION_DEFAULT_WEIGHT = 'corrarfo_default_weight';
const CORRARFO_OPTION_FREE_SHIPPING_ENABLED = 'corrarfo_free_shipping_enabled';
const CORRARFO_OPTION_FREE_SHIPPING_AMOUNT = 'corrarfo_free_shipping_amount';
const CORRARFO_OPTION_PREFIX_ENABLED = 'corrarfo_prefix_enabled';
const CORRARFO_OPTION_PREFIX_VALUE = 'corrarfo_prefix_value';
const CORRARFO_OPTION_SHIPPING_ENABLE = 'corrarfo_shipping_enable';
const CORRARFO_OPTION_SHIPPING_MIN_TOTAL = 'corrarfo_shipping_min_total';
const CORRARFO_OPTION_SHIPPING_PERCENTAGE = 'corrarfo_shipping_percentage';
const CORRARFO_OPTION_SHIPPING_TYPE = 'corrarfo_shipping_type';
