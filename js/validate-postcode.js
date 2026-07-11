document.addEventListener('DOMContentLoaded', function () {
    ['billing_postcode', 'shipping_postcode', 'calc_shipping_postcode'].forEach(function(name) {
        const field = document.querySelector(`input[name="${name}"]`);
        if (field) {
            field.addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '').slice(0, 4);
            });
        }
    });
});
