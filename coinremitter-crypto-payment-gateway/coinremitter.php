<?php

if (!defined( 'ABSPATH' ) || !defined( 'COINREMITTER' )) exit; 


final class coinremitterclass
{
	private $options 		= array(); 		// global setting values
	private $hash_url		= "";			// security; save your coinremitter public/private keys sha1 hash in file (db and file)
	private $errors			= array(); 		// global setting errors
	private $payments		= array(); 		// global activated payments (bitcoin, litecoin, etc)
	
	private $page 			= array(); 		// current page url
	private $id 			= 0; 			// current record id
	public $PageID;
        private $updated		= false;		// publish 'record updated' message
	
	private $coin_names     	= array();
	private $coin_chain     	= array();
	private $coin_www       	= array();
	private $languages			= array();
	public $coinList;
	
	public $MinimumInvoiceValue = 0.0001;
	public $ExchangeRate_Multiplier = 1;
	
	public function __construct()
	{
		// --------------------------------------
		// path to images/js/php files. Use in coinremitter payment library class coinremitter.class.php
		DEFINE("COINREMITTER_PHP_FILES_PATH",    plugins_url('/includes/', __FILE__));      // path to directory with files: coinremitter.class.php / coinremitter.callback.php;
		DEFINE("COINREMITTER_IMG_FILES_PATH",    plugins_url('/images/', __FILE__));  // path to directory with coin image files (directory '/images' by default)
		DEFINE("COINREMITTER_JS_FILES_PATH",     plugins_url('/js/', __FILE__));		      // path to directory with js files
		DEFINE("COINREMITTER_CSS_FILES_PATH",     plugins_url('/css/', __FILE__));		      //

		$val2 = "coinremittercoin_user";
		$val3 = substr(strtolower(preg_replace("/[^a-zA-Z]+/", "", base64_encode(home_url('/', 'http')))), -7, 5)."_";
		if (!$val3 || strlen($val3) < 5) $val3 = "cnrm_";
		if (is_admin())
		{
		    $val2 = "coinremittercoin";
		    $val3 = "coinrem_";
		}
		DEFINE("COINREMITTER_COINS_HTMLID",      $val2);	    // coins selection list html id; any value
		DEFINE("COINREMITTER_PREFIX_HTMLID",     $val3);	    // prefix for all html elements; any value

		//$this->coin_names 	= self::coinremitter_coin_names();
		
		// compatible test
		$ver = get_option(COINREMITTER.'version');
		if (!$ver || version_compare($ver, COINREMITTER_VERSION) < 0) $this->coinremitter_upgrade();
		elseif (is_admin()) coinremitter_retest_dir();
		
		if (is_admin()) coinremitter_retest_dir();
		// Current Page, Record ID
		$this->page = (isset($_GET['page']) && sanitize_key($_GET['page'])) ? sanitize_key($_GET['page']) : "";
		$this->id 	= (isset($_GET['id']) && intval($_GET['id'])) ? intval($_GET['id']) : 0;
		$this->updated = (isset($_GET['updated']) && is_string($_GET["updated"]) == "true") ? true : false;
				
		
		
		// Redirect
		if ($this->page == COINREMITTER."contact") { header("Location: ".COINREMITTER_ADMIN.COINREMITTER."#i3"); die; }

	

		// A. General Plugin Settings
		// Admin
		if (is_admin())
		{

			add_action( 'admin_notices', 'coinremitter_sample_admin_notice__success');
			if ($this->errors && $this->page != 'coinremittercredentials') add_action('admin_notices', array(&$this, 'admin_warning_coinremitter'));
			if (!file_exists(COINREMITTER_DIR."files") || !file_exists(COINREMITTER_DIR."images") || !file_exists(COINREMITTER_DIR."lockimg")) add_action('admin_notices', array(&$this, 'admin_warning_reactivate_coinremitter'));
			add_action('admin_menu', 			array(&$this, 'admin_menu_coinremitter'));
			add_action('init', 					array(&$this, 'admin_init_coinremitter'));
			add_action('admin_head', 			array(&$this, 'admin_header_coinremitter'), 15);

			if (strpos($this->page, COINREMITTER) === 0)  add_action("admin_enqueue_scripts", array(&$this, "admin_scripts_coinremitter"));

			if (in_array($this->page, array("coinremitter", "coinremitterpayments","coinremittercredentials"))) add_action('admin_footer_text', array(&$this, 'admin_footer_text_coinremitter'), 15);

			$this->coinremitter_postRecordExists();
		} 
		else 
		{
			add_action("init", 					array(&$this, "front_init_coinremitter"));

			add_action("wp_enqueue_scripts",    array(&$this, "front_scripts_coinremitter"));
			
		}
		
		// Process Callbacks from CoinRemitter.com Payment Server
		add_action('parse_request', array(&$this, 'callback_parse_request_coinremitter'), 1);
		
		
		// Force Login - external plugins
		add_filter('v_forcelogin_whitelist', array(&$this, "coinremitter_forcelogin_whitelist"), 10, 1); // https://wordpress.org/plugins/wp-force-login/
	
	}
	
	public function coinremitter_postRecordExists(){
		
		global $wpdb;
		$tablename = $wpdb->prefix."posts";
		$SQL = "SELECT * FROM $tablename WHERE post_title = 'Crypto Checkout'";
		//echo $SQL;
		$dataVal = $wpdb->get_results($SQL);

		if($wpdb->num_rows > 0) {
			$this->PageID = $dataVal[0]->ID;
		}else{
			$postArr = array(
			  'post_title'    => wp_strip_all_tags('Crypto Checkout'),
			  'post_content'  => '[dis_custom_ord]',
			  'post_status'   => 'publish',
			  'post_author'   => 1,
			  'post_type'   => 'page',
			);
			$this->PageID = wp_insert_post( $postArr);
			//return false;
		}
	}//postRecordExists
	public function admin_scripts_coinremitter()
	{
	    wp_enqueue_style ( 'cr-style-admin',   plugins_url('/css/style.admin.css', __FILE__) );
	    wp_enqueue_style ( 'cr-style-bootstrap-admin',   plugins_url('/css/bootstrapcustom.min.css', __FILE__) );
	    wp_enqueue_style ( 'cr-style',         plugins_url('/css/style.front.css', __FILE__) );
	    wp_enqueue_style ( 'cr-stylecss',       plugins_url('/css/style.css', __FILE__) );
	    wp_enqueue_style ( 'font-awsome-all',   plugins_url('/css/font-awsome-all.css', __FILE__) );
	    wp_enqueue_style ( 'bootstrapmin',  plugins_url('/css/bootstrapmin.css', __FILE__) );
	    wp_enqueue_script ( 'jquery.validate', plugins_url('/js/jquery.validate.js', __FILE__) );
		wp_enqueue_script ( 'cr-customjs',       plugins_url('/js/crypto-custom.js', __FILE__) );

	    return true;
	}

	
	public function front_scripts_coinremitter()
	{
	    wp_enqueue_style ( 'cr-style',         plugins_url('/css/style.front.css', __FILE__) );
	    wp_enqueue_style ( 'cr-cryptocustom',         plugins_url('/css/crypto-custom.css', __FILE__) );
	    wp_enqueue_style ( 'cr-thankyou',         plugins_url('/css/thankyou.css', __FILE__) );
	    wp_enqueue_style ( 'font-awsome-all',   plugins_url('/css/font-awsome-all.css', __FILE__) );
	    
	    add_action( 'wp_enqueue_scripts', 'my_enqueue' );

	    return true;
	}
	public static function my_enqueue() {

    	wp_localize_script( 'ajax-script', 'my_ajax_object',
            array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
	}

    public static function coinremitter_coin_names()
	{
		$ActCoinArr = coinremitter_getActCoins();
		$coinArr = array();
		if(is_array($ActCoinArr) && sizeof($ActCoinArr)){
			foreach($ActCoinArr as $Key => $Val){
				$coinArr[$Key] = $Val['name'];
			}
		}
		return $coinArr;
	}

    public function coinremitter_payments()
	{
		return $this->payments;
	}
        
    private function coinremitter_payment_box_style()
	{
		$opt = $this->options["box_border"];
		
		if (!$opt) $tmp = "";
		elseif ($opt == 1) $tmp = "border-radius:15px;border:1px solid #eee;padding:3px 6px;margin:10px;";
		elseif ($opt == 2) $tmp = "padding:5px;margin:10px;";
		elseif ($opt == 3) $tmp = $this->options["box_style"];
	
		return $tmp;
	}
	
    
	public function page_summary_coinremitter()
	{
		global $wpdb;
		$CoinName =self::coinremitter_coin_names();
		$this->get_settings_coinremitter($CoinName);
		
		if ($this->errors) $message = "<div class='error'>".__('Please fix errors below:', COINREMITTER)."<ul><li>- ".implode("</li><li>- ", $this->errors)."</li></ul></div>";

		else $message = "";
		
        if (!$this->errors && ((isset($_GET['updated']) && sanitize_text_field($_GET['updated']) == "true") || $this->updated))
        {
            $messages = $this->test_coinremitter_connection( $this->updated );
            if (isset($messages["error"])) 
            {
                unset($messages["error"]);
            }
            elseif (!$this->updated) $message .= "<div class='updated'><p><b>ALL CONNECTIONS ARE OK!</b></p><ol><li>".implode("</li><li>", $messages)."</li></ol></div>";
        }
        if(isset($_GET['delete']) && sanitize_text_field($_GET['delete'])){
        	$message = "<div class='updated'><ul><li>Wallet deleted successfully.</li></ul></div>";
        }
        if(isset($_GET['withdraw']) && sanitize_text_field($_GET['withdraw'])){
        	$message = "<div class='updated'><ul><li>Withdraw amount successfully.</li></ul></div>";
        }
        if(isset($_GET['up']) && sanitize_text_field($_GET['up'])){
        	$message = "<div class='updated'><ul><li>Wallet updated successfully.</li></ul></div>";
        }

        if(isset($_GET['new_wallet']) && sanitize_text_field($_GET['new_wallet'])){
        	$message = "<div class='updated'><ul><li>Wallet inserted successfully.</li></ul></div>";
        }
        $tmp  = "<div class='wrap ".COINREMITTER."admin'>";
		$tmp .= $message;
		
		$tmp .= $this->page_title_coinremitter($this->space(1));
		$tmp .= "<div class='postbox crypto-wallets'>";
		$tmp .= "<h2>Wallet</h2>";
		$tmp .= "<div class='update-nag notice notice-warning inline'>For all these wallets, add this <b>".site_url()."/?coinremitter_webhook</b> URL in the Webhook URL field of your Coinremitter wallet's General Settings.   </div>";
		$tmp .= "<div class='inside coinremittersummary'>";
		$tmp .= "<div class='wallets-button'>
                     <button class='wallets-btn OpenPopup'><i class='fa fa-plus'></i> Add Wallet </button>
                  </div>";
		
		$tmp .= "<div class='bootstrapiso'>";
		
        $sql_where = "";
		
		$us_other = "";
		$dt_other = "";
		// 7
		
		// 8
		$all_details = "";
		$dt_last = "";
		$tmp2 = "";

		$tmp .= "<a name='i1'></a>";
		        $tmp .= "<div class='table-responsive' style='overflow-x: hidden;'>";
                $tmp .= "<div class='row'>";

                foreach($CoinName as $ck=>$cv){
                	// $cv = preg_replace('/\s+/', '', $cv);
                    //$payAmount = get_option( COINREMITTER.$cv.'_amount' );
                    $coin_full_name = $cv;
                    $cv = strtolower($ck);
                    $class = 'well-inactive';
                    $href = "#i2";
                    $text = 'Follow CoinRemitter instruction to setup wallet';
                    
                    $usd_text = '-';
                    $title = 'Follow CoinRemitter instruction to setup wallet';
                    $opacity = 'opacity: 0.2;';
                    
                    
                   if(isset($this->options[$cv.'api_key']) && $this->options[$cv.'api_key'] != '' && isset($this->options[$cv.'password']) && $this->options[$cv.'password'] != ''){
                        $class = 'well';
                        $opacity = '';
                        $href = COINREMITTER_ADMIN.COINREMITTER."payments&s=".$ck;
                        $text = $ck.' '.$payAmount;
                        $usd_text = 'Total received USD : $'.$usd_price;
                        $title ='total payment received';
                    }
                    $APIVal = get_option( COINREMITTER.$cv.'api_key' );
                    $PasswordVal = get_option( COINREMITTER.$cv.'password' );
                    $WalletAmt =  get_option( COINREMITTER.$cv.'amount' );
                    $payAmount = number_format($WalletAmt,8);//Wallet balance
                    $EditButton ='';
                    $wallet_name =get_option( COINREMITTER.$cv.'wallet_name' ) ;
                    $CoinType = strtoupper($cv);

                    if($APIVal != '' && $PasswordVal != ''){
                    	$postdata = array('cointype'=>$CoinType,'api_key' => $APIVal, 'password'=>$PasswordVal);
                    	$BalanceRes = coinremitter_getBalanceByCurl($postdata);
                 		if($BalanceRes['flag'] == 0){
                 			$payAmount ='0';
                 			$erroMsg = $BalanceRes['msg'];
                 			update_option(COINREMITTER.$cv.'is_deleted',1);
                 		}else{
                 			$erroMsg = '';
                 			update_option(COINREMITTER.$cv.'is_deleted',0);
                 		}
                 		
                    }
                    if(isset($BalanceRes) && is_array($BalanceRes) && sizeof($BalanceRes) && $BalanceRes['flag'] != 0){
                    	
                    	$payAmount = is_numeric($BalanceRes['data']['balance']) ? number_format($BalanceRes['data']['balance'],8) : 0;//Wallet balance	
                    	$wallet_name = $BalanceRes['data']['wallet_name'];
                    }

                    $imageDir = dirname(__FILE__).'/images';
					$path = $imageDir.'/'.$cv.'.png';
					if(!file_exists($path)){
						$wallet_logo = plugins_url('/images/dollar-ico.png',__FILE__);
					}
					else{
						$wallet_logo = plugins_url('/images/'.$cv.'.png',__FILE__);
					}

                    if(isset($APIVal) && !empty($APIVal)){
                    	$EditButton = '<div class="wallet-usd">
                    			<button class="coinremitterbutton btn btn-danger deleteBtn" style="float:left;font-size: 12px;font-weight: 700;" data-rel='.$cv.' >Remove</button>
                                <button class="wallet-edit EditOpenPopup" data-rel='.$cv.'>Edit</button>
                             </div>';
                        $tmp2 .='<div class="col-lg-3 col-md-6 col-sm-12 col-xs-12 coin_labels" >
			                        <div class="well-inactive">
			                           <div class="row">
			                              <div class="col-md-12">
			                                 <div class="wallet-ico-box clearfix">
			                                    <div class="wallet-ico">
			                                       <img src="'.$wallet_logo.'">
			                                    </div>
			                                    <div class="wallet-coin-name">
			                                    <h2>'.$coin_full_name.' <span id="coin_shot_'.$cv.'">'.$wallet_name.'</span> </h2>
			                                    <div class="wallet-balance">
			                                 		<h4>'.$payAmount.' '.strtoupper($cv).'</h4>	
			                                 	</div>
			                                 	</div>
			                                 </div>
			                                 
			                                 <span style="font-size:12px;color:red;">'.$erroMsg.'</span>
			                                 '.$EditButton.'
			                              </div>
			                           </div>
			                        </div>
			                     </div>';     
					}else{
						$EditButton = '<div class="wallet-usd">&nbsp;</div>';	
					}
                    
                }
		$tmp .= $tmp2."</div>";
                
		
                $tmp .= "<a name='i2'></a>";
                
		$tmp .= "<br><br><br>";
		$tmp .= "<div class='crypto-summary'>";
		$tmp .= "<div class='coinremittertitle wallet-title'>1. ".__('CoinRemitter Instruction', COINREMITTER)."</div>";
		
		$tmp .= "<ul class='coinremitterlist wallet-content'>";
		$tmp .= "<li> ".sprintf(__("Signup on <a href='%s' target='_blank'>CoinRemitter.com</a> - Cheapest Cryptocurrency Payment Gateway.", COINREMITTER), "https://coinremitter.com")."</li>";
		$tmp .= "<li> ".sprintf(__("Create wallet from Wallet page for those all coins which you want to integrate in to your site.", COINREMITTER))."</li>";
		$tmp .= "<li> ".sprintf(__("Set password to your wallet.", COINREMITTER))."</li>";
		$tmp .= "<li> ".sprintf(__("After successfully creation of wallet, you will have APIkey. Use this APIKey to create configure wallet on <b>CoinRemitter Crypto Payment Gateway</b> plugin.  ", COINREMITTER))."</li>";
		$tmp .= "<li> ".sprintf(__("Click Verify and Add test connection and you can check setting with <a href='%s' target='_blank'>CoinRemitter</a>.", COINREMITTER),'https://coinremitter.com')."</li>";
		$tmp .= "<li> ".sprintf(__("To view all transactions record of deposit and withdraw, Check your <a href='%s' target='_blank'>CoinRemitter</a> account.", COINREMITTER),'https://coinremitter.com')."</li>";
		$tmp .= "</ul>";

		
		$tmp .= "<a name='i3'></a>";
		$tmp .= "</p>";
		
		$tmp .= "<div class='coinremittertitle wallet-title'>2. ".__('CoinRemitter Contacts', COINREMITTER)."</div>";

		
		

		$tmp .= "<p>".sprintf(__("If you have question/query, don't hesitate to contact us at %s", COINREMITTER), "<a  target='_blank' href='https://coinremitter.com/support'>https://coinremitter.com/support</a>")."</p>";
		
		
                $tmp .= "<p>". sprintf(__(" <a href='%s' target='_blank'>  CoinRemitter </a> is the secure, reliable and cheapest cryptocurrencies payment gateway ever. Anyone can setup CoinRemitter and accept cryptocurrencies for their services.", COINREMITTER),'https://coinremitter.com')."</p>";
        $tmp .= "</div>";
		$tmp .= "</div>";
                
		$tmp .= "</div>";
		$tmp .= "</div>";
		$DroOpt = "";
		$CurrenyObj = "";
		$iCount = 1;
		foreach($CoinName as $k => $v){
            $coin_full_name = $v;
			$coinObj = strtolower($k);

			$DisNone = 'display:none;';
			$APIVal = get_option( COINREMITTER.$coinObj.'api_key' );
			$PasswordVal = get_option( COINREMITTER.$coinObj.'password' );
			$MinVal = get_option( COINREMITTER.$coinObj.'min_invoice_value');
			$MultiplierVal = get_option( COINREMITTER.$coinObj.'exchange_rate_multiplier');

			if(empty($APIVal) || empty($PasswordVal)){
				$DroOpt .= '<option value="'.$coinObj.'">'.$coin_full_name.'</option>';
			}
			$DivClass = 'div'.$coinObj;
			$CurrenyObj .= "<div class='wallet-form-box add-wallet ".$DivClass." allDiv' style='".$DisNone."' >
                                 <label>API Key <a href='http://coinremitter.com' target='_blank' style='float:right; font-size:12px;' >(get API KEY)</a></label>
                                 <input type='text' autocomplete='off' name='".COINREMITTER.$coinObj."api_key' id='".COINREMITTER.$coinObj."api_key' placeholder='' class='popupapikey' value=".$APIVal.">
                              </div>
                              <div class='wallet-form-box add-wallet ".$DivClass." allDiv'   style='".$DisNone."'  >
                                 <label>Password</label>
                                 <input type='password'  autocomplete='off' name='".COINREMITTER.$coinObj."password' id='".COINREMITTER.$coinObj."password' placeholder=''  class='popuppass' value=".decrypt($PasswordVal)." >
                              </div>
							  <div class='wallet-form-box add-wallet ".$DivClass." allDiv' style='".$DisNone."'>
							 	<label>Minimum Invoice value (In ".$coinObj.")</label>
								 <input type='number' autocomplete='off' name='".COINREMITTER.$coinObj."minInvoiceValue' id='".COINREMITTER.$coinObj."minInvoiceValue' placeholder='' class='popupminInvoiceValue' value=".$MinVal."> 
							  </div>
							  <div class='wallet-form-box add-wallet ".$DivClass." allDiv' style='".$DisNone."'>
							 	<label>Exchange Rate Multiplier</label>
								 <input type='number' autocomplete='off' name='".COINREMITTER.$coinObj."exchangeRateMultiplier' id='".COINREMITTER.$coinObj."exchangeRateMultiplier' placeholder='' class='popupexchangeRateMultiplier' value=".$MultiplierVal."> 
							  </div>";
			$iCount++;                              
		}//foreach
		/* Add Popup */
		$tmp .= "<div id='pum_trigger_add_type_modal' class='pum-modal-background AddWalletPopup' role='dialog' aria-hidden='true' aria-labelledby='pum_trigger_add_type_modal-title' aria-describedby='pum_trigger_add_type_modal-description' style='display:none;'>
               <div class='pum-modal-wrap'>
               <form enctype='multipart/form-data' name='WalletFrm' id='WalletFrm' method='post' action='".COINREMITTER_ADMIN.COINREMITTER."'>
               		
               		<input type='hidden' name='ak_action' value='".COINREMITTER."save_settings' />
               		<input type='hidden' name='currency_type' id='currency_type' />
               		<input type='hidden' name='frm_type' id='frm_type' value='1' />
					<input type='hidden' name='add_new' id='add_new'  />
                     <div class='pum-modal-header'>
                        <span id='pum_trigger_add_type_modal-title' class='pum-modal-title'>Add Wallet</span>
                        <button type='button' class='pum-modal-close fa fa-times ClosePopup' aria-label='Close'></button>
                     </div>
                     <div class='pum-modal-content'>
                    
                        <div class='pum-field-section '>
                           <div class='wallet-form'>
                              <div class='wallet-form-box'>
                                 <label>Wallet</label>
                                 <select name='CoinOpt' id='cointypeid' class='CoinOptList' >
                                 	<option value=''>Select</option>
                                    ".$DroOpt."
                                 </select>
                              </div>".$CurrenyObj."
                              
                           </div>
                        </div>
                     </div>
                     <div class='pum-modal-footer submitbox'>
                      <span class='frmError' style='color: red;'></span>
                        <div class='pum-submit' >
                           <!-- <button class='button button-primary' onclick='javascript:addCryptoCurr(true);' >Verify & Add</button> -->
                           <!-- onclick='this.value=".__("Please wait...", COINREMITTER).";document.getElementById('coinremittersubmitloading').style.display='inline';return true;'  -->
                           <span class='spinner add_spinner' style='float:none'></span>
                           <input type='button' class='".COINREMITTER."button button-primary VerifyBtn' name='submit' value='".__("Verify & Add", COINREMITTER)."' style='background-color:#0085ba;' >
                           <a href='#' style='display:none;' class='hiddenHref'>Click</a>
                        </div>
                     </div>
                 </form>
               </div>
            </div>";

        /* Edit Popup */
        $DroOpt2 = "";
        $CurrenyObj2 = "";
        foreach($CoinName as $k => $v){
 			$coin_full_name = $v;
			$short_name = strtolower($k);


			$APIVal2 = get_option( COINREMITTER.$short_name.'api_key' );
			$PasswordVal2 = get_option( COINREMITTER.$short_name.'password' );
			$MinVal = get_option( COINREMITTER.$short_name.'min_invoice_value');
			$MultiplierVal = get_option( COINREMITTER.$short_name.'exchange_rate_multiplier');
			$coinObj = $k;
			$DroOpt2 .= '<option value='.$short_name.'>'.$coin_full_name.'</option>';
			$DivClass = $short_name;
			$CurrenyObj2 .= "<div class='wallet-form-box div".$DivClass." allDiv' style='display:none;' >
                                 <label>API Key <a href='http://coinremitter.com' target='_blank' style='float:right; font-size:12px;' >(get API KEY)</a></label>
                                 <input type='text' name='".COINREMITTER.$DivClass."api_key' id='".COINREMITTER.$DivClass."api_key' placeholder='' class='popupapikey' value=".$APIVal2." >
                              </div>
                              <div class='wallet-form-box div".$DivClass." allDiv' style='display:none;' >
                                 <label>Password</label>
                                 <input type='password' name='".COINREMITTER.$DivClass."password' id='".COINREMITTER.$DivClass."password' placeholder=''  class='popuppass' value=".decrypt($PasswordVal2)." >
                              </div>
							  <div class='wallet-form-box div".$DivClass." allDiv' style='display:none;'>
							  <label>Minimum Invoice value (In ".$short_name.")</label>
							  <input type='number' autocomplete='off' name='".COINREMITTER.$DivClass."minInvoiceValue' id='".COINREMITTER.$DivClass."minInvoiceValue' placeholder='' class='popupminInvoiceValue' value=".$MinVal."> 
						   </div>
						   <div class='wallet-form-box div".$DivClass." allDiv' style='display:none;'>
							  <label>Exchange Rate Multiplier</label>
							  <input type='number' autocomplete='off' name='".COINREMITTER.$DivClass."exchangeRateMultiplier' id='".COINREMITTER.$DivClass."exchangeRateMultiplier' placeholder='' class='popupexchangeRateMultiplier' value=".$MultiplierVal."> 
						   </div>";
		
		}
        
		$tmp .= "<div id='pum_trigger_add_type_modal2' class='pum-modal-background' role='dialog' aria-hidden='true' aria-labelledby='pum_trigger_add_type_modal-title' aria-describedby='pum_trigger_add_type_modal-description' style='display:none;'>
               <div class='pum-modal-wrap'>
               <form enctype='multipart/form-data' method='post' name='frmupdate' id='frmupdate' accept-charset='utf-8' action='".COINREMITTER_ADMIN.COINREMITTER."'>
               		<input type='hidden' name='ak_action' value='".COINREMITTER."save_settings' />
               		<input type='hidden' name='update_wallet' id='update_wallet' />
               		<input type='hidden' name='delete_wallet' id='delete_wallet' />
               		<input type='hidden' name='currency_type' id='currency_type' />
               		<input type='hidden' id='cointy_in_update' />	
                     <div class='pum-modal-header'>
                        <span id='pum_trigger_add_type_modal-title' class='pum-modal-title'>Update Wallet</span>
                        <button type='button' class='pum-modal-close fa fa-times ClosePopup' aria-label='Close'></button>
                     </div>
                     <div class='pum-modal-content'>
                        <div class='pum-field-section '>
                           <div class='wallet-form'>
                              <div class='wallet-form-box'>
                                 <label class='CurrencyName' style='text-transform: capitalize;'>Wallet</label>
                                 
                                <select name='CoinOpt' class='CoinOpt' style='display:none;' >
                                 	<option value=''>Select</option>
                                    ".$DroOpt2."
                                 </select>

                              </div>".$CurrenyObj2."
                              
                           </div>
                        </div>
                     </div>
                     <div class='pum-modal-footer submitbox'>
                     	<div class='pum-delete' >
                     		
                     	</div>
                     	<div style='display:inline'>
                     		<span class='frmUpdateError' style='color:red;color: red;padding-left: 20px;'></span>
                     	</div>
                        <div class='pum-submit' >
                           <!-- <button class='button button-primary' onclick='javascript:addCryptoCurr(true);' >Verify & Add</button> -->
                           <span class='spinner update_spinner' style='float:none;'></span>
                           <input type='button' class='".COINREMITTER."button button-primary UpdateBtn' name='submit' value='".__("Update", COINREMITTER)."' style='background-color:#0085ba;' >

                           <input type='button' class='".COINREMITTER."button button-primary deleteBtn ClosePopup' data-rol='' name='Cancel' value='".__("Cancel", COINREMITTER)."' style='background-color:#0085ba;' >
                        </div>
                     </div>
                 </form>
               </div>
            </div>";

        $tmp .= "<div id='pum_trigger_add_type_modal3' class='pum-modal-background' role='dialog' aria-hidden='true' aria-labelledby='pum_trigger_add_type_modal-title' aria-describedby='pum_trigger_add_type_modal-description' style='display:none;'>
               <div class='pum-modal-wrap'>
               <form enctype='multipart/form-data' method='post' name='frmwithdraw' id='frmwithdraw' accept-charset='utf-8' action='".COINREMITTER_ADMIN.COINREMITTER."'>
               		<input type='hidden' name='ak_action' value='".COINREMITTER."save_settings' />
               		<input type='hidden' name='remove' id='remove' />
               		<input type='hidden' name='currency_type' id='currency_type' />
                     <div class='pum-modal-header'>
                        <span id='pum_trigger_add_type_modal-title' class='pum-modal-title'>Remove <span class='wallet_coin'></span> Wallet</span>
                        <button type='button' class='pum-modal-close fa fa-times ClosePopup' aria-label='Close'></button>
                     </div>
                     <div class='pum-modal-content'>
                        <div class='pum-field-section '>
                           <div class='wallet-form'>
                           		<h3>Are you sure want to remove  ?</h3>
                              <p>It will remove <span class='wallet_coin'></span> wallet from your database only. It will not remove actual wallet from coinremitter.</p>
                           </div>
                        </div>
                     </div>
                     <div class='pum-modal-footer submitbox'>
                     	<span class='frmWithdrowError' style='color:red;'></span>
                        <div class='pum-submit' >
                        	<span class='spinner delete_spinner' style='float:none'></span>
                           <input type='button' class='".COINREMITTER."button button-primary' name='submit' value='".__("Remove", COINREMITTER)."' style='background-color:#0085ba;' onclick='javascript:deleteWallete(this);' >

                           <input type='button' class='".COINREMITTER."button button-primary deleteBtn ClosePopup' data-rol='' name='Cancel' value='".__("Cancel", COINREMITTER)."' style='background-color:#0085ba;' >
                        </div>
                     </div>
                 </form>
               </div>
            </div>";                       
		echo $tmp;
		return true;

	} 
	
	private function get_settings_coinremitter($CoinName)
	{

		$arr = array("box_type"=>"", "box_theme"=>"", "box_width"=>540, "box_height"=>230, "box_border"=>"", "box_style"=>"", "message_border"=>"", "message_style"=>"", "login_type"=>"", "rec_per_page"=>20, "popup_message"=>__('It is a Paid Download ! Please pay below', COINREMITTER), "file_columns"=>"", "chart_reverse"=>"");
		foreach($arr as $k => $v) $this->options[$k] = "";
		
		foreach($CoinName as $k => $v)
		{
			$this->options[$v."api_key"] = "";
			$this->options[$v."password"] = "";
		}
			
		foreach ($this->options as $key => $value) 
		{
			$key = preg_replace('/\s+/', '', $key);
			$this->options[$key] = get_option(COINREMITTER.$key);

		}
	
		foreach($arr as $k => $v) 
		{
			if (!$this->options[$k]) $this->options[$k] = $v;
		}

		return true;
	}
	
	
	private function post_settings_coinremitter()
	{
		
        foreach ($this->options as $key => $value)
		{
			$this->options[$key] = (isset($_POST[COINREMITTER.$key])) ? stripslashes($_POST[COINREMITTER.$key]) : "";
			if (is_string($this->options[$key])) $this->options[$key] = trim($this->options[$key]);
		}
	
		return true;
	}

	private function check_settings_coinremitter()
	{
		$f = true;
                
		foreach($this->coin_names as $k => $v)
		{

			$public_key  = sanitize_text_field($_POST['coinremitter'.$v.'api_key']);
			$private_key = sanitize_text_field($_POST['coinremitter'.$v.'password']);

			if ($public_key && !$private_key) $this->errors[$v."password"] = ucfirst($v) . ' ' . __('Wallet Password - cannot be empty', COINREMITTER);
			if ($private_key && !$public_key) $this->errors[$v."api_key"]  = ucfirst($v) . ' ' . __('Wallet api key  - cannot be empty', COINREMITTER);


			if ($public_key || $private_key){
					$f = false;
			} 
			if ($public_key && $private_key  && !isset($this->errors[$v."api_key"]) && !isset($this->errors[$v."password"])) $this->payments[$k] = ucfirst($v);
		}
		
		if ($f && !isset($this->errors["md5_error"]))  $this->errors[] = sprintf(__("You must choose at least one payment method. Please enter your CoinRemitter Api Key/Password. <a href='%s'>Instruction here &#187;</a>", COINREMITTER), COINREMITTER_ADMIN.COINREMITTER."#i3");

		// system re-test
		if (!function_exists( 'curl_init' )) 				$this->errors[] = sprintf(__("Error. Please enable <a target='_blank' href='%s'>CURL extension</a> in PHP. <a target='_blank' href='%s'>Read here &#187;</a>", COINREMITTER), "http://php.net/manual/en/book.curl.php", "http://stackoverflow.com/questions/1347146/how-to-enable-curl-in-php-xampp");
		if (!function_exists( 'mysqli_connect' )) 			$this->errors[] = sprintf(__("Error. Please enable <a target='_blank' href='%s'>MySQLi extension</a> in PHP. <a target='_blank' href='%s'>Read here &#187;</a>", COINREMITTER), "http://php.net/manual/en/book.mysqli.php", "http://crybit.com/how-to-enable-mysqli-extension-on-web-server/");
		if (version_compare(phpversion(), '5.4.0', '<')) 	$this->errors[] = sprintf(__("Error. You need PHP 5.4.0 (or greater). Current php version: %s", COINREMITTER), phpversion());

		return true;
	}

	private function save_settings_coinremitter($flag = 1,$amount = 0)
	{
		$arr = array();
		foreach ($this->options as $key => $value){

		    $boxkey = (strpos($key, "api_key") || strpos($key, "password")) ? true : false;
		    if (!$boxkey)
		    {

		        $oldval = get_option(COINREMITTER.$key);
		        if ($boxkey && $oldval != $value) $arr[$key] = array("old_key" => ($oldval ? substr($oldval, 0, -20)."....." : "-empty-"), "new_key" => ($value ? substr($value, 0, -20)."....." : "-empty-"));
		    	if($flag == 1){
		   			if($key == "password"){
		    			update_option(COINREMITTER.$key, encrypt($value));
		   			}else{
		    			update_option(COINREMITTER.$key, $value);
		   			}
		    	}
		    }
		}
		
		if ($arr && $flag == 1) 
		{    
		    wp_mail(get_bloginfo('admin_email'), 'Notification - CoinRermitter Payment Gateway Plugin', 
		    date("r")."\n\nCOinRemitter Crypto Payment Gateway for Wordpress plugin\n\nFollowing crypto wallets keys was changed on your website -\n\n".print_r($arr, true));
		}    
		
		return true;
	}
	
    private function test_coinremitter_connection($one_key = true)
    {
        $messages = array();
        $arr = $arr2 = array();

        foreach ($this->coin_names as $k => $v)
        if (!$one_key || !$arr)
        {
        	
            $public_key 	= $this->options[$v.'api_key'];
            $private_key 	= $this->options[$v.'password'];

            if ($public_key || $private_key) $arr[$v] = array("api_key" => $public_key, "password" => decrypt($private_key),'amount'=>10,'orderID'=>'testing','coinLabel'=>$k);
            if ($private_key) $arr2[] = $private_key; 
        }

        if (!$arr) return array("error" => true, "desc" => 'Please add your CoinRemitter Wallet API Key/Password on this credentials page');
        
        include_once(plugin_dir_path( __FILE__ )."includes/coinremitter.class.php");
        
        foreach($arr as $k => $v)
        {
            $obj = new CoinRemitterCrypto($v);
            $bal = $obj->coinremitter_get_balance($v);
            if($bal['flag'] != 1){
            	$k = $v['coinLabel'];
                $messages[$k] = sprintf(__(ucwords($k). ' - connection failed.please check your api key/password is correct or not.', COINREMITTER));
                $messages["error"] = true;
            }else{
            	$k = $v['coinLabel'];
                $messages[$k] = "<div style='color:green !important'>" . ucwords($k) . " - " . sprintf(__('connected successfully. your balance is %s', COINREMITTER),$bal['data']) . "</div>";
            }
            
        }

        return $messages;
    }
	
	private function check_payment_confirmation_coinremitter($paymentID)
	{
		global $wpdb;
		
		$res = $wpdb->get_row("SELECT * from coinremitter_payments WHERE paymentID = ".intval($paymentID), OBJECT);
		
		if (!$res) return false;
		if ($res->txConfirmed) return true;
		
		$public_key 	= $this->options[$this->coin_names[$res->coinLabel].'api_key'];
		$private_key 	= $this->options[$this->coin_names[$res->coinLabel].'password'];
		if (!$public_key || !$private_key) return false;
		
                $options = array(
				"api_key"  => $public_key,
				"password" => decrypt($private_key),
				"orderID"     => $res->orderID,
				"userID"      => $res->userID,
				"amount"   	  => $res->amount
				);
                
		include_once(plugin_dir_path( __FILE__ )."includes/coinremitter.class.php");
		
		$box = new CoinRemitterCrypto ($options);
		
		return $box->is_paid();
	}
	
	public function  front_init_coinremitter()
	{
		return true;
	}
	
	public function admin_init_coinremitter()
	{
		global $wpdb;
	
		// Actions POST
		if (isset($_POST['ak_action']) && strpos($this->page, COINREMITTER) === 0)
		{
			switch(sanitize_text_field($_POST['ak_action']))
			{
				case COINREMITTER.'save_settings':
	

					$this->post_settings_coinremitter();
					$this->check_settings_coinremitter();


					if (!$this->errors)
					{
						$CoinType = sanitize_text_field($_POST['CoinOpt']);
						$cURL = COINREMITTER_API_URL.$CoinType.'/get-balance';
						$parm = array(
								'api_key'	=>sanitize_text_field($_POST[COINREMITTER.$CoinType.'api_key']),
								'password'	=>sanitize_text_field($_POST[COINREMITTER.$CoinType.'password']),
								);
						$Conn = $this->coinremitter_chkConnection($cURL,$parm);
						$this->save_settings_coinremitter($Conn['flag'],$Conn['data']);
						if (isset($_POST['delete_wallet']) && !empty($_POST['delete_wallet'])){
							$delqstr ='updated=true&delete=true';
						}
						if(isset($_POST['update_wallet']) && !empty($_POST['update_wallet'])){
							$upqstr ='updated=true&up=true';
						}
						if(isset($_POST['add_new']) && !empty($_POST['add_new'])){
							$upqstr ='updated=true&inew=true';
						}
							header('Location: '.COINREMITTER_ADMIN.'coinremitter&'.$delqstr.$upqstr);
						die();
					}
	
					break;
                                default:
						
					break;
			}
		}
		return true;
	}
	
	public function coinremitter_chkConnection($url,$post='')
	{
		$header[] = "Accept: application/json";
		$userAgent = 'CR@'.COINREMITTER_API_VERSION.',wordpress worwoocommerce-wordpress-master@'.COINREMITTER_VERSION;
	    $curl = $url;
	    $body = array(
	        'api_key' => $post['api_key'],
	        'password' => decrypt($post['password']),
        );	

	    $args = array(
	        'method'      => 'POST',
	        'timeout'     => 45,
	        'sslverify'   => false,
	        'user-agent'  => $userAgent,
	        'headers'     => array(
	            'Content-Type'  => 'application/json'
	        ),
	        'body'        => json_encode($body),
	    );
	    $request = wp_remote_post( $curl, $args );
	    if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
	        error_log( print_r( $request, true ) );
	    }
    
	    $response = wp_remote_retrieve_body( $request );
	    return json_decode($response,true);
	}

