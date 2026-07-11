// Toggle enable/disable and force uppercase for the order prefix field
document.addEventListener('DOMContentLoaded', function () {
  var toggle = document.querySelector('input[name="corrarfo_prefix_enabled"]');
  var target = document.querySelector('input[name="corrarfo_prefix_value"]');
  if (!target) return;

  // Force uppercase on input (replaces inline oninput previously used)
  target.addEventListener('input', function () {
    this.value = (this.value || '').toUpperCase();
  });

  // Enable/disable based on checkbox
  function updateState() {
    if (toggle) {
      target.disabled = !toggle.checked;
    }
  }

  if (toggle) {
    toggle.addEventListener('change', updateState);
    updateState();
  }
});
