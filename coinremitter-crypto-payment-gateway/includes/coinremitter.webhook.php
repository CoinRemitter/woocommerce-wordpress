<?php

if(!defined("COINREMITTER_WORDPRESS")) define("COINREMITTER_WORDPRESS", true); 

if (!COINREMITTER_WORDPRESS) require_once( "coinremitter.class.php" ); 
elseif (!defined('ABSPATH')) exit; // Exit if accessed directly in wordpress

if($_SERVER['REQUEST_METHOD'] != 'POST'){
    die('only post method allowed.');
}

if(!(isset($_POST['address']) && isset($_POST['coin_short_name']) && isset($_POST['amount']) && isset($_POST['id']))){
    exit('Invalid Post data.');
}

$param['address'] = sanitize_text_field($_POST['address']);
// $param['address'] = 'dfsfsdf';
$param['coin'] = sanitize_text_field($_POST['coin_short_name']);
$param['amount'] = sanitize_text_field($_POST['amount']);
$param['id'] = sanitize_text_field($_POST['id']);

error_log(print_r($param,true));

$coinremiter_url = COINREMITTER_URL.'api/'.COINREMITTER_API_VERSION.'/';
function coinremitter_check_callback_validation($param){
    if(!isset($param['address'])){
        return false;
    }elseif(!isset($param['coin'])){
        return false;
    }
    return true;
}
if(!coinremitter_check_callback_validation($param)){
    die('something might wrong.');
}

$addrSql = 'select * from coinremitter_order_address where addr="'.$param['address'].'" && coinLabel ="'.$param['coin'].'"';
error_log($addrSql);

$getData = run_sql_coinremitter($addrSql);

