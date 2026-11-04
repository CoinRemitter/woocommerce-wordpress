<?php
if (!defined('ABSPATH')) {
    exit;
}
require_once CR_PLUGIN_DIR . '/front/cron-event.php';

#--------------------------------------------------------------------------------
#region coinremitter payment setting
#--------------------------------------------------------------------------------

require_once CR_PLUGIN_DIR . '/admin/coinremitter-payment-setting.php';

// Hook the custom function to the 'woocommerce_blocks_loaded' action
add_action('woocommerce_blocks_loaded', 'oawoo_register_order_approval_payment_method_type');
/**
 * Custom function to register a payment method type

 */
function oawoo_register_order_approval_payment_method_type()
{
    // Check if the required class exists
    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        return;
    }

    // Include the custom Blocks Checkout class
    require_once plugin_dir_path(__FILE__) . '/payment-class-block.php';

    // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            // Register an instance of My_Custom_Gateway_Blocks
            $payment_method_registry->register(new My_Custom_Gateway_Blocks);
        }
    );
}


function is_checkout_block()
{
    return WC_Blocks_Utils::has_block_in_page(wc_get_page_id('checkout'), 'woocommerce/checkout');
}


// Block direct browser address-bar visits with 404 while allowing genuine AJAX calls
function coinremitter_block_direct_browser_access()
{
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
        global $wp_query;
        if (is_object($wp_query)) {
            $wp_query->set_404();
        }
        status_header(404);
        nocache_headers();
        $template = get_query_template('404');
        if ($template && file_exists($template)) {
            include($template);
        } else {
            echo '<h1>404 Not Found</h1>';
        }
        exit;
    }
}

// session currency store 
function store_rel_value()
{
    coinremitter_block_direct_browser_access();
    check_ajax_referer('coinremitter_store_rel_nonce', 'security');

    $rel_value = isset($_POST['rel_value']) ? sanitize_text_field($_POST['rel_value']) : '';

    if (isset(WC()->session)) {
        WC()->session->set('coinremitter_selected_coin', $rel_value);
    }
    // Set transient as fallback
    set_transient('currency_value', $rel_value, HOUR_IN_SECONDS);
    wc_clear_notices(); // Clear old coin error notices when customer switches coin selection
    echo 'Transient set successfully: ' . $rel_value;
    wp_die();
}

// Clear payment error notices on Cart page so they only show on Checkout
add_action('template_redirect', 'coinremitter_clear_notices_on_cart');
function coinremitter_clear_notices_on_cart()
{
    if (is_cart()) {
        wc_clear_notices();
    }
}



