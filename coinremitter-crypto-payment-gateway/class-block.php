<?php



use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class My_Custom_Gateway_Blocks extends AbstractPaymentMethodType
{

    public function __construct( $payment_request_configuration = null ) {
		// add_action( 'woocommerce_rest_checkout_process_payment_with_context', [ $this, 'add_payment_request_order_meta' ], 8, 2 );
        $priority = 10;
		add_action('woocommerce_view_order', 				'coinremitter_wc_payment_history', $priority, 1);
		add_action('woocommerce_email_after_order_table', 	'coinremitter_wc_payment_link', 15, 2);
		add_filter('woocommerce_currency_symbol', 			'coinremitter_wc_currency_symbol', $priority, 2);
		add_filter('wc_get_price_decimals',                'coinremitter_wc_currency_decimals', $priority, 1);
		add_action('woocommerce_after_order_notes', 'coinremitter_custom_checkout_field');
		// echo '<pre>'; print_r($_POST['radio-control-wc-payment-method-options']);die;
		if (isset($_POST['currency_type']) && $_POST['currency_type'] != "") {
			// echo '<pre>'; print_r($_POST);die;

		}
        add_filter('woocommerce_get_return_url', 'coinremitter_override_return_url', 10, 3);
		add_action('woocommerce_checkout_process', 'coinremitter_customised_checkout_field_process');
		add_action('woocommerce_checkout_update_order_meta', 'coinremitter_custom_checkout_field_update_order_meta');
		remove_action('woocommerce_order_details_after_order_table', 'woocommerce_order_again_button');
		add_action('after_woocommerce_pay', 'coinremitter_show_order_details', 10);
		add_action('woocommerce_order_details_after_order_table', 'coinremitter_nolo_custom_field_display_cust_order_meta', 10, 1);
        add_action('wp_enqueue_scripts', 'coinremitter_select_payment_coin');
        add_action('add_meta_boxes', 'coinremitter_cd_meta_box_add');
        add_action('wc_ajax_coinremitter_cancel_order', 'coinremitter_cancel_order');
	}

    private $methods;
    protected $name = 'coinremitterpayments'; // your payment gateway name

    public function initialize()
    {
        $this->settings = get_option('woocommerce_coinremitterpayments_settings', []);
        $this->methods = new WC_Gateway_CoinRemitter();
    }

    public function is_active()
    {
        return $this->methods->is_available();
    }

    public function get_payment_method_script_handles()
    {

        wp_register_script(
            'my_custom_gateway-blocks-integration',
            plugin_dir_url(__FILE__) . './js/crypto-custom.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('my_custom_gateway-blocks-integration');
        }
        return ['my_custom_gateway-blocks-integration'];
    }
   
    public function get_payment_method_data()
    {

        ?>

        <?PHP

        $html = '';
        $html .= '<div>';
        $html .= '<input type="hidden" rel="" name="currency_type" id="currency_type" value="" >';
        $html .= '<input type="hidden" rel="" name="site_url" id="site_url" value="' . site_url() . '" >';
        $html .= '</div>';
        $available_coins = array();
        $cryptobox_localisation_coinremitter    = array(
            "name"        => "English",
            "button"            => "Click Here if you have already sent %coinNames%",
            "msg_not_received"     => "<b>%coinNames% have not yet been received.</b><br>If you have already sent %coinNames% (the exact %coinName% sum in one payment as shown in the box below), please wait a few minutes to receive them by %coinName% Payment System. If you send any other sum, Payment System will ignore the transaction and you will need to send the correct sum again, or contact the site owner for assistance.",
            "msg_received"          => "%coinName% Payment System received %amountPaid% %coinLabel% successfully !",
            "msg_received2"     => "%coinName% Captcha received %amountPaid% %coinLabel% successfully !",
            "payment"            => "Select Payment Method",
            "pay_in"            => "Payment in %coinName%",
            "loading"            => "Loading ..."
        );
        global $woocommerce;
        $total_amt = 0;
        $count = 0;
        if (!defined('COINREMITTER_CRYPTOBOX_LOCALISATION')) define('COINREMITTER_CRYPTOBOX_LOCALISATION', wp_json_encode($cryptobox_localisation_coinremitter));
        $localisation = json_decode(COINREMITTER_CRYPTOBOX_LOCALISATION, true);
        $checkCoin = 0;
        $currancy_type = get_woocommerce_currency();
        if (isset($woocommerce->cart)) {
            $total_amt = $woocommerce->cart->total;
        }
        $getAction = coinremitter_getActCoins();
        // print_r($getAction);
        if (is_array($getAction)) {
        foreach ($getAction as $crypto) {
            // echo '<pre>'; print_r($crypto);
            $symbol = $crypto['symbol'];
            $CurrAPIKey = get_option(COINREMITTER . strtolower($symbol) . 'api_key');
            $CurrPassword = get_option(COINREMITTER . strtolower($symbol) . 'password');
            $Currdeleted = get_option(COINREMITTER . strtolower($symbol) . 'is_deleted');
            $multiplier = get_option(COINREMITTER . strtolower($symbol) . 'exchange_rate_multiplier');
            $minimum_invoice_val = get_option(COINREMITTER . strtolower($symbol) . 'min_invoice_value');
            // echo $minimum_invoice_val . "<br>";
            $public_key     = $CurrAPIKey;
            $private_key     = $CurrPassword;
            if ($private_key && $public_key && $Currdeleted != 1) {
                // $all_keys[$v] = array("api_key" => $public_key,  "password" => $private_key);
                if ($total_amt > 0) {
                    if ($multiplier == 0 || $multiplier == '')
                        $multiplier = 1;

                    if ($minimum_invoice_val == '' || $minimum_invoice_val == null)
                        $minimum_invoice_val = 0.0001;

                    $total_amount = $total_amt * $multiplier;

                    $rate_param = [
                        'coin' => strtoupper($symbol),
                        'fiat_symbol' => $currancy_type, 
                        'fiat_amount' => $total_amount,
                    ];
                    $converted_rate = coinremitterGetConvertRate($rate_param);

                    if ($converted_rate['data']['crypto_amount'] >= $minimum_invoice_val) {
                        $available_coins[] = $symbol;
                        
                        $count = 1;
                        $checkCoin++;
                    } else {
                        $checkCoin++;
                    }
                } else {
                    $available_coins[] = $symbol;
                }
            }
        }
    }
 else {
    echo "Unable to retrieve active cryptocurrencies.";
 }
        if ($checkCoin == 0)
            $count = -1;
            if ($count == -1) {
                add_action('wp_enqueue_scripts', 'coinremitter_select_payment_coin');
                $temp = "<p class='noCoin wallet_error_checkout' >No coin wallet setup!</p>";
                $CryptoOpt = !empty($temp) ? (isset($Script) ? $Script : '') . $temp . '<input type="hidden" name="crpopt" id="crpopt" >' : '';
                $SetPaymentOpt = !empty($CryptoOpt) ? (isset($CoinSript) ? $CoinSript : '') . '<div>' . $CryptoOpt . '</div>' : '';
                return $SetPaymentOpt;
            } else if ($count == 0) {
                add_action('wp_enqueue_scripts', 'coinremitter_select_payment_coin');
                $temp = "<p class='noCoin wallet_error_checkout' >Invoice amount is too low. Choose other payment method !</p>";
                $CryptoOpt = !empty($temp) ? (isset($Script) ? $Script : '') . $temp . '<input type="hidden" name="crpopt" id="crpopt" >' : '';
                $SetPaymentOpt = !empty($CryptoOpt) ? (isset($CoinSript) ? $CoinSript : '') . '<div>' . $CryptoOpt . '</div>' : '';
                return $SetPaymentOpt;
            }
        $tmp = '';
        $iconWidth = 70;
        // echo "<br>"; print_r($available_coins);
        $first_coin = true; 
        if (is_array($available_coins) && sizeof($available_coins)) {
            foreach ($available_coins as $v) {
                $v = trim(strtolower($v));

                $imageDir = plugin_dir_url(__FILE__) . 'images';
                $coin_imge_name = $v;
                $path = $imageDir . '/' . $coin_imge_name . '.png';
                // if (!file_exists($path)) {
                // 	$wallet_logo = 'dollar-ico';
                // } else {
                    $active_class = $first_coin ? ' active' : '';
                $wallet_logo = $coin_imge_name;
                // }
        // echo '<pre>'; print_r($available_coins[0]);
                add_action('wp_enqueue_scripts', 'coinremitter_select_payment_coin');
                $tmp .= "<a href='#' rel='" . $v . "' class='crpObj" . $active_class . "' >
                            <img class='active-wallet'  title='" . str_replace("%coinName%", ucfirst($v), $localisation["pay_in"]) . "' alt='" . str_replace("%coinName%", $v, $localisation["pay_in"]) . "' src='" . plugins_url('/images/' . $wallet_logo, __FILE__) . ($iconWidth > 70 ? "2" : "") . ".png'></a>";
                            $first_coin = false;
                        }
        }
        $CryptoOpt = !empty($tmp) ? (isset($Script) ? $Script : '') . $tmp . '<input type="hidden" name="crpopt" id="crpopt" >' : '';
        $SetPaymentOpt = !empty($CryptoOpt) ? (isset($CoinSript) ? $CoinSript : '') . '<div class="pay-box">' . $CryptoOpt . '</div>' : '';
        if (!empty($available_coins[0])) {
            set_transient('currency_value', $available_coins[0], HOUR_IN_SECONDS);
        }
        // echo '<pre>'; print_r($tmp);
        $dataa = wp_kses_post($SetPaymentOpt);
        $des = !empty($this->get_setting('description')) ? $this->get_setting('description') : __('Enter your payment details below:', 'coinremitter'); // Add description field
        $dataa .= '<p style="margin-top: 10px; font-size: 14px;">' . $des . '</p>';
        return [
            'title' => !empty($this->get_setting('title')) ? $this->get_setting('title') : __('Pay With Cryptocurrency', 'coinremitter'),
            'enabledCurrency' => $dataa . $html,
            'description' =>  $des,
            'supports' => array_filter($this->methods->supports, [$this->methods, 'supports']),
        ];
    }
}




?>
