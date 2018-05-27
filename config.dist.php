<?php
/**
 * Kubis Group F2FS API connector
 * 
 * Connect your Fler.cz eshop with superfaktura.cz. 
 * Its simple, you need only API keys not more because it is fully automated
 */

/**
 * System API configuration
 */


/**
 * DEBUG
 * Show debug information 
 * @param true/false
 */
define("DEBUG", true);

/**
 * MySQL database information 
 * Database must be created!
 * tables when don't exist will be created
 */

/**
  * MySQL server information
  */
define("MySQL_Server", "");
/**
  * MySQL user information
  */
define("MySQL_User", "");
/**
  * MySQL port information
  */
define("MySQL_Port", "3306");
/**
  * MySQL Password information
  */
define("MySQL_Password", "");
/**
  * MySQL Database information
  */
define("MySQL_Database", "");
/**
  * MySQL table prefix information
  */
define("MySQL_Prefix", "");

/**
 * FLER settings - 
 * set your secret and public key... 
 * look at: https://www.fler.cz/prodejce/nastroje/api?view=keys
*/
define("FLER_public_KEY", "");
define("FLER_secret_KEY", "");

/**
 * SuperFaktura settings - 
 * set your email and token... 
 * look at: https://moje.superfaktura.cz/api_access
 */

/**
  * SuperFaktura API EMAIL
  */
DEFINE('SFAPI_EMAIL', ''); //secret forest
/**
  * SuperFaktura API TOKEN
  */
DEFINE('SFAPI_KEY', ''); //secret forest


/**
  * SuperFaktura API MODULE NAME
  */
DEFINE('SFAPI_MODULE', 'Cron F2SF synchronizer'); // TITLE OF MODULE FE. 'WOOCOMMERCE MODULE'
/**
  * SuperFaktura API TITLE
  */
DEFINE('SFAPI_APPTITLE', 'Kubis Group F2SF connertor'); // TITLE OF YOUR APPLICATION FE.

/**
 * Company Setting
 * 
 * Set your company informations
 */

/**
  * Issued by - company name
  */
 define("COMPANY_issued_by","");
 /**
  * ISSUED by - phone
  */
 define("COMPANY_issued_by_phone","");
 /**
  * ISSUED by - email
  */
 define("COMPANY_issued_by_email","");
 /**
  * ISSUED by - website url
  */
 define("COMPANY_issued_by_web","");
