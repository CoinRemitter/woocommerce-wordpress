
<?php

function coinremitter_wp_admin_menu()
{
    add_menu_page(
        'CoinRemitter',                // Page title
        'Coin Remitter',               // Menu title
        'manage_options',              // Capability required to view this menu
        'coinremitter',               // Menu slug
        'coinremitter_wp_page_summary',   // Function to display the page content
        CR_PLUGIN_PATH . '/images/coin_admin.svg', // Icon URL
        6                               // Position in the menu
    );
}

function coinremitter_wp_page_summary()
{ 
    require_once CR_PLUGIN_DIR . 'admin/admin-js.php';
    
    do_action( 'coinremitter_enqueue_script_admin' );
    $currancy_type = get_woocommerce_currency();
    ?>
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content coin_model_content">
            <div class="modal-header model_coin_header">
                <h1 class="modal-title fs-5 fw-bold" id="exampleModalLabel">Add Wallet</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="post" action="" enctype="multipart/form-data" id="csf-form" autocomplete="off" novalidate="novalidate">
                <div class="wallet-form-box">

                    <div class="csf-field csf-field-text">
                        <div class="csf-title">
                            <label>Api Key <span class="reques_data">*</span> <a href="https://blog.coinremitter.com/how-to-get-api-key-and-password-of-coinremitter-wallet/" target="_blank" style="float:right; font-size:12px; font-weight:600;">(get API KEY)</a></label>
                        </div>
                        <div class="csf-fieldset">
                            <input type="text" name="wallet_key" value="" id="wallet_key" data-depend-id="wallet_key">
                        </div>
                        <div class="clear"></div>
                    </div>
                    <div class="csf-field csf-field-text">
                        <div class="csf-title">
                            <label>Password <span class="reques_data">*</span></label>
                        </div>
                        <div class="csf-fieldset"><input type="password" name="wallet_password" id="wallet_password" value="" data-depend-id="wallet_password">
                        </div>
                        <div class="clear"></div>
                    </div>

                    <div class="csf-field csf-field-text">
                        <div class="csf-title">
                            <label class="dynamic_val">Minimum Order Value (in <?php echo $currancy_type ?>)
                                <span class="reques_data">*</span>
                                    <div class="hover_text">
                                    <svg fill="#0d6efd" height="15px" width="15px" version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 29.536 29.536" xml:space="preserve"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g> <path d="M14.768,0C6.611,0,0,6.609,0,14.768c0,8.155,6.611,14.767,14.768,14.767s14.768-6.612,14.768-14.767 C29.535,6.609,22.924,0,14.768,0z M14.768,27.126c-6.828,0-12.361-5.532-12.361-12.359c0-6.828,5.533-12.362,12.361-12.362 c6.826,0,12.359,5.535,12.359,12.362C27.127,21.594,21.594,27.126,14.768,27.126z"></path> <path d="M14.385,19.337c-1.338,0-2.289,0.951-2.289,2.34c0,1.336,0.926,2.339,2.289,2.339c1.414,0,2.314-1.003,2.314-2.339 C16.672,20.288,15.771,19.337,14.385,19.337z"></path> <path d="M14.742,6.092c-1.824,0-3.34,0.513-4.293,1.053l0.875,2.804c0.668-0.462,1.697-0.772,2.545-0.772 c1.285,0.027,1.879,0.644,1.879,1.543c0,0.85-0.67,1.697-1.494,2.701c-1.156,1.364-1.594,2.701-1.516,4.012l0.025,0.669h3.42 v-0.463c-0.025-1.158,0.387-2.162,1.311-3.215c0.979-1.08,2.211-2.366,2.211-4.321C19.705,7.968,18.139,6.092,14.742,6.092z"></path> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> </g> </g></svg>
                                        <p class="hover_info tooltip_minimum">This wallet option will be hidden during checkout if the total order value is below the required minimum order value.</p>
                                    </div>
                            </label>
                        </div>
                        <div class="csf-fieldset"><input type="number" name="minimum_invoice_amount"
                                id="minimum_invoice_amount" value="" data-depend-id="wallet_invoice">
                        </div>
                        <div class="clear"></div>
                    </div>

                    <div class="csf-field csf-field-text">
                        <div class="csf-title">
                            <label>Order Multiplier<span class="reques_data">*</span>
                            <div class="hover_text">
                            <svg fill="#0d6efd" height="15px" width="15px" version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 29.536 29.536" xml:space="preserve"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g> <path d="M14.768,0C6.611,0,0,6.609,0,14.768c0,8.155,6.611,14.767,14.768,14.767s14.768-6.612,14.768-14.767 C29.535,6.609,22.924,0,14.768,0z M14.768,27.126c-6.828,0-12.361-5.532-12.361-12.359c0-6.828,5.533-12.362,12.361-12.362 c6.826,0,12.359,5.535,12.359,12.362C27.127,21.594,21.594,27.126,14.768,27.126z"></path> <path d="M14.385,19.337c-1.338,0-2.289,0.951-2.289,2.34c0,1.336,0.926,2.339,2.289,2.339c1.414,0,2.314-1.003,2.314-2.339 C16.672,20.288,15.771,19.337,14.385,19.337z"></path> <path d="M14.742,6.092c-1.824,0-3.34,0.513-4.293,1.053l0.875,2.804c0.668-0.462,1.697-0.772,2.545-0.772 c1.285,0.027,1.879,0.644,1.879,1.543c0,0.85-0.67,1.697-1.494,2.701c-1.156,1.364-1.594,2.701-1.516,4.012l0.025,0.669h3.42 v-0.463c-0.025-1.158,0.387-2.162,1.311-3.215c0.979-1.08,2.211-2.366,2.211-4.321C19.705,7.968,18.139,6.092,14.742,6.092z"></path> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> </g> </g></svg>
                                        <p class="hover_info">It will adjust the order value by applying a multiplier, which can then be converted into the equivalent cryptocurrency amount. For instance, to increase the charge by 10%, you would apply a multiplier of 1.1. Conversely, to offer a 10% discount, the multiplier would be set to 0.9.</p>
                                    </div>
                                </label>
                        </div>
                        <div class="csf-fieldset"><input type="number" name="exchange_rate_multiplier"
                                id="exchange_rate_multiplier" value="1"></div>
                        <div class="clear"></div>
                    </div>

                </div>
                <div class="pum-submit">
                    <div id="error-message-wallet"></div>
                    <div id="add-success-message-wallet"></div>
                    <button type="submit" class="button button-primary js-register-theme add-wallet-btn" id="VerifyBtnwallet">Add
                        Wallet</button>

                </div>
            </form>

        </div>
    </div>
</div>


<?php
    global $wpdb;
    $table_name = $wpdb->prefix . 'coinremitter_wallets';

    $results = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
    if ($results) {
    ?>
        <?php
        foreach ($results as $result) {
            $wallet_name = $result['wallet_name'];
        ?>

            <input type='hidden' id='coin_id' name="coin_id" value="" />
            <input type='hidden' id='coinName' name="coinName" value="" />

        <?php } ?>
    <?php
    }  ?>

    <div class="modal fade" id="update_wallet" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content coin_model_content">
                <div class="modal-header model_coin_header">
                    <h1 class="modal-title fs-5 fw-bold" id="exampleModalLabel">Update
                        <img class="wallet_image" alt="Wallet Image" style="width: 5%;">
                        <span id="editcointypeid"> <?php
                         if ($results) {
                         echo $wallet_name;
                        }
                          ?> </span>
                        Wallet
                    </h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form method="post" action="" enctype="multipart/form-data" id="coinedit-form" autocomplete="off" novalidate="novalidate">
                    <div class="wallet-form-box">
                        <input type='hidden' name='currency_type' id='currency_type' value="<?php if ($results) { echo $wallet_name;  }?>" />


                        <div class="csf-field csf-field-text">
                            <div class="csf-title">
                                <label>Api Key <span class="reques_data">*</span>
                                <a href="https://blog.coinremitter.com/how-to-get-api-key-and-password-of-coinremitter-wallet/" target="_blank" style="float:right; font-size:12px; font-weight:600;">(get API KEY)</a></label>
                            </div>
                            <div class="csf-fieldset">
                                <input type="text" name="wallet_key_update" value="" id="wallet_key_update" data-depend-id="wallet_key">
                            </div>
                            <div class="clear"></div>
                        </div>
                        <div class="csf-field csf-field-text">
                            <div class="csf-title">
                                <label>Password <span class="reques_data">*</span></label>
                            </div>
                            <div class="csf-fieldset"><input type="password" name="wallet_password_update" id="wallet_password_update" value="" data-depend-id="wallet_password">
                            </div>
                            <div class="clear"></div>
                        </div>
                        <div class="csf-field csf-field-text">
                            <div class="csf-title">
                                <label class="dynamic_val">Minimum Order Value (in <?php echo $currancy_type ?>) <span class="reques_data"> *</span>
                                    <div class="hover_text">
                                    <svg fill="#0d6efd" height="15px" width="15px" version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 29.536 29.536" xml:space="preserve"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g> <path d="M14.768,0C6.611,0,0,6.609,0,14.768c0,8.155,6.611,14.767,14.768,14.767s14.768-6.612,14.768-14.767 C29.535,6.609,22.924,0,14.768,0z M14.768,27.126c-6.828,0-12.361-5.532-12.361-12.359c0-6.828,5.533-12.362,12.361-12.362 c6.826,0,12.359,5.535,12.359,12.362C27.127,21.594,21.594,27.126,14.768,27.126z"></path> <path d="M14.385,19.337c-1.338,0-2.289,0.951-2.289,2.34c0,1.336,0.926,2.339,2.289,2.339c1.414,0,2.314-1.003,2.314-2.339 C16.672,20.288,15.771,19.337,14.385,19.337z"></path> <path d="M14.742,6.092c-1.824,0-3.34,0.513-4.293,1.053l0.875,2.804c0.668-0.462,1.697-0.772,2.545-0.772 c1.285,0.027,1.879,0.644,1.879,1.543c0,0.85-0.67,1.697-1.494,2.701c-1.156,1.364-1.594,2.701-1.516,4.012l0.025,0.669h3.42 v-0.463c-0.025-1.158,0.387-2.162,1.311-3.215c0.979-1.08,2.211-2.366,2.211-4.321C19.705,7.968,18.139,6.092,14.742,6.092z"></path> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> </g> </g></svg>
                                        <p class="hover_info tooltip_minimum">This wallet option will be hidden during checkout if the total order value is below the required minimum order value.</p>
                                    </div>
                                </label>
                            </div>
                            <div class="csf-fieldset"><input type="number" name="minimum_invoice_amount_edit"
                                    id="minimum_invoice_amount_edit" value="" data-depend-description="wallet_description">
                            </div>
                            <div class="clear"></div>
                        </div>
                        <div class="csf-field csf-field-text">
                            <div class="csf-title">
                                <label class="dynamic_val">Order Multiplier<span class="reques_data">*</span>
                                <div class="hover_text">
                                <svg fill="#0d6efd" height="15px" width="15px" version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 29.536 29.536" xml:space="preserve"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g> <path d="M14.768,0C6.611,0,0,6.609,0,14.768c0,8.155,6.611,14.767,14.768,14.767s14.768-6.612,14.768-14.767 C29.535,6.609,22.924,0,14.768,0z M14.768,27.126c-6.828,0-12.361-5.532-12.361-12.359c0-6.828,5.533-12.362,12.361-12.362 c6.826,0,12.359,5.535,12.359,12.362C27.127,21.594,21.594,27.126,14.768,27.126z"></path> <path d="M14.385,19.337c-1.338,0-2.289,0.951-2.289,2.34c0,1.336,0.926,2.339,2.289,2.339c1.414,0,2.314-1.003,2.314-2.339 C16.672,20.288,15.771,19.337,14.385,19.337z"></path> <path d="M14.742,6.092c-1.824,0-3.34,0.513-4.293,1.053l0.875,2.804c0.668-0.462,1.697-0.772,2.545-0.772 c1.285,0.027,1.879,0.644,1.879,1.543c0,0.85-0.67,1.697-1.494,2.701c-1.156,1.364-1.594,2.701-1.516,4.012l0.025,0.669h3.42 v-0.463c-0.025-1.158,0.387-2.162,1.311-3.215c0.979-1.08,2.211-2.366,2.211-4.321C19.705,7.968,18.139,6.092,14.742,6.092z"></path> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> </g> </g></svg>
                                        <p class="hover_info">It will adjust the order value by applying a multiplier, which can then be converted into the equivalent cryptocurrency amount. For instance, to increase the charge by 10%, you would apply a multiplier of 1.1. Conversely, to offer a 10% discount, the multiplier would be set to 0.9.</p>
                                    </div>
                                </label>
                            </div>
                            <div class="csf-fieldset"><input type="number" name="exchange_rate_multiplier_edit"
                                    id="exchange_rate_multiplier_edit" value="" data-depend-description="wallet_description">
                            </div>
                            <div class="clear"></div>
                        </div>
                    </div>
                    <div class="pum-submit">
                        <h6 id="edit-success-message-wallet"></h6>
                        <h6 id="error-message-wallet-edit"></h6>
                        <p id="message-container"></p>
                        <button type="submit" class="button button-primary js-register-theme walletUpdateBtn" id="<?php  if ($results) { echo $wallet_name; } ?>">Update
                            Wallet</button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <?php
    #--------------------------------------------------------------------------------
    #region wallet delete modal
    #--------------------------------------------------------------------------------
    ?>


    <div class="modal pum-modal-background fade" id="delete_wallet" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="pum-modal-header">
                    <span id="pum_trigger_add_type_modal-title" class="pum-modal-title">Remove <span class="wallet_coin">TCN</span> Wallet</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="pum-modal-content">
                    <div class="pum-field-section ">
                        <div class="wallet-form">
                            <h3>Are you sure want to remove ?</h3>
                            <p>It will remove <span class="wallet_coin">TCN</span> wallet from your database only. It will not remove actual wallet from coinremitter.</p>
                        </div>
                    </div>
                </div>
                <div class="pum-modal-footer submitbox">
                    <span class="frmWithdrowError" style="color:red;"></span>
                    <div class="pum-submit_delete">
                        <span class="delete_spinner" style="float:none"></span>
                        <input type="button" class="coinremitterbutton button-primary walletdeleteBtn" name="submit" value="Remove" style="background-color:#0085ba;">
                        <input type="button" class="coinremitterbutton button-primary  ClosePopup" data-bs-dismiss="modal" aria-label="Close" value="Cancel" style="background-color:#0085ba;">
                    </div>
                </div>
            </div>
        </div>
    </div>


    <?php


    if (! class_exists('WP_List_Table')) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
    }

    if (! class_exists('Coinremitter_Wp_List_Table')) {
        class Coinremitter_Wp_List_Table extends WP_List_Table
        {

            // Constructor
            public function __construct()
            {
                parent::__construct(array(
                    'singular' => 'item', // Singular name of the listed records
                    'plural'   => 'items', // Plural name of the listed records
                    'ajax'     => true // Does this table support ajax?
                ));
            }

            // Prepare items for display
            public function prepare_items()
            {
                $columns = $this->get_columns();
                $this->_column_headers = array($columns, array(), array());
                $data = $this->get_data();
                $this->items = $data;
            }

            // Define the columns for the table
            public function get_columns()
            {
                return array(
                    'wallet_image'              => __('Wallet image', 'textdomain'),
                    'wallet_name'              => __('Wallet Name', 'textdomain'),
                    'balance'                  => __('Balance', 'textdomain'),
                    'api_key'                  => __('API Key', 'textdomain'),
                    'minimum_invoice_amount'   => __('Minimum Order Value', 'textdomain'),
                    'exchange_rate_multiplier' => __('Order Multiplier', 'textdomain'),
                    'actions' => __('Actions', 'textdomain')
                );
            }

            // Retrieve data for the table
            private function get_data()
            {

                global $wpdb;
                $table_name = $wpdb->prefix . 'coinremitter_wallets';
                $results = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

                $data = array();
                $currancy_type = get_woocommerce_currency();
                foreach ($results as $result) {
                    $wallet_id = $result['id'];
                    $wallet_name = $result['wallet_name'];
                    $coin_symbol = $result['coin_symbol'];
                    $minimum_de_amount = $result['minimum_invoice_amount'];
                    $minimum_invoice_amount = $result['minimum_invoice_amount'];
                    $exchange_rate_multiplier = $result['exchange_rate_multiplier'];
                    $api_key = decrypt_data($result['api_key']);
                    $password = decrypt_data($result['password']);
                    $resultArr = balance_request($api_key, $password); // api call
                   
                    if (isset($resultArr['data'])) {
                        $wallet_coin = $resultArr['data'];
                        $wallet_image = CR_PLUGIN_PATH . 'images/' . strtolower($coin_symbol) . '.png';
                        $wal_img = '<img src="' . esc_url($wallet_image) . '" alt="' . esc_attr($coin_symbol) . '" style="width: 10%;">';
                        $coin_nmame = '<sup> ' . $coin_symbol . '</sup>';
                        $data[] = array(
                            'id' => $wallet_id,
                            'wallet_name' => $wallet_name,
                            'wallet_image' => $wal_img,
                            'coin_symbol'           => $coin_symbol,
                            'balance'               => $wallet_coin['balance'] . $coin_nmame,
                            'api_key'               => $api_key,
                            'password'               => $password,
                            'minimum_invoice_amount' => $minimum_invoice_amount .' '. $currancy_type,
                            'minimum_invoice_amounts' => $minimum_de_amount,
                            'exchange_rate_multiplier' => $exchange_rate_multiplier
                        );
                    } else {
                        // Handle cases where no data is returned from the API
                        $data[] = array(
                            'id' => $wallet_id,
                            'coin_symbol'           => $coin_symbol,
                            'balance'               => 'N/A',
                            'api_key'               => $api_key,
                            'password'               => $password,
                            'minimum_invoice_amount' => $minimum_invoice_amount,
                            'exchange_rate_multiplier' => $exchange_rate_multiplier
                        );
                    }
                }

                return $data;
            }

            // Display a single column's value
            protected function column_default($item, $column_name)
            {
                switch ($column_name) {
                    case 'actions':
                        $edit_url = add_query_arg(array('action' => 'edit', 'id' => $item['id'], 'password' => $item['password'], 'coin_symbol' => $item['coin_symbol'], 'api_key' => $item['api_key'], 'minimum_invoice_amounts' => $item['minimum_invoice_amounts'], 'exchange_rate_multiplier' => $item['exchange_rate_multiplier']));
                        $delete_url = add_query_arg(array('action' => 'delete', 'id' => $item['id']), admin_url('admin.php'));
                        return sprintf(
                            '<a href="javascript:void(0)" data-id="' . $item['id'] . '" data-rel="' . $item['coin_symbol'] . '" data-key="' . $item['api_key'] . '" data-password="' . $item['password'] . '" data-amount="' . $item['minimum_invoice_amounts'] . '" data-rate="' . $item['exchange_rate_multiplier'] . '" class="EditOpenPopup">Edit</a> | 
                        <a href="javascript:void(0)" class="coinremitterbutton delete_wallet walletdelete_pop" 
                        data-upname="' . $item['coin_symbol'] . '" data-walletid="' . $item['id'] . '">Delete</a>',

                            esc_url($edit_url),
                            esc_url($delete_url)
                        );
                    default:
                        return isset($item[$column_name]) ? $item[$column_name] : '';
                }
            }
        }
    }


    $list_table = new Coinremitter_Wp_List_Table();
    $list_table->prepare_items();
    ?>
    <div class="wrap">
        <div class="container-fluid">
            <div class="row">
                <div class="col-6 coin_title">
                    <h1 class="admin_coin_titel">CoinRemitter Crypto Payment Gateway </h1>
                </div>
                <div class="col-6">
                    <div class="coinremitterlogo">
                        <a href="https://coinremitter.com/" target="_blank"><img title="CRYPTO-CURRENCY PAYMENT GATEWAY" src="<?php echo CR_PLUGIN_PATH . '/images/coinremitter.png'; ?>" border="0"></a>
                    </div>
                </div>
                <div class='wallets-button'>

                    <h1 class="wp-heading-inline">
                        Wallets</h1>

                    <a type="button" class="page-title-action wallet_add_btn" data-bs-toggle="modal" data-bs-target="#exampleModal">
                        <i class="fa fa-plus"></i> Add New Wallet
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php
    $list_table->display();
    ?>
    </div>
    
<?php } ?>