<?php
// Fler.cz api driver
require_once(__DIR__.'/flerAPI/Fler_API_REST_Driver.php');

// SuperFaktura.cz api driver
//require_once(__DIR__.'/vendor/superfaktura/apiclient/SFAPIclient/SFAPIclient.php');

//composer autoloader
require_once(__DIR__.'/vendor/autoload.php');

class flerConnecor {
	private $flerAPI;
	private $superfakturaAPI;
	//database configurations
	private $database;
	private $databaseServer = MySQL_Server;
	private $databaseUser = MySQL_User;
	private $databasePassword = MySQL_Password;
	private $databaseDatabase = MySQL_Database;
	private $databasePrefix = MySQL_Prefix;
	//table list + create table data
	private $database_tables = Array(
		array (
			"name" => "flerInvoices" , 
			"create" => "CREATE TABLE IF NOT EXISTS `[prefix]flerInvoices`
				(
					`id` INT AUTO_INCREMENT NOT NULL, 
					`flerInvoice_id` INT NOT NULL COMMENT 'Fler Invoice ID', 
					`flerInvoice_evid_num` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci  COMMENT 'Fler Invoice TEXT ID', 
					`flerInvoice_position` INT  COMMENT 'Fler order counter', 
					`flerInvoice_for_year` varchar(4) CHARACTER SET utf8 COLLATE utf8_general_ci COMMENT 'Fler invoice for year', 
					`flerInvoice_for_month` varchar(7) CHARACTER SET utf8 COLLATE utf8_general_ci COMMENT 'Fler month when was be created', 
					`flerInvoice_id_order` INT COMMENT 'Fler order ID', 
					PRIMARY KEY (`id`)
				)  CHARACTER SET utf8 COLLATE utf8_general_ci
			"
		),
		array (
			"name" => "superfakturaSync",
			"create" => "CREATE TABLE IF NOT EXISTS `[prefix]superfakturaSync`
				(
					`id` INT AUTO_INCREMENT NOT NULL,
					`flerInvoice_id` INT NOT NULL COMMENT 'Fler Invoice ID',
					`flerInvoice_cid` BOOLEAN COMMENT 'Fler Client (BUYER) ID',
					`flerInvoice_order_created` DATETIME COMMENT 'Fler Order Created',
					`sync_date` TIMESTAMP COMMENT 'Last synchronization timestamp',
					`superfaktura_create_date` TIMESTAMP COMMENT 'Created timestamp',
					`superfaktura_update_date` TIMESTAMP COMMENT 'Update timestamp',
					`superfaktura_invoice_id` INT NOT NULL COMMENT 'Invoice ID', 
					`superfaktura_cid` INT NOT NULL COMMENT 'Client (BUYER) ID',
					`superfaktura_paid_type` ENUM( 'regular', 'proforma', 'estimate', 'cancel', 'order', 'delivery' ) CHARACTER SET utf8 COLLATE utf8_general_ci COMMENT 'Invoice type',
					PRIMARY KEY (`id`)
				)  CHARACTER SET utf8 COLLATE utf8_general_ci
			"
		)
	);
	
	/**
	 * Paid statuses 
	 */
	private $FpaidStatuses = Array (
		"UHRAZENA" => "regular", // Bežná
		"VYRIZENA" => "proforma", // Zálohová faktura
		"ODMITNUTA" => "cancel", // Dobropis
		"PRIJATA" => "order", // Přijatá objednávka
		"" => "estimate", // Cenová nabídka
		"VYRIZENA" => "delivery" // Dodací list
	);

	private $SFpaidStatuses = Array ( 
		"regular" => "Bežná", 
		"proforma" => "Zálohová faktura",
		"estimate" => "Cenová nabídka",
		"cancel" => "Dobropis",
		"order" => "Přijatá objednávka",
		"delivery" => "Dodací list",
	);

	/**
	 * Construct
	 * 
	 */
	public function __construct($publicKey, $secretKey, $SFAPI_EMAIL, $SFAPI_KEY, $SFAPI_APPTITLE, $SFAPI_MODULE) {
		//FLER connect
		$this->flerAPI = new Fler_API_REST_Driver();
		$this->flerAPI->setKeys($publicKey, $secretKey);
		$this->flerAPI->setHost('http://www.fler.cz');

		//MySQL connect
		try {
			$this->database = new PDO('mysql:dbname=' .$this->databaseDatabase . ';host=' . $this->databaseServer . '', $this->databaseUser, $this->databasePassword);
			$this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

			foreach ($this->database_tables as $table) {
				$this->tableExists($table["name"], $table["create"], $this->databasePrefix);
			}
		} catch (PDOException $e) {
			die("Connection failed: \n" . $e->getMessage());
		}
	}

