<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Download coin logo from API and save into uploads/coinremitter/COINSYMBOL.png
 */
function cr_save_coin_logo($api_data)
{
    if (empty($api_data['coin_symbol']) || empty($api_data['coin_logo'])) {
        error_log("CR ERROR: Missing coin symbol or coin logo.");
        return false;
    }

    // Lowercase symbol for file naming - strict alphanumeric to eliminate path traversal
    $coin_symbol = preg_replace('/[^a-z0-9_-]/', '', strtolower(sanitize_text_field($api_data['coin_symbol'])));
    $image_url   = esc_url_raw($api_data['coin_logo']);

    // === 1. Check plugin images folder ===
    $plugin_dir  = CR_PLUGIN_DIR . 'images/';
    $plugin_file = $plugin_dir . $coin_symbol . '.png';

    if (file_exists($plugin_file)) {
        error_log("CR INFO: Logo found in plugin folder => $plugin_file");
        return $plugin_file;
    }

    // === 2. Check uploads folder ===
    $upload_dir  = wp_upload_dir();
    $upload_path = $upload_dir["basedir"] . "/coinremitter/";
    $upload_file = $upload_path . $coin_symbol . '.png';

    if (file_exists($upload_file)) {
        error_log("CR INFO: Logo found in uploads folder => $upload_file");
        return $upload_file;
    }

    // Create directory if not exists
    if (!file_exists($upload_path)) {
        wp_mkdir_p($upload_path);
        error_log("CR INFO: Created uploads/coinremitter folder.");
    }

    // === 3. Fetch image from API ===
    $response = wp_remote_get($image_url, [
        'timeout'   => 30
    ]);

    if (is_wp_error($response)) {
        error_log("CR ERROR: Failed fetching logo: " . $response->get_error_message());
        return false;
    }

    $image_data = wp_remote_retrieve_body($response);

    if (empty($image_data)) {
        error_log("CR ERROR: Empty image data for: $coin_symbol");
        return false;
    }

    // === 4. Save image ===
    if (file_put_contents($upload_file, $image_data) === false) {
        error_log("CR ERROR: Cannot save logo to: $upload_file");
        return false;
    }

    error_log("CR SUCCESS: Logo saved => $upload_file");
    return $upload_file;
}

#--------------------------------------------------------------------------------
#region coinremitter wallet add
#--------------------------------------------------------------------------------

function coinremitter_check_ajax_security()
{
    if (!current_user_can('manage_options')) {
        $Result['flag'] = 0;
        $Result['msg'] = 'Unauthorized access.';
        wp_send_json($Result);
    }

    if (!check_ajax_referer('coinremitter_ajax_nonce', 'security', false)) {
        $Result['flag'] = 0;
        $Result['msg'] = 'Invalid security token (nonce).';
        wp_send_json($Result);
    }
}