	public function admin_header_coinremitter()
	{
		global $current_user;
		
		$_administrator = $_editor = false;
		if (is_user_logged_in())
		{
			$_administrator = in_array('administrator', $current_user->roles);
			$_editor 		= in_array('editor', 		$current_user->roles);
		}
		return true;
	}
	
	public function admin_footer_text_coinremitter()
	{
		return sprintf( __( "If you like <strong>CoinRemitter Crypto Payment Gateway</strong> please leave us a %s rating on %s. A huge thank you from CoinRemitter  in advance!", COINREMITTER ), "<a href='https://wordpress.org/support/view/plugin-reviews/coinremitter-payment-gateway?filter=5#postform' target='_blank'>&#9733;&#9733;&#9733;&#9733;&#9733;</a>", "<a href='https://wordpress.org/plugins/coinremitter-crypto-payment-gateway/#reviews' target='_blank'>WordPress.org</a>");
	}
	
	public function admin_warning_coinremitter()
	{
		echo '<div class="updated"><p>'.sprintf(__("<strong>%s Plugin is almost ready to use!</strong> All you need to do is to <a style='text-decoration:underline' href='%s'>update your plugin settings</a>", COINREMITTER), __('CoinRemitter Crypto Payment Gateway', COINREMITTER), COINREMITTER_ADMIN.COINREMITTER."credentials").'</p></div>';
	
		return true;
	}
	
	public function admin_warning_reactivate_coinremitter()
	{
		echo '<div class="error"><p>'.sprintf(__("<strong>Please deactivate %s Plugin,<br>manually set folder %s permission to 0777 and activate it again.</strong><br><br>if you have already done so before, please create three folders below manually and set folder permissions to 0777:<br>- %s<br>- %s<br>- %s", COINREMITTER), __('CoinRemitter Crypto Payment Gateway', COINREMITTER), COINREMITTER_DIR2, COINREMITTER_DIR2."files/", COINREMITTER_DIR2."images/", COINREMITTER_DIR2."lockimg/").'</p></div>';
	
		return true;
	}
	
	public function admin_menu_coinremitter()
	{
		global $submenu;
		
		add_menu_page(
				__("CoinRemitter", COINREMITTER)		
				, __('Coin Remitter', COINREMITTER)
				, COINREMITTER_PERMISSION
				, COINREMITTER
				, array(&$this, 'page_summary_coinremitter'),
				 plugins_url('/images/coinremitter_icon.png', __FILE__),
				'21.777'
		);

		add_submenu_page(
		COINREMITTER
				, __('&#149; Summary', COINREMITTER)
				, __('&#149; Summary', COINREMITTER)
				, COINREMITTER_PERMISSION
				, COINREMITTER
				, array(&$this, 'page_summary_coinremitter')
				
		);
		
		return true;
	}
	
	private function page_title_coinremitter($title='', $type = 1) // 1 - Plugin Name, 2 - Pay-Per-Download,  3 - Pay-Per-View ,  4 - Pay-Per-Membership, 5 - Pay-Per-Product, 20 - Custom
	{
		$text = __('CoinRemitter Crypto Payment Gateway', COINREMITTER);
	
		$tmp = "<div class='".COINREMITTER."logo'><a href='https://coinremitter.com/' target='_blank'><img title='".__('CRYPTO-CURRENCY PAYMENT GATEWAY', COINREMITTER)."' src='".plugins_url('/images/coinremitter.png', __FILE__)."' border='0'></a></div>";
		if ($title) $tmp .= "<div id='icon-options-general' class='icon32'><br></div><h2>".$text.__(($title?$title.' ':''), COINREMITTER)."</h2><br>";
		
		return $tmp;
	}
	
