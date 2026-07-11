document.addEventListener('DOMContentLoaded', function () {
  ['shipping_postcode', 'billing_postcode'].forEach(function (name) {
    var field = document.querySelector('input[name="' + name + '"]');
    if (field) {
      field.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 4);
      });
    }
  });
});