<?php

if(!defined("COINREMITTER_WORDPRESS")) define("COINREMITTER_WORDPRESS", true); 

if (!COINREMITTER_WORDPRESS) require_once( "coinremitter.class.php" ); 
elseif (!defined('ABSPATH')) exit; // Exit if accessed directly in wordpress
$param = $_POST;
if(!$param){
    die('only post method allowed.');
}
if(!defined('COINREMITTER_PRIVATE_KEYS')) die('no direct access allowed.');

$coinremiter_url = COINREMITTER_API_URL;

$coins = json_decode(COINREMITTER_PRIVATE_KEYS,true);

    function check_callback_validation($param){
        if(!isset($param['txid'])){
            return false;
        }elseif(!isset($param['id'])){
            return false;
        }elseif(!isset($param['type'])){
            return false;
        }elseif(!isset($param['coin_short_name'])){
            return false;
        }elseif(!isset($param['address'])){
            return false;
        }elseif(!isset($param['amount'])){
            return false;
        }elseif(!isset($param['confirmations'])){
            return false;
        }
        
        return true;
    }
    
    function exec_url_for_callback($url,$post='')
    {
            $header[] = "Accept: application/json";

            $body = $post;
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
            $ResultArr = json_decode($response,true);
            //print_r($ResultArr);
            return $ResultArr;
            //return $arr;
    }
    
    if(!check_callback_validation($param)){
        die('something might wrong.');
    }
    
    $callback_coin = strtoupper($param['coin_short_name']);
    
    if(!isset($coins[$callback_coin])){
        die('invalid callback coin.');
    }
    
    $trx_url = $coinremiter_url.$callback_coin.'/get-transaction';
    $trx_param = $coins[$callback_coin];
    $trx_param['id'] = $param['id'];
    
    $check_transaction = exec_url_for_callback($trx_url,$trx_param);
    if($check_transaction['flag'] != 1){
        exit('something might wrong while cross checking.');
    }
    $check_transaction = $check_transaction['data'];
    
    $check_transaction = $param;
    if($check_transaction['type'] != 'receive'){
        die('invalid callback type.');
    }
    
    $address = $check_transaction['address'];
    $amount = $check_transaction['amount'];
    $trxid = $check_transaction['txid'];
    $id = $check_transaction['id'];
    $confirms = $check_transaction['confirmations'];
    
    $addrSql = 'select * from coinremitter_order_address where addr="'.$address.'" && coinLabel="'.$callback_coin.'"';
    
    $getData = run_sql_coinremitter($addrSql);
    if(!$getData){
        exit('no order found with given data.');
    }
    
    //delete extra records from coinremitter_order_address for other coins except received coin callback
    
    $deleteAddress = run_sql_coinremitter('delete from coinremitter_order_address where orderID="'.$getData->orderID.'" and coinLabel != "'.$callback_coin.'"');
    
    //process callback
    
    $dt			= gmdate('Y-m-d H:i:s');
    $obj 		= run_sql_coinremitter("select paymentID, txConfirmed from coinremitter_payments where orderID = '".$getData->orderID."' && userID = '".$getData->userID."' && txID = '".$trxid."' && amount = ".intval($_POST["amount"])." && addr = '".$address."' limit 1");


    $paymentID		= ($obj) ? $obj->paymentID : 0;
    $txConfirmed	= ($obj) ? $obj->txConfirmed : 0;
    
    $amountUSD=$getData->amountUSD;
    $unrecognised = $amount != $getData->amount ? 1 : 0;
    if (!$paymentID){
            
            
            if($unrecognised){
//                $amountUSD = coinremitter_convert_currency($getData->coinLabel, 'USD', $amount);
            }
            $sql = "INSERT INTO coinremitter_payments ( orderID, userID, coinLabel, amount, amountUSD, unrecognised, addr, txID, crID, txDate, txConfirmed, txCheckDate, createdAt)
                            VALUES ( '".$getData->orderID."', '".$getData->userID."', '".$getData->coinLabel."', ".$amount.", ".$amountUSD.", ".$unrecognised.", '".$address."', '".$trxid."', '".$param['id']."', '".$param['time']."', ".$confirms.", '$dt', '$dt')";

            $paymentID = run_sql_coinremitter($sql);
            
            if(!$unrecognised){
                //update coinremitter_order_address
                $sql = "UPDATE coinremitter_order_address SET payment_status = 1 , paymentDate = '".$param['time']."' where addrID = $getData->addrID LIMIT 1";
                run_sql_coinremitter($sql);
            }
            
    }elseif ($confirms && !$txConfirmed){
            $sql = "UPDATE coinremitter_payments SET txConfirmed = 1, txCheckDate = '$dt' WHERE paymentID = $paymentID LIMIT 1";
            run_sql_coinremitter($sql);
            $txConfirmed = 1;
    }else {
            die('order already paid.');
    }
    
    if(!$unrecognised){
        if (strpos($getData->userID, "user_") === 0) 	$user_id  = substr($getData->userID, 5);
        elseif (strpos($getData->userID, "user") === 0) $user_id  = substr($getData->userID, 4);
        else $user_id = $getData->userID;

        $order_id 		= mb_substr($getData->orderID, mb_strpos($getData->orderID, ".") + 1);

        $process_data = [
            'paymentTimestamp'=>strtotime($param['time']),
            'coinlabel'=>$callback_coin,
            'amount'=>$amount,
            'amountusd'=>$amountUSD,
            'paymentID'=>$paymentID,
            'is_confirmed'=>$txConfirmed,
            'tx'=>$trxid,
            'addr'=>$address,
        ];
        
        coinremitterwoocommerce_callback ($user_id, $order_id, $process_data);
    }
    
    
    exit('transaction processed successfully.');