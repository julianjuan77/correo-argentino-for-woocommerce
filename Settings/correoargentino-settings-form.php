<?php
namespace Corrarfo\Settings;

use Corrarfo\Lib\Utils;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Correo Argentino Admin Form
 */
class AdminForm
{

    public const REDIRECT_FORM_URL_BASE = '?page=wc-settings&tab=shipping&section=correoargentino_shipping_method&form=';

    /**
     * Checks system requirements
     *
     * @return array
     */
    public static function getLoginPaqArForm(): array
    {
        $credentials = Utils::getCredentials();

        $fields = [
            'form_id' => self::getFormFieldID('wc-correoargentino-form-id', 'paq-ar', 'log-in'),
            'agreement' => [
                'id' => 'agreement',
                'type' => 'text',
                'title' => __('Agree', 'correo-argentino-for-woocommerce') . Utils::REQUIRED_SIGN,
                'default' => isset($credentials['agreement']) ? $credentials['agreement'] : '',
            ],
            'api_key' => [
                'id' => 'api_key',
                'type' => 'textarea',
                'title' => __('API KEY', 'correo-argentino-for-woocommerce') . Utils::REQUIRED_SIGN,
                'default' => isset($credentials['api_key']) ? $credentials['api_key'] : '',
                'description' => sprintf(
                    // translators: 1: Message "Don't have an account yet?", 2: Anchor link text "Create new account"
                    __('%1$s <a href="https://www.correoargentino.com.ar/" target="_blank" rel="noopener noreferrer">%2$s</a>', 'correo-argentino-for-woocommerce'),
                    esc_html__("Don't have an account yet?", 'correo-argentino-for-woocommerce'),
                    esc_html__('Create new account', 'correo-argentino-for-woocommerce')
                ),
            ],
            'sandbox' => [
                'id' => 'sandbox',
                'type' => 'checkbox',
                'title' => __('Sandbox Mode', 'correo-argentino-for-woocommerce'),
                'label' => __('Enable Sandbox Mode', 'correo-argentino-for-woocommerce'),
                'default' => isset($credentials['sandbox']) && (int) $credentials['sandbox'] === 1 ? 'yes' : 'no',
                'description' => __('Once logged in, to perform the environment change, it is necessary to disable and re-enable the plugin.', 'correo-argentino-for-woocommerce'),
            ],
        ];

        // Disable fields if logged in
        if (class_exists(Utils::class) && Utils::isConnected()) {
            foreach ($fields as $key => &$field) {
                if (isset($field['type']) && $field['type'] !== 'hidden') {
                    $field['custom_attributes'] = ['disabled' => 'disabled', 'readonly' => 'readonly'];
                }
            }

            // Disable the "Create new account" link
            $fields['api_key']['description'] = sprintf(
                // translators: 1: Message "Don't have an account yet?", 2: Link "Create new account"
                __('%1$s <span style="color: #999;">%2$s</span>', 'correo-argentino-for-woocommerce'),
                esc_html__("Don't have an account yet?", 'correo-argentino-for-woocommerce'),
                esc_html__('Create new account', 'correo-argentino-for-woocommerce')
            );

        }

        return $fields;
    }



    public static function getFormFieldID($formId, $serviceName, $subject)
    {
        return [
            'id' => $formId,
            'custom_attributes' => ['data-form-id' => strtolower($subject) . '-' . strtolower($serviceName)],
            'type' => 'hidden',
            'class' => 'wc-hidden-field',
        ];
    }

    public static function getLoginMiCorreoForm(): array
    {
        $credentials = Utils::getCredentials();

        return [
            'username_login' => [
                'id' => 'username',
                'type' => 'text',
                'title' => __('User', 'correo-argentino-for-woocommerce') . Utils::REQUIRED_SIGN,
                'default' => isset($credentials['username']) ? $credentials['username'] : '',
            ],
            'password_login' => [
                'id' => 'password',
                'type' => 'password',
                'title' => __('Password', 'correo-argentino-for-woocommerce') . Utils::REQUIRED_SIGN,
                'default' => isset($credentials['api_key']) ? $credentials['api_key'] : '',
            ],
        ];
    }

