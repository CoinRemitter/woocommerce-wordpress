<?php

#--------------------------------------------------------------------------------
#region coinremitter wallet add
#--------------------------------------------------------------------------------

function coinremitter_wp_wallet_add()
{
    error_log('Wallet_add');
    global $wallet_balance;
    $api_key = sanitize_text_field($_POST["wallet_key"]);
    $password = sanitize_text_field($_POST["wallet_password"]);
    $minimum_invoice_amount = sanitize_text_field($_POST["minimum_invoice_amount"]);
    $exchange_rate_multiplier = sanitize_text_field($_POST["exchange_rate_multiplier"]);

    if (
        isset($_POST['wallet_key']) && !empty($_POST['wallet_key'])
        && isset($_POST['wallet_password']) && !empty($_POST['wallet_password'])
        && isset($_POST['minimum_invoice_amount']) && !empty($_POST['minimum_invoice_amount'])
        && isset($_POST['exchange_rate_multiplier']) && !empty($_POST['exchange_rate_multiplier'])
    ) {
    $resultArr = balance_request($api_key, $password); // balance api call
    if(!$resultArr['success']){
        $Result['flag'] = 0;
        $Result['msg'] = $resultArr['msg'];
        echo wp_send_json($Result);
        die;
    }

    $wallet_coin = $resultArr['data']?? '';
    $wallet_name = $wallet_coin['wallet_name']?? '';
    $coin_name = $wallet_coin['coin']?? '';
    $coin_symbol = $wallet_coin['coin_symbol']?? '';

    $currancy_type = get_woocommerce_currency();
    $supported_currencies = fetch_supported_currencies();
    
    if (!$supported_currencies) {
        error_log("Failed to fetch supported currencies.");
        return;
    }

    $currency_data = find_currency_data($supported_currencies, $coin_symbol); // api call
    if (!$currency_data) {
        error_log("Currency data not found for coin: $coin_symbol");
        return;
    }
    $minInvoiceValInFiat = calculate_min_invoice_amount($currency_data); // api call
    $unit_fiat_amount = $currency_data['price_in_usd'];

    if ($currancy_type !== 'USD') {
        $fiat_request = crypto_to_fiat($currancy_type, $coin_symbol,1); // api call
        $minInvoiceValInFiat = number_format($fiat_request * $currency_data['minimum_deposit_amount'], 8, '.', '');
        $unit_fiat_amount = $fiat_request;
    }

    if ($minimum_invoice_amount < $minInvoiceValInFiat) {
        $Result['msg'] = 'The minimum order value must be atleast <span>' . $minInvoiceValInFiat . ' ' . $currancy_type . '.</span>';
        $Result['flag'] = 0;
        echo wp_send_json($Result);
        die;
    }

        global $wpdb;
        $tablename = $wpdb->prefix . 'coinremitter_wallets';
        $sql = $wpdb->get_results("SELECT * FROM $tablename ");
        foreach ($sql as $row) {
            if ($row->coin_symbol == $coin_symbol) {
                $Result['flag'] = 0;
                $Result['msg'] = 'Wallet already exists.';
                echo wp_send_json($Result);
                die;
            }
        }
        $data = $resultArr['data'];
        $wallet_balance = $data['balance'];

        $insert_result =  $wpdb->insert(
            $tablename,
            array(
                'wallet_name' => $wallet_name,
                'coin_symbol' => $coin_symbol,
                'coin_name' => $coin_name,
                'api_key' => encrypt_data($api_key),
                'password' => encrypt_data($password),
                'minimum_invoice_amount' => $minimum_invoice_amount,
                'unit_fiat_amount' => $unit_fiat_amount,
                'exchange_rate_multiplier' => $exchange_rate_multiplier,
            )
        );

        if ($insert_result === false) {
            $Result['flag'] = 0;
            $Result['msg'] = 'Failed to insert data into the database.';
            echo wp_send_json($Result);
            die;
        } else {
            $Result['flag'] = 1;
            $Result['msg'] = 'Get balance successfully.';
            echo wp_send_json($Result);
            die;
        }
 
} else {
    $Result['flag'] = 0;
    $Result['msg'] = 'Please fill out all the fields to proceed.';
    echo wp_send_json($Result);
    die;
}
}




#--------------------------------------------------------------------------------
#region coinremitter update data
#--------------------------------------------------------------------------------

