
<?php

/**
 * Custom function to declare compatibility with cart_checkout_blocks feature 
*/
function declare_cart_checkout_blocks_compatibility() {
    // Check if the required class exists
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        // Declare compatibility for 'cart_checkout_blocks'
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}
// Hook the custom function to the 'before_woocommerce_init' action
add_action('before_woocommerce_init', 'declare_cart_checkout_blocks_compatibility');


class My_Custom_Gateway extends WC_Payment_Gateway {
  
    // Constructor method
    // public function __construct() {
    //   $this->id                 = 'my_custom_gateway';
    //   $this->method_title       = __('Coinremitter Crypto payment gateway', COINREMITTER);
    //   $this->method_description = __('Accept payments through My Crypto Gateway', COINREMITTER);
      
    //   // Other initialization code goes here
      
    //   $this->init_form_fields();
    //   $this->init_settings();
      
    //   add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    // }
    
    // public function init_form_fields() {
    //   $this->form_fields = array(
    //     'title' => array(
    //         'title'   => __('Title', 'coinremitter'),
    //         'type'    => 'text', // Change type to 'text' for a text input field
    //         'label'   => __('Title for My Custom Gateway', 'coinremitter'),
    //         'default' => __('My Custom Gateway', 'coinremitter'), // Default title value
    //     ),
    //     'description' => array(
    //         'title'       => __('Description', 'coinremitter'),
    //         'type'        => 'textarea', // Change type to 'textarea' for a text area input field
    //         'description' => __('Description for My Custom Gateway', 'coinremitter'),
    //         'default'     => __('', 'coinremitter'), // Default description value
    //     ),
    //     'enabled' => array(
    //         'title'   => __('Enable/Disable', 'coinremitter'),
    //         'type'    => 'checkbox',
    //         'label'   => __('Enable My Custom Gateway', 'coinremitter'),  
    //         'default' => 'yes',
    //     ),
    //     // Add more settings fields as needed
    // );
    
        
    // }
    
    // Process the payment
    public function process_payment($order_id) {
      $order = wc_get_order($order_id);      
			$OrdID = $order->get_id();
      $order->payment_complete();
      $userID = get_current_user_id();
			$payment_title = $order->get_payment_method_title();
			$cancel_url = $order->get_cancel_order_url();
			$s_url =  $_SERVER['HTTP_REFERER'];
			$test_order = new WC_Order($OrdID);
			$test_order_key = $test_order->get_order_key();
			$sss_url = $s_url . '/order-pay/' . $OrdID . '/?pay_for_order=true&key=' . $test_order_key;
			// if ($payment_title == 'Cash on delivery') {
				$modified_url = $sss_url;
				return array(
            'result'   => 'success',
            'redirect' => $modified_url,
          );
			// }
      
      // Redirect to the thank you page
      // return array(
      //   'result'   => 'success',
      //   'redirect' => $this->get_return_url($order), 
      // );
    }
    
  }
  