function coinremitter_override_return_url($return_url, $order)
{
    if (!$order instanceof WC_Order) {
        return $return_url;
    }

    // Run invoice page redirect ONLY for coinremitterpayments
    $payment_method = $order->get_payment_method();
    if ($payment_method !== 'coinremitterpayments') {
        return $return_url; // <-- COD, PayPal, Razorpay, etc go to normal thank-you page
    }

    global $wpdb;
    global $woocommerce;
    $coin = '';
    if (isset(WC()->session)) {
        $coin = WC()->session->get('coinremitter_selected_coin');
    }
    if (empty($coin)) {
        $coin = get_transient('currency_value');
    }

    if (!$coin) {
        $msg = __('Please select a coin for cryptocurrency payment.', 'coinremitter-crypto-payment-gateway');
        $order->update_status('failed', $msg);
        if (isset(WC()->session)) {
            WC()->session->set('order_awaiting_payment', 0);
        }
        wc_add_notice($msg, 'error');
        throw new \Exception($msg);
    }

    $OrdID = $order->get_id();
    $order_total = floatval($order->get_total());

    $tablename = $wpdb->prefix . 'coinremitter_wallets';
    $sql = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tablename WHERE coin_symbol = %s", $coin));

    if (!$sql) {
        $msg = sprintf(__('Wallet configuration for %s was not found.', 'coinremitter-crypto-payment-gateway'), strtoupper($coin));
        $order->update_status('failed', $msg);
        if (isset(WC()->session)) {
            WC()->session->set('order_awaiting_payment', 0);
        }
        wc_add_notice($msg, 'error');
        throw new \Exception($msg);
    }

    $currancy_type = get_woocommerce_currency();
    $CurrAPIKey = decrypt_data($sql->api_key);
    $CurrPassword = decrypt_data($sql->password);
    $multiplier = (isset($sql->exchange_rate_multiplier) && floatval($sql->exchange_rate_multiplier) > 0) ? floatval($sql->exchange_rate_multiplier) : 1;
    $unit_fiat_amount = floatval($sql->unit_fiat_amount);

    $total_amount = $order_total * $multiplier;
    $convert_amount = ($unit_fiat_amount > 0) ? ($total_amount / $unit_fiat_amount) : 0;
    $crypto_amount = number_format($convert_amount, 8, '.', '');

    if ($order->get_payment_method() !== 'coinremitterpayments') {
        if (isset(WC()->session)) {
            WC()->session->__unset('coinremitter_selected_coin');
        }
        delete_transient('currency_value');
    }

    $userID = get_current_user_id();
    $s_url = site_url();
    $test_order = new WC_Order($OrdID);
    $test_order_key = $test_order->get_order_key();
    $sss_url = $s_url . '/index.php/invoice-page/' . $OrdID . '/?pay_for_order=true&key=' . $test_order_key;

    if ($multiplier == 0 || $multiplier == '') {
        $invoice_exchange_rate = 1;
    } else {
        $invoice_exchange_rate = $multiplier;
    }
    $multiplier = $sql->exchange_rate_multiplier;
    $coin_name = $sql->coin_name;

    $result_arr = create_address($coin, $CurrAPIKey, $CurrPassword); //api call address create
    $result_arrs = (is_array($result_arr) && isset($result_arr['success'])) ? $result_arr['success'] : 0;
    $apiMinimum_deposit_amount = (is_array($result_arr) && isset($result_arr['data']['minimum_deposit_amount'])) ? $result_arr['data']['minimum_deposit_amount'] : 0;

    if ($result_arrs == 1) {
        $option_data = get_option('woocommerce_coinremitterpayments_settings');
        if (empty($option_data['invoice_expiry'])) {
            $invoice_expiry = '';
        } else {
            $invoice_expiry = $option_data['invoice_expiry'];
        }
        $expiry_date = null;

        if ($invoice_expiry != "") {
            $expiry_date = gmdate("Y-m-d H:i:s", time() + (intval($invoice_expiry) * 60));
        }

        if ($crypto_amount >= $apiMinimum_deposit_amount) {
            $available_coins[] = $coin;
            $tablename_orders = $wpdb->prefix . 'coinremitter_orders';

            $wpdb->delete($tablename_orders, array('order_id' => $OrdID));

            $data = array(
                'order_id' => $OrdID,
                'user_id' => $userID,
                'coin_symbol' => sanitize_text_field($coin),
                'coin_name' => sanitize_text_field($coin_name),
                'crypto_amount' => floatval($crypto_amount),
                'fiat_symbol' => sanitize_text_field($currancy_type),
                'fiat_amount' => sanitize_text_field($total_amount),
                'paid_crypto_amount' => 0,
                'paid_fiat_amount' => 0,
                'payment_address' => sanitize_text_field($result_arr['data']['address']),
                'qr_code' => sanitize_text_field($result_arr['data']['qr_code']),
                'expiry_date' => $expiry_date,
                'order_status' => '0',
                'created_at' => gmdate("Y-m-d H:i:s"),
                'updated_at' => gmdate("Y-m-d H:i:s"),
            );

            $inserted = $wpdb->insert($tablename_orders, $data);

            if ($inserted === false) {
                $error = $wpdb->last_error;
                error_log("Failed to insert data into $tablename_orders: $error");
            } else {
                $address = isset($result_arr['data']['address']) ? $result_arr['data']['address'] : 'N/A';
                $qr_code = isset($result_arr['data']['qr_code']) ? $result_arr['data']['qr_code'] : 'N/A';

                error_log(sprintf(
                    "Data inserted successfully into %s, %d, %s, %s, %f, %s, %f, %s, %s",
                    $tablename_orders,
                    $OrdID,
                    $coin,
                    $coin_name,
                    $crypto_amount,
                    $currancy_type,
                    $total_amount,
                    $address,
                    $qr_code
                ));
            }
            update_post_meta($OrdID, '_order_crypto_price', $crypto_amount);
            update_post_meta($OrdID, '_order_crypto_coin', $coin);

            if ($payment_method === 'coinremitterpayments') {
                return $sss_url;
            }
        } else {
            $order->update_status('failed');
            if (isset(WC()->session)) {
                WC()->session->set('order_awaiting_payment', 0);
            }
            $tablename_orders = $wpdb->prefix . 'coinremitter_orders';
            $wpdb->delete($tablename_orders, array('order_id' => $OrdID));
            $msg = sprintf(__('Order total is less than the minimum deposit required (%s %s) for this coin.', 'coinremitter-crypto-payment-gateway'), $apiMinimum_deposit_amount, strtoupper($coin));
            wc_add_notice($msg, 'error');
            throw new \Exception($msg);
        }
    } else {
        $order->update_status('failed');
        if (isset(WC()->session)) {
            WC()->session->set('order_awaiting_payment', 0);
        }
        $tablename_orders = $wpdb->prefix . 'coinremitter_orders';
        $wpdb->delete($tablename_orders, array('order_id' => $OrdID));
        $msg = (is_array($result_arr) && isset($result_arr['msg']) && !empty($result_arr['msg']))
            ? $result_arr['msg']
            : sprintf(__('%s server is currently unavailable or offline.', 'coinremitter-crypto-payment-gateway'), strtoupper($coin));
        wc_add_notice($msg, 'error');
        throw new \Exception($msg);
    }
}


#--------------------------------------------------------------------------------
#region cr webhook
#--------------------------------------------------------------------------------


