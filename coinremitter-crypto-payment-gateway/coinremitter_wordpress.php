<?php
/*
Plugin Name: 		CoinRemitter Crypto Payment Gateway
Plugin URI: 		https://coinremitter.com/bitcoin-php-api
Description: 		<a href="https://coinremiiter.com">coinremitter.com</a> CoinRemitter Crypto Payment Gateway. 
Version: 		1.0.0
Author: 		CoinRemitter
Author URI: 		https://coinremitter.com
WC requires at least: 	3.5
WC tested up to: 	5.2.0


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


if (!defined( 'ABSPATH' )) exit; // Exit if accessed directly  


$dir_arr = wp_upload_dir();

DEFINE('COINREMITTER', 				"coinremitter");
DEFINE('COINREMITTER_API_URL', 		"https://coinremitter.com/api/");
DEFINE('COINREMITTER_API_URL_ALL_COIN', "https://coinremitter.com/");
DEFINE('COINREMITTER_PREVIEW', 		"coinremitteradmin");
DEFINE('COINREMITTER_PAYMENT_FAIl', 		site_url("payment-fail"));
DEFINE('COINREMITTER_INVOICE_NOTIFY_URL', 		site_url("?coinremitter_notify"));
DEFINE('COINREMITTER_VERSION', 		"0.1");
DEFINE('COINREMITTER_ADMIN', 			admin_url("admin.php?page="));
DEFINE('COINREMITTER_DIR',  			$dir_arr["basedir"]."/".COINREMITTER.'/');
DEFINE('COINREMITTER_DIR2', 			$dir_arr["baseurl"]."/".COINREMITTER.'/');
DEFINE('COINREMITTER_IMG', 			plugins_url('/images/', __FILE__));
DEFINE('COINREMITTER_BASENAME', 		plugin_basename(__FILE__));
DEFINE("COINREMITTER_PERMISSION", 		"add_users");
DEFINE('COINREMITTER_WORDPRESS',	true);
DEFINE('COINREMITTER_RATES', json_encode(array("USD" => "US Dollar")));
DEFINE('INV_PENDING', 0);
DEFINE('INV_PAID', 1);
DEFINE('INV_UNDER_PAID', 2);
DEFINE('INV_OVER_PAID', 3);
DEFINE('INV_EXPIRED', 4);
unset($dir_arr);


require_once(plugin_dir_path( __FILE__ )."/coinremitter.php");


register_activation_hook(__FILE__, "coinremitter_activate");
register_deactivation_hook(__FILE__, "coinremitter_deactivate");


add_filter('plugin_action_links', 	'coinremitter_action_links', 10, 2);


if (function_exists( 'mb_stripos' ) && function_exists( 'mb_strripos' ) && function_exists( 'curl_init' ) && function_exists( 'mysqli_connect' ) && version_compare(phpversion(), '5.4.0', '>=')) $coinremitter = new coinremitterclass();
         
       
       
