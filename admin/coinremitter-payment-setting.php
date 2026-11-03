<?php
if (!defined('ABSPATH')) {
    exit;
}

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
    if (!class_exists('WC_Payment_Gateway')) {
        return $methods;
    }
    $methods[] = 'WC_Gateway_CoinRemitter';
    return $methods;
}


function coinremitter_wp_payment_gateways()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    if (class_exists('WC_Gateway_CoinRemitter')) {
        return;
    }

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
            $this->method_title           = __('CoinRemitter Crypto Payment Gateway', 'coinremitter-crypto-payment-gateway');
            $this->method_description      = "<a target='_blank' href='https://coinremitter.com/'></a>";
            $this->has_fields             = false;
            $this->supports                 = array('products', 'refunds', 'pay', 'subscriptions',);

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
                    'title'             => __('Enable/Disable', 'coinremitter-crypto-payment-gateway'),
                    'type'              => 'checkbox',
                    'default'          => (COINREMITTERWC_AFFILIATE_KEY == 'coinremitter' ? 'yes' : 'no'),
                    'label'             => sprintf(__("Enable CoinRemitter Crypto Payments in WooCommerce with <a href='%s'>CoinRemitter Crypto Payment Gateway</a>", 'coinremitter-crypto-payment-gateway'), $this->url3)
                ),
                'title'            => array(
                    'title'           => __('Title', 'coinremitter-crypto-payment-gateway'),
                    'type'            => 'text',
                    'default'         => __('Pay Using Cryptocurrency', 'coinremitter-crypto-payment-gateway'),
                    'description'     => __('Payment method title that the customer will see on your checkout', 'coinremitter-crypto-payment-gateway')
                ),
                'description'     => array(
                    'title'           => __('Description', 'coinremitter-crypto-payment-gateway'),
                    'type'            => 'textarea',
                    'default'         => trim(sprintf(__('Secure, anonymous payment with virtual currency - %s', 'coinremitter-crypto-payment-gateway'), implode(", ", $this->payments)), " -") . ". <a target='_blank' href='https://coinremitter.com/'>" . __('What is coinremitter?', 'coinremitter-crypto-payment-gateway') . "</a>",
                    'description'     => __('Payment method description that the customer will see on your checkout', 'coinremitter-crypto-payment-gateway')
                ),
                'ostatus'         => array(
                    'title'         => __('Order Status - On Payment Received', 'coinremitter-crypto-payment-gateway'),
                    'type'             => 'select',
                    'options'         => $this->statuses,
                    'default'         => 'Processing',
                    'description'     => sprintf(__("When customer pay coinremitter invoice, What order status should be ? Set it here", 'coinremitter-crypto-payment-gateway'), $this->url2)
                ),
                'invoice_expiry'        => array(
                    'title'           => __('Invoice expiry time in Minutes', 'coinremitter-crypto-payment-gateway'),
                    'type'            => 'text',
                    'default'         => "30",
                    'description'     => __("It indicates invoice validity. An invoice will not valid after expiry minutes. E.g if you set Invoice expiry time in Minutes 30 then the invoice will expire after 30 minutes. Set 0 to avoid expiry", 'coinremitter-crypto-payment-gateway')
                )

            );
            return true;
        }

        function coinremitterSetPaymnetOptDesc()
        {
            //    FIX: ORDER-PAY page custom message 
            if (is_wc_endpoint_url('order-pay')) {

                global $wpdb;

                // Get order ID
                $order_id = absint(get_query_var('order-pay'));

                // Fetch stored coin name from your table
                $table = $wpdb->prefix . 'coinremitter_orders';
                $coin = $wpdb->get_row(
                    $wpdb->prepare("SELECT coin_name, coin_symbol FROM $table WHERE order_id = %s", $order_id),
                    ARRAY_A
                );

                // If coin is stored → show correct message
                if (!empty($coin)) {
                    $coin_name   = esc_html($coin['coin_name']);         // Example: Bitcoin
                    $coin_symbol = esc_html(strtoupper($coin['coin_symbol'])); // BTC

                    return "<p class='wallet_error_checkout' style='color:#000;font-weight:600;'> 
                        Pay Using $coin_name ($coin_symbol)
                    </p>";
                }

                // If not found, fallback
                return "<p class='wallet_error_checkout' style='color:#000;font-weight:600;'> 
                    Pay Using Cryptocurrency
                </p>";
            }


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
                    $total_amt = floatval($woocommerce->cart->total);
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
                        $wall_Coin_IM = cr_get_coin_logo_url($v);

                        if ($multiplier == 0 || $multiplier == '') {
                            $multiplier = 1;
                        }

                        if ($minimum_invoice_val == '' || $minimum_invoice_val == null) {
                            $minimum_invoice_val = 0.0001;
                        }

                        $sql_wallet = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tablename WHERE coin_symbol = %s", $v));
                        $coin_name = isset($sql_wallet->coin_name) ? $sql_wallet->coin_name : strtoupper($v);
                        $exchange_rate_multiplier = isset($sql_wallet->exchange_rate_multiplier) ? floatval($sql_wallet->exchange_rate_multiplier) : 1;
                        $unit_fiat_amount = isset($sql_wallet->unit_fiat_amount) ? floatval($sql_wallet->unit_fiat_amount) : 0;
                        $total_amount = $total_amt * $exchange_rate_multiplier;
                        $currancy_type = get_woocommerce_currency();

                        if ($unit_fiat_amount > 0) {
                            $crypto_amount = $total_amount / $unit_fiat_amount;
                        } else {
                            $result_arr = fiat_cryoto($total_amount, $currancy_type, $v);
                            $crypto_amount = (is_array($result_arr) && isset($result_arr['data'][0]['price'])) ? floatval($result_arr['data'][0]['price']) : 0;
                        }

                        $usd_am_currency = number_format($crypto_amount, 8, '.', '');

                        $tmp .= "
                                    <label for='$v' class='align-items-center gap-3 coin_data py-main' rel='" . $v . "' coin='" . esc_attr($coin_name) . "'>
                                      <div class='py-left d-flex'>
                                        <input type='radio' name='coins' id='$v' value='$v' $checked>
                                        <div>
                                            <h5 class='m-0'>" . esc_html($coin_name) . "</h5>
                                            <h6 class='m-0'>" . esc_html($usd_am_currency . ' ' . strtoupper($v)) . "</h6>
                                        </div>
                                      </div>
                                      <div class='py-right'>
                                        <img src='" . esc_url($wall_Coin_IM) . "' alt='" . esc_attr($v) . "' class='waller_coin_img'>                        
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

                if (isset(WC()->session)) {
                    $sess_coin = WC()->session->get('coinremitter_selected_coin');
                    if (empty($sess_coin) && !empty($available_coins[0])) {
                        WC()->session->set('coinremitter_selected_coin', $available_coins[0]);
                    }
                }
                if (!get_transient('currency_value') && !empty($available_coins[0])) {
                    set_transient('currency_value', $available_coins[0], HOUR_IN_SECONDS);
                }

                echo $html . $SetPaymentOpt;
            }
        }

        function process_payment($order_id)
        {
            global $woocommerce;

            $order = new WC_Order($order_id);

            try {
                $redirect_url = $this->get_return_url($order);
            } catch (Exception $e) {
                return array(
                    'result'   => 'failure',
                    'redirect' => ''
                );
            }

            if (!$redirect_url || strpos($redirect_url, 'invoice-page') === false || wc_notice_count('error') > 0) {
                return array(
                    'result'   => 'failure',
                    'redirect' => ''
                );
            }

            $order->update_status('pending', __('Awaiting cryptocurrency payment via CoinRemitter', 'coinremitter-crypto-payment-gateway'));

            $woocommerce->cart->empty_cart();
            wc_reduce_stock_levels($order_id);

            return array(
                'result'   => 'success',
                'redirect' => $redirect_url
            );
        }


        public function payment_fields()
        {
            $coinsGet = '';
            $coinsGet = $this->coinremitterSetPaymnetOptDesc();
            $this->description = $this->description . $coinsGet;
            echo wpautop(wp_kses_post($this->description));
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
        $payment_query = $wpdb->prepare("SELECT * FROM $order_table WHERE order_id = %s", $order_id);
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
        $payment_query = $wpdb->prepare("SELECT * FROM $order_table WHERE order_id = %s", $order_id);
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
        // --- [START] Direct API check (Active fallback alongside Webhook) ---
        if ($order_statusData == COINREMITTER_INV_PENDING || $order_statusData == COINREMITTER_INV_UNDER_PAID) {

            $transaction_details = create_transaction_check($address, $api_key, $api_password); //api call

            $webhookTrandata = (is_array($transaction_details) && isset($transaction_details['data'])) ? $transaction_details['data'] : array();
            $address = isset($webhookTrandata['address']) ? $webhookTrandata['address'] : $address;
            $required_confirmations = isset($webhookTrandata['required_confirmations']) ? intval($webhookTrandata['required_confirmations']) : 3;
            $coin = isset($webhookTrandata['coin_name']) ? $webhookTrandata['coin_name'] : $coin_type;
            $coin_symbol = isset($webhookTrandata['coin_symbol']) ? $webhookTrandata['coin_symbol'] : $coin_type;
            $transactions = (isset($webhookTrandata['transactions']) && is_array($webhookTrandata['transactions'])) ? $webhookTrandata['transactions'] : array();

            if (!empty($transactions)) {
                $sql_trx = "SELECT * FROM " . $wpdb->prefix . "coinremitter_transactions WHERE order_id = %s";
                $existing_order = $wpdb->get_results($wpdb->prepare($sql_trx, $order_id));
                $meta_data = array();
                $has_existing_order = false;
                if (is_array($existing_order) && count($existing_order) > 0) {
                    $has_existing_order = true;
                    $decoded = json_decode($existing_order[0]->meta, true);
                    if (is_array($decoded)) {
                        $meta_data = $decoded;
                    }
                }

                $store_currency = !empty($get_order_data[0]->fiat_symbol) ? $get_order_data[0]->fiat_symbol : get_woocommerce_currency();

                foreach ($transactions as $transaction) {
                    if (!isset($transaction['type']) || $transaction['type'] != 'receive') {
                        continue;
                    }
                    $txid = $transaction['txid'];
                    $amount = floatval($transaction['amount']);
                    $confirmations = isset($transaction['confirmations']) ? intval($transaction['confirmations']) : 0;
                    $explorer_url = isset($transaction['explorer_url']) ? $transaction['explorer_url'] : '';
                    $trx_date = isset($transaction['date']) ? $transaction['date'] : current_time('mysql');

                    $order_crypto_req = floatval($get_order_data[0]->crypto_amount);
                    $order_fiat_req   = floatval($get_order_data[0]->fiat_amount);

                    $trx_exists = false;
                    foreach ($meta_data as &$item) {
                        if ($item['trx_id'] == $txid) {
                            $trx_exists = true;
                            $item['confirmations'] = $confirmations;
                            if ($confirmations >= $required_confirmations) {
                                $item['status'] = 1;
                            }
                            break;
                        }
                    }
                    unset($item);

                    if (!$trx_exists) {
                        $new_status = ($confirmations >= $required_confirmations) ? 1 : 0;
                        $meta_data[] = [
                            'payment_address' => $address,
                            'explorer_url'    => $explorer_url,
                            'crypto_amount'   => $transaction['amount'],
                            'coin_symbol'     => $coin_symbol,
                            'confirmations'   => $confirmations,
                            'coin_name'       => $coin,
                            'fiat_symbol'     => $store_currency,
                            'trx_id'          => $txid,
                            'status'          => $new_status,
                            'created_at'      => $trx_date,
                            'updated_at'      => $trx_date,
                        ];
                    }
                }

                if (!empty($meta_data)) {
                    $total_confirmed_crypto = 0;
                    $order_crypto_req = floatval($get_order_data[0]->crypto_amount);
                    $order_fiat_req   = floatval($get_order_data[0]->fiat_amount);

                    foreach ($meta_data as $m_trx) {
                        if (isset($m_trx['status']) && $m_trx['status'] == 1) {
                            $total_confirmed_crypto += floatval($m_trx['crypto_amount']);
                        }
                    }

                    $total_paid_fiat = ($order_crypto_req > 0) ? ($total_confirmed_crypto * $order_fiat_req) / $order_crypto_req : 0;

                    $option_data = get_option('woocommerce_coinremitterpayments_settings');
                    $ostatus = isset($option_data['ostatus']) ? $option_data['ostatus'] : 'completed';

                    if ($order_crypto_req > 0 && $total_confirmed_crypto >= $order_crypto_req) {
                        if ($total_confirmed_crypto > $order_crypto_req) {
                            $order_status_code = COINREMITTER_INV_OVER_PAID;
                        } else {
                            $order_status_code = COINREMITTER_INV_PAID;
                        }
                        $target_status = str_replace('wc-', '', $ostatus);
                        if ($wcOrderStatus !== $target_status && $wcOrderStatus !== 'cancelled' && $wcOrderStatus !== 'refunded') {
                            $order->update_status($target_status, __('Cryptocurrency payment received in full via CoinRemitter.', 'coinremitter-crypto-payment-gateway'));
                        }
                    } else if ($total_confirmed_crypto > 0) {
                        $order_status_code = COINREMITTER_INV_UNDER_PAID;
                        $remaining_crypto = max(0, round($order_crypto_req - $total_confirmed_crypto, 8));
                        if (round(floatval($get_order_data[0]->paid_crypto_amount), 8) !== round($total_confirmed_crypto, 8)) {
                            $order->add_order_note(sprintf(
                                __('Partial cryptocurrency payment received: %s %s. Remaining pending balance: %s %s.', 'coinremitter-crypto-payment-gateway'),
                                number_format($total_confirmed_crypto, 8, '.', ''),
                                strtoupper($coin),
                                number_format($remaining_crypto, 8, '.', ''),
                                strtoupper($coin)
                            ));
                        }
                    } else {
                        $order_status_code = COINREMITTER_INV_PENDING;
                    }

                    $wpdb->update(
                        $wpdb->prefix . 'coinremitter_orders',
                        array(
                            'paid_crypto_amount' => number_format($total_confirmed_crypto, 8, '.', ''),
                            'paid_fiat_amount'   => number_format($total_paid_fiat, 2, '.', ''),
                            'order_status'       => $order_status_code,
                        ),
                        array('order_id' => $order_id),
                        array('%s', '%s', '%d'),
                        array('%s')
                    );

                    if ($has_existing_order) {
                        $wpdb->update(
                            $wpdb->prefix . 'coinremitter_transactions',
                            array('meta' => json_encode($meta_data)),
                            array('order_id' => $order_id)
                        );
                    } else {
                        $wpdb->insert(
                            $wpdb->prefix . 'coinremitter_transactions',
                            array(
                                'order_id' => $order_id,
                                'meta'     => json_encode($meta_data),
                            )
                        );
                    }

                    // Refresh order data object so table display below reflects the latest state
                    $get_order_data = $wpdb->get_results($payment_query);
                }
            }
        }
        // --- [END] Direct API check ---
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
        $total_amount = floatval($total_amount);
        $paid_amount = floatval($paid_amount);
        $pending_amount = max(0, $total_amount - $paid_amount);
        $overpaid_amount = max(0, $paid_amount - $total_amount);
        $tr_tablename = $wpdb->prefix . 'coinremitter_transactions';
        $sql = $wpdb->prepare("SELECT * FROM $tr_tablename WHERE order_id = %s", $order_id);
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
                    <td class="label greeColour"><a title="' . __('Transaction Details', 'coinremitter-crypto-payment-gateway') . ' - ' . $trx_id . '" href="' . $explorer_url . '" target="_blank"><strong>' . substr($trx_id, 0, 20) . '....</strong></a></td>
                    <td> ' . sprintf('%.8f', $crypto_amount) . ' ' . $coin_symbol . '</td>
                    <td>' . $created_at . '</td>
                    <td style="text-align:center"><img src="' . CR_PLUGIN_PATH . 'images/checked.gif' . '"></td>
                    </tr>';
                }
            }
        } else if (isset($payment_history) && is_array($payment_history)) {
            foreach ($payment_history as $key => $value) {
                $temp2 .= '<tr>
				<td class="label greeColour"><a title="' . __('Transaction Details', 'coinremitter-crypto-payment-gateway') . ' - ' . $value['txid'] . '" href="' . $value['explorer_url'] . '" target="_blank"><strong>' . substr($value['txid'], 0, 20) . '....</strong></a></td>
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


        if ($overpaid_amount > 0) {
            $box_width = '19%';
            $overpaid_html = "<div class='inside cr_table' id='postcustomstuff' style='width:{$box_width};float:left;'><table class='wc-crpto-data'>
		<thead>
		<tr>
		<th style='text-align: left' colspan='2'>Overpaid Amount</th>
		</tr>
		</thead>
		<tbody>
		<table class='wc-crpto-data'>
		<tbody><tr><td style='color:#28a745;font-weight:bold;'>" . number_format($overpaid_amount, 8, '.', '') . "</td></tr></tbody>
		</table>
		</tbody>
		</table></div>";
        } else {
            $box_width = '24%';
            $overpaid_html = '';
        }

        $pending_html = "<div class='inside cr_table' id='postcustomstuff' style='width:{$box_width};float:left;'><table class='wc-crpto-data'>
		<thead>
		<tr>
		<th style='text-align: left' colspan='2'>Pending Amount</th>
		</tr>
		</thead>
		<tbody>
		<table class='wc-crpto-data'>
		<tbody><tr><td>" . number_format($pending_amount, 8, '.', '') . "</td></tr></tbody>
		</table>
		</tbody>
		</table></div>";
        $desc_html = "<div class='inside cr_table' id='postcustomstuff' style='width:{$box_width};float:left;'><table class='wc-crpto-data'>
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
        $t_html = "<div class='inside cr_table' id='postcustomstuff' style='width:{$box_width};float:left;'><table class='wc-crpto-data'>
		<thead>
		<tr>
		<th style='text-align: left' colspan='2'>Order Amount</th>
		</tr>
		</thead>
		<tbody>
		<table class='wc-crpto-data'>
		<tbody><tr><td>" . number_format($total_amount, 8, '.', '') . "</td></tr></tbody>
		</table>
		</tbody>
		</table></div>";
        $p_html = "<div class='inside cr_table' id='postcustomstuff' style='width:{$box_width};float:left;'><table class='wc-crpto-data'>
		<thead>
		<tr>
		<th style='text-align: left' colspan='2'> Paid Amount</th>
		</tr>
		</thead>
		<tbody>
		<table class='wc-crpto-data'>
		<tbody><tr><td>" . number_format($paid_amount, 8, '.', '') . "</td></tr></tbody>
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
        echo wp_kses_normalize_entities($detail . $url . $desc_html . $t_html . $p_html . $pending_html . $overpaid_html . $payment_html);
    }
}




