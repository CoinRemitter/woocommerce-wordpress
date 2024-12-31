<?php



add_filter('cron_schedules', 'coinremitter_wp_cron_interval');
function coinremitter_wp_cron_interval($schedules)
{
    $schedules['every_five_minutes'] = array(
        'interval' => 60, // 5 minutes in seconds
        'display' => __('Every 5 Minutes')
    );
    return $schedules;
}
function coinremitter_wp_update_fiat_rate()
{
    global $wpdb;
    $currancy_type = get_woocommerce_currency();
    error_log('currancy_type: ' . $currancy_type);
    $tablename = $wpdb->prefix . 'coinremitter_wallets';
    $sql = $wpdb->get_results("SELECT * FROM $tablename");
    foreach ($sql as $row) {
        $coin_symbol = $row->coin_symbol;

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

        $min_invoice_amount = calculate_min_invoice_amount($currency_data);
        $unit_fiat_amount = $currency_data['price_in_usd'];
        
        if ($currancy_type !== 'USD') {
            $fiat_request = crypto_to_fiat($currancy_type, $coin_symbol,1); // api call
            $final_min_invoice_amount = number_format($fiat_request * $currency_data['minimum_deposit_amount'], 8, '.', '');
            $unit_fiat_amount = $fiat_request;
            error_log('fiat_request: ' . $fiat_request);
            error_log('final_min_invoice_amount: ' . $final_min_invoice_amount);
            error_log('cron_unit_fiat_amount: ' . $unit_fiat_amount);
        }

        $data = array('unit_fiat_amount' => $unit_fiat_amount);
        $where = array('coin_symbol' => $coin_symbol);
        $updated = $wpdb->update($tablename, $data, $where);
        if ($updated === false) {
            error_log('Failed to update fiat_rate: ' . $wpdb->last_error);
        } else {
            error_log($coin_symbol . ' fiat_rate updated successfully to: ' . $unit_fiat_amount);
        }
    }
}


// Step 3: Schedule the cron event
function coinremitter_wp_schedule_fiat_rate_update()
{
    if (!wp_next_scheduled('update_fiat_rate_hook')) {
        wp_schedule_event(time(), 'every_five_minutes', 'update_fiat_rate_hook');
    }
}
