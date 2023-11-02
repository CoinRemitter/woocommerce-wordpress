<?php

if ( ! defined( 'COINREMITTER_WORDPRESS')) {
    define( 'COINREMITTER_WORDPRESS', true);
}

if (!COINREMITTER_WORDPRESS) {
    require_once("coinremitter.class.php");
} elseif ( ! defined('ABSPATH')) {
exit; // Exit if accessed directly in wordpress
}
$param['invoice_id'] = sanitize_text_field($_POST['invoice_id']);
$param['coin'] = sanitize_text_field($_POST['coin']);

if(!$param){
    die('only post method allowed.');
}

$coinremiter_url = COINREMITTER_URL.'api/'.COINREMITTER_API_VERSION.'/';
function coinremitter_check_callback_validation($param){
    if(!isset($param['invoice_id'])){
        return false;
    }elseif(!isset($param['coin'])){
        return false;
    }
    return true;
}

if(!coinremitter_check_callback_validation($param)){
    die('something might wrong.');
}

$callback_coin = strtoupper($param['coin']);
$trx_url = $trx_url = $coinremiter_url.$callback_coin.'/get-invoice';
$trx_param['api_key'] = get_option( COINREMITTER.$callback_coin.'api_key' );
$trx_param['password'] = get_option( COINREMITTER.$callback_coin.'password' );
$trx_param['invoice_id'] = $param['invoice_id'];

$header[] = "Accept: application/json";
$curl = $trx_url;
$body = array(
    'api_key' => $trx_param['api_key'], 
    'password'=>decrypt($trx_param['password']),
    'invoice_id'=> $trx_param['invoice_id'],
);  
    // print_r($body);
$userAgent = 'CR@'.COINREMITTER_API_VERSION.',wordpress worwoocommerce-wordpress-master@'.COINREMITTER_VERSION;
$args = array(
    'method'      => 'POST',
    'timeout'     => 45,
    'sslverify'   => false,
    'user-agent'  => $userAgent,
    'headers'     => array(
        'Content-Type'  => 'application/json',
    ),
    'body'        => wp_json_encode($body),
);
$request = wp_remote_post( $curl, $args );

if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
    error_log( print_r( $request, true ) );
}

$response = wp_remote_retrieve_body( $request );

$check_transaction = json_decode($response,true);
if($check_transaction['flag'] != 1){
    exit('something might wrong while cross checking.');
}
$check_transaction = $check_transaction['data'];
$coin = $check_transaction['coin'];
$address = $check_transaction['address'];
$base_currency =$check_transaction['base_currency'];
$payment_history = wp_json_encode($check_transaction['payment_history']);
$conversion_rate =wp_json_encode($check_transaction['conversion_rate']);
$paid_amount = wp_json_encode($check_transaction['paid_amount']);
$total_amount = wp_json_encode($check_transaction['total_amount']);
$invoice_id = $check_transaction['invoice_id'];
$desc = $check_transaction['description'];
$status = $check_transaction['status'];
$status_code = $check_transaction['status_code'];
$url = $check_transaction['url'];
$dt = gmdate('Y-m-d H:i:s');

