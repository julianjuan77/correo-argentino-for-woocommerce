/* global corrarfo_branch_cfg, jQuery */
jQuery(function ($) {
  var path = corrarfo_branch_cfg && corrarfo_branch_cfg.ajaxUrl ? corrarfo_branch_cfg.ajaxUrl : '';
  var edit_address_path = corrarfo_branch_cfg && corrarfo_branch_cfg.editAddressUrl ? corrarfo_branch_cfg.editAddressUrl : '';
  var isCart = corrarfo_branch_cfg && corrarfo_branch_cfg.isCart ? true : false;

  var province_select = '.correoargentino_province_select';
  var agency_select = '.correoargentino_branch_select';

  /**
   * Display/update the shipping message with the chosen branch
   */
  function branchSelected(branch) {
    var id = branch.id || 'NONE';
    var zip_code = branch.zip_code || '';
    var name = branch.name || '';
    var street_name = branch.street_name || '';
    var street_number = branch.street_number || '';
    var city = branch.city || '';
    var state = branch.state || '';

    $('#woocommerce_correoargentino_branch_zip_code_hidden').val(zip_code);

    var shipping_address_message = 'Cargando sucursal...';
    if (name) {
      shipping_address_message =
        'Enviar a sucursal <strong>' +
        name + ', ' + street_name + ' ' + street_number + ', ' +
        city + ', ' + state + ' (' + id + '), ' + zip_code +
        '</strong>.';
    }

    if (id !== 'NONE') {
      $('.woocommerce-shipping-destination').empty().html(shipping_address_message).show();
      $('#correo_argentino_review_order_section_address').empty().html(shipping_address_message).show();
    } else {
      $('.woocommerce-shipping-destination').empty().show();
      $('#correo_argentino_review_order_section_address').empty().show();
    }
  }

  function loadProvincesSelect2() {
    if (!$(province_select).length) return;

    if (!$(province_select).hasClass('select2-hidden-accessible')) {
      $(province_select).select2();
    }

    $(province_select).off('select2:select.ca').on('select2:select.ca', function () {
      var province_code = $(province_select).val();
      var defaultProductType = $(agency_select).data('product-type') || $(agency_select).attr('data-product-type') || '';

      if (!province_code || province_code === 'NONE') {
        branchSelected({ id: 'NONE' });
        return;
      }

      $(agency_select).attr('disabled', true);

      $.ajax({
        type: 'post',
        dataType: 'json',
        url: path,
        data: {
          action: 'corrarfo_update_province',
          province_code: province_code,
          nonce: corrarfo_branch_cfg.nonceProvince
        },
        success: function (response) {
          $(agency_select).empty();

          var selectedAgency = null;

          if (response && response.province_list) {
            response.province_list.forEach(function (data) {
              var newOption = new Option(data.text, data.id, false, false);
              // datasets
              newOption.dataset.branchName = data.agency_name;
              newOption.dataset.branchZipCode = data.agency_zip_code;
              newOption.dataset.branchCity = data.agency_city;
              newOption.dataset.branchState = data.agency_state;
              newOption.dataset.branchStreetName = data.agency_street_name;
              newOption.dataset.branchStreetNumber = data.agency_street_number;
              newOption.dataset.branchChosenProductType = data.agency_chosen_product_type || defaultProductType;

              var selected = (data.id === response.branch_id);
              newOption.selected = selected;
              if (selected) selectedAgency = data;

              $(agency_select).append(newOption).trigger('change');
            });

            if (selectedAgency) {
              branchSelected({
                id: selectedAgency.id,
                zip_code: selectedAgency.agency_zip_code,
                name: selectedAgency.agency_name,
                street_name: selectedAgency.agency_street_name,
                street_number: selectedAgency.agency_street_number,
                city: selectedAgency.agency_city,
                state: selectedAgency.agency_state
              });
            } else {
              branchSelected({ id: 'NONE' });
            }
          }

          $(agency_select).attr('disabled', false);
        },
        error: function (error) {
          console.error(error);
          $(agency_select).attr('disabled', false);
        }
      });
    });

    // Only trigger manually if the select is empty and has a value, to avoid loops on updated_checkout
    if ($(province_select).val() && $(province_select).val() !== 'NONE' && $(agency_select).children().length <= 1) {
      $(province_select).trigger('select2:select');
    }
  }

  function loadAgencySelect2() {
    if (!$(agency_select).length) return;

    if (!$(agency_select).hasClass('select2-hidden-accessible')) {
      $(agency_select).select2();
    }

    $(agency_select).off('select2:select.ca').on('select2:select.ca', function () {
      var $currentSelect = $(this);
      var $selectedOption = $currentSelect.find('option:selected');

      var id = $currentSelect.val();
      var name = $selectedOption.data('branch-name');
      var zip_code = $selectedOption.data('branch-zip-code');
      var city = $selectedOption.data('branch-city');
      var state = $selectedOption.data('branch-state');
      var street_name = $selectedOption.data('branch-street-name');
      var street_number = $selectedOption.data('branch-street-number');
      var chosen_product_type = $selectedOption.data('branch-chosen-product-type')
        || $currentSelect.data('product-type')
        || $currentSelect.attr('data-product-type')
        || '';

      try {
        validateArguments({
          id: id,
          name: name,
          zip_code: zip_code,
          city: city,
          state: state,
          street_name: street_name,
          street_number: street_number
        });
      } catch (error) {
        console.log(error.message);
      }

      if (!chosen_product_type) {
        console.warn('Correo Argentino: missing product type for selected branch.');
        return;
      }

      $('.woocommerce-shipping-destination').html('Cargando...');
      $.ajax({
        type: 'post',
        dataType: 'json',
        url: path,
        data: {
          action: 'corrarfo_update_branch',
          nonce: corrarfo_branch_cfg.nonceBranch,
          branch_id: id,
          branch_name: name,
          branch_city: city,
          branch_state: state,
          branch_zip_code: zip_code,
          branch_street_name: street_name,
          branch_street_number: street_number,
          branch_chosen_product_type: chosen_product_type
        },
        success: function () {
          $('#shipping_postcode').val(zip_code);
          $('#shipping_city').val(city);
          $('#shipping_address_1').val(street_name);
          $('#shipping_address_2').val(street_number);
          $('#shipping_company').val(name);

          var provinceMap = {
            'capital federal': 'C',
            'buenos aires': 'B',
            'catamarca': 'K',
            'chaco': 'H',
            'chubut': 'U',
            'cordoba': 'X',
            'corrientes': 'W',
            'entre ríos': 'E',
            'formosa': 'P',
            'jujuy': 'Y',
            'la pampa': 'L',
            'la rioja': 'F',
            'mendoza': 'M',
            'misiones': 'N',
            'neuquen': 'Q',
            'rio negro': 'R',
            'salta': 'A',
            'san juan': 'J',
            'san luis': 'D',
            'santa cruz': 'Z',
            'santa fe': 'S',
            'santiago del estero': 'G',
            'tierra del fuego': 'V',
            'tucuman': 'T'
          };
          var formattedState = (state || '').toLowerCase().trim();
          var selectedProvinceValue = provinceMap[formattedState] || '';
          $('#shipping_state').val(selectedProvinceValue).trigger('change');

          $('#calc_shipping_postcode').val(zip_code);

          branchSelected({
            id: id,
            zip_code: zip_code,
            name: name,
            street_name: street_name,
            street_number: street_number,
            city: city,
            state: state
          });

          if (isCart) {
            $('.shipping_method:checked').change();
          } else {
            // Delay update_checkout further and ensure no element is focused
            // to prevent WooCommerce's focus restoration logic from failing.
            setTimeout(function () {
              if (document.activeElement && typeof document.activeElement.blur === 'function') {
                document.activeElement.blur();
              }
              $(document.body).trigger('update_checkout');
            }, 300);
          }
        },
        error: function (error) {
          console.error(error);
          $('.woocommerce-shipping-destination').empty();
        }
      });
    });

    function validateArguments(args) {
      var requiredArguments = ['id', 'name', 'zip_code', 'city', 'state', 'street_name', 'street_number'];
      var missing = [];
      requiredArguments.forEach(function (key) {
        if (!args[key]) missing.push(key);
      });
      if (missing.length > 0) {
        throw new Error('Error on arguments. Missing: ' + missing.join(', '));
      }
    }
  }

  loadProvincesSelect2();
  loadAgencySelect2();

  $(document.body).on('updated_cart_totals updated_checkout', function () {
    loadProvincesSelect2();
    loadAgencySelect2();
  });
});