function coinremitter_wp_wallet_add()
{
    coinremitter_check_ajax_security();
    error_log('Wallet_add');
    global $wallet_balance;
    $api_key = isset($_POST["wallet_key"]) ? sanitize_text_field(trim($_POST["wallet_key"])) : '';
    $password = isset($_POST["wallet_password"]) ? sanitize_text_field($_POST["wallet_password"]) : '';
    $minimum_invoice_amount = isset($_POST["minimum_invoice_amount"]) ? sanitize_text_field($_POST["minimum_invoice_amount"]) : '';
    $exchange_rate_multiplier = isset($_POST["exchange_rate_multiplier"]) ? sanitize_text_field($_POST["exchange_rate_multiplier"]) : '';

    if (
        empty($api_key)
        || empty($password)
        || empty($minimum_invoice_amount)
        || empty($exchange_rate_multiplier)
    ) {
        $Result['flag'] = 0;
        $Result['msg'] = 'Please fill out all the fields to proceed.';
        wp_send_json($Result);
    }

    if (!is_numeric($minimum_invoice_amount) || floatval($minimum_invoice_amount) <= 0) {
        $Result['flag'] = 0;
        $Result['msg'] = 'Minimum order value must be greater than 0.';
        wp_send_json($Result);
    }

    if (!is_numeric($exchange_rate_multiplier) || floatval($exchange_rate_multiplier) <= 0) {
        $Result['flag'] = 0;
        $Result['msg'] = 'Order multiplier must be greater than 0.';
        wp_send_json($Result);
    }

    $resultArr = balance_request($api_key, $password); // balance api call
    if (empty($resultArr) || empty($resultArr['success'])) {
        $Result['flag'] = 0;
        $Result['msg'] = isset($resultArr['msg']) ? $resultArr['msg'] : 'Failed to connect to CoinRemitter API.';
        wp_send_json($Result);
    }

    $wallet_coin = isset($resultArr['data']) ? $resultArr['data'] : array();
    $wallet_name = isset($wallet_coin['wallet_name']) ? $wallet_coin['wallet_name'] : '';
    $coin_name = isset($wallet_coin['coin']) ? $wallet_coin['coin'] : '';
    $coin_symbol = isset($wallet_coin['coin_symbol']) ? $wallet_coin['coin_symbol'] : '';

    /** ⭐ Download coin logo here */
    cr_save_coin_logo($wallet_coin);
        
    $currancy_type = get_woocommerce_currency();
    $supported_currencies = fetch_supported_currencies();
    
    if (!$supported_currencies) {
        error_log("Failed to fetch supported currencies.");
        $Result['flag'] = 0;
        $Result['msg'] = 'Failed to fetch supported currencies from CoinRemitter.';
        wp_send_json($Result);
    }

    $currency_data = find_currency_data($supported_currencies, $coin_symbol); // api call
    if (!$currency_data) {
        error_log("Currency data not found for coin: $coin_symbol");
        $Result['flag'] = 0;
        $Result['msg'] = 'Currency data not found for coin: ' . esc_html($coin_symbol);
        wp_send_json($Result);
    }
    $minInvoiceValInFiat = calculate_min_invoice_amount($currency_data); // api call
    $unit_fiat_amount = isset($currency_data['price_in_usd']) ? floatval($currency_data['price_in_usd']) : 0;

    if ($currancy_type !== 'USD') {
        $fiat_request = crypto_to_fiat($currancy_type, $coin_symbol, 1); // api call
        if (!is_wp_error($fiat_request) && is_numeric($fiat_request) && floatval($fiat_request) > 0) {
            $minInvoiceValInFiat = number_format(floatval($fiat_request) * floatval($currency_data['minimum_deposit_amount']), 8, '.', '');
            $unit_fiat_amount = floatval($fiat_request);
        }
    }

    if (floatval($minimum_invoice_amount) < floatval($minInvoiceValInFiat)) {
        $Result['msg'] = 'The minimum order value must be atleast <span>' . $minInvoiceValInFiat . ' ' . $currancy_type . '.</span>';
        $Result['flag'] = 0;
        wp_send_json($Result);
    }

    global $wpdb;
    $tablename = $wpdb->prefix . 'coinremitter_wallets';
    $sql = $wpdb->get_results("SELECT * FROM $tablename ");
    foreach ($sql as $row) {
        if ($row->coin_symbol == $coin_symbol) {
            $Result['flag'] = 0;
            $Result['msg'] = 'Wallet already exists.';
            wp_send_json($Result);
        }
    }
    $data = isset($resultArr['data']) ? $resultArr['data'] : array();
    $wallet_balance = isset($data['balance']) ? $data['balance'] : 0;

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
        ),
        array(
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%f',
            '%f',
            '%f'
        )
    );

    if ($insert_result === false) {
        $Result['flag'] = 0;
        $Result['msg'] = 'Failed to insert data into the database.';
        wp_send_json($Result);
    } else {
        $Result['flag'] = 1;
        $Result['msg'] = 'Wallet added successfully.';
        wp_send_json($Result);
    }
}