function coinremitter_webhook_data()
{
    coinremitter_block_direct_browser_access();
    check_ajax_referer('coinremitter_invoice_nonce', 'security');
    // echo 'coinremitter_webhook_data';
    global $wpdb;
    $addr = isset($_GET['addr']) ? sanitize_text_field($_GET['addr']) : '';
    if (empty($addr)) {
        error_log('CoinRemitter Webhook: missing addr parameter');
        wp_send_json_error(['message' => 'Missing addr'], 400);
        exit;
    }
    $orderWtablename = $wpdb->prefix . 'coinremitter_orders';
    $order_query = $wpdb->prepare(
        "SELECT * FROM $orderWtablename WHERE payment_address = %s",
        $addr
    );
    $order_data = $wpdb->get_results($order_query);

    if (empty($order_data)) {
        error_log('CoinRemitter Webhook: Order not found');
        wp_send_json_error(['message' => 'Missing addr'], 400);
        exit;
    }
    $order_data = $order_data[0];
    $order_id = $order_data->order_id;
    $coin_symbol = $order_data->coin_symbol;
    $expiry_date = $order_data->expiry_date;
    $created_at = $order_data->created_at;
    $paidAmount = $order_data->paid_crypto_amount;
    $paidFiatAmount = $order_data->paid_fiat_amount;
    $fiat_amount = $order_data->fiat_amount;

    $order = wc_get_order($order_id);
    if (!$order || !is_a($order, 'WC_Order')) {
        wp_send_json_error(['message' => 'Invalid order'], 404);
        exit;
    }

    $order_key = $order->get_order_key();
    $wcOrderStatus = $order->get_status();

    if ($order_data->order_status == COINREMITTER_INV_EXPIRED || $wcOrderStatus == 'cancelled') {
        $url = method_exists($order, 'get_cancel_order_url_raw') ? $order->get_cancel_order_url_raw() : $order->get_cancel_order_url();
        $Result['link'] = $url;
        $Result['flag'] = '2';
        $Result['is_cancelled'] = 1;
        echo wp_json_encode($Result);
        return false;
    }

    // --- [START] Direct API check (Active fallback alongside Webhook) ---
    if ($order_data->order_status != COINREMITTER_INV_PAID && $order_data->order_status != COINREMITTER_INV_OVER_PAID) {
        $tablename = $wpdb->prefix . 'coinremitter_wallets';
        $walletData = $wpdb->get_row($wpdb->prepare("SELECT wallet_name, coin_symbol, minimum_invoice_amount, api_key, password FROM $tablename WHERE coin_symbol = %s", $coin_symbol));

        $api_key = '';
        $api_password = '';
        if ($walletData) {
            $api_key = decrypt_data($walletData->api_key);
            $api_password = decrypt_data($walletData->password);
        }

        $transaction_details = create_transaction_check($addr, $api_key, $api_password); //api call
        // echo '<pre>';print_r($transaction_details);echo '</pre>';

        $paid_amount = 0;

        $webhookTrandata = (is_array($transaction_details) && isset($transaction_details['data'])) ? $transaction_details['data'] : array();
        $address = isset($webhookTrandata['address']) ? $webhookTrandata['address'] : $addr;
        $required_confirmations = isset($webhookTrandata['required_confirmations']) ? $webhookTrandata['required_confirmations'] : 3;
        $coin = isset($webhookTrandata['coin']) ? $webhookTrandata['coin'] : $coin_symbol;
        error_log("new_webhookTrandata : " . json_encode($webhookTrandata));
        $coin_symbol = isset($webhookTrandata['coin_symbol']) ? $webhookTrandata['coin_symbol'] : $coin_symbol;
        $transactions = (isset($webhookTrandata['transactions']) && is_array($webhookTrandata['transactions'])) ? $webhookTrandata['transactions'] : array();

        if (!empty($transactions)) {
            $sql = "SELECT * FROM " . $wpdb->prefix . "coinremitter_transactions WHERE order_id = %s";
            $existing_order = $wpdb->get_results($wpdb->prepare($sql, $order_id));
            $meta_data = array();
            $has_existing_order = false;
            if (is_array($existing_order) && count($existing_order) > 0) {
                $has_existing_order = true;
                $decoded = json_decode($existing_order[0]->meta, true);
                if (is_array($decoded)) {
                    $meta_data = $decoded;
                }
            }

            $store_currency = !empty($order_data->fiat_symbol) ? $order_data->fiat_symbol : get_woocommerce_currency();

            foreach ($transactions as $transaction) {
                if (!isset($transaction['type']) || $transaction['type'] != 'receive') {
                    continue;
                }
                $txid = $transaction['txid'];
                $amount = floatval($transaction['amount']);
                $confirmations = isset($transaction['confirmations']) ? intval($transaction['confirmations']) : 0;
                $explorer_url = isset($transaction['explorer_url']) ? $transaction['explorer_url'] : '';
                $trx_date = isset($transaction['date']) ? $transaction['date'] : current_time('mysql');

                $order_crypto_req = floatval($order_data->crypto_amount);
                $order_fiat_req   = floatval($order_data->fiat_amount);

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
                $order_crypto_req = floatval($order_data->crypto_amount);
                $order_fiat_req   = floatval($order_data->fiat_amount);

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
                    if (round(floatval($order_data->paid_crypto_amount), 8) !== round($total_confirmed_crypto, 8)) {
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

                $paidAmount = $total_confirmed_crypto;
                $paidFiatAmount = $total_paid_fiat;

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
            }
        }
    }
    // --- [END] Direct API check ---
    // awaiting data

    $webhook_data = $wpdb->prepare(
        "SELECT * FROM " . $wpdb->prefix . "coinremitter_transactions WHERE order_id = %s",
        $order_id
    );
    $web_hook = $wpdb->get_results($webhook_data);
    if (isset($web_hook[0]) && is_object($web_hook[0]) && isset($web_hook[0]->meta)) {
        $meta_data = json_decode($web_hook[0]->meta, true);
    } else {
        $meta_data = null;
    }


    $web_hook_data = '';
    $noexpitytime = 0;
    if (count($web_hook) === 1 || $expiry_date == "") {
        $noexpitytime = 1;
    } else {
        $expiry_date = date("M d, Y H:i:s", strtotime($expiry_date));
    }
    $web_hook_data .= "<input type='hidden' id='expiry_time' value='" . $expiry_date . "'>";
    if (count($web_hook) > 0) {
        foreach ($meta_data as $transaction) {
            $created_at = $transaction['created_at'];
            $trx_id = $transaction['trx_id'];
            $explorer_urll = $transaction['explorer_url'];
            $crypto_amount = $transaction['crypto_amount'];
            $confirmations_data = $transaction['confirmations'];

            $create_date = strtotime($created_at);
            $c_date = date('Y-m-d H:i:s', $create_date);
            $seconds = strtotime(gmdate('Y-m-d H:i:s')) - strtotime($created_at);
            $years = floor($seconds / (365 * 60 * 60 * 24));
            $months = floor(($seconds - $years * 365 * 60 * 60 * 24) / (30 * 60 * 60 * 24));
            $days = floor($seconds / 86400);
            $hours = floor(($seconds - ($days * 86400)) / 3600);
            $minutes = floor(($seconds - ($days * 86400) - ($hours * 3600)) / 60);
            $seconds = floor(($seconds - ($days * 86400) - ($hours * 3600) - ($minutes * 60)));

            if ($years > 0) {
                $diff = $years . " year(s) ago";
            } else if ($months > 0) {
                $diff = $months . " month(s) ago";
            } else if ($days > 0) {
                $diff = $days . " day(s) ago";
            } else if ($hours > 0) {
                $diff = $hours . " hour(s) ago";
            } else if ($minutes > 0) {
                $diff = $minutes . " minute(s) ago";
            } else {
                $diff = $seconds . " second(s) ago";
            }

            $c_date = date("M d, Y H:i:s", strtotime($create_date));

            if ($transaction['status'] == 1) {
                $icon = '<div class="cr-plugin-history-ico" title="Payment Confirmed" >
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <g clip-path="url(#clip0_2377_40)">
                                    <path
                                        d="M12 0C5.373 0 0 5.373 0 12C0 18.627 5.373 24 12 24C18.627 24 24 18.627 24 12C24 5.373 18.627 0 12 0ZM10.75 17.292L6.25 12.928L8.107 11.07L10.75 13.576L16.393 7.792L18.25 9.649L10.75 17.292Z"
                                        fill="#166534" />
                                </g>
                                <defs>
                                    <clippath id="clip0_2377_40">
                                        <rect width="24" height="24" fill="white" />
                                    </clippath>
                                </defs>
                            </svg>
                        </div>';
            } else {
                $icon = '<div class="cr-plugin-history-ico" title="' . $confirmations_data . ' confirmation(s)">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <g clip-path="url(#clip0_2377_49)">
                    <path d="M12 0C5.373 0 0 5.373 0 12C0 18.627 5.373 24 12 24C18.627 24 24 18.627 24 12C24 5.373 18.627 0 12 0ZM11 5H13V15H11V5ZM12 19.25C11.31 19.25 10.75 18.69 10.75 18C10.75 17.31 11.31 16.75 12 16.75C12.69 16.75 13.25 17.31 13.25 18C13.25 18.69 12.69 19.25 12 19.25Z" fill="#F59E0B"/>
                    </g>
                    <defs>
                    <clipPath id="clip0_2377_49">
                    <rect width="24" height="24" fill="white"/>
                    </clipPath>
                    </defs>
                </svg>
                </div>';
            }

            $web_hook_data .= '<div class="cr-plugin-history-box">
            <div class="cr-plugin-history d-flex justify-content-between ">
            <div class="d-flex gap-2 flex-nowrap">
            ' . $icon . '
            <div class="cr-plugin-history-des">
            <span><a class="text-dark" href="' . esc_url($explorer_urll) . '" target="_blank">' . esc_html(substr($trx_id, 0, 30)) . '...</a></span>
                                            <div class="cr-plugin-history-date" title="' . esc_attr($c_date) . ' (UTC)"><span>' . esc_html($diff) . '</span></div>
                                            </div>
                                    </div>';
            if ($confirmations_data >= 3) {
                $web_hook_data .= '
                                    <div class="text-success fw-bold">
                                        ' . $crypto_amount . ' <br class="d-none d-sm-block" />
                                        ' . $coin_symbol . '
                                    </div>';
            } else {
                $web_hook_data .= '
                                    <div class="text-pending fw-bold">
                                        ' . $crypto_amount . ' <br class="d-none d-sm-block" />
                                        ' . $coin_symbol . '
                                    </div>';
            }
            $web_hook_data .= '</div>
                                </div>';
        }
    } else {
        $web_hook_data .=  '<div class="cr-plugin-history-box">
                                <div class="cr-plugin-history" style="text-align: center; padding-left: 0;">
                                <span>No payment history found</span>
                                </div>
                            </div>';
    }

    $order_table_name = $wpdb->prefix . 'coinremitter_orders';
    $order = wc_get_order($order_id);
    $query = $wpdb->prepare("SELECT * FROM $order_table_name WHERE order_id = %s", $order_id);
    $get_order_data = $wpdb->get_results($query);

    $current_order_status = (!empty($get_order_data)) ? $get_order_data[0]->order_status : $order_data->order_status;
    $current_paid_amount = (!empty($get_order_data) && isset($get_order_data[0]->paid_crypto_amount)) ? $get_order_data[0]->paid_crypto_amount : $paidAmount;

    $symbol = get_woocommerce_currency_symbol();
    $order_items = '';
    $position = get_option('woocommerce_currency_pos');
    $prefix = '';
    $suffix = '';

    switch ($position) {
        case 'left_space':
            $prefix = $symbol . ' ';
            break;
        case 'left':
            $prefix = $symbol;
            break;
        case 'right_space':
            $suffix = ' ' . $symbol;
            break;
        case 'right':
            $suffix = $symbol;
            break;
    }
    $decimal_separator = wc_get_price_decimal_separator();
    $thousand_separator = wc_get_price_thousand_separator();
    $decimals = wc_get_price_decimals();

    $paidpricepadamount = floatval($current_paid_amount);
    $decimalspadamount = 8;
    $formatted_pricepadAmount_paid = number_format($paidpricepadamount, $decimalspadamount, $decimal_separator, $thousand_separator);
    $formatted_price_cur_paid = $formatted_pricepadAmount_paid . $suffix;

    $crypto_amount = $order_data->crypto_amount;
    $fiat_amount = $order_data->fiat_amount;
    $padding_amount = $crypto_amount - $current_paid_amount;

    $pricepadamount = floatval($padding_amount > 0 ? $padding_amount : 0);
    $decimalspadamount = 8;
    $formatted_pricepadAmount = number_format($pricepadamount, $decimalspadamount, $decimal_separator, $thousand_separator);
    $formatted_price_cur_pad = $formatted_pricepadAmount . $suffix;

    if (!empty($get_order_data) && ($current_order_status == COINREMITTER_INV_PAID || $current_order_status == COINREMITTER_INV_OVER_PAID)) {
        $url = $order->get_checkout_order_received_url();
        $Result['link'] = $url;
        $Result['flag'] = '2';
        $Result['coin_symbol'] = $coin_symbol;
        $Result['padding_amount'] = $formatted_price_cur_pad;
        $Result['paid_amount'] = $formatted_price_cur_paid;
        $Result['expiry'] = $noexpitytime;
        $Result['data'] = $web_hook_data;
        echo wp_json_encode($Result);
        return false;
    } else if (!empty($get_order_data) && ($current_order_status == COINREMITTER_INV_EXPIRED || $order->get_status() == 'cancelled')) {
        $url = method_exists($order, 'get_cancel_order_url_raw') ? $order->get_cancel_order_url_raw() : $order->get_cancel_order_url();
        $Result['link'] = $url;
        $Result['flag'] = '2';
        $Result['is_cancelled'] = 1;
        echo wp_json_encode($Result);
        return false;
    }

    $Result['coin_symbol'] = $coin_symbol;
    $Result['padding_amount'] = $formatted_price_cur_pad;
    $Result['paid_amount'] = $formatted_price_cur_paid;
    $Result['expiry'] = $noexpitytime;
    $Result['data'] = $web_hook_data;
    $Result['flag'] = '1';
    echo wp_json_encode($Result);
    return false;
}


#--------------------------------------------------------------------------------
#region cr cancel order
#--------------------------------------------------------------------------------


if (!function_exists('coinremitter_cancel_order')) {
    function coinremitter_cancel_order()
    {
        coinremitter_block_direct_browser_access();
        check_ajax_referer('coinremitter_invoice_nonce', 'security');
        error_log('coinremitter_cancel_order');
        global $wpdb;

        $order_id  = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        $order_key = isset($_GET['order_key']) ? sanitize_text_field(wp_unslash($_GET['order_key'])) : '';
        $order     = $order_id ? wc_get_order($order_id) : false;

        $forbidden = function () {
            wp_send_json(array('flag' => 0, 'message' => 'Forbidden.'), 403);
        };

        if (!$order || !is_a($order, 'WC_Order')) {
            $forbidden();
        }

        // Authorization: Owner, Shop Manager, or Guest with valid Order Key
        $is_owner     = (int) $order->get_customer_id() > 0 && (int) $order->get_customer_id() === get_current_user_id();
        $is_manager   = current_user_can('manage_woocommerce');
        $is_valid_key = !empty($order_key) && hash_equals($order->get_order_key(), $order_key);

        if (!$is_owner && !$is_manager && !$is_valid_key) {
            $forbidden();
        }

        // Only CoinRemitter orders that are still awaiting payment.
        if ($order->get_payment_method() !== 'coinremitterpayments') {
            $forbidden();
        }
        if (!$order->has_status(array('pending', 'on-hold'))) {
            $forbidden();
        }

        $web_hook = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT order_status, expiry_date FROM {$wpdb->prefix}coinremitter_orders WHERE order_id = %d",
                $order_id
            )
        );
        if (!$web_hook) {
            $forbidden();
        }

        // Cancel order if not paid/overpaid
        if (
            (int) $web_hook->order_status !== COINREMITTER_INV_PAID
            && (int) $web_hook->order_status !== COINREMITTER_INV_OVER_PAID
        ) {
            $order->update_status('cancelled', __('Order cancelled / expired before cryptocurrency payment was received.', 'coinremitter-crypto-payment-gateway'));
            $wpdb->update(
                $wpdb->prefix . 'coinremitter_orders',
                array('order_status' => COINREMITTER_INV_EXPIRED),
                array('order_id' => $order_id),
                array('%d'),
                array('%d')
            );
        }

        // Only an authorised owner/key-holder receives the cancel URL (raw unescaped for payment gateways).
        $cancel_url = method_exists($order, 'get_cancel_order_url_raw') ? $order->get_cancel_order_url_raw() : $order->get_cancel_order_url();
        wp_send_json(array('flag' => 1, 'url' => $cancel_url));
    }
}

