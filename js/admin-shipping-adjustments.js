document.addEventListener('DOMContentLoaded', function () {
  var toggle = document.querySelector('#corrarfo_shipping_enable');
  var fields = [
    document.querySelector('#corrarfo_shipping_type'),
    document.querySelector('#corrarfo_shipping_percentage'),
    document.querySelector('#corrarfo_shipping_min_total')
  ];

  function toggleFields() {
    var enabled = toggle && toggle.checked;
    fields.forEach(function (field) {
      if (field) field.disabled = !enabled;
    });
  }

  if (toggle) {
    toggleFields();
    toggle.addEventListener('change', toggleFields);
  }
});