#--------------------------------------------------------------------------------
#region coinremitter update data
#--------------------------------------------------------------------------------

function coinremitter_wp_wallet_edit()
{
    coinremitter_check_ajax_security();
    error_log('Wallet_update');
    $coinid = isset($_POST["coin_id"]) ? absint($_POST["coin_id"]) : 0;
    $api_key = isset($_POST["api_key_value"]) ? sanitize_text_field(trim($_POST["api_key_value"])) : '';
    $password = isset($_POST["password"]) ? sanitize_text_field($_POST["password"]) : '';
    $invoice = isset($_POST['invoice']) ? sanitize_text_field($_POST['invoice']) : '';
    $rate = isset($_POST['rate']) ? sanitize_text_field($_POST['rate']) : '';

    if (empty($coinid) || empty($api_key) || empty($invoice) || empty($rate)) {
        $Result['flag'] = 0;
        $Result['msg'] = 'Please fill out all the fields to proceed.';
        wp_send_json($Result);
    }

    if (!is_numeric($invoice) || floatval($invoice) <= 0) {
        $Result['flag'] = 0;
        $Result['msg'] = 'Minimum order value must be greater than 0.';
        wp_send_json($Result);
    }

    if (!is_numeric($rate) || floatval($rate) <= 0) {
        $Result['flag'] = 0;
        $Result['msg'] = 'Order multiplier must be greater than 0.';
        wp_send_json($Result);
    }

    global $wpdb;
    $tablename = $wpdb->prefix . 'coinremitter_wallets';
    $existingWallet = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $tablename WHERE id = %d", $coinid)
    );
    if (!$existingWallet) {
        $Result['flag'] = 0;
        $Result['msg'] = 'Wallet not found.';
        wp_send_json($Result);
    }

    // If password is left blank, reuse existing password from database
    if (empty($password)) {
        $password = decrypt_data($existingWallet->password);
    }

    $resultArr = balance_request($api_key, $password); // balance api call
    if (empty($resultArr) || empty($resultArr['success'])) {
        $Result['flag'] = 0;
        $Result['msg'] = isset($resultArr['msg']) ? $resultArr['msg'] : 'Failed to connect to CoinRemitter API.';
        wp_send_json($Result);
    }
    $wallet_coin = isset($resultArr['data']) ? $resultArr['data'] : array();
    $wallet_name = isset($wallet_coin['wallet_name']) ? $wallet_coin['wallet_name'] : '';
    $coin_name = isset($wallet_coin['coin']) ? $wallet_coin['coin'] : '';
    $coin_symbol = isset($wallet_coin['coin_symbol']) ? $wallet_coin['coin_symbol'] : '';

    $currancy_type = get_woocommerce_currency();

    $supported_currencies = fetch_supported_currencies();
    if (!$supported_currencies) {
        error_log("Failed to fetch supported currencies.");
        $Result['flag'] = 0;
        $Result['msg'] = 'Failed to fetch supported currencies from CoinRemitter.';
        wp_send_json($Result);
    }
    $currency_data = find_currency_data($supported_currencies, $coin_symbol); // api call
    if (!$currency_data) {
        error_log("Currency data not found for coin: $coin_symbol");
        $Result['flag'] = 0;
        $Result['msg'] = 'Currency data not found for coin: ' . esc_html($coin_symbol);
        wp_send_json($Result);
    }
    $minInvoiceValInFiat = calculate_min_invoice_amount($currency_data); // api call
    $unit_fiat_amount = isset($currency_data['price_in_usd']) ? floatval($currency_data['price_in_usd']) : 0;

    if ($currancy_type !== 'USD') {
        $fiat_request = crypto_to_fiat($currancy_type, $coin_symbol, 1); // api call
        if (!is_wp_error($fiat_request) && is_numeric($fiat_request) && floatval($fiat_request) > 0) {
            $minInvoiceValInFiat = number_format(floatval($fiat_request) * floatval($currency_data['minimum_deposit_amount']), 8, '.', '');
            $unit_fiat_amount = floatval($fiat_request);
        }
    }

    if (floatval($invoice) < floatval($minInvoiceValInFiat)) {
        $Result['msg'] = 'The minimum order value must be atleast <span>' . $minInvoiceValInFiat . ' ' . $currancy_type . '.</span>';
        $Result['flag'] = 0;
        wp_send_json($Result);
    }

    if ($existingWallet->coin_symbol !== $coin_symbol) {
        $Result['flag'] = 0;
        $Result['msg'] = 'Wallet currency mismatch.';
        wp_send_json($Result);
    }

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
            '%s',
            '%s',
            '%s',
            '%f',
            '%f',
            '%f'
        ),
        array('%d')
    );
    if ($update_result === false) {
        $Result['flag'] = 0;
        $Result['msg'] = 'Failed to update data into the database.';
        wp_send_json($Result);
    } else {
        $Result['flag'] = 1;
        $Result['msg'] = 'Wallet updated successfully.';
        wp_send_json($Result);
    }
}

