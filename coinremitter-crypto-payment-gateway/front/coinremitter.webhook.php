```
<?php

if (!defined("COINREMITTER_WORDPRESS")) {
    define("COINREMITTER_WORDPRESS", true);
}


$address = sanitize_text_field($_POST['address'] ?? '');
$coin = sanitize_text_field($_POST['coin_symbol'] ?? '');
$amount = sanitize_text_field($_POST['amount'] ?? '');
$id = sanitize_text_field($_POST['id'] ?? '');
$webhook_trxid = sanitize_text_field($_POST['txid'] ?? '');
$explorer_url = sanitize_text_field($_POST['explorer_url'] ?? '');
$confirmations = sanitize_text_field($_POST['confirmations'] ?? '');
$date = sanitize_text_field($_POST['date'] ?? '');



if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    die('only post method allowed.');
}

if (isset($_POST['type']) != "receive") {
    exit('Invalid webhook type');
}

error_log("webhook recieve" . json_encode($_POST));
global $wpdb;
$addrSql = 'select * from wp_coinremitter_orders where payment_address="' . $address . '" && coin_symbol ="' . $coin . '"';
$getDatas = $wpdb->get_results($addrSql);
$getData = $getDatas[0];
// print_r($getDatas);

if (!$getData) {
    exit('no order found with given data.');
}

// $order_id =  str_replace("coinremitterwc.order", "", $getData->order_id);
$order_id =  $getData->order_id;
$order = wc_get_order($order_id);
$wcOrderStatus = $order->get_status();

$option_data = get_option('woocommerce_coinremitterpayments_settings');
if ($option_data['invoice_expiry'] == 0 || $option_data['invoice_expiry'] == '') {
    $invoice_expiry = '';
} else {
    $invoice_expiry = $option_data['invoice_expiry'];
}

$expiry_date = null;

if ($invoice_expiry != "") {
    $diff = date("Y/m/d H:i:s", strtotime("+" . $invoice_expiry . " minutes"));
}

// if (count($getData) == 0  && isset($diff) && $diff <= 0) {
if ((is_array($getData) || $getData instanceof Countable) && count($getData) == 0 && isset($diff) && $diff <= 0) {

    error_log('inside if');
    if ($wcOrderStatus == 'pending') {
        $order->update_status('cancelled');
    }
    $order_status_code = COINREMITTER_INV_EXPIRED;
    $order_status = 'Expired';

    $payment_data = "UPDATE `wp_coinremitter_transactions` SET `status` = '$order_status_code' WHERE `order_id` = '" . $order_id . "' ";
    $update_payment_data = $wpdb->get_results($payment_data);
}



$callback_coin = strtoupper($coin);

$tablename = $wpdb->prefix . 'coinremitter_wallets';
$wallet = $wpdb->get_row($wpdb->prepare("SELECT  wallet_name, coin_symbol, api_key, password FROM $tablename WHERE coin_symbol = %s", $coin));
if (is_null($wallet)) {
    exit('Coin wallet not found in db');
}
$wallet_name = $wallet->wallet_name;
$coin_symbol = $wallet->coin_symbol;
$CurrAPIKey = decrypt_data($wallet->api_key);
$CurrPassword = decrypt_data($wallet->password);


/**
 * Find transaction by address from address
 */

 
$check_transaction = createTransaction($id, $CurrAPIKey, $CurrPassword); //api call


if (!$check_transaction['success']) {
    exit('Transaction not found for this address ' . $address);
}
$addrTransaction = $check_transaction['data'];
if ($addrTransaction['type'] != 'receive') {
    exit('Transaction not found for this address ' . $address);
}

$metaTrx = [
    'payment_address' => $address,
    'explorer_url'   => $addrTransaction['explorer_url'],
    'crypto_amount'  => $addrTransaction['amount'],
    'coin_symbol'    => $coin_symbol,
    'confirmations'   => $addrTransaction['confirmations'],
    'coin_name'      => $coin,
    'fiat_symbol'    => 'USD',
    'trx_id'         => $addrTransaction['txid'],
    'status'         => 0,
    'created_at'     => $addrTransaction['date'],
    'updated_at'     => $addrTransaction['date'],
];

$sql = "SELECT * FROM wp_coinremitter_transactions WHERE order_id = %s";
$existing_order = $wpdb->get_results($wpdb->prepare($sql, $order_id));


$trxStatus = 1;
$userID = get_current_user_id();
if (count($existing_order) <= 0) {
    error_log('Order data not found in transaction table.Make an entry in transaction table.');
    error_log("Meta_trx : " . json_encode($metaTrx));
    $metaTrxs[] = $metaTrx;
    $meta_json = json_encode($metaTrxs);
    error_log("meta_json : " . json_encode($meta_json));
    $data = [
        'order_id'       => $order_id,
        'meta'         => $meta_json,
    ];
    $wpdb->insert('wp_coinremitter_transactions', $data);
    exit('Order Entry added intransaction table.');
}
$existing_order = $existing_order[0];
$trxMeta = json_decode($existing_order->meta, true);
if ($trxMeta) {

    $orderAmount = 'select * from wp_coinremitter_orders where payment_address="' . $address . '" && coin_symbol ="' . $coin . '"';
    $orderRow = $wpdb->get_results($orderAmount);
    $order_result = $orderRow[0];
    error_log(json_encode($order_result));

    $order_status_order =  $order_result->order_status;
    $paidAmount =  $order_result->paid_crypto_amount;
    $paidFiatAmount =  $order_result->paid_fiat_amount;

    $fiat_amount = 0.05;       // Amount in fiat (USD)
    $fiat_currency = 'USD';    // Fiat currency
    $result_arr = fiat_cryoto($fiat_amount, $fiat_currency, $coin); //api call

    $fiat_price = $result_arr['data'][0]['price'];
    error_log("fiat_rate : " . $fiat_price);

    $trxArray = array_column($trxMeta, 'trx_id');
    foreach ($trxMeta as &$data_up) {
        if (!in_array($webhook_trxid, $trxArray)) {
            error_log("Transaction not found in metaTrx trx_id : " . $webhook_trxid);
            array_push($trxMeta, $metaTrx);
            break;
        } else {
            error_log("Transaction found in meta data. Trx_id : " . $webhook_trxid);
            $data_up['confirmations'] = $confirmations;
            // update order data
            error_log("Trx id to be processed : " . $data_up['trx_id'] . " status : " . $data_up['status']);
            if ($data_up['status'] == 0 && $data_up['trx_id'] == $webhook_trxid) {
                error_log("Change status of transaction : " . $webhook_trxid);
                error_log("Previous paid amount : " . " trx id : " . $webhook_trxid . " amount : " . $paidAmount);
                error_log("Webhook amount : " . " trx id : " . $webhook_trxid . " amount : " . $amount);
                error_log("Transaction id processing : " . $data_up['trx_id']);

                $paidAmount +=  $amount;
                error_log("New paid amount : " . $paidAmount . " Trx_id : " . $webhook_trxid);
                $walletTotalAmount =  ($amount * $paidFiatAmount) / $paidAmount;
                $paidFiatAmount += $walletTotalAmount;
                $paidFiatAmtWithTrunc = $paidAmount + $fiat_price;

                error_log("trucationVal : " . $paidFiatAmtWithTrunc . " walletTotalAmount: " . $walletTotalAmount . "");

                $crypto_amount = $order_result->crypto_amount;
                $fiat_amount = $order_result->fiat_amount;
                $status = 0;
                if ($paidAmount >= $order_result->paid_fiat_amount) {
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
                        error_log("webhook Status change over paid");
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
                if ($crypto_amount <= $paidFiatAmtWithTrunc &&  $order_status_code == COINREMITTER_INV_UNDER_PAID) {
                    if ($wcOrderStatus == 'processing' || $wcOrderStatus == 'pending') {
                        $order->update_status('completed');
                        error_log("trucation webhook Status change paid");
                        $order->update_status('wc-' . $ostatus);
                    }
                    $order_status = "Paid";
                    $order_status_code = COINREMITTER_INV_PAID; //3 

                }

                error_log('Change order data : ' . " trx id : " . $webhook_trxid . " order id : " . $order_id . ' : ' . $paidAmount . ' : ' . $paidFiatAmount . ' : ' . $order_status_code);

                error_log("Status change of trx_id : " . $data_up['trx_id']);
                $updateOrder = "UPDATE `wp_coinremitter_orders` SET `paid_crypto_amount`='$paidAmount', `paid_fiat_amount`='$paidFiatAmount', `order_status`='$order_status_code' WHERE `order_id`='$order_id'";
                $data_up['status'] = 1;
                $wpdb->get_results($updateOrder);
            }
        }
    }
    $data = [
        'meta' => json_encode($trxMeta),
    ];
    $where = [
        'order_id' => $order_id,
    ];
    error_log(message: "Order transaction meta update successfully. for trx Id : " . $webhook_trxid);
    error_log(json_encode($data));
    $wpdb->update('wp_coinremitter_transactions', $data, $where);
}
