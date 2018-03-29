<?php
require_once "./functions.php";
/**
 * MySQL database information 
 * Database must be created!
 * tables when don't exist will be created
 */
define("MySQL_Server", "localhost");
define("MySQL_User", "root");
define("MySQL_Port", "3306");
define("MySQL_Password", "djkubis41791");
define("MySQL_Database", "fler");
//define("MySQL_Prefix", "oc_");
define("MySQL_Prefix", "ac_");
/**
 * FLER settings
 * set your secret and public key
 * look at: https://www.fler.cz/prodejce/nastroje/api?view=keys
*/
define("FLER_public_KEY", "o9al8ow7mosazrhclsi6");
define("FLER_secret_KEY", "yG3XMnJC2A2G3u0x6Z458DN8PW1266xZvKhi03bU");
require_once "./F2SF.php";

/**
 * SuperFaktura settings
 * set your email and token
 * look at: https://moje.superfaktura.cz/api_access
 */
DEFINE('SFAPI_EMAIL', 'dj-kubis@reversity.org');
DEFINE('SFAPI_KEY', '5e911ec9762d8786ed03bfe74255f41f');
DEFINE('SFAPI_MODULE', 'Cron F2SF synchronizer'); // TITLE OF MODULE FE. 'WOOCOMMERCE MODULE'
DEFINE('SFAPI_APPTITLE', 'Kubis Group F2SF connertor'); // TITLE OF YOUR APPLICATION FE.

/**
 * Connect to fler api
 */
$flerCon = new flerConnecor(FLER_public_KEY, FLER_secret_KEY);
$flerCon->getInvoices();