	public function callback_parse_request_coinremitter()
	{
		
		if(in_array(strtolower($this->right($_SERVER["REQUEST_URI"], "/", false)), array("?coinremitter.webhook", "index.php?coinremitter.webhook", "?coinremitter_webhook", "index.php?coinremitter_webhook", "?coinremitter-webhook", "index.php?coinremitter-webhook"))){
			
			ob_clean();
			
			$coinremitter_private_keys = array();
			foreach($this->coin_names as $k => $v)
			{ 
				$key = get_option(COINREMITTER.$v."api_key");
				$val = get_option(COINREMITTER.$v."password");
				if ($val) $coinremitter_private_keys[$k] = ['api_key'=>$key,'password'=>$val];
			}
			
			if ($coinremitter_private_keys) DEFINE("COINREMITTER_PRIVATE_KEYS", json_encode($coinremitter_private_keys));

			include_once(plugin_dir_path( __FILE__ )."includes/coinremitter.class.php");
			include_once(plugin_dir_path( __FILE__ )."includes/coinremitter.webhook.php");
			
			ob_flush();
			
			die;

		}
	
		return true;
	}
        
	
	private function coinremitter_upgrade()
	{
		global $wpdb;

		// TABLE 1 - coinremitter_payments
		// ------------------------------
		if ($wpdb->get_var("SHOW TABLES LIKE 'coinremitter_payments'") != 'coinremitter_payments')
		{


			$table_name ='coinremitter_payments';
			$table_name2 ='coinremitter_order_address';
   			
   			$sql = "CREATE TABLE `coinremitter_payments` (
			  `paymentID` int(11) unsigned NOT NULL AUTO_INCREMENT,
			  `orderID` varchar(50) NOT NULL DEFAULT '',
			  `userID` varchar(50) NOT NULL DEFAULT '',
			  `coinLabel` varchar(20) NOT NULL DEFAULT '',
			  `invoice_id` char(255) NOT NULL DEFAULT '',
			  `base_currency` text NOT NULL DEFAULT '',
			  `payment_history` text NOT NULL DEFAULT '',
			  `total_amount` text NOT NULL DEFAULT '',
			  `paid_amount` text NOT NULL DEFAULT '',
			  `conversion_rate` text NOT NULL DEFAULT '',
			  `description` varchar(255) NOT NULL DEFAULT '',
			  `invoice_url` varchar(255) NOT NULL DEFAULT '',
			  `status` varchar(255) NOT NULL DEFAULT '',
			  `expiry_date` datetime DEFAULT NULL, 
			  `status_code` tinyint(1) NOT NULL DEFAULT '0',
			  `is_status_flag` tinyint(1) NOT NULL DEFAULT '0',		 
			  `txConfirmed` tinyint(1) unsigned NOT NULL DEFAULT '0',
			  `txCheckDate` datetime DEFAULT NULL,
			  `processed` tinyint(1) unsigned NOT NULL DEFAULT '0',
			  `processedDate` datetime DEFAULT NULL,
			  `createdAt` datetime DEFAULT NULL,
			  PRIMARY KEY (`paymentID`),
			  KEY `cruserID` (`userID`),
			  KEY `crorderID` (`orderID`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
	
			$wpdb->query($sql);
		}else{
			$myCustomer = (array)$wpdb->get_row("SELECT * FROM coinremitter_payments");
			//Add column if not present.
			if(!array_key_exists("is_status_flag",$myCustomer)){
				$sql="ALTER TABLE `coinremitter_payments` ADD  `is_status_flag` int(11) NOT NULL DEFAULT '1' ";
				$wpdb->query($sql);
			}
			
			if(!array_key_exists("expiry_date",$myCustomer)){
				$sql="ALTER TABLE `coinremitter_payments` ADD `expiry_date` datetime DEFAULT NULL";
				$wpdb->query($sql);
			}
			if(!array_key_exists("invoice_id",$myCustomer)){
				$sql="ALTER TABLE `coinremitter_payments` ADD `invoice_id` datetime DEFAULT NULL";
				$wpdb->query($sql);
			}
			if(!array_key_exists("base_currency",$myCustomer)){
				$sql="ALTER TABLE `coinremitter_payments` ADD `base_currency` datetime DEFAULT NULL";
				$wpdb->query($sql);
			}
			if(!array_key_exists("payment_history",$myCustomer)){
				$sql="ALTER TABLE `coinremitter_payments` ADD `payment_history` datetime DEFAULT NULL";
				$wpdb->query($sql);
			}
			if(!array_key_exists("total_amount",$myCustomer)){
				$sql="ALTER TABLE `coinremitter_payments` ADD `total_amount` datetime DEFAULT NULL";
				$wpdb->query($sql);
			}
			if(!array_key_exists("paid_amount",$myCustomer)){
				$sql="ALTER TABLE `coinremitter_payments` ADD `paid_amount` datetime DEFAULT NULL";
				$wpdb->query($sql);
			}
			if(!array_key_exists("conversion_rate",$myCustomer)){
				$sql="ALTER TABLE `coinremitter_payments` ADD `conversion_rate` datetime DEFAULT NULL";
				$wpdb->query($sql);
			}
			if(!array_key_exists("description",$myCustomer)){
				$sql="ALTER TABLE `coinremitter_payments` ADD `description` datetime DEFAULT NULL";
				$wpdb->query($sql);
			}
			if(!array_key_exists("invoice_url",$myCustomer)){
				$sql="ALTER TABLE `coinremitter_payments` ADD `invoice_url` datetime DEFAULT NULL";
				$wpdb->query($sql);
			}
			if(!array_key_exists("status",$myCustomer)){
				$sql="ALTER TABLE `coinremitter_payments` ADD `status` datetime DEFAULT NULL";
				$wpdb->query($sql);
			}
			if(!array_key_exists("status_code",$myCustomer)){
				$sql="ALTER TABLE `coinremitter_payments` ADD `status_code` datetime DEFAULT NULL";
				$wpdb->query($sql);
			}
			if(!array_key_exists("status_code",$myCustomer)){
				$sql="ALTER TABLE `coinremitter_payments` ADD `status_code` datetime DEFAULT NULL";
				$wpdb->query($sql);
			}
		}
	
                // TABLE 2 - coinremitter_order_address
		// ------------------------------
		if ($wpdb->get_var("SHOW TABLES LIKE 'coinremitter_order_address'") != 'coinremitter_order_address')
		{
			$sql = "CREATE TABLE `coinremitter_order_address` (
			  `addrID` int(11) unsigned NOT NULL AUTO_INCREMENT,
			  `orderID` varchar(50) NOT NULL DEFAULT '',
			  `userID` varchar(50) NOT NULL DEFAULT '',
			  `invoice_id` varchar(50) NOT NULL DEFAULT '', 
			  `coinLabel` varchar(20) NOT NULL DEFAULT '',
			  `amount` double(20,8) NOT NULL DEFAULT '0.00000000',
			  `amountUSD` double(20,8) NOT NULL DEFAULT '0.00000000',
			  `addr` varchar(255) NOT NULL DEFAULT '',
			  `qr_code` varchar(255) NOT NULL DEFAULT '',
			  `payment_status` tinyint(1) NOT NULL DEFAULT '0',
			  `paymentDate` datetime DEFAULT NULL,
			  `createdAt` datetime DEFAULT NULL,
			  PRIMARY KEY (`addrID`),
			  KEY `crAddruserID` (`userID`),
			  KEY `crAddrorderID` (`orderID`),
			  KEY `crAddramount` (`amount`),
			  KEY `crAddramountUSD` (`amountUSD`),
			  KEY `crAddrcoinLabel` (`coinLabel`),
			  KEY `crAddraddr` (`addr`),
			  KEY `crAddrcreatedAt` (`createdAt`),
			  KEY `crAddrkey1` (`orderID`,`userID`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
	
			$wpdb->query($sql);
		}else{
			$myCustomer = $wpdb->get_row("SELECT * FROM coinremitter_order_address");
			//Add column if not present.
			if(!isset($myCustomer->invoice_id)){
				$sql="ALTER TABLE `coinremitter_order_address` ADD `invoice_id` varchar(255) NOT NULL DEFAULT ''";
				$wpdb->query($sql);
			}
			if(!isset($myCustomer->coinLabel)){
				$sql="ALTER TABLE `coinremitter_order_address` ADD `coinLabel` varchar(255) NOT NULL DEFAULT ''";
				$wpdb->query($sql);
			}
			if(!isset($myCustomer->amount)){
				$sql="ALTER TABLE `coinremitter_order_address` ADD `amount` varchar(255) NOT NULL DEFAULT ''";
				$wpdb->query($sql);
			}
			if(!isset($myCustomer->amountUSD)){
				$sql="ALTER TABLE `coinremitter_order_address` ADD `amountUSD` varchar(255) NOT NULL DEFAULT ''";
				$wpdb->query($sql);
			}
			if(!isset($myCustomer->addr)){
				$sql="ALTER TABLE `coinremitter_order_address` ADD `addr` varchar(255) NOT NULL DEFAULT ''";
				$wpdb->query($sql);
			}
			if(!isset($myCustomer->qr_code)){
				$sql="ALTER TABLE `coinremitter_order_address` ADD `qr_code` varchar(255) NOT NULL DEFAULT ''";
				$wpdb->query($sql);
			}
			if(!isset($myCustomer->payment_status)){
				$sql="ALTER TABLE `coinremitter_order_address` ADD `payment_status` varchar(255) NOT NULL DEFAULT ''";
				$wpdb->query($sql);
			}
			if(!isset($myCustomer->paymentDate)){
				$sql="ALTER TABLE `coinremitter_order_address` ADD `paymentDate` varchar(255) NOT NULL DEFAULT ''";
				$wpdb->query($sql);
			}

		}


		if ($wpdb->get_var("SHOW TABLES LIKE 'coinremitter_webhook'") != 'coinremitter_webhook')
		{
			$sql = "CREATE TABLE `coinremitter_webhook` (
			  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
			  `order_id` varchar(50) NOT NULL DEFAULT '',
			  `addr` varchar(255) NOT NULL DEFAULT '',
			  `transaction_id` varchar(255) NOT NULL DEFAULT '',
			  `tx_id` varchar(255) NOT NULL DEFAULT '',
			  `explorer_url` varchar(255) NOT NULL DEFAULT '',
			  `paid_amount` double(20,8) NOT NULL DEFAULT '0.00000000',
			  `coin` varchar(20) NOT NULL DEFAULT '',
			  `confirmation` varchar(255) NOT NULL DEFAULT '',
			  `paid_date`datetime DEFAULT NULL,
			  `created_date` datetime DEFAULT NULL,
			  `updated_date` datetime DEFAULT NULL,
			  PRIMARY KEY (`id`)

			) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
		
			$wpdb->query($sql);
		}
		
		$coin_names = self::coinremitter_coin_names();

		foreach($coin_names as $k => $v){
			$short_name = $k;
			$wallet_name = get_option(COINREMITTER.strtolower($short_name).'wallet_name');
			if($wallet_name != ''){
				$minimum_invoice_val = get_option(COINREMITTER.strtolower($short_name).'min_invoice_value');
				$multiplier = get_option(COINREMITTER.strtolower($short_name).'exchange_rate_multiplier');
				if($multiplier == ''){
					update_option(COINREMITTER.strtolower($short_name).'exchange_rate_multiplier', maybe_serialize('1'));
				}
				if($minimum_invoice_val == ''){
					update_option(COINREMITTER.strtolower($short_name).'min_invoice_value', maybe_serialize('0'));
				}

			}

		}

			
		// var_dump($coin_names);
		
		$woocommerce_setting_data = [
			 'enabled' => 'yes',
			  'title' => 'Pay Using Cryptocurrency',
			  'description' => 'Secure, anonymous payment with cryptocurrency. <a target="_blank" href="https://en.wikipedia.org/wiki/Cryptocurrency">What is it?</a>',
			  'ostatus' => 'processing',
			  'invoice_expiry' => '30'
		];
		update_option('woocommerce_coinremitterpayments_settings', $woocommerce_setting_data);
		
		// upload dir
		coinremitter_retest_dir();
		
		update_option(COINREMITTER.'version', COINREMITTER_VERSION);
				
		ob_flush();
		
		return true;
	}
	
	public function coinremitter_forcelogin_whitelist ($arr)
	{
		$url = trim(get_site_url(), "/ ") . "/";	
			
		$arr[] = $url . "?coinremitter.callback";
		$arr[] = $url . "index.php?coinremitter.callback";
		$arr[] = $url . "?coinremitter_callback";
		$arr[] = $url . "index.php?coinremitter_callback";
		$arr[] = $url . "?coinremitter-callback";
		$arr[] = $url . "index.php?coinremitter-callback]";
		
		return $arr;
	}
	
	private function sel($val1, $val2)
	{
		$tmp = ((is_array($val1) && in_array($val2, $val1)) || strval($val1) == strval($val2)) ? ' selected="selected"' : '';
	
		return $tmp;
	}
	
	public function left($str, $findme, $firstpos = true)
	{
		$pos = ($firstpos)? mb_stripos($str, $findme) : mb_strripos($str, $findme);
	
		if ($pos === false) return $str;
		else return mb_substr($str, 0, $pos);
	}
	public function right($str, $findme, $firstpos = true)
	{
		$pos = ($firstpos)? mb_stripos($str, $findme) : mb_strripos($str, $findme);
	
		if ($pos === false) return $str;
		else return mb_substr($str, $pos + mb_strlen($findme));
	}
	
	private function space($n=1)
	{
		$tmp = "";
		for ($i=1;$i<=$n;$i++) $tmp .= " &#160; ";
		return $tmp;
	} 
}
// end class coineremitter

function coinremitter_activate()
{
	if (!function_exists( 'mb_stripos' ) || !function_exists( 'mb_strripos' ))  { echo sprintf(__("Error. Please enable <a target='_blank' href='%s'>MBSTRING extension</a> in PHP. <a target='_blank' href='%s'>Read here &#187;</a>", COINREMITTER), "http://php.net/manual/en/book.mbstring.php", "http://www.knowledgebase-script.com/kb/article/how-to-enable-mbstring-in-php-46.html"); die(); }
	if (!function_exists( 'curl_init' )) 										{ echo sprintf(__("Error. Please enable <a target='_blank' href='%s'>CURL extension</a> in PHP. <a target='_blank' href='%s'>Read here &#187;</a>", COINREMITTER), "http://php.net/manual/en/book.curl.php", "http://stackoverflow.com/questions/1347146/how-to-enable-curl-in-php-xampp"); die(); }
	if (!function_exists( 'mysqli_connect' )) 									{ echo sprintf(__("Error. Please enable <a target='_blank' href='%s'>MySQLi extension</a> in PHP. <a target='_blank' href='%s'>Read here &#187;</a>", COINREMITTER), "http://php.net/manual/en/book.mysqli.php", "http://crybit.com/how-to-enable-mysqli-extension-on-web-server/"); die(); }
	if (version_compare(phpversion(), '5.4.0', '<')) 							{ echo sprintf(__("Error. You need PHP 5.4.0 (or greater). Current php version: %s", COINREMITTER), phpversion()); die(); }
        if ( !defined('WOOCOMMERCE_VERSION')){
            echo sprintf(__( "The CoinRemitter Crypto Payment Gateway plugin requires WooCommerce 2.1 or higher to function. Please install <a href='%s'>latest version</a>.", COINREMITTER ), admin_url('plugin-install.php?tab=search&type=term&s=WooCommerce+excelling+eCommerce+WooThemes+Beautifully')) ; 
            die();
        }else if(true === version_compare(WOOCOMMERCE_VERSION, '2.1', '<')){
            echo sprintf(__( "Your WooCommerce version is too old. The CoinRemitter Ctypto Payment Gateway plugin requires WooCommerce 2.1 or higher to function. Please update to <a href='%s'>latest version</a>.", COINREMITTER ), admin_url('plugin-install.php?tab=search&type=term&s=WooCommerce+excelling+eCommerce+WooThemes+Beautifully')) ; 
            die();
        }
}

function coinremitter_deactivate()
{
	update_option(COINREMITTER.'version', '');
}

function coinremitter_retest_dir()
{
	
	$elevel = error_reporting();
	error_reporting(0);
	
	$dir = plugin_dir_path( __FILE__ )."images/dir/";
	
	if (!file_exists(COINREMITTER_DIR."files")) wp_mkdir_p(COINREMITTER_DIR."files");
	if (!file_exists(COINREMITTER_DIR."files/.htaccess")) copy($dir."files/.htaccess", COINREMITTER_DIR."files/.htaccess");
	if (!file_exists(COINREMITTER_DIR."files/index.htm")) copy($dir."files/index.htm", COINREMITTER_DIR."files/index.htm");
	
	
	if (!file_exists(COINREMITTER_DIR."lockimg")) wp_mkdir_p(COINREMITTER_DIR."lockimg");
	if (!file_exists(COINREMITTER_DIR."lockimg/index.htm")) copy($dir."lockimg/index.htm", COINREMITTER_DIR."lockimg/index.htm");
	if (!file_exists(COINREMITTER_DIR."lockimg/image1.jpg")) copy($dir."lockimg/image1.jpg", COINREMITTER_DIR."lockimg/image1.jpg");
	if (!file_exists(COINREMITTER_DIR."lockimg/image1.png")) copy($dir."lockimg/image1.png", COINREMITTER_DIR."lockimg/image1.png");
	if (!file_exists(COINREMITTER_DIR."lockimg/image1b.png")) copy($dir."lockimg/image1b.png", COINREMITTER_DIR."lockimg/image1b.png");
	if (!file_exists(COINREMITTER_DIR."lockimg/image2.jpg")) copy($dir."lockimg/image2.jpg", COINREMITTER_DIR."lockimg/image2.jpg");
	
	if (!file_exists(COINREMITTER_DIR."box")) wp_mkdir_p(COINREMITTER_DIR."box");
	
	if (!file_exists(COINREMITTER_DIR."images"))
	{
		wp_mkdir_p(COINREMITTER_DIR."images");
		
		$files = scandir($dir."images");
		foreach($files as $file)
			if (is_file($dir."images/".$file) && !in_array($file, array(".", "..")))
			copy($dir."images/".$file, COINREMITTER_DIR."images/".$file);
	}
	
	error_reporting($elevel);

	return true;
}

function coinremitter_number_format ($num, $precision = 1,$comma = 0)
{
        
        if($comma){
            $num = number_format($num, $precision,'.','');
        }else{
            $num = number_format($num, $precision);
        }
	
	if (strpos($num, ".")) $num = rtrim(rtrim($num, "0"), ".");
	
	return $num;
}

function coinremitter_checked_image ($val)
{
	$val = ($val) ? "checked" : "unchecked";
	$tmp = "<img alt='".__(ucfirst($val), COINREMITTER)."' src='".plugins_url('/images/'.$val.'.gif', __FILE__)."' border='0'>";
	return $tmp;
}

function coinremitter_userdetails($val, $br = true)
{
	$tmp = $val;
	
	if ($val)
	{
		if (strpos($val, "user_") === 0)    $userID = substr($val, 5);
		elseif (strpos($val, "user") === 0) $userID = substr($val, 4);
		else $userID = $val;	
		
		$userID = intval($userID);
		if ($userID)
		{	
			$obj =  get_userdata($userID);
			if ($obj && $obj->data->user_nicename) $tmp = "user".$userID." - <a href='".admin_url("user-edit.php?user_id=".$userID)."'>".$obj->data->user_nicename . ($br?"<br>":", &#160; ") . $obj->data->user_email . "</a>";
			else $tmp = "user".$userID;
		}	
	}
	
	return $tmp;
}


if( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

function coinremitter_action_links($links, $file)
{
	static $this_plugin;

	if (false === isset($this_plugin) || true === empty($this_plugin)) {
		$this_plugin = COINREMITTER_BASENAME;
	}

	if ($file == $this_plugin) {
		$unrecognised_link = '<a href="'.admin_url('admin.php?page='.COINREMITTER.'payments&s=unrecognised').'">'.__( 'Unrecognised', COINREMITTER ).'</a>';
		$settings_link = '<a href="'.admin_url('admin.php?page='.COINREMITTER).'">'.__( 'Wallet', COINREMITTER ).'</a>';
		array_unshift($links, $settings_link);
	}
	return $links;
}


function coinremitter_get_url( $url, $timeout = 20 )
{
	global $wp_version;
	$userAgent = 'CR@'.COINREMITTER_API_VERSION.',wordpress worwoocommerce-wordpress-master@'.COINREMITTER_VERSION;
	$arrg = array(
			'headers'     => array(
    			'Content-Type'  => 'application/json',
			),
			'timeout'     => $timeout,
			'sslverify' => false,
			'user-agent' => $userAgent,
		);
	
    
    $request = wp_remote_get( $url, $args );
    if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
        error_log( print_r( $request, true ) );
    }
    $response = wp_remote_retrieve_body( $request );
    $httpcode = $request['response']['code'];

    return ($httpcode>=200 && $httpcode<300) ? $response : false;
}





if (!function_exists('coinremitter_wc_gateway_load') && !function_exists('coinremitter_wc_action_links')) // Exit if duplicate
{
    
    DEFINE('COINREMITTERWC', 'coinremitter-woocommerce');
    DEFINE('COINREMITTERWC_2WAY', json_encode(array("BTC", "BCH", "LTC", "ETH")));

    if (!defined('COINREMITTERWC_AFFILIATE_KEY'))
    {
            DEFINE('COINREMITTERWC_AFFILIATE_KEY', 	'coinremitter');
            add_action( 'plugins_loaded', 		'coinremitter_wc_gateway_load', 20 );
            add_filter( 'plugin_action_links', 	'coinremitter_wc_action_links', 10, 2 );
            
    }

    function coinremitter_wc_action_links($links, $file)
    {
            static $this_plugin;

            if (!class_exists('WC_Payment_Gateway')) return $links;

            if (false === isset($this_plugin) || true === empty($this_plugin)) {
                    $this_plugin =dirname( plugin_basename( __FILE__ ) ).'/coinremitter_wordpress.php';
                    
            }
            
            if ($file == $this_plugin) {
                  $settings_link = '<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_gateway_coinremitter').'">'.__( 'Settings', COINREMITTERWC ).'</a>';
                    array_unshift($links, $settings_link);

            }

            return $links;
    }

