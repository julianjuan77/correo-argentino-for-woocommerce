/* global corrarfo_postal_codes_cfg, jQuery */
jQuery(function ($) {
  var cfg = window.corrarfo_postal_codes_cfg || {};
  var cache = {};
  var prefixes = ['billing', 'shipping'];

  function fieldSelector(prefix, name) {
    return '#' + prefix + '_' + name;
  }

  function optionLabel(entry) {
    if (entry.partido && entry.partido.toUpperCase() !== entry.localidad.toUpperCase()) {
      return entry.localidad + ' (' + entry.partido + ')';
    }
    return entry.localidad;
  }

  function ensureSelect($field) {
    if ($field.is('select')) {
      return $field;
    }
    var $select = $('<select></select>')
      .attr('id', $field.attr('id'))
      .attr('name', $field.attr('name'))
      .attr('class', $field.attr('class'));
    $field.replaceWith($select);
    return $select;
  }

  function ensureTextInput($field) {
    if (!$field.is('select')) {
      return $field;
    }
    if ($field.hasClass('select2-hidden-accessible')) {
      $field.select2('destroy');
    }
    var $input = $('<input type="text" />')
      .attr('id', $field.attr('id'))
      .attr('name', $field.attr('name'))
      .attr('class', $field.attr('class'));
    $field.replaceWith($input);
    return $input;
  }

  function applyPostcode(prefix, cps) {
    var $postcode = $(fieldSelector(prefix, 'postcode'));
    if (!$postcode.length) {
      return;
    }

    if (!cps || !cps.length) {
      return;
    }

    if (cps.length === 1) {
      $postcode = ensureTextInput($postcode)
        .attr('maxlength', 4)
        .attr('inputmode', 'numeric');
      $postcode.val(cps[0]).prop('readonly', true).trigger('change');
      return;
    }

    var $select = ensureSelect($postcode).addClass('corrarfo-postcode-select');
    $select.empty().append(new Option('Código postal...', '', true, true));
    cps.forEach(function (cp) {
      $select.append(new Option(cp, cp, false, false));
    });

    if ($select.hasClass('select2-hidden-accessible')) {
      $select.select2('destroy');
    }
    if (typeof $select.select2 === 'function') {
      $select.select2({ width: '100%' });
    }
    $select.trigger('change');
  }

  function populateCitySelect(prefix, localidades) {
    var $city = $(fieldSelector(prefix, 'city'));
    if (!$city.length) {
      return;
    }

    if (!localidades.length) {
      // Sin cobertura del dataset para esta provincia: no tocamos el campo (texto libre).
      ensureTextInput($city);
      return;
    }

    var currentVal = ($city.val() || '').trim();
    var $select = ensureSelect($city).addClass('corrarfo-locality-select');

    $select.empty().append(new Option('Seleccioná tu localidad...', '', true, true));

    var matchedValue = null;
    localidades.forEach(function (entry) {
      // El value es el nombre real de la localidad: es lo que WooCommerce guarda como
      // billing_city/shipping_city. Dos localidades homónimas en distinto partido pueden
      // compartir value; no es un problema porque el cambio se lee del <option> realmente
      // seleccionado (option:selected), no del value.
      var opt = new Option(optionLabel(entry), entry.localidad, false, false);
      opt.dataset.cps = JSON.stringify(entry.cps);
      if (matchedValue === null && currentVal && entry.localidad.toUpperCase() === currentVal.toUpperCase()) {
        matchedValue = entry.localidad;
      }
      $select.append(opt);
    });

    if ($select.hasClass('select2-hidden-accessible')) {
      $select.select2('destroy');
    }
    if (typeof $select.select2 === 'function') {
      $select.select2({ width: '100%' });
    }

    $select.off('change.corrarfo').on('change.corrarfo', function () {
      var selectedOption = $select.find('option:selected')[0];
      var cps = selectedOption && selectedOption.dataset.cps ? JSON.parse(selectedOption.dataset.cps) : [];
      applyPostcode(prefix, cps);
    });

    if (matchedValue !== null) {
      $select.val(matchedValue).trigger('change');
      if (typeof $select.select2 === 'function') {
        $select.trigger('change.select2');
      }
    }
  }

  function loadLocalidades(provinceLetter, callback) {
    if (!provinceLetter) {
      callback([]);
      return;
    }
    if (cache[provinceLetter]) {
      callback(cache[provinceLetter]);
      return;
    }
    $.ajax({
      type: 'post',
      dataType: 'json',
      url: cfg.ajaxUrl,
      data: {
        action: 'corrarfo_get_localidades',
        province: provinceLetter,
        nonce: cfg.nonce
      }
    }).done(function (response) {
      var localidades = (response && response.success && response.data && response.data.localidades) || [];
      cache[provinceLetter] = localidades;
      callback(localidades);
    }).fail(function () {
      callback([]);
    });
  }

  function handleStateChange(prefix) {
    var province = $(fieldSelector(prefix, 'state')).val();
    loadLocalidades(province, function (localidades) {
      populateCitySelect(prefix, localidades);
    });
  }

  prefixes.forEach(function (prefix) {
    $(document.body).on('change', fieldSelector(prefix, 'state'), function () {
      handleStateChange(prefix);
    });

    var initialState = $(fieldSelector(prefix, 'state')).val();
    if (initialState) {
      handleStateChange(prefix);
    }
  });
});
