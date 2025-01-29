<?php


function cr_common_data()
{
    // require_once CR_PLUGIN_DIR . '/front/payment-setting.php';

}
if (is_admin()) {
    require_once CR_PLUGIN_DIR . 'admin/coinremitter.php';
    require_once CR_PLUGIN_DIR . 'admin/coin-data.php';
}



function coinremitter_wp_gateway_class($methods)
{
    $methods[] = 'WC_Gateway_CoinRemitter';
    return $methods;
}


function coinremitter_wp_payment_gateways()
{
    class WC_Gateway_CoinRemitter extends WC_Payment_Gateway
    {
        private $notices = array();
        private $payments           = array();
        private $statuses           = array('pending' => 'Pending payment', 'processing' => 'Processing Payment', 'on-hold' => 'On Hold', 'completed' => 'Completed');
        private $url2               = '';
        private $url3               = 'https://coinremitter.com/';
        function __construct()
        {

            // error_log('coinremitter_wp_payment_gateways');


            $this->url2 = admin_url('admin.php?page=wc-settings&tab=order&section=coinremitterpayments');
            $this->id                     = 'coinremitterpayments';
            $this->method_title           = __('CoinRemitter Crypto Payment Gateway', COINREMITTERWC);
            $this->method_description      = "<a target='_blank' href='https://coinremitter.com/'></a>";
            $this->has_fields             = false;
            $this->supports                 = array('subscriptions', 'products');

            //load the settings
            $this->init_form_fields();
            $this->enabled = $this->get_option('enabled');
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');

            //process settings with parent method
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'coinremitter_process_admin_options'));
            add_action('admin_notices', array($this, 'display_admin_notices'));
            // }
        }

        public function coinremitter_process_admin_options()
        {
            $post_data = $this->get_post_data();
            if (!empty($post_data)) {
                if (isset($post_data['woocommerce_coinremitterpayments_invoice_expiry']) && !preg_match('/^[0-9]+$/', $post_data['woocommerce_coinremitterpayments_invoice_expiry'])) {
                    $this->add_notice('Invoice expiry only accepts numbers', 'error');
                } else if (isset($post_data['woocommerce_coinremitterpayments_invoice_expiry']) && $post_data['woocommerce_coinremitterpayments_invoice_expiry'] < 0 || $post_data['woocommerce_coinremitterpayments_invoice_expiry'] > 10080) {
                    $this->add_notice('Invoice expiry minutes should be 0 to 10080', 'error');
                } else {
                    foreach ($this->get_form_fields() as $key => $field) {
                        if ('title' !== $this->get_field_type($field)) {
                            try {
                                $this->settings[$key] = $this->get_field_value($key, $field, $post_data);
                            } catch (Exception $e) {
                                $this->add_error($e->getMessage());
                            }
                        }
                    }
                    return update_option($this->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings), 'yes');
                }
            }
        }
        private function add_notice($message, $type)
        {
            $this->notices[] = array(
                'message' => $message,
                'type'    => $type,
            );
        }
        public function display_admin_notices()
        {
            foreach ($this->notices as $notice) {
                $class = 'notice notice-' . esc_attr($notice['type']);
                $message = esc_html($notice['message']);
                echo "<div class='$class'><p>$message</p></div>";
            }
        }
        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled'        => array(
                    'title'             => __('Enable/Disable', COINREMITTERWC),
                    'type'              => 'checkbox',
                    'default'          => (COINREMITTERWC_AFFILIATE_KEY == 'coinremitter' ? 'yes' : 'no'),
                    'label'             => sprintf(__("Enable CoinRemitter Crypto Payments in WooCommerce with <a href='%s'>CoinRemitter Crypto Payment Gateway</a>", COINREMITTERWC), $this->url3)
                ),
                'title'            => array(
                    'title'           => __('Title', COINREMITTERWC),
                    'type'            => 'text',
                    'default'         => __('Pay Using Cryptocurrency', COINREMITTERWC),
                    'description'     => __('Payment method title that the customer will see on your checkout', COINREMITTERWC)
                ),
                'description'     => array(
                    'title'           => __('Description', COINREMITTERWC),
                    'type'            => 'textarea',
                    'default'         => trim(sprintf(__('Secure, anonymous payment with virtual currency - %s', COINREMITTERWC), implode(", ", $this->payments)), " -") . ". <a target='_blank' href='https://coinremitter.com/'>" . __('What is coinremitter?', COINREMITTERWC) . "</a>",
                    'description'     => __('Payment method description that the customer will see on your checkout', COINREMITTERWC)
                ),
                'ostatus'         => array(
                    'title'         => __('Order Status - On Payment Received', COINREMITTERWC),
                    'type'             => 'select',
                    'options'         => $this->statuses,
                    'default'         => 'Processing',
                    'description'     => sprintf(__("When customer pay coinremitter invoice, What order status should be ? Set it here", COINREMITTERWC), $this->url2)
                ),
                'invoice_expiry'        => array(
                    'title'           => __('Invoice expiry time in Minutes', COINREMITTERWC),
                    'type'            => 'text',
                    'default'         => "30",
                    'description'     => __("It indicates invoice validity. An invoice will not valid after expiry minutes. E.g if you set Invoice expiry time in Minutes 30 then the invoice will expire after 30 minutes. Set 0 to avoid expiry", COINREMITTERWC)
                )

            );
            return true;
        }

        function coinremitterSetPaymnetOptDesc()
        {
            if (is_checkout()) {
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
                    $total_amt = WC()->cart->subtotal;
                    $shipping = WC()->cart->get_shipping_total();
                }
                $tablename = $wpdb->prefix . 'coinremitter_wallets';
                $sql = $wpdb->get_results("SELECT * FROM $tablename");
                foreach ($sql as $row) {
                    $wallet_name = $row->wallet_name;
                    $coin_symbol = $row->coin_symbol;
                    $CurrAPIKey = decrypt_data($row->api_key);
                    $CurrPassword = decrypt_data($row->password);
                    $minimum_invoice_val = $row->minimum_invoice_amount;
                    $multiplier = $row->exchange_rate_multiplier;

                    if ($CurrAPIKey && $CurrPassword) {
                        if ($total_amt > 0) {
                            if ($multiplier == 0 || $multiplier == '')
                                $multiplier = 1;

                            if ($minimum_invoice_val == '' || $minimum_invoice_val == null)
                                $minimum_invoice_val = 0.0001;

                            if ($total_amt >= $minimum_invoice_val) {
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

                        $total_amount = $total_amt;
                        $currancy_type = get_woocommerce_currency();

                        $coupons = WC()->cart->get_coupons();
                        $coupon = new WC_Coupon(key( $coupons ));
                        $discount_amount = WC()->cart->get_coupon_discount_amount($coupon->get_code());
                        $tax = $woocommerce->cart->get_shipping_tax();
                        $sql_wallet = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tablename WHERE coin_symbol = %s", $v));
                        $coin_name = $sql_wallet->coin_name;
                        $unit_fiat_amount = $sql_wallet->unit_fiat_amount;
                        $exchange_rate_multiplier = $sql_wallet->exchange_rate_multiplier;
                        $total_amount = ($total_amount * $exchange_rate_multiplier ) + $shipping + $tax;
                        $Amount = $total_amount - $discount_amount;
                        $convert_amount = $Amount / $unit_fiat_amount;
                        // echo '<pre>';print_r($convert_amount);die;
                        
                        $usd_am_currency = number_format($convert_amount, 8);
                        $tmp .= "
                                    <label for='$v' class='align-items-center gap-3 coin_data py-main' rel='" . $v . "' coin='" . $coin_name . "'>
                                      <div class='py-left d-flex'>
                                        <input type='radio' name='coins' id='$v' value='$v' $checked>
                                        <div>
                                            <h5 class='m-0'> $coin_name </h5>
                                            <h6 class='m-0'>" . $usd_am_currency . ' ' . $v . '' . "</h6>
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

                $wc_gateways = new WC_Payment_Gateways();
                $payment_gateways = $wc_gateways->get_available_payment_gateways();
                foreach ($payment_gateways as $gateway) {
                    $payment_data = $gateway->id;
                    if ($payment_data != 'coinremitterpayments') {

                        $transient_name = 'currency_value';
                        delete_transient($transient_name);
                    }
                }

                if (!empty($available_coins[0])) {
                    set_transient('currency_value', $available_coins[0], HOUR_IN_SECONDS);
                }

                echo $html . $SetPaymentOpt;
            }
        }

        function process_payment($order_id)
        {
            global $woocommerce;

            $order = new WC_Order($order_id);

            $order->update_status('Processing', 'Additional data like transaction id or reference number');

            $woocommerce->cart->empty_cart();
            $order->reduce_order_stock();

            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }


        public function payment_fields()
        {
            $coinsGet = '';
            $coinsGet = $this->coinremitterSetPaymnetOptDesc();
            $this->description = $this->description . $coinsGet;
            echo wpautop(wp_kses_post($this->description));
?>
<?php
        }
    }
}




#--------------------------------------------------------------------------------
#region Database table create
#--------------------------------------------------------------------------------

function coinremitter_wp_table_create()
{
    error_log('db create');
    global $wpdb;

    $wallet_table = $wpdb->prefix . 'coinremitter_wallets'; // Table name
    $order_table = $wpdb->prefix . 'coinremitter_orders'; // Table name
    $transaction_table = $wpdb->prefix . 'coinremitter_transactions'; // Table name
    $charset_collate = $wpdb->get_charset_collate();


    $wallet_sql = "CREATE TABLE IF NOT EXISTS $wallet_table (
        id INT(11) NOT NULL AUTO_INCREMENT,
        wallet_name VARCHAR(255),
        coin_symbol VARCHAR(255),
        coin_name VARCHAR(255),
        api_key VARCHAR(255),
        password VARCHAR(255),
        minimum_invoice_amount DOUBLE(20, 8) NOT NULL COMMENT 'value in coin',
        exchange_rate_multiplier DOUBLE(20, 8),
        unit_fiat_amount DOUBLE(20, 4),
        -- fiat_rate DECIMAL(20,8) NOT NULL,
        created_at TIMESTAMP,
        updated_at TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate ";

    $order_sql = "CREATE TABLE IF NOT EXISTS $order_table (
        id INT(11) NOT NULL AUTO_INCREMENT,
        order_id VARCHAR(255) NOT NULL,
        user_id VARCHAR(20) NOT NULL,
        coin_symbol VARCHAR(10) NOT NULL,
        coin_name VARCHAR(100) NOT NULL,
        crypto_amount DOUBLE(18, 8) NOT NULL,
        fiat_symbol VARCHAR(10) NOT NULL,
        fiat_amount DOUBLE(18, 2) NOT NULL,
        paid_crypto_amount DOUBLE(18, 8) DEFAULT NULL,
        paid_fiat_amount DOUBLE(18, 2) DEFAULT NULL,
        payment_address TEXT NOT NULL,
        qr_code TEXT DEFAULT NULL,
        order_status VARCHAR(50) NOT NULL DEFAULT '0',
        expiry_date datetime DEFAULT NULL, 
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate ";

    $transaction_sql = "CREATE TABLE IF NOT EXISTS $transaction_table (
        id INT(11) NOT NULL AUTO_INCREMENT,
        order_id VARCHAR(255) NOT NULL,
        user_id VARCHAR(20) NOT NULL,
        meta TEXT NULL,
        PRIMARY KEY (id)
    ) $charset_collate ";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($wallet_sql);
    dbDelta($order_sql);
    dbDelta($transaction_sql);
}