#--------------------------------------------------------------------------------
#region cr thank you
#--------------------------------------------------------------------------------

function coinremitter_thank_you_field_display_cust_order_meta($d)
{
    error_log('coinremitter_thank_you_field_display_cust_order_meta');
    global $wpdb;

    if (!is_a($d, 'WC_Order')) {
        return;
    }

    // Show ONLY for CoinRemitter payment method
    if ($d->get_payment_method() !== 'coinremitterpayments') {
        return;
    }

    $order_id = $d->get_id();

    $webhook_data = $wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "coinremitter_transactions WHERE order_id = %s", $order_id);
    $web_hook = $wpdb->get_results($webhook_data);
    $meta_data = (!empty($web_hook) && isset($web_hook[0]->meta)) ? json_decode($web_hook[0]->meta, true) : array();
    if (!is_array($meta_data)) {
        $meta_data = array();
    }

    $addrSql = $wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "coinremitter_orders WHERE order_id = %s", $order_id);
    $getDatas = $wpdb->get_results($addrSql);
    if (empty($getDatas)) {
        return;
    }
    $getData = $getDatas[0];
    $coin_type = isset($getData->coin_symbol) ? $getData->coin_symbol : '';
    $address = isset($getData->payment_address) ? $getData->payment_address : '';
    $method = 'Coinremitter';
    $payments_date = isset($getData->created_at) ? $getData->created_at : '';
    $total_amount = isset($getData->crypto_amount) ? floatval($getData->crypto_amount) : 0;
    $paid_amount = isset($getData->paid_crypto_amount) ? floatval($getData->paid_crypto_amount) : 0;
    $status = isset($getData->order_status) ? $getData->order_status : '';
    $pending_amount = max(0, round($total_amount - $paid_amount, 8));
    $overpaid_amount = max(0, round($paid_amount - $total_amount, 8));

    // if ($status == COINREMITTER_INV_PENDING) {

    //     $order_status = "Pending";
    // } else if ($status == COINREMITTER_INV_UNDER_PAID) {

    //     $order_status = "Under paid";
    // } else if ($status == COINREMITTER_INV_OVER_PAID) {

    //     $order_status = "Over paid";
    // } else if ($status == COINREMITTER_INV_PAID) {

    //     $order_status = "Paid";
    // }


    // Get Woo order status
    $woo_status = $d->get_status(); // e.g., 'completed', 'pending', 'cancelled'

    if ($woo_status === 'cancelled') {
        $order_status = "Cancelled";
    } else {
        // Original CoinRemitter mapping
        if ($status == COINREMITTER_INV_PENDING) {
            $order_status = "Pending";
        } else if ($status == COINREMITTER_INV_UNDER_PAID) {
            $order_status = "Under paid";
        } else if ($status == COINREMITTER_INV_OVER_PAID) {
            $order_status = "Over paid";
        } else if ($status == COINREMITTER_INV_PAID) {
            $order_status = "Paid";
        }
    }

