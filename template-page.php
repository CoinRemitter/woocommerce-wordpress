 <?php
    /* Template Name: cr Template */
    get_header();
    ?>

 <?php
    global $wpdb;
    $order_key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
    $order_id = wc_get_order_id_by_order_key($order_key);
    $order = wc_get_order($order_id);
    $used_coupons = $order->get_used_coupons();
    // echo 'hyyyyy'; print_r($used_coupons); die;

    if ($order && is_object($order)) {
        $items = $order->get_items();
    }
    $order_status = $order->get_status();
    $symbol = get_woocommerce_currency_symbol();
    $order_items = '';
    $position = get_option('woocommerce_currency_pos');
    $prefix = '';
    $suffix = '';

    switch ($position) {
        case 'left_space':
            $prefix = $symbol . ' ';
            break;
        case 'left':
            $prefix = $symbol;
            break;
        case 'right_space':
            $suffix = ' ' . $symbol;
            break;
        case 'right':
            $suffix = $symbol;
            break;
    }

    $decimal_separator = wc_get_price_decimal_separator();
    $thousand_separator = wc_get_price_thousand_separator();
    $decimals = wc_get_price_decimals();
    
    foreach ($items as $item) {
        $product = $item->get_product();
        $price = floatval($product->get_price());
        $formatted_price = number_format($price, $decimals, $decimal_separator, $thousand_separator);

        $formatted_price = $prefix . $formatted_price . $suffix;
        $order_items .= '   
                  <div class="order_summary d-flex flex-column"><div class="product_details">
                                          <div class="product_details_img">
                                              <div class="image flex-shrink-0 product_images">
                                                 ' . $product->get_image() . '
                                              </div>
                                              <p class="mb-0 invoice-label">' . $item->get_name() . '</p>
                                          </div>
                                          <div class="product_sum">
                                              <span class="bg-light px-2 rounded fw-bold">' . $item->get_quantity() . 'X</span>
                                              <span class="fw-bold">' . $formatted_price . '</span>
                                          </div>
                                      </div></div>';
    }

    $order_table_name = $wpdb->prefix . 'coinremitter_orders';

    $query = "SELECT * FROM $order_table_name WHERE order_id = " . $order_id . "";
    $get_order_data = $wpdb->get_results($query);
    if ($get_order_data[0]->order_status == COINREMITTER_INV_PAID || $get_order_data[0]->order_status == COINREMITTER_INV_OVER_PAID) {
        $url = site_url("inedx.php/checkout/?order-received=" . $order_id . "&key=" . $order_key . "");
        wp_redirect($url);
    } else if ($get_order_data[0]->order_status == COINREMITTER_INV_EXPIRED || $order->get_status() == 'cancelled') {
        // echo '<pre>';print_r($get_order_data[0]->order_status);die;
        $url = $order->get_cancel_order_url();
        wp_redirect($url);
    }

    $discount_amount = $order->get_discount_total();
    $dis_price = number_format($discount_amount, $decimals, $decimal_separator, $thousand_separator);
    $formattedDis_price = $prefix . $dis_price . $suffix;
    
    $price = floatval($order->get_subtotal());
    $formatted_priceTot = number_format($price, $decimals, $decimal_separator, $thousand_separator);
    $formatted_price_cur = $prefix . $formatted_priceTot . $suffix;

    // grand total
    $pricegrand = floatval(($order->get_subtotal()) + $order->get_shipping_total() + $order->get_shipping_tax() - $order->get_discount_total());
    $formatted_priceGrand = number_format($pricegrand, $decimals, $decimal_separator, $thousand_separator);
    $formatted_price_cur_total = $prefix . $formatted_priceGrand . $suffix;

    // Amount cal
    $priceamount = floatval($get_order_data[0]->crypto_amount);
    $decimalsamount = 8;
    $formatted_priceAmount = number_format($priceamount, $decimalsamount, $decimal_separator, $thousand_separator);
    $formatted_price_cur_am = $prefix . $formatted_priceAmount . $suffix;

    // Padding Amount
    $pad1 = $get_order_data[0]->crypto_amount - $get_order_data[0]->paid_crypto_amount;
    // $pad1 = $get_order_data[0]->paid_crypto_amount;
    // if ($pad <b 0) {
    //     $pad = 0;
    // }
    $pricepadamount = floatval($pad1);
    $decimalspadamount = 8;
    $formatted_pricepadAmount = number_format($pricepadamount, $decimalspadamount, $decimal_separator, $thousand_separator);
    $formatted_price_cur_pad = $prefix . $formatted_pricepadAmount . $suffix;

    // shipping amount
    $pricepadamountshiping = floatval($order->get_shipping_total());
    $decimalspadamount = 2;
    $formatted_priceshipigAmount = number_format($pricepadamountshiping, $decimalspadamount, $decimal_separator, $thousand_separator);
    $formatted_price_cur_ship = $prefix . $formatted_priceshipigAmount . $suffix;

    // taxes amount
    $pricepadamounttax = floatval($order->get_shipping_tax(),);
    $decimalspadamount = 2;
    $formatted_pricetaxAmount = number_format($pricepadamounttax, $decimalspadamount, $decimal_separator, $thousand_separator);
    $formatted_price_cur_tax = $prefix . $formatted_pricetaxAmount . $suffix;

    $padding_amount = $get_order_data[0]->paid_crypto_amount;

    // paid amount
    $paidpricepadamount = floatval($padding_amount);
    $decimalspadamount = 8;
    $formatted_pricepadAmount_paid = number_format($paidpricepadamount, $decimalspadamount, $decimal_separator, $thousand_separator);
    $formatted_price_cur_paid = $prefix . $formatted_pricepadAmount_paid . $suffix;

    ?>

 <div class="my-5 order-invoice main_box_invoice">
     <div class="container">
         <div class="row align-items-center fw-semibold">
             <div class="p-0">
                <div class="order-head">
                    <h2>Order Invoice #<?php echo $order_id  ?></h2>
                    <div class="d-flex align-items-center gap-2">
                        <span id="timer_status"></span>
                        <button class="timing-tooltip-btn">
                            <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M8 0.5C3.582 0.5 0 4.082 0 8.5C0 12.918 3.582 16.5 8 16.5C12.418 16.5 16 12.918 16 8.5C16 4.082 12.418 0.5 8 0.5ZM8.66667 12.5H7.33333V8.5H6V7.16667H8.66667V12.5ZM8 6C7.54 6 7.16667 5.62667 7.16667 5.16667C7.16667 4.70667 7.54 4.33333 8 4.33333C8.46 4.33333 8.83333 4.70667 8.83333 5.16667C8.83333 5.62667 8.46 6 8 6Z"
                                    fill="#166534" />
                            </svg>
                            <div class="tooltip-content">
                                <span class="content">Please complete the payment before time runs out</span>
                                <div class="arrow-down"></div>
                            </div>
                        </button>
                    </div>
                </div>
             </div>
             <div class="progress p-0" role="progressbar" id="progressbar" aria-label="Basic example" aria-valuenow="25"
                 aria-valuemin="0" aria-valuemax="100">
                 <div class="progress-bar-cr" style="width: 25%"></div>
             </div>
         </div>

         <div class="row border rounded p-1">
             <div class="col-lg-6 p-0">
                 <div class="d-flex flex-column gap-12 sub_main_payment p-sm-3 h-100">
                     <div class="bg-white p-3 main-payment-info">
                         <h4 class="title fw-semi-bold">Payment Info</h4>
                         <div class="flex-xl-row payment-info-sub">
                             <div class="qr text-center">
                                 <img src="<?php echo $get_order_data[0]->qr_code ?>">
                             </div>
                             <div class="payment-info-wrapper">
                                 <div class="mb-3">
                                     <span>Amount</span>
                                     <div class="payment-info amount_copy">
                                         <h4 data-copy-amount="<?php echo $formatted_priceAmount ?>"><?php echo $formatted_priceAmount . ' ' . $get_order_data[0]->coin_symbol ?></h4>
                                         <svg width="16" height="16" viewBox="0 0 16 16" fill="none"
                                             xmlns="http://www.w3.org/2000/svg">
                                             <path
                                                 d="M7.5 0H11.3781C11.775 0 12.1562 0.159375 12.4375 0.440625L14.5594 2.5625C14.8406 2.84375 15 3.225 15 3.62188V10.5C15 11.3281 14.3281 12 13.5 12H7.5C6.67188 12 6 11.3281 6 10.5V1.5C6 0.671875 6.67188 0 7.5 0ZM2.5 4H5V6H3V14H9V13H11V14.5C11 15.3281 10.3281 16 9.5 16H2.5C1.67188 16 1 15.3281 1 14.5V5.5C1 4.67188 1.67188 4 2.5 4Z"
                                                 fill="#696969" />
                                         </svg>
                                     </div>
                                 </div>
                                 <div>
                                     <span>Address</span>
                                     <div class="payment-info addr_copy">
                                         <h4 data-copy-detail="<?php echo $get_order_data[0]->payment_address ?>" id="order_addr"><?php echo $get_order_data[0]->payment_address ?></h4>

                                         <svg width="16" height="16" viewBox="0 0 16 16" fill="none"
                                             xmlns="http://www.w3.org/2000/svg">
                                             <path
                                                 d="M7.5 0H11.3781C11.775 0 12.1562 0.159375 12.4375 0.440625L14.5594 2.5625C14.8406 2.84375 15 3.225 15 3.62188V10.5C15 11.3281 14.3281 12 13.5 12H7.5C6.67188 12 6 11.3281 6 10.5V1.5C6 0.671875 6.67188 0 7.5 0ZM2.5 4H5V6H3V14H9V13H11V14.5C11 15.3281 10.3281 16 9.5 16H2.5C1.67188 16 1 15.3281 1 14.5V5.5C1 4.67188 1.67188 4 2.5 4Z"
                                                 fill="#696969" />
                                         </svg>
                                     </div>
                                 </div>
                             </div>
                         </div>
                     </div>
                     <div class="bg-white p-3 main-payment-info">
                         <h4 class="title d-flex justify-content-between fw-semi-bold">Payment History
                             <div class="cr-plugin-timer" id="timer_status_payment">
                             </div>
                         </h4>
                         <div class="mb-3 payment_history">
                             <div class="w-100">
                                 <div class="cr-plugin-history-list w-100" id="Webhook_history">
                                     <input type="hidden" id="expiry_time" value>
                                     <div class="cr-plugin-history-box">
                                         <div class="cr-plugin-history"
                                             style="text-align: center; padding-left: 0;">
                                             <span>No payment history found</span>
                                         </div>
                                     </div>
                                 </div>
                             </div>
                         </div>

                         <div class="bg-light d-flex rounded flex-column flex-xl-row">
                             <div class="col paid-amount">
                                 <h5>Paid Amount</h5>
                                 <strong class="m-0 font-bold"><span id="paid_amount"> <?php echo $formatted_pricepadAmount_paid ?></span> <sup id="coin_symbol"><?php echo $get_order_data[0]->coin_symbol ?> </sup></strong>
                             </div>
                             <div class="vr my-3 d-xl-block d-none"></div>
                             <div class="col paid-amount">
                                 <h5>Pending Amount</h5>
                                 <strong class="m-0" ><span id="padding_amount"><?php echo $formatted_pricepadAmount ?> </span> <sup id="coin_symbol"><?php echo $get_order_data[0]->coin_symbol ?> </sup></strong>
                             </div>
                         </div>
                     </div>
                 </div>
             </div>

             <div class="col-lg-6 p-0">
                 <div class="d-flex flex-column gap-12 sub_main_payment p-sm-3 bg-transparent">
                     <div class="bg-white p-3 main-payment-info border-0">
                         <h4 class="title fw-semi-bold">Billing Details</h4>
                         <div class="billing-details">
                             <div class="row">
                                 <div class="col-sm-3">
                                     <div class="d-flex justify-content-sm-between gap-1 text-secondary invoice-label-text"><span>Name</span> <span>:</span></div>
                                 </div>
                                 <div class="col-sm-9">
                                     <div class="d-flex justify-content-sm-between gap-1 invoice-label"><?php echo $order->get_billing_first_name() ?></div>
                                 </div>
                             </div>
                         </div>
                         <div class="billing-details">
                             <div class="row">
                                 <div class="col-sm-3">
                                     <div class="d-flex justify-content-sm-between gap-1 text-secondary invoice-label-text"><span>Address</span> <span>:</span></div>
                                 </div>
                                 <div class="col-sm-9">
                                     <div class="d-flex justify-content-sm-between gap-1 invoice-label"><?php echo $order->get_billing_address_1() ?></div>
                                 </div>
                             </div>
                         </div>
                         <div class="billing-details">
                             <div class="row">
                                 <div class="col-sm-3">
                                     <div class="d-flex justify-content-sm-between gap-1 text-secondary invoice-label-text"><span>Email</span> <span>:</span></div>
                                 </div>
                                 <div class="col-sm-9">
                                     <div class="d-flex justify-content-sm-between gap-1 invoice-label"><?php echo $order->get_billing_email() ?></div>
                                 </div>
                             </div>
                         </div>
                     </div>
                     <div class="bg-white p-3 main-payment-info border-0">
                         <h4 class="title fw-semi-bold">Shipping Details</h4>
                         <div class="billing-details">
                             <div class="row">
                                 <div class="col-sm-3">
                                     <div class="d-flex justify-content-sm-between gap-1 text-secondary invoice-label-text"><span>Name</span> <span>:</span></div>
                                 </div>
                                 <div class="col-sm-9">
                                     <div class="d-flex justify-content-sm-between gap-1 invoice-label"><?php echo $order->get_shipping_first_name() ?> </div>
                                 </div>
                             </div>
                         </div>
                         <div class="billing-details">
                             <div class="row">
                                 <div class="col-sm-3">
                                     <div class="d-flex justify-content-sm-between gap-1 text-secondary invoice-label-text"><span>Address</span> <span>:</span></div>
                                 </div>
                                 <div class="col-sm-9">
                                     <div class="d-flex justify-content-sm-between gap-1 invoice-label">
                                         <?php echo $order->get_shipping_address_1() ?>
                                     </div>
                                 </div>
                             </div>
                         </div>
                         <div class="billing-details">
                             <div class="row">
                                 <div class="col-sm-3">
                                     <div class="d-flex justify-content-sm-between gap-1 text-secondary invoice-label-text"><span>Email</span> <span>:</span></div>
                                 </div>
                                 <div class="col-sm-9">
                                     <div class="d-flex justify-content-sm-between gap-1 invoice-label"><?php echo $order->get_billing_email() ?></div>
                                 </div>
                             </div>
                         </div>
                     </div>
                     <div class="bg-white p-3 main-payment-info border-0">
                         <h4 class="title fw-semi-bold">Order Summary</h4>
                         <?php echo $order_items ?>
                         <div class="border-dashed border-secondary my-3"></div>
                         <div class="d-flex flex-column gap-2 border-bottom pb-3 border-dark mb-2">
                             <div class=" invoice_main_total">
                                 <span class="text-secondary invoice-label-text">Total</span>
                                 <strong><?php echo $formatted_price_cur ?></strong>
                             </div>
                             <div class=" invoice_main_total">
                                 <span class="text-secondary invoice-label-text">coupon</span>
                                 <strong><?php if($discount_amount){
                                        echo '-'.$formattedDis_price;
                                 } else {
                                        echo $formattedDis_price;
                                 } ?> </strong>
                             </div>
                             <div class=" invoice_main_total">
                                 <span class="text-secondary invoice-label-text">Total Taxes</span>
                                 <strong><?php echo $formatted_price_cur_tax ?> </strong>
                             </div>
                             <div class=" invoice_main_total">
                                 <span class="text-secondary invoice-label-text">Shipping Fee</span>
                                 <strong><?php echo $formatted_price_cur_ship ?> </strong>
                             </div>
                         </div>
                         <div class=" invoice_main_total">
                             <strong> Grand Total </strong>
                             <strong> <?php echo $formatted_price_cur_total ?> </strong>
                         </div>
                     </div>
                 </div>
             </div>
         </div>
     </div>
 </div>

 <input type="hidden" id="base_url" value="<?php echo site_url() ?>"><input type="hidden" id="order_id" value="<?php echo $order_id ?>"><input type="hidden" id="order_key" value="<?php echo $order_key ?>">
 <div class="cr-plugin-copy">
     <p>Copied Successfully.</p>
 </div>



 <?php get_footer(); ?>