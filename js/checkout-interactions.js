jQuery(document).ready(function($) {
    function isAddressIncomplete() {
        const street = $('input[name="billing_address_1"]').val();
        const number = $('input[name="billing_address_2"]').val();
        const city = $('input[name="billing_city"]').val();
        const postalCode = $('input[name="billing_postcode"]').val();
    
        return !street || !number || !city || !postalCode;
    }

    function checkShippingMethod() {
        const $checkbox = $('#ship-to-different-address-checkbox');

        if (isAddressIncomplete()) {
            if ($checkbox.prop('checked')) {
                $checkbox.prop('checked', false).change();            }
            $('.shipping_address').show();
            return;
        }

        $('input[name^="shipping_method"]:checked').each(function() {
            const $shippingRow = $(this).closest('li');
            const label = $shippingRow.length ? $shippingRow.text().toLowerCase() : '';

            if (
                label.includes('correo argentino clásico a domicilio') ||
                label.includes('correo argentino expreso a domicilio')
            ) {
                if ($checkbox.prop('checked')) {
                    $checkbox.prop('checked', false).change();
                }
                $('.shipping_address').hide();
            }
        });
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
