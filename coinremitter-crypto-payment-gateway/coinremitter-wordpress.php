<?php

/*
Plugin Name:        CoinRemitter Crypto Payment Gateway
Plugin URI:         https://coinremitter.com/plugins
Description:        <a href="https://coinremitter.com">coinremitter.com</a> CoinRemitter Crypto Payment Gateway.
Version:            1.1.2
Author:             CoinRemitter
Author URI:         https://coinremitter.com
Requires at least:  6.6
Tested up to:       6.6.1


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


if (! defined('ABSPATH')) {
    exit;
}

$dir_arr = wp_upload_dir();

DEFINE('COINREMITTER', 'coinremitter');
DEFINE('COINREMITTER_DIR_PATH', plugin_dir_path(__FILE__));
DEFINE('COINREMITTER_URL', 'https://coinremitter.com/');
DEFINE('COINREMITTER_API_VERSION', 'v3');
DEFINE('COINREMITTER_PREVIEW', 'coinremitteradmin');
DEFINE('COINREMITTER_PAYMENT_FAIL', site_url('payment-fail'));
DEFINE('COINREMITTER_WEBHOOK_URL', site_url('?coinremitter_webhook'));
DEFINE('COINREMITTER_VERSION', '1.1.2');
DEFINE('COINREMITTER_ADMIN', admin_url('admin.php?page='));
DEFINE('COINREMITTER_DIR', $dir_arr['basedir'] . '/' . COINREMITTER . '/');
DEFINE('COINREMITTER_DIR2', $dir_arr['baseurl'] . '/' . COINREMITTER . '/');
DEFINE('COINREMITTER_IMG', plugins_url('/images/', __FILE__));
DEFINE('COINREMITTER_BASENAME', plugin_basename(__FILE__));
DEFINE('COINREMITTER_PERMISSION', 'add_users');
DEFINE('COINREMITTER_WORDPRESS', true);
DEFINE('COINREMITTER_RATES', wp_json_encode(array( 'USD' => 'US Dollar' )));
DEFINE('COINREMITTER_INV_PENDING', 0);
DEFINE('COINREMITTER_INV_PAID', 1);
DEFINE('COINREMITTER_INV_UNDER_PAID', 2);
DEFINE('COINREMITTER_INV_OVER_PAID', 3);
DEFINE('COINREMITTER_INV_EXPIRED', 4);
DEFINE('COINREMITTER_ECR_SKEY', 'coinremitter');
DEFINE('COINREMITTER_ECR_CIPHERING', 'AES-256-CBC');
DEFINE('COINREMITTER_ECR_OPTIONS', 0);
DEFINE('COINREMITTER_ECR_IV', 'Coinremitter__iv');
DEFINE('WOOCOMMERCE_VERSION', '3.0');
unset($dir_arr);

require_once plugin_dir_path(__FILE__) . '/coinremitter.php';


register_activation_hook(__FILE__, 'coinremitter_activate');
register_deactivation_hook(__FILE__, 'coinremitter_deactivate');

add_filter('plugin_action_links', 'coinremitter_action_links', 10, 2);


if (function_exists('mb_stripos') && function_exists('mb_strripos') && function_exists('curl_init')  && function_exists('mysqli_connect') && version_compare(phpversion(), '5.4.0', '>=')) {
    $coinremitter = new CoinremitterClass();
}





// Hook the custom function to the 'woocommerce_blocks_loaded' action
add_action( 'woocommerce_blocks_loaded', 'oawoo_register_order_approval_payment_method_type' );

/**
 * Custom function to register a payment method type

 */
function oawoo_register_order_approval_payment_method_type() {
    // Check if the required class exists
    if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        return;
    }

    // Include the custom Blocks Checkout class
    require_once plugin_dir_path(__FILE__) . '/class-block.php';

    // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
            // Register an instance of My_Custom_Gateway_Blocks
            $payment_method_registry->register( new My_Custom_Gateway_Blocks );
        }
    );
}



// add_action('plugins_loaded', 'woocommerce_myplugin', 0);
// function woocommerce_myplugin(){
//     if (!class_exists('WC_Payment_Gateway'))
//         return; // if the WC payment gateway class 
    
//     include(plugin_dir_path(__FILE__) . 'includes/class-gateway.php');
// }


// add_filter('woocommerce_payment_gateways', 'add_my_custom_gateway');

// function add_my_custom_gateway($gateways) {
//   $gateways[] = 'WC_Gateway_CoinRemitter';
//   return $gateways;
// }
function add_custom_class_to_body($classes) {
    if ( class_exists( 'woocommerce' ) ) {
        
    if (is_wc_endpoint_url('order-pay')) {
        $classes[] = 'coinremitter-invoice-page';
    }
}
return $classes;
}
add_filter('body_class', 'add_custom_class_to_body');
