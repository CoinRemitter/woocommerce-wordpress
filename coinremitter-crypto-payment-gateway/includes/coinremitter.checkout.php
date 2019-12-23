<?php

if(!defined("COINREMITTER_WORDPRESS")) define("COINREMITTER_WORDPRESS", true); 

if (!COINREMITTER_WORDPRESS) require_once( "coinremitter.class.php" ); 
elseif (!defined('ABSPATH')) exit; // Exit if accessed directly in wordpress
$param = $_POST;
print_r($param);
exit(0);
if(!$param){
    die('only post method allowed.');
}

  