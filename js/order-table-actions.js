/* global corrarfo_order_table_cfg */
document.addEventListener('DOMContentLoaded', function () {
  var id = (corrarfo_order_table_cfg && corrarfo_order_table_cfg.checkboxId) ? corrarfo_order_table_cfg.checkboxId : 'show_hidden_orders';
  var cb = document.getElementById(id);
  if (!cb || !cb.form) return;

  cb.addEventListener('change', function () {
    cb.form.submit();
  });
});
