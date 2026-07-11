<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once CORRARFO_PLUGIN_DIR . 'Helper/ShippingAdjustmentsHelper.php';
use Corrarfo\Helper\ShippingAdjustmentsHelper;

if (!class_exists('Corrarfo_Shipping_Method')) {
    class Corrarfo_Shipping_Method extends WC_Shipping_Method
    {

        public $corrarfo_data = [];
        private $generalKey = 0;

        public function __construct($instance_id = 0)
        {


            $this->id = CORRARFO_PLUGIN_ID;
            $this->instance_id = absint($instance_id);
            $this->method_title = __('Correo Argentino', 'correo-argentino-for-woocommerce');
            $this->method_description = __('Official Correo Argentino plugin for WooCommerce', 'correo-argentino-for-woocommerce');
            $this->supports = [
                'settings',
                'shipping-zones',
                'instance-settings',
            ];

            $this->init();

            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        }


        public function init()
        {
            try {
                $this->instance_form_fields = CorreoArgentinoAdminForm::getInstanceBusinessForm();

                global $corrarfo_error;
                if (is_admin()) {
                    $this->handleAdminForms();
                }
                $this->init_settings();

                $this->init_instance_settings();
                $this->title = $this->get_option('title', $this->method_title);

                $this->loadData();
            } catch (Exception $e) {
                if (class_exists('WC_Admin_Settings')) {
                    WC_Admin_Settings::add_error('Error: ' . $e->getMessage());
                }
            }
        }

        private function loadData()
        {
            if (!isset($this->instance_id) && (get_option(CORRARFO_BUSINESS_DETAILS_FILLED) === 0)) {
                throw new Exception("Correo Argentino se encuentra activo y Datos de Negocio no completados");
            }

            $key = null;
            if (!isset($this->instance_id) && (get_option(CORRARFO_BUSINESS_DETAILS_FILLED) === 1)) {
                $key = $this->generalKey;
            }

            if (isset($this->instance_id)) {
                $key = $this->instance_id;
            }

            if (!isset($key)) {
                throw new Exception("Correo Argentino se encuentra activo y no se dispone de la clave para realizar consultas");
            }

            // All forms keys
            $keys = [
                'business_name',
                'email',
                'state',
                'city_name',
                'department',
                'floor',
                'street_name',
                'street_number',
                'zip_code',
                'cellphone',
                'phone',
                'title',
                'metodo_envio',
                'tipo_servicio',
                'observation'
            ];

            $row = [];
            foreach ($keys as $k) {
                $row[strtolower($k)] = $this->get_option($k);
            }
            $this->corrarfo_data[$key] = $row;
        }

        private function handleAdminForms()
        {
            $isConnected = Corrarfo_Utils::isConnected();
            $currentServiceType = Corrarfo_Utils::getCurrentServiceType();

            $this->handleServiceSelectorForm($currentServiceType, $isConnected);
            $this->handleUserValidateMiCorreoForm();
            $this->handleLoginPaqArForm();
            $this->handleRegisterMiCorreoForm();
            $this->handleBusinessForm($isConnected);
        }


        /**
         * environment_check function.
         */
        private function environment_check()
        {
            if (WC()->countries->get_base_country() !== 'AR') {
                echo '
                    <div class="error">
                        <p>' . esc_html__('Correo Argentino. Argentina must be the origin country.', 'correo-argentino-for-woocommerce') . '</p>
                    </div>';
            }
        }

        /**
         * admin_options function.
         */
        public function admin_options()
        {
            // Check users environment supports this method
            $this->environment_check();

            // Show settings
            parent::admin_options();
        }

        private function handleServiceSelectorForm($currentServiceType, $isConnected)
        {
            // Read-only reading of $_GET (nonce non required).
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $section = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $form = isset($_GET['form']) ? sanitize_key(wp_unslash($_GET['form'])) : '';

            if ($page === 'wc-settings' && $section === CORRARFO_PLUGIN_ID && $form === 'service-selector') {
                if (isset($currentServiceType) && $isConnected) {
                    $formType = ($currentServiceType === CORRARFO_PAQ_AR)
                        ? CORRARFO_LOGIN_PAQ_AR_FORM
                        : CORRARFO_USER_VALIDATE_MI_CORREO_FORM;

                    $url = add_query_arg(
                        array(
                            'page' => 'wc-settings',
                            'tab' => 'shipping',
                            'section' => $this->id,
                            'form' => $formType,
                        ),
                        admin_url('admin.php')
                    );
                    wp_safe_redirect($url);
                    exit;
                }

                $this->method_description = __('Now select which service you want to use.', 'correo-argentino-for-woocommerce');
                $this->form_fields = CorreoArgentinoAdminForm::getServiceSelectorForm();
            }
        }

        private function handleUserValidateMiCorreoForm()
        {
            // Read-only reading of $_GET (nonce non required).
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $section = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $form = isset($_GET['form']) ? sanitize_key(wp_unslash($_GET['form'])) : '';

            $is_valid_request = (
                $page === 'wc-settings' &&
                $section === CORRARFO_PLUGIN_ID &&
                $form === sanitize_key(CORRARFO_USER_VALIDATE_MI_CORREO_FORM)
            );

            if ($is_valid_request) {
                if ((0 === (int) $this->instance_id) && Corrarfo_MiCorreoService::isValidToken()) {
                    if (class_exists('WC_Admin_Settings')) {
                        WC_Admin_Settings::add_message(
                            __('You can now operate in MiCorreo. Update this data only if you want to renew the session.', 'correo-argentino-for-woocommerce')
                        );
                    }
                }
                $this->form_fields = CorreoArgentinoAdminForm::getUserValidateMiCorreoForm();
            }
        }

        private function handleLoginPaqArForm()
        {
            // Read-only reading of $_GET (nonce non required).
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $section = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $form = isset($_GET['form']) ? sanitize_key(wp_unslash($_GET['form'])) : '';

            $is_valid_request = (
                $page === 'wc-settings' &&
                $section === CORRARFO_PLUGIN_ID &&
                $form === sanitize_key(CORRARFO_LOGIN_PAQ_AR_FORM)
            );

            if ($is_valid_request) {
                $this->form_fields = CorreoArgentinoAdminForm::getLoginPaqArForm();
            }
        }

        private function handleRegisterMiCorreoForm()
        {
            // Read-only reading of $_GET (nonce non required).
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $section = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $form = isset($_GET['form']) ? sanitize_key(wp_unslash($_GET['form'])) : '';

            $is_valid_request = (
                $page === 'wc-settings' &&
                $section === CORRARFO_PLUGIN_ID &&
                $form === sanitize_key(CORRARFO_BUSINESS_MI_CORREO_FORM)
            );

            if ($is_valid_request) {
                $this->method_description = __('Now add your business information.', 'correo-argentino-for-woocommerce');
                $this->form_fields = CorreoArgentinoAdminForm::getMiCorreoBusinessForm();
            }
        }

        private function handleBusinessForm($isConnected)
        {
            // Read-only reading of $_GET.
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $section = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $form = isset($_GET['form']) ? sanitize_key(wp_unslash($_GET['form'])) : '';

            $is_valid_request = ($page === 'wc-settings' && $section === CORRARFO_PLUGIN_ID && $form === 'business');

            if ($is_valid_request) {
                if (!$isConnected) {
                    $url = add_query_arg(
                        array(
                            'page' => 'wc-settings',
                            'tab' => 'shipping',
                            'section' => $this->id,
                        ),
                        admin_url('admin.php')
                    );
                    wp_safe_redirect($url);
                    exit;
                }

                $this->method_description = __('Now add your business information.', 'correo-argentino-for-woocommerce');
                $this->form_fields = CorreoArgentinoAdminForm::getBusinessForm();
            }
        }

        private function getActiveForm(): array
        {
            if (isset($this->instance_id) && ($this->instance_id > 0) && (count($this->instance_form_fields) > 0)) {
                return $this->instance_form_fields;
            }
            return $this->form_fields;
        }

        public function validate_text_field($key, $value)
        {
            $form = $this->getActiveForm();
            $title = isset($form[$key]['title']) ? (string) $form[$key]['title'] : '';

            $is_required = ($title !== '' && strpos($title, Corrarfo_Utils::REQUIRED_SIGN) !== false);

            if ($is_required && trim((string) $value) === '') {
                $this->add_field_error($key);
                return false;
            }
            return $value;
        }

        public function validate_last_name_field($key, $value)
        {
            $form = $this->getActiveForm();

            $doc_key = 'woocommerce_' . CORRARFO_PLUGIN_ID . '_document_type';
            // Reading in saved settings: WC checks the nonce 'woocommerce-settings'.
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $document_type = isset($_POST[$doc_key]) ? sanitize_key(wp_unslash($_POST[$doc_key])) : '';

            if ($document_type === 'dni') {
                if (is_array($form) && isset($form[$key])) {
                    if ($value === '' || $value === null) {
                        $this->add_field_error($key);
                    }
                }
            }

            return $value;
        }

        public function validate_password_field($key, $value)
        {
            $form = $this->getActiveForm();
            if (isset($form[$key])) {
                // if title ends with $variable
                if (empty($value)) {
                    $this->add_field_error($key);
                }
                // check if password is longer between 6 and 20 characters
                if (strlen($value) < 6) {
                    $this->add_field_error($key, __('The password must be at least 6 characters long', 'correo-argentino-for-woocommerce'));
                }
                if (strlen($value) > 20) {
                    $this->add_field_error($key, __('The password must not exceed 20 characters', 'correo-argentino-for-woocommerce'));
                }
            }
            return $value;
        }

        private function invalid_same_method_type($metodo_envio, $tipo_servicio): bool
        {
            $instance_id = $this->instance_id;
            $my_zone = WC_Shipping_Zones::get_zone_by('instance_id', $instance_id);
            $all_shipping_methods_in_zone = $my_zone->get_shipping_methods(true);
            $corrarfo_shipping_methods_in_zone = array_filter(
                $all_shipping_methods_in_zone,
                function ($shipping_method) use ($instance_id) {
                    return $shipping_method->id == CORRARFO_PLUGIN_ID && $shipping_method->instance_id != $instance_id;
                }
            );

            $corrarfo_shipping_methods_with_same_method_type = array_filter(
                $corrarfo_shipping_methods_in_zone,
                function ($shipping_method) use ($metodo_envio, $tipo_servicio) {
                    $settings = $shipping_method->instance_settings;
                    if ($settings && $settings['metodo_envio'] && $settings['tipo_servicio']) {
                        return $settings['metodo_envio'] == $metodo_envio && $settings['tipo_servicio'] == $tipo_servicio;
                    }
                    return false;
                }
            );
            return $corrarfo_shipping_methods_with_same_method_type && count($corrarfo_shipping_methods_with_same_method_type) > 0;
        }

        public function validate_title_field($key, $value)
        {
            // WooCommerce checks the nonce 'woocommerce-settings' before invoking validators.
            // See: WC_Admin_Settings::save()
            // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
            $metodo_envio = isset($_POST['woocommerce_correoargentino_shipping_method_metodo_envio'])
                ? sanitize_key(wp_unslash($_POST['woocommerce_correoargentino_shipping_method_metodo_envio']))
                : '';

            $tipo_servicio = isset($_POST['woocommerce_correoargentino_shipping_method_tipo_servicio'])
                ? sanitize_key(wp_unslash($_POST['woocommerce_correoargentino_shipping_method_tipo_servicio']))
                : '';
            // phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended

            $has_error = false;

            if ($metodo_envio === '') {
                $this->add_field_error($key, 'El método de envío debe ser seleccionado');
                $has_error = true;
            }
            if ($tipo_servicio === '') {
                $this->add_field_error($key, 'El tipo de servicio debe ser seleccionado');
                $has_error = true;
            }
            if ($has_error) {
                return $value;
            }

            if ($this->invalid_same_method_type($metodo_envio, $tipo_servicio)) {
                $this->add_field_error($key, 'Existe otra instancia de Correo Argentino, con el mismo Tipo de Servicio y Método de envío');
                return false;
            }

            $labelTipoServicio = array(
                'e' => 'expreso',
                'c' => 'clásico',
            );
            $labelMetodoEnvio = array(
                'd' => 'a domicilio',
                's' => 'a sucursal',
            );

            $etiquetaTipo = isset($labelTipoServicio[$tipo_servicio]) ? $labelTipoServicio[$tipo_servicio] : $tipo_servicio;
            $etiquetaEnvio = isset($labelMetodoEnvio[$metodo_envio]) ? $labelMetodoEnvio[$metodo_envio] : $metodo_envio;

            return "Correo Argentino {$etiquetaTipo} {$etiquetaEnvio}";
        }

        private function add_field_error($key, $message = '')
        {
            $form = $this->getActiveForm();

            $title = isset($form[$key]['title']) ? (string) $form[$key]['title'] : ucfirst((string) $key);

            if (isset($form[$key])) {
                $form[$key]['class'] = (isset($form[$key]['class']) ? $form[$key]['class'] . ' ' : '') . 'error';
            }

            if (isset($this->instance_form_fields[$key])) {
                $this->instance_form_fields[$key]['class'] =
                    (isset($this->instance_form_fields[$key]['class']) ? $this->instance_form_fields[$key]['class'] . ' ' : '') . 'error';
            }

            if ($message === '' || $message === null) {
                $message = str_replace(Corrarfo_Utils::REQUIRED_SIGN, '', $title) . ' es requerido';
            }

            if (class_exists('WC_Admin_Settings')) {
                WC_Admin_Settings::add_error(esc_html($message));
            }
            throw new \Exception(esc_html($message));
        }

        public function getDimensions()
        {
            $cart = WC()->cart;
            $packages = $cart->get_shipping_packages();
            $weight = 0;
            $height = 0;
            $restDimensions = [];

            foreach ($packages as $package) {
                foreach ($package['contents'] as $item) {
                    $productId = $item['product_id'];
                    $quantity = $item['quantity'];
                    $product = wc_get_product($productId);
                    $dimensions = Corrarfo_Utils::getProductDimensions($product);

                    if (!is_array($dimensions) || !isset($dimensions['height'], $dimensions['width'], $dimensions['length'], $dimensions['weight'])) {
                        continue;
                    }

                    $weight += $dimensions['weight'] * $quantity;
                    $orderedDimensions = [$dimensions['height'], $dimensions['width'], $dimensions['length']];
                    sort($orderedDimensions);
                    $height += $orderedDimensions[0] * $quantity;

                    for ($i = 0; $i < $quantity; $i++) {
                        $restDimensions[] = [$orderedDimensions[1], $orderedDimensions[2]];
                    }
                }
            }

            $widths = array_column($restDimensions, 0);
            $lengths = array_column($restDimensions, 1);

            $width = max($widths);
            $length = max($lengths);

            return [
                "weight" => $weight,
                "height" => $height,
                "width" => $width,
                "length" => $length
            ];
        }

        private function updateDestinationPostcode()
        {
            $cart = WC()->cart;
            $package = $cart->get_shipping_packages();

            if (
                isset($this->corrarfo_data) &&
                isset($this->corrarfo_data[$this->instance_id]) &&
                isset($this->corrarfo_data[$this->instance_id]['metodo_envio']) &&
                $this->corrarfo_data[$this->instance_id]['metodo_envio'] == 'S'
            ) {
                if ((is_checkout() || (defined('DOING_AJAX') && DOING_AJAX)) && Corrarfo_Utils::getUseRates() && WC()->session->get(CORRARFO_CHOSEN_BRANCH_ZIP_CODE) !== null) {
                    return Corrarfo_Utils::normalizeZipCode(WC()->session->get(CORRARFO_CHOSEN_BRANCH_ZIP_CODE));
                }
            }

            $postcode = isset($package[0]['destination']['postcode']) ? $package[0]['destination']['postcode'] : '';

            // Fork Julian Juan: fallback para cotizar a domicilio aunque el paquete no traiga CP
            // (p. ej. con ship_to_destination='billing', o en pasos intermedios del checkout).
            if ('' === $postcode && WC()->customer) {
                $postcode = WC()->customer->get_shipping_postcode();
                if ('' === $postcode) {
                    $postcode = WC()->customer->get_billing_postcode();
                }
            }

            return $postcode;
        }

        private function getMostValuedProductInCart()
        {
            $cart = WC()->cart;
            $packages = $cart->get_shipping_packages();
            $maxVolumetricWeight = 0;
            $mostValuedProduct = null;

            foreach ($packages as $package) {
                foreach ($package['contents'] as $item) {
                    $productId = $item['product_id'];
                    $product = wc_get_product($productId);

                    $dimensions = Corrarfo_Utils::getProductDimensions($product);
                    $volumetricWeight = ($dimensions["length"] * $dimensions["width"] * $dimensions["height"]);

                    if ($volumetricWeight > $maxVolumetricWeight) {
                        $maxVolumetricWeight = $volumetricWeight;
                        $mostValuedProduct = $product;
                    }
                }
            }

            return $mostValuedProduct;
        }

        private function getMaxHeightIndividual()
        {
            $heights = [];

            $cart = WC()->cart;
            $packages = $cart->get_shipping_packages();

            foreach ($packages as $package) {
                foreach ($package['contents'] as $item) {
                    $product = wc_get_product($item['product_id']);
                    $quantity = $item['quantity'];
                    $dimensions = Corrarfo_Utils::getProductDimensions($product);

                    if (
                        is_array($dimensions) &&
                        isset($dimensions['height'], $dimensions['width'], $dimensions['length']) &&
                        is_numeric($dimensions['height']) &&
                        is_numeric($dimensions['width']) &&
                        is_numeric($dimensions['length'])
                    ) {
                        $ordered = [$dimensions['height'], $dimensions['width'], $dimensions['length']];
                        sort($ordered);
                        for ($i = 0; $i < $quantity; $i++) {
                            $heights[] = $ordered[0];
                        }
                    }
                }
            }

            return empty($heights) ? 0 : max($heights);
        }

        private function validateProductDimensions($dimensions): bool
        {

            $maxWeight = Corrarfo_Utils::getCurrentServiceType() == CORRARFO_PAQ_AR ? CORRARFO_MAX_WEIGHT_PAQ_AR : CORRARFO_MAX_WEIGHT_MI_CORREO;
            $maxLength = Corrarfo_Utils::getCurrentServiceType() == CORRARFO_PAQ_AR ? CORRARFO_MAX_LENGTH_PAQ_AR : CORRARFO_MAX_LENGTH_MI_CORREO;
            $maxVolumetric = Corrarfo_Utils::getCurrentServiceType() == CORRARFO_PAQ_AR ? CORRARFO_MAX_VOLUMETRIC_DIMENSIONS_PAQ_AR : CORRARFO_MAX_VOLUMETRIC_DIMENSIONS_MI_CORREO;
            $maxStackedHeight = Corrarfo_Utils::getCurrentServiceType() == CORRARFO_PAQ_AR ? CORRARFO_MAX_STACKED_HEIGHT_PAQ_AR : CORRARFO_MAX_STACKED_HEIGHT_MI_CORREO;

            $maxHeight = $this->getMaxHeightIndividual();

            if (
                $dimensions["weight"] > $maxWeight ||
                $dimensions["length"] > $maxLength ||
                ($dimensions["length"] + $dimensions["width"] + $maxHeight) > $maxVolumetric ||
                $dimensions["height"] > $maxStackedHeight
            ) {
                return false;
            }
            return true;
        }

        private function fetchRates($service, $postalCode, $dimensions, $settings = null, $instance_id = null)
        {
            $cache_key = md5(serialize([$postalCode, $dimensions, $settings]));
            $old_key = WC()->session->get('rateListKey_' . $instance_id);
            if ($old_key != $cache_key) {
                WC()->session->set('rateList_' . $instance_id, null);
            }

            $cached = WC()->session->get('rateList_' . $instance_id);

            if ($cached) {
                return $cached;
            }

            $rateList = [];

            if ('' == $postalCode) {
                return;
            }

            $ratesHome = $service->getRates($postalCode, 'D', $dimensions, $settings);

            $ratesHomeFiltered = [];

            if (isset($ratesHome["rates"]) && is_array($ratesHome["rates"])) {
                $ratesHomeFiltered = array_filter($ratesHome["rates"], function ($rate) {
                    return isset($rate['productName']) && $rate['productName'] !== "Correo Argentino Hoy";
                });
            }

            $ratesHome["rates"] = $ratesHomeFiltered;

            $ratesAgency = $service->getRates($postalCode, 'S', $dimensions, $settings);

            if (!isset($ratesHome["rates"]) || !isset($ratesAgency["rates"])) {
                return $rateList;
            }

            $rates = [
                "rates" => array_merge($ratesHome["rates"], $ratesAgency["rates"])
            ];

            if (!empty($rates['rates'])) {
                foreach ($rates['rates'] as $i => $rate) {
                    if ($rate && $rate['productName']) {
                        $branchLabel = $rate['deliveredType'] === 'S' ? '_branch' : '';
                        $rateList[] = [
                            "ID" => md5($rate['productName'] . $rate['deliveredType']) . $branchLabel,
                            "serviceName" => $rate['productName'],
                            "description" => $rate['productName'],
                            "serviceCode" => $rate['productType'],
                            'deliveredType' => $rate['deliveredType'],
                            "currency" => "ARS",
                            "totalPrice" => (float) $rate['price']
                        ];
                    }
                }
            }

            WC()->session->set('rateList_' . $instance_id, $rateList);
            WC()->session->set('rateListKey_' . $instance_id, $cache_key);
            return $rateList;
        }

        private function getElementById($array, $id)
        {
            foreach ($array as $index => $element) {
                if (isset($element['ID']) && $element['ID'] == $id) {
                    return ['element' => $element, 'index' => $index];
                }
            }
            return null;
        }


        public function calculate_rates($package = array())
        {
            if (!Corrarfo_Utils::canListRates($this->corrarfo_data[$this->instance_id])) {
                return [];
            }

            $postalCode = $this->updateDestinationPostcode();
            if (!$postalCode || !isset($this->corrarfo_data[$this->instance_id])) {
                return [];
            }

            $weight = 0;

            foreach ($package['contents'] as $values) {
                $product = $values['data'];
                $dims = Corrarfo_Utils::getProductDimensions($product); // ya contempla valores por defecto si están activados

                $useDefaults = get_option('corrarfo_default_enabled') === '1';
                if (!$useDefaults && $dims['weight'] <= 0) {
                    return [];
                }

                $weight += $dims['weight'] * (int) $values['quantity'];

            }

            if ($weight <= 0) {
                return [];
            }

            $dimensions = $this->getDimensions();
            $dimensions['weight'] = $weight;

            if (!$this->validateProductDimensions($dimensions)) {
                return [];
            }

            // Fork Julian Juan: si la API de Correo Argentino falla (token
            // inválido, 401, timeout, etc.) NO debe romper el carrito/checkout.
            // Se captura cualquier error y se devuelve [] (no ofrece tarifa) en
            // vez de propagar un fatal 500.
            try {
                $service = (new Corrarfo_ServiceFactory())->get();

                $rates = $this->fetchRates(
                    $service,
                    $postalCode,
                    $dimensions,
                    $this->corrarfo_data[$this->instance_id],
                    $this->instance_id
                );

                if (!$rates) {
                    return [];
                }

                $ratesArray = [];
                foreach ($rates as $rate) {
                    $key = $rate['serviceCode'] . $rate['deliveredType'];
                    $ratesArray[$key] = $rate;
                }

                return $ratesArray;
            } catch (\Throwable $e) {
                if (function_exists('wc_get_logger')) {
                    wc_get_logger()->warning(
                        'Correo Argentino: no se pudieron calcular tarifas — ' . $e->getMessage(),
                        ['source' => 'correo-argentino']
                    );
                }
                return [];
            }
        }


        public function calculate_shipping($package = array())
        {
            if (!$this->hasValidWeight($package)) {
                return;
            }

            if (!$this->isValidDimensions($package)) {
                $this->add_rate([
                    'id' => $this->id . '_excess_dimensions',
                    'label' => __('One or more packages exceed the maximum weight and/or dimensions allowed by Correo Argentino', 'correo-argentino-for-woocommerce'),
                    'cost' => 0,
                    'package' => $package,
                ]);
                return;
            }

            if (!Corrarfo_Utils::canListRates($this->corrarfo_data[$this->instance_id]) || !$this->updateDestinationPostcode()) {
                $this->add_rate([
                    'id' => $this->id . '_direccion_incompleta',
                    'label' => __('To get the shipping cost by Correo Argentino, complete the address details', 'correo-argentino-for-woocommerce'),
                    'cost' => 0,
                    'package' => $package,
                ]);
                return;
            }

            $tipo_servicio = $this->get_option('tipo_servicio');
            $metodo_envio = $this->get_option('metodo_envio');

            if ($tipo_servicio === CORRARFO_PAQ_AR) {
                $this->add_rate([
                    'id' => $this->id . '_' . $this->instance_id,
                    'label' => $this->title,
                    'cost' => '',
                    'calc_tax' => '',
                    'meta_data' => array(
                        CORRARFO_IS_BRANCH => $metodo_envio === 'S',
                        CORRARFO_CHOSEN_SERVICE_TYPE => $tipo_servicio,
                        CORRARFO_CHOSEN_PRODUCT_TYPE => null,
                        CORRARFO_CHOSEN_BRANCH => null,
                        CORRARFO_CHOSEN_BRANCH_NAME => null,
                        CORRARFO_CHOSEN_BRANCH_ZIP_CODE => null
                    )
                ]);
                return;
            }

            $ratesArray = $this->calculate_rates($package);
            if (empty($ratesArray)) {
                return;
            }

            $metodo_envio = $this->get_option('metodo_envio');
            $tipo_servicio = $this->get_option('tipo_servicio');

            $key = $tipo_servicio . 'P' . $metodo_envio;
            if (isset($ratesArray[$key])) {
                $rate = $ratesArray[$key];
                $cartSubtotal = WC()->cart ? WC()->cart->subtotal : 0;
                $rate['totalPrice'] = ShippingAdjustmentsHelper::applyAdjustment($rate['totalPrice'], $cartSubtotal);

                require_once CORRARFO_PLUGIN_DIR . 'Hooks/correoargentino-free-shipping-hook.php';
                $rate['totalPrice'] = corrarfo_process_free_shipping($rate['totalPrice'], $cartSubtotal);

                $this->add_rate([
                    'id' => $this->id . '_' . $this->instance_id,
                    'label' => $this->title,
                    'cost' => $rate['totalPrice'],
                    'calc_tax' => '',
                    'meta_data' => array(
                        CORRARFO_IS_BRANCH => $rate['deliveredType'] === 'S',
                        CORRARFO_CHOSEN_SERVICE_TYPE => Corrarfo_Utils::getCurrentServiceType(),
                        CORRARFO_CHOSEN_PRODUCT_TYPE => $rate['serviceCode'],
                        CORRARFO_CHOSEN_BRANCH => null,
                        CORRARFO_CHOSEN_BRANCH_NAME => null,
                        CORRARFO_CHOSEN_BRANCH_ZIP_CODE => null
                    )
                ]);
            }
        }


        private function isValidDimensions($package): bool
        {
            $weight = 0;

            foreach ($package['contents'] as $values) {
                $product = $values['data'];
                $dims = Corrarfo_Utils::getProductDimensions($product);

                $useDefaults = get_option('corrarfo_default_enabled') === '1';
                if (!$useDefaults && $dims['weight'] <= 0) {
                    return false;
                }

                $weight += $dims['weight'] * (int) $values['quantity'];
            }

            if ($weight <= 0) {
                return false;
            }

            $dimensions = $this->getDimensions();
            $dimensions['weight'] = $weight;

            return $this->validateProductDimensions($dimensions);
        }

        private function hasValidWeight($package): bool
        {
            $useDefaults = get_option('corrarfo_default_enabled') === '1';
            $weight = 0;

            foreach ($package['contents'] as $item) {
                $product = $item['data'];
                $dims = Corrarfo_Utils::getProductDimensions($product);
                $weight += $dims['weight'] * (int) $item['quantity'];
            }

            if ($weight > 0) {
                return true;
            }

            return $useDefaults;
        }

    }
}