/**
 * Fetch supported currencies using the Coinremitter API.
 */
function fetch_supported_currencies()
{
    $curl = COINREMITTER_CURL . '/rate/supported-currency';
    $userAgent = 'wordpress@' . WORDPRESS_VERSION . ', Woocommerce@'.WOOOCOMMERCE_VERSION.' crplugin@' . COINREMITTER_PLUGIN_VERSION;
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
    $userAgent = 'wordpress@' . WORDPRESS_VERSION . ', Woocommerce@'.WOOOCOMMERCE_VERSION.' crplugin@' . COINREMITTER_PLUGIN_VERSION;
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
    $userAgent = 'wordpress@' . WORDPRESS_VERSION . ', Woocommerce@'.WOOOCOMMERCE_VERSION.' crplugin@' . COINREMITTER_PLUGIN_VERSION;
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
{   
    $api_url = COINREMITTER_CURL . '/wallet/balance';
    $userAgent = 'wordpress@' . WORDPRESS_VERSION . ', Woocommerce@'.WOOOCOMMERCE_VERSION.' crplugin@' . COINREMITTER_PLUGIN_VERSION;
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
    $userAgent = 'wordpress@' . WORDPRESS_VERSION . ', Woocommerce@'.WOOOCOMMERCE_VERSION.' crplugin@' . COINREMITTER_PLUGIN_VERSION;
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
    $userAgent = 'wordpress@' . WORDPRESS_VERSION . ', Woocommerce@'.WOOOCOMMERCE_VERSION.' crplugin@' . COINREMITTER_PLUGIN_VERSION;
    $body = json_encode(['label' => $coin]);
    $args = [
        'method' => 'POST',
        'timeout' => 30,
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
    if (is_wp_error($response)) {
        return [
            'success' => 0,
            'msg' => $response->get_error_message()
        ];
    }
    $data = wp_remote_retrieve_body($response);
    $result = json_decode($data, true);
    if (!is_array($result)) {
        return [
            'success' => 0,
            'msg' => sprintf(__('%s server is currently unavailable or offline.', 'coinremitter-crypto-payment-gateway'), strtoupper($coin))
        ];
    }
    return $result;
}

// transaction create api call
function createTransaction($id, $CurrAPIKey, $CurrPassword)
{
    $trx_url = COINREMITTER_CURL . '/wallet/transaction'; 
    $userAgent = 'wordpress@' . WORDPRESS_VERSION . ', Woocommerce@'.WOOOCOMMERCE_VERSION.' crplugin@' . COINREMITTER_PLUGIN_VERSION;
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
