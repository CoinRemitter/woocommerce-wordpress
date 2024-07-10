
  jQuery(document).ready(function () {
    
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
  });//jQuery(document).ready