#--------------------------------------------------------------------------------
#region cr admin order data
#--------------------------------------------------------------------------------


if (!function_exists('coinremitter_cd_meta_box_add')) {
    function coinremitter_cd_meta_box_add()
    {
        global $wpdb;
        if (isset($_GET['id']))
            $order_id = $_GET['id'];
        else
        $order_id = isset($_GET['post']) ? $_GET['post'] : '';
        $method = "Payment Detail (Coinremitter)";
        $order_table = $wpdb->prefix . 'coinremitter_orders';
        $payment_query = "SELECT * FROM $order_table WHERE order_id = '" . $order_id . "'";
        $payment_details = $wpdb->get_results($payment_query);
        $args = array(
            'post_type'         => 'shop_order',
            'posts_per_page'    => -1
    );
    
    $posts = get_posts($args);
        if (!empty($payment_details)) {
            add_meta_box('my-meta-box-id', $method, 'coinremitter_cd_meta_box_cb', $posts, 'advanced', 'high');
        }
    }
}

if (!function_exists('coinremitter_cd_meta_box_cb')) {
    function coinremitter_cd_meta_box_cb()
    {
        global $wpdb;
        if (isset($_GET['id']))
            $id = $_GET['id'];
        else
            $id = $_GET['post'];
        $order_id = sanitize_text_field($id);
        $order = new WC_Order($order_id);

        $order_table = $wpdb->prefix . 'coinremitter_orders';
        $payment_query = "SELECT * FROM $order_table WHERE order_id = '" . $order_id . "'";
        $get_order_data = $wpdb->get_results($payment_query);
        $address = $get_order_data[0]->payment_address;
        $coin_type = $get_order_data[0]->coin_symbol;
        $order_statusData = $get_order_data[0]->order_status;
        $paidAmount = $get_order_data[0]->paid_crypto_amount;
        $paidFiatAmount = $get_order_data[0]->paid_fiat_amount;
        $wcOrderStatus = $order->get_status();
        if (count($get_order_data) < 1) {
            return "";
        }

        $tablename = $wpdb->prefix . 'coinremitter_wallets';
        $sql = $wpdb->get_row($wpdb->prepare("SELECT  wallet_name, coin_symbol, api_key, password FROM $tablename WHERE coin_symbol = %s", $coin_type));


        if ($sql) {
            $api_key = decrypt_data($sql->api_key);
            $api_password = decrypt_data($sql->password);
        }
        if ($order_statusData == COINREMITTER_INV_PENDING || $order_statusData == COINREMITTER_INV_UNDER_PAID) {

            $transaction_details = create_transaction_check($address, $api_key, $api_password); //api call

            $paid_amount = 0;

            $webhookTrandata = $transaction_details['data'];
            $address = $webhookTrandata['address'];
            $required_confirmations = $webhookTrandata['required_confirmations'];
            $coin = $webhookTrandata['coin_name']?? '';
            $coin_symbol = $webhookTrandata['coin_symbol'];
            foreach ($webhookTrandata['transactions'] as $transaction) {

                $meta_json = '';
                $txid = $transaction['txid'];
                $amount = $transaction['amount'];
                $status = $transaction['status'];
                $confirmations = $transaction['confirmations'];
                $explorer_url = $transaction['explorer_url'];
                // echo 'confirmations<pre>';print_r($confirmations);
                error_log("webhook Transaction. confirmations : " . $confirmations . " txid : " . $txid);

                $metaTrx = [
                    'payment_address' => $address,
                    'explorer_url' => $transaction['explorer_url'],
                    'crypto_amount' => $transaction['amount'],
                    'coin_symbol' => $coin_symbol,
                    'confirmations' => $confirmations,
                    'coin_name' => $coin,
                    'fiat_symbol' => 'USD',
                    'trx_id' => $transaction['txid'],
                    'status' => 0,
                    'created_at' => $transaction['date'],
                    'updated_at' => $transaction['date'],
                ];
                error_log("webhook. metaTrx : " . json_encode($metaTrx));

                if ($status == 'confirm') {
                    $status_code = 0;
                }

                if ($confirmations >= $required_confirmations) {
                    $paid_amount += $amount;
                }
                $sql = "SELECT * FROM wp_coinremitter_transactions WHERE order_id = %s";
                $existing_order = $wpdb->get_results($wpdb->prepare($sql, $order_id));

                if (is_array($existing_order) && count($existing_order) > 0) {

                    $fiat_amount = 0.05;       // Amount in fiat (USD)
                    $fiat_currency = 'USD';    // Fiat currency
                    $result_arr = fiat_cryoto($fiat_amount, $fiat_currency, $coin); //api call
                    $fiat_price = $result_arr['data'][0]['price'];
                    error_log("fiat_rate : " . $fiat_price);

                    $meta_data = json_decode($existing_order[0]->meta, true);
                    error_log("existing order : " . json_encode($meta_data));

                    $trxArray = array_column($meta_data, 'trx_id');
                    foreach ($meta_data as $key => &$data_up) {
                        if (!in_array($txid, $trxArray)) {
                            error_log("webhook Transaction not found in metaTrx trx_id : " . $txid . '' . $metaTrx);
                            array_push($meta_data, $metaTrx);
                            break;
                        } else {
                            error_log("webhook Transaction found in meta data. Trx_id : " . $txid . '___' . json_encode($metaTrx));
                            error_log("existing order found data : " . json_encode($data_up));
                            // update order data
                            error_log("webhook Trx id to be processed : " . $data_up['trx_id'] . " status : " . $data_up['status'] . " confirmations : " . $data_up['confirmations']);
                            if ($data_up['trx_id'] == $txid) {
                                $meta_data[$key]['confirmations'] = $confirmations;
                                if ($data_up['status'] == 0 && $confirmations >= $required_confirmations) {

                                    $paidAmount +=  $amount;
                                    error_log("webhook New paid amount : " . $paidAmount . " Trx_id : " . $txid);
                                    $walletTotalAmount =  ($amount * $paidFiatAmount) / $paidAmount;
                                    $paidFiatAmount += $walletTotalAmount;
                                    $paidFiatAmtWithTrunc = $paidAmount + $fiat_price;

                                    error_log("webhook trucationVal : " . $paidFiatAmtWithTrunc . " walletTotalAmount: " . $walletTotalAmount . "");

                                    $crypto_amount = $get_order_data[0]->crypto_amount;
                                    $fiat_amount = $get_order_data[0]->fiat_amount;
                                    $status = 0;
                                    if ($paidAmount >= $get_order_data[0]->paid_fiat_amount) {
                                        $status = 1;
                                    }
                                    $order_status = "";
                                    $order_status_code = "";

                                    $option_data = get_option('woocommerce_coinremitterpayments_settings');
                                    $ostatus = $option_data['ostatus'];
                                    if ($paidFiatAmtWithTrunc == 0) {

                                        if ($wcOrderStatus == 'pending') {
                                            $order->update_status('pending');
                                            error_log("webhook Status change pending");
                                        }
                                        $order_status = "Pending";
                                        $order_status_code = COINREMITTER_INV_PENDING;  //0

                                    } else if ($crypto_amount > $paidAmount) {

                                        if ($wcOrderStatus == 'pending') {
                                            $order->update_status('processing');
                                            error_log("webhook Status change pending");
                                        }
                                        $order_status_code = COINREMITTER_INV_UNDER_PAID; // 2

                                    } else if ($crypto_amount < $paidAmount) {

                                        if ($wcOrderStatus == 'pending' || $wcOrderStatus == 'processing') {
                                            $order->update_status('completed');
                                            error_log("webhook Status change over paid completed");
                                            $order->update_status('wc-' . $ostatus);
                                        }
                                        $order_status = "Over paid";
                                        $order_status_code = COINREMITTER_INV_OVER_PAID; //3

                                    } else if ($crypto_amount == $paidAmount) {

                                        if ($wcOrderStatus == 'processing' || $wcOrderStatus == 'pending') {
                                            $order->update_status('completed');
                                            error_log("webhook Status change paid");
                                            $order->update_status('wc-' . $ostatus);
                                        }
                                        $order_status = "Paid";
                                        $order_status_code = COINREMITTER_INV_PAID; //1

                                    }

                                    error_log(" crypto_amount " . $crypto_amount . "  paidFiatAmtWithTrunc  " . $paidFiatAmtWithTrunc . " order_status_code " . $order_status_code);
                                    if ($crypto_amount <= $paidFiatAmtWithTrunc &&  $order_status_code == COINREMITTER_INV_UNDER_PAID) {
                                        if ($wcOrderStatus == 'processing' || $wcOrderStatus == 'pending') {
                                            $order->update_status('completed');
                                            error_log("webhook Status change paid");
                                            $order->update_status('wc-' . $ostatus);
                                        }
                                        $order_status = "Paid";
                                        $order_status_code = COINREMITTER_INV_PAID; //3 

                                    }
                                    error_log('webhook Change order data : ' . " trx id : " . $txid . " order id : " . $order_id . ' : ' . $paidAmount . ' : ' . $paidFiatAmount . ' : ' . $order_status_code);
                                    error_log("webhook Status change of trx_id : " . $data_up['trx_id']);
                                    $updateOrder = "UPDATE `wp_coinremitter_orders` SET `paid_crypto_amount`='$paidAmount', `paid_fiat_amount`='$paidFiatAmount', `order_status`='$order_status_code' WHERE `order_id`='$order_id'";
                                    $data_up['status'] = 1;
                                    $wpdb->get_results($updateOrder);
                                
                                }
                            }
                        }
                    }
                    $data = [
                        'meta' => json_encode($meta_data),
                    ];
                    $where = [
                        'order_id' => $order_id,
                    ];
                    error_log(message: "Order transaction meta update successfully. for trx Id : " . $txid . '' . json_encode($data));
                    $wpdb->update('wp_coinremitter_transactions', $data, $where);
                }
            }
            if (is_array($existing_order) && count($existing_order) <= 0) {
                $metaTrxs[] = $metaTrx;
                $meta_json = json_encode($metaTrxs);
                $data = [
                    'order_id' => $order_id,
                    'meta' => $meta_json,
                ];

                $wpdb->insert('wp_coinremitter_transactions', $data);
            }
        }
        $expiry_date = $get_order_data[0]->expiry_date;
        $status = $get_order_data[0]->order_status;
        if ($status == COINREMITTER_INV_PENDING) {

            $order_status = "Pending";
        } else if ($status == COINREMITTER_INV_UNDER_PAID) {

            $order_status = "Under paid";
        } else if ($status == COINREMITTER_INV_OVER_PAID) {

            $order_status = "Over paid";
        } else if ($status == COINREMITTER_INV_PAID) {

            $order_status = "Paid";
        } else if ($status == COINREMITTER_INV_EXPIRED) {

            $order_status = "cancelled";
        }
        $order = wc_get_order($order_id);

        $coin = $get_order_data[0]->coin_symbol;
        $base_currency = $get_order_data[0]->fiat_symbol;
        $test_order = new WC_Order($order_id);
        $test_order_key = $test_order->get_order_key();
        $s_url = site_url();
        $sss_url = $s_url . '/index.php/invoice-page/' . $order_id . '/?pay_for_order=true&key=' . $test_order_key;
        $invoice_url = $sss_url;
        $created_date = $get_order_data[0]->created_at;
        $expiry_date = ($get_order_data[0]->expiry_date == "" ? "-" : $get_order_data[0]->expiry_date);

        if ($expiry_date != "") {
            $diff = strtotime($expiry_date) - strtotime(gmdate('Y-m-d H:i:s'));
        }

        if ($get_order_data[0]->order_id != "") {
            $paid_amount = json_decode($get_order_data[0]->paid_crypto_amount, true);
            $total_amount = json_decode($get_order_data[0]->crypto_amount, true);
        } else {
            $total_amount = $get_order_data[0]->crypto_amount;
            $paid_amount = ($get_order_data[0]->paid_crypto_amount == "" ? 0 : $get_order_data[0]->paid_crypto_amount);
        }
        $pending_amount = $total_amount - $paid_amount;
        $tr_tablename = $wpdb->prefix . 'coinremitter_transactions';
        $sql = "SELECT * FROM $tr_tablename WHERE order_id='" . $order_id . "'";
        $webhook = $wpdb->get_results($sql);


        $temp2 = "<style>.cr_table table {border:none !important;}</style>";
        if (!empty($webhook)) {

            foreach ($webhook as $value) {
                $v = $value->meta;
                $metaData = json_decode($v, true);

                foreach ($metaData as $mdata) {
                    $explorer_url = $mdata['explorer_url'];
                    $crypto_amount = $mdata['crypto_amount'];
                    $coin_symbol = $mdata['coin_symbol'];
                    $confirmations = $mdata['confirmations'];
                    $trx_id = $mdata['trx_id'];
                    $created_at = $mdata['created_at'];

                    $temp2 .= '<tr>
                    <td class="label greeColour"><a title="' . __('Transaction Details', COINREMITTER) . ' - ' . $trx_id . '" href="' . $explorer_url . '" target="_blank"><strong>' . substr($trx_id, 0, 20) . '....</strong></a></td>
                    <td> ' . sprintf('%.8f', $crypto_amount) . ' ' . $coin_symbol . '</td>
                    <td>' . $created_at . '</td>
                    <td style="text-align:center"><img src="' . CR_PLUGIN_PATH . 'images/checked.gif' . '"></td>
                    </tr>';
                }
            }
        } else if (isset($payment_history) && is_array($payment_history)) {
            foreach ($payment_history as $key => $value) {
                $temp2 .= '<tr>
				<td class="label greeColour"><a title="' . __('Transaction Details', COINREMITTER) . ' - ' . $value['txid'] . '" href="' . $value['explorer_url'] . '" target="_blank"><strong>' . substr($value['txid'], 0, 20) . '....</strong></a></td>
				<td> ' . sprintf('%.8f', $value['amount']) . ' ' . $coin . '</td>
				<td>' . $value['date'] . '</td>
				<td style="text-align:center"><img src="' . plugins_url('/images/checked.gif', __FILE__) . '"></td>
				</tr>';
            }
        } else {
            $temp2 .= '<tr>
			<td colspan="4" style="text-align:center"> - </td>
			</tr>';
        }


        $pending_html = "<div class='inside cr_table' id='postcustomstuff' style='width:25%;float:left;'><table class='wc-crpto-data'>
		<thead>
		<tr>
		<th style='text-align: left' colspan='2'>Pending Amount</th>
		</tr>
		</thead>
		<tbody>
		<table class='wc-crpto-data'>
		<tbody><tr><td>" . number_format($pending_amount, 8) . "</td></tr></tbody>
		</table>
		</tbody>
		</table></div>";
        $desc_html = "<div class='inside cr_table' id='postcustomstuff' style='width:25%;float:left;'><table class='wc-crpto-data'>
		<thead>
		<tr>
		<th style='text-align: left' colspan='2'>Description</th>
		</tr>
		</thead>
		<tbody>
		<table class='wc-crpto-data'>
		<tbody><tr><td>" . $order_id . "</td></tr></tbody>
		</table>
		</tbody>
		</table></div>";
        $url = "<div class='inside cr_table' id='postcustomstuff'>
		<table class='wc-crpto-data'>

		<thead>
		<tr>
		<th style='text-align: left'>Invoice Url</th>
		</tr>
		</thead>
		<tbody><tr><td><a href='" . $invoice_url . "' target='_blank'>" . $invoice_url . "</a></td></tr></tbody>
		</table>
		</div>";
        $t_html = "<div class='inside cr_table' id='postcustomstuff' style='width:21%;float:left;'><table class='wc-crpto-data'>
		<thead>
		<tr>
		<th style='text-align: left' colspan='2'>Order Amount</th>
		</tr>
		</thead>
		<tbody>
		<table class='wc-crpto-data'>
		<tbody><tr><td>" . number_format($total_amount, 8) . "</td></tr></tbody>
		</table>
		</tbody>
		</table></div>";
        $p_html = "<div class='inside cr_table' id='postcustomstuff' style='width:21%;float:left;'><table class='wc-crpto-data'>
		<thead>
		<tr>
		<th style='text-align: left' colspan='2'> Paid Amount</th>
		</tr>
		</thead>
		<tbody>
		<table class='wc-crpto-data'>
		<tbody><tr><td>" . number_format($paid_amount, 8) . "</td></tr></tbody>
		</table>
		</tbody>
		</table></div>";
        $detail = "<div class='inside cr_table' id='postcustomstuff'><table class='wc-crpto-data'>
		<thead>
		<tr>
		<th style='text-align: left' colspan='2'>Order Detail</th>
		</tr>
		</thead>
		<tbody>
		<table class='wc-crpto-data'>
		<thead>
		<tr>
		<td class='label'>Invoice Id</td>
		<td >Base Currency</td>
		<td >Coin</td>
		<td >Status</td>
		<td class='total'>Create On</td>
		<td class='total'>Expiry On</td>
		</tr>
		</thead>
		<tbody>
		<tr>
		<td>#" . $order_id . "</td>
		<td>" . $base_currency . "</td>
		<td>" . $coin . "</td>
		<td>" . $order_status . "</td>
		<td>" . $created_date . "</td>
		<td>" . $expiry_date . "</td>

		</tr>
		</tbody>
		</table>
		</tbody>
		</table></div>";



        $payment_html = '<div class="inside">
		<div class="cr_table" id="postcustomstuff">
		<table >
		<thead>
		<tr>
		<th style="text-align: left" colspan="2">Payment History</th>
		</tr>
		</thead>
		<tbody id="the-list" data-wp-lists="list:meta">
		<tr>
		<table class="wc-crpto-data">
		<thead>
		<tr>
		<td class="label">Transaction Id</td>
		<td >Amount</td>
		<td class="total">Date</td>
		<td style="text-align:center">Confirmation</td>
		</tr>' . $temp2 . '
		</thead>
		</table>
		</tr>
		</tbody>
		</table>
		</div>
		</div>
		</div>';
        echo wp_kses_normalize_entities($detail . $url . $desc_html . $t_html . $p_html . $pending_html . $payment_html);
    }
}




