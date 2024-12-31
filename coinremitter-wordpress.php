<?php

/*
Plugin Name:        CoinRemitter Crypto Payment Gateway
Plugin URI:         https://coinremitter.com/plugins
Description:        <a href="https://coinremitter.com">coinremitter.com</a> CoinRemitter Crypto Payment Gateway.
Version:            1.1.3
Author:             CoinRemitter
Author URI:         https://coinremitter.com
Requires Plugins:   woocommerce
Requires at least:  6.7
Tested up to:       6.7.1


 *
 * CoinRemitter Crypto Payment Gateway is free software:
 * you can redistribute/resell it and/or modify it under the terms of the
 * GNU General Public License as published by  the Free Software Foundation,
 * either version 2 of the License, or any later version.
 *
 * CoinRemitter Crypto Payment Gateway is distributed
 * in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.    
 * See the GNU General Public License for more details.
*/


define('COINREMITTER', 'coinremitter');
define('COINREMITTER_SKEY', 'coinremitterdata');
define('COINREMITTER_CURL', 'https://api.coinremitter.com/v1');
DEFINE('COINREMITTER_PLUGIN_VERSION', '1.1.3');
DEFINE('COINREMITTER_API_VERSION', 'v1');
define('COINREMITTERWC', 'coinremitter-woocommerce');
define('COINREMITTERWC_AFFILIATE_KEY',     'coinremitter');
define('CR_PLUGIN_PATH', plugin_dir_url(__FILE__));
define('CR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('COINREMITTER_WORDPRESS', true);
define('COINREMITTER_INV_PENDING', 0);
define('COINREMITTER_INV_PAID', 1);
define('COINREMITTER_INV_UNDER_PAID', 2);
define('COINREMITTER_INV_OVER_PAID', 3);
define('COINREMITTER_INV_EXPIRED', 4);



add_action('init', 'coinre_actions_call');
function coinre_actions_call()
{
    if (is_admin()) {
        // error_log('admin_data');
        // admin script
        add_action('coinremitter_enqueue_script_admin', 'coinremitter_wp_admin_script');
        // wallet add
        add_action('wp_ajax_coinremitter_wp_wallet_add', 'coinremitter_wp_wallet_add');
        add_action('wp_ajax_nopriv_coinremitter_wp_wallet_add', 'coinremitter_wp_wallet_add');

        // wallet edit
        add_action('wp_ajax_coinremitter_wp_wallet_edit', 'coinremitter_wp_wallet_edit');
        add_action('wp_ajax_nopriv_coinremitter_wp_wallet_edit', 'coinremitter_wp_wallet_edit');

        // wallet delete
        add_action('wp_ajax_coinremitter_wp_wallet_delete', 'coinremitter_wp_wallet_delete');
        add_action('wp_ajax_nopriv_coinremitter_wp_wallet_delete', 'coinremitter_wp_wallet_delete');

        // admin menu create
        add_action('admin_menu', 'coinremitter_wp_admin_menu');

        // payment name
        add_filter('woocommerce_payment_gateways', 'coinremitter_wp_gateway_class');

        add_action('add_meta_boxes', 'coinremitter_cd_meta_box_add');

        // woocommerce setting page curency update
        add_action('update_option', 'track_currency_change', 10, 3);
        
    } 

}

add_filter('woocommerce_get_return_url', 'coinremitter_override_return_url', 20, 2);
add_action('plugins_loaded', 'coinremitter_wp_payment_gateways');   

add_action('wp_enqueue_scripts', 'coinremitter_wp_plugin_scripts', 20);
add_filter('body_class', 'add_custom_class_to_body');

// Webhook data
add_action('wc_ajax_coinremitter_webhook_data', 'coinremitter_webhook_data');

// Cancel order action
add_action('wc_ajax_coinremitter_cancel_order', 'coinremitter_cancel_order');

// Display custom meta data on the Order Received page
add_action('woocommerce_order_details_after_order_table', 'coinremitter_thank_you_field_display_cust_order_meta', 10, 1);
add_action('parse_request', 'callback_parse_request_coinremitter', 1);
add_action('wp_ajax_store_rel_value', 'store_rel_value');
add_action('wp_ajax_nopriv_store_rel_value', 'store_rel_value');
add_filter('woocommerce_payment_gateways', 'coinremitter_wp_gateway_class');
// cron update
add_action('update_fiat_rate_hook', 'coinremitter_wp_update_fiat_rate');
add_action('wp', 'coinremitter_wp_schedule_fiat_rate_update');




#--------------------------------------------------------------------------------
#region coinremitter payment block setting
#--------------------------------------------------------------------------------

require_once CR_PLUGIN_DIR . '/front/payment-setting.php';


#--------------------------------------------------------------------------------
#region invoice page create
#--------------------------------------------------------------------------------


register_activation_hook(__FILE__, 'cr_create_page');
function cr_create_page()
{
    // Check if the page already exists
    $page_title = 'Invoice Page';
    $page_check = get_page_by_title($page_title);

    // If the page doesn't exist, create it
    if (!isset($page_check->ID)) {
        $new_page_id = wp_insert_post(array(
            'post_title' => $page_title,
            'post_status' => 'publish',
            'post_type' => 'page',
        ));

        // Set the custom template for the new page
        if ($new_page_id) {
            update_post_meta($new_page_id, '_wp_page_template', 'template-page.php');
        }
    }
}
// Register the custom template
function cr_register_template($page_template)
{
    if (is_page('invoice-page')) {
        $page_template = plugin_dir_path(__FILE__) . 'template-page.php';
    }
    return $page_template;
}
add_filter('page_template', 'cr_register_template');


add_filter('theme_page_templates', 'cr_template_to_select', 10, 4);
function cr_template_to_select($post_templates, $wp_theme, $post, $post_type)
{
    $post_templates['template-page.php'] = __('Templateconfigurator');

    return $post_templates;
}




#--------------------------------------------------------------------------------
#region plugin activation invoice timer set 
#--------------------------------------------------------------------------------
class CoinremitterPlugin
{
    // Constructor for CoinremitterPlugin object
    function __construct()
    {
        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'coinrimitter_plugin_activate'));
    }

    // Activation function
    function coinrimitter_plugin_activate()
    {
        // Set WooCommerce settings for cryptocurrency payment
        $woocommerce_setting_data = [
            'enabled' => 'yes',
            'title' => 'Pay Using Cryptocurrency',
            'description' => 'Secure, anonymous payment with cryptocurrency. <a target="_blank" href="https://en.wikipedia.org/wiki/Cryptocurrency">What is it?</a>',
            'ostatus' => 'Processing',
            'invoice_expiry' => '30'
        ];

        // Update the WooCommerce settings
        update_option('woocommerce_coinremitterpayments_settings', $woocommerce_setting_data);
        coinremitter_wp_table_create();
        // Optional: Flush output buffers (not usually necessary here)
        ob_flush();
    }
}

// Instantiate the CoinremitterPlugin class
if (class_exists('CoinremitterPlugin')) {
    $my_plugin = new CoinremitterPlugin();
}

add_action( 'upgrader_process_complete', 'update_cr_tables', 10, 2 );
function update_cr_tables( $upgrader, $options ) {
    coinremitter_wp_table_create();
}