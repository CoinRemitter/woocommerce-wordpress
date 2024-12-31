jQuery(document).ready(function ($) {
  // coinremitter wallet add
  $("#csf-form").on("submit", function (event) {
    // $(".add-wallet-btn").prop("disabled", true);
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
    if (api_key === "" || wallet_pass === "") {
      $("#error-message-wallet").text(
        "Please fill out all the fields to proceed."
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
        wallet_key: api_key,
        wallet_password: wallet_pass,
        minimum_invoice_amount: minimum_invoice_amount,
        exchange_rate_multiplier: exchange_rate_multiplier,
      },
      success: function (response) {
        console.log(response);
        
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





  // function edit_data(apikey, pass, amount, rate, id, shortName) {
  //   $("#coin_id").val(id);
  //   $("#editcointypeid").text(shortName);
  //   $(".wallet_image").attr("src" ,"././images/"+shortName);
  //   $("#wallet_key_update").val(apikey);
  //   $("#wallet_password_update").val(pass);
  //   $("#minimum_invoice_amount_edit").val(amount);
  //   $("#exchange_rate_multiplier_edit").val(rate);
  //   $("#coinName").val(shortName);
  //   // console.log(apikey);
  // }

  // coinremitter wallet Update
  jQuery(".walletUpdateBtn").on("click", function (e) {
    e.preventDefault();

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
    if (api_key_value === "" || password === "") {
      $("#error-message-wallet-edit").text(
        "Please fill out all the fields to proceed."
      );
      return;
    }

    var frmData = {
      action: "coinremitter_wp_wallet_edit",
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
        var msg = response.msg;
        console.log(response);
        if (response.flag == 1) {
          var msg = response.msg;
          $("#edit-success-message-wallet").html(
            '<h6 class="success_msg"> wallet updated successfully</h6>'
          );
          $(".walletUpdateBtn").prop("disabled", true).text("Add Wallet");
          window.location.reload();
        }
        if (response.flag === 0) {
          $("#error-message-wallet").html("<p>" + msg + "</p>");
          $("#error-message-wallet-edit").html(msg);
        }
      },
      error: function (jqXHR, exception) {},
    });
  });
  

  $(".walletdelete_pop").on("click", function () {
    var walletd = $(this).data("walletid");
    var upname = $(this).data("upname");
    $(".wallet_coin").text(upname);   
    $("#coin_id").val(walletd);   
  });

  // coinremitter wallet Delete
 jQuery(document).ready(function($) {
    $('.walletdelete_pop').on('click', function(e) {
        e.preventDefault();

        var upname = $(this).data('upname');
        // var walletid = $(this).data('walletid');
        var id = $('#coin_id').val();

        if (confirm('It will remove ' + upname + ' wallet from your database only. It will not remove actual wallet from coinremitter.')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'coinremitter_wp_wallet_delete', // This is the action name to hook into in PHP
                    id: id
                },
                success: function(response) { 
                    if (response.success) {
                          location.reload();  
                    } else {
                        alert('Failed to delete wallet.');
                    }
                },
                error: function() {
                    alert('An error occurred.');
                }
            });
        }
    });

    $('.btn-close').on('click', function() {
      location.reload();
  });
});


$('#wallet_password , #minimum_invoice_amount , #exchange_rate_multiplier ,#wallet_password_update ,#minimum_invoice_amount_edit , #exchange_rate_multiplier_edit').on('keypress', function(event) {
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

