<?php 
$wallet_image = CR_PLUGIN_PATH . './images/';
?>

<script>
    jQuery(document).ready(function($) {
        $(".EditOpenPopup").on("click", function() {
            var shortName = $(this).data("rel");
            var id = $(this).data("id");
            var apikey = $(this).data("key");
            var pass = $(this).data("password");
            var amount = $(this).data("amount");
            var rate = $(this).data("rate");

            edit_data(apikey, pass, amount, rate, id, shortName);
            jQuery("#update_wallet").modal("show");
        });

        var walletImagePath = "<?php echo $wallet_image; ?>";

        function edit_data(apikey, pass, amount, rate, id, shortName) {
            $("#coin_id").val(id);
            $("#editcointypeid").text(shortName);
            var shortName = shortName.toLowerCase(); 
            var imagePath = walletImagePath + shortName;
            $(".wallet_image").attr("src", imagePath + ".png");
            $("#wallet_key_update").val(apikey);
            $("#wallet_password_update").val(pass);
            $("#minimum_invoice_amount_edit").val(amount);
            $("#exchange_rate_multiplier_edit").val(rate);
            $("#coinName").val(shortName);
            // console.log(apikey);
        }
    });
</script>