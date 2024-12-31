<?php

function coinremitter_wp_table_create()
{
    error_log('db create');
    global $wpdb;

    $wallet_table = $wpdb->prefix . 'coinremitter_wallets'; // Table name
    $order_table = $wpdb->prefix . 'coinremitter_orders'; // Table name
    $transaction_table = $wpdb->prefix . 'coinremitter_transactions'; // Table name
    $charset_collate = $wpdb->get_charset_collate();


    $wallet_sql = "CREATE TABLE IF NOT EXISTS $wallet_table (
        id INT(11) NOT NULL AUTO_INCREMENT,
        wallet_name VARCHAR(255),
        coin_symbol VARCHAR(255),
        coin_name VARCHAR(255),
        api_key VARCHAR(255),
        password VARCHAR(255),
        minimum_invoice_amount DOUBLE(20, 8) NOT NULL COMMENT 'value in coin',
        exchange_rate_multiplier DOUBLE(20, 8),
        fiat_rate DECIMAL(20,8) NOT NULL,
        created_at TIMESTAMP,
        updated_at TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate ";

    $order_sql = "CREATE TABLE IF NOT EXISTS $order_table (
        id INT(11) NOT NULL AUTO_INCREMENT,
        order_id VARCHAR(255) NOT NULL,
        user_id VARCHAR(20) NOT NULL,
        coin_symbol VARCHAR(10) NOT NULL,
        coin_name VARCHAR(100) NOT NULL,
        crypto_amount DOUBLE(18, 8) NOT NULL,
        fiat_symbol VARCHAR(10) NOT NULL,
        fiat_amount DOUBLE(18, 2) NOT NULL,
        paid_crypto_amount DOUBLE(18, 8) DEFAULT NULL,
        paid_fiat_amount DOUBLE(18, 2) DEFAULT NULL,
        payment_address TEXT NOT NULL,
        qr_code TEXT DEFAULT NULL,
        order_status VARCHAR(50) NOT NULL DEFAULT '0',
        expiry_date datetime DEFAULT NULL, 
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate ";

    $transaction_sql = "CREATE TABLE IF NOT EXISTS $transaction_table (
        id INT(11) NOT NULL AUTO_INCREMENT,
        order_id VARCHAR(255) NOT NULL,
        user_id VARCHAR(20) NOT NULL,
        meta TEXT NULL,
        PRIMARY KEY (id)
    ) $charset_collate ";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($wallet_sql);
    dbDelta($order_sql);
    dbDelta($transaction_sql);
    
 
}
// add_action('plugins_loaded', 'coinremitter_wp_table_create');
