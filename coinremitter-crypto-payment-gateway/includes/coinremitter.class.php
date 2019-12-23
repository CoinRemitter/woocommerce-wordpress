<?php

if(!defined("COINREMITTER_CRYPTOBOX_WORDPRESS")) define("COINREMITTER_CRYPTOBOX_WORDPRESS", true); 

elseif (!defined('ABSPATH')) exit; // Wordpress


define("COINREMITTER_CRYPTOBOX_VERSION", "0.1");

// CoinRemitter supported crypto currencies
define("COINREMITTER_CRYPTOBOX_COINS", json_encode(array('bitcoin', 'bitcoincash', 'litecoin', 'ethereum', 'dogecoin', 'tether', 'dash')));

class CoinRemitterCrypto{
        private $api_key 	= "";		
	private $password 	= "";		
	
	private $amount 		= 0;	
	private $amountUSD 		= 0;		
	
	private $language		= "en";		
	
	private $orderID 		= "";		
	private $userID 		= "";		
							
	private $userFormat 	= "COOKIE"; 
	  
	
	private $boxID			= 0; 		
	private $coinLabel		= ""; 		
	private $coinName		= ""; 		
	private $paid			= false;	
	private $confirmed		= false;	
	private $paymentID		= false;	
	private $paymentDate	= "";		
	private $amountPaid 	= 0;		
		
	private $boxType		= "";	
	private $processed		= false;
	private $localisation 	= "";		
	public $longname;
        public function __construct($options = array()) 
	{
		
		// Min requirements
		if (!function_exists( 'mb_stripos' ) || !function_exists( 'mb_strripos' ))  die(sprintf("Error. Please enable <a target='_blank' href='%s'>MBSTRING extension</a> in PHP. <a target='_blank' href='%s'>Read here &#187;</a>", "http://php.net/manual/en/book.mbstring.php", "http://www.knowledgebase-script.com/kb/article/how-to-enable-mbstring-in-php-46.html"));
		if (!function_exists( 'curl_init' )) 										die(sprintf("Error. Please enable <a target='_blank' href='%s'>CURL extension</a> in PHP. <a target='_blank' href='%s'>Read here &#187;</a>", "http://php.net/manual/en/book.curl.php", "http://stackoverflow.com/questions/1347146/how-to-enable-curl-in-php-xampp"));
		if (!function_exists( 'mysqli_connect' )) 									die(sprintf("Error. Please enable <a target='_blank' href='%s'>MySQLi extension</a> in PHP. <a target='_blank' href='%s'>Read here &#187;</a>", "http://php.net/manual/en/book.mysqli.php", "http://crybit.com/how-to-enable-mysqli-extension-on-web-server/"));
		if (version_compare(phpversion(), '5.4.0', '<')) 							die(sprintf("Error. You need PHP 5.4.0 (or greater). Current php version: %s", phpversion()));
		
		
		foreach($options as $key => $value) 
			if (in_array($key, array("api_key", "password", "coinName", "coinLabel" ,"amount", "amountUSD", "period", "language", "orderID", "userID", "userFormat"))) $this->$key = (is_string($value)) ? trim($value) : $value;

		
		if ($this->amount 	 && strpos($this->amount, ".")) 	$this->amount = rtrim(rtrim($this->amount, "0"), ".");
		if ($this->amountUSD && strpos($this->amountUSD, ".")) 	$this->amountUSD = rtrim(rtrim($this->amountUSD, "0"), ".");

		if (!$this->amount || $this->amount <= 0) 		$this->amount 	 = 0;
		if (!$this->amountUSD || $this->amountUSD <= 0) 	$this->amountUSD = 0;
		
		if (($this->amount <= 0 && $this->amountUSD <= 0) || ($this->amount > 0 && $this->amountUSD > 0)) die("You can use in cryptobox options one of variable only: amount or amountUSD. You cannot place values in that two variables together (submitted amount = '".$this->amount."' and amountUSD = '".$this->amountUSD."' )");
		 
		if ($this->amount && (!is_numeric($this->amount) || $this->amount < 0.0000001 || $this->amount > 500000000)) die("Invalid Amount - ".sprintf('%.8f', $this->amount)." $this->coinLabel. Allowed range: 0.0000001 .. 500,000,000");
		if ($this->amountUSD && (!is_numeric($this->amountUSD) || $this->amountUSD < 0.01 || $this->amountUSD > 1000000)) die("Invalid amountUSD - ".sprintf('%.8f', $this->amountUSD)." USD. Allowed range: 0.01 .. 1,000,000");
		
		
		$this->localisation = json_decode(COINREMITTER_CRYPTOBOX_LOCALISATION, true);
		$this->localisation = $this->localisation[$this->language];
		
		$this->userID = trim($this->userID);
		if ($this->userID && preg_replace('/[^A-Za-z0-9\.\_\-\@]/', '', $this->userID) != $this->userID) die("Invalid User ID - $this->userID. Allowed symbols: a..Z0..9_-@.");
		if (strlen($this->userID) > 50) die("Invalid User ID - $this->userID. Max: 50 symbols");
		
		$this->orderID = trim($this->orderID);
		if ($this->orderID && preg_replace('/[^A-Za-z0-9\.\_\-\@]/', '', $this->orderID) != $this->orderID) die("Invalid Order ID - $this->orderID. Allowed symbols: a..Z0..9_-@.");
		if (!$this->orderID || strlen($this->orderID) > 50) die("Invalid Order ID - $this->orderID. Max: 50 symbols");
		
		
		$this->check_payment();
		
		return true;
	}
        