	/**
	 * Check if a table exists in the current database.
	 *
	 * @param PDO $pdo PDO instance connected to a database.
	 * @param string $prefix.$table Table to search for.
	 */
	private function tableExists($table, $create, $prefix) {
		// Try a select statement against the table
		// Run it in try/catch in case PDO is in ERRMODE_EXCEPTION.
		try {
			$result = $this->database->query("SELECT * FROM ".$prefix.$table." LIMIT 1");
			$result->execute();

			$result = $this->database->query("TRUNCATE ".$prefix.$table."");
			$result->execute();
		} catch (Exception $e) {
			//create table
			try {
				$result = $this->database->query( str_replace("[prefix]", $prefix, $create) );
				$result->execute();
				echo "table \"".$prefix.$table."\" created\n";
			} catch (Exception $e) {
				die("Create table error: \n" . $e);
			}
			
		}

		// Result is either boolean FALSE (no table found) or PDOStatement Object (table found)
		return $result !== FALSE;
	}

	public function getInvoices() {
		// list of invoices
		// /api/rest/seller/invoice/order/list
		unset($response);
		$response = $this->flerAPI->request('GET', '/api/rest/seller/invoice/order/list');

		if($response->http_code == 200 && empty($response->data["error"])) {
			//read all invoices
			for($inv = 0; $inv < count($response->data); $inv++) {
				$invoice = $response->data[$inv];
				//check data if is in database
				$resultTest = $this->database->prepare("SELECT 1  FROM `".$this->databasePrefix."flerInvoices` WHERE `flerInvoice_id` = ? AND `flerInvoice_id_order` = ?");
				$resultTest->execute(
					array(
						$invoice["id"],
						$invoice["id_order"],
					)
				);
				//if isn't in database that we add new row
				if (!$resultTest->fetchColumn()) {
					try {
						$result = $this->database->prepare("INSERT INTO `".$this->databasePrefix."flerInvoices` (`id`, `flerInvoice_id`, `flerInvoice_evid_num`, `flerInvoice_position`, `flerInvoice_for_year`, `flerInvoice_for_month`, `flerInvoice_id_order`) VALUES (NULL, ?, ?, ?, ?, ?, ?);");
						$result->execute(array(
							$invoice["id"],
							$invoice["evid_num"],
							$invoice["position"],
							$invoice["for_year"],
							$invoice["for_month"],
							$invoice["id_order"]
						));
						$this->getInvoiceDetail($invoice["id"]);
					} catch (Exception $e) {
						die("Insert invoice error: \n" . $e);
					}
				}
			}
		}
		else {
			die("Api error (".$response->data["error_number"]."): \"".$response->data["error"]."\"\n");
		}
	}