    function coinremitter_wc_gateway_load(){
            $priority = 10;

            $filters = [
                'woocommerce_get_price_html',
                'woocommerce_get_variation_prices_hash',
            ];
            if (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')){
                $filters[] = 'woocommerce_get_sale_price';
                $filters[] = 'woocommerce_get_regular_price';
                $filters[] = 'woocommerce_get_price';
            }else{
                $filters[] = 'woocommerce_product_get_sale_price';
                $filters[] = 'woocommerce_product_get_regular_price';
                $filters[] = 'woocommerce_product_get_price';
                $filters[] = 'woocommerce_product_variation_get_sale_price';
                $filters[] = 'woocommerce_product_variation_get_regular_price';
                $filters[] = 'woocommerce_product_variation_get_price';
                $filters[] = 'woocommerce_variation_prices_sale_price';
                $filters[] = 'woocommerce_variation_prices_regular_price';
                $filters[] = 'woocommerce_variation_prices_price';
            }
            
            foreach($filters as $v){
                remove_all_filters($v);
            }

        // WooCommerce required
	if (!class_exists('WC_Payment_Gateway') || class_exists('WC_Gateway_CoinRemitter')) return;
	
	add_filter( 'woocommerce_payment_gateways', 		'coinremitter_wc_gateway_add' );
	add_action( 'woocommerce_view_order', 				'coinremitter_wc_payment_history', $priority, 1 );

	add_action( 'woocommerce_email_after_order_table', 	'coinremitter_wc_payment_link', 15, 2 );
	
	add_filter( 'woocommerce_currency_symbol', 			'coinremitter_wc_currency_symbol', $priority, 2);
	add_filter( 'wc_get_price_decimals',                'coinremitter_wc_currency_decimals', $priority, 1 );
	/* Custom Code MM 17102019*/
	add_action('woocommerce_after_order_notes', 'custom_checkout_field');

	if(isset($_POST['currency_type']) && $_POST['currency_type'] != "" ){
		add_filter('woocommerce_get_return_url','coinremitter_override_return_url',10,3);

	}
	
	add_action('woocommerce_checkout_process', 'customised_checkout_field_process');
	add_action('woocommerce_checkout_update_order_meta', 'custom_checkout_field_update_order_meta');
	remove_action( 'woocommerce_order_details_after_order_table', 'woocommerce_order_again_button' );

	add_action( 'after_woocommerce_pay', 'show_order_details', 10);
	
	add_action( 'woocommerce_order_details_after_order_table', 'nolo_custom_field_display_cust_order_meta', 10, 1 );




	function cloudways_show_email_order_meta( $order, $sent_to_admin, $plain_text ) {
	    $cloudways_text_field = get_post_meta( $order->id, '_cloudways_text_field', true );
	    $cloudways_dropdown = get_post_meta( $order->id, '_cloudways_dropdown', true );
	    if( $plain_text ){
	        echo 'The value for some field is ' . $cloudways_text_field . ' while the value of another field is ' . $cloudways_dropdown;
	    } else {
	        echo '<p>The value for <strong>input text field</strong> is ' . $cloudways_text_field. ' while the value of <strong>drop down</strong> is ' . $cloudways_dropdown . '</p>';
	    }
	}
	add_action('woocommerce_email_customer_details', 'cloudways_show_email_order_meta', 30, 3 );


	function coinremitter_coin_to_usd($amount,$coin){
	    $CoinPrice = coinremitter_getActCoins();
		$coinprice = $CoinPrice[$coin]['price'];
	    $amount_in_usd = $amount * $coinprice;
	    return number_format((float)$amount_in_usd, 2, '.', '');
	}


	function check_and_update_payment($param,$order_id){
		global $wpdb;
		$dt  = gmdate('Y-m-d H:i:s');
		$param['status_code'] = 1;
		if($param['status_code'] == COINREMITTER_INV_OVER_PAID || $param['status_code'] == 
			COINREMITTER_INV_PAID){
			$order_data = "SELECT * FROM coinremitter_order_address WHERE orderID = 'coinremitterwc.order".$order_id."'";
			$order_details = $wpdb->get_results($order_data);
			$user_id = $order_details[0]->userID;
			$order_details_status = $order_details[0]->payment_status;
			$crp_amount = $order_details[0]->amount;
			$order = wc_get_order($order_id);
			$invoiceurl = $param['url'];
			$invoice_id = $param['invoice_id'];
    		$coin = $param['coin'];
    		$address = $param['address'];
    		$base_currency =$param['base_currency'];
    		$payment_history = json_encode($param['payment_history']);
    		$conversion_rate =json_encode($param['conversion_rate']);
    		$paid_amount = json_encode($param['paid_amount']);
    		$total_amount = json_encode($param['total_amount']);
   			$desc = $param['description'];
    		$status = $param['status'];
    		$status_code = $param['status_code'];
    		$dt = gmdate('Y-m-d H:i:s');

			$invoice_note = "Invoice  <a href='".$invoiceurl."' target='_blank'>".$invoice_id."</a>".$status;
        	$order->add_order_note($invoice_note);
			if($param['status_code'] == COINREMITTER_INV_OVER_PAID || $param['status_code'] == COINREMITTER_INV_PAID){        	
				$sql = "INSERT INTO coinremitter_payments ( orderID, userID, coinLabel,base_currency,payment_history,total_amount,invoice_id, paid_amount, conversion_rate, description, status,invoice_url,status_code, txCheckDate, createdAt)
                                VALUES ('coinremitterwc.order".$order_id."', '".$user_id."', '".$coin."','".$base_currency."','".$payment_history."', '".$total_amount."', '".$invoice_id."', '".$paid_amount."','".$conversion_rate."', '".$desc."', '".$status."', '".$url."', '".$status_code."', '$dt', '$dt')";
            	$paymentID =$wpdb->get_results($sql);
            
				$up_payment_query = "UPDATE coinremitter_order_address SET payment_status = '".$status_code."' , paymentDate = '".$dt."' where addr = '".$param['address']."'";
				$insert = $wpdb->get_results($up_payment_query);
	          	$option_data = get_option('woocommerce_coinremitterpayments_settings');
	         
	            $ostatus = $option_data['ostatus'];    
	        	
	            $order = new WC_Order($order_id);
        		$order->update_status('wc-'.$ostatus);
	            add_post_meta( $order_id, '_order_crypto_price', $crp_amount);
	            add_post_meta( $order_id, '_order_crypto_coin', $callback_coin);
			}else{
				die('not Paid');
			}
			
		}
	}
	function nolo_custom_field_display_cust_order_meta($d)
	{
		global $wpdb;
		$dd = json_decode($d,true);
		if(isset($_GET['order-received'])){
			$order_id = sanitize_text_field($_GET['order-received']);	
		}else{
			$order_id = $d->get_order_number();	 
		}
		
		$method = get_post_meta( $order_id, '_payment_method_title', true );
		$option_data = get_option('woocommerce_coinremitterpayments_settings');

		if($method != $option_data['title']){
			return '';
		}
		
		$order_data = "SELECT * FROM coinremitter_payments WHERE orderID = 'coinremitterwc.order".$order_id."'";
		
		$order_details = $wpdb->get_results($order_data);

		if($order_details[0]->invoice_id == "" )
		{
					
			$query = "SELECT * FROM coinremitter_order_address WHERE orderID = 'coinremitterwc.order".$order_id."'";
		
			$get_order_data = $wpdb->get_results($query);
			if(count($get_order_data) < 1)
			{
				return "";
			}
			$coin_type = $get_order_data[0]->coinLabel;
			$address = $get_order_data[0]->addr;
			$payments_date =  $get_order_data[0]->createdAt;
			$expiry_date=$order_details[0]->expiry_date;
			$order = wc_get_order( $order_id );
	        $webhook_data = "SELECT * FROM coinremitter_webhook WHERE `addr` = '".$address."' ";
			$web_hook = $wpdb->get_results($webhook_data);
			if($expiry_date != "" ){
				$diff=strtotime($expiry_date)- strtotime(gmdate('Y-m-d H:i:s'));
			}
			if(count($web_hook) == 0  && isset($diff) && $diff <= 0 ){
	        	$order->update_status('cancelled');
				$order_status_code = COINREMITTER_INV_EXPIRED;
				$order_status='Expired';

				$u_order_data="UPDATE `coinremitter_order_address` SET `payment_status` = '$order_status_code' WHERE `addr` = '$address' ";
				$update_order_data = $wpdb->get_results($u_order_data);

				$payment_data="UPDATE `coinremitter_payments` SET `status` = '$order_status',`status_code` = '$order_status_code' WHERE `orderID` = '".$get_order_data[0]->orderID."' ";
				$update_payment_data = $wpdb->get_results($payment_data);
			}

			$order_data = "SELECT * FROM coinremitter_payments WHERE orderID = 'coinremitterwc.order".$order_id."'";

			$order_details = $wpdb->get_results($order_data);
			$status=$order_details[0]->status_code;
			$status_flag=$order_details[0]->is_status_flag;

			if($status == COINREMITTER_INV_PENDING || $status == COINREMITTER_INV_UNDER_PAID )
			{
				$param=[
					'coin'=> $coin_type,
					'address'=>$address,
				];
				$transaction_data=coinremitter_geTransaction_by_address($param);
				$paid_amount=0;
				if(isset($transaction_data['flag']) && $transaction_data['flag'] == 1)
				{
					$transaction=$transaction_data['data'];
					foreach ($transaction as $t) {
						
						if( $t['type'] == 'receive' )
						{
							$id=$t['id'];
							$date=gmdate('Y-m-d H:i:s');
							$txid=$t['txid'];
							$amount=$t['amount'];
							$explorer_url=$t['explorer_url'];
							$confirmations=$t['confirmations'];
							if($confirmations >= 3)
							{
								$paid_amount += $amount;
							}
							
							$query="SELECT * FROM coinremitter_webhook WHERE `transaction_id` = '".$id."' ";
							$transtion_entry = $wpdb->get_results($query);
							if(count($transtion_entry) > 0 )
							{
						
								if($confirmations < 3){
				                    $confirmations_order=$confirmations;
				                }else{
				                    $confirmations_order=3;
				                }

								$sql = "UPDATE `coinremitter_webhook` SET 
								`confirmation`='$confirmations_order' , `updated_date`='$date' WHERE `transaction_id`= '".$id."'";
								
								$update = $wpdb->get_results($sql);
							}else{
								
								$sql = "INSERT INTO coinremitter_webhook ( order_id, transaction_id,addr, tx_id,explorer_url,paid_amount,coin,confirmation,paid_date,created_date,updated_date)
	                                VALUES ('".$order_details[0]->orderID."', '".$id."', '".$address."', '".$txid."','".$explorer_url."','".$amount."', '".$coin_type."','".$confirmations."','".$date."','".$date."','".$date."')";
	                            
	                            $inserted = $wpdb->get_results($sql);
							}
						}
						
					}
					$total_amount=$get_order_data[0]->amountUSD;
					$total_paidamount=number_format($paid_amount,8);
					$order_status="";
		        	$order_status_code="";
		        	$option_data = get_option('woocommerce_coinremitterpayments_settings');
	            	$ostatus = $option_data['ostatus'];  
				        if($total_paidamount == 0){
				        	$order_status="Pending";
				        	$order->update_status('pending');
				            $order_status_code=COINREMITTER_INV_PENDING;
				        }else if($total_amount > $total_paidamount ){
				            $order_status="Under paid";
				        	$order->update_status('pending');
				            $order_status_code=COINREMITTER_INV_UNDER_PAID;
				        }else if($total_amount == $total_paidamount){
				            $order_status="Paid";
				            if($status_flag == 0){
								$order->update_status('wc-'.$ostatus);
								$status_flag = 1;
				            }else{
				            	$status_flag=1;
				            }
				        	$order_status_code=COINREMITTER_INV_PAID;
				        }else if($total_amount < $total_paidamount){
				        	if($status_flag == 0){
								$order->update_status('wc-'.$ostatus);
								$status_flag = 1;
				            }else{
				            	$status_flag = 1;
				            }
				        	$order_status="Over paid";
				            $order_status_code=COINREMITTER_INV_OVER_PAID;
				        }
			        $u_order_data="UPDATE `coinremitter_order_address` SET `payment_status` = '$order_status_code' WHERE `addr` = '$address' ";
					$update_order_data = $wpdb->get_results($u_order_data);

					$payment_data="UPDATE `coinremitter_payments` SET `status` = '$order_status',`status_code` = '$order_status_code',`is_status_flag`='$status_flag' ,`paid_amount`='".$total_paidamount."' WHERE `orderID` = '".$get_order_data[0]->orderID."' ";
				
					$update_payment_data = $wpdb->get_results($payment_data);
				}
			}
			$payment_query = "SELECT * FROM coinremitter_payments WHERE orderID = 'coinremitterwc.order".$order_id."' LIMIT 1";

			$payment_details = $wpdb->get_results($payment_query);
			$link =$payment_details[0]->invoice_url;
			$status_code = $payment_details[0]->status_code;
			$status = ($payment_details[0]->status == "" ? 'Pending' : $payment_details[0]->status );
			$payment_webhook = "SELECT * FROM coinremitter_webhook WHERE addr = '".$address."' ";
			$webhook_details = $wpdb->get_results($payment_webhook);
			$total_amount=$payment_details[0]->total_amount;

			$paid_amount=($payment_details[0]->paid_amount == "" ? 0 : $payment_details[0]->paid_amount );
			
			$pending_amount=$total_amount - $paid_amount;
			if($pending_amount < 0)
			{
				$pending_amount= 0	;
			}
			$temp2="";
			if($webhook_details){
					$temp2.='<tr>
								<th scope="row">Transaction ID :</th>
							<td>';
					foreach ($webhook_details as $web) {
						$temp2.='<span class="woocommerce-Price-amount amount">
										<a title="'.__('Transaction Details', COINREMITTER).' - '.$web->tx_id.'"  href="'.$web->explorer_url.'" target="_blank" class="woocommerce-Price-currencySymbol">'.substr($web->tx_id,0,20).'...</a>
									</span><br>';     
					}
					$temp2.='</td></tr>';
				}
		

		
			if($status_code == COINREMITTER_INV_UNDER_PAID || $status_code == COINREMITTER_INV_PENDING && $status != 'Paid' ){
				$get_status = $status.' <a href="'.$link.'" class="button" href="abc" style="padding:6px;text-decoration: none;margin-left:20px">Pay</a>';
			}else{
				$get_status = $status;
			}
		
			$div = '<table class="woocommerce-table woocommerce-table--order-details shop_table order_details" style="word-break:break-all;">
						<thead>
							<tr>
								<th colspan="2">'.$method.'</th>
							</tr>
						</thead>
						<tfoot>
							<tr>
								<th scope="row">Total Amount :</th>
								<td>'.number_format($total_amount,8)." ".$coin_type.'</td>
							</tr>
							<tr>
								<th scope="row">Paid Amount :</th>
								<td>'.number_format($paid_amount,8)." ".$coin_type.'</td>
							</tr>
							<tr>
								<th scope="row">Pending Amount :</th>
								<td>'.number_format($pending_amount,8)." ".$coin_type.'</td>
							</tr>
								'.$temp2.'
							<tr>
								<th scope="row">Address : </th>
								<td>'.$address.'</td>
							</tr>
							<tr>
								<th scope="row">Date :</th>
								<td>'.$payments_date.' (UTC)</td>
							</tr>
							<tr>
								<th scope="row">Status :</th>
								<td>'.$get_status.'</td>
							</tr>
						</tfoot>
					</table>';
			echo $div;
		}else{

		$order_detail =wc_get_order( $order_id );
		$o_status = $order_detail->get_status();

		$query = "SELECT * FROM coinremitter_order_address WHERE orderID = 'coinremitterwc.order".$order_id."'";
		$get_order_data = $wpdb->get_results($query);
		$payment_status = $get_order_data[0]->payment_status;
		$param['coin'] = $get_order_data[0]->coinLabel;
		$param['invoice_id'] = $get_order_data[0]->invoice_id;
		$coin_type = $get_order_data[0]->coinLabel;
		$address = $get_order_data[0]->addr;
		$payments_date =  $get_order_data[0]->createdAt;
		$payment_query = "SELECT * FROM coinremitter_payments WHERE orderID = 'coinremitterwc.order".$order_id."' LIMIT 1";
		$invoice = coinremitter_getInvoiceData($param);
		$link ='';
		if($invoice['flag'] == 1){
			$link = $invoice['data']['url'];
    	}
		$payment_details = $wpdb->get_results($payment_query);
		if($payment_details){
			$payment_history = json_decode($payment_details[0]->payment_history,true);
			$paid_amount = json_decode($payment_details[0]->paid_amount,true);
			if(empty($paid_amount)){
				$paid_amount[$coin_type] ='0.00000';
				$paid_amount['USD'] = '0.00';
			}
			$total_amount = json_decode($payment_details[0]->total_amount,true);
			if($o_status =='cancelled'){
				$status = 'Cancelled';
			}else{
				$status = $payment_details[0]->status;
			}
		}else{
			$payment_history= [];
			$paid_amount[$coin_type] ='0.00000';
			$paid_amount['USD'] = '0.00';
			$total_amount[$coin_type] =$get_order_data[0]->amount;
			$total_amount['USD'] =$get_order_data[0]->amountUSD;
			$status = 'Pending';
			if($o_status =='cancelled'){
				$status = 'Cancelled';
			}
			
		}
		$temp2='';
		if($payment_history){
			$temp2.='<tr>
							<th scope="row">Transaction ID:</th>
							<td>';
			foreach ($payment_history as $key => $value) {
				// print_r($value['txid']);
				// exit();
				$temp2.='<span class="woocommerce-Price-amount amount">
								<a title="'.__('Transaction Details', COINREMITTER).' - '.$value['txid'].'"  href="'.$value['explorer_url'].'" target="_blank" class="woocommerce-Price-currencySymbol">'.substr($value['txid'],0,20).'...</a>
							</span><br>';     
			}
			$temp2.='</td></tr>';
		}

		
		if($status == 'Pending'){
			$get_status = $status.' <a href="'.$link.'" class="button" href="abc" style="padding:6px;text-decoration: none;margin-left:20px">Pay</a>';
		}else{
			$get_status = $status;
		}
		
		$div = '<table class="woocommerce-table woocommerce-table--order-details shop_table order_details" style="word-break:break-all;">
					<thead>
						<tr>
							<th colspan="2">'.$method.'</th>
						</tr>
					</thead>
					<tfoot>
						<tr>
							<th scope="row">Total Amount :</th>
							<td>'.$total_amount[$coin_type].$coin_type.'  ( ~ $'.number_format((float)$total_amount['USD'], 2, '.', '').' USD )</td>
						</tr>
						<tr>
							<th scope="row">Paid Amount :</th>
							<td>'.$paid_amount[$coin_type].$coin_type.'  ( ~ $'.number_format((float)$paid_amount['USD'], 2, '.', '').' USD)</td>
						</tr>
							'.$temp2.'
						</tr>
						<tr>
							<th scope="row">Address</th>
							<td>'.$address.'</td>
						</tr>
						<tr>
							<th scope="row">Date</th>
							<td>'.$payments_date.' (UTC)</td>
						</tr>
						<tr>
							<th scope="row">Status</th>
							<td>'.$get_status.'</td>
						</tr>
					</tfoot>
				</table>';
			echo $div;
		}
	}
	
	function show_order_details(){
		global $wpdb;
		$order_key = sanitize_text_field($_GET['key']);	
		
		$order_key=$_GET['key'];
		
		$order_id = wc_get_order_id_by_order_key($order_key);


		// $order_detail =wc_get_order( $order_id );

		$method = get_post_meta( $order_id, '_payment_method_title', true );
		$option_data = get_option('woocommerce_coinremitterpayments_settings');

		if($method != $option_data['title']){
			return '';
		}
		$order = wc_get_order( $order_id );
		
		$items = $order->get_items();
		
		$symbol= get_woocommerce_currency_symbol();

		$order_items='';
		foreach ( $items as $item ) {
			$product        = $item->get_product(); // Get the WC_Product object
			$order_items.=  '<tr>
		                  		<td style="width: 200px;">
		                         	<div class="cr-plugin-cart-img">
		                            	'.$product->get_image().'
		                         	</div>
		                         	<div class="cr-plugin-cart-des">
		                            	<p>'.$item->get_name().'</p>
		                         	</div>
		                  		</td>
		                      	<td style="text-align: center;">
		                         	<span>'.$item->get_quantity().'</span>
		                      	</td>
		                      	<td style="text-align: right;">
		                         	<span>'.$symbol.' '.number_format($product->get_price(),2).'</span>
		                      	</td>
		               		</tr>';


		}
		$query = "SELECT * FROM coinremitter_order_address  WHERE orderID = 'coinremitterwc.order".$order_id."'";
		$get_order_data = $wpdb->get_results($query);
		
		if($get_order_data[0]->payment_status== COINREMITTER_INV_PAID || $get_order_data[0]->payment_status== COINREMITTER_INV_OVER_PAID )
		{
			$url= site_url("index.php/checkout/?order-received=".$order_id."&key=".$order_key."");
			wp_redirect( $url );
		}

		else if($get_order_data[0]->payment_status == COINREMITTER_INV_EXPIRED || $order->get_status() == 'cancelled'){
			$url= $order->get_cancel_order_url();
			wp_redirect( $url );
		}
		
		$payment_query = "SELECT * FROM coinremitter_payments WHERE orderID = 'coinremitterwc.order".$order_id."' LIMIT 1";
		$payment_details = $wpdb->get_results($payment_query);
		
		if($payment_details[0]->paid_amount == "")
		{
				$order_paid_amount=0;
		}else{
			$order_paid_amount=$payment_details[0]->paid_amount;
		}

		$padding_amount=$payment_details[0]->total_amount - $order_paid_amount;
		
		$div='<style>#order_review{
    				display:none !important;
				}</style>
				<div class="cr-plugin-copy">
				<p>Copied</p>
				</div>
				<input type="hidden" id="base_url" value="'.site_url().'"><input type="hidden" id="order_id" value="'.$order_id.'"><input type="hidden" id="order_key" value="'.$order_key.'">
				<main id="site-content" role="main">
         		<article class="post-8 page type-page status-publish hentry" id="post-8">
            		<div class="post-inner thin ">
					<div style="font-size:41px;">Order Invoice #'.$order_id.' </div>
               			<div class="entry-content">
                  			<div class="cr-plugin-main-box clearfix">
                     			<div class="cr-plugin-left">
                        			<div class="cr-plugin-shipping cr-plugin-shadow cr-plugin-mr-top clearfix">
                           				<div class="cr-plugin-shipping-address">
                              			<h3 class="cr-plugin-title">Billing Address</h3>
                              			<p>'.$order->get_formatted_billing_address().'</p>
                           				</div>
                           				<div class="cr-plugin-billing-address">
                              				<h3 class="cr-plugin-title">Shipping Address</h3>
                              				<p>'.$order->get_formatted_shipping_address().'</p>
                           				</div>
                        			</div>
                        			<div class="cr-plugin-cart-summary cr-plugin-shadow cr-plugin-mr-top">
                           				<h3 class="cr-plugin-title">Cart Summary</h3>
                           			<div class="cr-plugin-cart-table">
                              		<div class="cr-plugin-cart-table-box">
                                 	<table>
                                    	<thead>
                                       		<tr>
                                          		<th>Product Info</th>
                                          		<th style="text-align: center;">Quantity</th>
                                          		<th style="text-align: right;">Price</th>
                                       		</tr>
                                    	</thead>
                                   	 	<tbody>
                                       		'.$order_items.'
                                    </tbody>
                                 </table>
                              </div>
                           </div>
                           <div class="cr-plugin-payment-detail">
                              <h3 class="cr-plugin-title">Payment Details</h3>
                              <ul>
                                 <li>Total <span>'.$symbol.' '.number_format($order->get_subtotal(),2).'</span></li>
                                 <li>Shipping  Fee <span>'.$symbol.' '.number_format($order->get_shipping_total(),2).'</span></li>
                                 <li>Total Taxes (2%) <span>'.$symbol.' '.number_format($order->get_shipping_tax(),2).'</span></li>
                              </ul>
                              <ul class="cr-plugin-payment-grand">
                                 <li>Grand Total <span>'.$symbol.' '.$order->get_total().'</span></li>
                              </ul>
                           </div>
                        </div>
                     </div>
                     <div class="cr-plugin-right">
                        <div class="cr-plugin-billing-main cr-plugin-shadow">
                           <h3 class="cr-plugin-title">Billing Address</h3>
						 	<div class="cr-plugin-timer" id="timer_status"></div>
							<div class="cr-plugin-billing-box">
                              <div class="cr-plugin-billing-code addr_copy" style="cursor: pointer;" >
                                 <img src="'.$get_order_data[0]->qr_code.'" align="">
                              </div>
                              <div class="cr-plugin-billing-amount">
                                 <ul>
                                    <li>
										<span>Address</span>
                                       <p class="addr_copy" style="cursor: pointer;" data-copy-detail="'.$get_order_data[0]->addr.'"><b id="order_addr" >'.$get_order_data[0]->addr.'</b> <i id="order_copy" class="dashicons-before dashicons-admin-page"></i></p>
                                    </li>
                                    <li id="order_amount" data-copy-detail="'.number_format($get_order_data[0]->amountUSD,8).'">
                                       <span>Amount</span>
                                       <p style="cursor: pointer;">'.number_format($get_order_data[0]->amountUSD,8).' '.$get_order_data[0]->coinLabel.'</p>
                                    </li>
                                 </ul>
                              </div>
                           </div>
                        </div>
                      <div class="cr-plugin-payment-history cr-plugin-shadow cr-plugin-mr-top">
                           <h3 class="cr-plugin-title">Payment  History</h3>
                           	<div class="cr-plugin-timer" id="timer_status_payment">
                           	</div>
                           <div class="cr-plugin-history-list" id="Webhook_history">
                           <input type="hidden" id="expiry_time" value="">
								<div class="cr-plugin-history-box">
                                 <div class="cr-plugin-history" style="text-align: center;">
                                 	<span>No payment history found</span>
                                </div>
                            </div>                          
                           </div>
                           <div class="cr-plugin-history-footer">
                              <ul class="clearfix">
                                 <li>Paid <span><span id="paid_amount">'.number_format($order_paid_amount,8).'</span><span> '.$payment_details[0]->coinLabel.'</span></span></li>
                                 <li>Pending <span><span id="padding_amount">'.number_format($padding_amount,8).'</span><span> '.$payment_details[0]->coinLabel.'</span></span></li>
                              </ul>
                           </div>
                        </div>
                        <div class="cr-plugin-brand">
							<span style="">Secured by</span>
							<a href="https://coinremitter.com" target="_blank">
							<img src="'.plugins_url('/images/coinremitter_logo.png', __FILE__).'">
							</a>
						</div>
                     </div>
                      	
                  </div>

               </div>
           </div>
        </article>
      </main>';
		echo $div;
		
	}

	if (!current_user_can('manage_options'))
	{
	    if (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<'))
	    { // WooCommerce 2.x+
		  add_filter( 'woocommerce_get_sale_price', 	'coinremitter_wc_crypto_price', $priority, 2 );
		  add_filter( 'woocommerce_get_regular_price', 	'coinremitter_wc_crypto_price', $priority, 2 );
		  add_filter( 'woocommerce_get_price', 			'coinremitter_wc_crypto_price', $priority, 2 );
	    }
	    else
	    {  // WooCommerce 3.x+
	        add_filter( 'woocommerce_product_get_sale_price',              'coinremitter_wc_crypto_price', $priority, 2 );
	        add_filter( 'woocommerce_product_get_regular_price',           'coinremitter_wc_crypto_price', $priority, 2 );
	        add_filter( 'woocommerce_product_get_price', 			       'coinremitter_wc_crypto_price', $priority, 2 );

	        add_filter( 'woocommerce_product_variation_get_sale_price',    'coinremitter_wc_crypto_price', $priority, 2 );
	        add_filter( 'woocommerce_product_variation_get_regular_price', 'coinremitter_wc_crypto_price', $priority, 2 );
	        add_filter( 'woocommerce_product_variation_get_price',         'coinremitter_wc_crypto_price', $priority, 2 );

			add_filter('woocommerce_variation_prices_sale_price',          'coinremitter_wc_crypto_price', $priority, 2 );
			add_filter('woocommerce_variation_prices_regular_price',       'coinremitter_wc_crypto_price', $priority, 2 );
			add_filter('woocommerce_variation_prices_price',               'coinremitter_wc_crypto_price', $priority, 2 );
	    }
	}


	add_filter('woocommerce_get_variation_prices_hash',					'coinremitter_wc_variation_prices_hash', $priority, 1 );
	// add_action('woocommerce_admin_order_data_after_billing_address', 'coinremitter_wc_admin_order_stats');
    
        
	function coinremitter_wc_gateway_add( $methods )
	{
	        if (!in_array('WC_Gateway_CoinRemitter', $methods)) {
	                $methods[] = 'WC_Gateway_CoinRemitter';
	        }
	        return $methods;
	}

	function coinermitter_wc_currency_type( $currency = "" )
	{
	static $res = array();

	if (!$currency && function_exists('get_woocommerce_currency')) $currency = get_woocommerce_currency();
	$currency = coinremitter_currency_convert($currency)['currency'];

	if ($currency && isset($res[$currency]["user"]) && $res[$currency]["user"]) return $res[$currency];

	if (in_array(strlen($currency), array(6, 7)) && in_array(substr($currency, 3), json_decode(COINREMITTERWC_2WAY, true)) && in_array(substr($currency, 0, 3), array_keys(json_decode(COINREMITTER_RATES, true))))
	{
	    $user_currency  = substr($currency, 3);
	    $admin_currency = substr($currency, 0, 3);
	    $twoway = true;
	}
	else
	{
	    $user_currency  = $admin_currency = $currency;
	    $twoway = false;
	}

	$res[$currency] = array("2way"  => $twoway,
	                       "admin" => $admin_currency,
	                       "user"  => $user_currency
	                    );

	return $res[$currency];
	}
  function coinremitter_wc_payment_history( $order_id )
	{
		$order = new WC_Order( $order_id );

		$order_id     = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->id          : $order->get_id();
		$order_status = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->status      : $order->get_status();
		$post_status  = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->post_status : get_post_status( $order_id );
		$userID       = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->user_id     : $order->get_user_id();
		$method_title = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->payment_method_title  : $order->get_payment_method_title();

		$coin = get_post_meta($order_id, '_coinremitter_worder_coinname', true);
		if (is_user_logged_in() && ($coin || (stripos($method_title, "bitcoin")!==false && ($order_status == "pending" || $post_status=="wc-pending"))) && (is_super_admin() || get_current_user_id() == $userID))
		{
			echo "<br><a href='".$order->get_checkout_order_received_url()."&".COINREMITTER_COINS_HTMLID."=".strtolower($coin)."&prvw=1' class='button wc-forward'>".__( 'View Payment Details', COINREMITTERWC )." </a>";

		}

		return true;
	}
        
    function coinremitter_wc_payment_link( $order, $is_admin_email )
	{
		$order_id    = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->id          : $order->get_id();

	
		$coin = get_post_meta($order_id, '_coiniremitter_worder_coinname', true);
		if ($coin) echo "<br><h4><a href='".$order->get_checkout_order_received_url()."&".COINREMITTER_COINS_HTMLID."=".strtolower($coin)."&prvw=1'>".__( 'View Payment Details', COINREMITTERWC )." </a></h4><br>";

		return true;
	}
    function coinremitter_currency_convert($currency){
        if($currency){
            $exist = strpos($currency,'coinremitter');
            if($exist === false){
                
                return ['flag'=>0,'currency'=>$currency];
            }
            $currency = str_replace('coinremitter', '', $currency);
            $curArr = explode('.', $currency);
            if(count($curArr) == 2){
                $currency = str_replace('.', '', $currency);
            }
        }
        return ['flag'=>1,'currency'=>$currency];
    }
	
	function coinremitter_wc_currency_type( $currency = "" )
	{
	    static $res = array();

	    if (!$currency && function_exists('get_woocommerce_currency')) $currency = get_woocommerce_currency();
            
            $currency = coinremitter_currency_convert($currency)['currency'];
            
	    if ($currency && isset($res[$currency]["user"]) && $res[$currency]["user"]) return $res[$currency];

	    if (in_array(strlen($currency), array(6, 7)) && in_array(substr($currency, 3), json_decode(COINREMITTERWC_2WAY, true)) && in_array(substr($currency, 0, 3), array_keys(json_decode(COINREMITTER_RATES, true))))
	    {
	        $user_currency  = substr($currency, 3);
	        $admin_currency = substr($currency, 0, 3);
	        $twoway = true;
	    }
	    else
	    {
	        $user_currency  = $admin_currency = $currency;
	        $twoway = false;
	    }

	    $res[$currency] = array(   "2way"  => $twoway,
            	                   "admin" => $admin_currency,
            	                   "user"  => $user_currency
            	                );

	    return $res[$currency];
	}

	/*
	 * Currency symbol
	 */
	function coinremitter_wc_currency_symbol ( $currency_symbol, $currency )
	{
	    global $post;

        $currency = coinremitter_currency_convert($currency)['currency'];
            

	    if (coinremitter_wc_currency_type($currency)["2way"])
	    {
                
	        if (current_user_can('manage_options') && isset($post->post_type) && $post->post_type == "product")
	        {
	            $currency_symbol = get_woocommerce_currency_symbol(substr($currency, 0, 3));
	            if (!$currency_symbol) $currency_symbol = substr($currency, 0, 3);
	        }
	        elseif (current_user_can('manage_options') && isset($_GET["page"]) && sanitize_text_field($_GET["page"]) == "wc-settings" && (!isset($_GET["tab"]) || sanitize_text_field($_GET["tab"]) == "general"))
	        {
	            $currency_symbol = substr($currency, 0, 3) . " &#10143; " . substr($currency, 3);  // Currency options Menu
	        }
	        else $currency_symbol = substr($currency, 3);
                
	    }
	    
	    

	    return $currency_symbol;
	}

 	/*
	 * Allowance: For fiat - 0..2 decimals, for cryptocurrency 0..4 decimals
	 */
	function coinremitter_wc_currency_decimals( $decimals )
	{
	    global $post;
	    static $res;
            
	    if ($res) return $res;

	    $arr = coinremitter_wc_currency_type();

	    if ($arr["2way"])
        {
            $decimals = absint($decimals);

            if (current_user_can('manage_options') && isset($post->post_type) && $post->post_type == "product")
            {
                $decimals = 2;
            }
            elseif (function_exists('get_woocommerce_currency'))
            {
                
                $currency = $arr["user"]; // user visible currency
                if (in_array($currency, array("BTC", "BCH", "ETH")) && !in_array($decimals, array(3,4,5,6,7,8))) $decimals = 8;
                if (in_array($currency, array("LTC")) && !in_array($decimals, array(2,3)))                $decimals = 3;
                
            }
        }

        $res = $decimals;

        return $decimals;
	}
        
    function coinremitter_wc_crypto_price ( $price, $product = '' )
	{
	    global $woocommerce;
	    static $emultiplier = 0;
	    static $btc = 0;
            $pprice = 0;
            
            $currency = get_woocommerce_currency();
            
            $currency = coinremitter_currency_convert($currency)['flag'];
			print($currency);
			die();

            if(!$currency){
                return $price;
            }
	    	$live = 0;

	    if (!$price) return $price;
	 
	    $arr = coinremitter_wc_currency_type();

	    if ($arr["2way"])
	    {
                
	        if (!$emultiplier)
	        {
	            $gateways = $woocommerce->payment_gateways->payment_gateways();
	            if (isset($gateways['coinremitterpayments'])) $emultiplier = trim(str_replace(array("%", ","), array("", "."), $gateways['coinremitterpayments']->get_option('emultiplier')));
	            if (!$emultiplier || !is_numeric($emultiplier) || $emultiplier < 0.01) $emultiplier = 1;
	        }
	        if ($arr["user"] == "BTC") $live = $btc;
	        if ($live > 0) $price = floatval($price) / floatval($live) * 1.01 * floatval($emultiplier);
	        else  $price = 99999;
	    }
	    return $price;

	}
        
    function coinremitter_wc_variation_prices_hash( $hash )
	{
	    $arr = coinremitter_wc_currency_type();
	    if ($arr["2way"]) $hash[] = (current_user_can('manage_options') ? $arr["admin"] : $arr["user"]."-".date("Ymdh"));
	    return $hash;
	}
        
 
    /* Custom Functions MM 17102019*/ 
    function coinremitter_getExistsRecord(){
		
		global $wpdb;
		$tablename = $wpdb->prefix."posts";
		$SQL = "SELECT * FROM $tablename WHERE post_title = 'Crypto Checkout'";
		$dataVal = $wpdb->get_results($SQL);
		if($wpdb->num_rows > 0) {
			$PageID = $dataVal[0]->ID;
		}else{
			$postArr = array(
			  'post_title'    => wp_strip_all_tags('Crypto Checkout'),
			  'post_content'  => '[dis_custom_ord]',
			  'post_status'   => 'publish',
			  'post_author'   => 1,
			  'post_type'   => 'page',
			);
			$PageID = wp_insert_post( $postArr);
		}
		return $PageID;
	}//getExistsRecord

   function coinremitter_override_return_url($return_url,$order){
   		global $wpdb;
		$PID = coinremitter_getExistsRecord();
		$coin = sanitize_text_field($_POST['currency_type']);
		$return_url = get_the_permalink($PID);

		$OrdID = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->id          : $order->get_id(); 
		$order_amount = $order->get_total();
		$userID = get_current_user_id();
		$payment_title = $order->get_payment_method_title();
		$cancel_url = $order->get_cancel_order_url();
		$tablename = $wpdb->prefix."posts";
		$SQL = "SELECT * FROM $tablename WHERE post_title = 'checkout'";
		$dataVal = $wpdb->get_results($SQL);
		$s_url = get_permalink($dataVal[0]->ID);
		$test_order = new WC_Order($OrdID);	
		$test_order_key = $test_order->get_order_key();
		$sss_url = $s_url.'/order-pay/'.$OrdID.'/?pay_for_order=true&key='.$test_order_key;	
		if($payment_title == 'Cash on delivery'){
			$modified_url = $sss_url;
    		return $modified_url;
		}
		$currancy_type = get_woocommerce_currency();
		$option_data = get_option('woocommerce_coinremitterpayments_settings');
		$multiplier = get_option(COINREMITTER.strtolower($coin).'exchange_rate_multiplier');
		if($option_data['invoice_expiry'] == 0 || $option_data['invoice_expiry'] == ''){
			$invoice_expiry = '';
		}else{
        	$invoice_expiry = $option_data['invoice_expiry'];
		}

        if($multiplier == 0 && $multiplier == ''){
			$invoice_exchange_rate = 1;
        }else{
			$invoice_exchange_rate = $multiplier;
        }

		$amount = $order_amount * $invoice_exchange_rate; 
		
		$param = [
			'coin'=> strtoupper($coin), 
		];
		
        $tablename = 'coinremitter_order_address';
        $tablename2 = 'coinremitter_payments';
		$SQL = "SELECT * FROM $tablename WHERE orderID = 'coinremitterwc.order$OrdID'";

		$dataVal = $wpdb->get_results($SQL);

		if($wpdb->num_rows < 1) {
			
			$Address_data = coinremitter_getAddress($param);
			// print_r($Address_data);
			// die();
			if($Address_data['flag'] == 1){

				$rate_param = [
					'coin'=> strtoupper($coin),
					'fiat_symbol'=> $currancy_type,
					'fiat_amount'=>$amount, 
				];

				$Amount_data = coinremitter_getConvertRate($rate_param);
   		 		
				$coin = strtoupper($coin);
				$coin_price = $amount;
				$expiry_date=null;

				if($invoice_expiry != ""){
					$expiry_date=date("Y/m/d H:i:s", strtotime("+".$invoice_expiry." minutes"));
				}
	        	$wpdb->insert( $tablename, array(
		            'orderID' => 'coinremitterwc.order'.$OrdID,
		            'userID' => $userID,
		            'invoice_id' => '', 
		            'coinLabel' => $coin,
		            'amount' => $coin_price, 
		            'amountUSD' => $Amount_data['data']['crypto_amount'],
		            'addr' => $Address_data['data']['address'],
		            'qr_code'=> $Address_data['data']['qr_code'],
		            'createdAt' => gmdate("Y-m-d H:i:s"), 
		             ),
		            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s') 
		        );
		        $wpdb->insert( $tablename2, array(
		            'orderID' => 'coinremitterwc.order'.$OrdID,
		            'userID' => $userID,
		            'coinLabel' => $coin,
		            'invoice_id' => '', 
		            'base_currency'=>$currancy_type,
		            'total_amount' => $Amount_data['data']['crypto_amount'], 
		            'paid_amount' => "",
		            'conversion_rate' => "", 
		           	'invoice_url' =>$sss_url,
		            'expiry_date'=> $expiry_date,
		            'is_status_flag'=>0,
		            'status' => "", 
		            'status_code' => "", 
		            'description' =>'Order Id #'.$OrdID.'',
		            'txCheckDate' => gmdate("Y-m-d H:i:s"), 
		            'createdAt' => gmdate("Y-m-d H:i:s"), 

		             ),
		            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s','%s', '%s', '%s', '%s', '%s', '%s', '%s') 
		        );
		        update_post_meta( $OrdID, '_order_crypto_price', $Amount_data['data']['crypto_amount']);
                update_post_meta( $OrdID, '_order_crypto_coin', $coin);
				
		       $modified_url = $sss_url;
    			return $modified_url;
    			
    				
			}else{
				wp_delete_post($OrdID,true);  
				wc_add_notice(__($Address_data['msg']) , 'error');
			}
        }
    	
  	} 

 
  	function coinremitter_getAddress($param){
    	$Coin = $param['coin'];
    	
		$APIVal = get_option( COINREMITTER.strtolower($Coin).'api_key' );
	    $PasswordVal = get_option( COINREMITTER.strtolower($Coin).'password' );	   
	    
	   	$header[] = "Accept: application/json";
	    $curl =  COINREMITTER_API_URL.COINREMITTER_API_VERSION.'/'.$Coin.'/get-new-address';
	    $body = array(
	        'api_key' => $APIVal, 
	        'password'=>decrypt($PasswordVal),
	        
        );	
	    $userAgent = 'CR@'.COINREMITTER_API_VERSION.',wordpress worwoocommerce-wordpress-master@'.COINREMITTER_VERSION;
	    $args = array(
	        'method'      => 'POST',
	        'timeout'     => 45,
	        'sslverify'   => false,
	        'user-agent'  => $userAgent,
	        'headers'     => array(
	            'Content-Type'  => 'application/json',
	        ),
	        'body'        => json_encode($body),
	    );
	    $request = wp_remote_post( $curl, $args );

	    if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
	        error_log( print_r( $request, true ) );
	    }
    
	    $response = wp_remote_retrieve_body( $request );
	    return json_decode($response,true);
    }