/**
 * Fetch supported currencies using the Coinremitter API.
 */
function fetch_supported_currencies()
{
    $curl = COINREMITTER_CURL . '/rate/supported-currency';
    $userAgent = 'CR@' . COINREMITTER_API_VERSION . ',wordpress worwoocommerce-wordpress-master@' . COINREMITTER_PLUGIN_VERSION;
    $args = array(
        'method'    => 'POST',
        'sslverify' => false,
        'user-agent'  => $userAgent,
        'headers'   => array('Content-Type' => 'application/json'),
        'body'      => wp_json_encode(array()),
    );

    $response = wp_remote_post($curl, $args);
    return handle_api_response($response);
}

/**
 * Find currency data by coin symbol.
 */
function find_currency_data($supported_currencies, $coin_symbol)
{
    foreach ($supported_currencies['data'] as $currency) {
        if ($currency['coin_symbol'] === $coin_symbol) {
            return $currency;
        }
    }
    return null;
}

/**
 * Calculate the minimum invoice amount in USD.
 */
function calculate_min_invoice_amount($currency_data)
{
    $minimum_deposit_amount = $currency_data['minimum_deposit_amount'];
    $price_in_usd = $currency_data['price_in_usd'];
    $min_invoice_val = $price_in_usd * $minimum_deposit_amount;
    // error_log('calculate_min_invoice_amount: ' . $min_invoice_val . ' minimum_deposit_amount '. $minimum_deposit_amount .' price_in_usd '. $price_in_usd);

    return number_format(round($min_invoice_val, 4), 8, '.', '');
}

