/* global corrarfo_bulk_cfg, jQuery */
jQuery(function ($) {

  var path = corrarfo_bulk_cfg.ajaxUrl;
  var maxPerOp = corrarfo_bulk_cfg.maxPerOp;
  var nonce = corrarfo_bulk_cfg.nonce;

  var mainCheckboxes = $('.wc-correoargentino-all-orders');
  var orderCheckboxes = $('input[name="order_id[]"]');

  // Toggle all
  mainCheckboxes.on('change', function () {
    var isChecked = $(this).prop('checked');
    mainCheckboxes.prop('checked', isChecked);
    orderCheckboxes.prop('checked', isChecked);
  });

  // Reflect all checked → master checked
  orderCheckboxes.on('change', function () {
    var allChecked = orderCheckboxes.filter(':checked').length === orderCheckboxes.length;
    mainCheckboxes.prop('checked', allChecked);
  });

  // Bulk action
  $('.bulk-actions-button').on('click', function () {
    var $btn = $(this);
    $btn.attr('disabled', true);

    var selectedAction = $('.bulk-actions-top option:selected').val();
    if (selectedAction == -1) {
      $btn.attr('disabled', false);
      return;
    }

    var selectedOrderIds = [];
    $('input[name="order_id[]"]:checked').each(function () {
      selectedOrderIds.push($(this).val());
    });

    var maxPerOp = (corrarfo_bulk_cfg && corrarfo_bulk_cfg.maxPerOp) ? parseInt(corrarfo_bulk_cfg.maxPerOp, 10) : 10;
    if (selectedOrderIds.length > maxPerOp) {
      alert('Solo se permiten ' + maxPerOp + ' registros por operación');
      $btn.attr('disabled', false);
      return;
    }

    var path = (corrarfo_bulk_cfg && corrarfo_bulk_cfg.ajaxUrl) ? corrarfo_bulk_cfg.ajaxUrl : '';

    var promises = selectedOrderIds.map(function (orderId) {
      showLoadingMessage(orderId);
      return new Promise(function (resolve, reject) {
        $.ajax({
          type: 'POST',
          url: path,
          data: {
            action: 'corrarfo_process_orders',
            order_id: orderId,
            _wpnonce: corrarfo_bulk_cfg.nonce
          },
          success: function (response) { resolve(response); },
          error: function (error) { reject(error); },
          complete: function () { hideLoadingMessage(orderId); }
        });
      });
    });

    Promise.all(promises)
      .then(function (results) {
        results.forEach(function (result) {
          var data = result && result.data ? result.data : {};
          var order_id = data.order_id;
          var reference = data.reference;
          var api_error_message = data.api_error_message;

          if (!reference) {
            handleError(order_id, 'Error: ' + (api_error_message || ''));
            return;
          }
          handleSuccess(order_id, reference);
        });

        // Small delay then reload
        setTimeout(function () {
          window.location.reload();
        }, 3000);
      })
      .catch(function () {
        // All errors are handled individually above
      })
      .finally(function () {
        $btn.attr('disabled', false);
      });

    function handleSuccess(orderId, reference) {
      var $col = $('.ph-tracking_number-' + orderId);
      var url = reference;

      // hide the checkbox in the same row
      var $checkbox = $col.closest('tr').find('input[type="checkbox"]');
      $checkbox.hide();

      $col.empty().html(url);
    }

    function handleError(orderId, status) {
      var $col = $('.ph-tracking_number-' + orderId);
      $col.html('<span style="color: #ff645c;">' + status + '</span>');
    }

    function showLoadingMessage(orderId) {
      $('.ph-tracking_number-' + orderId).text('Procesando...');
    }

    function hideLoadingMessage(orderId) {
      $('.ph-tracking_number-' + orderId).text('');
    }
  });

});
