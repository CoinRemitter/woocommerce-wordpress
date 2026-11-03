<?php
if (!defined('ABSPATH')) {
    exit;
}

add_filter('cron_schedules', 'coinremitter_wp_cron_interval');
function coinremitter_wp_cron_interval($schedules)
{
    $schedules['every_five_minutes'] = array(
        'interval' => 60, // 1 minutes in seconds
        'display' => __('Every 1 Minutes', 'coinremitter-crypto-payment-gateway')
    );
    return $schedules;
}
function coinremitter_wp_update_fiat_rate()
{
    global $wpdb;
    $currancy_type = get_woocommerce_currency();
    error_log('currancy_type: ' . $currancy_type);
    $tablename = $wpdb->prefix . 'coinremitter_wallets';
    $supported_currencies = fetch_supported_currencies();
    if (!$supported_currencies) {
        error_log("CoinRemitter Cron: Failed to fetch supported currencies.");
        return;
    }

    $sql = $wpdb->get_results("SELECT * FROM $tablename");
    if (empty($sql)) {
        return;
    }

    foreach ($sql as $row) {
        $coin_symbol = $row->coin_symbol;

        $currency_data = find_currency_data($supported_currencies, $coin_symbol);
        if (!$currency_data) {
            error_log("CoinRemitter Cron: Currency data not found for coin: $coin_symbol");
            continue;
        }

        $min_invoice_amount = calculate_min_invoice_amount($currency_data);
        $unit_fiat_amount = isset($currency_data['price_in_usd']) ? floatval($currency_data['price_in_usd']) : 0;
        
        if ($currancy_type !== 'USD') {
            $fiat_request = crypto_to_fiat($currancy_type, $coin_symbol, 1);
            if (!is_wp_error($fiat_request) && is_numeric($fiat_request) && floatval($fiat_request) > 0) {
                $final_min_invoice_amount = number_format(floatval($fiat_request) * floatval($currency_data['minimum_deposit_amount']), 8, '.', '');
                $unit_fiat_amount = floatval($fiat_request);
                error_log("CoinRemitter Cron: Updated $coin_symbol rate to $unit_fiat_amount");
            } else {
                error_log("CoinRemitter Cron: Failed to fetch fiat rate for $coin_symbol to $currancy_type");
                continue;
            }
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


// Cancel expired CoinRemitter orders (used for guest customers)
function coinremitter_cancel_expired_orders()
{
    global $wpdb;

    $table = $wpdb->prefix . 'coinremitter_orders';
    $now   = gmdate('Y-m-d H:i:s');

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT order_id FROM $table
             WHERE expiry_date IS NOT NULL
               AND expiry_date <= %s
               AND order_status NOT IN (%d, %d, %d)
             ORDER BY expiry_date ASC
             LIMIT 50",
            $now,
            COINREMITTER_INV_PAID,
            COINREMITTER_INV_OVER_PAID,
            COINREMITTER_INV_EXPIRED
        )
    );

    if (empty($rows)) {
        return;
    }

    foreach ($rows as $row) {
        $order = wc_get_order((int) $row->order_id);

        if (!$order || !is_a($order, 'WC_Order')) {
            continue;
        }
        if ($order->get_payment_method() !== 'coinremitterpayments') {
            continue;
        }
        if (!$order->has_status(array('pending', 'on-hold'))) {
            continue;
        }

        $order->update_status('cancelled', __('Order expired before cryptocurrency payment was received.', 'coinremitter-crypto-payment-gateway'));
        $wpdb->update(
            $table,
            array('order_status' => COINREMITTER_INV_EXPIRED),
            array('order_id' => (int) $row->order_id),
            array('%d'),
            array('%d')
        );
    }
}

function coinremitter_wp_schedule_cancel_expired()
{
    if (!wp_next_scheduled('coinremitter_cancel_expired_orders_hook')) {
        wp_schedule_event(time(), 'every_five_minutes', 'coinremitter_cancel_expired_orders_hook');
    }
}

function coinremitter_clear_scheduled_crons()
{
    wp_clear_scheduled_hook('coinremitter_cancel_expired_orders_hook');
    wp_clear_scheduled_hook('update_fiat_rate_hook');
}