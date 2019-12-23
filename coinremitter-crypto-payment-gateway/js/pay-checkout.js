
  jQuery(document).ready(function() {
    
    jQuery(document).on("click",".crpObj", function(e){
      e.preventDefault();
      var relVal = jQuery(this).attr('rel');
      jQuery('.crpObj').removeClass('active');
      jQuery(this).addClass('active');
      jQuery('#currency_type').val(relVal);
    });//jQuery('.crpObj').click
  });//jQuery(document).ready