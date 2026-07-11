/* global corrarfo_checkout_toggle_cfg, jQuery */
jQuery(function ($) {
  var wasProvinceOrBranchVisible = false;

  function isBranchUIVisible() {
    var provinceSelectVisible = $('#corrarfo_container_select_provinces').is(':visible');
    var branchSelectVisible = $('#corrarfo_container_select_branches').is(':visible');
    return provinceSelectVisible || branchSelectVisible;
  }

  function setReadonlyShipping(on) {
    $('.shipping_address :input:not([type="checkbox"]), .shipping_address textarea').prop('readonly', !!on);
    var $selects = $('.shipping_address select');
    if (on) $selects.addClass('non-interactive'); else $selects.removeClass('non-interactive');
    $('#shipping_first_name, #shipping_last_name').prop('readonly', false);
  }

  function prefillFromAccount() {
    var pf = (corrarfo_checkout_toggle_cfg && corrarfo_checkout_toggle_cfg.prefill) ? corrarfo_checkout_toggle_cfg.prefill : {};
    $('#shipping_first_name').val(pf.shipping_first_name || '');
    $('#shipping_last_name').val(pf.shipping_last_name || '');
    $('#shipping_company').val(pf.shipping_company || '');
    $('#shipping_address_1').val(pf.shipping_address_1 || '');
    $('#shipping_address_2').val(pf.shipping_address_2 || '');
    $('#shipping_city').val(pf.shipping_city || '');
    $('#shipping_state').val(pf.shipping_state || '').trigger('change');
    $('#shipping_postcode').val(pf.shipping_postcode || '');
    $('#shipping_phone').val(pf.shipping_phone || '');
  }

  function prefillFromBilling() {
    $('#shipping_first_name').val($('#billing_first_name').val() || '');
    $('#shipping_last_name').val($('#billing_last_name').val() || '');
    $('#shipping_company').val($('#billing_company').val() || '');
    $('#shipping_address_1').val($('#billing_address_1').val() || '');
    $('#shipping_address_2').val($('#billing_address_2').val() || '');
    $('#shipping_city').val($('#billing_city').val() || '');
    $('#shipping_state').val($('#billing_state').val() || '').trigger('change');
    $('#shipping_postcode').val($('#billing_postcode').val() || '');
    $('#shipping_phone').val($('#billing_phone').val() || '');
  }

  function loadShippingAddressData() {
    if (corrarfo_checkout_toggle_cfg && corrarfo_checkout_toggle_cfg.isLoggedIn) {
      prefillFromAccount();
    } else {
      prefillFromBilling();
    }
  }

  function toggleShippingAddress() {
    var visible = isBranchUIVisible();

    if (visible) {
      wasProvinceOrBranchVisible = true;
      $('#ship-to-different-address-checkbox').prop('checked', true);
      $('.shipping_address').show();
      setReadonlyShipping(true);
    } else {
      setReadonlyShipping(false);
      $('#correo_argentino_review_order_section_address').hide();

      if (wasProvinceOrBranchVisible) {
        loadShippingAddressData();
        wasProvinceOrBranchVisible = false;
      }
    }
  }

  // init
  toggleShippingAddress();

  $('#corrarfo_container_select_provinces, #corrarfo_container_select_branches').on('change', toggleShippingAddress);

  $('#ship-to-different-address-checkbox').on('change', function () {
    if (!isBranchUIVisible()) toggleShippingAddress();
  });

  $(document.body).on('updated_checkout', toggleShippingAddress);
});
