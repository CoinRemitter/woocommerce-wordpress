  
  var interval;
  var ajaxUrl = jQuery('#ajaxurl').html();
  var siteurl = jQuery('#siteurl').html();
  var qString = jQuery('#qString').html();
  var pCoin = jQuery('#pCoin').html();
  var ordid = jQuery('#ordid').html();
  var usdAmount = jQuery('#usdAmount').html();
	jQuery(document).ready(function(){
		/*Open QR popup*/
    
    
    
    var BoxData = jQuery('#BoxData').html();
    var BoxImgPath = jQuery('#BoxImgPath').html();
    var BoxLogpImgPath = jQuery('#BoxLogpImgPath').html();
    var BoxExt = jQuery('#BoxExt').html();
    //console.log("' . base64_encode($ext) . '");
    coinremitter_cryptobox_update_page(BoxData,BoxImgPath,BoxLogpImgPath,BoxExt);
       
    setTimeout(function(){
        var coinamt = jQuery(".coinremittercoin_usercopy_amount").html();
        var walletAdd = jQuery(".coinremittercoin_userwallet_address").html();
        jQuery(".payment-add-notes").html("<b>"+coinamt+"</b> paid successfully to <b>"+walletAdd+"</b>");
    }, 5000);

		jQuery('#qr-btn').click(function(){
			//alert('XXX');
       jQuery('.cryptocurrancy-popup').addClass('popup-open');

       jQuery('.overlay').addClass('overlay-show');
    });
        jQuery('#popup-close').click(function(){
           jQuery('.cryptocurrancy-popup').removeClass('popup-open');
           jQuery('.overlay').removeClass('overlay-show');
        });
        
        var runpayment = jQuery('#ps').val();
        //alert(runpayment);
        if(runpayment){
          interval =setInterval(function(){
            getPaymentResponse();
          },30000);  
        }
        
    jQuery('.entry-title' ).text('Pay Now - coinremitter-woocommerce' );
    jQuery( '.woocommerce-thankyou-order-received' ).remove();
        
        //getPaymentResponse();
	});//jQuery(document).ready



  function getPaymentResponse(){
    
    var pAddress = jQuery('.coinremittercoin_userwallet_address').html();
    var pAmount = jQuery('.coinremittercoin_useramount').html();
    
    var frmData = {
      'action': 'paymentResponse',
      /*'qstring': qString,
      'coinname': pCoin,
      'paddress': pAddress,
      'pAmount': pAmount,*/
      'ordid': ordid,
      //'usdamount': usdAmount,
    };
    //alert(ajaxUrl);
    jQuery.ajax({
        type: "post",
        /*dataType: 'json',*/
        url: ajaxUrl,
        data: frmData,
        error: function (jqXHR, exception) {
            var msg = '';
            if (jqXHR.status === 0) {
                msg = 'Not connect.\n Verify Network.';
            } else if (jqXHR.status == 404) {
                msg = 'Requested page not found. [404]';
            } else if (jqXHR.status == 500) {
                msg = 'Internal Server Error [500].';
            } else if (exception === 'parsererror') {
                msg = 'Requested JSON parse failed.';
            } else if (exception === 'timeout') {
                msg = 'Time out error.';
            } else if (exception === 'abort') {
                msg = 'Ajax request aborted.';
            } else {
                msg = 'Uncaught Error.\n' + jqXHR.responseText;
            }
            alert(msg);
        },
        success: function(html){
            
            //alert(html);

            jQuery('#responsview').html(html);
            //console.log(html);
            //console.log(html.flag);
            //if(html.flag == 1)
            if(html == 1){
              clearInterval(interval); // stop the interval

                var coinamt = jQuery(".coinremittercoin_usercopy_amount").html();
                var walletAdd = jQuery(".coinremittercoin_userwallet_address").html();
                //alert("<b>"+coinamt+"</b> paid successfully to <b>"+walletAdd+"</b>");
                //jQuery(".payment-add-notes").html("<b>"+coinamt+"</b> paid successfully to <b>"+walletAdd+"</b>");

              jQuery('.HideByStatus').hide();
              jQuery('.loaderIcon').hide();
              jQuery('.cryptocurrancy-address').hide();
              jQuery('.cryptocurrancy-notes').hide();
              jQuery('.DisThanksMsg').show();
              jQuery('.payment-add-notes').show();
              jQuery('#popup-close').trigger('click');
              /*setTimeout(function(){
                location.href = siteurl;
              },5000);*/
              runpayment = false;
              //alert(runpayment);
            }  //if
            
      }
    });//jQuery.ajax
  }//getPaymentResponse

  function refreshPage(){
    window.location.reload();
}