    function coinremitter_getConvertRate($param){
    	// print_r($param);
    	// $address=$param['address'];
    	$Coin=$param['coin'];
    	$fiat_symbol = $param['fiat_symbol'];
    	$fiat_amount = $param['fiat_amount'];
    	
		$APIVal = get_option( COINREMITTER.strtolower($Coin).'api_key' );
	    $PasswordVal = get_option( COINREMITTER.strtolower($Coin).'password' );	   
	    
	   	$header[] = "Accept: application/json";
	    $curl =  COINREMITTER_API_URL.COINREMITTER_API_VERSION.'/'.$Coin.'/get-fiat-to-crypto-rate';
	   
	    $body = array(
	        'api_key' => $APIVal, 
	        'password'=>decrypt($PasswordVal),
	        'fiat_symbol'=>$fiat_symbol,
	        'fiat_amount'=>$fiat_amount,
	        
        );	
	    $userAgent = 'CR@'.COINREMITTER_API_VERSION.',wordpress worwoocommerce-wordpress-master@'.COINREMITTER_VERSION;
	    $args = array(
	        'method'      => 'POST',
	        'timeout'     => 45,
	        'sslverify'   => false,
	        'user-agent'  => $userAgent,
	        'headers'     => array(
	            'Content-Type'  => 'application/json',
	        ),
	        'body'        => json_encode($body),
	    );
	    $request = wp_remote_post( $curl, $args );

	    if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
	        error_log( print_r( $request, true ) );
	    }
    
	    $response = wp_remote_retrieve_body( $request );
	    return json_decode($response,true);
	    
    }

    function coinremitter_getInvoiceData($param){

    
		$coin = $param['coin'];
		$APIVal = get_option( COINREMITTER.strtolower($coin).'api_key' );
	    $PasswordVal = get_option( COINREMITTER.strtolower($coin).'password' );	   
	   	$header[] = "Accept: application/json";
	    $curl = $curl = COINREMITTER_API_URL.COINREMITTER_API_VERSION.'/'.$coin.'/get-invoice';
	    $userAgent = 'CR@'.COINREMITTER_API_VERSION.',wordpress worwoocommerce-wordpress-master@'.COINREMITTER_VERSION;
	
	    $body = array(
	        'api_key' => $APIVal, 
	        'password'=>decrypt($PasswordVal),
	        'invoice_id'=>$param['invoice_id'],
        );	
	    $args = array(
	        'method'      => 'POST',
	        'timeout'     => 45,
	        'user-agent'  => $userAgent,
	        'sslverify'   => false,
	        'headers'     => array(
	            'Content-Type'  => 'application/json',
	        ),
	        'body'        => json_encode($body),
	    );
	    $request = wp_remote_post( $curl, $args );

	    if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
	        error_log( print_r( $request, true ) );
	    }
    
	    $response = wp_remote_retrieve_body( $request );
	    return json_decode($response,true);    	
    }
    function coinremitter_geTransaction_by_address($param)
    {
    	$coin = strtoupper($param['coin']);
    	$address = $param['address'];
       	$curl = $curl = COINREMITTER_API_URL.COINREMITTER_API_VERSION.'/'.$coin.'/get-transaction-by-address';
       	$trx_param['api_key'] = get_option( COINREMITTER.$coin.'api_key' );
        $trx_param['password'] = get_option( COINREMITTER.$coin.'password' );
        
    
        $header[] = "Accept: application/json";
        $curl = $curl;
        $body = array(
            'api_key' => $trx_param['api_key'], 
            'password'=>decrypt($trx_param['password']),
            'address'=> $address,
        );  
        $userAgent = 'CR@'.COINREMITTER_API_VERSION.',wordpress worwoocommerce-wordpress-master@'.COINREMITTER_VERSION;
        $args = array(
            'method'      => 'POST',
            'timeout'     => 45,
            'sslverify'   => false,
            'user-agent'  => $userAgent,
            'headers'     => array(
                'Content-Type'  => 'application/json',
            ),
            'body'        => json_encode($body),
        );
        $request = wp_remote_post( $curl, $args );
        if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
            error_log( print_r( $request, true ) );
        }

        $response = wp_remote_retrieve_body( $request );
        return json_decode($response,true);
    	
    }
    function coinremitter_getTrantion($param){

    	$coin = strtoupper($param['coin']);
       	$curl = $curl = COINREMITTER_API_URL.COINREMITTER_API_VERSION.'/'.$coin.'/get-transaction';
        $trx_param['api_key'] = get_option( COINREMITTER.$coin.'api_key' );
        $trx_param['password'] = get_option( COINREMITTER.$coin.'password' );
        $trx_param['id'] = $param['id'];
    
        $header[] = "Accept: application/json";
        $curl = $curl;
        $body = array(
            'api_key' => $trx_param['api_key'], 
            'password'=>decrypt($trx_param['password']),
            'id'=> $trx_param['id'],
        );  
        $userAgent = 'CR@'.COINREMITTER_API_VERSION.',wordpress worwoocommerce-wordpress-master@'.COINREMITTER_VERSION;
        $args = array(
            'method'      => 'POST',
            'timeout'     => 45,
            'sslverify'   => false,
            'user-agent'  => $userAgent,
            'headers'     => array(
                'Content-Type'  => 'application/json',
            ),
            'body'        => json_encode($body),
        );
        $request = wp_remote_post( $curl, $args );
        if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
            error_log( print_r( $request, true ) );
        }

        $response = wp_remote_retrieve_body( $request );
        return json_decode($response,true);
    }

  	function custom_checkout_field($checkout){

		woocommerce_form_field('currency_type', array(
		'type' => 'text',
		'class' => array(
		'disNone'
		) ,
		'label' => __('') ,
		'placeholder' => __('') ,
		) ,
		$checkout->get_value('currency_type'));
	}

	function customised_checkout_field_process(){
		
	// Show an error message if the field is not set.
		
		if(sanitize_text_field($_POST['payment_method']) == 'coinremitterpayments'){
   			if (!sanitize_text_field($_POST['currency_type'])) wc_add_notice(__('Please select crypto payment method.') , 'error');	
		}
	}
	function custom_checkout_field_update_order_meta($order_id){
		
		if (!empty($_POST['currency_type'])) {
			update_post_meta($order_id, 'currency_type',sanitize_text_field($_POST['currency_type']));

		}

	}
	
    /* END Custom Functions MM 17102019*/     
        class WC_Gateway_CoinRemitter extends WC_Payment_Gateway
        {

                    private $payments           = array();
                    private $languages          = array();
                    private $coin_names         = array('BTC' => 'bitcoin', 'BCH' => 'bitcoincash', 'LTC' => 'litecoin', 'ETH'=>'ethereum', 'DOGE'=>'dogecoin', 'USDT'=>'tether', 'DASH'=>'dash');
                    private $statuses           = array('pending'=>'Pending payment','processing' => 'Processing Payment', 'on-hold' => 'On Hold', 'completed' => 'Completed','cancel' => 'Cancelled','refunded' => 'Refunded','failed'=>'Failed');
                    private $cryptorices        = array();
                    private $showhidemenu       = array('show' => 'Show Menu', 'hide' => 'Hide Menu');
                    private $mainplugin_url     = '';
                    private $url                = '';
                    private $url2               = '';
                    private $url3               = '';
                    private $cointxt            = '';

                    private $logo               = '';
                    private $ostatus            = '';
                    private $ostatus2           = '';
                    private $cryptoprice        = '';
                    private $deflang            = '';
                    private $defcoin            = '';
                    private $iconwidth          = '';

                    private $customtext         = '';
                    private $qrcodesize         = '';
                    private $langmenu           = '';
                    private $redirect           = '';


                public function __construct()
                {
                    global $coinremitter;

                            $this->id                 	= 'coinremitterpayments';
                            $this->method_title       	= __( 'CoinRemitter Crypto Payment Gateway', COINREMITTERWC );
                            $this->method_description  	= "<a target='_blank' href='https://coinremitter.com/'></a>";
                            
                            $this->has_fields         	= false;
                            $this->supports 			= array( 'subscriptions', 'products' );

                            $enabled = ((COINREMITTERWC_AFFILIATE_KEY=='coinremitter' && $this->get_option('enabled')==='') || $this->get_option('enabled') == 'yes' || $this->get_option('enabled') == '1' || $this->get_option('enabled') === true) ? true : false;


                            if (true === version_compare(WOOCOMMERCE_VERSION, '2.1', '<'))
                            {
                                    if ($enabled) $this->method_description .= '<div class="error"><p><b>' .sprintf(__( "Your WooCommerce version is too old. The CoinRemitter Crypto Payment Gateway plugin requires WooCommerce 2.1 or higher to function. Please update to <a href='%s'>latest version</a>.", COINREMITTERWC ), admin_url('plugin-install.php?tab=search&type=term&s=WooCommerce+excelling+eCommerce+WooThemes+Beautifully')).'</b></p></div>';
                            }
                            else
                            {

                                    $this->payments = $coinremitter->coinremitter_payments(); 	
                                    	// Activated Payments
                                    $this->coin_names = $coinremitter->coinremitter_coin_names(); 			// All Coins
                            }

                            $this->url		= COINREMITTER_ADMIN.COINREMITTER."credentials";
                            $this->url2		= COINREMITTER_ADMIN.COINREMITTER."payments&s=coinremitterwc";
                            $this->url3		= COINREMITTER_ADMIN.COINREMITTER;
                            $this->cointxt 	= (implode(", ", $this->payments)) ? implode(", ", $this->payments) : __( '- Please setup -', COINREMITTERWC );

                            $this->method_description  .= "<b>" . __( "Secure payments with virtual currency. <a target='_blank' href='https://bitcoin.org/'>What is Bitcoin?</a>", COINREMITTERWC ) . '</b><br>';
                            $this->method_description  .= sprintf(__( 'Accept %s payments online in WooCommerce.', COINREMITTERWC ), __( ucwords(implode(", ", $this->coin_names)), COINREMITTERWC )).'<br>';
                            if ($enabled) $this->method_description .= sprintf(__( "If you use multiple stores/sites online, please create separate <a target='_blank' href='%s'>CoinRemitter Wallet </a> (with unique wallet api key/password ) for each of your stores/websites. Do not use the same CoinRemitter wallet with the same api key/password on your different websites/stores.", COINREMITTERWC ), "https://coinremitter.com") . '<br><br>';
                            else $this->method_description .= '<br>';

                            $this->cryptorices = array("Original Price only");
                            foreach ($this->coin_names as $k => $v) $this->cryptorices['coinremitter'.$k] = sprintf(__( "Fiat + %s", COINREMITTERWC ), ucwords($v));

                            foreach ($this->coin_names as $k => $v)
                                foreach ($this->coin_names as $k2 => $v2)
                                     if ($k != $k2) $this->cryptorices['coinremitter'.$k."_".$k2] = sprintf(__( "Fiat + %s + %s", COINREMITTERWC ), ucwords($v), ucwords($v2));

                            // Update some WooCommerce settings
                            // --------------------------------
                            // for WooCommerce 2.1x
                            if ($enabled && coinremitter_wc_currency_type()["2way"] && !function_exists('wc_get_price_decimals')) update_option( 'woocommerce_price_num_decimals', 4 );

                            // increase Hold stock to 200 minutes
                            if ($enabled && get_option( 'woocommerce_hold_stock_minutes' ) > 0 && get_option( 'woocommerce_hold_stock_minutes' ) < 80) update_option( 'woocommerce_hold_stock_minutes', 200 );

                            // Load the settings.
                            $this->init_form_fields();
                            $this->init_settings();
                            $this->coinremitter_settings();

                  	          // Hooks

       						add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'set_validation_payment_gateways' ) );
                            
                            //Subscriptions
                            if ( class_exists( 'WC_Subscriptions_Order' ) ) {
                            }
						// return true;
                }
               	public function set_validation_payment_gateways()
               	{
               		$post_data = $this->get_post_data();
               		if(!empty($post_data)){
               			
               			if(isset($post_data['woocommerce_coinremitterpayments_invoice_expiry']) && !preg_match('/^[0-9]+$/', $post_data['woocommerce_coinremitterpayments_invoice_expiry'])){
							
							echo '<div class="error notice"><p>Invoice expiry only accept numbers</p></div>';
							
							return false;
						}else if(isset($post_data['woocommerce_coinremitterpayments_invoice_expiry']) && $post_data['woocommerce_coinremitterpayments_invoice_expiry'] < 0 || $post_data['woocommerce_coinremitterpayments_invoice_expiry'] > 10080){
							
							echo '<div class="error notice"><p>Invoice expiry minutes should be 0 to 10080</p></div>';
							
							return false;
						}else{
							foreach ( $this->get_form_fields() as $key => $field ) {
									if ( 'title' !== $this->get_field_type( $field ) ) {
										try {
											$this->settings[ $key ] = $this->get_field_value( $key, $field, $post_data );
										} catch ( Exception $e ) {
											$this->add_error( $e->getMessage() );
										}
									}
								}
							return update_option( $this->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ), 'yes' );
						   // return true;
						}
                    }

                     // return true;
               	}
               
                public function init_form_fields()
                {
                	$urlcoin = 'https://coinremitter.com/api/get-coin-rate';
                	$per1 = '5%';
                	$per2 = '15%';
                   

                    $this->form_fields = array(
                        'enabled'		=> array(
                        'title'   	  	=> __( 'Enable/Disable', COINREMITTERWC ),
                        'type'    	  	=> 'checkbox',
                        'default'	  	=> (COINREMITTERWC_AFFILIATE_KEY=='coinremitter'?'yes':'no'),
                        'label'   	  	=> sprintf(__( "Enable CoinRemitter Crypto Payments in WooCommerce with <a href='%s'>CoinRemitter Crypto Payment Gateway</a>", COINREMITTERWC ), $this->url3)
                    ),
                        'title'			=> array(
                        'title'       	=> __( 'Title', COINREMITTERWC ),
                        'type'        	=> 'text',
                        'default'     	=> __( 'Pay Using Cryptocurrency', COINREMITTERWC ),
                        'description' 	=> __( 'Payment method title that the customer will see on your checkout', COINREMITTERWC )
                    ),
                        'description' 	=> array(
                        'title'       	=> __( 'Description', COINREMITTERWC ),
                        'type'        	=> 'textarea',
                        'default'     	=> trim(sprintf(__( 'Secure, anonymous payment with virtual currency - %s', COINREMITTERWC ), implode(", ", $this->payments)), " -") . ". <a target='_blank' href='https://bitcoin.org/'>" . __( 'What is bitcoin?', COINREMITTERWC ) . "</a>",
                        'description' 	=> __( 'Payment method description that the customer will see on your checkout', COINREMITTERWC )
                    ),
                        'ostatus' 		=> array(
                        'title' 		=> __('Order Status - On Payment Received', COINREMITTERWC ),
                        'type' 			=> 'select',
                        'options' 		=> $this->statuses,
                        'default' 		=> 'processing',
                        'description' 	=> sprintf(__("When customer pay coinremitter invoice, What order status should be ? Set it here", COINREMITTERWC), $this->url2)
                    ),
                      	'invoice_expiry'		=> array(
                        'title'       	=> __( 'Invoice expiry time in Minutes', COINREMITTERWC ),
                        'type'        	=> 'text',
                        'default'     	=> "0",
                        'description' 	=> __( "It indicates invoice validity. An invoice will not valid after expiry minutes. E.g if you set Invoice expiry time in Minutes 30 then the invoice will expire after 30 minutes. Set 0 to avoid expiry", COINREMITTERWC )
                    )
                     
                );

                    return true;
                }

				private function coinremitter_settings()
                {
                	// Define user set variables
                    $this->enabled      = $this->get_option( 'enabled' );
                    $this->title        = $this->get_option( 'title' );
                    $this->description  = $this->get_option( 'description' );
                    $this->logo         = $this->get_option( 'logo' );
                    $this->ostatus      = $this->get_option( 'ostatus' );
                    $this->ostatus2     = $this->get_option( 'ostatus2' );
                    $this->cryptoprice  = $this->get_option( 'cryptoprice' );
                    $this->deflang      = $this->get_option( 'deflang' );
                    $this->defcoin      = $this->get_option( 'defcoin' );
                    $this->iconwidth    = trim(str_replace("px", "", $this->get_option( 'iconwidth' )));

                    $this->customtext   = $this->get_option( 'customtext' );
                    $this->qrcodesize   = trim(str_replace("px", "", $this->get_option( 'qrcodesize' )));
                    $this->langmenu     = $this->get_option( 'langmenu' );
                    $this->redirect     = $this->get_option( 'redirect' );


                    
                    // Re-check
                    if (!$this->title)                                  $this->title 		= __('Pay Using Cryptocurrency -  CoinRemitter', COINREMITTERWC);
                    if (!$this->description)                            $this->description 	= sprintf(__('Secure, anonymous payment with virtual currency - %s', COINREMITTERWC), implode(',', $this->payments));
                    if (!isset($this->statuses[$this->ostatus]))        $this->ostatus  	= 'processing';
                    if (!isset($this->statuses[$this->ostatus2]))       $this->ostatus2 	= 'processing';
                    if (!isset($this->cryptoprices[$this->cryptoprice])) $this->cryptoprice = '';
                    if (!isset($this->languages[$this->deflang]))       $this->deflang 		= 'en';

                    $this->description = $this->description.$this->setPaymnetOptDesc();	

                    if (!in_array($this->logo, $this->coin_names) && $this->logo != 'global')                   $this->logo = 'bitcoin';
                    if (!is_numeric($this->iconwidth) || $this->iconwidth < 30 || $this->iconwidth > 250)       $this->iconwidth = 60;
                    if (!is_numeric($this->qrcodesize) || $this->qrcodesize < 0 || $this->qrcodesize > 500)     $this->qrcodesize = 200;

                    if ($this->defcoin && $this->payments && !isset($this->payments[$this->defcoin]))           $this->defcoin = key($this->payments);
                    elseif (!$this->payments)                                                                   $this->defcoin = '';
                    elseif (!$this->defcoin)                                                                    $this->defcoin = key($this->payments);

                    if (!isset($this->showhidemenu[$this->langmenu])) 	$this->langmenu     = 'show';
                    if ($this->langmenu == 'hide') define("COINREMITTER_LANGUAGE_HTMLID_IGNORE", TRUE);

                    if (stripos($this->redirect, "http") !== 0)         $this->redirect     = '';

                 	
                    return true;
                }
                public function setPaymnetOptDesc(){
		           	global $coinremitter;
					global $woocommerce;
					$total_amt = 0;
					$currancy_type = get_woocommerce_currency();

					if( isset( $woocommerce->cart ) ) {
						$total_amt = $woocommerce->cart->total;
					}

		           	$cryptobox_localisation_coinremitter	= array(
								"name"		=> "English", 
								"button"			=> "Click Here if you have already sent %coinNames%",
								"msg_not_received" 	=> "<b>%coinNames% have not yet been received.</b><br>If you have already sent %coinNames% (the exact %coinName% sum in one payment as shown in the box below), please wait a few minutes to receive them by %coinName% Payment System. If you send any other sum, Payment System will ignore the transaction and you will need to send the correct sum again, or contact the site owner for assistance.",
								"msg_received" 	 	=> "%coinName% Payment System received %amountPaid% %coinLabel% successfully !",
								"msg_received2" 	=> "%coinName% Captcha received %amountPaid% %coinLabel% successfully !",
								"payment"			=> "Select Payment Method",
								"pay_in"			=> "Payment in %coinName%",
								"loading"			=> "Loading ..."
                                        );

                	if(!defined("COINREMITTER_CRYPTOBOX_LOCALISATION")) define("COINREMITTER_CRYPTOBOX_LOCALISATION", json_encode($cryptobox_localisation_coinremitter));

                	$directory   = (defined("COINREMITTER_IMG_FILES_PATH")) ? COINREMITTER_IMG_FILES_PATH : "images/";     // path to directory with coin image files (directory 'images' by 
                	$localisation = json_decode(COINREMITTER_CRYPTOBOX_LOCALISATION, true);
                	$id 	 			= (defined("COINREMITTER_COINS_HTMLID")) ? COINREMITTER_COINS_HTMLID : "coinremittercryptocoin";
                	// Url for Change Coin
		            $coin_url = $_SERVER["REQUEST_URI"];
					$count = 0;
					$checkCoin = 0;
		            $coin_names = $coinremitter->coinremitter_coin_names();
					$available_coins = array();
                	foreach ($coin_names as $k => $v){
                		$v = preg_replace('/\s+/', '', $v);
                		$short_name = $k;
                		$CurrAPIKey = get_option(COINREMITTER.strtolower($short_name).'api_key');
                		$CurrPassword = get_option(COINREMITTER.strtolower($short_name).'password');
                		$Currdeleted = get_option(COINREMITTER.strtolower($short_name).'is_deleted');
						$multiplier = get_option(COINREMITTER.strtolower($short_name).'exchange_rate_multiplier');
						$minimum_invoice_val = get_option(COINREMITTER.strtolower($short_name).'min_invoice_value');
					    $public_key 	= $CurrAPIKey;
					    $private_key 	= $CurrPassword;
					    if ($private_key && $public_key && $Currdeleted != 1)
					    {
					        $all_keys[$v] = array("api_key" => $public_key,  "password" => $private_key);
							if($total_amt > 0){
								if($multiplier == 0 || $multiplier == '')
									$multiplier = $ExchangeRate_Multiplier;
						
								if($minimum_invoice_val == '' || $minimum_invoice_val == null)
									$minimum_invoice_val = $MinimumInvoiceValue;

								$total_amount = $total_amt * $multiplier;

								$rate_param = [
									'coin'=> strtoupper($short_name),
									'fiat_symbol'=> $currancy_type,
									'fiat_amount'=>$total_amount, 
								];
							
								$converted_rate = coinremitter_getConvertRate($rate_param);
									
								if($converted_rate['data']['crypto_amount'] >= $minimum_invoice_val){
									$available_coins[] = $short_name;
									$count = 1;
									$checkCoin++;
								} else {
									$checkCoin++;
								}
							} else {
								$available_coins[] = $short_name;
							}
							
					    }
					}
					if($checkCoin == 0)
						$count = -1;

					if($count == -1) {
						add_action('wp_enqueue_scripts', 'select_paument_coin');
						$temp = "<p class='noCoin' >No coin wallet setup!</p>";
						$CryptoOpt = !empty($temp) ? (isset($Script) ? $Script : '').$temp.'<input type="hidden" name="crpopt" id="crpopt" >' : '';
						$SetPaymentOpt = !empty($CryptoOpt) ? (isset($CoinSript) ? $CoinSript : '').'<div>'.$CryptoOpt.'</div>' : '';
						return $SetPaymentOpt;
					} else if($count == 0){
						add_action('wp_enqueue_scripts', 'select_paument_coin');
						$temp = "<p class='noCoin' >Invoice amount is too low. Choose other payment method !</p>";
						$CryptoOpt = !empty($temp) ? (isset($Script) ? $Script : '').$temp.'<input type="hidden" name="crpopt" id="crpopt" >' : '';
						$SetPaymentOpt = !empty($CryptoOpt) ? (isset($CoinSript) ? $CoinSript : '').'<div>'.$CryptoOpt.'</div>' : '';
						return $SetPaymentOpt;
					}

					$iconWidth = 70;
					$tmp='';
					
					if(is_array($available_coins) && sizeof($available_coins)){
	                	foreach ($available_coins as $v){
			                    $v = trim(strtolower($v));
								
			                    // $url = $coin_url.$v."#".$anchor;
			                    $imageDir = dirname(__FILE__).'/images';
								$coin_imge_name = $v;
								$path = $imageDir.'/'.$coin_imge_name.'.png';
								if(!file_exists($path)){
									$wallet_logo = 'dollar-ico';
								}
								else{
									$wallet_logo = $coin_imge_name;
								}
			                    add_action('wp_enqueue_scripts', 'select_paument_coin');
			                    $tmp .= "<a href='#' rel='".$v."' class='crpObj' ><img style='box-shadow:none; margin-right:30px;padding: 10px;".round($iconWidth/10)."px ".round($iconWidth/6)."px;border:0;display:inline;' width='$iconWidth' title='".str_replace("%coinName%", ucfirst($v), $localisation["pay_in"])."' alt='".str_replace("%coinName%", $v, $localisation["pay_in"])."' src='".$directory.$wallet_logo.($iconWidth>70?"2":"").".png'></a>";
			            }
		            } 
		            $CryptoOpt = !empty($tmp) ? (isset($Script) ? $Script : '').$tmp.'<input type="hidden" name="crpopt" id="crpopt" >' : '';

		            $SetPaymentOpt = !empty($CryptoOpt) ? (isset($CoinSript) ? $CoinSript : '').'<div>'.$CryptoOpt.'</div>' : '';
					
					return $SetPaymentOpt;
				
			
                }
                public function process_payment( $order_id )
                {
                    global $woocommerce;

                    $arr = coinremitter_wc_currency_type();
                    // New Order
                    $order = new WC_Order( $order_id );

                    $order_id    = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->id          : $order->get_id();
                    $userID      = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->user_id     : $order->get_user_id();
                    $order_total = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->order_total : $order->get_total();

                    // Mark as pending (we're awaiting the payment)
                    $order->update_status('pending', __('Awaiting payment notification from CoinRemitter', COINREMITTERWC));


                    // Payment Page
                    $payment_link = $this->get_return_url($order);

                    // Get original price in fiat

                    $total = ($order_total >= 1000 ? number_format($order_total) : $order_total)." ".$arr["user"];
                
                    $orderpage = $this->get_return_url($order);

                    if (!get_post_meta( $order_id, '_coinremitter_worder_orderid', true ))
                    {
                        update_post_meta( $order_id, '_coinremitter_worder_orderid', 	    $order_id );
                        update_post_meta( $order_id, '_coinremitter_worder_userid', 	    $userID );
                        update_post_meta( $order_id, '_coinremitter_worder_createtime',   gmdate("c") );

                        update_post_meta( $order_id, '_coinremitter_worder_orderpage',     $orderpage );
                        update_post_meta( $order_id, '_coinremitter_worder_created',      gmdate("d M Y, H:i") );

                        update_post_meta( $order_id, '_coinremitter_worder_currencies', $arr );
                        update_post_meta( $order_id, '_coinremitter_worder_amountcrypto', $total );
                        update_post_meta( $order_id, '_coinremitter_worder_amountfiat',   (isset($totalFiat)?$totalFiat:$total) );
                    }

                    $total_html = $total;
                    if (isset($totalFiat)) $total_html .= " / <b> ".$totalFiat."</b>";
                    else $total_html = "<b>" . $total_html . "</b>";

                    $userprofile = (!$userID) ? __('Guest', COINREMITTERWC) : "<a href='".admin_url("user-edit.php?user_id=".$userID)."'>User".$userID."</a>";
                    // Remove cart
                    WC()->cart->empty_cart();

                    // Return redirect
                    return array(
                        'result' 	=> 'success',
                        'redirect'	=> $payment_link
                    );
                }
            }
            
            function coinremitterwoocommerce_callback ($user_id, $order_id, $payment_details)
            {
                    global $woocommerce;

                    $gateways = $woocommerce->payment_gateways->payment_gateways();

                    if (!isset($gateways['coinremitterpayments'])) return;
                    // forward data to WC_Gateway_CoinRemitter
                    $gateways['coinremitterpayments']->coinremittercallback( $user_id, $order_id, $payment_details);

                    return true;
            }
        
    }

    
}