	private function getInvoiceDetail($invoiceID) {
		// detail of invoice by invoice ID
		// /api/rest/seller/invoice/order/detail/<INVOICE_ID>
		unset($infoDB, $clientData, $setInvoice, $addInvoiceItem, $invoiceDetail);
		$invoiceDetail = $this->flerAPI->request('GET', '/api/rest/seller/invoice/order/detail/'.$invoiceID);
		if($invoiceDetail->http_code == 200 && empty($invoiceDetail->data["error"])) {
				$invoiceInfo = $invoiceDetail->data["info"]; //info about order
				$invoicePaymentSummary = $invoiceDetail->data["payment_summary"];
				$invoiceProvider = $invoiceDetail->data["provider"];
				$invoiceRecipient = $invoiceDetail->data["recipient"]; //client
				$invoiceItems = $invoiceDetail->data["items"];
				$invoiceVatSummary = $invoiceDetail->data["vat_summary"];
				$invoicePayment = $invoiceDetail->data["payment"];
				$invoiceText = $invoiceDetail->data["texts"];
				//get order INFO 
				$orderInfo = $this->getOrderInfo( $invoiceInfo["id_order"] );
				//order informations
				$orderState = ( $orderInfo["state"] == "UHRAZENA" ? $this->FpaidStatuses[ $orderInfo["state"] ] : "TATO FAKTURA (id: ".$invoiceInfo["id"].") objednavky (order id: ".$invoiceInfo["id_order"].") NEBUDE POSLANA DO SUPERFAKTURY" ) ;
				$orderStateNormal = $orderInfo["state"];
				$orderCreated = $orderInfo["date_created"];
				$orderBuyerID = $orderInfo["buyer_uid"];
				/* superfaktura add invoice */
				//Client information
				$clientData = array(
						'name'    => (!empty($invoiceRecipient["company"]) ? $invoiceRecipient["company"]." | " : "") . $invoiceRecipient["name"],
						'company'    => (!empty($invoiceRecipient["company"]) ? $invoiceRecipient["company"] : ""),
						'ico'     => (!empty($invoiceRecipient["business_id"]) ? $invoiceRecipient["business_id"] : ""),
						'dic'     => (!empty($invoiceRecipient["tax_id"]) ? $invoiceRecipient["tax_id"] : ""),
						'email'   => (!empty($invoiceRecipient["email"]) ? $invoiceRecipient["email"] : ""),
						'address' => (!empty($invoiceRecipient["address"]) ? $invoiceRecipient["address"] : ""),
						'city'    => (!empty($invoiceRecipient["city"]) ? $invoiceRecipient["city"] : ""),
						'country' => (!empty($invoiceRecipient["country_name"]) ? $invoiceRecipient["country_name"] : ""),
						'zip'     => (!empty($invoiceRecipient["zip"]) ? $invoiceRecipient["zip"] : ""),
						'phone'   => (!empty($invoiceRecipient["phone"]) ? str_replace(" ", "", $invoiceRecipient["phone"]) : ""),
						'currency' => (!empty($invoicePaymentSummary["currency"]) ? $invoicePaymentSummary["currency"] : "czk") 
				);
				//bank information
				$bkAccount = Array();
				foreach($invoicePayment["bank_accounts"] as $bankAccount) {
					$bkAccount[] = array(
						'bank_name' => $bankAccount["bank_name"],
						'account' => $bankAccount["account_number"],
						'bank_code' => $bankAccount["bank_code"],
						'iban' => $bankAccount["iban"],
						'swift' => $bankAccount["swift"],
					);
				}
				//Invoice information
				$setInvoice = Array (
					//all items are optional, if not used, they will be filled automatically
					'name' => 'Fler.cz objednávka č.'.$invoiceInfo["id_order"],
					'issued_by' => 'Kubis Group F2SF robot api',
					'issued_by_phone' => '+420774015557',
					'issued_by_email' => 'jan.kubka@kubisgroup.org',
					'issued_by_web' => 'http://www.kubisgroup.org',
					'order_no' => $invoiceInfo["id_order"],
					'invoice_no_formatted' => $invoiceInfo["id"],
					'created' => $invoiceInfo["date_created"],
					'delivery' => $invoiceInfo["date_issue"],
					'due' => $invoiceInfo["date_due"],
					'payment_type' => $invoicePayment["method"],
					'variable' => $invoicePayment["variable_symbol"], //variable symbol / reference
					'constant' => $invoicePayment["constant_symbol"], //constant symbol
					'specific' => $invoicePayment["specific_symbol"], //specific symbol
					'already_paid' => ($orderState == "regular" ? true : false), //has the invoices been already paid?
					'comment' => 'Fler.cz objednávka č.'.$invoiceInfo["id_order"],
					'invoice_currency' => $invoicePaymentSummary["currency"],
					'bank_accounts' => array(
						$bkAccount
					)
				);
				//add invoice item, this can be called multiple times
				//if you are not a VAT registered, use tax = 0
				$addInvoiceItem;
				foreach($invoiceItems as $items) {
					$addInvoiceItem[] = array( 
						'name' => $items["label"], 
						'description' => $items["description"], 
						'quantity' => $items["unit_number"], //množství 
						'unit' => $items["unit_name"], //jednotka 
						'unit_price' => $items["unit_price"], //cena bez DPH, resp. celková cena, pokud nejste platci DPH 
						'tax' => 0, //sazba DPH, pokud nejste plátcem DPH, zadajte 0 
						'stock_item_id' => $items["unit_id"], //id skladové položky 
						'sku' => '', //skladové označení 
						//'discount' => 0, //Sleva na položku v %
						//'discount_description' => '',
						'load_data_from_stock' => false //Načíst nevyplněné údaje položky ze skladu 
					);
				}
				//database informations
				$infoDB = Array (
					"flerInvoice_id" => $invoiceInfo["id"], //Fler Invoice ID
					"flerInvoice_cid" => $orderBuyerID, //Fler Client (BUYER) ID
					"flerInvoice_order_created" => $orderCreated, //Fler Order Created
					"superfaktura_paid_type" => $orderState, //Invoice type
				);
				//save data to superfaktura but only paid orders
				if($orderStateNormal == "UHRAZENA") {
					$this->superFacturaAdd($infoDB, $clientData, $setInvoice, $addInvoiceItem);
					unset($infoDB, $clientData, $setInvoice, $addInvoiceItem, $invoiceDetail);
				}
		}
		else {
			die("Api error (".$invoiceDetail->data["error_number"]."): \"".$invoiceDetail->data["error"]."\"\n");
		}
	}

