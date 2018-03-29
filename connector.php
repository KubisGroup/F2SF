<?php
require_once "./functions.php";
/**
 * MySQL database information 
 * Database must be created!
 * tables when don't exist will be created
 */
define("MySQL_Server", "");
define("MySQL_User", "");
define("MySQL_Port", "");
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
require_once "./fler.php";

/**
 * SuperFaktura settings
 * set your email and token
 * look at: https://moje.superfaktura.cz/api_access
 */
DEFINE('SFAPI_EMAIL', '');
DEFINE('SFAPI_KEY', '');
DEFINE('SFAPI_MODULE', 'Cron F2SF synchronizer'); // TITLE OF MODULE FE. 'WOOCOMMERCE MODULE'
DEFINE('SFAPI_APPTITLE', 'Kubis Group F2SF connertor'); // TITLE OF YOUR APPLICATION FE.

/**
 * Connect to fler api
 */
$flerCon = new flerConnecor(FLER_public_KEY, FLER_secret_KEY);
$flerCon->getInvoices();
