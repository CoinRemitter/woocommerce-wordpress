jQuery(document).ready(function ($) {
  // coinremitter wallet add
  $("#csf-form").on("submit", function (event) {
    $(".add-wallet-btn").prop("disabled", true);
    event.preventDefault();

    var api_key = $('input[name="wallet_key"]').val();
    var wallet_pass = $('input[name="wallet_password"]').val();
    var minimum_invoice_amount = $(
      'input[name="minimum_invoice_amount"]'
    ).val();
    var exchange_rate_multiplier = $(
      'input[name="exchange_rate_multiplier"]'
    ).val();


    $("#error-message-wallet").empty();
    $("#usd-rate-error").empty();
    if (api_key === "" || wallet_pass === "" || minimum_invoice_amount === "" || exchange_rate_multiplier === "") {
      $("#error-message-wallet").text(
        "Please fill out all the fields to proceed."
      );
      $(".add-wallet-btn").prop("disabled", false);
      return;
    }
    if (isNaN(parseFloat(minimum_invoice_amount)) || parseFloat(minimum_invoice_amount) <= 0) {
      $("#error-message-wallet").text(
        "Minimum order value must be greater than 0."
      );
      $(".add-wallet-btn").prop("disabled", false);
      return;
    }
    if (isNaN(parseFloat(exchange_rate_multiplier)) || parseFloat(exchange_rate_multiplier) <= 0) {
      $("#error-message-wallet").text(
        "Order multiplier must be greater than 0."
      );
      $(".add-wallet-btn").prop("disabled", false);
      return;
    }
    $.ajax({
      url: ajaxurl,
      datatype: JSON,
      type: "POST",
      data: {
        action: "coinremitter_wp_wallet_add",
        security: my_ajax_obj.nonce,
        wallet_key: api_key,
        wallet_password: wallet_pass,
        minimum_invoice_amount: minimum_invoice_amount,
        exchange_rate_multiplier: exchange_rate_multiplier,
      },
      success: function (response) {
        console.log(response);
        if (typeof response == 'string') {
          response = JSON.parse(response)
        }
        $("#error-message-wallet").html(response);
        $("#error-message-wallet").empty();
        var msg = response.msg;
        if (response.flag == 1) {
          $("#add-success-message-wallet").html(
            '<h6 class="success_msg">wallet added successfully</h6>'
          );
          $(".add-wallet-btn").prop("disabled", true);
          window.location.reload();
        }
        if (response.flag == 0) {
          $("#error-message-wallet").html("<p>" + msg + "</p>");
          $(".add-wallet-btn").prop("disabled", false);
        }
      },
      error: function (xhr, status, error) {
        $("#error-message-wallet").html("<p>" + error + "</p>");
        console.log(error);
      },
    });
  });

  // Fetch live wallet data and open Edit modal
  $(".EditOpenPopup").on("click", function () {
    var $this = $(this);
    var id = $this.data("id");

    var ajaxEndpoint = (typeof my_ajax_obj !== "undefined" && my_ajax_obj.ajaxurl) ? my_ajax_obj.ajaxurl : ajaxurl;
    var ajaxNonce = (typeof my_ajax_obj !== "undefined" && my_ajax_obj.nonce) ? my_ajax_obj.nonce : "";

    // Fetch live, fresh wallet data from DB via AJAX
    $.ajax({
      type: "POST",
      url: ajaxEndpoint,
      data: {
        action: "coinremitter_wp_wallet_get",
        security: ajaxNonce,
        id: id,
      },
      success: function (response) {
        if (response.success && response.data) {
          var data = response.data;
          $("#coin_id").val(data.id);
          $("#editcointypeid").text(data.coin_symbol);
          if (data.coin_logo_url) {
            $(".wallet_image").attr("src", data.coin_logo_url);
          }
          $("#wallet_key_update").val(data.api_key);
          $("#wallet_password_update").val(data.password);
          $("#minimum_invoice_amount_edit").val(data.minimum_invoice_amount);
          $("#exchange_rate_multiplier_edit").val(data.exchange_rate_multiplier);
          $("#coinName").val(data.coin_symbol ? String(data.coin_symbol).toLowerCase() : "");
        }
      },
      complete: function () {
        $("#update_wallet").modal("show");
      },
    });
  });

  // coinremitter wallet Update
  jQuery(".walletUpdateBtn").on("click", function (e) {
    e.preventDefault();
    $(".walletUpdateBtn").prop("disabled", true)
    var coinName = jQuery('input[name="coinName"]').val();
    var coin_id = jQuery('input[name="coin_id"]').val();
    var api_key_value = jQuery('input[name="wallet_key_update"]').val();
    // console.log(api_key_value);
    var password = jQuery('input[name="wallet_password_update"]').val();
    var invoice = jQuery('input[name="minimum_invoice_amount_edit"]').val();
    var rate = jQuery('input[name="exchange_rate_multiplier_edit"]').val();
    // console.log(invoice);

    //   if (invoice === '0') {
    //     $("#error-message-wallet-edit").text(
    //       "Minimum invoice value must be greater than 0"
    //     );
    //     return;
    // } 

    $("#error-message-wallet-edit").empty();
    if (api_key_value === "" || invoice === "" || rate === "") {
      $("#error-message-wallet-edit").text(
        "Please fill out all the fields to proceed."
      );
      $(".walletUpdateBtn").prop("disabled", false);
      return;
    }
    if (isNaN(parseFloat(invoice)) || parseFloat(invoice) <= 0) {
      $("#error-message-wallet-edit").text(
        "Minimum order value must be greater than 0."
      );
      $(".walletUpdateBtn").prop("disabled", false);
      return;
    }
    if (isNaN(parseFloat(rate)) || parseFloat(rate) <= 0) {
      $("#error-message-wallet-edit").text(
        "Order multiplier must be greater than 0."
      );
      $(".walletUpdateBtn").prop("disabled", false);
      return;
    }

    var frmData = {
      action: "coinremitter_wp_wallet_edit",
      security: my_ajax_obj.nonce,
      coin_id: coin_id,
      cointype: coinName,
      api_key_value: api_key_value,
      password: password,
      invoice: invoice,
      rate: rate,
    };
    console.log(frmData);


    jQuery.ajax({
      type: "post",
      url: ajaxurl,
      data: frmData,
      success: function (response) {
        console.log(response);
        if (typeof response == 'string') {
          response = JSON.parse(response)
        }
        var msg = response.msg;
        if (response.flag == 1) {
          var msg = response.msg;
          $("#edit-success-message-wallet").html(
            '<h6 class="success_msg"> wallet updated successfully</h6>'
          );
          window.location.reload();
        }
        if (response.flag === 0) {
          $(".walletUpdateBtn").prop("disabled", false)
          $("#error-message-wallet").html("<p>" + msg + "</p>");
          $("#error-message-wallet-edit").html(msg);
        }
      },
      error: function (jqXHR, exception) { },
    });
  });


  // coinremitter wallet Delete
  $('.walletdelete_pop').on('click', function (e) {
    e.preventDefault();

    var upname = $(this).data('upname');
    var id = $(this).data('walletid') || $('#coin_id').val();
    if (!id) {
      alert('Invalid wallet selected.');
      return;
    }

    if (confirm('It will remove ' + upname + ' wallet from your database only. It will not remove actual wallet from coinremitter.')) {
      $.ajax({
        url: (typeof my_ajax_obj !== 'undefined' && my_ajax_obj.ajaxurl) ? my_ajax_obj.ajaxurl : ajaxurl,
        type: 'POST',
        data: {
          action: 'coinremitter_wp_wallet_delete', // This is the action name to hook into in PHP
          security: (typeof my_ajax_obj !== 'undefined' && my_ajax_obj.nonce) ? my_ajax_obj.nonce : '',
          id: id
        },
        success: function (response) {
          if (typeof response == 'string') {
            response = JSON.parse(response);
          }
          if (response.success) {
            location.reload();
          } else {
            alert('Failed to delete wallet.');
          }
        },
        error: function () {
          alert('An error occurred.');
        }
      });
    }
  });

  // Remove focus on close buttons so browser doesn't log aria-hidden focus warning
  $('.btn-close, [data-bs-dismiss="modal"]').on('click', function () {
    $(this).trigger('blur');
  });

  // Reset modal state on close without reloading the entire page
  $('#exampleModal, #update_wallet').on('hidden.bs.modal', function () {
    $('#error-message-wallet, #add-success-message-wallet, #error-message-wallet-edit, #edit-success-message-wallet').empty();
    $('.add-wallet-btn, .walletUpdateBtn').prop('disabled', false);
  });


  $('#wallet_password , #minimum_invoice_amount , #exchange_rate_multiplier ,#wallet_password_update ,#minimum_invoice_amount_edit , #exchange_rate_multiplier_edit').on('keypress', function (event) {
    // var key = event.which || event.keyCode;
    // // Allow: backspace, delete, tab, escape, enter, and numbers
    // if (key >= 48 && key <= 57 || key === 8 || key === 9 || key === 27 || key === 13) {
    //     return true;
    // }
    // // Prevent any other keypress
    // event.preventDefault();

    // var value = $(this).val();

    //           // Allow only numbers and one decimal point
    //           var validValue = value.replace(/[^0-9.]/g, ''); // Remove non-numeric characters except decimal point

    //           // Check for multiple decimal points
    //           var decimalCount = validValue.split('.').length - 1;
    //           if (decimalCount > 1) {
    //               validValue = validValue.replace(/\.+$/, ''); // Remove extra decimal points at the end
    //               validValue = validValue.replace(/\.(?=.*\.)/, ''); // Remove additional decimal points
    //           }

    //           $(this).val(validValue);
  });

});