add_action('wp_ajax_coinremitter_withdraw', 'coinremitter_withdraw');
add_action('wp_ajax_nopriv_coinremitter_withdraw', 'coinremitter_withdraw');
function coinremitter_withdraw(){

	$CoinType = sanitize_text_field($_POST['cointype']);

	$APIVal = get_option( COINREMITTER.strtolower($CoinType).'api_key' );
 	$PasswordVal = get_option( COINREMITTER.strtolower($CoinType).'password' );
 	$TO_Address = isset($_POST['address']) ? sanitize_text_field($_POST['address']) : '';
 	$Amount = sanitize_text_field($_POST['amount']);
 
	$postdata = array('api_key' => $APIVal, 'password'=>$PasswordVal,'to_address'=>$TO_Address,'amount'=>$Amount);
    $curl =  COINREMITTER_API_URL.COINREMITTER_API_VERSION.'/'.$CoinType.'/withdraw';
    $userAgent = 'CR@'.COINREMITTER_API_VERSION.',wordpress worwoocommerce-wordpress-master@'.COINREMITTER_VERSION;
    $body = array(
        'api_key' => $APIVal,
        'password' => decrypt($PasswordVal),
        'to_address' => $TO_Address,
        'amount' => $Amount,
    );
    $args = array(
        'method'      => 'POST',
        'timeout'     => 45,
        'sslverify'   => false,
        'user-agent'  => $userAgent,
        'headers'     => array(
            'Content-Type'  => 'application/json',
        ),
        'body'        => json_encode($body),
    );
    $request = wp_remote_post( $curl, $args );
    if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
        error_log( print_r( $request, true ) );
    }
    $response = wp_remote_retrieve_body( $request );
    echo $response;
	exit(0);
}	

function select_paument_coin() {
		wp_enqueue_script ( 'jquery.validate', '/wp-includes/js/jquery/jquery.js');
		if(isset($_GET['pay_for_order'])){
			wp_enqueue_script ( 'paycheckout', plugins_url('/js/pay-webhook.js', __FILE__) );
		}else{
			wp_enqueue_script ( 'paycheckout', plugins_url('/js/pay-checkout.js', __FILE__) );
		}
	
}


/* AJAX call for API response */

/* Check Payment Status */
function getOrderPaymentStatus($OrdId){
	global $wpdb;
	$table_name = 'coinremitter_order_address';
	$OrdId = 'coinremitterwc.order'.$OrdId;

	$SQL = "SELECT * FROM $table_name WHERE orderID = '".$OrdId."' ";
	$results = $wpdb->get_results($SQL);
	if(is_array($results) && sizeof($results)){
		$PaymentInf['payment_status'] = $results[0]->payment_status;
		$PaymentInf['payment_addr'] = $results[0]->addr;
		$PaymentInf['payment_amount'] = $results[0]->amount;
		$PaymentInf['payment_coinlabel'] = $results[0]->coinLabel;
		
	}
	return $PaymentInf;
}//getOrderPaymentStatus
add_filter( 'manage_edit-shop_order_columns', 'my_woo_order_list_col' );
function my_woo_order_list_col( $columns ) {
	$new_columns = ( is_array( $columns ) ) ? $columns : array();
  	$new_columns['crypto_amnt_col'] = 'Crypto Amount';
  	$new_columns[ 'order_actions' ] = $columns['order_actions'];
  	return $new_columns;
}
function sv_wc_cogs_add_order_profit_column_content( $column ) {
    global $post;
    $OrdNo = $post->ID;
   
   	$CryptoPrice = get_post_meta( $OrdNo, '_order_crypto_price');
	$CryptoType = get_post_meta( $OrdNo, '_order_crypto_coin');
	
	$table_name = 'coinremitter_payments';
    if( 'crypto_amnt_col' === $column ) {

	global $wpdb;
	$order_id = $OrdNo;

	$order_data = "SELECT * FROM coinremitter_payments WHERE orderID = 'coinremitterwc.order".$order_id."'";
		$order_details = $wpdb->get_results($order_data);
		
		if(!empty($order_details) && $order_details[0]->invoice_id == "" )
		{
			$query = "SELECT * FROM coinremitter_order_address WHERE orderID = 'coinremitterwc.order".$order_id."'";
		
			$get_order_data = $wpdb->get_results($query);
			if(count($get_order_data) < 1)
			{
				return "";
			}
		
		$coin_type = $get_order_data[0]->coinLabel;
		$address = $get_order_data[0]->addr;
		$payments_date =  $get_order_data[0]->createdAt;
		
		$expiry_date=$order_details[0]->expiry_date;
		$status=$order_details[0]->status_code;
		$order = wc_get_order( $order_id );
	    $webhook_data = "SELECT * FROM coinremitter_webhook WHERE `addr` = '".$address."' ";
		$web_hook = $wpdb->get_results($webhook_data);
		if($expiry_date != "" ){
			$diff=strtotime($expiry_date)- strtotime(gmdate('Y-m-d H:i:s'));
		}
		if(count($web_hook) == 0  && isset($diff) && $diff <= 0 ){
        	$order->update_status('cancelled');
			$order_status_code=COINREMITTER_INV_EXPIRED;
			$order_status='Expired';

			$u_order_data="UPDATE `coinremitter_order_address` SET `payment_status` = '$order_status_code' WHERE `addr` = '$address' ";
			$update_order_data = $wpdb->get_results($u_order_data);

			$payment_data="UPDATE `coinremitter_payments` SET `status` = '$order_status',`status_code` = '$order_status_code' WHERE `orderID` = '".$get_order_data[0]->orderID."' ";
			
			$update_payment_data = $wpdb->get_results($payment_data);
		}
		$order_data = "SELECT * FROM coinremitter_payments WHERE orderID = 'coinremitterwc.order".$order_id."'";
		$order_details = $wpdb->get_results($order_data);
		$status=$order_details[0]->status_code;
		if($status == COINREMITTER_INV_PENDING || $status == COINREMITTER_INV_UNDER_PAID )
		{
			$param=[
				'coin'=> $coin_type,
				'address'=>$address,
			];

			$transaction_data=coinremitter_geTransaction_by_address($param);
			$paid_amount=0;
			if(isset($transaction_data['flag']) && $transaction_data['flag'] == 1)
			{
				$transaction=$transaction_data['data'];
				foreach ($transaction as $t) {
					
					if( $t['type'] == 'receive' )
					{
						$id=$t['id'];
						$date=gmdate('Y-m-d H:i:s');
						$txid=$t['txid'];
						$amount=$t['amount'];
						$explorer_url=$t['explorer_url'];
						$confirmations=$t['confirmations'];
						if($confirmations >= 3)
						{
							$paid_amount +=$amount;
						}
						
						$query="SELECT * FROM coinremitter_webhook WHERE `transaction_id` = '".$id."' ";
						$transtion_entry = $wpdb->get_results($query);
						if(count($transtion_entry) > 0 )
						{
					
							if($confirmations < 3){
			                    $confirmations_order=$confirmations;
			                }else{
			                    $confirmations_order=3;
			                }

							$sql = "UPDATE `coinremitter_webhook` SET 
							`confirmation`='$confirmations_order' , `updated_date`='$date' WHERE `transaction_id`= '".$id."'";
							
							$update = $wpdb->get_results($sql);
						}else{
							
							$sql = "INSERT INTO coinremitter_webhook ( order_id, transaction_id,addr, tx_id,explorer_url,paid_amount,coin,confirmation,paid_date,created_date,updated_date)
                                VALUES ('".$order_details[0]->orderID."', '".$id."', '".$address."', '".$txid."','".$explorer_url."','".$amount."', '".$coin_type."','".$confirmations."','".$date."','".$date."','".$date."')";
                            
                            $inserted = $wpdb->get_results($sql);
						}
					}
					
				}
				$total_amount=$get_order_data[0]->amountUSD;
				$total_paidamount=number_format($paid_amount,8);
				
				$order_status="";
		        $order_status_code="";
		        $option_data = get_option('woocommerce_coinremitterpayments_settings');
	            $ostatus = $option_data['ostatus'];
		        if($total_paidamount == 0){
		        	$order_status="Pending";
		        	$order->update_status('pending');
		            $order_status_code=COINREMITTER_INV_PENDING;
		        }else if($total_amount > $total_paidamount ){
		            $order_status="Under paid";
		        	$order->update_status('pending');
		            $order_status_code=COINREMITTER_INV_UNDER_PAID;
		        }else if($total_amount == $total_paidamount){
		            $order_status="Paid ";
		            $order->update_status('wc-'.$ostatus);
		        	$order_status_code=COINREMITTER_INV_PAID;
		        }else if($total_amount < $total_paidamount){
		        	$order->update_status('wc-'.$ostatus);
		        	$order_status="Over paid";
		            $order_status_code=COINREMITTER_INV_OVER_PAID;
		        }
		        $u_order_data="UPDATE `coinremitter_order_address` SET `payment_status` = '$order_status_code' WHERE `addr` = '$address' ";
				$update_order_data = $wpdb->get_results($u_order_data);

				$payment_data="UPDATE `coinremitter_payments` SET `status` = '$order_status',`status_code` = '$order_status_code', `paid_amount`='".$total_paidamount."' WHERE `orderID` = '".$get_order_data[0]->orderID."' ";
				
				$update_payment_data = $wpdb->get_results($payment_data);
			}
		}
	}
	
	
		$OrdId = 'coinremitterwc.order'.$OrdNo;
    	$SQL = "SELECT * FROM $table_name WHERE orderID = '".$OrdId."' ";
		$results = $wpdb->get_results($SQL);
		
		echo !empty($CryptoPrice[0]) ? '<span class="greeColour">'.sprintf('%.8f',$CryptoPrice[0]).' '.$CryptoType[0].'</span>' : '-';
    }
}


add_action( 'manage_shop_order_posts_custom_column', 'sv_wc_cogs_add_order_profit_column_content' );

/* Show crypto price on order detail page back-end after Total Price */
add_action( 'woocommerce_admin_order_totals_after_refunded', 'crypto_amt_on_ord_detail_page', 10, 1 ); 

// add_action( 'woocommerce_admin_order_data_after_order_details', 'output_data', 10, 2 );

add_action( 'add_meta_boxes', 'coinremitter_cd_meta_box_add' );

function coinremitter_cd_meta_box_add()
{	 global $wpdb;
	$order_id = sanitize_text_field($_GET['post']);
	$method = "Payment Detail (Coinremitter)";
	$order_type_object = get_post();
	$payment_query = "SELECT * FROM coinremitter_payments WHERE orderID = 'coinremitterwc.order".$order_id."'";
	$payment_details = $wpdb->get_results($payment_query);
	if(!empty($payment_details)){
  		add_meta_box( 'my-meta-box-id', $method, 'coinremitter_cd_meta_box_cb', $order_type_object->post_type, 'normal', 'high' );
	}
}