        public function is_paid($remotedb = false)
	{
		if (!$this->paymentID && $remotedb) $this->check_payment($remotedb);
		if ($this->paid) return true;
		else return false;
	}
        
        public function is_confirmed()
	{
		if ($this->confirmed) return true;
		else return false;
	}
        
        public function amount_paid()
	{
		if ($this->paid) return $this->amountPaid; 
		else return 0;
	}
        
        public function is_processed()
	{
		if ($this->paid && $this->processed) return true;
		else return false;
	}
        public function cryptobox_type()
	{
		return $this->boxType;
	}
        public function payment_id()
	{
		return $this->paymentID;
	}
        public function payment_date()
	{
		return $this->paymentDate;
	}
        public function coin_name()
	{
		return $this->coinName;
	}
        public function coin_label()
	{
		return $this->coinLabel;
	}
        public function payment_status_text()
	{
        if ($this->paid) $txt = str_replace(array("%coinName%", "%coinLabel%", "%amountPaid%"), array($this->coinName, $this->coinLabel, $this->amountPaid), ($this->boxType=="paymentbox"?"%coinName% Payment System received %amountPaid% %coinLabel% successfully !":"<b>%coinNames% have not yet been received.</b><br>If you have already sent %coinNames% (the exact %coinName% sum in one payment as shown in the box below), please wait a few minutes to receive them by %coinName% Payment System. If you send any other sum, Payment System will ignore the transaction and you will need to send the correct sum again, or contact the site owner for assistance."));
        else $txt = str_replace(array("%coinName%", "%coinNames%", "%coinLabel%"), array($this->coinName, (in_array($this->coinLabel, array('BCH', 'DASH'))?$this->coinName:$this->coinName.'s'), $this->coinLabel), "<b>%coinNames% have not yet been received.</b><br>If you have already sent %coinNames% (the exact %coinName% sum in one payment as shown in the box below), please wait a few minutes to receive them by %coinName% Payment System. If you send any other sum, Payment System will ignore the transaction and you will need to send the correct sum again, or contact the site owner for assistance.");
	             
	    return $txt;        
	}
        public function payment_info()
	{
		$obj = ($this->paymentID) ? run_sql_coinremitter("SELECT * FROM coinremitter_payments WHERE paymentID = $this->paymentID LIMIT 1") : false;
		
		return $obj;
	}
        public function set_status_processed()
	{
		if ($this->paymentID && $this->paid)
		{
			if (!$this->processed)
			{
				$sql = "UPDATE coinremitter_payments SET processed = 1, processedDate = '".gmdate("Y-m-d H:i:s")."' WHERE paymentID = $this->paymentID LIMIT 1";
				run_sql_coinremitter($sql);
				$this->processed = true;
			}
			return true;
		}
		else return false;
	}
    