	private function getOrderInfo($orderID) {
		// List of order state by ID
		// /api/rest/seller/orders/detail/<ORDER_ID>
		$orderDetail = $this->flerAPI->request('GET', '/api/rest/seller/orders/detail/'.$orderID);
		if($orderDetail->http_code == 200 && empty( $orderDetail->data["error"] ) ) {
			return array (
				"state" => $orderDetail->data["order"]["state"],
				"date_created" => $orderDetail->data["order"]["date_created"],
				"buyer_uid" => $orderDetail->data["order"]["buyer_uid"]
			);
		}
		else {
			die("Api error (".$orderDetail->data["error_number"]."): \"".$orderDetail->data["error"]."\"\n");
		}
	}

	private function superFacturaAdd(array $infoDB, array $clientData, array $setInvoice, array $addInvoiceItems) {
		/*
		`flerInvoice_id` $infoDB["flerInvoice_id"]//Fler Invoice ID
		`flerInvoice_cid`$infoDB["flerInvoice_cid"]//Fler Client (BUYER) ID
		`flerInvoice_order_created` $infoDB["flerInvoice_order_created"]//Fler Order Created
		`sync_date` //Last synchronization timestamp
		`superfaktura_create_date` //Created timestamp
		`superfaktura_update_date` //Update timestamp
		`superfaktura_invoice_id` [id]//Invoice ID 
		`superfaktura_cid` ["client_id"] //Client (BUYER) ID
		`superfaktura_paid_type` $infoDB["superfaktura_paid_type"] //Invoice type

		INSERT INTO `ac_superfakturaSync` (
		`id` ,
		`flerInvoice_id` ,
		`flerInvoice_cid` ,
		`flerInvoice_order_created` ,
		`sync_date` ,
		`superfaktura_create_date` ,
		`superfaktura_update_date` ,
		`superfaktura_invoice_id` ,
		`superfaktura_cid` ,
		`superfaktura_paid_type`
		)
		VALUES (
		NULL , '?', '?', ? , CURRENT_TIMESTAMP , NOW( ) , NULL , '?', '?', 'regular'
		);

		*/
		//SuperFactura connect
		$superfakturaAPI = new SFAPIclientCZ(SFAPI_EMAIL, SFAPI_KEY, SFAPI_APPTITLE, SFAPI_MODULE);
		//setup client data
		$superfakturaAPI->setClient( $clientData );
		//setup invoice data
		$superfakturaAPI->setInvoice( $setInvoice );
		//add invoice item, this can be called multiple times
		//if you are not a VAT registered, use tax = 0
		foreach($addInvoiceItems as $items) {
			$superfakturaAPI->addItem( $items );
			unset($items);
		}
		//save invoice
		$response = $superfakturaAPI->save();
		unset($infoDB, $clientData, $setInvoice, $addInvoiceItem, $invoiceDetail, $superfakturaAPI);
		// response object contains data about created invoices, or error messages respectively
		if($response->error === 0) {
			//complete information about created invoice
			echo "<pre>Data: <br>\n";
			var_dump($response->data);
			echo "</pre><hr>";

			/*try {
				$result = $this->database->prepare("INSERT INTO `".$this->databasePrefix."superfakturaSync` (
					`id` ,
					`flerInvoice_id` ,
					`flerInvoice_cid` ,
					`flerInvoice_order_created` ,
					`sync_date` ,
					`superfaktura_create_date` ,
					`superfaktura_update_date` ,
					`superfaktura_invoice_id` ,
					`superfaktura_cid` ,
					`superfaktura_paid_type`
					)
					VALUES (
					NULL , '?', '?', ? , CURRENT_TIMESTAMP , NOW( ) , NULL , '?', '?', 'regular'
					);"
				);
				$result->execute(array(
					$invoice["id"],
					$invoice["evid_num"],
					$invoice["position"],
					$invoice["for_year"],
					$invoice["for_month"],
					$invoice["id_order"]
				));
				$this->getInvoiceDetail($invoice["id"]);
			} catch (Exception $e) {
				die("Insert invoice error: \n" . $e);
			}*/

			return 0;
		} else {
			//error descriptions
			echo "<pre>Data: <br>\n";
			var_dump($response->error_message);
			echo "</pre><hr>";
			return $response->error;
		}
		unset($response);
	}

}