function coinremitter_cd_meta_box_cb()  
{  

	global $wpdb;

	$order_id = sanitize_text_field($_GET['post']);
	
	$order_detail =wc_get_order( $order_id );

		$o_status = $order_detail->get_status();


		$method = get_post_meta( $order_id, '_payment_method_title', true );
		$option_data = get_option('woocommerce_coinremitterpayments_settings');

		if($method != $option_data['title']){
			return '';
		}

		$query = "SELECT * FROM coinremitter_order_address WHERE orderID = 'coinremitterwc.order".$order_id."'";
		
			$get_order_data = $wpdb->get_results($query);
			if(count($get_order_data) < 1)
			{
				return "";
			}
			
		
		
		$coin_type = $get_order_data[0]->coinLabel;
		$address = $get_order_data[0]->addr;
		$payments_date =  $get_order_data[0]->createdAt;


		$order_data = "SELECT * FROM coinremitter_payments WHERE orderID = 'coinremitterwc.order".$order_id."'";
		$order_details = $wpdb->get_results($order_data);
		if($order_details[0]->invoice_id == "" )
		{
					
			
			$expiry_date=$order_details[0]->expiry_date;
			$status=$order_details[0]->status_code;
			$order = wc_get_order( $order_id );
		    $webhook_data = "SELECT * FROM coinremitter_webhook WHERE `addr` = '".$address."' ";
			$web_hook = $wpdb->get_results($webhook_data);
			if($expiry_date != "" ){
				$diff=strtotime($expiry_date)- strtotime(gmdate('Y-m-d H:i:s'));
			}

			if(count($web_hook) == 0  && isset($diff) && $diff <= 0 ){
	        	$order->update_status('cancelled');
				$order_status_code=COINREMITTER_INV_EXPIRED;
				$order_status='Expired';

				$u_order_data="UPDATE `coinremitter_order_address` SET `payment_status` = '$order_status_code' WHERE `addr` = '$address' ";

				$update_order_data = $wpdb->get_results($u_order_data);

				$payment_data="UPDATE `coinremitter_payments` SET `status` = '$order_status',`status_code` = '$order_status_code' WHERE `orderID` = '".$get_order_data[0]->orderID."' ";
				
				$update_payment_data = $wpdb->get_results($payment_data);

			}
			$order_data = "SELECT * FROM coinremitter_payments WHERE orderID = 'coinremitterwc.order".$order_id."'";
			$order_details = $wpdb->get_results($order_data);
			$status=$order_details[0]->status_code;
			$status_flag=$order_details[0]->is_status_flag;
			if($status == COINREMITTER_INV_PENDING || $status == COINREMITTER_INV_UNDER_PAID )
			{
				$param=[
					'coin'=> $coin_type,
					'address'=>$address,
				];
				$transaction_data=coinremitter_geTransaction_by_address($param);
				$paid_amount=0;
				if(isset($transaction_data['flag']) && $transaction_data['flag'] == 1)
				{
					$transaction=$transaction_data['data'];
					foreach ($transaction as $t) {
						
						if( $t['type'] == 'receive' )
						{
							$id=$t['id'];
							$date=gmdate('Y-m-d H:i:s');
							$txid=$t['txid'];
							$amount=$t['amount'];
							$explorer_url=$t['explorer_url'];
							$confirmations=$t['confirmations'];
							if($confirmations >= 3)
							{
								$paid_amount +=$amount;
							}
							
							$query="SELECT * FROM coinremitter_webhook WHERE `transaction_id` = '".$id."' ";
							$transtion_entry = $wpdb->get_results($query);
							if(count($transtion_entry) > 0 )
							{
						
								if($confirmations < 3){
				                    $confirmations_order=$confirmations;
				                }else{
				                    $confirmations_order=3;
				                }

								$sql = "UPDATE `coinremitter_webhook` SET 
								`confirmation`='$confirmations_order' , `updated_date`='$date' WHERE `transaction_id`= '".$id."'";
								
								$update = $wpdb->get_results($sql);
							}else{
								
								$sql = "INSERT INTO coinremitter_webhook ( order_id, transaction_id,addr, tx_id,explorer_url,paid_amount,coin,confirmation,paid_date,created_date,updated_date)
	                                VALUES ('".$order_details[0]->orderID."', '".$id."', '".$address."', '".$txid."','".$explorer_url."','".$amount."', '".$coin_type."','".$confirmations."','".$date."','".$date."','".$date."')";
	                            
	                            $inserted = $wpdb->get_results($sql);
							}
						}
						
					}
				
					$total_amount=$get_order_data[0]->amountUSD;
					$total_paidamount=number_format($paid_amount,8);
					$order_status="";
			        $order_status_code="";
			        $option_data = get_option('woocommerce_coinremitterpayments_settings');
		            $ostatus = $option_data['ostatus'];
			        if($total_paidamount == 0){
			        	$order_status="Pending";
			        	$order->update_status('pending');
			            $order_status_code=COINREMITTER_INV_PENDING;
			        }else if($total_amount > $total_paidamount ){
			            $order_status="Under paid";
			        	$order->update_status('pending');
			            $order_status_code=COINREMITTER_INV_UNDER_PAID;
			        }else if($total_amount == $total_paidamount){
			            $order_status="Paid ";
			            if($status_flag==0){
			            	$order->update_status('wc-'.$ostatus);
			            	$status_flag=1;
						}else{
			            	$status_flag=1;
			            }
			        	$order_status_code=COINREMITTER_INV_PAID;
			        }else if($total_amount < $total_paidamount){
			        	if($status_flag==0){
			            	$order->update_status('wc-'.$ostatus);
			            	$status_flag=1;
						}else{
			            	$status_flag=1;
			            }
			        	$order_status="Over paid";
			            $order_status_code=COINREMITTER_INV_OVER_PAID;
			        }
			       
			        $u_order_data="UPDATE `coinremitter_order_address` SET `payment_status` = '$order_status_code' WHERE `addr` = '$address' ";
					$update_order_data = $wpdb->get_results($u_order_data);
					$payment_data="UPDATE `coinremitter_payments` SET `status` = '$order_status',`status_code` = '$order_status_code',`is_status_flag`='$status_flag', `paid_amount`='".$total_paidamount."' WHERE `orderID` = '".$get_order_data[0]->orderID."' ";
					
					$update_payment_data = $wpdb->get_results($payment_data);
				}
			}
		}

		$payment_query = "SELECT * FROM coinremitter_payments WHERE orderID = 'coinremitterwc.order".$order_id."'";
			$payment_details = $wpdb->get_results($payment_query);
			$payment_details=$payment_details[0];
			$desc = $payment_details->description;
		    $coin =$payment_details->coinLabel;
			$base_currency=$payment_details->base_currency;
			$status=($payment_details->status=="" ? 'Pending' : $payment_details->status );
			$invoice_url=$payment_details->invoice_url;
			$created_date = $payment_details->createdAt;
			$expiry_date = ($payment_details->expiry_date == "" ? "-" : $payment_details->expiry_date );

			$invoice_id=$payment_details->orderID;
			$order_id = mb_substr($invoice_id, mb_strpos($invoice_id, ".") + 1);
			$order_id =  str_replace("order","",$order_id);
		    $invoice_id =  str_replace("order","",$order_id);

		    if($order_details[0]->invoice_id != "" )
			{
				$payment_history = json_decode($payment_details->payment_history,true);
				$paid_amount = json_decode($payment_details->paid_amount,true);
				$paid_amount=$paid_amount[$coin_type];
				$total_amount = json_decode($payment_details->total_amount,true);
				$total_amount=$total_amount[$coin_type];
			}else{
				$total_amount=$payment_details->total_amount;
				$paid_amount=($payment_details->paid_amount==""? 0 : $payment_details->paid_amount);	
				
			}
			$pending_amount= $total_amount - $paid_amount; 


				
			$sql="SELECT * FROM coinremitter_webhook WHERE order_id='coinremitterwc.order".$order_id."'";
			$webhook = $wpdb->get_results($sql);
			
			

		    $temp2 = "<style>.cr_table table {border:none !important;}</style>";
		    if(!empty($webhook)){

				foreach ($webhook as $value) {
					$temp2.='<tr>
								<td class="label greeColour"><a title="'.__('Transaction Details', COINREMITTER).' - '.$value->tx_id.'" href="'.$value->explorer_url.'" target="_blank"><strong>'.substr($value->tx_id,0,20).'....</strong></a></td>
								<td> '.sprintf('%.8f',$value->paid_amount).' '.$coin.'</td>
								<td>'.$value->created_date.'</td>
								<td style="text-align:center"><img src="'.plugins_url('/images/checked.gif',__FILE__).'"></td>
						</tr>';		
				}

			}else if(isset($payment_history) && is_array($payment_history)){
				foreach ($payment_history as $key => $value) {
					$temp2.='<tr>
								<td class="label greeColour"><a title="'.__('Transaction Details', COINREMITTER).' - '.$value['txid'].'" href="'.$value['explorer_url'].'" target="_blank"><strong>'.substr($value['txid'],0,20).'....</strong></a></td>
							<td> '.sprintf('%.8f',$value['amount']).' '.$coin.'</td>
							<td>'.$value['date'].'</td>
							<td style="text-align:center"><img src="'.plugins_url('/images/checked.gif',__FILE__).'"></td>
						</tr>';		
				}
			}
			else{
				$temp2.='<tr>
							<td colspan="4" style="text-align:center"> - </td>
						</tr>';
			}

	$pending_html ="<div class='inside cr_table' id='postcustomstuff' style='width:25%;float:left;'><table class='wc-crpto-data'>
				<thead>
					<tr>
						<th style='text-align: left' colspan='2'>Pending Amount</th>
					</tr>
				</thead>
				<tbody>
					<table class='wc-crpto-data'>
						<tbody><tr><td>".number_format($pending_amount,8)."</td></tr></tbody>
					</table>
				</tbody>
			</table></div>"; 
	$desc_html ="<div class='inside cr_table' id='postcustomstuff' style='width:25%;float:left;'><table class='wc-crpto-data'>
				<thead>
					<tr>
						<th style='text-align: left' colspan='2'>Description</th>
					</tr>
				</thead>
				<tbody>
					<table class='wc-crpto-data'>
						<tbody><tr><td>".$desc."</td></tr></tbody>
					</table>
				</tbody>
			</table></div>"; 
	$url ="<div class='inside cr_table' id='postcustomstuff'>
					<table class='wc-crpto-data'>

						<thead>
						<tr>
							<th style='text-align: left'>Invoice Url</th>
						</tr>
						</thead>
						<tbody><tr><td><a href='".$invoice_url."' target='_blank'>".$invoice_url."</a></td></tr></tbody>
					</table>
				</div>"; 
	$t_html ="<div class='inside cr_table' id='postcustomstuff' style='width:21%;float:left;'><table class='wc-crpto-data'>
				<thead>
					<tr>
						<th style='text-align: left' colspan='2'>Order Amount</th>
					</tr>
				</thead>
				<tbody>
					<table class='wc-crpto-data'>
						<tbody><tr><td>".number_format($total_amount,8)."</td></tr></tbody>
					</table>
				</tbody>
			</table></div>";
	$p_html ="<div class='inside cr_table' id='postcustomstuff' style='width:21%;float:left;'><table class='wc-crpto-data'>
				<thead>
					<tr>
						<th style='text-align: left' colspan='2'> Paid Amount</th>
					</tr>
				</thead>
				<tbody>
					<table class='wc-crpto-data'>
						<tbody><tr><td>".number_format($paid_amount,8)."</td></tr></tbody>
					</table>
				</tbody>
			</table></div>";


	$detail ="<div class='inside cr_table' id='postcustomstuff'><table class='wc-crpto-data'>
				<thead>
					<tr>
						<th style='text-align: left' colspan='2'>Order Detail</th>
					</tr>
				</thead>
				<tbody>
					<table class='wc-crpto-data'>
								<thead>
									<tr>
										<td class='label'>Invoice Id</td>
										<td >Base Currency</td>
										<td >Coin</td>
										<td >Status</td>
										<td class='total'>Create On</td>
										<td class='total'>Expiry On</td>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td>#".$invoice_id."</td>
										<td>".$base_currency."</td>
										<td>".$coin."</td>
										<td>".$status."</td>
										<td>".$created_date."</td>
										<td>".$expiry_date."</td>

									</tr>
								</tbody>
							</table>
				</tbody>
			</table></div>";

	
	
	$payment_html ='<div class="inside">
			<div class="cr_table" id="postcustomstuff">
					<table >
						<thead>
						<tr>
							<th style="text-align: left" colspan="2">Payment History</th>
						</tr>
						</thead>
						<tbody id="the-list" data-wp-lists="list:meta">
								<tr>
							<table class="wc-crpto-data">
								<thead>
									<tr>
									<td class="label">Transaction Id</td>
									<td >Amount</td>
									<td class="total">Date</td>
									<td style="text-align:center">Confirmation</td>
								</tr>'.$temp2.'
								</thead>
							</table>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>'; 
	echo $detail.$url.$desc_html.$t_html.$p_html.$pending_html.$payment_html;
}  
 
function crypto_amt_on_ord_detail_page( $order_get_id ) { 
   
    global $wpdb;

	$CryptoPrice = get_post_meta( $order_get_id, '_order_crypto_price');
	$CryptoType = get_post_meta( $order_get_id, '_order_crypto_coin');

	

	if(!empty($CryptoPrice)){
		echo  '<tr>
					<td class="label greeColour"><strong>Total '.$CryptoType[0].' :</strong></td>
					<td width="1%"></td>
					<td class="total greeColour"><strong>'.sprintf('%.8f',$CryptoPrice[0]).'</strong></td>
				</tr>';	
				
	}
};

add_action('admin_head', 'hide_wc_refund_button');
function hide_wc_refund_button() {

?>
    <script>
      jQuery(function () {
            jQuery('.refund-items').hide();
            jQuery('.order_actions option[value=send_email_customer_refunded_order]').remove();
        });
    </script>
    <style>

    	.column-crypto_amnt_col{
    		text-align: right !important;
    	}
    	.greeColour{
    		color: #28cc07 !important;
    	}
    	.wc-crpto-data tr td {
		    padding: 8px 15px;
		}
		.wc-crpto-data {
		    width: 100%;
		} 
		/* .conform{
		    
		} */
    </style>
    <?php

}         


function coinremitter_add_wallet(){
	$CoinType = isset($_POST['cointype']) ? sanitize_text_field($_POST['cointype']) : '';
	$CoinAPIKey = isset($_POST['coinapikey']) ? sanitize_text_field($_POST['coinapikey']) : '';
	$CoinPass = isset($_POST['coinpass']) ? sanitize_text_field($_POST['coinpass']) : '';
	$MinInvoiceValue = isset($_POST['coinmininvoicevalue']) ? sanitize_text_field($_POST['coinmininvoicevalue']) : '';
	$ExchangeRateMultiplier = isset($_POST['coinexchangeratemult']) ? sanitize_text_field($_POST['coinexchangeratemult']) : '';
	$frm_type = isset($_POST['frm_type']) ? sanitize_text_field($_POST['frm_type']) : '';
	$ConResp = coinremitter_checkConn($CoinAPIKey, $CoinPass, $CoinType);
	if($ConResp['flag'] != 1){

		$Result['msg'] = $ConResp['msg'];//$erroMsg;
		$Result['flag'] = $ConResp['flag'];
	}else{
		if($MinInvoiceValue == ''){
			$Result['msg'] = 'Minimum invoice value required';//$erroMsg;
			$Result['flag'] = 0;
			echo json_encode($Result);
			exit(0);
		} else if($MinInvoiceValue < 0.00001) {
			$Result['msg'] = 'Minimum invoice value must be greater than 0.00001';//$erroMsg;
			$Result['flag'] = 0;
			echo json_encode($Result);
			exit(0);
			
		} else if($MinInvoiceValue > 1000000) {
			$Result['msg'] = 'Minimum invoice value must be less than 1000000';//$erroMsg;
			$Result['flag'] = 0;
			echo json_encode($Result);
			exit(0);
			
		} else if(!preg_match('/^[0-9]+(\.[0-9]{1,8})?$/', $MinInvoiceValue)){
			$Result['msg'] = 'Minimum invoice value accept only 8 digit after decimal point';//$erroMsg;
			$Result['flag'] = 0;
			echo json_encode($Result);
			exit(0);
		}

		if($ExchangeRateMultiplier == ''){
			$Result['msg'] = 'Exchange rate multiplier required';//$erroMsg;
			$Result['flag'] = 0;
			echo json_encode($Result);
			exit(0);
		} else if($ExchangeRateMultiplier <= 0) {
			$Result['msg'] = 'Exchange rate multiplier must be greater than 0';//$erroMsg;
			$Result['flag'] = 0;
			echo json_encode($Result);
			exit(0);
		} else if($ExchangeRateMultiplier >= 101) {
			$Result['msg'] = 'Exchange rate multiplier must be less than 101';//$erroMsg;
			$Result['flag'] = 0;
			echo json_encode($Result);
			exit(0);
		} else if(!preg_match('/^[0-9]+(\.[0-9]{1,2})?$/', $ExchangeRateMultiplier)){
				$Result['msg'] = 'Exchange rate multiplier accept only 2 digit after decimal point';//$erroMsg;
				$Result['flag'] = 0;
				echo json_encode($Result);
				exit(0);
		} 


		$file_name=strtolower($CoinType).".png";
		$permfile = COINREMITTER_DIR_PATH."images/".$file_name;
		if(!file_exists($permfile)){
        $url = COINREMITTER_API_URL_ALL_COIN."assets/img/home-coin/coin/".$file_name;
			if (@getimagesize($url)) {
				$tmpfile = download_url( $url, $timeout = 300 );
				copy( $tmpfile, $permfile );
				unlink( $tmpfile );
			} 
		}
		
		$amount = $ConResp['data']['balance'];
		$wallet_name = $ConResp['data']['wallet_name'];
		update_option(COINREMITTER.strtolower($CoinType).'api_key', maybe_serialize($CoinAPIKey));
		update_option(COINREMITTER.strtolower($CoinType).'password', maybe_serialize(encrypt($CoinPass)));
		update_option(COINREMITTER.strtolower($CoinType).'min_invoice_value', maybe_serialize($MinInvoiceValue));
		update_option(COINREMITTER.strtolower($CoinType).'exchange_rate_multiplier', maybe_serialize($ExchangeRateMultiplier));
		update_option(COINREMITTER.strtolower($CoinType).'amount', $amount);
		update_option(COINREMITTER.strtolower($CoinType).'wallet_name', $wallet_name);
		$Result['msg'] = 'Wallet successfully Added.';
		$Result['flag'] = $ConResp['flag'];
	}
	echo json_encode($Result);
	exit(0);
}
function coinremitter_webhook_data()
{
	global $wpdb;	
	$addr = isset($_GET['addr']) ? sanitize_text_field($_GET['addr']) : '';
	$order_query = "SELECT * FROM coinremitter_order_address WHERE addr = '".$addr."' ";
	
	$order_data = $wpdb->get_results($order_query);
	$order_id = mb_substr($order_data[0]->orderID, mb_strpos($order_data[0]->orderID, ".") + 1);
    $order_id =  str_replace("order","",$order_id);
				
	$order = new WC_Order($order_id);
	$order_key = $order->get_order_key();
	
	$web_hook_data="";
	if(count($order_data) > 0 )
	{
		if($order_data[0]->payment_status== COINREMITTER_INV_PAID || $order_data[0]->payment_status== COINREMITTER_INV_OVER_PAID ){

			$url= site_url("checkout/?order-received=".$order_id."&key=".$order_key."");
			$Result['link'] = $url;
			$Result['flag'] = '2';
			echo json_encode($Result);
			exit(0);
		}
		else if($order_data[0]->payment_status== COINREMITTER_INV_EXPIRED || $order->get_status() == 'cancelled' ){
			
			$url= $order->get_cancel_order_url();
			$Result['link'] = $url;
			$Result['flag'] = '2';
			echo json_encode($Result);
			exit(0);
		}


		$order_data_query = "SELECT * FROM coinremitter_payments WHERE orderID = 'coinremitterwc.order".$order_id."'";
		$order_details = $wpdb->get_results($order_data_query);
		
		$status=$order_details[0]->status_code;
		$coin=$order_details[0]->coinLabel;

		if($status == COINREMITTER_INV_PENDING || $status == COINREMITTER_INV_UNDER_PAID )
		{
			$param=[
				'coin'=> $coin,
				'address'=>$addr,
			];
			$transaction_data=coinremitter_geTransaction_by_address($param);

			$paid_amount=0;

			if(isset($transaction_data['flag']) && $transaction_data['flag'] == 1)
			{
				$transaction=$transaction_data['data'];
				foreach ($transaction as $t) {
					
					if( $t['type'] == 'receive' )
					{
						$id=$t['id'];
						$date=gmdate('Y-m-d H:i:s');
						$txid=$t['txid'];
						$amount=$t['amount'];
						$explorer_url=$t['explorer_url'];
						$confirmations=$t['confirmations'];
						if($confirmations >= 3)
						{
							$paid_amount +=$amount;
						}
						
						$query="SELECT * FROM coinremitter_webhook WHERE `transaction_id` = '".$id."' ";
						$transtion_entry = $wpdb->get_results($query);
						if(count($transtion_entry) > 0 )
						{
					
							if($confirmations < 3){
			                    $confirmations_order=$confirmations;
			                }else{
			                    $confirmations_order=3;
			                }

							$sql = "UPDATE `coinremitter_webhook` SET 
							`confirmation`='$confirmations_order' , `updated_date`='$date' WHERE `transaction_id`= '".$id."'";
							
							$update = $wpdb->get_results($sql);
						}else{
							
							$sql = "INSERT INTO coinremitter_webhook ( order_id, transaction_id,addr, tx_id,explorer_url,paid_amount,coin,confirmation,paid_date,created_date,updated_date)
                                VALUES ('".$order_details[0]->orderID."', '".$id."', '".$addr."', '".$txid."','".$explorer_url."','".$amount."', '".$coin."','".$confirmations."','".$date."','".$date."','".$date."')";
                            
                            $inserted = $wpdb->get_results($sql);
						}
					}
					
				}
				$total_paidamount=number_format($paid_amount,8);
				$total_amount=$order_details[0]->total_amount;
				
				$order_status="";
		        $order_status_code="";
		        $option_data = get_option('woocommerce_coinremitterpayments_settings');
		        $ostatus = $option_data['ostatus']; 
		        if($total_paidamount == 0){
		        	$order_status="Pending";
		        	$order->update_status('pending');
		            $order_status_code=COINREMITTER_INV_PENDING;
		        }else if($total_amount > $total_paidamount ){
		            $order_status="Under paid";
		        	$order->update_status('pending');
		            $order_status_code=COINREMITTER_INV_UNDER_PAID;
		        }else if($total_amount < $total_paidamount){
		            $order_status="Over paid";
		            $order->update_status('wc-'.$ostatus);
		        	$order_status_code=COINREMITTER_INV_OVER_PAID;
		        }else if($total_amount == $total_paidamount){
		        	$order->update_status('wc-'.$ostatus);
		            $order_status="Paid";
		            $order_status_code=COINREMITTER_INV_PAID;
		        }
				$u_order_data="UPDATE `coinremitter_order_address` SET `payment_status` = '$order_status_code' WHERE `addr` = '$addr' ";
				$update_order_data = $wpdb->get_results($u_order_data);

				$payment_data="UPDATE `coinremitter_payments` SET `status` = '$order_status',`status_code` = '$order_status_code' ,`paid_amount` = '$total_paidamount' WHERE `orderID` = '".$order_details[0]->orderID."' ";
				$update_payment_data = $wpdb->get_results($payment_data);
			}
		}
		
		$order_query = "SELECT * FROM coinremitter_order_address WHERE addr = '".$addr."' ";
		$order_data = $wpdb->get_results($order_query);

		if($order_data[0]->payment_status== COINREMITTER_INV_PAID || $order_data[0]->payment_status== COINREMITTER_INV_OVER_PAID ){
				$url= site_url("checkout/?order-received=".$order_id."&key=".$order_key."");
				$Result['link'] = $url;
				$Result['flag'] = '2';
				echo json_encode($Result);
				exit(0);
		}
	
		$payment_query = "SELECT * FROM coinremitter_webhook WHERE addr = '".$addr."'";
		$payment_webhook = $wpdb->get_results($payment_query);
		

		$noexpitytime=0;
		

		$expiry_time="SELECT * FROM coinremitter_payments WHERE `orderID` = '".$order_details[0]->orderID."' ";
		
		$payment_data = $wpdb->get_results($expiry_time);
		$expiry=$payment_data[0]->expiry_date;
		$total_amount=$payment_data[0]->total_amount;
		$total_paidamount=($payment_data[0]->paid_amount == "" ? 0 : $payment_data[0]->paid_amount);

		$padding_amount=($total_amount - $total_paidamount);

		if(count($payment_webhook)> 0  || $expiry == ""){
			$noexpitytime=1;
		}else{
			$expiry=date("M d, Y H:i:s", strtotime($expiry));
		}
		$web_hook_data.="<input type='hidden' id='expiry_time' value='".$expiry."'>";
		if(count($payment_webhook) > 0)
		{
			foreach($payment_webhook as $web)
			{
				$create_date = strtotime($web->created_date);             // returns bool(false)
				$c_date = date('Y-m-d H:i:s', $create_date);
				$seconds = strtotime(gmdate('Y-m-d H:i:s')) - strtotime($web->created_date);
				$years = floor($seconds / (365*60*60*24));
				$months = floor(($seconds - $years * 365*60*60*24) / (30*60*60*24));
				$days    = floor($seconds / 86400);
				$hours   = floor(($seconds - ($days * 86400)) / 3600);
				$minutes = floor(($seconds - ($days * 86400) - ($hours * 3600))/60);
				$seconds = floor(($seconds - ($days * 86400) - ($hours * 3600) - ($minutes*60)));
				if($years > 0)
				{
					$diff=$years ." year(s) ago";	
				}else if ($months > 0) {
					$diff=$months ." month(s) ago";	
				}else if($days > 0){
					$diff=$days." day(s) ago";
				}else if($hours > 0){
					$diff=$hours." hour(s) ago";
				}else if($minutes >0 ){
					$diff=$minutes." minute(s) ago";
				}else {
					$diff=$seconds." second(s) ago";
				}
				$c_date=date("M d, Y H:i:s", strtotime($web->created_date));

				if($web->confirmation >= 3){
					$icon='<div class="cr-plugin-history-ico" title="Payment Confirmed" ><i class="fa fa-check"></i></div>';
				}else{
					$icon='<div class="cr-plugin-history-ico" title="'.$web->confirmation.' confirmation(s)" style="background-color: #FF8A4F;"><i class="fa fa-exclamation"></i></div>';
				}
				$web_hook_data.='<div class="cr-plugin-history-box">
	                                 <div class="cr-plugin-history">
	                                 '.$icon.'
	                                    <div class="cr-plugin-history-des">
	                                       <span><a href="'.$web->explorer_url.'" target="_blank">'.substr($web->tx_id,0,30).'...</a></span>
	                                       <p>'.number_format($web->paid_amount,8).' '.$web->coin.'</p>
	                                    </div>
	                                    <div class="cr-plugin-history-date" title="'.$c_date.' (UTC)"><span>'.$diff.'</span></div>
	                                 </div>
	                              </div>';
			}

		}else{
			$web_hook_data.='<div class="cr-plugin-history-box">
                                 <div class="cr-plugin-history" style="text-align: center;">
                                 	<span>No payment history found</span>
                                </div>
                            </div>';
		}
			
	}
	if($padding_amount < 0 )
	{
		$padding_amount=0;
	}

	$Result['expiry']=$noexpitytime;
	$Result['paid_amount']=number_format($total_paidamount,8);
	$Result['padding_amount']=number_format($padding_amount,8);
	$Result['data'] = $web_hook_data;
	$Result['flag'] = '1';
	echo json_encode($Result);
	exit(0);
}
add_action('wp_ajax_coinremitter_add_wallet', 'coinremitter_add_wallet');
add_action('wp_ajax_nopriv_coinremitter_add_wallet', 'coinremitter_add_wallet');

add_action('wc_ajax_coinremitter_webhook_data', 'coinremitter_webhook_data');
add_action('wc_ajax_coinremitter_cancel_order', 'coinremitter_cancel_order');

function coinremitter_cancel_order(){
	global $wpdb;
	$order_id = isset($_GET['order_id']) ? $_GET['order_id']: '';
		$order = wc_get_order( $order_id );
		$order_canclled=$order->get_cancel_order_url();


	$order_data = "SELECT * FROM coinremitter_payments WHERE orderID = 'coinremitterwc.order".$order_id."'";
	$order_details = $wpdb->get_results($order_data);
	
	$webhook_data = "SELECT * FROM coinremitter_webhook WHERE order_id = 'coinremitterwc.order".$order_id."'";
	$web_hook = $wpdb->get_results($webhook_data);
	$expiry_date=$order_details[0]->expiry_date;
	
	$diff=strtotime($expiry_date)- strtotime(gmdate('Y-m-d H:i:s'));	
	
	if(count($web_hook) == 0  && $diff < 0 )
	{
		$order->update_status('cancelled');
		$status_code=COINREMITTER_INV_EXPIRED;
		$status='Expired';
		$sql="UPDATE coinremitter_payments SET `status_code`='".$status_code."',`status` = '".$status."' WHERE  `orderID` = 'coinremitterwc.order".$order_id."' ";	
		$wpdb->get_results($sql);

		$sql="UPDATE coinremitter_order_address SET `payment_status`='".$status_code."' WHERE  `orderID` = 'coinremitterwc.order".$order_id."' ";	
		$wpdb->get_results($sql);
	
	}
	$Result['url']=$order_canclled;
	$Result['flag']=1;
	echo json_encode($Result);
	exit(0);
}


