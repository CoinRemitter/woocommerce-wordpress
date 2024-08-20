
jQuery(document).ready(function () {
  
  // jQuery('.short_payment_block .crpObj').first().addClass('active');
  var relValue = jQuery('a.crpObj').attr('rel');
  jQuery('#currency_type').val(relValue);

    jQuery(document).on("click",".crpObj", function (e) {
      e.preventDefault();
        var relVal = jQuery(this).attr('rel');
        console.log(relVal);
        jQuery('.crpObj').removeClass('active');
        jQuery(this).addClass('active');
      // alert(relVal);
        jQuery('#currency_type').val(relVal);
        const currency_type = jQuery('#currency_type').val();
console.log(currency_type,'currency_type');
      // var data=jQuery('#currency_type').val();
      // alert(data);
    });//jQuery('.crpObj').click
    
    
  });

    jQuery(document).on("click",".payment_method_coinremitterpayments", function (e) {
      jQuery('.short_payment_block .crpObj').first().addClass('active'); // Add 'active' class to the first

  });