    public static function getUserValidateMiCorreoForm(): array
    {
        $credentials = Utils::getCredentials();

        $fields = [
            'form_id' => self::getFormFieldID('wc-correoargentino-form-id', 'mi-correo', 'log-in'),
            'email' => [
                'id' => 'email',
                'type' => 'text',
                'title' => __('User', 'correo-argentino-for-woocommerce') . Utils::REQUIRED_SIGN,
                'default' => isset($credentials['email']) ? $credentials['email'] : '',
            ],
            'password' => [
                'id' => 'password',
                'type' => 'password',
                'title' => __('Password', 'correo-argentino-for-woocommerce') . Utils::REQUIRED_SIGN,
                'description' => sprintf(
                    /* translators: 1: Text before link, 2: Link text */
                    __('%1$s <a href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a>', 'correo-argentino-for-woocommerce'),
                    esc_html__("Don't have an account yet?", 'correo-argentino-for-woocommerce'),
                    esc_url(self::REDIRECT_FORM_URL_BASE . CORRARFO_BUSINESS_MI_CORREO_FORM),
                    esc_html__('Create new account', 'correo-argentino-for-woocommerce')
                ),
            ],
            'customer_id' => [
                'id' => 'customer-id',
                'custom_attributes' => ['value' => null],
                'type' => 'hidden',
                'class' => 'wc-hidden-field'
            ],
            'sandbox' => [
                'id' => 'sandbox',
                'type' => 'checkbox',
                'title' => __('Sandbox Mode', 'correo-argentino-for-woocommerce'),
                'label' => __('Enable Sandbox Mode', 'correo-argentino-for-woocommerce'),
                'default' => isset($credentials['sandbox']) && (int) $credentials['sandbox'] === 1 ? 'yes' : 'no',
                'description' => __('Once logged in, to perform the environment change, it is necessary to disable and re-enable the plugin.', 'correo-argentino-for-woocommerce'),
            ],
        ];

        // Disable fields if logged in
        if (class_exists(Utils::class) && Utils::isConnected()) {
            foreach ($fields as $key => &$field) {
                if (isset($field['type']) && $field['type'] !== 'hidden') {
                    $field['custom_attributes'] = [
                        'disabled' => 'disabled',
                        'readonly' => 'readonly'
                    ];
                }
            }

            // Disable the "Create new account" link
            $fields['password']['description'] = sprintf(
                // translators: 1: Message "Don't have an account yet?", 2: Link "Create new account"
                __('%1$s <span style="color: #999;">%2$s</span>', 'correo-argentino-for-woocommerce'),
                esc_html__("Don't have an account yet?", 'correo-argentino-for-woocommerce'),
                esc_html__('Create new account', 'correo-argentino-for-woocommerce')
            );

        }

        return $fields;
    }

    public static function getServiceSelectorForm(): array
    {
        $service_type_options = array_merge(
            ["-1" => esc_html__("Select an option", 'correo-argentino-for-woocommerce')],
            Utils::getAvailableServices()
        );
        return [
            'service_type' => [
                'id' => 'wc-correoargentino-service-type',
                'type' => 'select',
                'title' => __('Service', 'correo-argentino-for-woocommerce') . Utils::REQUIRED_SIGN,
                'options' => $service_type_options,
                'class' => 'wc-correoargentino-service-type',
                'custom_attributes' => [
                    'data-allow-clear' => 'false',
                    'data-minimum-results-for-search' => 'Infinity',
                ],
            ],
            'form_id' => [
                'id' => 'wc-correoargentino-form-id',
                'custom_attributes' => ['data-form-id' => CORRARFO_SERVICE_SELECTOR],
                'type' => 'hidden',
                'class' => 'wc-hidden-field'
            ]
        ];
    }