?>

    <div class="thank-page container card p-4 border shadow-sm rounded">

        <h2 class="mb-4">CoinRemitter</h2>

        <!-- SECTION 1 → MAIN INFO -->
        <div class="row g-3 mb-4">

            <div class="col-md-4">
                <div class="card p-3 h-100 shadow-sm">
                    <span class="fw-bold">Payment Address</span>
                    <div class="mt-1 t--value"><?php echo $address ?></div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card p-3 h-100 shadow-sm">
                    <span class="fw-bold">Payment Date</span>
                    <div class="mt-1 t--value"><?php echo $payments_date ?> (UTC)</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card p-3 h-100 shadow-sm">
                    <span class="fw-bold">Payment Status</span>

                    <?php if ($order_status === "Paid") { ?>
                        <div class="alert alert-success mt-2 py-1 mb-0"><?php echo $order_status ?></div>
                    <?php } elseif ($order_status === "Cancelled") { ?>
                        <div class="alert alert-danger mt-2 py-1 mb-0"><?php echo $order_status ?></div>
                    <?php } else { ?>
                        <div class="alert alert-primary mt-2 py-1 mb-0"><?php echo $order_status ?></div>
                    <?php } ?>

                </div>
            </div>

        </div>

        <!-- NEW 2-COLUMN LAYOUT: LEFT (TRANSACTIONS), RIGHT (TOTALS) -->
        <div class="row g-4">

            <!-- LEFT COLUMN → TRANSACTIONS -->
            <div class="col-md-6 d-flex">
                <div class="card h-100 shadow-sm flex-fill">
                    <div class="card-body">

                        <h5 class="mb-3">Transactions</h5>

                        <div class="list-group">

                            <?php foreach ($meta_data as $transaction) { ?>
                                <div class="list-group-item">

                                    <a href="<?php echo esc_url($transaction['explorer_url']); ?>"
                                        target="_blank" rel="noopener noreferrer"
                                        class="fw-bold text-break d-block mb-1 text-danger">
                                        <?php echo esc_html($transaction['trx_id']); ?>
                                    </a>

                                    <span class="text-muted small d-block mb-2">
                                        <?php echo $transaction['created_at']; ?> (UTC)
                                    </span>

                                    <div class="d-flex justify-content-between">
                                        <span class="fw-bold t--value">
                                            <?php echo $transaction['crypto_amount']; ?>
                                        </span>
                                        <span class="text-muted">
                                            <?php echo $coin_type; ?>
                                        </span>
                                    </div>

                                </div>
                            <?php } ?>

                        </div>

                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN → TOTALS -->
            <div class="col-md-6 d-flex">
                <div class="card h-100 shadow-sm flex-fill p-3 ">
                    <?php if ($status == COINREMITTER_INV_OVER_PAID && $overpaid_amount > 0.00000001) { ?>
                        <!-- Overpaid Amount -->
                        <div class="d-flex justify-content-between align-items-center mb-2 border-bottom border-2 border-secondary pb-2">
                            <span class="fw-semibold">Overpaid Amount</span>
                            <span class="t--value text-end fw-bold text-success">
                                <?php echo number_format($overpaid_amount, 8, '.', ''); ?>
                                <sub><?php echo $coin_type ?></sub>
                            </span>
                        </div>
                    <?php } ?>
                    <!-- Pending Amount -->

                    <div class="d-flex justify-content-between align-items-center mb-2 border-bottom border-2 border-secondary pb-2">
                        <span class="fw-semibold">Pending Amount</span>
                        <span class="t--value text-end fw-bold <?php echo ($pending_amount > 0) ? 'text-danger' : 'text-muted'; ?>">
                            <?php echo number_format($pending_amount, 8, '.', ''); ?>
                            <sub><?php echo $coin_type ?></sub>
                        </span>
                    </div>
                    <!-- Paid Amount -->
                    <div class="d-flex justify-content-between align-items-center mb-2 border-bottom border-2 border-secondary pb-2">
                        <span class="fw-semibold">Paid Amount</span>
                        <span class="t--value text-end fw-bold">
                            <?php echo number_format($paid_amount, 8, '.', ''); ?>
                            <sub><?php echo $coin_type ?></sub>
                        </span>
                    </div>
                    <!-- Total Amount -->
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Total Amount</span>
                        <span class="t--value text-end fw-bold">
                            <?php echo number_format($total_amount, 8, '.', ''); ?>
                            <sub><?php echo $coin_type ?></sub>
                        </span>
                    </div>

                </div>
            </div>

        </div>

    </div>

