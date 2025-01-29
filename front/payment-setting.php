<?php
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


// error_log('currency_update_checkout');
// session currency store 
function store_rel_value()
{

    $rel_value = isset($_POST['rel_value']) ? $_POST['rel_value'] : '';

    // Set transient
    set_transient('currency_value', $rel_value, HOUR_IN_SECONDS); // set transient for 1 hour
    echo 'Transient set successfully: ' . $rel_value;
    wp_die();
}



function coinremitter_override_return_url($return_url, $order)
{
    global $wpdb;
    global $woocommerce;
    $coin = get_transient('currency_value');

        if ($coin) {
            $OrdID = $order->get_id();
            $order_amount = $order->get_total();
            $order_subamount = $order->get_subtotal();
            $shipping = $order->get_shipping_total();
            $tax = $woocommerce->cart->get_shipping_tax();
            $discount_amount = $order->get_discount_total();

            $tablename = $wpdb->prefix . 'coinremitter_wallets';
            $sql = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tablename WHERE coin_symbol = %s", $coin));

                $currancy_type = get_woocommerce_currency();
                $CurrAPIKey = decrypt_data($sql->api_key);
                $CurrPassword = decrypt_data($sql->password);
                $multiplier = $sql->exchange_rate_multiplier;
                $unit_fiat_amount = $sql->unit_fiat_amount;

                $total_amount = ($order_subamount * $multiplier ) + $shipping + $tax;
                $Amount = $total_amount - $discount_amount;
                $convert_amount = $Amount / $unit_fiat_amount;
                $crypto_amount = number_format($convert_amount,8);
              

            $wc_gateways = new WC_Payment_Gateways();
            $payment_gateways = $wc_gateways->get_available_payment_gateways();
            foreach ($payment_gateways as $gateway) {
                $payment_data = $gateway->id;
                if($payment_data != 'coinremitterpayments'){
                    $transient_name = 'currency_value';
                    delete_transient($transient_name);
                }
            }
            $userID = get_current_user_id();
            $s_url = site_url();
            $test_order = new WC_Order($OrdID);
            $test_order_key = $test_order->get_order_key();
            $sss_url = $s_url . '/index.php/invoice-page/' . $OrdID . '/?pay_for_order=true&key=' . $test_order_key;

            $currancy_type = get_woocommerce_currency();

            if ($multiplier == 0 && $multiplier == '') {
                $invoice_exchange_rate = 1;
            } else {
                $invoice_exchange_rate = $multiplier;
            }
            $multiplier = $sql->exchange_rate_multiplier;
            $coin_name = $sql->coin_name;

            $result_arr = create_address($coin, $CurrAPIKey, $CurrPassword); //api call address create
            $result_arrs = $result_arr['success'];
           $apiMinimum_deposit_amount = $result_arr['data']['minimum_deposit_amount'];
        //    print_r($apiMinimum_deposit_amount);

            if ($result_arrs == 1) {

                $option_data = get_option('woocommerce_coinremitterpayments_settings');
                if ($option_data['invoice_expiry'] == 0 || $option_data['invoice_expiry'] == '') {
                    $invoice_expiry = '';
                } else {
                    $invoice_expiry = $option_data['invoice_expiry'];
                }
                $expiry_date = null;

                if ($invoice_expiry != "") {
                    $expiry_date = date("Y/m/d H:i:s", strtotime("+" . $invoice_expiry . " minutes"));
                }
                
                    if ($crypto_amount >= $apiMinimum_deposit_amount) {
                        $available_coins[] = $coin;
                        global $wpdb;
                        $tablename = $wpdb->prefix . 'coinremitter_orders';
        
                        $data = array(
                            'order_id' => $OrdID,
                            'user_id' => $userID,
                            'coin_symbol' => sanitize_text_field($coin),
                            'coin_name' => sanitize_text_field($coin_name),
                            'crypto_amount' => floatval($crypto_amount),
                            'fiat_symbol' => sanitize_text_field($currancy_type),
                            'fiat_amount' => sanitize_text_field($total_amount),
                            'paid_crypto_amount' => '',
                            'paid_fiat_amount' => '',
                            'payment_address' => sanitize_text_field($result_arr['data']['address']),
                            'qr_code' => sanitize_text_field($result_arr['data']['qr_code']),
                            'expiry_date' => $expiry_date,
                            'order_status' => '0',
                            'created_at' => gmdate("Y-m-d H:i:s"),
                            'updated_at' => gmdate("Y-m-d H:i:s"),
                        );
                        
                        $inserted = $wpdb->insert($tablename, $data);
        
                        if ($inserted === false) {
                            $error = $wpdb->last_error;
                            error_log("Failed to insert data into $tablename: $error");
                        } else {
                            $address = isset($result_arr['data']['address']) ? $result_arr['data']['address'] : 'N/A';
                            $qr_code = isset($result_arr['data']['qr_code']) ? $result_arr['data']['qr_code'] : 'N/A';
                            
                            // Log the data with proper formatting
                            error_log(sprintf(
                                "Data inserted successfully into %s, %d, %s, %s, %f, %s, %f, %s, %s",
                                $tablename,
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
        
                        if ($payment_data == 'coinremitterpayments') {
                            $modified_url = $sss_url;
                            return $modified_url;
                        }
                    } else {
                        // echo 'If the cart total is less than the minimum value';
                        wc_add_notice(__('If the cart total is less than the minimum value'), 'error');                   
                        // }
                        return false;
                    }

            } else {
                wp_delete_post($OrdID, force_delete: true);
                wc_add_notice(__($result_arr['msg']), 'error');
            }
        }

}


#--------------------------------------------------------------------------------
#region cr webhook
#--------------------------------------------------------------------------------


function coinremitter_webhook_data()
{
    // echo 'coinremitter_webhook_data';
    global $wpdb;
    $addr = isset($_GET['addr']) ? sanitize_text_field($_GET['addr']) : '';
    $orderWtablename = $wpdb->prefix . 'coinremitter_orders';
    $order_query = "SELECT * FROM $orderWtablename WHERE payment_address = '" . $addr . "' ";
    $order_data = $wpdb->get_results($order_query);

    if (count($order_data) > 0) {
        $order_id = $order_data[0]->order_id;
        $coin_symbol = $order_data[0]->coin_symbol;
        $expiry_date = $order_data[0]->expiry_date;
        $created_at = $order_data[0]->created_at;
        $paidAmount = $order_data[0]->paid_crypto_amount;
        $paidFiatAmount = $order_data[0]->paid_fiat_amount;
        $fiat_amount = $order_data[0]->fiat_amount;

        $order = new WC_Order($order_id);
        
        $order_key = $order->get_order_key();
        $wcOrderStatus = $order->get_status();

        $tablename = $wpdb->prefix . 'coinremitter_wallets';
        $sql = $wpdb->get_row($wpdb->prepare("SELECT wallet_name, coin_symbol, api_key, password FROM $tablename WHERE coin_symbol = %s", $coin_symbol));

        if ($sql) {
            $api_key = decrypt_data($sql->api_key);
            $api_password = decrypt_data($sql->password);
        }
        
        $transaction_details = create_transaction_check($addr, $api_key, $api_password); //api call
        // echo '<pre>';print_r($transaction_details);echo '</pre>';

        $paid_amount = 0;

        $webhookTrandata = $transaction_details['data'];
        $address = $webhookTrandata['address'];
        $required_confirmations = $webhookTrandata['required_confirmations'];
        $coin = $webhookTrandata['coin'];
        error_log("new_webhookTrandata : " . json_encode($webhookTrandata));
        $coin_symbol = $webhookTrandata['coin_symbol'];
        foreach ($webhookTrandata['transactions'] as $transaction) {
            $meta_json = '';
            $txid = $transaction['txid'];
            $amount = $transaction['amount'];
            $status = $transaction['status'];
            $confirmations = $transaction['confirmations'];
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
                $result_arr = fiat_cryoto($fiat_amount, $fiat_currency, $coin_symbol); //api call
                $fiat_price = $result_arr['data'][0]['price'];
                error_log("fiat_rate_result_arr : " . json_encode($result_arr));

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

                                error_log("webhook trucationVal : " . $paidFiatAmtWithTrunc . " walletTotalAmount: " . $walletTotalAmount . "paidFiatAmount". $paidFiatAmount. "confirmations". $confirmations);

                                $crypto_amount = $order_data[0]->crypto_amount;
                                $fiat_amount = $order_data[0]->fiat_amount;
                                $status = 0;
                                if ($paidAmount >= $order_data[0]->paid_fiat_amount) {
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

                                    if ($wcOrderStatus == 'pending' || $wcOrderStatus == 'processing' || $wcOrderStatus == 'Processing') {
                                        $order->update_status('completed');
                                        error_log("webhook Status change over paid");
                                        $order->update_status('wc-' . $ostatus);
                                    }
                                    $order_status = "Over paid";
                                    $order_status_code = COINREMITTER_INV_OVER_PAID; //3 

                                } else if ($crypto_amount == $paidAmount) {

                                    if ($wcOrderStatus == 'processing' || $wcOrderStatus == 'pending' || $wcOrderStatus == 'Processing') {
                                        $order->update_status('completed');
                                        error_log("webhook Status change paid");
                                        $order->update_status('wc-' . $ostatus);
                                    }
                                    $order_status = "Paid";
                                    $order_status_code = COINREMITTER_INV_PAID; //1

                                }
                                if ($crypto_amount <= $paidFiatAmtWithTrunc &&  $order_status_code == COINREMITTER_INV_UNDER_PAID) {
                                    if ($wcOrderStatus == 'processing' || $wcOrderStatus == 'pending' || $wcOrderStatus == 'Processing') {
                                        $order->update_status('completed');
                                        error_log("webhook Status change paid trucation val");
                                        $order->update_status('wc-' . $ostatus);
                                    }
                                    $order_status = "Paid";
                                    $order_status_code = COINREMITTER_INV_PAID; //3 

                                }
                                error_log('webhook Change order data : ' . " trx id : " . $txid . " order id : " . $order_id . ' : ' . $paidAmount . ' : ' . $paidFiatAmount . ' : ' . $order_status_code);
                                
                                    error_log("webhook Status change of trx_id : " . $data_up['trx_id'] ."confirmations " . $confirmations);
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
        // if (count($existing_order) <= 0) {
        if (isset($existing_order) && is_array($existing_order) && count($existing_order) <= 0) {
            $metaTrxs[] = $metaTrx;
            $meta_json = json_encode($metaTrxs);
            $data = [
                'order_id' => $order_id,
                'meta' => $meta_json,
            ];

            $wpdb->insert('wp_coinremitter_transactions', $data);
        }
        // awaiting data

        $webhook_data = "SELECT * FROM wp_coinremitter_transactions WHERE order_id = '" . $order_id . "'";
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

                if ($confirmations_data >= 3) {
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
                <span><a class="text-dark" href="' . $explorer_urll . '" target="_blank">' . substr($trx_id, 0, 30) . '...</a></span>
                                                <div class="cr-plugin-history-date" title="' . $c_date . ' (UTC)"><span>' . $diff . '</span></div>
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
    }

    $order_table_name = $wpdb->prefix . 'coinremitter_orders';
    $order = wc_get_order($order_id);
    $query = "SELECT * FROM $order_table_name WHERE order_id = " . $order_id . "";
    $get_order_data = $wpdb->get_results($query);
    if ($get_order_data[0]->order_status == COINREMITTER_INV_PAID || $get_order_data[0]->order_status == COINREMITTER_INV_OVER_PAID) {
        $url = site_url("index.php/checkout/?order-received=" . $order_id . "&key=" . $order_key . "");
        $Result['link'] = $url;
        $Result['flag'] = '2';
        echo wp_json_encode($Result);
        return false;
    } else if ($get_order_data[0]->order_status == COINREMITTER_INV_EXPIRED || $order->get_status() == 'cancelled') {

        $url = $order->get_cancel_order_url();
        $Result['link'] = $url;
        $Result['flag'] = '2';
        echo wp_json_encode($Result);
        return false;
    }
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

    $paidpricepadamount = floatval($paidAmount);
    $decimalspadamount = 8;
    $formatted_pricepadAmount_paid = number_format($paidpricepadamount, $decimalspadamount, $decimal_separator, $thousand_separator);
    $formatted_price_cur_paid = $formatted_pricepadAmount_paid . $suffix;

    $crypto_amount = $order_data[0]->crypto_amount;
    $fiat_amount = $order_data[0]->fiat_amount;
    $padding_amount = $crypto_amount - $paidAmount;

    $pricepadamount = floatval($padding_amount);
    $decimalspadamount = 8;
    $formatted_pricepadAmount = number_format($pricepadamount, $decimalspadamount, $decimal_separator, $thousand_separator);
    $formatted_price_cur_pad = $formatted_pricepadAmount . $suffix;

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
        error_log('coinremitter_cancel_order');
        global $wpdb;
        $order_id = isset($_GET['order_id']) ? sanitize_text_field($_GET['order_id']) : '';
        $order = wc_get_order($order_id);
        $order_canclled = $order->get_cancel_order_url();

        $tran_data = "SELECT * FROM wp_coinremitter_transactions WHERE order_id = '" . $order_id . "'";
        $trnweb_hook = $wpdb->get_results($tran_data);

        $webhook_data = "SELECT * FROM wp_coinremitter_orders WHERE order_id = '$order_id'";
        $web_hook = $wpdb->get_results($webhook_data);
        $expiry_date = $web_hook[0]->expiry_date;

        $diff = strtotime($expiry_date) - strtotime(gmdate('Y-m-d H:i:s'));

        if ($diff <= 0) {
            $order->update_status('cancelled');
            $status_code = COINREMITTER_INV_EXPIRED;
            $status = 'Expired';
            $sql = "UPDATE wp_coinremitter_orders  SET `order_status`='" . $status_code . "' WHERE  `order_id` = $order_id";
            $wpdb->get_results($sql);
        }
        $Result['url'] = $order_canclled;
        $Result['flag'] = 1;
        echo wp_json_encode($Result);
        return false;
    }
}


#--------------------------------------------------------------------------------
#region cr thank you
#--------------------------------------------------------------------------------

function coinremitter_thank_you_field_display_cust_order_meta($d)
{
    error_log('coinremitter_thank_you_field_display_cust_order_meta');
    global $wpdb;
    $dd = json_decode($d, true);
    if (isset($_GET['order-received'])) {
        $order_id = sanitize_text_field($_GET['order-received']);
    } else {
        $order_id = $d->get_order_number();
    }

    $webhook_data = "SELECT * FROM wp_coinremitter_transactions WHERE order_id = '" . $order_id . "'";
    $web_hook = $wpdb->get_results($webhook_data);
    $meta_data = json_decode($web_hook[0]->meta, true);

    $addrSql = 'select * from wp_coinremitter_orders where order_id ="' . $order_id . '"';
    $getDatas = $wpdb->get_results($addrSql);
    $getData = $getDatas[0];
    $coin_type = $getData->coin_symbol;
    $address = $getData->payment_address;
    $method = 'Coinremitter';
    $payments_date = $getData->created_at;
    $total_amount = json_decode($getData->crypto_amount, true);
    $paid_amount = json_decode($getData->paid_crypto_amount, true);
    $status = $getData->order_status;
    $pending_amount = $total_amount - $paid_amount;

    if ($status == COINREMITTER_INV_PENDING) {

        $order_status = "Pending";
    } else if ($status == COINREMITTER_INV_UNDER_PAID) {

        $order_status = "Under paid";
    } else if ($status == COINREMITTER_INV_OVER_PAID) {

        $order_status = "Over paid";
    } else if ($status == COINREMITTER_INV_PAID) {

        $order_status = "Paid";
    }

?>

    <div class="thank-page">
        <h2>CoinRemitter</h2>
        <div class="table-responsive">
            <table>
                <tbody>
                    <tr>
                        <td>
                            <span>Payment Address</span>
                            <div class="t--value"><?php echo $address ?></div>
                        </td>
                        <td>
                            <span>Payment Date</span>
                            <div class="t--value"><?php echo $payments_date ?> (UTC)</div>
                        </td>
                        <td>
                            <span>Payment Status</span>
                            <?php if($status == 1){ ?>
                            <div class="alert alert-success" role="alert"><?php echo $order_status ?></div> <?php } else { ?>
                                <div class="alert alert-primary" role="alert"><?php echo $order_status ?></div>
                           <?php } ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="pt-0 pb-0" colspan="2">
                            <table>
                                <tbody>
                                    <?php
                                    foreach ($meta_data as $transaction) {
                                        $created_at = $transaction['created_at'];
                                        $trx_id = $transaction['trx_id'];
                                        $explorer_url = $transaction['explorer_url'];
                                        $crypto_amount = $transaction['crypto_amount'];
                                 ?>
                                            <tr class="i--value">
                                                <td>
                                                    <a href="<?php echo $explorer_url; ?>"><?php echo $trx_id; ?></a>
                                                    <span><?php echo $created_at; ?> (UTC)</span>
                                                </td>
                                                <td>
                                                    <div class="t--value text-end"><?php echo $crypto_amount ?> <br> <span><?php echo $coin_type ?></span></div>
                                                </td>
                                            </tr> <?php
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </td>
                        <td class="pt-0 pb-0">
                            <table>
                                <tbody>
                                    <tr>
                                        <td>
                                            <div class="tpay-statys">
                                                <p>Total Amount</p>
                                                <div class="t--value text-end"><?php echo number_format($total_amount, 8) ?><sub><?php echo $coin_type ?></sub></div>
                                            </div>
                                            <div class="tpay-statys">
                                                <p>Paid Amount</p>
                                                <div class="t--value text-end"><?php echo number_format($paid_amount, 8) ?><sub><?php echo $coin_type ?></sub></div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                        <div class="tpay-statys">
                                                <p>Pending Amount</p>
                                                <div class="t--value text-end"><?php echo number_format($pending_amount, 8) ?><sub><?php echo $coin_type ?></sub></div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                </tbody>
            </table>
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


function encrypt_data($data)
{
    return openssl_encrypt($data, 'aes-256-cbc', COINREMITTER_SKEY, 0, substr(COINREMITTER_SKEY, 0, 16));
}
function decrypt_data($encrypted_data)
{

    $iv = substr(COINREMITTER_SKEY, 0, 16);

    // Pad the IV if it's shorter than 16 bytes
    if (strlen($iv) < 16) {
        $iv = str_pad($iv, 16, "\0");
    }

    return openssl_decrypt($encrypted_data, 'aes-256-cbc', COINREMITTER_SKEY, 0, $iv);
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
    if (in_array(strtolower(coinremitter_right($_SERVER["REQUEST_URI"], "/", false)), array("?coinremitter.webhook", "index.php?coinremitter.webhook", "?coinremitter_webhook", "index.php?coinremitter_webhook", "?coinremitter-webhook", "index.php?coinremitter-webhook"))) {

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

function coinremitter_wp_admin_script() {

    wp_enqueue_style('cr-style-bootstrap-admin', CR_PLUGIN_PATH . '/css/bootstrapcustom.min.css');
    wp_enqueue_style('bootstrapmin', CR_PLUGIN_PATH . '/css/bootstrapmin.css');
    wp_enqueue_style('custom-apmin', CR_PLUGIN_PATH . '/css/admin-custom.css');
    wp_enqueue_script('bootstrapminjs', CR_PLUGIN_PATH . '/js/bootstrap1.min.js', array('jquery'));
    wp_enqueue_script('admin-jquery-js', CR_PLUGIN_PATH . '/js/jquery.min.js', array('jquery'));
    wp_enqueue_script('my-ajax-script', CR_PLUGIN_PATH .'/js/admin-custom.js', array('jquery'));
    wp_localize_script('my-ajax-script', 'my_ajax_obj', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('my_nonce_action')
    ));
}

function script_common(){
    wp_enqueue_style('cr-style', CR_PLUGIN_PATH . '/css/front.css');
    wp_enqueue_style('cr-style-bootstrap-admin', CR_PLUGIN_PATH . '/css/bootstrapcustom.min.css');
    wp_enqueue_style('bootstrapmin', CR_PLUGIN_PATH . '/css/bootstrapmin.css');
    wp_enqueue_script('bootstrapminjs', CR_PLUGIN_PATH . '/js/bootstrap1.min.js', array('jquery'));
    wp_enqueue_script('jquery-js', CR_PLUGIN_PATH . '/js/jquery.min.js', array('jquery'));
    wp_enqueue_script('custom-js', CR_PLUGIN_PATH . '/js/custom.js', array('jquery'));
}
#--------------------------------------------------------------------------------
#region front scripts
#--------------------------------------------------------------------------------


function coinremitter_wp_plugin_scripts() {
    // Register the script
    if (is_page('invoice-page') || is_wc_endpoint_url('order-received')) {
        wp_enqueue_style('roboto_font', 'https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap');
        script_common();
        wp_enqueue_script('checkout-js', CR_PLUGIN_PATH . '/js/checkout.js', array('jquery'));
}

if (is_checkout()) {
    if (is_checkout_block()) {
        script_common();
    }   
    script_common();
}

}