    public function getCoinShortName(){

    	$CoinArr = getActCoins();
    	
		if(is_array($CoinArr) && sizeof($CoinArr)){
			foreach($CoinArr as $k => $v){
				$coin = preg_replace('/\s+/', '', $v['name']);
				if(strtolower($coin) == $this->longname){
					return $k;
				}
			}
		}

  
    	return 'BTC';
    }
    public function coinremitter_cryptobox_json_url($CoinLable = '')
	{
		if(is_admin()){
			$this->longname = $CoinLable;
		}else{
			$queries = array();
			parse_str($_SERVER['QUERY_STRING'], $queries);

			$this->longname = $queries['vcni'];
		}
		$this->coinLabel = $this->getCoinShortName();
		$ApiCoin = $this->getCoinShortName();
	    $url = COINREMITTER_API_URL.$ApiCoin.'/';
	    return $url;
	}
    
   
        
    public function get_balance($coinArr)
    {
    	
        $url = $this->coinremitter_cryptobox_json_url($coinArr['coinLabel']);
    	$url = $url.'get-balance';
    	$CoinType = $coinArr['coinLabel'];

        $param = [
            'api_key'=> $coinArr['api_key'],//$this->api_key,
            'password'=> $coinArr['password'],//$this->password,
        ];
        $bal = $this->exec_url($url,$param);
        return $bal;
    }
        