if(!$getData){
    exit('no order found with given data.');
}else
{ 
    $sql= "SELECT * FROM `coinremitter_payments` WHERE  `orderID` = '$getData->orderID'";
    $payment_data= run_sql_coinremitter($sql);
	// $o_status = $order_detail->get_status();
    
    $expiry_date=$payment_data->expiry_date;
    
    $order_id =  str_replace("coinremitterwc.order", "",$getData->orderID);
    $order = wc_get_order($order_id);
    $wcOrderStatus = $order->get_status();

    $webhook_data = "SELECT * FROM coinremitter_webhook WHERE `addr` = '".$param['address']."' ";
    $web_hook = (array) run_sql_coinremitter($webhook_data);

    if($expiry_date != "" ){
        $diff=strtotime($expiry_date)- strtotime(gmdate('Y-m-d H:i:s'));
    }
    
    if(count($web_hook) == 0  && isset($diff) && $diff <= 0 ){
        error_log('inside if');
        if($wcOrderStatus == 'pending'){
            $order->update_status('cancelled');
        }
        $order_status_code=COINREMITTER_INV_EXPIRED;
        $order_status='Expired';
        
        $u_order_data="UPDATE `coinremitter_order_address` SET `payment_status` = '$order_status_code' WHERE `addr` = '$address' ";
        $update_order_data = run_sql_coinremitter($u_order_data);

        $payment_data="UPDATE `coinremitter_payments` SET `status` = '$order_status' ,`status_code` = '$order_status_code' WHERE `orderID` = '".$getData->orderID."' ";
        
        $update_payment_data = run_sql_coinremitter($payment_data);
    }
    if($payment_data->status_code == COINREMITTER_INV_PENDING || $payment_data->status_code == COINREMITTER_INV_UNDER_PAID )
    {
        $callback_coin = strtoupper($param['coin']);
        $trx_url = $trx_url = $coinremiter_url.$callback_coin.'/get-transaction';
        $trx_param['api_key'] = get_option( COINREMITTER.$callback_coin.'api_key' );
        $trx_param['password'] = get_option( COINREMITTER.$callback_coin.'password' );
        $trx_param['id'] = $param['id'];
        
        $header[] = "Accept: application/json";
        $curl = $trx_url;
        $body = array(
            'api_key' => $trx_param['api_key'], 
            'password'=>coinremitter_decrypt($trx_param['password']),
            'id'=> $trx_param['id'],
        );  
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
        if($check_transaction['flag'] == 1)
        {
            if($check_transaction['data']['type'] == 'receive'){
                    // write_log('transaction call');
                $date=gmdate('Y-m-d H:i:s');
                $id=$check_transaction['data']['id'];
                $address=$check_transaction['data']['address'];
                $coin=$check_transaction['data']['coin_short_name'];
                $txid=$check_transaction['data']['txid'];
                $amount=$check_transaction['data']['amount'];
                $confirmations=$check_transaction['data']['confirmations'];
                $explorer_url=$check_transaction['data']['explorer_url'];
                
                $sql="SELECT * from coinremitter_webhook WHERE transaction_id='".$id."'";                
                $web_hook=(array)run_sql_coinremitter($sql);


                if($confirmations < 3){
                    $confirmations_order=$confirmations;
                }else{
                    $confirmations_order=3;
                }

                if(count($web_hook) > 0)
                {
                    $sql = "UPDATE `coinremitter_webhook` SET `order_id` = '$getData->orderID',`addr` ='$address', `tx_id` = '$txid',`explorer_url` ='$explorer_url',`paid_amount`='$amount',`coin`= '$coin' ,`confirmation`='$confirmations_order' ,`paid_date`='$date',`updated_date`='$date' WHERE `transaction_id`= '".$id."'";

                }else{
                    
                    $sql = "INSERT INTO coinremitter_webhook ( order_id, transaction_id,addr, tx_id,explorer_url,paid_amount,coin,confirmation,paid_date,created_date,updated_date)
                    VALUES ('".$getData->orderID."', '".$id."', '".$address."', '".$txid."','".$explorer_url."','".$amount."', '".$coin."','".$confirmations_order."','".$date."','".$date."','".$date."')";
                }
                
                $web_hook=run_sql_coinremitter($sql);
                $payment_query = "SELECT  * FROM `coinremitter_webhook` WHERE  `addr` = '".$address."' AND `confirmation` >= 3 " ;
                
                $webhook_data_amount = run_sql_coinremitter($payment_query);
                $total_paid=0;

                if($webhook_data_amount){
                    if(count((array)$webhook_data_amount) == 1){
                        $total_paid = $webhook_data_amount[0]->paid_amount;
                    }else{
                        foreach ($webhook_data_amount as $web) {
                            $total_paid = $total_paid + $web->paid_amount;
                        }
                    }        
                }
                

                $total_paidamount= $total_paid;
                $total_amount=$getData->amountUSD;

                
                    //expired check
                $sql= "SELECT * FROM `coinremitter_payments` WHERE  `orderID` = '$getData->orderID'";
                $payment_data= run_sql_coinremitter($sql);

                
                
                $order_status="";
                $order_status_code="";

                $option_data = get_option('woocommerce_coinremitterpayments_settings');
                $ostatus = $option_data['ostatus']; 
                if($total_paidamount == 0){
                    if($wcOrderStatus == 'pending'){
                        $order->update_status('pending');
                    }
                    $order_status="Pending";
                    $order_status_code=COINREMITTER_INV_PENDING;
                }else if($total_amount > $total_paidamount ){
                    if($wcOrderStatus == 'pending'){
                        $order->update_status('pending');
                    }
                    $order_status_code=COINREMITTER_INV_UNDER_PAID;
                }else if($total_amount < $total_paidamount){
                    if($wcOrderStatus == 'pending'){
                        $order->update_status('wc-'.$ostatus);
                    }
                    $order_status="Over paid";
                    $order_status_code=COINREMITTER_INV_OVER_PAID;
                }else if($total_amount == $total_paidamount){
                    if($wcOrderStatus == 'pending'){
                        $order->update_status('wc-'.$ostatus);
                    }
                    $order_status="Paid";
                    $order_status_code=COINREMITTER_INV_PAID;
                }
                
                $u_order_data="UPDATE `coinremitter_order_address` SET `payment_status` = '$order_status_code' WHERE `addr` = '$address' ";
                $update_order_data = run_sql_coinremitter($u_order_data);

                $payment_data="UPDATE `coinremitter_payments` SET `status` = '$order_status' ,`paid_amount` = '".$total_paidamount."',`status_code` = '$order_status_code' WHERE `orderID` = '".$getData->orderID."' ";
                
                $update_payment_data = run_sql_coinremitter($payment_data);
                

                exit('transaction processed successfully.');     
            }
        }
    }
    else
    {
        exit('payment not pay.');
    }
}