/**
 * Convert currency using the Coinremitter API.
 */
function fiat_cryoto($amount, $from_currency, $coin_symbol)
{
    $curl = COINREMITTER_CURL . '/rate/fiat-to-crypto';
    $userAgent = 'CR@' . COINREMITTER_API_VERSION . ',wordpress worwoocommerce-wordpress-master@' . COINREMITTER_PLUGIN_VERSION;
    $body = array(
        'fiat'        => $from_currency,
        'fiat_amount' => $amount,
        'crypto'      => $coin_symbol,
    );

    $args = array(
        'method'    => 'POST',
        'sslverify' => false,
        'user-agent'  => $userAgent,
        'headers'   => array('Content-Type' => 'application/json'),
        'body'      => wp_json_encode($body),
    );

    $response = wp_remote_post($curl, $args);
    $result = handle_api_response($response);
    return $result;
}

/**
 * Update wallet data in the database.
 */
function update_wallet_data($table_name, $coin_symbol, $min_invoice_amount,$unit_fiat_amount)
{
    global $wpdb;

    $data = array(
        'unit_fiat_amount' => $unit_fiat_amount,
        'minimum_invoice_amount' => $min_invoice_amount,
    );
    $where = array('coin_symbol' => $coin_symbol);

    $updated = $wpdb->update($table_name, $data, $where);

    if ($updated === false) {
        error_log("Failed to update minimum_invoice_amount for coin: $coin_symbol");
    } else {
        error_log("Successfully updated minimum_invoice_amount for coin: $coin_symbol to $min_invoice_amount");
    }
}

