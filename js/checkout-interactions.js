jQuery(document).ready(function($) {
    function isAddressIncomplete() {
        const street = $('input[name="billing_address_1"]').val();
        const number = $('input[name="billing_address_2"]').val();
        const city = $('input[name="billing_city"]').val();
        const postalCode = $('input[name="billing_postcode"]').val();
    
        return !street || !number || !city || !postalCode;
    }

    // El forzado de "otra dirección" según el método de envío (domicilio/sucursal)
    // vive en checkout-branch-ux.js, que es quien decide esa visibilidad hoy.
    // Acá sólo queda la guarda de dirección incompleta.
    function checkShippingMethod() {
        const $checkbox = $('#ship-to-different-address-checkbox');

        if (isAddressIncomplete()) {
            if ($checkbox.prop('checked')) {
                $checkbox.prop('checked', false).change();
            }
            $('.shipping_address').show();
        }
    }
    // Execute when the shipping method changes
    $('form.checkout').on('change', 'input[name^="shipping_method"]', function() {
        checkShippingMethod();
    });

    $('body').on('updated_checkout', function() {
        checkShippingMethod();
    });

    checkShippingMethod();
});
