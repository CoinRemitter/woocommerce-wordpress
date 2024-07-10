jQuery(document).ready(function () {
  jQuery(".woocommerce-Order-customerIP").replaceWith(function () {
    var ip = jQuery.trim(jQuery(this).text());
    return (
      '<a href="https://myip.ms/info/whois/' +
      ip +
      '" target="_blank">' +
      ip +
      "</a>"
    );
  });
}); //jQuery(document).ready
jQuery(window).load(function () {
  jQuery(".OpenPopup").click(function (e) {
    e.preventDefault();
    jQuery(".VerifyBtn").attr("disabled", false);
    jQuery(".AddWalletPopup").fadeIn("slow");
  }); //jQuery('.OpenPopup')

  jQuery(".VerifyBtn").click(function () {
    var pathname = window.location.pathname;
    var url = window.location.origin;
    var reloadurl = url + pathname + "?page=coinremitter&new_wallet=true";
    var cointyp = jQuery(".CoinOptList").val();

    if (jQuery(".CoinOptList").val() == "") {
      alert("Please select Wallet");
      return false;
    }
    if (jQuery("#coinremitter" + cointyp + "api_key").val() == "") {
      alert("Please insert API Key");
      return false;
    }
    if (jQuery("#coinremitter" + cointyp + "password").val() == "") {
      alert("Please insert password");
      return false;
    }
    if (jQuery("#coinremitter" + cointyp + "minInvoiceValue").val() == "") {
      alert("Please insert minimum invoice value");
      return false;
    }
    if (
      jQuery("#coinremitter" + cointyp + "exchangeRateMultiplier").val() == ""
    ) {
      alert("Please insert exchange rate multiplier");
      return false;
    }
    jQuery(this).prop("disabled", true);
    jQuery(".add_spinner").addClass("is-active");
    jQuery("#add_new").val("1");
    var cointype = jQuery(".CoinOptList").val();
    jQuery("#WalletFrm #currency_type").val(cointype);
    var coin = jQuery(".CoinOptList").val().toUpperCase();
    var frmData = {
      action: "coinremitter_add_wallet",
      cointype: coin,
      coinapikey: jQuery("#coinremitter" + cointype + "api_key").val(),
      coinpass: jQuery("#coinremitter" + cointype + "password").val(),
      coinmininvoicevalue: jQuery(
        "#coinremitter" + cointyp + "minInvoiceValue"
      ).val(),
      coinexchangeratemult: jQuery(
        "#coinremitter" + cointyp + "exchangeRateMultiplier"
      ).val(),
      frm_type: jQuery("#frm_type").val(),
    };

    var succ = false;
    var res;
    jQuery(".VerifyBtn").attr("disabled", true);
    jQuery.ajax({
      type: "post",
      /*dataType: 'json',*/
      url: ajaxurl,
      data: frmData,
      error: function (jqXHR, exception) {
        var msg = "";
        console.log(jqXHR);
        if (jqXHR.status === 0) {
          msg = "Not connect.\n Verify Network.";
        } else if (jqXHR.status == 404) {
          msg = "Requested page not found. [404]";
        } else if (jqXHR.status == 500) {
          msg = "Internal Server Error [500].";
        } else if (exception === "parsererror") {
          msg = "Requested JSON parse failed.";
        } else if (exception === "timeout") {
          msg = "Time out error.";
        } else if (exception === "abort") {
          msg = "Ajax request aborted.";
        } else {
          msg = "Uncaught Error.\n" + jqXHR.responseText;
        }
        jQuery(".VerifyBtn").attr("disabled", false);

        alert(msg);
      },
      success: function (html) {
        jQuery(".VerifyBtn").attr("disabled", false);
        var response = JSON.parse(html);
        console.log("response", response);
        if (response.flag === 0) {
          jQuery(".frmError").html(response.msg);
        } else {
          jQuery(".frmError").html("");
          window.location.href = reloadurl;
        }
        jQuery(this).prop("disabled", false);
        jQuery(".add_spinner").removeClass("is-active");
      },
    });
  });

  jQuery(".UpdateBtn").on("click", function () {
    jQuery(this).prop("disabled", true);
    jQuery("#update_wallet").val("1");
    jQuery("#add_new").val("");
    //return false;
    jQuery(".update_spinner").addClass("is-active");
    var pathname = window.location.pathname;
    var url = window.location.origin;
    var reloadurl = url + pathname + "?page=coinremitter&up=true";
    var cointyp = jQuery("#cointy_in_update").val();
    var api_key_value = jQuery(
      "#frmupdate #coinremitter" + cointyp + "api_key"
    ).val();
    var api_password_value = jQuery(
      "#frmupdate #coinremitter" + cointyp + "password"
    ).val();
    var min_invoice_value = jQuery(
      "#frmupdate #coinremitter" + cointyp + "minInvoiceValue"
    ).val();
    var exchange_rate_mul = jQuery(
      "#frmupdate #coinremitter" + cointyp + "exchangeRateMultiplier"
    ).val();
    var frmData = {
      action: "coinremitter_verifyApi",
      cointype: cointyp.toUpperCase(),
      coinapikey: api_key_value,
      coinpass: api_password_value,
      coinmininvoicevalue: min_invoice_value,
      coinexchangeratemult: exchange_rate_mul,
      frm_type: jQuery("#frm_type").val(),
    };
    var succ = false;
    var res;
    jQuery.ajax({
      type: "post",
      /*dataType: 'json',*/
      url: ajaxurl,
      data: frmData,
      error: function (jqXHR, exception) {
        var msg = "";
        if (jqXHR.status === 0) {
          msg = "Not connect.\n Verify Network.";
        } else if (jqXHR.status == 404) {
          msg = "Requested page not found. [404]";
        } else if (jqXHR.status == 500) {
          msg = "Internal Server Error [500].";
        } else if (exception === "parsererror") {
          msg = "Requested JSON parse failed.";
        } else if (exception === "timeout") {
          msg = "Time out error.";
        } else if (exception === "abort") {
          msg = "Ajax request aborted.";
        } else {
          msg = "Uncaught Error.\n" + jqXHR.responseText;
        }
        alert(msg);
      },
      success: function (html) {
        var response = JSON.parse(html);
        if (response.flag === 1) {
          jQuery(".frmUpdateError").html("");
          window.location.href = reloadurl;
        } else {
          jQuery("#frmupdate .frmUpdateError").html(response.msg);
        }
        jQuery(".UpdateBtn").prop("disabled", false);
        jQuery(".update_spinner").removeClass("is-active");
      },
    });
  });
  jQuery(".WithdrawBtn").on("click", function () {
    var pageURL = jQuery(location).attr("href");
    var redirect = pageURL + "&withdraw=true";
    var cointyp = jQuery("#frmwithdraw #currency_type").val().toUpperCase();
    var address = jQuery("#frmwithdraw #address").val();
    var amount = jQuery("#frmwithdraw #amount").val();

    if (address == "") {
      alert("Please insert Address");
      return false;
    }
    if (amount == "") {
      alert("Please insert Amount");
      return false;
    } else {
      if (isNaN(amount)) {
        alert("Please insert proper amount value");
        return false;
      }
    }
    var frmData = {
      action: "coinremitter_withdraw",
      cointype: cointyp,
      address: address,
      amount: amount,
      frm_type: jQuery("#frm_type").val(),
    };
    var succ = false;
    var res;
    jQuery.ajax({
      type: "post",
      url: ajaxurl,
      data: frmData,
      error: function (jqXHR, exception) {
        var msg = "";
        if (jqXHR.status === 0) {
          msg = "Not connect.\n Verify Network.";
        } else if (jqXHR.status == 404) {
          msg = "Requested page not found. [404]";
        } else if (jqXHR.status == 500) {
          msg = "Internal Server Error [500].";
        } else if (exception === "parsererror") {
          msg = "Requested JSON parse failed.";
        } else if (exception === "timeout") {
          msg = "Time out error.";
        } else if (exception === "abort") {
          msg = "Ajax request aborted.";
        } else {
          msg = "Uncaught Error.\n" + jqXHR.responseText;
        }
        alert(msg);
      },
      success: function (html) {
        var response = JSON.parse(html);
        if (response.flag === 1) {
          jQuery(".frmWithdrowError").html("");
          jQuery(location).attr("href", redirect);
        } else {
          jQuery(".frmWithdrowError").html(response.msg);
        }
      },
    });
  });

  jQuery(".ClosePopup").click(function () {
    jQuery(".AddWalletPopup").fadeOut("fast");
    jQuery("#pum_trigger_add_type_modal2").fadeOut("fast");
    jQuery("#pum_trigger_add_type_modal3").fadeOut("fast");
    jQuery("#WalletFrm").trigger("reset");
    jQuery("#frmwithdraw").trigger("reset");
    jQuery(".frmError").html("");
  }); //jQuery('.OpenPopup')

  jQuery(".EditOpenPopup").click(function () {
    var coinDiv = jQuery(this).attr("data-rel");
    jQuery("#pum_trigger_add_type_modal2 #withdraw").val(1);
    jQuery(".CurrencyName").html("Wallet " + coinDiv.toUpperCase());
    jQuery("#cointy_in_update").val(coinDiv.toLowerCase());
    jQuery("#pum_trigger_add_type_modal2 .allDiv").hide();
    jQuery("#pum_trigger_add_type_modal2 .div" + coinDiv).show();
    jQuery("#pum_trigger_add_type_modal2 .CoinOpt").val(coinDiv);
    jQuery("#pum_trigger_add_type_modal2").fadeIn("slow");
  });

  jQuery(".deleteBtn").click(function () {
    var coinDiv = jQuery(this).attr("data-rel");
    jQuery("#frmwithdraw #currency_type").val(coinDiv.toLowerCase());
    jQuery(".wallet_coin").text(coinDiv.toUpperCase());
    jQuery("#pum_trigger_add_type_modal3 .allDiv").hide();
    jQuery("#pum_trigger_add_type_modal3 .div" + coinDiv).show();
    jQuery("#pum_trigger_add_type_modal3").fadeIn("slow");
  });

  jQuery(".CoinOptList").change(function () {
    var idxVal = jQuery(this).val();
    if (!idxVal) {
      return false;
    }
    jQuery("#WalletFrm").children("#currency_type").val(idxVal);
    jQuery(".allDiv").hide();
    jQuery(".div" + idxVal).show();
    jQuery("#curren_type").val(idxVal);
  }); //jQuery('.OpenPopup')
}); //jQuery(window).load
function deleteWallete(e) {
  jQuery(".delete_spinner").addClass("is-active");
  jQuery(e).prop("disabled", true);
  var coinDiv = jQuery(".CoinOpt").val();
  jQuery("#add_new").val("");
  jQuery("#pum_trigger_add_type_modal2 #delete_wallet").val(coinDiv);
  jQuery("#pum_trigger_add_type_modal2 #update_wallet").val("");
  var datarel = jQuery("#frmwithdraw #currency_type").val();
  setTimeout(function () {
    var frmData = {
      action: "coinremitter_deleteCoinData",
      cointype: datarel,
    };

    jQuery.ajax({
      type: "post",
      dataType: "json",
      url: ajaxurl,
      data: frmData,
      success: function (html) {
        if (html["flag"] == 1) {
          location.href = html["redirect"];
        } else {
          alert(html.msg);
        }
        jQuery(e).prop("disabled", false);
        jQuery(".delete_spinner").removeClass("is-active");
      },
    }); //jQuery.ajax
  }, 1000);
} //deletWallete

