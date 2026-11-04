jQuery(document).ready(function ($) {
    makeTimer();
});
function getUTCTime() {
    var tmLoc = new Date();
    return tmLoc.getTime() + tmLoc.getTimezoneOffset() * 60000;
}

var timerInterval = null;
var pollInterval = null;
timerInterval = setInterval(updateTime, 1000);
pollInterval = setInterval(function () {
    makeTimer();
}, 30000);
updateTime();

function updateTime() {
    var expiryVal = jQuery("#expiry_time").val();
    if (expiryVal && expiryVal !== "") {
        var current = getUTCTime();
        var expire = new Date(expiryVal).getTime();

        var date_diff = expire - current;
        if (date_diff <= 0) {
            clearInterval(timerInterval);
            var order_id = jQuery("#order_id").val();
            funExpire(order_id);
            return;
        }

        var hours = Math.floor(date_diff / (1000 * 60 * 60));
        var minutes = Math.floor((date_diff % (1000 * 60 * 60)) / (1000 * 60));
        var seconds = Math.floor((date_diff % (1000 * 60)) / 1000);

        var safeHours = Math.max(0, hours);
        var safeMinutes = Math.max(0, minutes);
        var safeSeconds = Math.max(0, seconds);

        jQuery("#hours").html(("0" + safeHours).slice(-2));
        jQuery("#minutes").html(("0" + safeMinutes).slice(-2));
        jQuery("#seconds").html(("0" + safeSeconds).slice(-2));
    }
}

var isCancelling = false;

function funExpire(order_id) {
    if (isCancelling) return;
    isCancelling = true;

    clearInterval(timerInterval);
    clearInterval(pollInterval);

    var base_url  = jQuery("#base_url").val();
    var order_key = jQuery("#order_key").val();
    var nonce     = (typeof coinremitter_invoice_params !== "undefined" && coinremitter_invoice_params.nonce) 
                    ? coinremitter_invoice_params.nonce 
                    : (typeof coinremitter_params !== "undefined" ? coinremitter_params.invoice_nonce : "");

    var frmData = {
        order_id:  order_id,
        order_key: order_key,
        security:  nonce
    };

    jQuery.ajax({
        type: "GET",
        dataType: "json",
        url: base_url + "/?wc-ajax=coinremitter_cancel_order",
        headers: {
            "X-Requested-With": "XMLHttpRequest"
        },
        data: frmData,
        success: function (html) {
            if (html.flag == 1 && html.url) {
                setTimeout(function () {
                    window.location = html.url;
                }, 1000);
            } else {
                window.location.reload();
            }
        },
        error: function () {
            window.location.reload();
        }
    });
}

function makeTimer() {
    var base_url = jQuery("#base_url").val();
    var addr = jQuery("#order_addr").html();
    if (!addr || addr.trim() === "") {
        return;
    }
    var nonce = (typeof coinremitter_invoice_params !== "undefined" && coinremitter_invoice_params.nonce) ? coinremitter_invoice_params.nonce : (typeof coinremitter_params !== "undefined" ? coinremitter_params.invoice_nonce : "");

    var frmData = {
        addr: addr.trim(),
        security: nonce
    };

    jQuery.ajax({
        type: "GET",
        dataType: "json",
        url: base_url + "/?wc-ajax=coinremitter_webhook_data",
        headers: {
            "X-Requested-With": "XMLHttpRequest"
        },
        data: frmData,
        success: function (html) {
            if (html.flag == 1) {
                if (html.expiry == 1) {
                    jQuery("#timer_status").empty();
                    jQuery(".timing-tooltip-btn").hide();
                    jQuery("#timer_status_payment").empty();
                    clearInterval(timerInterval);
                    jQuery("#timer_status_payment").append(
                        "<span>Awaiting Payment</span>"
                    );
                    jQuery("#timer_status_payment").append("<div></div>");
                } else {
                    if (jQuery("#timer_status").is(":empty")) {
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
                clearInterval(timerInterval);
                clearInterval(pollInterval);

                if (html.is_cancelled) {
                    window.location.href = html.link;
                    return;
                }
                // 1. Render Green Checkmark and updated amounts immediately
                if (html.data) {
                    jQuery("#Webhook_history").html(html.data);
                }
                if (html.paid_amount) {
                    jQuery("#paid_amount").html(html.paid_amount);
                }
                if (html.padding_amount) {
                    jQuery("#padding_amount").html(html.padding_amount);
                }
                if (html.coin_symbol) {
                    jQuery("#coin_symbol").html(html.coin_symbol);
                }

                // 2. Stop countdown timer & show confirmation banner
                jQuery("#timer_status").empty();
                jQuery(".timing-tooltip-btn").hide();
                jQuery("#timer_status_payment").html(
                    '<span style="color: #166534; font-weight: 600;">Payment Confirmed! Redirecting...</span>'
                );

                // 3. WAIT (Delay 3.5 seconds) so customer sees the Green Checkmark before auto-redirect
                setTimeout(function () {
                    window.location.href = html.link;
                }, 3500);
            }
        },
    });
}