    public static function getMiCorreoBusinessForm(): array
    {
        $settings = Utils::getSettings();
        $serviceType = Utils::getCurrentServiceType();

        return [
            'form_id' => self::getFormFieldID('wc-correoargentino-form-id', 'mi-correo', 'new-account'),
            'document_type' => [
                'id' => 'wc-correoargentino-document-type',
                'type' => 'select',
                'title' => __('Document Type', 'correo-argentino-for-woocommerce') . Utils::REQUIRED_SIGN,
                'desc_tip' => __('Choose CUIT if you want to operate as Monotributista or Responsable Inscripto.', 'correo-argentino-for-woocommerce'),
                'options' => Utils::getDocumentTypes(),
                'class' => 'documentType'
            ],
            'document_id' => [
                'id' => 'wc-correoargentino-document-id',
                'type' => 'text',
                'title' => __('Document Number', 'correo-argentino-for-woocommerce') . Utils::REQUIRED_SIGN,
                'description' => __('If you choose CUIT, enter it without hyphens.', 'correo-argentino-for-woocommerce'),
                'class' => 'documentId'
            ],
            'first_name' => [
                'id' => 'wc-correoargentino-first-name',
                'type' => 'text',
                'title' => __('First Name', 'correo-argentino-for-woocommerce') . Utils::REQUIRED_SIGN,
                'description' => __('You can enter a personal or business name.', 'correo-argentino-for-woocommerce'),
                'class' => 'firstName'
            ],
            'last_name' => [
                'id' => 'wc-correoargentino-last-name',
                'type' => 'text',
                'title' => __('Last Name', 'correo-argentino-for-woocommerce'),
                'description' => __('This field is required for final consumers.', 'correo-argentino-for-woocommerce'),
                'class' => 'lastName'
            ],
            'email' => [
                'id' => 'wc-correoargentino-email',
                'type' => 'text',
                'title' => __('Email', 'correo-argentino-for-woocommerce') . Utils::REQUIRED_SIGN,
                'class' => 'email'
            ],
            'password' => [
                'id' => 'wc-correoargentino-password',
                'type' => 'password',
                'title' => __('Password', 'correo-argentino-for-woocommerce') . Utils::REQUIRED_SIGN,
                'class' => 'password'
            ],
            'street_name' => [
                'id' => 'wc-correoargentino-street-name',
                'type' => 'text',
                'title' => __('Street', 'correo-argentino-for-woocommerce') . Utils::REQUIRED_SIGN,
                'class' => 'streetName'
            ],
            'street_number' => [
                'id' => 'wc-correoargentino-street-number',
                'type' => 'text',
                'title' => __('Street Number', 'correo-argentino-for-woocommerce') . Utils::REQUIRED_SIGN,
                'class' => 'streeNumber'
            ],
            'floor' => [
                'id' => 'wc-correoargentino-floor',
                'type' => 'text',
                'title' => __('Floor', 'correo-argentino-for-woocommerce'),
                'class' => 'floor'
            ],
            'department' => [
                'id' => 'wc-correoargentino-apartment',
                'type' => 'text',
                'title' => __('Apartment', 'correo-argentino-for-woocommerce'),
                'class' => 'department'
            ],
            'city_name' => [
                'id' => 'wc-correoargentino-city',
                'type' => 'text',
                'title' => __('City', 'correo-argentino-for-woocommerce') . Utils::REQUIRED_SIGN,
                'class' => 'wc-correoargentino-city'
            ],
            'state_code' => [
                'id' => 'wc-correoargentino-state-code',
                'type' => 'select',
                'title' => __('Province', 'correo-argentino-for-woocommerce') . Utils::REQUIRED_SIGN,
                'options' => Utils::getProvinces(),
                'class' => 'wc-correoargentino-state-code'
            ],
            'zip_code' => [
                'id' => 'wc-correoargentino-zip-code',
                'type' => 'text',
                'title' => __('ZIP Code', 'correo-argentino-for-woocommerce') . Utils::REQUIRED_SIGN,
                'class' => 'postalCode'
            ],
            'phone' => [
                'id' => 'wc-correoargentino-phone',
                'type' => 'text',
                'title' => __('Phone', 'correo-argentino-for-woocommerce'),
                'class' => 'phone'
            ],
            'cellphone' => [
                'id' => 'wc-correoargentino-cellphone',
                'type' => 'text',
                'title' => __('Mobile Phone', 'correo-argentino-for-woocommerce') . Utils::REQUIRED_SIGN,
                'class' => 'phone'
            ],
            'customer_id' => [
                'id' => 'wc-correoargentino-customer-id',
                'custom_attributes' => ['value' => $settings["customer_id"]],
                'type' => 'hidden',
                'class' => 'wc-hidden-field'
            ],
            'current_service_type' => [
                'id' => 'wc-correoargentino-current-service-type',
                'custom_attributes' => ['data-current-service-type' => $serviceType],
                'type' => 'hidden',
                'class' => 'wc-hidden-field'
            ],
        ];
    }