function addCryptoCurr(Verify) {
  //return false;
  var idxVal = jQuery("#CoinOpt").val();
  var frmdata = {
    action: "__construct",
    verify: Verify,
    currname: idxVal,
    auth_key: jQuery("#coinremitter" + idxVal + "api_key").val(),
    auth_pass: jQuery("#coinremitter" + idxVal + "password").val(),
  };
  jQuery.ajax({
    type: "post",
    url: ajaxurl,
    data: frmdata,
    error: function (jqXHR, exception) {
      var msg = "";

      if (jqXHR.status === 0) {
        msg = "Not connect.\n Verify Network.";
      } else if (jqXHR.status == 404) {
        msg = "Requested page not found. [404]";
      } else if (jqXHR.status == 500) {
        msg = "Internal Server Error [500].";
      } else if (exception === "parsererror") {
        msg = "Requested JSON parse failed.";
      } else if (exception === "timeout") {
        msg = "Time out error.";
      } else if (exception === "abort") {
        msg = "Ajax request aborted.";
      } else {
        msg = "Uncaught Error.\n" + jqXHR.responseText;
      }
      alert(jqXHR.status);
    },
    success: function (html) {
      jQuery(".ErrMsg").html(html.msg);
      if (html.data == 1) {
        jQuery("#WalletAuthSbt").show();
      }
      if (html.data == 2) {
        disWallets();
        jQuery("#WalletAuth").trigger("reset");
        openAuthFrm();
      }
    },
  }); //jQuery.ajax
} //addCryptoCurr

    
  jQuery(document).on("click",".crpObj", function (e) {
    e.preventDefault();
      var relVal = jQuery(this).attr('rel');
      console.log(relVal);
      jQuery('.crpObj').removeClass('active');
      jQuery(this).addClass('active');
      jQuery('#currency_type').val(relVal);
      jQuery('#currency_type').val(relVal);
      var site_url = jQuery('#site_url').val(); 

      jQuery.ajax({
        url: site_url+'/wp-admin/admin-ajax.php', 
        type: 'POST',
        data: {
            action: 'store_rel_value', 
            rel_value: relVal 
        },
        success: function(response) {
            // AJAX request was successful
            console.log('Value stored successfully:', response);
        },
        error: function(xhr, status, error) {
            // Handle errors here
            console.error('Error occurred:', error);
        }
    });

  });

