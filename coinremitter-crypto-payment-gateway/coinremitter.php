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


		// security data hash; you can change path / file location
		$this->hash_url = COINREMITTER_DIR."files/coinremitter.hash";
	    

		$this->coin_names 	= self::coin_names();
		
		// compatible test
		$ver = get_option(COINREMITTER.'version');
		if (!$ver || version_compare($ver, COINREMITTER_VERSION) < 0) $this->upgrade();
		elseif (is_admin()) coinremitter_retest_dir();
		
		if (is_admin()) coinremitter_retest_dir();
		// Current Page, Record ID
		$this->page = (sanitize_key($_GET['page'])) ? sanitize_key($_GET['page']) : "";
		$this->id 	= (intval($_GET['id'])) ? intval($_GET['id']) : 0;
		$this->updated = (isset($_GET['updated']) && is_string($_GET["updated"]) == "true") ? true : false;
				
		
		// Redirect
		if ($this->page == COINREMITTER."contact") { header("Location: ".COINREMITTER_ADMIN.COINREMITTER."#i3"); die; }
				
		// A. General Plugin Settings
		$this->get_settings_coinremitter();
		
		// Admin
		if (is_admin())
		{

			add_action( 'admin_notices', 'sample_admin_notice__success');
			if ($this->errors && $this->page != 'coinremittercredentials') add_action('admin_notices', array(&$this, 'admin_warning_coinremitter'));
			if (!file_exists(COINREMITTER_DIR."files") || !file_exists(COINREMITTER_DIR."images") || !file_exists(COINREMITTER_DIR."lockimg")) add_action('admin_notices', array(&$this, 'admin_warning_reactivate_coinremitter'));
			add_action('admin_menu', 			array(&$this, 'admin_menu_coinremitter'));
			add_action('init', 					array(&$this, 'admin_init_coinremitter'));
			add_action('admin_head', 			array(&$this, 'admin_header_coinremitter'), 15);

			if (strpos($this->page, COINREMITTER) === 0)  add_action("admin_enqueue_scripts", array(&$this, "admin_scripts_coinremitter"));

			if (in_array($this->page, array("coinremitter", "coinremitterpayments","coinremittercredentials"))) add_action('admin_footer_text', array(&$this, 'admin_footer_text_coinremitter'), 15);

			$this->postRecordExists();
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
	
	public function postRecordExists(){
		
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

		wp_enqueue_script ( 'cr-jquery-min',       plugins_url('/js/jquery-min.js', __FILE__) );
	    
	    add_action( 'wp_enqueue_scripts', 'my_enqueue' );

	    return true;
	}
	public static function my_enqueue() {

    	wp_localize_script( 'ajax-script', 'my_ajax_object',
            array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
	}

    public static function coin_names()
	{
		$ActCoinArr = getActCoins();
		$coinArr = array();
		if(is_array($ActCoinArr) && sizeof($ActCoinArr)){
			foreach($ActCoinArr as $Key => $Val){
				$coinArr[$Key] = strtolower(preg_replace('/\s+/', '', $Val['name']));
			}
		}
		return $coinArr;
	}

    public function payments()
	{
		return $this->payments;
	}

    public function bootstrap_scripts()
	{
	    $theme = $this->options['box_theme'];

	    $css =  plugins_url("/css/bootstrapcustom.min.css", __FILE__);


	    $tmp  = "<link rel='stylesheet' id='cr-bootstrapcss-css'  href='".$css."' type='text/css' media='all' />";

	    wp_enqueue_script ( 'popper-min', plugins_url('/js/popper.min.js', __FILE__) );
	    wp_enqueue_script ( 'bootstrap-min', plugins_url('/js/bootstrap.min.js', __FILE__) );
	    wp_enqueue_script ( 'font-awsome-all', plugins_url('/js/font-awsome-all.js', __FILE__) );

	    wp_enqueue_script ( 'coin-support-min', plugins_url("/js/coinremittersupport.min.js?ver=".COINREMITTER_VERSION, __FILE__) );
	    
	    return $tmp;
	}
        
        private function payment_box_style()
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
		
		$this->get_settings_coinremitter();
		
		if ($this->errors) $message = "<div class='error'>".__('Please fix errors below:', COINREMITTER)."<ul><li>- ".implode("</li><li>- ", $this->errors)."</li></ul></div>";

		else $message = "";
		
        if (!$this->errors && ((isset($_GET['updated']) && $_GET['updated'] == "true") || $this->updated))
        {
            $messages = $this->test_coinremitter_connection( $this->updated );
            if (isset($messages["error"])) 
            {
                unset($messages["error"]);
            }
            elseif (!$this->updated) $message .= "<div class='updated'><p><b>ALL CONNECTIONS ARE OK!</b></p><ol><li>".implode("</li><li>", $messages)."</li></ol></div>";
        }
        if($_GET['delete']){
        	$message = "<div class='updated'><ul><li>Wallet deleted successfully.</li></ul></div>";
        }
        if($_GET['withdraw']){
        	$message = "<div class='updated'><ul><li>Withdraw amount successfully.</li></ul></div>";
        }
        if($_GET['up']){
        	$message = "<div class='updated'><ul><li>Wallet updated successfully.</li></ul></div>";
        }
         if($_GET['wp_invoice_responce']){
        	$success_page = $this->postPaymentSuccess();
        	$failed = $this->postPaymentFailed();
        	$link = get_permalink($failed);
        	wp_redirect($link);
        	//InvoiceResponce();
        }
        
        if($_GET['paymentfailed']){
        	$success_page = $this->postPaymentFailed();
        	$link = get_permalink($success_page);
        	wp_redirect($link);
        }
        if($_GET['new_wallet']){
        	$message = "<div class='updated'><ul><li>Wallet inserted successfully.</li></ul></div>";
        }
        $tmp  = "<div class='wrap ".COINREMITTER."admin'>";
		$tmp .= $message;
		
		$tmp .= $this->page_title_coinremitter($this->space(1));
		$tmp .= "<div class='postbox crypto-wallets'>";
		$tmp .= "<h2>Wallets</h2>";
		$tmp .= "<div class='inside coinremittersummary'>";
		$tmp .= "<div class='wallets-button'>
                     <button class='wallets-btn OpenPopup'><i class='fa fa-plus'></i> Add Wallet </button>
                  </div>";
		
		$tmp .= "<div class='bootstrapiso'>";
		
        $sql_where = "";
		
		$us_other = "";
		$dt_other = "";
		$res = $wpdb->get_row("SELECT count(*) as cnt, sum(amountUSD) as total from coinremitter_payments where orderID like '%.%'".$sql_where, OBJECT);
		$tr_other = ($res) ? $res->cnt : 0;
		if ($tr_other)
		{
			$us_other = " ( $" . coinremitter_number_format($res->total, 2) . " )";
			$res = $wpdb->get_row("SELECT paymentID, amount, coinLabel, DATE_FORMAT(txDate, '%d %b %Y, %H:%i %p') as dt from coinremitter_payments where orderID like '%.%' ".$sql_where." order by txDate desc", OBJECT);
			$dt_other = "<span title='".__('Latest Payment to Other Plugins', COINREMITTER)."'>".$this->space(2).$res->dt.$this->space()."-".$this->space().
			
			"<a href='".COINREMITTER_ADMIN.COINREMITTER."payments&s=payment_".$res->paymentID."'>" . coinremitter_number_format($res->amount, 8) . "</a> " . $res->coinLabel . "</span>";
		}

		// 7
		$us_unrecognised = "";
		$dt_unrecognised = "";
		$res = $wpdb->get_row("SELECT count(*) as cnt, sum(amountUSD) as total from coinremitter_payments where unrecognised = 1", OBJECT);
		$tr_unrecognised = ($res) ? $res->cnt : 0;
		if ($tr_unrecognised)
		{
			$us_unrecognised = " ( $" . coinremitter_number_format($res->total, 2) . " )";
			$res = $wpdb->get_row("SELECT paymentID, amount, coinLabel, DATE_FORMAT(txDate, '%d %b %Y, %H:%i %p') as dt from coinremitter_payments where unrecognised = 1 order by txDate desc", OBJECT);
			$dt_unrecognised = "<span title='".__('Unrecognised Latest Payment', COINREMITTER)."'>".$this->space(2).$res->dt.$this->space()."-".$this->space().
			"<a href='".COINREMITTER_ADMIN.COINREMITTER."payments&s=payment_".$res->paymentID."'>" . coinremitter_number_format($res->amount, 8) . "</a> " . $res->coinLabel . "</span>";
		}
		
		// 8
		$all_details = "";
		$dt_last = "";
		$res = $wpdb->get_row("SELECT count(*) as cnt, sum(amountUSD) as total from coinremitter_payments", OBJECT);
		$all_payments = ($res) ? $res->cnt : 0;
		if ($all_payments)
		{
			$all_details .= $this->space()."~ ".coinremitter_number_format($res->total, 2)." ".__('USD', COINREMITTER);
			$res = $wpdb->get_row("SELECT paymentID, amount, coinLabel, amountUSD, DATE_FORMAT(txDate, '%d %b %Y, %H:%i %p') as dt from coinremitter_payments order by txDate desc", OBJECT);
			
						$res->dt.$this->space()."-".$this->space()."<a title='".__('Latest Payment', COINREMITTER)."' href='".COINREMITTER_ADMIN.COINREMITTER."payments&s=payment_".$res->paymentID."'>" . coinremitter_number_format($res->amount, 8) . "</a> " . $res->coinLabel . $this->space() . "<small>( " . coinremitter_number_format($res->amountUSD, 2)." ".__('USD', COINREMITTER). " )</small>";
		}


		$tmp .= "<a name='i1'></a>";
		        $tmp .= "<div class='table-responsive' style='overflow-x: hidden;'>";
                $tmp .= "<div class='row'>";
                foreach($this->coin_names as $ck=>$cv){
                	$cv = preg_replace('/\s+/', '', $cv);
                    $coinRes = $wpdb->get_row("SELECT count(*) as cnt, sum(amountUSD) as totalUsd, sum(amount) as total from coinremitter_payments where coinLabel = '".$ck."'", OBJECT);
                    $payCnt = ($coinRes) ? $coinRes->cnt : 0;
                    $payAmount = ($coinRes) ? $coinRes->total : 0;

                    //$payAmount = get_option( COINREMITTER.$cv.'_amount' );
                    $payAmountUsd = ($coinRes) ? $coinRes->totalUsd : 0;

                    $payAmount = coinremitter_number_format($payAmount,8);
                    $usd_price = coinremitter_number_format($payAmountUsd,2);
                    
                    $class = 'well-inactive';
                    $href = "#i2";
                    $text = 'Follow CoinRemitter instruction to setup wallet';
                    
                    $usd_text = '-';
                    $title = 'Follow CoinRemitter instruction to setup wallet';
                    $opacity = 'opacity: 0.2;';
                    
                    $CoinName = $this->coin_names;
                    foreach ($CoinName as $key => $value) {
                    	$CoinShortName[$value] = $key;
                    }

                   if(isset($this->options[$cv.'api_key']) && $this->options[$cv.'api_key'] != '' && isset($this->options[$cv.'password']) && $this->options[$cv.'password'] != ''){
                        $class = 'well';
                        $opacity = '';
                        $href = COINREMITTER_ADMIN.COINREMITTER."payments&s=".$ck;
                        $text = $ck.' '.$payAmount;
                        $usd_text = 'Total received USD : $'.$usd_price;
                        $title ='total payment received';
                    }
                    $cv;
                    $APIVal = get_option( COINREMITTER.$cv.'api_key' );
                    $PasswordVal = get_option( COINREMITTER.$cv.'password' );
                    $WalletAmt =  get_option( COINREMITTER.$cv.'_amount' );
                    $payAmount = number_format($WalletAmt,8);//Wallet balance
                    $EditButton ='';

                    //Get balance from CURL
                    $CoinType = CoinShortCon($cv);
                    $postdata = array('cointype'=>$CoinType,'api_key' => $APIVal, 'password'=>$PasswordVal);
                    $BalanceRes = getBalanceByCurl($postdata);
                 	if($BalanceRes['flag'] == 0){
                 		$erroMsg = $BalanceRes['msg'];
                 	}else{
                 		$erroMsg = '';
                 	}
                    if(is_array($BalanceRes) && sizeof($BalanceRes)){
                    	
                    	$payAmount = is_numeric($BalanceRes['data']['balance']) ? number_format($BalanceRes['data']['balance'],8) : '';//Wallet balance	
                    }

                    $imageDir = dirname(__FILE__).'/images';
					$coin_imge_name = strtolower(preg_replace('/\s+/', '', $cv));
					$path = $imageDir.'/'.$coin_imge_name.'.png';
					if(!file_exists($path)){
						$wallet_logo = plugins_url('/images/dollar-ico.png',__FILE__);
					}
					else{

						$wallet_logo = plugins_url('/images/'.$cv.'.png',__FILE__);
					}
         			

                    if(isset($APIVal) && !empty($APIVal)){
                    	$EditButton = '<div class="wallet-usd">
                    			<button class="wallet-edit WithdrawOpenPopup" style="float: left;" data-rel='.$cv.'>Withdraw</button>
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
			                                    <h2>'.ucfirst($cv).' <span id="coin_shot_'.$cv.'">'.$CoinShortName[$cv].'</span></h2>
			                                 </div>
			                                 <div class="wallet-balance">
			                                 	<h4>'.$payAmount.' '.$CoinShortName[$cv].'</h4>
			                                 </div>
			                                 <span style="font-size:12px;color:red;">'.$erroMsg.'</span>
			                                 '.$WithdrawButton.$EditButton.'
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

		$iCount = 1;
		foreach($this->coin_names as $k => $v){
			$coinObj = preg_replace('/\s+/', '', $v);

			$DisNone = 'display:none;';
			$APIVal = get_option( COINREMITTER.$v.'api_key' );
			$PasswordVal = get_option( COINREMITTER.$v.'password' );

			if(empty($APIVal) || empty($PasswordVal)){
				$DivClass = preg_replace('/\s+/', '', $v);
				$DroOpt .= '<option value="'.$DivClass.'">'.ucfirst($v).'</option>';
			}
			$DivClass = 'div'.$coinObj;
			$CurrenyObj .= "<div class='wallet-form-box add-wallet ".$DivClass." allDiv' style='".$DisNone."' >
                                 <label>API Key <a href='http://coinremitter.com' target='_blank' style='float:right; font-size:12px;' >(get API KEY)</a></label>
                                 <input type='text' autocomplete='off' name='".COINREMITTER.$coinObj."api_key' id='".COINREMITTER.$coinObj."api_key' placeholder='' class='popupapikey' value=".$APIVal.">
                              </div>
                              <div class='wallet-form-box add-wallet ".$DivClass." allDiv'   style='".$DisNone."'  >
                                 <label>Password</label>
                                 <input type='password'  autocomplete='off' name='".COINREMITTER.$coinObj."password' id='".COINREMITTER.$coinObj."password' placeholder=''  class='popuppass' value=".$PasswordVal." >
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
                           <input type='button' class='".COINREMITTER."button button-primary VerifyBtn' name='submit' value='".__("Verify & Add", COINREMITTER)."' style='background-color:#0085ba;' >
                           <a href='#' style='display:none;' class='hiddenHref'>Click</a>
                        </div>
                     </div>
                 </form>
               </div>
            </div>";

        /* Edit Popup */
        foreach($this->coin_names as $k => $v){
        	$v = preg_replace('/\s+/', '', $v);
			$APIVal2 = get_option( COINREMITTER.$v.'api_key' );
			$PasswordVal2 = get_option( COINREMITTER.$v.'password' );
			$coinObj = preg_replace('/\s+/', '', $v);
			$DroOpt2 .= '<option value='.$v.'>'.ucfirst($v).'</option>';
			$DivClass = $v;
			$CurrenyObj2 .= "<div class='wallet-form-box div".$DivClass." allDiv' style='display:none;' >
                                 <label>API Key <a href='http://coinremitter.com' target='_blank' style='float:right; font-size:12px;' >(get API KEY)</a></label>
                                 <input type='text' name='".COINREMITTER.$coinObj."api_key' id='".COINREMITTER.$coinObj."api_key' placeholder='' class='popupapikey' value=".$APIVal2." >
                              </div>
                              <div class='wallet-form-box div".$DivClass." allDiv'  style='display:none;' >
                                 <label>Password</label>
                                 <input type='password' name='".COINREMITTER.$coinObj."password' id='".COINREMITTER.$coinObj."password' placeholder=''  class='popuppass' value=".$PasswordVal2." >
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
                     		<input type='button' onclick='javascript:deleteWallete();' class='".COINREMITTER."button button-primary deleteBtn' data-rol='' name='submit' value='".__("Delete", COINREMITTER)."' style='background-color:#ff3f3f;' >
                     	</div>
                     	<div style='display:inline'>
                     		<span class='frmUpdateError' style='color:red;color: red;padding-left: 20px;'></span>
                     	</div>
                        <div class='pum-submit' >
                           <!-- <button class='button button-primary' onclick='javascript:addCryptoCurr(true);' >Verify & Add</button> -->
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
               		<input type='hidden' name='withdraw' id='withdraw' />
               		<input type='hidden' name='currency_type' id='currency_type' />
                     <div class='pum-modal-header'>
                        <span id='pum_trigger_add_type_modal-title' class='pum-modal-title'>Withdraw</span>
                        <button type='button' class='pum-modal-close fa fa-times ClosePopup' aria-label='Close'></button>
                     </div>
                     <div class='pum-modal-content'>
                        <div class='pum-field-section '>
                           <div class='wallet-form'>
                              <div class='wallet-form-box'>
                                <div class='wallet-form-box'>
                                 	<label>Address</label>
									<input type='text' name='address' id='address' placeholder='Enter Address'>
								</div>
								<div class='wallet-form-box'>
                                 	<label>Amount</label>
									<input type='numbers' name='amount' id='amount' placeholder='Enter Amount'>
								</div>
                              </div>
                              <div style='padding:5px'> Processing Fee : 
                              <span id='withprocessing' >
                              </span><span id='withpp' class='withpp'></span></div>
                              <div style='padding:5px'> Transaction Fee : <span id='withtransaction'></span><span id='withtp' class='withtp'></span></div>
                              <div style='padding:5px'> Total : <span id='withtotal'></span></div>
                           </div>
                        </div>
                     </div>
                     <div class='pum-modal-footer submitbox'>
                     	<span class='frmWithdrowError' style='color:red;'></span>
                        <div class='pum-submit' >
                           <!-- <button class='button button-primary' onclick='javascript:addCryptoCurr(true);' >Verify & Add</button> -->
                           <input type='button' class='".COINREMITTER."button button-primary WithdrawBtn' name='submit' value='".__("Withdraw", COINREMITTER)."' style='background-color:#0085ba;' >

                           <input type='button' class='".COINREMITTER."button button-primary deleteBtn ClosePopup' data-rol='' name='Cancel' value='".__("Cancel", COINREMITTER)."' style='background-color:#0085ba;' >
                        </div>
                     </div>
                 </form>
               </div>
            </div>";                       
		echo $tmp;
		
		return true;
	} 
	
	private function get_settings_coinremitter()
	{

		$arr = array("box_type"=>"", "box_theme"=>"", "box_width"=>540, "box_height"=>230, "box_border"=>"", "box_style"=>"", "message_border"=>"", "message_style"=>"", "login_type"=>"", "rec_per_page"=>20, "popup_message"=>__('It is a Paid Download ! Please pay below', COINREMITTER), "file_columns"=>"", "chart_reverse"=>"");
		foreach($arr as $k => $v) $this->options[$k] = "";
		
		foreach($this->coin_names as $k => $v)
		{
			$this->options[$v."api_key"] = "";
			$this->options[$v."password"] = "";
		}
			
		foreach ($this->options as $key => $value) 
		{
			$key = preg_replace('/\s+/', '', $key);
			$this->options[$key] = get_option(COINREMITTER.$key);

		}
		
		// default
		foreach($arr as $k => $v) 
		{
			if (!$this->options[$k]) $this->options[$k] = $v;
		}

		// Additional Securyit - compare coinremitter api_key/password keys sha1 hash with hash stored in file $this->hash_url
		// ------------------
		$txt = (is_readable($this->hash_url)) ? file_get_contents($this->hash_url) : "";
		$arr = json_decode($txt, true);

		if (isset($arr["nonce"]) && $arr["nonce"] != sha1(md5(NONCE_KEY)))
		{
		    $this->save_cryptokeys_hash_coinremitter(); // admin changed NONCE_KEY
		    $txt = (is_readable($this->hash_url)) ? file_get_contents($this->hash_url) : "";
		    $arr = json_decode($txt, true);
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

		// writable directory
		if (!file_exists($this->hash_url) && !is_writable(dirname($this->hash_url))) $this->errors[] = sprintf(__("Error. Cannot write file %s - please make directory %s writable.", COINREMITTER), $this->hash_url, dirname($this->hash_url));

		return true;
	}

	private function save_settings_coinremitter($flag = 1,$amount = 0)
	{
		$arr = array();
		foreach ($this->options as $key => $value){

		    $boxkey = (strpos($key, "api_key") || strpos($key, "password")) ? true : false;
		    if (!(file_exists($this->hash_url) && !is_writable($this->hash_url) && $boxkey))
		    {

		        $oldval = get_option(COINREMITTER.$key);
		        if ($boxkey && $oldval != $value) $arr[$key] = array("old_key" => ($oldval ? substr($oldval, 0, -20)."....." : "-empty-"), "new_key" => ($value ? substr($value, 0, -20)."....." : "-empty-"));
		    	if($flag == 1){
		    		update_option(COINREMITTER.$key, $value);
		    	}
		    }
		}
		
		if ($arr && $flag == 1) 
		{    
		    wp_mail(get_bloginfo('admin_email'), 'Notification - CoinRermitter Payment Gateway Plugin', 
		    date("r")."\n\nCOinRemitter Crypto Payment Gateway for Wordpress plugin\n\nFollowing crypto wallets keys was changed on your website -\n\n".print_r($arr, true));
		}    
		if ($arr && $flag == 1){
			$this->save_cryptokeys_hash_coinremitter();
		}
		return true;
	}
	
	private function save_cryptokeys_hash_coinremitter()
	{
	    if (!file_exists($this->hash_url) || is_writable($this->hash_url))
	    {
        	$arr = array("nonce" => sha1(md5(NONCE_KEY)));
        	foreach($this->coin_names as $k => $v)
        	{
        	    $pub  = $v."api_key";
        	    $prv  = $v."password";
        	    if ($this->options[$pub] && $this->options[$prv])
        	    {
        	        $arr[$pub] = sha1($this->options[$pub].NONCE_KEY.$this->options[$pub]);
        	        $arr[$prv] = sha1($this->options[$prv].NONCE_KEY.$this->options[$prv]);
        	    }
        	}

        	file_put_contents($this->hash_url, json_encode($arr));
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

            if ($public_key || $private_key) $arr[$v] = array("api_key" => $public_key, "password" => $private_key,'amount'=>10,'orderID'=>'testing','coinLabel'=>$k);
            if ($private_key) $arr2[] = $private_key; 
        }

        if (!$arr) return array("error" => true, "desc" => 'Please add your CoinRemitter Wallet API Key/Password on this credentials page');
        
        include_once(plugin_dir_path( __FILE__ )."includes/coinremitter.class.php");
        
        foreach($arr as $k => $v)
        {
            $obj = new CoinRemitterCrypto($v);
            $bal = $obj->get_balance($v);
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
				"password" => $private_key,
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
						$this->longname = $CoinType;
						$CoinLable = $this->CoinShortName();
						$cURL = COINREMITTER_API_URL.$CoinLable.'/get-balance';
						$parm = array(
								'api_key'	=>sanitize_text_field($_POST[COINREMITTER.$CoinType.'api_key']),
								'password'	=>sanitize_text_field($_POST[COINREMITTER.$CoinType.'password']),
								);
						$Conn = $this->chkConnection($cURL,$parm);
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
	
	public function chkConnection($url,$post='')
	{
		$header[] = "Accept: application/json";
	    $curl = $url;
	    $body = array(
	        'api_key' => $post['api_key'],
	        'password' => $post['password'],
        );	

	    $args = array(
	        'method'      => 'POST',
	        'timeout'     => 45,
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

	public function admin_header_coinremitter()
	{
		global $current_user;
		
		$_administrator = $_editor = false;
		if (is_user_logged_in())
		{
			$_administrator = in_array('administrator', $current_user->roles);
			$_editor 		= in_array('editor', 		$current_user->roles);
		}

		if (isset($_GET[COINREMITTER_PREVIEW]) && $_GET[COINREMITTER_PREVIEW] && !$_POST && ($_administrator || $_editor))
		{
			$filePath = COINREMITTER_DIR."files/".$_GET[COINREMITTER_PREVIEW];
			
			if (file_exists($filePath) && is_file($filePath))
			{
				// Starting Download
				$this->download_file($filePath);
					
				// Flush Cache
				ob_flush();
				
				die;
			}	
		}
		
		return true;
	}
	
	public function admin_footer_text_coinremitter()
	{
		return sprintf( __( "If you like <strong>CoinRemitter Crypto Payment Gateway</strong> please leave us a %s rating on %s. A huge thank you from CoinRemitter  in advance!", COINREMITTER ), "<a href='https://wordpress.org/support/view/plugin-reviews/coinremitter-payment-gateway?filter=5#postform' target='_blank'>&#9733;&#9733;&#9733;&#9733;&#9733;</a>", "<a href='https://wordpress.org/support/view/plugin-reviews/coinremitter-payment-gateway?filter=5#postform' target='_blank'>WordPress.org</a>");
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
		if (in_array(strtolower($this->right($_SERVER["REQUEST_URI"], "/", false)), array("?coinremitter.callback", "index.php?coinremitter.callback", "?coinremitter_callback", "index.php?coinremitter_callback", "?coinremitter-callback", "index.php?coinremitter-callback")))
		{
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
			include_once(plugin_dir_path( __FILE__ )."includes/coinremitter.callback.php");
			
			ob_flush();
			
			die;
		}
		if(in_array(strtolower($this->right($_SERVER["REQUEST_URI"], "/", false)), array("?coinremitter.notify", "index.php?coinremitter.notify", "?coinremitter_notify", "index.php?coinremitter_notify", "?coinremitter-notify", "index.php?coinremitter-notify"))){
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
			include_once(plugin_dir_path( __FILE__ )."includes/coinremitter.notify.php");
			
			ob_flush();
			
			die;

		}
	
		return true;
	}
        
	
	private function upgrade ()
	{
		global $wpdb;
	
		// TABLE 1 - coinremitter_payments
		// ------------------------------
		if ($wpdb->get_var("SHOW TABLES LIKE 'coinremitter_payments'") != 'coinremitter_payments')
		{
			$sql = "CREATE TABLE `coinremitter_payments` (
			  `paymentID` int(11) unsigned NOT NULL AUTO_INCREMENT,
			  `orderID` varchar(50) NOT NULL DEFAULT '',
			  `userID` varchar(50) NOT NULL DEFAULT '',
			  `coinLabel` varchar(8) NOT NULL DEFAULT '',
			  `explorer_url` char(255) NOT NULL DEFAULT '',
			  `amount` double(20,8) NOT NULL DEFAULT '0.00000000',
			  `amountUSD` double(20,8) NOT NULL DEFAULT '0.00000000',
			  `unrecognised` tinyint(1) unsigned NOT NULL DEFAULT '0',
			  `addr` varchar(255) NOT NULL DEFAULT '',
			  `txID` char(255) NOT NULL DEFAULT '',
			  `crID` char(255) NOT NULL DEFAULT '',
			  `txDate` datetime DEFAULT NULL,
			  `txConfirmed` tinyint(1) unsigned NOT NULL DEFAULT '0',
			  `txCheckDate` datetime DEFAULT NULL,
			  `processed` tinyint(1) unsigned NOT NULL DEFAULT '0',
			  `processedDate` datetime DEFAULT NULL,
			  `createdAt` datetime DEFAULT NULL,
			  PRIMARY KEY (`paymentID`),
			  KEY `cruserID` (`userID`),
			  KEY `crorderID` (`orderID`),
			  KEY `cramount` (`amount`),
			  KEY `cramountUSD` (`amountUSD`),
			  KEY `crcoinLabel` (`coinLabel`),
			  KEY `explorer_url` (`explorer_url`),
			  KEY `crunrecognised` (`unrecognised`),
			  KEY `craddr` (`addr`),
			  KEY `crtxID` (`txID`),
			  KEY `crCrID` (`crID`),
			  KEY `crtxDate` (`txDate`),
			  KEY `crtxConfirmed` (`txConfirmed`),
			  KEY `crtxCheckDate` (`txCheckDate`),
			  KEY `crprocessed` (`processed`),
			  KEY `crprocessedDate` (`processedDate`),
			  KEY `crcreatedAt` (`createdAt`),
			  KEY `crkey1` (`orderID`,`userID`),
			  KEY `crkey2` (`orderID`,`userID`,`txID`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
	
			$wpdb->query($sql);
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
			  `coinLabel` varchar(8) NOT NULL DEFAULT '',
			  `amount` double(20,8) NOT NULL DEFAULT '0.00000000',
			  `amountUSD` double(20,8) NOT NULL DEFAULT '0.00000000',
			  `addr` varchar(255) NOT NULL DEFAULT '',
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
		}
		
		$woocommerce_setting_data = [
			 'enabled' => 'yes',
			  'title' => 'Pay Using Cryptocurrency',
			  'description' => 'Secure, anonymous payment with virtual currency. <a target="_blank" href="https://bitcoin.org/">What is bitcoin?</a>',
			  'emultiplier' => '1.0',
			  'ostatus' => 'processing',
			  'invoice_expiry' => '0'
		];
		update_option('woocommerce_coinremitterpayments_settings', $woocommerce_setting_data);
		
		// upload dir
		coinremitter_retest_dir();
	
		if (!file_exists($this->hash_url)) file_put_contents($this->hash_url, '{"nonce":"1"}');

		// current version
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
	$arrg = array(
			'headers'     => array(
    			'Content-Type'  => 'application/json',
			),
			'timeout'     => $timeout,
			'sslverify' => false,
			'user-agent' => 'WordPress/'. $wp_version.';'. get_bloginfo('url'),
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

	add_filter('woocommerce_get_return_url','override_return_url',10,2);
	
	add_action('woocommerce_checkout_process', 'customised_checkout_field_process');
	add_action('woocommerce_checkout_update_order_meta', 'custom_checkout_field_update_order_meta');
	remove_action( 'woocommerce_order_details_after_order_table', 'woocommerce_order_again_button' );
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


	function coin_to_usd($amount,$coin){
	    $CoinPrice = getActCoins();
		$coinprice = $CoinPrice[$coin]['price'];
	    $amount_in_usd = $amount * $coinprice;
	    return number_format((float)$amount_in_usd, 2, '.', '');
	}


	function check_and_update_payment($param,$order_id){
		global $wpdb;
		$dt  = gmdate('Y-m-d H:i:s');
		if($param['status_code'] == INV_OVER_PAID || $param['status_code'] == INV_PAID){
			$order_data = "SELECT * FROM coinremitter_order_address WHERE orderID = 'coinremitterwc.order".$order_id."'";
			$order_details = $wpdb->get_results($order_data);
			$user_id = $order_details[0]->userID;
			$order_details_status = $order_details[0]->payment_status;
			$crp_amount = $order_details[0]->amount;
			$order = wc_get_order($order_id);
			$invoiceurl = $param['url'];
			$invoice_id = $param['invoice_id'];
           	$invoice_note ="Invoice  <a href='".$invoiceurl."' target='_blank'>".$invoice_id."</a>  paid";
        	$order->add_order_note($invoice_note);
			if($order_details_status == 0){
				foreach ($param['payment_history'] as $key => $value) {
					
					$amount_usd =coin_to_usd($value['amount'],$param['coin']);
					$sql = "INSERT INTO coinremitter_payments ( orderID, userID, coinLabel,explorer_url, amount, amountUSD, addr, txID, crID, txDate, txCheckDate, createdAt)
	                             VALUES ( 'coinremitterwc.order".$order_id."', '".$user_id."', '".$param['coin']."','".$value['explorer_url']."', ".$value['amount'].", ".$amount_usd.",'".$param['address']."', '".$value['txid']."', '0', '".$dt."','".$dt."','".$dt."')";
	               $wpdb->get_results($sql);
	            }
	           
				$up_payment_query = "UPDATE coinremitter_order_address SET payment_status = 1 , paymentDate = '".$dt."' where addr = '".$param['address']."'";
				$insert = $wpdb->get_results($up_payment_query);
	          	$option_data = get_option('woocommerce_coinremitterpayments_settings');
	         
	            $ostatus = $option_data['ostatus'];

	        	$ostatus = $option_data['ostatus'];
	            $order = new WC_Order($order_id);
        		$order->update_status('wc-'.$ostatus);
	            add_post_meta( $order_id, '_order_crypto_price', $crp_amount);
	            add_post_meta( $order_id, '_order_crypto_coin', $callback_coin);
			}else{
				die('payment paid');
			}
			
		}
	}
	function nolo_custom_field_display_cust_order_meta($d){
		global $wpdb;
		$dd = json_decode($d,true);
		if(isset($_GET['order-received'])){
			$order_id = $_GET['order-received'];	
		}else{
			$order_id = $d->get_order_number();	
		}
		$method = get_post_meta( $order_id, '_payment_method_title', true );
		if($method == 'Cash on delivery'){
			return '';
		}

		$query = "SELECT * FROM coinremitter_order_address WHERE orderID = 'coinremitterwc.order".$order_id."'";
		$get_order_data = $wpdb->get_results($query);
		$payment_status = $get_order_data[0]->payment_status;
		$param['coin'] = $get_order_data[0]->coinLabel;
		$param['invoice_id'] = $get_order_data[0]->invoice_id;
		$coin_type = $get_order_data[0]->coinLabel;
		$payment_query = "SELECT * FROM coinremitter_payments WHERE orderID = 'coinremitterwc.order".$order_id."'";
		$payment_details = $wpdb->get_results($payment_query);	
		
		$get_data = getInvoiceData($param); 
		if($payment_status == 1){
				$paid_trasaction = "SELECT SUM(amount),SUM(amountUSD) FROM coinremitter_payments WHERE orderID = 'coinremitterwc.order".$order_id."'";
				$total_crypto_paid = $wpdb->get_results($paid_trasaction);

				$total_amount = $get_order_data[0]->amount;
				$total_usd_amount = $get_order_data[0]->amountUSD;
				$address = $get_order_data[0]->addr;
				$payments_date =  $get_order_data[0]->createdAt;
				if($payment_status == 1){
					$status = 'Paid';
				}else{
					$status = 'Pending';
				}
				$json_coin_t = json_encode($total_crypto_paid);
				$json_coin_d = json_decode($json_coin_t,true);
				$paid_amount_coin = $json_coin_d[0]['SUM(amount)'];
				$paid_amount_usd = $json_coin_d[0]['SUM(amountUSD)'];
				
		}else{
			$get_results = getInvoiceData($param); 
			if($get_results['flag'] == 1){
				$invoice_data = $get_results['data'];
				check_and_update_payment($invoice_data,$order_id);
				$total_amount = $get_order_data[0]->amount;
				$total_usd_amount = $get_order_data[0]->amountUSD;
				$address = $get_order_data[0]->addr;
				$payments_date =  $get_order_data[0]->createdAt;
				if($payment_status == 1){
					$status = 'Paid';
				}else{
					$status = 'Pending';
				}
				$json_coin_t = json_encode($total_crypto_paid);
				$json_coin_d = json_decode($json_coin_t,true);
				$paid_amount_coin = $json_coin_d[0]['SUM(amount)'];
				$paid_amount_usd = $json_coin_d[0]['SUM(amountUSD)'];
			}
		}	

		if($payment_details){
			$temp2.='<tr>
							<th scope="row">Transaction ID:</th>
							<td>';
			foreach ($payment_details as $key => $value) {
				$temp2.='<span class="woocommerce-Price-amount amount">
								<a title="'.__('Transaction Details', COINREMITTER).' - '.$value->txID.'"  href="'.$value->explorer_url.'" target="_blank" class="woocommerce-Price-currencySymbol">'.substr($value->txID,0,20).'...</a>
							</span><br>';     
			}
			$temp2.='</td></tr>';
		}

		
		if($status == 'Pending'){
			$link = $get_data['data']['url'];
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
							<th scope="row">Total '.$coin_type.' :</th>
							<td>'.$total_amount.'  ( ~ $'.number_format((float)$total_usd_amount, 2, '.', '').' USD )</td>
						</tr>
						<tr>
							<th scope="row">Paid '.$coin_type.' :</th>
							<td>'.$paid_amount_coin.'  ( ~ $'.number_format((float)$paid_amount_usd, 2, '.', '').' USD)</td>
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

	add_filter('woocommerce_get_variation_prices_hash',              'coinremitter_wc_variation_prices_hash', $priority, 1 );
	add_action('woocommerce_admin_order_data_after_billing_address', 'coinremitter_wc_admin_order_stats');
    
        
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

	$res[$currency] = array(   "2way"  => $twoway,
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
	        elseif (current_user_can('manage_options') && isset($_GET["page"]) && $_GET["page"] == "wc-settings" && (!isset($_GET["tab"]) || $_GET["tab"] == "general"))
	        {
	            $currency_symbol = substr($currency, 0, 3) . " &#10143; " . substr($currency, 3);  // Currency options Menu
	        }
	        else $currency_symbol = substr($currency, 3);
                
	    }
	    elseif (class_exists('coinremitterclass') && defined('COINREMITTER') && defined('COINREMITTER_ADMIN'))
	    {
                
	        $arr = coinremitterclass::coin_names();

	        if (isset($arr[$currency])) $currency_symbol = $currency;
	    }

	    if ($currency_symbol == "BTC") $currency_symbol = "&#579;";
	    

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
    function getExistsRecord(){
		
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

   function override_return_url($return_url,$order){
   		global $wpdb;
		$PID = getExistsRecord();
		$qString = $_POST['currency_type'];
		$return_url = get_the_permalink($PID);
		$OrdID = $order->get_id();
		$order_amount = $order->get_total();
		$coin = CoinShortCon($qString);
		$userID = get_current_user_id();
		$payment_title = $order->get_payment_method_title();
		$cancel_url = $order->get_cancel_order_url();

		$tablename = $wpdb->prefix."posts";
		$SQL = "SELECT * FROM $tablename WHERE post_title = 'checkout'";
		$dataVal = $wpdb->get_results($SQL);
		$s_url = get_permalink($dataVal[0]->ID);

		$test_order = new WC_Order($OrdID);	
		$test_order_key = $test_order->order_key;
		$sss_url = $s_url.'?order-received='.$OrdID.'&key='.$test_order_key;	
		if($payment_title == 'Cash on delivery'){
			$modified_url = $sss_url;
    		return $modified_url;
		}

		$currancy_type = get_woocommerce_currency();
		$option_data = get_option('woocommerce_coinremitterpayments_settings');

		if($option_data['invoice_expiry'] == 0 || $option_data['invoice_expiry'] == ''){
			$invoice_expiry = '';
		}else{
        	$invoice_expiry = $option_data['invoice_expiry'];
		}

        if($option_data['emultiplier'] == 0 && $option_data['emultiplier'] == ''){
			$invoice_exchange_rate = 1;
        }else{
			$invoice_exchange_rate = $option_data['emultiplier'];
        }

		$amount = $order_amount*$invoice_exchange_rate; 
		$param = [
			'amount' => $amount,
			'coin'=>$qString, 
			'expire_time'=>$invoice_expiry,
			'notify_url' => COINREMITTER_INVOICE_NOTIFY_URL,
			'success_url' => $sss_url,
			'fail_url' => $cancel_url,
			'currency' => $currancy_type,
			'description' => 'Order Id #'.$OrdID,
		];
        $tablename = 'coinremitter_order_address';
		$SQL = "SELECT * FROM $tablename WHERE orderID = 'coinremitterwc.order$OrdID'";
		$dataVal = $wpdb->get_results($SQL);
		if($wpdb->num_rows < 1) {

			$invoice_data = GetInvoice($param);
			if($invoice_data['flag'] == 1){
				$coin = $invoice_data['data']['coin'];
				$coin_price = $invoice_data['data']['total_amount'][$coin];
	        	$wpdb->insert( $tablename, array(
		            'orderID' => 'coinremitterwc.order'.$OrdID,
		            'userID' => $userID,
		            'invoice_id' => $invoice_data['data']['invoice_id'], 
		            'coinLabel' => $coin,
		            'amount' => $coin_price, 
		            'amountUSD' => $invoice_data['data']['usd_amount'],
		            'addr' => $invoice_data['data']['address'], 
		            'createdAt' => gmdate("Y-m-d H:i:s"), 
		             ),
		            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s') 
		        );

				$modified_url = $invoice_data['data']['url'];
    			return $modified_url;	
			}else{
				wp_delete_post($OrdID,true);  
				wc_add_notice(__($invoice_data['msg']) , 'error');
			}
        }
    	
  	}   

  	function GetInvoice($param){
    	$qString = $param['coin'];
		$APIVal = get_option( COINREMITTER.$qString.'api_key' );
	    $PasswordVal = get_option( COINREMITTER.$qString.'password' );	   
		$Coin = CoinShortCon($qString);
	   	$header[] = "Accept: application/json";
	    $curl = COINREMITTER_API_URL.$Coin.'/create-invoice';
	    $body = array(
	        'api_key' => $APIVal, 
	        'password'=>$PasswordVal,
	        'notify_url'=>$param['notify_url'],
	        'amount'=>$param['amount'],
	        'currency' => $param['currency'],
	        'expire_time'=> $param['expire_time'],
	        'description'=> $param['description'],
	        'suceess_url' => $param['success_url'],
	        'fail_url'=>$param['fail_url']
        );	
	    $args = array(
	        'method'      => 'POST',
	        'timeout'     => 45,
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

    function getInvoiceData($param){
		$qString = CoinFullnameCon($param['coin']);
		$APIVal = get_option( COINREMITTER.$qString.'api_key' );
	    $PasswordVal = get_option( COINREMITTER.$qString.'password' );	   
		$Coin = CoinShortCon($qString);
	   	$header[] = "Accept: application/json";
	    $curl = COINREMITTER_API_URL.$Coin.'/get-invoice';
	    $body = array(
	        'api_key' => $APIVal, 
	        'password'=>$PasswordVal,
	        'invoice_id'=>$param['invoice_id'],
        );	
	    $args = array(
	        'method'      => 'POST',
	        'timeout'     => 45,
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

    function CoinFullnameCon($CoinTyep=''){
		$CoinArr = getActCoins();
		if(is_array($CoinArr) && sizeof($CoinArr)){
			foreach($CoinArr as $k => $v){
				if($CoinTyep == $k){
					$v = strtolower(preg_replace('/\s+/', '', $v['name']));
					return $v;
				}

			}
		}
    	return 'BTC';
    }//CoinShortCon

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
		if($_POST['payment_method'] == 'coinremitterpayments'){
   			if (!$_POST['currency_type']) wc_add_notice(__('Please select crypto payment method.') , 'error');	
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
                    private $emultiplier        = '';
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

                            $enabled = ((COINREMITTERLWC_AFFILIATE_KEY=='coinremitter' && $this->get_option('enabled')==='') || $this->get_option('enabled') == 'yes' || $this->get_option('enabled') == '1' || $this->get_option('enabled') === true) ? true : false;


                            if (true === version_compare(WOOCOMMERCE_VERSION, '2.1', '<'))
                            {
                                    if ($enabled) $this->method_description .= '<div class="error"><p><b>' .sprintf(__( "Your WooCommerce version is too old. The CoinRemitter Crypto Payment Gateway plugin requires WooCommerce 2.1 or higher to function. Please update to <a href='%s'>latest version</a>.", COINREMITTERWC ), admin_url('plugin-install.php?tab=search&type=term&s=WooCommerce+excelling+eCommerce+WooThemes+Beautifully')).'</b></p></div>';
                            }
                            else
                            {

                                    $this->payments 			= $coinremitter->payments(); 	
                                    	// Activated Payments
                                    $this->coin_names			= $coinremitter->coin_names(); 	// All Coins
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
                            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
                          
                            // Subscriptions
                            if ( class_exists( 'WC_Subscriptions_Order' ) ) {
                            }

                            return true;
                }
               
                public function init_form_fields()
                {
                	$urlcoin = 'https://coinremitter.com/api/get-coin-rate';
                	$per1 = '5%';
                	$per2 = '15%';
                    $logos = array('global' => __( "CoinRemitter default logo - 'Global Payments'", COINREMITTERWC ));
                    foreach ($this->coin_names as $v) $logos[$v] = sprintf(__( "CoinRemitter logo with text - '%s Payments'", COINREMITTERWC ), __( ucfirst($v), COINREMITTERWC ));

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
                    
                        'emultiplier' 	=> array(
                        'title' 		=> __('Exchange Rate Multiplier', COINREMITTERWC ),
                        'type' 			=> 'text',
                        'default' 		=> '1.00',
                        'description' 	=> sprintf(__("The system will fetch LIVE cryptocurrency rates from coinremitter.com. Check here ( <a href='%s'>https://coinremitter.com/api/get-coin-rate </a> ) for current USD price <br>Example: 1.05 - will add an extra %s to the total price in bitcoin/altcoins, 0.85 - will be a %s discount for the price in bitcoin/altcoins. Default: 1.00", COINREMITTERWC), $urlcoin,$per1,$per2)
                    ),
                        'ostatus' 		=> array(
                        'title' 		=> __('Order Status - Cryptocoin Payment Received', COINREMITTERWC ),
                        'type' 			=> 'select',
                        'options' 		=> $this->statuses,
                        'default' 		=> 'processing',
                        'description' 	=> sprintf(__("Payment is received successfully from the customer. You will see the bitcoin/altcoin payment statistics in one common table <a href='%s'>'All Payments'</a> with details of all received payments.<br>If you sell digital products / software downloads you can use the status 'Completed' showing that particular customer already has instant access to your digital products", COINREMITTERWC), $this->url2)
                    ),
                      	'invoice_expiry'		=> array(
                        'title'       	=> __( 'Invoice expiry time in Minutes', COINREMITTERWC ),
                        'type'        	=> 'text',
                        'default'     	=> "0",
                        'description' 	=> __( "", COINREMITTERWC )
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
                    $this->emultiplier  = trim(str_replace(array("%", ","), array("", "."), $this->get_option( 'emultiplier' )));
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
                    if (!$this->emultiplier || !is_numeric($this->emultiplier) || $this->emultiplier < 0.01)    $this->emultiplier = 1;
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
                	
                	if(!defined("COINREMITTER_CRYPTOBOX_LOCALISATION")) define("COINREMITTER_CRYPTOBOX_LOCALISATION", json_encode($cryptobox_localisation_coinremitter));

                	$directory   = (defined("COINREMITTER_IMG_FILES_PATH")) ? COINREMITTER_IMG_FILES_PATH : "images/";     // path to directory with coin image files (directory 'images' by 
                	$localisation = json_decode(COINREMITTER_CRYPTOBOX_LOCALISATION, true);
                	$arr 	 			= $_GET;
                	$id 	 			= (defined("COINREMITTER_COINS_HTMLID")) ? COINREMITTER_COINS_HTMLID : "coinremittercryptocoin";

                	// Url for Change Coin
		            $coin_url = $_SERVER["REQUEST_URI"];
		            if (mb_strpos($coin_url, "?")) $coin_url = mb_substr($coin_url, 0, mb_strpos($coin_url, "?"));
		            if (isset($arr[$id])) unset($arr[$id]);
		            $coin_url = "//".$_SERVER["HTTP_HOST"].$coin_url."?".http_build_query($arr).($arr?"&amp;":"").$id."=";


                	foreach ($this->coin_names as $k => $v){
                		$v = preg_replace('/\s+/', '', $v);
                		$CurrAPIKey = get_option(COINREMITTER.$v.'api_key');
                		$CurrPassword = get_option(COINREMITTER.$v.'password');

					    $public_key 	= $CurrAPIKey;
					    $private_key 	= $CurrPassword;
					    
					    if ($private_key && $public_key)
					    {
					        $all_keys[$v] = array("api_key" => $public_key,  "password" => $private_key);
					        $available_coins[] = $v;
					    }
					}
					$iconWidth    = 70;

					if(is_array($available_coins) && sizeof($available_coins)){
	                	foreach ($available_coins as $v){

			                    $v = trim(strtolower($v));
			                    $url = $coin_url.$v."#".$anchor;

			                    $imageDir = dirname(__FILE__).'/images';
								$coin_imge_name = strtolower(preg_replace('/\s+/', '', $v));
								$path = $imageDir.'/'.$coin_imge_name.'.png';
								if(!file_exists($path)){
									$wallet_logo = 'dollar-ico';
								}
								else{
									$wallet_logo = $coin_imge_name;
								}

			                    if ($jquery){ $tmp .= "<input type='radio' class='aradioimage' data-title='".str_replace("%coinName%", ucfirst($v), $localisation["pay_in"])."' ".($coinName==$v?"checked":"")." data-url='$url' data-width='$iconWidth' data-alt='".str_replace("%coinName%", $v, $localisation["pay_in"])."' data-image='".$directory.$wallet_logo.($iconWidth>70?"2":"").".png' name='aradioname' value='$v'>&#160; ".($iconWidth>70 || count($coins)<4?"&#160; ":"");
			                    }
			                    else{ 
			                    	add_action('wp_enqueue_scripts', 'select_paument_coin');
			                    	$tmp .= "<a href='#' rel='".$v."' class='crpObj' ><img style='box-shadow:none; margin-right:30px;padding: 10px;".round($iconWidth/10)."px ".round($iconWidth/6)."px;border:0;display:inline;' width='$iconWidth' title='".str_replace("%coinName%", ucfirst($v), $localisation["pay_in"])."' alt='".str_replace("%coinName%", $v, $localisation["pay_in"])."' src='".$directory.$wallet_logo.($iconWidth>70?"2":"").".png'></a>";
			                    }
			            }
		            }
		           
		            $CryptoOpt = !empty($tmp) ? $Script.$tmp.'<input type="hidden" name="crpopt" id="crpopt" >' : '';
		            $SetPaymentOpt = !empty($CryptoOpt) ? $CoinSript.'<div>'.$CryptoOpt.'</div>' : '';
                	return $SetPaymentOpt;
                }
                public function process_payment( $order_id )
                {
                    global $woocommerce;
                    static $emultiplier = 0;


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
                        update_post_meta( $order_id, '_coinremitter_worder_amountfiat',   ($totalFiat?$totalFiat:$total) );
                    }

                    $total_html = $total;
                    if ($totalFiat) $total_html .= " / <b> ".$totalFiat."</b>";
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


add_action('wp_ajax_withdraw', 'withdraw');
add_action('wp_ajax_nopriv_withdraw', 'withdraw');
function withdraw(){

	$CoinType = sanitize_text_field($_POST['cointype']);
	$APIVal = get_option( COINREMITTER.$CoinType.'api_key' );
 	$PasswordVal = get_option( COINREMITTER.$CoinType.'password' );
 	$TO_Address = isset($_POST['address']) ? sanitize_text_field($_POST['address']) : '';
 	$Amount = $_POST['amount'];
 	
 	$Coin = CoinShortCon($CoinType);
	$postdata = array('api_key' => $APIVal, 'password'=>$PasswordVal,'to_address'=>$TO_Address,'amount'=>$Amount);
    $curl = COINREMITTER_API_URL.$Coin.'/withdraw';
    
    $body = array(
        'api_key' => $APIVal,
        'password' => $PasswordVal,
        'to_address' => $TO_Address,
        'amount' => $Amount,
    );
    $args = array(
        'method'      => 'POST',
        'timeout'     => 45,
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
    echo $response;
	exit(0);
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
  	$new_columns[ 'order_actions' ] = $columns[ 'order_actions' ];
  	return $new_columns;
}
function sv_wc_cogs_add_order_profit_column_content( $column ) {
    global $post;
    $OrdNo = $post->ID;
    $CryptoPrice = get_post_meta( $OrdNo, '_order_crypto_price');
	$CryptoType = get_post_meta( $OrdNo, '_order_crypto_coin');
	
    if( 'crypto_amnt_col' === $column ) {
    	echo !empty($CryptoPrice[0]) ? '<span class="greeColour">'.sprintf('%.8f',$CryptoPrice[0]).' '.$CryptoType[0].'</span>' : '-';
    }
}


add_action( 'manage_shop_order_posts_custom_column', 'sv_wc_cogs_add_order_profit_column_content' );

/* Show crypto price on order detail page back-end after Total Price */
add_action( 'woocommerce_admin_order_totals_after_refunded', 'crypto_amt_on_ord_detail_page', 10, 1 ); 

add_action( 'woocommerce_admin_order_data_after_order_details', 'output_data', 10, 2 );

add_action( 'add_meta_boxes', 'cd_meta_box_add' );

function cd_meta_box_add()
{	 global $wpdb;
	$order_id = $_GET['post'];
	$method = "Transaction History";
	$order_type_object = get_post();
	$payment_query = "SELECT * FROM coinremitter_payments WHERE orderID = 'coinremitterwc.order".$order_id."'";
	$payment_details = $wpdb->get_results($payment_query);
	if(!empty($payment_details)){
  		add_meta_box( 'my-meta-box-id', $method, 'cd_meta_box_cb', $order_type_object->post_type, 'normal', 'high' );
	}
}

function cd_meta_box_cb()  
{  

	global $wpdb;
	$order_id = $_GET['post'];
	$payment_query = "SELECT * FROM coinremitter_payments WHERE orderID = 'coinremitterwc.order".$order_id."'";
	$payment_details = $wpdb->get_results($payment_query);
	$json_data = json_encode($payment_details);
	$array_data = json_decode($json_data,true); 
	$CryptoType = get_post_meta( $order_id, '_order_crypto_coin');
	$paid_trasaction = "SELECT SUM(amount),SUM(amountUSD) FROM coinremitter_payments WHERE orderID = 'coinremitterwc.order".$order_id."'";
	$total_crypto_paid = $wpdb->get_results($paid_trasaction);
	$json_coin_t = json_encode($total_crypto_paid);
	$json_coin_d = json_decode($json_coin_t,true);
	$paid_amount_coin = $json_coin_d[0]['SUM(amount)'];
	$total_usd_amount = $get_order_data[0]->amountUSD;
 	
    if(!empty($array_data)){
		foreach ($array_data as $key => $value) {
			$temp2.='<tr>
						<td class="label greeColour"><a title="'.__('Transaction Details', COINREMITTER).' - '.$value['txID'].'" href="'.$value['explorer_url'].'" target="_blank"><strong>'.substr($value['txID'],0,20).'....</strong></a></td>
					<td> '.sprintf('%.8f',$value['amount']).' '.$value['coinLabel'].'</td>
					<td>'.$value['createdAt'].'</td>
					<td style="text-align:center"><img src="'.plugins_url('/images/checked.gif',__FILE__).'"></td>
				</tr>';	
			$coin = $value['coinLabel'];	
		}

	}
	echo "<table class='wc-crpto-data'>
				<thead>
					<tr>
					<td class='label'>Transaction Id:</td>
					<td >Amount</td>
					<td class='total'>Date</td>
					<td style='text-align:center'>Confirmation</td>
				</tr>".$temp2."
				</thead>
			</table>";
	echo "<table style='padding-left:10px;padding-top:20px;'>
			<thead>
				<th><b>Total :</b></th>
				<th><b>".$paid_amount_coin." ".$coin." </b></th>
			</thead>
		</table>";
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
		.conform{
		    
		}
    </style>
    <?php

}         


function add_wallet(){
	$CoinType = isset($_POST['cointype']) ? sanitize_text_field($_POST['cointype']) : '';
	$CoinAPIKey = isset($_POST['coinapikey']) ? sanitize_text_field($_POST['coinapikey']) : '';
	$CoinPass = isset($_POST['coinpass']) ? sanitize_text_field($_POST['coinpass']) : '';
	$frm_type = isset($_POST['frm_type']) ? sanitize_text_field($_POST['frm_type']) : '';
	$ConResp = checkConn($CoinAPIKey, $CoinPass, $CoinType);
	if($ConResp['flag'] != 1){

		$Result['msg'] = $ConResp['msg'];//$erroMsg;
		$Result['flag'] = $ConResp['flag'];
	}else{
		$amount = $ConResp['data']['balance'];
		update_option(COINREMITTER.$CoinType.'api_key', maybe_serialize($CoinAPIKey));
		update_option(COINREMITTER.$CoinType.'password', maybe_serialize($CoinPass));
		update_option(COINREMITTER.$CoinType.'amount', $amount);
		$Result['msg'] = 'Wallet successfully Added.';
		$Result['flag'] = $ConResp['flag'];
	}
	echo json_encode($Result);
	exit(0);
}
add_action('wp_ajax_add_wallet', 'add_wallet');
add_action('wp_ajax_nopriv_add_wallet', 'add_wallet');

function checkConn($CoinAPIKey, $CoinPass, $CoinType){
	
	$postdata = array('api_key' => $CoinAPIKey, 'password'=>$CoinPass);
	$Coin = CoinShortCon($CoinType);
	$curl = COINREMITTER_API_URL.$Coin.'/get-balance';
    $body = array(
        'api_key' => $CoinAPIKey,
        'password' => $CoinPass,
        );
    $args = array(
        'method'      => 'POST',
        'timeout'     => 45,
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

function getBalanceByCurl($Parm = ''){
	
	$Coin = $Parm['cointype'];
    $curl = COINREMITTER_API_URL.$Coin.'/get-balance';
    $body = array(
        'api_key' => $Parm['api_key'],
        'password' => $Parm['password'],
        );
    $args = array(
        'method'      => 'POST',
        'timeout'     => 45,
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
    $ResultArr = json_decode($response,true);
    return $ResultArr;
}

add_action('wp_ajax_transactionfees', 'transactionfees');
add_action('wp_ajax_nopriv_transactionfees', 'transactionfees');
function transactionfees(){
	$CoinType = sanitize_text_field($_POST['cointype']);
 	$Coin = CoinShortCon($CoinType);

	$APIVal = get_option( COINREMITTER.$CoinType.'api_key' );
	$PasswordVal = get_option( COINREMITTER.$CoinType.'password' );
  	$postdata = array('api_key' => $APIVal, 'password'=>$PasswordVal);
    $curl = COINREMITTER_API_URL.$Coin.'/get-transaction-fees';

    $body = array(
        'api_key' => $APIVal,
        'password' => $PasswordVal,
    );	

    $args = array(
        'method'      => 'POST',
        'timeout'     => 45,
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
	print_r($response);
	exit(0);
}	

add_action('wp_ajax_verifyApi', 'verifyApi');
add_action('wp_ajax_nopriv_verifyApi', 'verifyApi');
function verifyApi(){
	$CoinType = sanitize_text_field($_POST['cointype']);
	$postdata = array('api_key' => sanitize_text_field($_POST['coinapikey']), 'password'=>sanitize_text_field($_POST['coinpass']));
	$Coin = CoinShortCon($CoinType);
	$curl = COINREMITTER_API_URL.$Coin.'/get-balance';

    $body = array(
        'api_key' => sanitize_text_field($_POST['coinapikey']),
        'password' => sanitize_text_field($_POST['coinpass']),
        );
    $args = array(
        'method'      => 'POST',
        'timeout'     => 45,
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
    $Result = json_decode($response,true);
    if($Result['flag'] == 1){
    	update_option(COINREMITTER.$CoinType.'api_key', maybe_serialize($_POST['coinapikey']));
		update_option(COINREMITTER.$CoinType.'password', maybe_serialize($_POST['coinpass']));	
    }
    echo $response;
	exit(0);
}

function deleteCoinData(){

	global $wpdb;
	$CoinType = sanitize_text_field($_POST['cointype']);
	$tablename = $wpdb->prefix."options";
	$Opt_Field1 = 'coinremitter'.$CoinType.'api_key';
	$Opt_Field2 = 'coinremitter'.$CoinType.'password';
	$results = $wpdb->delete( $tablename, array( 'option_name' => $Opt_Field1) );
	$results2 = $wpdb->delete( $tablename, array( 'option_name' => $Opt_Field2 ) );
	
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
add_action('wp_ajax_deleteCoinData', 'deleteCoinData');
add_action('wp_ajax_nopriv_deleteCoinData', 'deleteCoinData');

function myplugin_activate() {
   echo "<div class='updated'><h3>Welcome to [name]</h3>";
}
register_activation_hook( __FILE__, 'myplugin_activate' );
function sample_admin_notice__success(){
	$PageUrl = $_SERVER['REQUEST_URI'];
	if (strpos($PageUrl, 'plugins.php') !== false) {
	    $Basepath = COINREMITTER_ADMIN.'coinremitter';
		$message = '<div class="updated updated notice notice-success is-dismissible" style="padding: 1px 5px 1px 12px; margin: 0; border: none; background: none; border-left: 4px solid #46b450; margin-top:10px; background-color:#ffffff;"><p>Coinremitter plugin activated. Now check add wallet to accept crypto payment. <a href="'.$Basepath.'" > Click here</a></div>';	
		echo $message;
	}
	
}

function CoinShortCon($CoinTyep=''){
	$CoinArr = getActCoins();
	$CoinTyep = preg_replace('/\s+/', '', $CoinTyep);
	
	if(is_array($CoinArr) && sizeof($CoinArr)){
		foreach($CoinArr as $k => $v){
			$coinObj = preg_replace('/\s+/', '', $v['name']);
			if(strtolower($coinObj) == $CoinTyep){
				return $k;
			}
		}
	}
	return 'BTC';
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

function select_paument_coin() {
  wp_enqueue_script ( 'paycheckout', plugins_url('/js/pay-checkout.js', __FILE__) );
}
function getActCoins(){
	$url = COINREMITTER_API_URL_ALL_COIN.'get-coins';
	$arrg = array(
				'headers'     => array(
	    			'Content-Type'  => 'application/json',
				),
				'timeout'     => $timeout,
				'sslverify' => false,
				'user-agent' => 'WordPress/'. $wp_version.';'. get_bloginfo('url'),
			);
     
    $request = wp_remote_get( $url, $args );
    if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
        error_log( print_r( $request, true ) );
    }
    $response = wp_remote_retrieve_body( $request );
    
    $responseArr = json_decode($response,true);
    if($responseArr['flag'] == 1){
    	return $responseArr['data'];
    }
}
?>