<?php
if (!defined('ABSPATH')) {
    exit;
}

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

if (!isset($_POST['type']) || sanitize_text_field($_POST['type']) !== "receive") {
    exit('Invalid webhook type');
}

error_log("webhook recieve" . json_encode($_POST));
global $wpdb;
$addrSql = $wpdb->prepare(
    'SELECT * FROM ' . $wpdb->prefix . 'coinremitter_orders WHERE payment_address = %s AND coin_symbol = %s',
    $address,
    $coin
);
$getDatas = $wpdb->get_results($addrSql);

if (empty($getDatas)) {
    exit('no order found with given data.');
}
$getData = $getDatas[0];

// $order_id =  str_replace("coinremitterwc.order", "", $getData->order_id);
$order_id =  $getData->order_id;
$order = wc_get_order($order_id);
if (!$order || !is_a($order, 'WC_Order')) {
    exit('Invalid order.');
}
$wcOrderStatus = $order->get_status();



$callback_coin = strtoupper($coin);

$tablename = $wpdb->prefix . 'coinremitter_wallets';
$wallet = $wpdb->get_row($wpdb->prepare("SELECT  wallet_name, coin_symbol, minimum_invoice_amount, api_key, password FROM $tablename WHERE coin_symbol = %s", $coin));
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


if (!$check_transaction || empty($check_transaction['success'])) {
    exit('Transaction not found for this address ' . $address);
}
$addrTransaction = $check_transaction['data'];
if ($addrTransaction['type'] != 'receive') {
    exit('Transaction not found for this address ' . $address);
}

// [SECURITY FIX 1] Verify the API address matches the order payment address in database
if (!empty($addrTransaction['address']) && !empty($getData->payment_address)) {
    if (strtolower(trim($addrTransaction['address'])) !== strtolower(trim($getData->payment_address))) {
        error_log('CoinRemitter Webhook: Address mismatch. API: ' . $addrTransaction['address'] . ' vs DB: ' . $getData->payment_address);
        exit('Transaction address mismatch.');
    }
}

// [SECURITY FIX 2] Verify the coin received matches the order coin symbol
if (!empty($addrTransaction['coin_symbol']) && !empty($getData->coin_symbol)) {
    if (strtoupper(trim($addrTransaction['coin_symbol'])) !== strtoupper(trim($getData->coin_symbol))) {
        error_log('CoinRemitter Webhook: Coin symbol mismatch. API: ' . $addrTransaction['coin_symbol'] . ' vs DB: ' . $getData->coin_symbol);
        exit('Coin symbol mismatch.');
    }
}

$verified_crypto_amount = floatval($addrTransaction['amount'] ?? 0);
if ($verified_crypto_amount <= 0) {
    exit('Invalid transaction amount from API.');
}

$order_crypto_req = floatval($getData->crypto_amount);
$order_fiat_req   = floatval($getData->fiat_amount);
if ($order_crypto_req <= 0) {
    exit('Invalid order crypto requirement.');
}
$fiat_amount = ($order_crypto_req > 0) ? ($addrTransaction['amount'] * $order_fiat_req) / $order_crypto_req : 0;
$fiat_amount = number_format($fiat_amount, 2, '.', '');
$minFiatAmount = isset($wallet->minimum_invoice_amount) ? floatval($wallet->minimum_invoice_amount) : 0;

if ($fiat_amount < $minFiatAmount) {
    error_log('Order amount is less than minimum amount : '.$fiat_amount);
    exit('Order amount is less than minimum amount. Required fiat amount : '.$minFiatAmount);
}
$required_confirmations = isset($addrTransaction['required_confirmations']) ? intval($addrTransaction['required_confirmations']) : 3;
$store_currency = !empty($getData->fiat_symbol) ? $getData->fiat_symbol : get_woocommerce_currency();

// [SECURITY FIX 3] Read confirmations directly from verified CoinRemitter API, never trust $_POST
$confirmations_int = isset($addrTransaction['confirmations']) ? intval($addrTransaction['confirmations']) : 0;
$new_status = ($confirmations_int >= $required_confirmations) ? 1 : 0;
$verified_txid = sanitize_text_field($addrTransaction['txid']);

$metaTrx = [
    'payment_address' => $getData->payment_address,
    'explorer_url'   => $addrTransaction['explorer_url'] ?? '',
    'crypto_amount'  => number_format($verified_crypto_amount, 8, '.', ''),
    'coin_symbol'    => $coin_symbol,
    'confirmations'   => $confirmations_int,
    'coin_name'      => $coin,
    'fiat_symbol'    => $store_currency,
    'trx_id'         => $verified_txid,
    'status'         => $new_status,
    'created_at'     => $addrTransaction['date'] ?? current_time('mysql'),
    'updated_at'     => current_time('mysql'),
];

$sql = "SELECT * FROM " . $wpdb->prefix . "coinremitter_transactions WHERE order_id = %s";
$existing_order = $wpdb->get_results($wpdb->prepare($sql, $order_id));
$has_existing_order = (is_array($existing_order) && count($existing_order) > 0);
$trxMeta = array();
if ($has_existing_order) {
    $decoded = json_decode($existing_order[0]->meta, true);
    if (is_array($decoded)) {
        $trxMeta = $decoded;
    }
}

$trx_found = false;
foreach ($trxMeta as &$data_up) {
    if ($data_up['trx_id'] == $verified_txid) {
        $trx_found = true;
        $data_up['confirmations'] = $confirmations_int;
        $data_up['status'] = ($confirmations_int >= $required_confirmations) ? 1 : 0;
        $data_up['crypto_amount'] = number_format($verified_crypto_amount, 8, '.', '');
        $data_up['updated_at'] = current_time('mysql');
        break;
    }
}
unset($data_up);

if (!$trx_found) {
    $trxMeta[] = $metaTrx;
}

// Calculate total confirmed crypto safely across all transactions
$total_confirmed_crypto = 0;
foreach ($trxMeta as $item) {
    if (isset($item['status']) && $item['status'] == 1) {
        $total_confirmed_crypto += floatval($item['crypto_amount']);
    }
}

$total_paid_fiat = ($order_crypto_req > 0) ? ($total_confirmed_crypto * $order_fiat_req) / $order_crypto_req : 0;

$option_data = get_option('woocommerce_coinremitterpayments_settings');
$ostatus = isset($option_data['ostatus']) ? $option_data['ostatus'] : 'completed';

$diff = round($total_confirmed_crypto - $order_crypto_req, 8);
if ($diff >= 0) {
    if ($diff > 0) {
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
    if (round(floatval($getData->paid_crypto_amount), 8) !== round($total_confirmed_crypto, 8)) {
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
        array('meta' => json_encode($trxMeta)),
        array('order_id' => $order_id)
    );
} else {
    $wpdb->insert(
        $wpdb->prefix . 'coinremitter_transactions',
        array(
            'order_id' => $order_id,
            'meta'     => json_encode($trxMeta),
        )
    );
}
error_log("CoinRemitter Webhook processed successfully for order: " . $order_id . " Trx: " . $webhook_trxid);
exit('Webhook processed successfully');