        public function exec_url($url,$post='')
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
	    $ResultArr = json_decode($response,true);
		return $ResultArr;
	}
        
    
	
	private function check_payment($remotedb = false)
	{
		static $already_checked = false;
	    
		$this->paymentID = $diff = 0;
		
		$obj = run_sql_coinremitter("SELECT paymentID, amount, amountUSD, txConfirmed, txCheckDate, txDate, processed FROM coinremitter_payments WHERE orderID = '$this->orderID' && userID = '$this->userID'  ORDER BY txDate DESC LIMIT 1");
	
		if ($obj)
		{
			$this->paymentID 		= $obj->paymentID;
			$this->paymentDate 		= $obj->txDate;
			$this->amountPaid 		= $obj->amount;
			$this->amountPaidUSD 	= $obj->amountUSD;
			$this->paid 			= true;
			$this->confirmed 		= $obj->txConfirmed;
			
			$this->processed 		= ($obj->processed) ? true : false;
			
		}
		return true;
	}
        public function left($str, $findme, $firstpos = true)
	{
		$pos = ($firstpos)? stripos($str, $findme) : strripos($str, $findme);
	
		if ($pos === false) return $str;
		else return substr($str, 0, $pos);
	}
	public function right($str, $findme, $firstpos = true)
	{
		$pos = ($firstpos)? stripos($str, $findme) : strripos($str, $findme);
	
		if ($pos === false) return $str;
		else return substr($str, $pos + strlen($findme));
	}
	public function icrc32($str)
	{
		$in = crc32($str);
		$int_max = pow(2, 31)-1;
		if ($in > $int_max) $out = $in - $int_max * 2 - 2;
		else $out = $in;
		$out = abs($out);
		 
		return $out;
	}
	private function ua($agent = true)
	{
	    return (isset($_SERVER['REQUEST_SCHEME'])?$_SERVER['REQUEST_SCHEME']:'http') . '://' . $_SERVER["SERVER_NAME"] . (isset($_SERVER["REDIRECT_URL"])?$_SERVER["REDIRECT_URL"]:$_SERVER["PHP_SELF"]) . ' | GU ' . (CRYPTOBOX_WORDPRESS?'WORDPRESS':'PHP') . ' ' . CRYPTOBOX_VERSION . ($agent && isset($_SERVER["HTTP_USER_AGENT"])?' | '.$_SERVER["HTTP_USER_AGENT"]:'');
	}
	
}

    
    function run_sql_coinremitter($sql)
	{
		static $mysqli;
	
		$f = true;
		$g = $x = false;
		$res = array();
	
		if (!$mysqli)
		{
			$dbhost = DB_HOST;
			$port = NULL; $socket = NULL; 
			if (strpos(DB_HOST, ":"))
			{ 
				list($dbhost, $port) = explode(':', DB_HOST);
				if (is_numeric($port)) $port = (int) $port;
				else
				{
					$socket = $port;
					$port = NULL;
				}
			}
			$mysqli = @mysqli_connect($dbhost, DB_USER, DB_PASSWORD, DB_NAME, $port, $socket);			
			if (mysqli_connect_errno())
			{
				echo "<br /><b>Error. Can't connect to your MySQL server.</b> You need to have PHP 5.2+ and MySQL 5.5+ with mysqli extension activated. <a href='http://crybit.com/how-to-enable-mysqli-extension-on-web-server/'>Instruction &#187;</a>\n";
				
				die("<br />Server has returned error - <b>".mysqli_connect_error()."</b>");
			}
			$mysqli->query("SET NAMES utf8");
		}

		$query = $mysqli->query($sql);

		if ($query === FALSE)
        {
            if (!COINREMITTER_CRYPTOBOX_WORDPRESS && stripos(str_replace('"', '', str_replace("'", "", $mysqli->error)), "coinremitter_payments doesnt exist"))
            {
                // Try to create new table - https://github.com/cryptoapi/Payment-Gateway#mysql-table
                $mysqli->query("CREATE TABLE `coinremitter_payments` (
			  `paymentID` int(11) unsigned NOT NULL AUTO_INCREMENT,
			  `orderID` varchar(50) NOT NULL DEFAULT '',
			  `userID` varchar(50) NOT NULL DEFAULT '',
			  `coinLabel` varchar(8) NOT NULL DEFAULT '',
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
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

                $query = $mysqli->query($sql);  // re-run previous query
            }
            if ($query === FALSE) die("MySQL Error: ".$mysqli->error."; SQL: $sql");
        }

		if (is_object($query) && $query->num_rows)
		{
			while($row = $query->fetch_object())
			{
				if ($f)
				{
					if (property_exists($row, "idx")) $x = true;
					$c = count(get_object_vars($row));
					if ($c > 2 || ($c == 2 && !$x)) $g = true;
					elseif (!property_exists($row, "nme")) die("Error in run_sql_coinremitter() - 'nme' not exists! SQL: $sql");
					$f = false;
				}
	
				if (!$g && $query->num_rows == 1 && property_exists($row, "nme")) return $row->nme;
				elseif ($x) $res[$row->idx] = ($g) ? $row : $row->nme;
				else $res[] = ($g) ? $row : $row->nme;
			}
		}
		elseif (stripos($sql, "insert ") !== false) $res = $mysqli->insert_id;

		if (is_object($query)) $query->close();
		if (is_array($res) && count($res) == 1 && isset($res[0]) && is_object($res[0])) $res = $res[0];

		return $res;
	}
    
    // en - English
	$cryptobox_localisation_coinremitter	= array(
							"en" => array("name"		=> "English", 
								"button"			=> "Click Here if you have already sent %coinNames%",
								"msg_not_received" 	=> "<b>%coinNames% have not yet been received.</b><br>If you have already sent %coinNames% (the exact %coinName% sum in one payment as shown in the box below), please wait a few minutes to receive them by %coinName% Payment System. If you send any other sum, Payment System will ignore the transaction and you will need to send the correct sum again, or contact the site owner for assistance.",
								"msg_received" 	 	=> "%coinName% Payment System received %amountPaid% %coinLabel% successfully !",
								"msg_received2" 	=> "%coinName% Captcha received %amountPaid% %coinLabel% successfully !",
								"payment"			=> "Select Payment Method",
								"pay_in"			=> "Payment in %coinName%",
								"loading"			=> "Loading ...")
                                        );

	if(!defined("COINREMITTER_CRYPTOBOX_LOCALISATION")) define("COINREMITTER_CRYPTOBOX_LOCALISATION", json_encode($cryptobox_localisation_coinremitter));
	unset($cryptobox_localisation_coinremitter);  
	
	