    private static function getCommonFormFields(): array
    {
        return [
            'business_name' => [
                'id' => 'wc-correoargentino-business-name',
                'type' => 'text',
                'title' => __('Name', 'correo-argentino-for-woocommerce') . Utils::REQUIRED_SIGN,
                'description' => __('You can enter a personal or business name.', 'correo-argentino-for-woocommerce'),
                'class' => 'businessName'
            ],
            'email' => [
                'id' => 'wc-correoargentino-email',
                'type' => 'text',
                'title' => __('Email', 'correo-argentino-for-woocommerce') . Utils::REQUIRED_SIGN,
                'description' => __('Use the following format: correoargentino@correo.com', 'correo-argentino-for-woocommerce'),
            ],
            'state' => [
                'id' => 'wc-correoargentino-state',
                'type' => 'select',
                'title' => __('Province', 'correo-argentino-for-woocommerce') . Utils::REQUIRED_SIGN,
                'options' => Utils::getProvinces(),
                'class' => 'wc-correoargentino-states'
            ],
            'city_name' => [
                'id' => 'wc-correoargentino-city-name',
                'type' => 'text',
                'title' => __('City', 'correo-argentino-for-woocommerce') . Utils::REQUIRED_SIGN,
                'class' => 'cityName'
            ],
            'department' => [
                'id' => 'wc-correoargentino-department',
                'type' => 'text',
                'title' => __('Apartment', 'correo-argentino-for-woocommerce'),
                'maxlength' => 3,
                'class' => 'department'
            ],
            'floor' => [
                'id' => 'wc-correoargentino-floor',
                'type' => 'text',
                'title' => __('Floor', 'correo-argentino-for-woocommerce'),
                'class' => 'floor'
            ],
            'street_name' => [
                'id' => 'wc-correoargentino-street-name',
                'type' => 'text',
                'title' => __('Street', 'correo-argentino-for-woocommerce') . Utils::REQUIRED_SIGN,
                'maxlength' => 32,
                'class' => 'streetName'
            ],
            'street_number' => [
                'id' => 'wc-correoargentino-street-number',
                'type' => 'text',
                'title' => __('Street Number', 'correo-argentino-for-woocommerce') . Utils::REQUIRED_SIGN,
                'maxlength' => 10,
                'class' => 'streetNumber'
            ],
            'zip_code' => [
                'id' => 'wc-correoargentino-zip-code',
                'type' => 'text',
                'title' => __('ZIP Code', 'correo-argentino-for-woocommerce') . Utils::REQUIRED_SIGN,
                'maxlength' => 10,
                'desc_tip' => __('Use the following format: LNNNNLLL or NNNN. e.g.: 1190 or A1190NNC', 'correo-argentino-for-woocommerce'),
                'description' => __('If you don’t know your ZIP code, check <a href="https://www.correoargentino.com.ar/formularios/cpa">here</a>', 'correo-argentino-for-woocommerce'),
                'class' => 'postalCode'
            ],
            'cellphone' => [
                'id' => 'wc-correoargentino-cellphone',
                'type' => 'text',
                'title' => __('Mobile Phone', 'correo-argentino-for-woocommerce'),
                'maxlength' => 16,
                'desc_tip' => __('Use the following format: 9999-99999999', 'correo-argentino-for-woocommerce'),
                'description' => __('Use the following format: 9999-99999999', 'correo-argentino-for-woocommerce'),
                'class' => 'phone'
            ],
            'phone' => [
                'id' => 'wc-correoargentino-phone',
                'type' => 'text',
                'title' => __('Phone', 'correo-argentino-for-woocommerce'),
                'maxlength' => 16,
                'desc_tip' => __('Use the following format: 9999-99999999', 'correo-argentino-for-woocommerce'),
                'description' => __('Use the following format: 9999-99999999', 'correo-argentino-for-woocommerce'),
                'class' => 'phone'
            ]
        ];
    }

    public static function getInstanceBusinessForm(): array
    {
        return array_merge(
            [
                'form_id' => self::getFormFieldID('wc-correoargentino-form-id', 'mi-correo', 'business-details'),
                'title' => [
                    'title' => __('Store description', 'correo-argentino-for-woocommerce'),
                    'type' => 'text',
                    'description' => __('Name displayed to buyers at checkout when selecting the required method', 'correo-argentino-for-woocommerce'),
                    'default' => __('Correo Argentino', 'correo-argentino-for-woocommerce'),
                    'desc_tip' => true,
                    'custom_attributes' => ["readonly" => true],
                ]
            ],
            self::getCommonFormFields(),
            [
                'tipo_servicio' => [
                    'title' => 'Tipo de envío',
                    'type' => 'select',
                    'label' => 'Tipo de envío',
                    'options' => [
                        'E' => 'Expreso',
                        'C' => 'Clásico',
                    ]
                ],
                'metodo_envio' => [
                    'title' => 'Método de envío',
                    'type' => 'select',
                    'label' => 'Método de envío',
                    'options' => [
                        'S' => 'Sucursal',
                        'D' => 'A Domicilio',
                    ]
                ],

            ],
            [
                'observation' => [
                    'id' => 'wc-correoargentino-observation',
                    'type' => 'textarea',
                    'title' => __('Observation', 'correo-argentino-for-woocommerce'),
                    'description' => ' <div id="the-count"><span id="current">0</span><span id="maximum">/ 150</span></div>',
                    'custom_attributes' => ["maxlength" => 150],
                ],
            ]
        );
    }

    public static function getBusinessForm(): array
    {
        return array_merge(
            [
                'form_id' => self::getFormFieldID('wc-correoargentino-form-id', 'mi-correo', 'business-details'),
            ],
            self::getCommonFormFields(),
            [
                'observation' => [
                    'id' => 'wc-correoargentino-observation',
                    'type' => 'textarea',
                    'title' => __('Observation', 'correo-argentino-for-woocommerce'),
                    'custom_attributes' => ["maxlength" => 150],
                    'error' => __('The observation must not exceed 150 characters', 'correo-argentino-for-woocommerce'),
                ],
            ]
        );
    }
}

// Backward compatibility alias
if (!class_exists('CorreoArgentinoAdminForm')) {
    class_alias(AdminForm::class, 'CorreoArgentinoAdminForm');
}
