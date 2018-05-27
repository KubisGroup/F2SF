<?php
/**
 * Kubis Group F2FS API connector
 * 
 * Connect your Fler.cz eshop with superfaktura.cz. 
 * Its simple, you need only API keys not more because it is fully automated
 */
<<<<<<< HEAD

 /**
  * Config file
  * 
  * @source ./config.php
  */
require_once __DIR__."/config.php";
=======
define("MySQL_Server", "localhost");
define("MySQL_User", "");
define("MySQL_Port", "3306");
define("MySQL_Password", "");
define("MySQL_Database", "");
define("MySQL_Prefix", "");
/**
 * FLER settings
 * set your secret and public key
 * look at: https://www.fler.cz/prodejce/nastroje/api?view=keys
*/
define("FLER_public_KEY", "");
define("FLER_secret_KEY", "");
require_once "./F2SF.php";
>>>>>>> 0be20776b7fddbec9cf5bf6cc835d72eb28c8bc5

/**
 * Show DEBUG PHP ERRORS
 */
<<<<<<< HEAD
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

=======
DEFINE('SFAPI_EMAIL', '');
DEFINE('SFAPI_KEY', '');
DEFINE('SFAPI_MODULE', 'Cron F2SF synchronizer'); // TITLE OF MODULE FE. 'WOOCOMMERCE MODULE'
DEFINE('SFAPI_APPTITLE', 'Kubis Group F2SF connertor'); // TITLE OF YOUR APPLICATION FE.
>>>>>>> 0be20776b7fddbec9cf5bf6cc835d72eb28c8bc5

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