function coinremitter_wp_wallet_edit()
{
    error_log('Wallet_update');
    $coinid = sanitize_text_field($_POST["coin_id"]);
    $api_key = sanitize_text_field($_POST["api_key_value"]);
    $password = sanitize_text_field($_POST["password"]);
    $invoice = sanitize_text_field($_POST['invoice']);
    $rate = sanitize_text_field($_POST['rate']);
    if (
        isset($_POST['coin_id']) && !empty($_POST['coin_id'])
        && isset($_POST['api_key_value']) && !empty($_POST['api_key_value'])
        && isset($_POST['password']) && !empty($_POST['password'])
        && isset($_POST['invoice']) && !empty($_POST['invoice'])
        && isset($_POST['rate']) && !empty($_POST['rate'])
    ) {

        $resultArr = balance_request($api_key, $password); // balance api call
        $wallet_coin = $resultArr['data'];
        if(!$resultArr['success']){
            $Result['flag'] = 0;
            $Result['msg'] = $resultArr['msg']; 
            echo wp_send_json($Result);
            die;
        }
        $wallet_name = $wallet_coin['wallet_name'];
        $coin_name = $wallet_coin['coin'];
        $coin_symbol = $wallet_coin['coin_symbol'];

        $currancy_type = get_woocommerce_currency();

        $supported_currencies = fetch_supported_currencies();
        if (!$supported_currencies) {
            error_log("Failed to fetch supported currencies.");
            return;
        }
        $currency_data = find_currency_data($supported_currencies, $coin_symbol); // api call
        if (!$currency_data) {
            error_log("Currency data not found for coin: $coin_symbol");
            return;
        }
        $minInvoiceValInFiat = calculate_min_invoice_amount($currency_data); // api call
        $unit_fiat_amount = $$currency_data['price_in_usd'];

        if ($currancy_type !== 'USD') {
            $fiat_request = crypto_to_fiat($currancy_type, $coin_symbol,1); // api call
            $minInvoiceValInFiat = number_format($fiat_request * $currency_data['minimum_deposit_amount'], 8, '.', '');
            $unit_fiat_amount = $fiat_request;
        }

        if ($invoice < $minInvoiceValInFiat) {
            $Result['msg'] = 'The minimum order value must be atleast <span>' . $minInvoiceValInFiat . ' ' . $currancy_type . '.</span>';
            $Result['flag'] = 0;
            echo wp_send_json($Result);
            die;
        }
            global $wpdb;
            $tablename = $wpdb->prefix . 'coinremitter_wallets';
            $existingWallet = $wpdb->get_row("SELECT * FROM $tablename WHERE id = $coinid");
            if ($existingWallet->coin_symbol !== $coin_symbol) {
                $Result['flag'] = 0;
                $Result['msg'] = 'Wallet already exists.';
                echo wp_send_json($Result);
                die;
            }

            if ($existingWallet->coin_symbol === $coin_symbol) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'coinremitter_wallets';
                $update_result = $wpdb->update(
                    $table_name,
                    array(
                        'wallet_name' => $wallet_name,
                        'coin_symbol' => $coin_symbol,
                        'coin_name' => $coin_name,
                        'api_key' => encrypt_data($api_key),
                        'password' => encrypt_data($password),
                        'minimum_invoice_amount' => $invoice,
                        'unit_fiat_amount' => $unit_fiat_amount,
                        'exchange_rate_multiplier' => $rate,
                    ),
                    array('id' => $coinid),
                    array(
                        '%s',
                        '%s',
                        '%s'
                    ),
                    array('%d')
                );
                if ($update_result === false) {
                    $Result['flag'] = 0;
                    $Result['msg'] = 'Failed to Update data into the database.';
                    echo wp_send_json($Result);
                    die;
                } else {
                    $Result['flag'] = 1;
                    $Result['msg'] = 'Update Wallet successfully.';
                    echo wp_send_json($Result);
                    die;
                }
            }
      
    } else {
        $Result['flag'] = 0;
        $Result['msg'] = 'Error updating data.';
        echo wp_send_json($Result);
        die;
    }
}

#--------------------------------------------------------------------------------
#region coinremitter wallet delete
#--------------------------------------------------------------------------------


function coinremitter_wp_wallet_delete()
{
    error_log('Wallet_delete');
    global $wpdb;
    $id = sanitize_text_field($_POST["id"]);
    $table_name = $wpdb->prefix . 'coinremitter_wallets';
    $result = $wpdb->delete($table_name, array('id' => $id), array('%d'));

    if ($result !== false) {
        wp_send_json_success('Wallet deleted successfully');
    } else {
        wp_send_json_error('Failed to delete wallet');
    }
    wp_die();
}


#--------------------------------------------------------------------------------
#region currency change tracking and updating minimum_invoice_amount
#--------------------------------------------------------------------------------

function track_currency_change($option_name, $old_value, $new_value)
{
    if ($option_name === 'woocommerce_currency' && $old_value !== $new_value) {
        // Currency has changed
        error_log('Currency has changed from ' . $old_value . ' to ' . $new_value);
        update_currency($new_value);
    }
}


// Function to update custom table
function update_currency($new_currency)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'coinremitter_wallets';
    $wallets = $wpdb->get_results("SELECT * FROM $table_name");

    if (empty($wallets)) {
        error_log("No wallets found in the database.");
        return;
    }

    // Fetch supported currencies only once.
    $supported_currencies = fetch_supported_currencies();
    if (!$supported_currencies) {
        error_log("Failed to fetch supported currencies.");
        return;
    }

    foreach ($wallets as $wallet_data) {
        $coin_symbol = $wallet_data->coin_symbol;
        $api_key = decrypt_data($wallet_data->api_key);
        $password = decrypt_data($wallet_data->password);

        if (!$api_key || !$password) {
            error_log("Failed to decrypt API credentials for coin: $coin_symbol");
            continue;
        }

        $currency_data = find_currency_data($supported_currencies, $coin_symbol);
        if (!$currency_data) {
            error_log("Currency data not found for coin: $coin_symbol");
            continue;
        }

        $min_invoice_amount = calculate_min_invoice_amount($currency_data);
        $final_min_invoice_amount = $min_invoice_amount; // Default to USD value.
        $unit_fiat_amount = $currency_data['price_in_usd'];
        // error_log('setting_unit_fiat_amount: ' . $unit_fiat_amount);

        if ($new_currency !== 'USD') {
            $fiat_request = crypto_to_fiat($new_currency, $coin_symbol,1); // api call
            $final_min_invoice_amount = number_format($fiat_request * $currency_data['minimum_deposit_amount'], 8, '.', '');
            $unit_fiat_amount = $fiat_request;
            Error_log('coin_fiat_request: ' . $fiat_request);
            Error_log('coin_final_min_invoice_amount: ' . $final_min_invoice_amount);
            Error_log('coin_unit_fiat_amount: ' . $unit_fiat_amount);
        }
        update_wallet_data($table_name, $coin_symbol, $final_min_invoice_amount ,$unit_fiat_amount);
    }
}

