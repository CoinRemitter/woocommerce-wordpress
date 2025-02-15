jQuery(document).ready(function ($) {
    makeTimer();
});
function getUTCTime() {
    var tmLoc = new Date();
    return tmLoc.getTime() + tmLoc.getTimezoneOffset() * 60000;
}

var interval = null;
interval = setInterval("updateTime()", 1000);
setInterval(function () {
    makeTimer();
}, 30000);
updateTime();
function updateTime() {
    if (jQuery("#expiry_time").val() != "") {
        var current = getUTCTime();
        var expire = new Date(jQuery("#expiry_time").val()).getTime();
        // console.log(expire);

        var date_diff = expire - current;
        var hours = Math.floor(date_diff / (1000 * 60 * 60));
        var minutes = Math.floor((date_diff % (1000 * 60 * 60)) / (1000 * 60));
        var seconds = Math.floor((date_diff % (1000 * 60)) / 1000);
        if (hours < 0 && minutes < 0 && seconds < 0) {
            var order_id = jQuery("#order_id").val();
            funExpire(order_id);
            return;
        } else {
            jQuery("#hours").html(("0" + hours).slice(-3));
            jQuery("#minutes").html(("0" + minutes).slice(-2));
            jQuery("#seconds").html(("0" + seconds).slice(-2));
        }
    }
}
function funExpire(order_id) {
    var base_url = jQuery("#base_url").val();

    var frmData = {
        order_id: order_id,
    };
    console.log(frmData);

    jQuery.ajax({
        type: "GET",
        dataType: "json",
        url: base_url + "/?wc-ajax=coinremitter_cancel_order",
        data: frmData,
        success: function (html) {
            if (html.flag == 1) {
                var redirect = html.url.replace(/amp;/g, "");
                console.log(redirect);
                setTimeout(function () {
                    window.location = html.url;
                }, 1000);
                window.location = html.url;
                // console.log(html);
            }
        },
    });
}

function makeTimer() {
    var base_url = jQuery("#base_url").val();
    var addr = jQuery("#order_addr").html();
    var frmData = {
        addr: addr,
    };

    jQuery.ajax({
        type: "GET",
        dataType: "json",
        url: base_url + "/?wc-ajax=coinremitter_webhook_data",
        data: frmData,
        success: function (html) {
            // console.log(html);

            if (html.flag == 1) {
                if (html.expiry == 1) {
                    jQuery("#timer_status").empty();
                    jQuery(".timing-tooltip-btn").hide();
                    jQuery("#timer_status_payment").empty();
                    clearInterval(interval);
                    jQuery("#timer_status_payment").append(
                        "<span>Awaiting Payment</span>"
                    );
                    jQuery("#timer_status_payment").append("<div></div>");
                } else {
                    if (jQuery("#timer_status").is(":empty")) {
                        // alert('bbbbbbbbbbyyy');
                        // jQuery("#timer_status").append("<span>This order will expire after</span>");
                        jQuery("#timer_status").append(
                            '<span id="hours">00</span>:<span id="minutes">00</span>:<span id="seconds">00</span>'
                        );
                    }
                }
                jQuery("#Webhook_history").html(html.data);
                jQuery("#coin_symbol").html(html.coin_symbol);
                jQuery("#paid_amount").html(html.paid_amount);
                jQuery("#padding_amount").html(html.padding_amount);
            } else if (html.flag == 2) {
                window.location.href = html.link;
            }
        },
    });
}