<?php
    // echo wp_kses_normalize_entities($div_html);
}


function add_custom_class_to_body($classes)
{
    if (class_exists('woocommerce')) {

        if (is_page('invoice-page')) {
            $classes[] = 'coinremitter-invoice-page';
        }
    }
    return $classes;
}

function coinremitter_get_encryption_key()
{
    if (defined('AUTH_KEY') && !empty(AUTH_KEY)) {
        return hash('sha256', AUTH_KEY, true);
    }
    $key = get_option('coinremitter_secret_key');
    if (!$key) {
        $key = wp_generate_password(64, true, true);
        update_option('coinremitter_secret_key', $key);
    }
    return hash('sha256', $key, true);
}

function encrypt_data($data)
{
    if (empty($data)) {
        return '';
    }
    $key = coinremitter_get_encryption_key();
    $iv  = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) {
        return '';
    }
    return 'crv2:' . base64_encode($iv . $encrypted);
}

function decrypt_data($encrypted_data)
{
    if (empty($encrypted_data)) {
        return '';
    }

    // v2: Decrypt with site-specific key and dynamic IV
    if (strpos($encrypted_data, 'crv2:') === 0) {
        $raw = base64_decode(substr($encrypted_data, 5));
        if ($raw !== false && strlen($raw) > 16) {
            $iv         = substr($raw, 0, 16);
            $ciphertext = substr($raw, 16);
            $key        = coinremitter_get_encryption_key();
            $decrypted  = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
            if ($decrypted !== false && $decrypted !== '') {
                return $decrypted;
            }
        }
    }

    // Legacy fallback: Decrypt existing wallets stored with the legacy key
    $legacy_key = defined('COINREMITTER_SKEY') ? COINREMITTER_SKEY : 'coinremitterdata';
    $iv = substr($legacy_key, 0, 16);
    if (strlen($iv) < 16) {
        $iv = str_pad($iv, 16, "\0");
    }
    $legacy_decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', $legacy_key, 0, $iv);
    if ($legacy_decrypted !== false && $legacy_decrypted !== '') {
        return $legacy_decrypted;
    }

    return $encrypted_data;
}