/**
 * Handle API response.
 */
function handle_api_response($response)
{
    if (is_wp_error($response)) {
        error_log('API Request Error: ' . $response->get_error_message());
        return null;
    }

    $response_body = wp_remote_retrieve_body($response);
    return json_decode($response_body, true);
}

function crypto_to_fiat($currancy_type, $coin_symbol,$crypto_amount=1)
{
    $crypto_curl =  COINREMITTER_CURL . '/rate/crypto-to-fiat';
    $userAgent = 'CR@' . COINREMITTER_API_VERSION . ',wordpress worwoocommerce-wordpress-master@' . COINREMITTER_PLUGIN_VERSION;
    $fiat_body = array(
        "fiat" => $currancy_type,
        "crypto_amount" => $crypto_amount,
        "crypto" => $coin_symbol
    );
    $fiat_args = array(
        'method'      => 'POST',
        'sslverify'   => false,
        'user-agent'  => $userAgent,
        'headers'     => array(
            'Content-Type'  => 'application/json',
        ),
        'body'        => wp_json_encode($fiat_body),
    );

    $fiat_request = wp_remote_post($crypto_curl, $fiat_args);
    $fiat_response = wp_remote_retrieve_body($fiat_request);
    $fiat_data = json_decode($fiat_response, true);
    if (isset($fiat_data['data']) && isset($fiat_data['data'][0]['amount'])) {
        return $fiat_data['data'][0]['amount'];  // Return the exchange rate
    } else {
        return new WP_Error('invalid_data', 'Invalid response data');
    }
}