#--------------------------------------------------------------------------------
#region coinremitter wallet get details (AJAX fetch for edit modal)
#--------------------------------------------------------------------------------

function coinremitter_wp_wallet_get()
{
    coinremitter_check_ajax_security();
    error_log('Wallet_get');
    global $wpdb;
    $id = isset($_POST["id"]) ? absint($_POST["id"]) : 0;
    if (!$id) {
        wp_send_json_error(array('msg' => 'Invalid wallet ID.'));
    }
    $table_name = $wpdb->prefix . 'coinremitter_wallets';
    $wallet = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    if (!$wallet) {
        wp_send_json_error(array('msg' => 'Wallet not found.'));
    }

    $api_key = decrypt_data($wallet->api_key);
    $password = decrypt_data($wallet->password);

    wp_send_json_success(array(
        'id' => $wallet->id,
        'coin_symbol' => $wallet->coin_symbol,
        'coin_name' => $wallet->coin_name,
        'coin_logo_url' => cr_get_coin_logo_url($wallet->coin_symbol),
        'api_key' => $api_key,
        'password' => $password,
        'minimum_invoice_amount' => $wallet->minimum_invoice_amount,
        'exchange_rate_multiplier' => $wallet->exchange_rate_multiplier,
    ));
}

#--------------------------------------------------------------------------------
#region coinremitter wallet delete
#--------------------------------------------------------------------------------


function coinremitter_wp_wallet_delete()
{
    coinremitter_check_ajax_security();
    error_log('Wallet_delete');
    global $wpdb;
    $id = isset($_POST["id"]) ? absint($_POST["id"]) : 0;
    if (!$id) {
        wp_send_json_error(array('msg' => 'Invalid wallet ID.'));
    }
    $table_name = $wpdb->prefix . 'coinremitter_wallets';
    $result = $wpdb->delete($table_name, array('id' => $id), array('%d'));

    if ($result !== false) {
        wp_send_json_success(array('msg' => 'Wallet deleted successfully'));
    } else {
        wp_send_json_error(array('msg' => 'Failed to delete wallet'));
    }
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
            $fiat_request = crypto_to_fiat($new_currency, $coin_symbol, 1); // api call
            if (!is_wp_error($fiat_request) && is_numeric($fiat_request) && floatval($fiat_request) > 0) {
                $final_min_invoice_amount = number_format(floatval($fiat_request) * floatval($currency_data['minimum_deposit_amount']), 8, '.', '');
                $unit_fiat_amount = floatval($fiat_request);
                Error_log('coin_fiat_request: ' . $fiat_request);
                Error_log('coin_final_min_invoice_amount: ' . $final_min_invoice_amount);
                Error_log('coin_unit_fiat_amount: ' . $unit_fiat_amount);
            }
        }
        update_wallet_data($table_name, $coin_symbol, $final_min_invoice_amount ,$unit_fiat_amount);
    }
}

