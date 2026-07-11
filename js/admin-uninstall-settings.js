document.addEventListener('DOMContentLoaded', function () {
  var checkbox = document.getElementById('corrarfo_remove_on_uninstall');
  var warning = document.getElementById('corrarfo_uninstall_warning');
  if (!checkbox || !warning) return;

  function sync() {
    warning.style.display = checkbox.checked ? 'block' : 'none';
  }

  checkbox.addEventListener('change', sync);
  sync();
});