// $CoinPrice = coinremitter_getActCoins();
// foreach($CoinPrice as $k => $v){
//     if($coin == $k){
//         $coinprice = $v['price'];
//     }
// }
if($check_transaction['status_code'] == COINREMITTER_INV_OVER_PAID || $check_transaction['status_code'] == COINREMITTER_INV_PAID){
    $addrSql = 'select * from coinremitter_order_address where addr="'.$address.'" && invoice_id ="'.$invoice_id.'"';
    $getData = run_sql_coinremitter($addrSql);
    $order_status = $getData->payment_status;

    if(!$getData){
        exit('no order found with given data.');
    }else{
        
        $pay = 'select * from coinremitter_payments where invoice_id ="'.$invoice_id.'"';
        $getData = run_sql_coinremitter($pay);
        $amount = $total_amount;
        $dt  = gmdate('Y-m-d H:i:s');
        if(count($getData) > 0){
            $sql = "UPDATE `coinremitter_payments` SET `status` = '$status',`total_amount` ='$total_amount', `paid_amount` = '$paid_amount', `payment_history` = '$payment_history',`conversion_rate` ='$conversion_rate' WHERE `coinremitter_payments`.`invoice_id` = '".$invoice_id."'";
            
        }else{
           $sql = "INSERT INTO coinremitter_payments ( orderID, userID, coinLabel,base_currency,payment_history,total_amount,invoice_id, paid_amount, conversion_rate, description, status,invoice_url,status_code, txCheckDate, createdAt)
           VALUES ('".$getData->orderID."', '".$getData->userID."', '".$coin."','".$base_currency."','".$payment_history."', '".$total_amount."', '".$invoice_id."', '".$paid_amount."','".$conversion_rate."', '".$desc."', '".$status."', '".$url."', '".$status_code."', '$dt', '$dt')";     
       }
       $paymentID = run_sql_coinremitter($sql);
       $sql = "UPDATE coinremitter_order_address SET payment_status = '1' , paymentDate = '".$dt."' where addrID = '$getData->addrID' LIMIT 1";
       run_sql_coinremitter($sql);
       if (strpos($getData->userID, "user_") === 0)    $user_id  = substr($getData->userID, 5);
       elseif (strpos($getData->userID, "user") === 0) $user_id  = substr($getData->userID, 4);
       else $user_id = $getData->userID;

       $order_id       = mb_substr($getData->orderID, mb_strpos($getData->orderID, ".") + 1);
       $order_id =  str_replace("order","",$order_id);
       $option_data = get_option('woocommerce_coinremitterpayments_settings');
       
       $ostatus = $option_data['ostatus'];
       if($order_status != 1){
        $order_detail =wc_get_order($order_id);
        $o_status = $order_detail->status;
        if($o_status != 'cancelled'){
            $order = new WC_Order($order_id);
            $order->update_status('wc-'.$ostatus);
            add_post_meta( $order_id, '_order_crypto_price', $paid_amount);
            add_post_meta( $order_id, '_order_crypto_coin', $coin);
        }
    }
    $order = wc_get_order($order_id);
    $invoiceurl = $check_transaction['url'];
    $invoice_note ="Invoice  <a href='".$invoiceurl."' target='_blank'>".$check_transaction['invoice_id']."</a> ".$status;
    $order->add_order_note($invoice_note);
    exit('transaction processed successfully.');
}
}else if($check_transaction['status_code'] == COINREMITTER_INV_EXPIRED){
    
    $addrSql = 'select * from coinremitter_order_address where addr="'.$address.'" && invoice_id ="'.$invoice_id.'"';
    $getData = run_sql_coinremitter($addrSql);
    $order_status = $getData->payment_status;
    if(!$getData){
        exit('no order found with given data.');
    }else{
        $sql = "UPDATE `coinremitter_payments` SET `status` = '$status',`total_amount` ='$total_amount', `paid_amount` = '$paid_amount', `payment_history` = '$payment_history',`conversion_rate` ='$conversion_rate' WHERE `coinremitter_payments`.`invoice_id` = '".$invoice_id."'";
        $paymentID = run_sql_coinremitter($sql);
        
        $ostatus = 'cancelled';
        $order_id = mb_substr($getData->orderID, mb_strpos($getData->orderID, ".") + 1);
        $order_id =  str_replace("order","",$order_id);
        $order = new WC_Order($order_id);
        $order->update_status('wc-'.$ostatus);
        $order = wc_get_order($order_id);
        $invoiceurl = $check_transaction['url'];
        $invoice_note ="Invoice  <a href='".$invoiceurl."' target='_blank'>".$check_transaction['invoice_id']."</a> ".$status;
        $order->add_order_note($invoice_note);
        exit('order canceled.');
    }
}else{
    exit('payment not pay.');
}