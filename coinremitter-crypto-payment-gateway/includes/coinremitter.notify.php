<?php

if(!defined("COINREMITTER_WORDPRESS")) define("COINREMITTER_WORDPRESS", true); 

if (!COINREMITTER_WORDPRESS) require_once( "coinremitter.class.php" ); 
elseif (!defined('ABSPATH')) exit; // Exit if accessed directly in wordpress
$param['invoice_id'] = sanitize_text_field($_POST['invoice_id']);
$param['coin'] = sanitize_text_field($_POST['coin']);

if(!$param){
    die('only post method allowed.');
}
if(!defined('COINREMITTER_PRIVATE_KEYS')) die('no direct access allowed.');

$coinremiter_url = COINREMITTER_API_URL;

$coins = json_decode(COINREMITTER_PRIVATE_KEYS,true);
    function check_callback_validation($param){
        if(!isset($param['invoice_id'])){
            return false;
        }elseif(!isset($param['coin'])){
            return false;
        }
        return true;
    }

    if(!check_callback_validation($param)){
        die('something might wrong.');
    }
    
    $callback_coin = strtoupper($param['coin']);
    
    if(!isset($coins[$callback_coin])){
        die('invalid callback coin.');
    }
   
 
    $trx_url = $trx_url = $coinremiter_url.$callback_coin.'/get-invoice';
    $trx_param = $coins[$callback_coin];
   
    $trx_param['invoice_id'] = $param['invoice_id'];
   
    $header[] = "Accept: application/json";
    $curl = $trx_url;
    $body = array(
        'api_key' => $trx_param['api_key'], 
        'password'=>$trx_param['password'],
        'invoice_id'=> $trx_param['invoice_id'],
    );  
    $args = array(
        'method'      => 'POST',
        'timeout'     => 45,
        'sslverify'   => false,
        'headers'     => array(
            'Content-Type'  => 'application/json',
        ),
        'body'        => json_encode($body),
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
    $amount = $check_transaction['amount'];
    $paid_amount = $check_transaction['paid_amount'][$coin];
    $total_amount = $check_transaction['total_amount'][$coin];
    $invoice_id = $check_transaction['invoice_id'];
    $t_id = [];
    $CoinPrice = getActCoins();
    foreach($CoinPrice as $k => $v){
        if($coin == $k){
            $coinprice = $v['price'];
        }
    }
    if($check_transaction['status_code'] == INV_OVER_PAID || $check_transaction['status_code'] == INV_PAID){
        $addrSql = 'select * from coinremitter_order_address where addr="'.$address.'" && invoice_id ="'.$invoice_id.'"';
        $getData = run_sql_coinremitter($addrSql);
        $order_status = $getData->payment_status;

        if(!$getData){
            exit('no order found with given data.');
        }else if($order_status == 1){
            exit('order is paid');
        }else{
        
            $amount = $total_amount;
            $dt  = gmdate('Y-m-d H:i:s');
            
            foreach ($check_transaction['payment_history'] as $key => $value) {
                $amount_in_coin = $value['amount'];
                $amount = $amount_in_coin * $coinprice;
                $amountUSD = number_format((float)$amount, 2, '.', ''); 


                $sql = "INSERT INTO coinremitter_payments ( orderID, userID, coinLabel,explorer_url, amount, amountUSD, addr, txID, crID, txDate, txCheckDate, createdAt)
                                VALUES ( '".$getData->orderID."', '".$getData->userID."', '".$coin."','".$value['explorer_url']."', ".$amount_in_coin.", ".$amountUSD.",'".$address."', '".$value['txid']."', '".$param['id']."', '$dt', '$dt', '$dt')";
                $paymentID = run_sql_coinremitter($sql);
                
            }
            $sql = "UPDATE coinremitter_order_address SET payment_status = 1 , paymentDate = '".$dt."' where addrID = $getData->addrID LIMIT 1";
            run_sql_coinremitter($sql);
            if (strpos($getData->userID, "user_") === 0)    $user_id  = substr($getData->userID, 5);
            elseif (strpos($getData->userID, "user") === 0) $user_id  = substr($getData->userID, 4);
            else $user_id = $getData->userID;

            $order_id       = mb_substr($getData->orderID, mb_strpos($getData->orderID, ".") + 1);
            $order_id =  str_replace("order","",$order_id);
            $option_data = get_option('woocommerce_coinremitterpayments_settings');
            
            $ostatus = $option_data['ostatus'];

            $order = new WC_Order($order_id);
            $order->update_status('wc-'.$ostatus);
            add_post_meta( $order_id, '_order_crypto_price', $paid_amount);
            add_post_meta( $order_id, '_order_crypto_coin', $coin);
            $order = wc_get_order($order_id);
            $invoiceurl = $check_transaction['url'];
            $invoice_note ="Invoice  <a href='".$invoiceurl."' target='_blank'>".$check_transaction['invoice_id']."</a>  paid";

            $order->add_order_note($invoice_note);
            exit('transaction processed successfully.');
        }
    }else{
        exit('payment not pay.');
    }
