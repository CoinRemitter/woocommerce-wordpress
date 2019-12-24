jQuery(document).ready(function() {
	jQuery('.woocommerce-Order-customerIP').replaceWith(function() {
		var ip = jQuery.trim(jQuery(this).text());
		return '<a href=\"https://myip.ms/info/whois/'+ip+'\" target=\"_blank\">' + ip + '</a>';
	});


});//jQuery(document).ready
jQuery(window).load(function(){
	
	jQuery('.OpenPopup').click(function(e){
		e.preventDefault();
	    jQuery('.VerifyBtn').attr("disabled", false);

		jQuery('.AddWalletPopup').fadeIn('slow');
	});//jQuery('.OpenPopup')

	jQuery('.VerifyBtn').click(function(){
		
		var pathname = window.location.pathname; 
		var url = window.location.origin;
		var reloadurl = url+pathname+'?page=coinremitter&new_wallet=true'
		var cointyp = jQuery('.CoinOptList').val();
		
		if(jQuery('.CoinOptList').val() == ''){
			alert('Please select Wallet');
			return false;
		}
		if(jQuery('#coinremitter'+cointyp+'api_key').val() == ''){
			alert('Please insert API Key');
			return false;
		}
		if(jQuery('#coinremitter'+cointyp+'password').val() == ''){
			alert('Please insert password');
			return false;
		}
		jQuery('#add_new').val('1');
		var cointype = jQuery('.CoinOptList').val();
		jQuery('#WalletFrm #currency_type').val(cointype);
		var frmData = {
	      'action': 'add_wallet',
	      'cointype': jQuery('.CoinOptList').val(),
	      'coinapikey': jQuery('#coinremitter'+cointype+'api_key').val(),
	      'coinpass': jQuery('#coinremitter'+cointype+'password').val(),
	      'frm_type': jQuery('#frm_type').val(),
	     };
	  
	    var succ=false;
	    var res;
	    jQuery('.VerifyBtn').attr("disabled", true);
	    jQuery.ajax({
	        type: "post",
	        url: ajaxurl,
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
	    		jQuery('.VerifyBtn').attr("disabled", false);

	            alert(msg);
	        },
	        success: function(html){
	    		jQuery('.VerifyBtn').attr("disabled", false);
	        	var response = JSON.parse(html);
	        	
	            if(response.flag === 0){
	            	jQuery('.frmError').html(response.msg);
	            }else{	
	            	jQuery('.frmError').html(''); 
	            	window.location.href = reloadurl;
	            }
	      	}
	    });
	});

	jQuery('.UpdateBtn').on('click', function () {
		jQuery('#update_wallet').val('1');
		jQuery('#add_new').val('');
		//return false;
		var pathname = window.location.pathname; 
		var url = window.location.origin;
		var reloadurl = url+pathname+'?page=coinremitter&up=true'
	    var cointyp = jQuery('#cointy_in_update').val();
	    var api_key_value = jQuery('#frmupdate #coinremitter'+cointyp+'api_key').val();
	    var api_password_value = jQuery('#frmupdate #coinremitter'+cointyp+'password').val();
	    var frmData = {
	      'action': 'verifyApi',
	      'cointype': cointyp,
	      'coinapikey':api_key_value,
	      'coinpass': api_password_value,
	      'frm_type': jQuery('#frm_type').val(),
	    };
		var succ=false;
	    var res;
	    jQuery.ajax({
	        type: "post",
	        url: ajaxurl,
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
	        	var response = JSON.parse(html);
	            if(response.flag === 1){
	            	jQuery('.frmUpdateError').html(''); 
	            	window.location.href = reloadurl;
	            	
	            }else{	
	            	jQuery('#frmupdate .frmUpdateError').html(response.msg);	
	            }
	      	}
	    });
	});
	jQuery('.WithdrawBtn').on('click', function () {
		var pageURL = jQuery(location).attr("href");
		var redirect = pageURL+'&withdraw=true';
	    var cointyp = jQuery('#frmwithdraw #currency_type').val();
	    var address = jQuery('#frmwithdraw #address').val();
	    var amount = jQuery('#frmwithdraw #amount').val();

	    if(address == ''){
			alert('Please insert Address');
			return false;
		}
		if(amount == ''){
			alert('Please insert Amount');
			return false;
		}else{
			if(isNaN(amount)){
				alert('Please insert proper amount value');
				return false;
			}
		}
	    var frmData = {
	      'action': 'withdraw',
	      'cointype': cointyp,
	      'address':address,
	      'amount': amount,
	      'frm_type': jQuery('#frm_type').val(),
	    };
		var succ=false;
	    var res;
	    jQuery.ajax({
	        type: "post",
	        url: ajaxurl,
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
	         	var response = JSON.parse(html);
	         	if(response.flag === 1){
	            	jQuery('.frmWithdrowError').html(''); 
	            	jQuery(location).attr('href', redirect)
	            	
	             }else{	
	           		jQuery('.frmWithdrowError').html(response.msg);	
	         	}
	      	}
	    });
	});

	jQuery('#amount').keyup(function(event){
		var coinDiv = jQuery('#frmwithdraw #currency_type').val();
		var rate = getTransactionfees(coinDiv);
		var processing_fees = parseFloat(rate.data.processing_fees);
    	var transaction_fees = parseFloat(rate.data.transaction_fees);
    	var processing_fees_type = rate.data.processing_fees_type;
    	var transaction_fees_type = rate.data.transaction_fees_type;
    	var fees_flat = 0;
    	var fees_percentage = 1;
        var amount = parseFloat(jQuery(this).val());
       
        if(processing_fees_type == 0){
            var prc_fees = processing_fees ;
            if(isNaN(prc_fees)){
                prc_fees = processing_fees;
            }
        }else if(processing_fees_type == 1){
            var prc_fees = amount*processing_fees/100 ;
            prc_fees =prc_fees;
            if(isNaN(prc_fees)){
                prc_fees = processing_fees+' %';
            }
        }
        if(transaction_fees_type==fees_flat){
            var trn_fees = transaction_fees ;
        }else if(transaction_fees_type==fees_percentage){
            var trn_fees = amount*transaction_fees/100 ;
        }else{
            var trn_fees = 0 ;
        }
        jQuery('#frmwithdraw #withprocessing').html(prc_fees);
        jQuery('#frmwithdraw #withpp').html('');
        var total_amount = amount+prc_fees+trn_fees;
        if(isNaN(total_amount)){
        	total_amount = 0;
        }
        jQuery('#frmwithdraw #withtotal').html(parseFloat(total_amount).toFixed(8));
    });


	jQuery('.ClosePopup').click(function(){
		jQuery('.AddWalletPopup').fadeOut('fast');
		jQuery('#pum_trigger_add_type_modal2').fadeOut('fast');
		jQuery('#pum_trigger_add_type_modal3').fadeOut('fast');
		jQuery('#WalletFrm').trigger('reset');
		jQuery('#frmwithdraw').trigger('reset');
		jQuery('.frmError').html(''); 
	});//jQuery('.OpenPopup')

	jQuery('.EditOpenPopup').click(function(){
		var coinDiv = jQuery(this).attr('data-rel');

		jQuery('#pum_trigger_add_type_modal2 #withdraw').val(1);
		jQuery('#cointy_in_update').val(coinDiv.toLowerCase());
		jQuery('#pum_trigger_add_type_modal2 .allDiv').hide();
		jQuery('#pum_trigger_add_type_modal2 .div'+coinDiv).show();
		jQuery('#pum_trigger_add_type_modal2 .CoinOpt').val(coinDiv);
		jQuery('#pum_trigger_add_type_modal2').fadeIn('slow');


	});//jQuery('.OpenPopup')

	jQuery('.WithdrawOpenPopup').click(function(){
		var coinDiv = jQuery(this).attr('data-rel');
		var rate = getTransactionfees(coinDiv);
		jQuery('#frmwithdraw #withprocessing').html(rate.data.processing_fees);
		jQuery('#frmwithdraw #withtransaction').html(rate.data.transaction_fees);
		jQuery('#frmwithdraw .withtp').html('');
	    jQuery('#frmwithdraw .withpp').html('');
		if(rate.data.transaction_fees_type == 1){
			jQuery('#frmwithdraw #withtp').html('%');
		}else{

		}
		if(rate.data.processing_fees_type == 1){
			jQuery('#frmwithdraw #withpp').html('%');
		}
		jQuery('#frmwithdraw #withtotal').html('0');
		jQuery('#frmwithdraw #currency_type').val(coinDiv.toLowerCase());
		jQuery('#pum_trigger_add_type_modal3 .allDiv').hide();
		jQuery('#pum_trigger_add_type_modal3 .div'+coinDiv).show();
		jQuery('#pum_trigger_add_type_modal3').fadeIn('slow');

	});

	jQuery('.CoinOptList').change(function(){
		var idxVal = jQuery(this).val();
		if(!idxVal){
			return false;
		}
		jQuery('#WalletFrm').children('#currency_type').val(idxVal);
		jQuery('.allDiv').hide();
		jQuery('.div'+idxVal).show();
		jQuery('#curren_type').val(idxVal);
	});//jQuery('.OpenPopup')
});//jQuery(window).load
function deleteWallete(){
if (confirm('Are you sure you want to delete wallet?')) {
	
	var coinDiv = jQuery('.CoinOpt').val();
	jQuery('#add_new').val('');
	jQuery('#pum_trigger_add_type_modal2 #delete_wallet').val(coinDiv);
	jQuery('#pum_trigger_add_type_modal2 #update_wallet').val('');
	
	//return false;
	var datarel = jQuery('#delete_wallet').val();
	
	jQuery('#pum_trigger_add_type_modal2 #coinremitter'+datarel+'api_key').val('');
	jQuery('#pum_trigger_add_type_modal2 #coinremitter'+datarel+'password').val('');

	setTimeout(function(){
		var frmData = {
	      'action': 'deleteCoinData',
	      'cointype': datarel,
	    };
	    
		jQuery.ajax({
	        type: "post",
	        dataType: 'json',
	        url: ajaxurl,
	        data: frmData,
	          success: function(html){
	            if(html['flag'] == 1){
	            	location.href=html['redirect'];
	            }else{
	            	alert('Something went wrong please try after sometime.');
	            }
	      }
	    });//jQuery.ajax
	},1000);
	
		} else {
		    return false;

		}
		
	}//deletWallete
	
	function addCryptoCurr(Verify){
		var idxVal= jQuery('#CoinOpt').val();
		var frmdata = {
			'action': '__construct',
			'verify': Verify,
			'currname': idxVal,
			'auth_key': jQuery('#coinremitter'+idxVal+'api_key').val(),
			'auth_pass': jQuery('#coinremitter'+idxVal+'password').val(),
		};

		jQuery.ajax({
		    type: "post",
		     url: ajaxurl,
		    data: frmdata,
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
		        alert(jqXHR.status);
		    },
		    success: function(html){
		    	jQuery('.ErrMsg').html(html.msg);
				if(html.data ==1){
					jQuery('#WalletAuthSbt').show();
				}

				if(html.data ==2){
					disWallets();

					jQuery('#WalletAuth').trigger('reset');
					openAuthFrm();
				}	
			}
		});//jQuery.ajax
	}//addCryptoCurr
	function getTransactionfees(Coin){
		var frmdata = {
			'action': 'transactionfees',
			'cointype': Coin,
			};
		var res;
		res = jQuery.ajax({
	      type: "Post",
	      url: ajaxurl,
	      data: frmdata,
	      async: false,
	      success: function(data){
	        return data;
	      }
	    });
	    return jQuery.parseJSON(res.responseText);
	}