// balance api call
function balance_request($api_key, $password, $body = array())
{    $api_url = COINREMITTER_CURL . '/wallet/balance';
    $userAgent = 'CR@' . COINREMITTER_API_VERSION . ',wordpress worwoocommerce-wordpress-master@' . COINREMITTER_PLUGIN_VERSION;
    $args = array(
        'method'    => 'POST',
        'sslverify' => false,
        'user-agent'  => $userAgent,
        'headers'   => array(
            'x-api-key'        => $api_key,
            'x-api-password'   => $password,
            'Content-Type'     => 'application/json',
        ),
        'body'      => wp_json_encode($body),
    );

    $response = wp_remote_post($api_url, $args);    
    $response_body = wp_remote_retrieve_body($response);
    error_log($response_body);
    return json_decode($response_body, true); // Return the decoded response
}

function create_transaction_check($addr, $api_key, $api_password)
{
    $curl = COINREMITTER_CURL . '/wallet/address/transactions';
    $userAgent = 'CR@' . COINREMITTER_API_VERSION . ',wordpress worwoocommerce-wordpress-master@' . COINREMITTER_PLUGIN_VERSION;
    $body = array(
        'address' => $addr,
    );

    $args = array(
        'method'      => 'POST',
        'timeout'     => 45,
        'sslverify'   => false,
        'user-agent'  => $userAgent,
        'headers'     => array(
            'x-api-key'     => $api_key,
            'x-api-password' => $api_password,
            'Content-Type'  => 'application/json',
        ),
        'body'        => wp_json_encode($body),
    );
    $request = wp_remote_post($curl, $args);
    $response = wp_remote_retrieve_body($request);
    $check_transaction = json_decode($response, true);

    if (is_array($check_transaction)) {
        return $check_transaction;
    } else {
        return 'Error: Unable to fetch transaction details or API returned an error.';
    }
}

