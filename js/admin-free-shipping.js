document.addEventListener('DOMContentLoaded', function () {
  var checkbox = document.getElementById('corrarfo_free_shipping_enabled');
  var amountField = document.getElementById('corrarfo_free_shipping_amount');
  if (!checkbox || !amountField) return;

  function toggleAmountField() {
    amountField.disabled = !checkbox.checked;
  }

  toggleAmountField();
  checkbox.addEventListener('change', toggleAmountField);
});