// const settings = window.wc.wcSettings.getSetting("coinremitterpayments");
const settings = window.wc.wcSettings.getPaymentMethodData("coinremitterpayments"); 
const label = window.wp.htmlEntities.decodeEntities(settings.title) || window.wp.i18n.__("Pay With Cryptocurrency", "coinremitterpayments");
const des = settings.description;
console.log(des);
// const enabledCurrency = settings.enabledCurrency ? settings.enabledCurrency : "<span class='wallet_error_checkout'>Wallet Not Found. Please Add Wallet</span>";
const enabledCurrency = settings.enabledCurrency;
console.log(settings);
if (settings.enabledCurrency && settings.enabledCurrency.length > 0) {
  wallet_data = settings.enabledCurrency;
} else {
  wallet_data = settings;
}
const htmlToElem = ( html ) => wp.element.RawHTML( { children: html } );

const Block_Gateway = {
  name: "coinremitterpayments",
  label: label,
  content: htmlToElem(wallet_data),
  // content: Object( window.wp.element.createElement )( Content, null ),
  edit: Object(window.wp.element.createElement)(htmlToElem, null),
  canMakePayment: () => true,
  ariaLabel: label,
  supports: {
    features: settings.supports,
  },
  description: settings.description,
};
window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);

jQuery(document).on('click','.wc-block-components-checkout-place-order-button',function (e) {
  console.log("called");
  return false;
  
})