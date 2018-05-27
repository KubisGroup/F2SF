<?php
/**
 * Kubis Group F2FS API connector
 * 
 * Connect your Fler.cz eshop with superfaktura.cz. 
 * Its simple, you need only API keys not more because it is fully automated
 */

 /**
  * Config file
  * 
  * @source ./config.php
  */
require_once __DIR__."/config.php";

/**
 * Show DEBUG PHP ERRORS
 */
if(DEBUG) {
	error_reporting(E_ALL);
	ini_set('display_errors', DEBUG);
	ini_set('display_startup_errors', DEBUG);
	ini_set('track_errors', DEBUG);
	ini_set('html_errors', DEBUG);
	ini_set('log_errors', DEBUG);
}

/**
  * Require functions
  * 
  * @source ./functions.php
  */
require_once __DIR__."/functions.php";


/**
 * Condition what we use
 * If we use Invoices or Orders...
 */
for($FLER_getOrders = 0; $FLER_getOrders <=1; $FLER_getOrders++) {
	if($FLER_getOrders){
	/**
	  * Require functions
	  * 
	  * @source ./F2SF_O.php
	  */
	require_once __DIR__."/F2SF_O.php";
	/**
 	* Connect to fler api
 	*/
	$flerCon = new flerConnector_O(FLER_public_KEY, FLER_secret_KEY);
	} else {
		/**
			* Require functions
			* 
			* @source ./F2SF_I.php
			*/
		require_once __DIR__."/F2SF_I.php";
		/**
		* Connect to fler api
		*/
		$flerCon = new flerConnector_I(FLER_public_KEY, FLER_secret_KEY);
	}
	/**
	 * Run api
	 * 
	 * @return errorcode|null
	 */
	$flerCon->getInvoices();
}