function coinremitter_right($str, $findme, $firstpos = true)
{
    $pos = ($firstpos) ? mb_stripos($str, $findme) : mb_strripos($str, $findme);

    if ($pos === false)
        return $str;
    else
        return mb_substr($str, $pos + mb_strlen($findme));
}

function callback_parse_request_coinremitter()
{
    if (
        isset($_GET['coinremitter_webhook']) ||
        isset($_GET['coinremitter-webhook']) ||
        isset($_GET['coinremitter.webhook']) ||
        in_array(strtolower(coinremitter_right($_SERVER["REQUEST_URI"], "/", false)), array("?coinremitter.webhook", "index.php?coinremitter.webhook", "?coinremitter_webhook", "index.php?coinremitter_webhook", "?coinremitter-webhook", "index.php?coinremitter-webhook"))
    ) {

        ob_clean();
        include_once(plugin_dir_path(__FILE__) . '/coinremitter.webhook.php');
        ob_flush();
        die;
    }
    return true;
}



#--------------------------------------------------------------------------------
#region admin scripts
#--------------------------------------------------------------------------------

function coinremitter_wp_admin_script()
{

    wp_enqueue_style('cr-style-bootstrap-admin', CR_PLUGIN_PATH . '/css/bootstrapcustom.min.css');
    wp_enqueue_style('bootstrapmin', CR_PLUGIN_PATH . '/css/bootstrapmin.css');
    wp_enqueue_style('custom-apmin', CR_PLUGIN_PATH . '/css/admin-custom.css');
    wp_enqueue_script('bootstrapminjs', CR_PLUGIN_PATH . '/js/bootstrap1.min.js', array('jquery'));
    wp_enqueue_script('jquery');
    wp_enqueue_script('my-ajax-script', CR_PLUGIN_PATH . '/js/admin-custom.js', array('jquery'));
    wp_localize_script('my-ajax-script', 'my_ajax_obj', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('coinremitter_ajax_nonce')
    ));
}

