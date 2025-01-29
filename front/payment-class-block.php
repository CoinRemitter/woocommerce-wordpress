<?php



use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class My_Custom_Gateway_Blocks extends AbstractPaymentMethodType
{

    public function __construct($payment_request_configuration = null)
    {
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
            CR_PLUGIN_PATH . 'js/front-checkout.js',
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
        
        coinremitter_wp_plugin_scripts();
        global $wpdb;

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
        $checkCoin = 0;
        if (isset($woocommerce->cart)) {
            $main_total = $woocommerce->cart->total;
            $total_amt = WC()->cart->subtotal;
			$shipping = WC()->cart->get_shipping_total();
            $tax = $woocommerce->cart->get_shipping_tax();
            $coupons = WC()->cart->get_coupons();
            $coupon = new WC_Coupon(key( $coupons ));
            $discount_amount = WC()->cart->get_coupon_discount_amount($coupon->get_code());
        }
        
        $tablename = $wpdb->prefix . 'coinremitter_wallets';
        $sql = $wpdb->get_results("SELECT * FROM $tablename");
        foreach ($sql as $row) {
            $coin_symbol = $row->coin_symbol;
            $CurrAPIKey = decrypt_data($row->api_key);
            $CurrPassword = decrypt_data($row->password);
            $minimum_invoice_val = $row->minimum_invoice_amount;
            $unit_fiat_amount = $row->unit_fiat_amount;
            $multiplier = $row->exchange_rate_multiplier;
            if ($CurrAPIKey && $CurrPassword) {
                if ($main_total > 0) {
                    if ($multiplier == 0 || $multiplier == '')
                    $multiplier = 1;

                    if ($minimum_invoice_val == '' || $minimum_invoice_val == null)
                    $minimum_invoice_val = 0.0001;
                
                if ($main_total >= $minimum_invoice_val) {
                    $available_coins[] = $coin_symbol;
                    
                    $count = 1;
                    $checkCoin++;
                    } else {
                        $checkCoin++;
                    }
                } else {
                    $available_coins[] = $coin_symbol;
                }
            }
        }
        if ($checkCoin == 0)
            $count = -1;
        if ($count == -1) {
            $temp = "<p class='noCoin wallet_error_checkout' >No coin wallet setup!</p>";
            $CryptoOpt = !empty($temp) ? (isset($Script) ? $Script : '') . $temp . '<input type="hidden" name="crpopt" id="crpopt" >' : '';
            $SetPaymentOpt = !empty($CryptoOpt) ? (isset($CoinSript) ? $CoinSript : '') . '<div>' . $CryptoOpt . '</div>' : '';
            return $SetPaymentOpt;
        } else if ($count == 0) {
            $transient_name = 'currency_value';
			delete_transient($transient_name);
            $temp = "<p class='noCoin wallet_error_checkout' >Invoice amount is too low. Choose other payment method !</p>";
            $CryptoOpt = !empty($temp) ? (isset($Script) ? $Script : '') . $temp . '<input type="hidden" name="crpopt" id="crpopt" >' : '';
            $SetPaymentOpt = !empty($CryptoOpt) ? (isset($CoinSript) ? $CoinSript : '') . '<div>' . $CryptoOpt . '</div>' : '';
            return $SetPaymentOpt;
        }

        
        $tmp = '';
        $first_coin = true;
        if (is_array($available_coins) && sizeof(value: $available_coins)) {

            foreach ($available_coins as $v) {

                $v = trim(strtolower($v));
                $checked = $first_coin ? ' checked' : '';
                $wall_Coin_IM =  CR_PLUGIN_PATH . 'images/' . $v . '.png';

                if ($multiplier == 0 || $multiplier == '') {
                    $multiplier = 1;
                }
    
                if ($minimum_invoice_val == '' || $minimum_invoice_val == null) {
                    $minimum_invoice_val = 0.0001;
                }
                $sql_wallet = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tablename WHERE coin_symbol = %s", $v));
                $exchange_rate_multiplier = $sql_wallet->exchange_rate_multiplier;
 
                
                $total_amount = ($total_amt * $exchange_rate_multiplier ) + $shipping + $tax;
                $Amount = $total_amount - $discount_amount;
                
                $addrSql = 'select * from wp_coinremitter_wallets where coin_symbol ="' . $v . '"';
                $getDatas = $wpdb->get_results($addrSql);
                $unit_fiat_amount = $getDatas[0]->unit_fiat_amount;
                $convert_amount = $Amount / $unit_fiat_amount;
                $coin_name = $sql_wallet->coin_name;
                $usd_am_currency = number_format($convert_amount,8);
                // }
                $tmp .= "
                        <label for='$v' class='align-items-center gap-3 coin_data py-main' rel='" . $v . "' coin='". $coin_name ."'>
                          <div class='py-left d-flex'>
                            <input type='radio' name='coins' id='$v' value='$v' $checked>
                            <div>
                                <h5 class='m-0'> $coin_name </h5>
                                <h6 class='m-0'>". $usd_am_currency .' '. $v .''."</h6>
                            </div>
                          </div>
                          <div class='py-right'>
                            <img src=' $wall_Coin_IM ' alt='$v' class='waller_coin_img'>                        
                          </div>
                        </label>
                      ";

                $first_coin = false;
            }
        }
        $CryptoOpt = !empty($tmp) ? (isset($Script) ? $Script : '') . $tmp . '<input type="hidden" name="crpopt" id="crpopt" >' : '';
        $SetPaymentOpt = !empty($CryptoOpt) ? (isset($CoinSript) ? $CoinSript : '') . '
        <div class="pay-box"> 
        <div id="search-results"></div>
        <div class="d-flex coins flex-wrap w-100">
        ' . $CryptoOpt . '</div>
        </div>' : '';

        
        if (!empty($available_coins[0])) {
            set_transient('currency_value', $available_coins[0], HOUR_IN_SECONDS);
        }
        $des = !empty($this->get_setting('description')) ? $this->get_setting('description') : __('Enter your payment details below:', 'coinremitter'); // Add description field
        $dataa = '<p style="margin-top: 10px; font-size: 14px;">' . $des . '</p>';
        return [
            'title' => !empty($this->get_setting('title')) ? $this->get_setting('title') : __('Pay With Cryptocurrency', 'coinremitter'),
            'enabledCurrency' => $SetPaymentOpt . $dataa . $html,
            'description' =>  $des,
            'supports' => array_filter($this->methods->supports, [$this->methods, 'supports']),
        ];
    }
    
}