function coinremitter_checkConn($CoinAPIKey, $CoinPass, $CoinType){
	$CoinType = strtoupper($CoinType);
	$postdata = array('api_key' => $CoinAPIKey, 'password'=>$CoinPass);
	$curl = COINREMITTER_API_URL.COINREMITTER_API_VERSION.'/'.$CoinType.'/get-balance';
	$userAgent = 'CR@'.COINREMITTER_API_VERSION.',wordpress worwoocommerce-wordpress-master@'.COINREMITTER_VERSION;
    $body = array(
        'api_key' => $CoinAPIKey,
        'password' => $CoinPass,
        );
    $args = array(
        'method'      => 'POST',
        'timeout'     => 45,
        'sslverify'   => false,
        'user-agent'  => $userAgent,
        'headers'     => array(
            'Content-Type'  => 'application/json',
        ),
        'body'        => json_encode($body),
    );
    $request = wp_remote_post( $curl, $args );
    if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
        error_log( print_r( $request, true ) );
    }

    $response = wp_remote_retrieve_body( $request );
   
    return json_decode($response,true);
   
}

function coinremitter_getBalanceByCurl($Parm = ''){
	
	$Coin = $Parm['cointype'];
    $curl = COINREMITTER_API_URL.COINREMITTER_API_VERSION.'/'.strtoupper($Coin).'/get-balance';
    $userAgent = 'CR@'.COINREMITTER_API_VERSION.',wordpress worwoocommerce-wordpress-master@'.COINREMITTER_VERSION;
    $body = array(
        'api_key' => $Parm['api_key'],
        'password' => decrypt($Parm['password']),
        );
    $args = array(
        'method'      => 'POST',
        'timeout'     => 45,
        'sslverify'   => false,
        'user-agent'  => $userAgent,
        'headers'     => array(
            'Content-Type'  => 'application/json',
        ),
        'body'        => json_encode($body),
    );
    $request = wp_remote_post( $curl, $args );
    if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
        error_log( print_r( $request, true ) );
    }

    $response = wp_remote_retrieve_body( $request );
    $ResultArr = json_decode($response,true);
    return $ResultArr;
}


add_action('wp_ajax_coinremitter_verifyApi', 'coinremitter_verifyApi');
add_action('wp_ajax_nopriv_coinremitter_verifyApi', 'coinremitter_verifyApi');
function coinremitter_verifyApi(){

	$CoinType = sanitize_text_field($_POST['cointype']);

	$MinInvoiceValue = isset($_POST['coinmininvoicevalue']) ? sanitize_text_field($_POST['coinmininvoicevalue']) : '';
	$ExchangeRateMultiplier = isset($_POST['coinexchangeratemult']) ? sanitize_text_field($_POST['coinexchangeratemult']) : '';

	$postdata = array('api_key' => sanitize_text_field($_POST['coinapikey']), 'password'=>sanitize_text_field($_POST['coinpass']));
	$curl = COINREMITTER_API_URL.COINREMITTER_API_VERSION.'/'.strtoupper($CoinType).'/get-balance';
	$userAgent = 'CR@'.COINREMITTER_API_VERSION.',wordpress worwoocommerce-wordpress-master@'.COINREMITTER_VERSION;
    $body = array(
        'api_key' => sanitize_text_field($_POST['coinapikey']),
        'password' => sanitize_text_field($_POST['coinpass']),
        );
    $args = array(
        'method'      => 'POST',
        'timeout'     => 45,
        'sslverify'   => false,
        'user-agent'  => $userAgent,
        'headers'     => array(
            'Content-Type'  => 'application/json',
        ),
        'body'        => json_encode($body),
    );
    $request = wp_remote_post( $curl, $args );
    if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
        error_log( print_r( $request, true ) );
    }
    $response = wp_remote_retrieve_body( $request );
    $Result = json_decode($response,true);
    if($Result['flag'] == 1){

		if($MinInvoiceValue == ''){
			$Result['msg'] = 'Minimum invoice value required';//$erroMsg;
			$Result['flag'] = 0;
			echo json_encode($Result);
			exit(0);
		} else if($MinInvoiceValue < 0.00001) {
			$Result['msg'] = 'Minimum invoice value must be greater than 0.00001';//$erroMsg;
			$Result['flag'] = 0;
			echo json_encode($Result);
			exit(0);
			
		} else if($MinInvoiceValue > 1000000) {
			$Result['msg'] = 'Minimum invoice value must be less than 1000000';//$erroMsg;
			$Result['flag'] = 0;
			echo json_encode($Result);
			exit(0);
			
		} else if(!preg_match('/^[0-9]+(\.[0-9]{1,8})?$/', $MinInvoiceValue)){
			$Result['msg'] = 'Minimum invoice value accept only 8 digit after decimal point';//$erroMsg;
			$Result['flag'] = 0;
			echo json_encode($Result);
			exit(0);
		}

		if($ExchangeRateMultiplier == ''){
			$Result['msg'] = 'Exchange rate multiplier required';//$erroMsg;
			$Result['flag'] = 0;
			echo json_encode($Result);
			exit(0);
		} else if($ExchangeRateMultiplier <= 0) {
			$Result['msg'] = 'Exchange rate multiplier must be greater than 0';//$erroMsg;
			$Result['flag'] = 0;
			echo json_encode($Result);
			exit(0);
		} else if($ExchangeRateMultiplier >= 101) {
			$Result['msg'] = 'Exchange rate multiplier must be less than 101';//$erroMsg;
			$Result['flag'] = 0;
			echo json_encode($Result);
			exit(0);
		} else if(!preg_match('/^[0-9]+(\.[0-9]{1,2})?$/', $ExchangeRateMultiplier)){
				$Result['msg'] = 'Exchange rate multiplier accept only 2 digit after decimal point';//$erroMsg;
				$Result['flag'] = 0;
				echo json_encode($Result);
				exit(0);
		} 

    	update_option(COINREMITTER.strtolower($CoinType).'api_key', maybe_serialize($_POST['coinapikey']));
		update_option(COINREMITTER.strtolower($CoinType).'password', maybe_serialize(encrypt($_POST['coinpass'])));	
		update_option(COINREMITTER.strtolower($CoinType).'min_invoice_value', maybe_serialize($MinInvoiceValue));
		update_option(COINREMITTER.strtolower($CoinType).'exchange_rate_multiplier', maybe_serialize($ExchangeRateMultiplier));
		update_option(COINREMITTER.strtolower($CoinType).'wallet_name', $Result['data']['wallet_name']);
		update_option(COINREMITTER.strtolower($CoinType).'amount',$Result['data']['balance']);	
    }
    echo $response;
	exit(0);
}

function coinremitter_deleteCoinData(){

	global $wpdb;
	$CoinType = sanitize_text_field($_POST['cointype']);
	$tablename = $wpdb->prefix."options";
	$Opt_Field1 = 'coinremitter'.strtolower($CoinType).'api_key';
	$Opt_Field2 = 'coinremitter'.strtolower($CoinType).'password';
	$Opt_Field3 = 'coinremitter'.strtolower($CoinType).'min_invoice_value';
	$Opt_Field4 = 'coinremitter'.strtolower($CoinType).'exchange_rate_multiplier';
	$Opt_Field5 = 'coinremitter'.strtolower($CoinType).'amount';
	$Opt_Field6 = 'coinremitter'.strtolower($CoinType).'wallet_name';

	$results = $wpdb->delete( $tablename, array( 'option_name' => $Opt_Field1) );
	$results2 = $wpdb->delete( $tablename, array( 'option_name' => $Opt_Field2 ) );
	$results3 = $wpdb->delete( $tablename, array( 'option_name' => $Opt_Field3 ) );
	$results4 = $wpdb->delete( $tablename, array( 'option_name' => $Opt_Field4 ) );
	$results5 = $wpdb->delete( $tablename, array( 'option_name' => $Opt_Field5 ) );
	$results6 = $wpdb->delete( $tablename, array( 'option_name' => $Opt_Field6 ) );
	
	if($results){
		$Redirect = COINREMITTER_ADMIN.'coinremitter&updated=true&delete=true';	
		$Return['redirect'] = $Redirect;
		$Return['flag'] = 1;
	}else{
		$Return['redirect'] = '';
		$Return['flag'] = 0;
	}
	echo json_encode($Return);
	exit(0);
}	
add_action('wp_ajax_coinremitter_deleteCoinData', 'coinremitter_deleteCoinData');
add_action('wp_ajax_nopriv_coinremitter_deleteCoinData', 'coinremitter_deleteCoinData');

function coinremitter_myplugin_activate() {
   echo "<div class='updated'><h3>Welcome to [name]</h3>";
   register_uninstall_hook( __FILE__, 'coinremitter_uninstall' );
}
register_activation_hook( __FILE__, 'coinremitter_myplugin_activate' );
function coinremitter_sample_admin_notice__success(){
	$PageUrl = $_SERVER['REQUEST_URI'];
	if (strpos($PageUrl, 'plugins.php') !== false) {
	    $Basepath = COINREMITTER_ADMIN.'coinremitter';
		$message = '<div class="updated updated notice notice-success is-dismissible" style="padding: 1px 5px 1px 12px; margin: 0; border: none; background: none; border-left: 4px solid #46b450; margin-top:10px; background-color:#ffffff;"><p>Coinremitter plugin activated. Now check add wallet to accept crypto payment. <a href="'.$Basepath.'" > Click here</a></div>';	
		echo $message;
	}
	
}
function coinremitter_uninstall(){
	
}
  
add_action('admin_head', 'my_custom_style');
function my_custom_style() {
    echo '<style>
            #woocommerce-order-notes .inside ul.order_notes li.system-note .note_content {
			    background: #fac876;
			}
			#woocommerce-order-notes .inside ul.order_notes li.system-note .note_content::after {
			    border-color: #fac876 transparent;
			}
            </style>';
    
}

function coinremitter_getActCoins(){
	$url = COINREMITTER_API_URL_ALL_COIN.'get-coins';
	$userAgent = 'CR@'.COINREMITTER_API_VERSION.',wordpress worwoocommerce-wordpress-master@'.COINREMITTER_VERSION;

	$arrg = array(
				'headers'     => array(
	    			'Content-Type'  => 'application/json',
				),
				'timeout'     => (isset($timeout) ? $timeout : ''),
				'sslverify' => false,
				'user-agent' => $userAgent,
			);
     
    $request = wp_remote_get( $url, $arrg);
    if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
        error_log( print_r( $request, true ) );
    }
    $response = wp_remote_retrieve_body( $request );
    
    $responseArr = json_decode($response,true);
	if($response){
		if($responseArr['flag'] == 1){
			return $responseArr['data'];
		}
	} else {
		// $error = new WP_Error( 'Notice : ', _e( '<div class="notice notice-danger is-dismissible"><p> Oops! Something went wrong </div></p>', "my_textdomain" ) );
	}
}
add_filter( 'woocommerce_my_account_my_orders_actions', 'change_my_account_my_orders_view_text_button', 10, 2 );

function change_my_account_my_orders_view_text_button( $actions, $order ) {
	if( is_wc_endpoint_url( 'orders' ) ){
		$order_id=(true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->id : $order->get_id();

		$payment_method = get_post_meta( $order_id, '_payment_method', true );
		if($payment_method == 'coinremitterpayments'){
			global $wpdb;
			$order_data = "SELECT * FROM coinremitter_payments WHERE orderID = 'coinremitterwc.order".$order_id."'";
			$order_details = $wpdb->get_results($order_data);
					
			if(!empty($order_details) && $order_details[0]->invoice_id == "" )
			{
						
				$query = "SELECT * FROM coinremitter_order_address WHERE orderID = 'coinremitterwc.order".$order_id."'";
				$get_order_data = $wpdb->get_results($query);
				if(count($get_order_data) < 1)
				{
					return "";
				}
				$coin_type = $get_order_data[0]->coinLabel;
				$address = $get_order_data[0]->addr;
				$expiry_date=$order_details[0]->expiry_date;
				$order = wc_get_order( $order_id );
			    $webhook_data = "SELECT * FROM coinremitter_webhook WHERE `addr` = '".$address."' ";
					$web_hook = $wpdb->get_results($webhook_data);
					if($expiry_date != "" ){
						$diff=strtotime($expiry_date)- strtotime(gmdate('Y-m-d H:i:s'));
					}
					if(count($web_hook) == 0  && isset($diff) && $diff <= 0 ){
			        	$order->update_status('cancelled');
						$order_status_code = COINREMITTER_INV_EXPIRED;
						$order_status='Expired';

						$u_order_data="UPDATE `coinremitter_order_address` SET `payment_status` = '$order_status_code' WHERE `addr` = '$address' ";
						$update_order_data = $wpdb->get_results($u_order_data);

						$payment_data="UPDATE `coinremitter_payments` SET `status` = '$order_status',`status_code` = '$order_status_code' WHERE `orderID` = '".$get_order_data[0]->orderID."' ";
						
						$update_payment_data = $wpdb->get_results($payment_data);
					}
				$order_data = "SELECT * FROM coinremitter_payments WHERE orderID = 'coinremitterwc.order".$order_id."'";
				$order_details = $wpdb->get_results($order_data);
				$order = wc_get_order( $order_id );
				$status=$order_details[0]->status_code;
				if($status == COINREMITTER_INV_PENDING || $status == COINREMITTER_INV_UNDER_PAID )
				{
					$param=[
						'coin'=> $coin_type,
						'address'=>$address,
					];
					$transaction_data=coinremitter_geTransaction_by_address($param);
					$paid_amount=0;

					if(isset($transaction_data['flag']) && $transaction_data['flag']==1)
					{
						$transaction=$transaction_data['data'];
						foreach ($transaction as $t) {
							if( $t['type'] == 'receive' ){
								$id=$t['id'];
								$date=gmdate('Y-m-d H:i:s');
								$txid=$t['txid'];
								$amount=$t['amount'];
								$explorer_url=$t['explorer_url'];
								$confirmations=$t['confirmations'];
								if($confirmations >= 3){
									$paid_amount += $amount;
								}
									
								$query="SELECT * FROM coinremitter_webhook WHERE `transaction_id` = '".$id."' ";
								$transtion_entry = $wpdb->get_results($query);
								if(count($transtion_entry) > 0 ){
									if($confirmations < 3){
				                    	$confirmations_order=$confirmations;
				                	}else{
				                    	$confirmations_order=3;
				                	}

									$sql = "UPDATE `coinremitter_webhook` SET 
										`confirmation`='$confirmations_order' , `updated_date`='$date' WHERE `transaction_id`= '".$id."'";
								
									$update = $wpdb->get_results($sql);
								}else{
									
									$sql = "INSERT INTO coinremitter_webhook ( order_id, transaction_id,addr, tx_id,explorer_url,paid_amount,coin,confirmation,paid_date,created_date,updated_date)
	                                VALUES ('".$order_details[0]->orderID."', '".$id."', '".$address."', '".$txid."','".$explorer_url."','".$amount."', '".$coin_type."','".$confirmations."','".$date."','".$date."','".$date."')";
	                            
	                            	$inserted = $wpdb->get_results($sql);
								}
							}
						}
					
						$total_paidamount=number_format($paid_amount,8);
						$total_amount=$order_details[0]->total_amount;
						$order_status="";
				        $order_status_code="";
				        $option_data = get_option('woocommerce_coinremitterpayments_settings');
			            $ostatus = $option_data['ostatus'];
			            if($total_paidamount == 0){
					        	$order_status="Pending";
				        	$order->update_status('pending');
				            $order_status_code=COINREMITTER_INV_PENDING;
				        }else if($total_amount > $total_paidamount ){
				            $order_status="Under paid";
				        	$order->update_status('pending');
				            $order_status_code=COINREMITTER_INV_UNDER_PAID;
				        }else if($total_amount == $total_paidamount){
				            $order_status="Paid ";
				            $order->update_status('wc-'.$ostatus);
				            $order_status_code=COINREMITTER_INV_PAID;
				        }else if($total_amount < $total_paidamount){
				        	$order->update_status('wc-'.$ostatus);
				            $order_status="Over paid";
				            $order_status_code=COINREMITTER_INV_OVER_PAID;
				        }
						$u_order_data="UPDATE `coinremitter_order_address` SET `payment_status` = '$order_status_code' WHERE `addr` = '$address' ";
						$update_order_data = $wpdb->get_results($u_order_data);

						$payment_data="UPDATE `coinremitter_payments` SET `status` = '$order_status',`status_code` = '$order_status_code', `paid_amount`='".$total_paidamount."' WHERE `orderID` = '".$get_order_data[0]->orderID."' ";
						
						$update_payment_data = $wpdb->get_results($payment_data);
					}
				}

			}
			$order_data = "SELECT * FROM coinremitter_payments WHERE orderID = 'coinremitterwc.order".$order_id."'";
			$order_details = $wpdb->get_results($order_data);
			
			$webhook="SELECT * FROM coinremitter_webhook WHERE order_id = 'coinremitterwc.order".$order_id."' ";
			$webhook_data = $wpdb->get_results($webhook);

			
			if(count($order_details) > 0){
				$url = $order_details[0]->invoice_url;
				if($order_details[0]->status_code == COINREMITTER_INV_UNDER_PAID || count($webhook_data) > 0 )
				{
					unset($actions['cancel']); 
				}
				if( isset( $actions['pay']['url'] ) ) {
			        $actions['pay']['url'] = $url;
			    }
        	}
		}
	}
	return $actions;
}
add_action( 'woocommerce_order_status_cancelled', 'change_status_to_refund', 1 );

function change_status_to_refund( $order_id ){
		global $wpdb;
		//webhook data
		$webhook_data = "SELECT * FROM coinremitter_webhook WHERE order_id = 'coinremitterwc.order".$order_id."'";
		$web_hook = $wpdb->get_results($webhook_data);
		
		//payment data
		$payment_data = "SELECT * FROM coinremitter_payments WHERE orderID = 'coinremitterwc.order".$order_id."'";
		$payment_details = $wpdb->get_results($payment_data);
		//order data
		$order_data = "SELECT * FROM coinremitter_order_address WHERE orderID = 'coinremitterwc.order".$order_id."'";
		$order_details = $wpdb->get_results($order_data);

		

		$expiry_date=$payment_details[0]->expiry_date;
		$status=$payment_details[0]->status_code;
		$address = $order_details[0]->addr;
		
		$order = wc_get_order( $order_id );
        if(count($web_hook) == 0 ){

        	$order->update_status('cancelled');
			$order_status_code=COINREMITTER_INV_EXPIRED;
			$order_status='Expired';

			$u_order_data="UPDATE `coinremitter_order_address` SET `payment_status` = '$order_status_code' WHERE `addr` = '$address' ";
			$update_order_data = $wpdb->get_results($u_order_data);
			$payment_data="UPDATE `coinremitter_payments` SET `status` = '$order_status',`status_code` = '$order_status_code' WHERE `orderID` = '".$order_details[0]->orderID."' ";
			$update_payment_data = $wpdb->get_results($payment_data);
		}else{

			$order_data = "SELECT * FROM coinremitter_payments WHERE orderID = 'coinremitterwc.order".$order_id."'";
			$order_details = $wpdb->get_results($order_data);
					
			if(!empty($order_details) && $order_details[0]->invoice_id == "" )
			{
						
				$query = "SELECT * FROM coinremitter_order_address WHERE orderID = 'coinremitterwc.order".$order_id."'";
				$get_order_data = $wpdb->get_results($query);
				if(count($get_order_data) < 1)
				{
					return "";
				}
				$coin_type = $get_order_data[0]->coinLabel;
				$address = $get_order_data[0]->addr;
				$expiry_date=$order_details[0]->expiry_date;
				$order = wc_get_order( $order_id );
			    $webhook_data = "SELECT * FROM coinremitter_webhook WHERE `addr` = '".$address."' ";
					$web_hook = $wpdb->get_results($webhook_data);
					if($expiry_date != "" ){
						$diff=strtotime($expiry_date)- strtotime(gmdate('Y-m-d H:i:s'));
					}
					if(count($web_hook) == 0  && isset($diff) && $diff <= 0 ){
			        	$order->update_status('cancelled');
						$order_status_code = COINREMITTER_INV_EXPIRED;
						$order_status='Expired';

						$u_order_data="UPDATE `coinremitter_order_address` SET `payment_status` = '$order_status_code' WHERE `addr` = '$address' ";
						$update_order_data = $wpdb->get_results($u_order_data);

						$payment_data="UPDATE `coinremitter_payments` SET `status` = '$order_status',`status_code` = '$order_status_code' WHERE `orderID` = '".$get_order_data[0]->orderID."' ";
						
						$update_payment_data = $wpdb->get_results($payment_data);
					}
				$order_data = "SELECT * FROM coinremitter_payments WHERE orderID = 'coinremitterwc.order".$order_id."'";
				$order_details = $wpdb->get_results($order_data);
				$order = wc_get_order( $order_id );
				$status=$order_details[0]->status_code;
				if($status == COINREMITTER_INV_PENDING || $status == COINREMITTER_INV_UNDER_PAID )
				{
					$param=[
						'coin'=> $coin_type,
						'address'=>$address,
					];
					$transaction_data=coinremitter_geTransaction_by_address($param);
					$paid_amount=0;

					if(isset($transaction_data['flag']) && $transaction_data['flag']==1)
					{
						$transaction=$transaction_data['data'];
						foreach ($transaction as $t) {
							if( $t['type'] == 'receive' ){
								$id=$t['id'];
								$date=gmdate('Y-m-d H:i:s');
								$txid=$t['txid'];
								$amount=$t['amount'];
								$explorer_url=$t['explorer_url'];
								$confirmations=$t['confirmations'];
								if($confirmations >= 3){
									$paid_amount += $amount;
								}
									
								$query="SELECT * FROM coinremitter_webhook WHERE `transaction_id` = '".$id."' ";
								$transtion_entry = $wpdb->get_results($query);
								if(count($transtion_entry) > 0 ){
									if($confirmations < 3){
				                    	$confirmations_order=$confirmations;
				                	}else{
				                    	$confirmations_order=3;
				                	}

									$sql = "UPDATE `coinremitter_webhook` SET 
										`confirmation`='$confirmations_order' , `updated_date`='$date' WHERE `transaction_id`= '".$id."'";
								
									$update = $wpdb->get_results($sql);
								}else{
									
									$sql = "INSERT INTO coinremitter_webhook ( order_id, transaction_id,addr, tx_id,explorer_url,paid_amount,coin,confirmation,paid_date,created_date,updated_date)
	                                VALUES ('".$order_details[0]->orderID."', '".$id."', '".$address."', '".$txid."','".$explorer_url."','".$amount."', '".$coin_type."','".$confirmations."','".$date."','".$date."','".$date."')";
	                            
	                            	$inserted = $wpdb->get_results($sql);
								}
							}
						}
						$total_paidamount=number_format($paid_amount,8);
						$total_amount=$order_details[0]->total_amount;
						$order_status="";
				        $order_status_code="";
				        $option_data = get_option('woocommerce_coinremitterpayments_settings');
			            $ostatus = $option_data['ostatus'];
			            if($total_paidamount == 0){
					        	$order_status="Pending";
					        	$order->update_status('pending');
					            $order_status_code=COINREMITTER_INV_PENDING;
					        }else if($total_amount > $total_paidamount ){
					            $order_status="Under paid";
					        	$order->update_status('pending');
					            $order_status_code=COINREMITTER_INV_UNDER_PAID;
					        }else if($total_amount == $total_paidamount){
					            $order_status="Paid ";
					            $order->update_status('wc-'.$ostatus);
					            $order_status_code=COINREMITTER_INV_PAID;
					        }else if($total_amount < $total_paidamount){
					        	$order->update_status('wc-'.$ostatus);
					            $order_status="Over paid";
					            $order_status_code=COINREMITTER_INV_OVER_PAID;
					        }
							$u_order_data="UPDATE `coinremitter_order_address` SET `payment_status` = '$order_status_code' WHERE `addr` = '$address' ";
							$update_order_data = $wpdb->get_results($u_order_data);

							$payment_data="UPDATE `coinremitter_payments` SET `status` = '$order_status',`status_code` = '$order_status_code', `paid_amount`='".$total_paidamount."' WHERE `orderID` = '".$get_order_data[0]->orderID."' ";
							
							$update_payment_data = $wpdb->get_results($payment_data);
					}
				}
			}
		}
}

function encrypt($value){
    if(!$value){
        return false;
    }
    $text = $value;
    $crypttext = openssl_encrypt($text, ECR_CIPHERING, ECR_SKEY, ECR_OPTIONS, ECR_IV); 
    return trim($crypttext);
}
function decrypt($value) {
    if (!$value) {
        return false;
    }
    $encryption = $value;
    $decrypttext=openssl_decrypt($encryption, ECR_CIPHERING, ECR_SKEY, ECR_OPTIONS, ECR_IV);
    return trim($decrypttext);
}
?>