function script_common()
{
    wp_enqueue_style('cr-style', CR_PLUGIN_PATH . '/css/front.css');
    wp_enqueue_style('cr-style-bootstrap-admin', CR_PLUGIN_PATH . '/css/bootstrapcustom.min.css');
    wp_enqueue_style('bootstrapmin', CR_PLUGIN_PATH . '/css/bootstrapmin.css');
    wp_enqueue_script('bootstrapminjs', CR_PLUGIN_PATH . '/js/bootstrap1.min.js', array('jquery'));
    wp_enqueue_script('jquery');
    wp_enqueue_script('custom-js', CR_PLUGIN_PATH . 'js/custom.js', array('jquery'), COINREMITTER_PLUGIN_VERSION);
    wp_localize_script('custom-js', 'coinremitter_params', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('coinremitter_store_rel_nonce'),
        'invoice_nonce' => wp_create_nonce('coinremitter_invoice_nonce')
    ));
}
#--------------------------------------------------------------------------------
#region front scripts
#--------------------------------------------------------------------------------


function coinremitter_wp_plugin_scripts()
{
    // Register the script
    if (is_page('invoice-page')) {
        wp_enqueue_style('roboto_font', 'https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap');
        script_common();
        wp_enqueue_script('checkout-js', CR_PLUGIN_PATH . 'js/checkout.js', array('jquery'), COINREMITTER_PLUGIN_VERSION);
        wp_localize_script('checkout-js', 'coinremitter_invoice_params', array(
            'nonce'        => wp_create_nonce('coinremitter_invoice_nonce'),
            'is_logged_in' => is_user_logged_in() ? 1 : 0
        ));
    }

    if (is_checkout()) {
        if (is_checkout_block()) {
            script_common();
        }
        script_common();
    }
    if (
        is_wc_endpoint_url('order-received') ||
        is_wc_endpoint_url('view-order')
    ) {

        wp_enqueue_style('bootstrapmin', CR_PLUGIN_PATH . '/css/bootstrap.min.css');
    }
}
