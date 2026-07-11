document.addEventListener('DOMContentLoaded', function () {
  var toggle = document.querySelector('input[name="corrarfo_default_enabled"]');
  var inputs = document.querySelectorAll('input[name^="corrarfo_default_"]:not([name="corrarfo_default_enabled"])');

  function toggleFields() {
    if (!toggle) return;
    inputs.forEach(function (input) {
      input.disabled = !toggle.checked;
    });
  }

  if (toggle) {
    toggle.addEventListener('change', toggleFields);
    toggleFields();
  }
});