// address create api call
function create_address($coin, $api_key, $api_password)
{
    $url = COINREMITTER_CURL . '/wallet/address/create';
    $userAgent = 'CR@' . COINREMITTER_API_VERSION . ',wordpress worwoocommerce-wordpress-master@' . COINREMITTER_PLUGIN_VERSION;
    $body = json_encode(['label' => $coin]);
    $args = [
        'method' => 'POST',
        'sslverify' => false,
        'user-agent'  => $userAgent,
        'headers' => [
            'Content-Type'  => 'application/json',
            'x-api-key'     => $api_key,
            'x-api-password' => $api_password,
        ],
        'body' => $body,
    ];

    $response = wp_remote_post($url, $args);
    $data = wp_remote_retrieve_body($response);
    $result = json_decode($data, true);
    return $result;
}

// transaction create api call
function createTransaction($id, $CurrAPIKey, $CurrPassword)
{
    $trx_url = COINREMITTER_CURL . '/wallet/transaction'; 
    $userAgent = 'CR@' . COINREMITTER_API_VERSION . ',wordpress worwoocommerce-wordpress-master@' . COINREMITTER_PLUGIN_VERSION;
    $header[] = "Accept: application/json";
    $body = array('id' => $id);
    $args = array(
        'method'      => 'POST',
        'timeout'     => 45,
        'sslverify'   => false,
        'user-agent'  => $userAgent,
        'headers'     => array(
            'x-api-key'    => $CurrAPIKey,  
            'x-api-password' => $CurrPassword,
            'Content-Type' => 'application/json',
        ),
        'body'        => wp_json_encode($body),
    );

    $request = wp_remote_post($trx_url, $args);
    if (is_wp_error($request) || wp_remote_retrieve_response_code($request) != 200) {
        error_log('Error: ' . print_r($request, true));
        return false;
    }
    $response = wp_remote_retrieve_body($request);
    $check_transaction = json_decode($response, true);

    if (isset($check_transaction['error'])) {
        error_log('API Error: ' . $check_transaction['error']);
        return false;
    }
    return $check_transaction;
}
