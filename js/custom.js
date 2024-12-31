var relValue = jQuery("a.crpObj").attr("rel");
jQuery("#currency_type").val(relValue);

jQuery(document).on("click", ".crpObj", function (e) {
    e.preventDefault();
    var relVal = jQuery(this).attr("rel");
    console.log(relVal);
    jQuery(".crpObj").removeClass("active");
    jQuery(this).addClass("active");
    jQuery("#currency_type").val(relVal);
    const currency_type = jQuery("#currency_type").val();
    console.log(currency_type, "currency_type");
});

jQuery(document).ready(function ($) {
    jQuery(document).on("click", "input[name='coins']", function (e) {
        var relVal = $(this).closest(".coin_data").attr("rel");
        var site_url = jQuery("#site_url").val();
        console.log(relVal);

        // AJAX request
        jQuery.ajax({
            url: site_url + "/wp-admin/admin-ajax.php",
            type: "POST",
            data: {
                action: "store_rel_value",
                rel_value: relVal,
            },
            success: function (response) {
                console.log("Value stored successfully:", response);
            },
            error: function (xhr, status, error) {
                console.error("Error occurred:", error);
            },
        });
    });

    jQuery(".addr_copy").click(function () {
        //   var value = jQuery(this).attr("data-copy-detail");
        var value = jQuery(this).find("h4").attr("data-copy-detail");
        var $temp = jQuery("<input>");
        jQuery("body").append($temp);
        $temp.val(value).select();
        document.execCommand("copy");
        jQuery(".cr-plugin-copy").fadeIn(1000).delay(1500).fadeOut(1000);
        $temp.remove();
    });

    jQuery(".amount_copy").click(function () {
        //   var value = jQuery(this).attr("data-copy-detail");
        var value = jQuery(this).find("h4").attr("data-copy-amount");
        var $temp = jQuery("<input>");
        jQuery("body").append($temp);
        $temp.val(value).select();
        document.execCommand("copy");
        jQuery(".cr-plugin-copy").fadeIn(1000).delay(1500).fadeOut(1000);
        $temp.remove();
    });

});