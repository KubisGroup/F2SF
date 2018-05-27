<?php
/**
 * Kubis Group F2FS API connector
 * 
 * Connect your Fler.cz eshop with superfaktura.cz. 
 * Its simple, you need only API keys not more because it is fully automated
 */


/**
 * require Fler.cz api driver
 */
require_once(__DIR__.'/flerAPI/Fler_API_REST_Driver.php');

/**
 * composer autoloader
 * 
 * Auto load composer superfaktura
 */
require_once(__DIR__.'/vendor/autoload.php');

class flerConnector_I {
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
					`flerOrder_id` INT NOT NULL COMMENT 'Fler Order ID',
					`flerOrder_cid` INT NOT NULL COMMENT 'Fler Client (BUYER) ID',
					`flerOrder_created` DATETIME COMMENT 'Fler Order Created',
					`sync_date` TIMESTAMP COMMENT 'Last synchronization timestamp',
					`superfaktura_create_date` DATETIME COMMENT 'Created timestamp',
					`superfaktura_update_date` DATETIME COMMENT 'Update timestamp',
					`superfaktura_invoice_id` INT NOT NULL COMMENT 'Invoice ID', 
					`superfaktura_cid` INT NOT NULL COMMENT 'Client (BUYER) ID',
					`superfaktura_paid_type` ENUM( 'regular', 'proforma', 'estimate', 'cancel', 'order', 'delivery' ) CHARACTER SET utf8 COLLATE utf8_general_ci COMMENT 'Invoice type',
					PRIMARY KEY (`id`)
				)  CHARACTER SET utf8 COLLATE utf8_general_ci
			"
		),
		array (
			"name" => "flerOrders",
			"create" => "CREATE TABLE IF NOT EXISTS `[prefix]flerOrders`
				(
					`id` INT AUTO_INCREMENT NOT NULL,
					`flerOrder_id` INT NOT NULL COMMENT 'Fler order ID',
					`flerBuyer_id` INT NOT NULL COMMENT 'Fler Client (BUYER) ID',
					`flerOrder_created` DATETIME COMMENT 'Fler Order Created',
					`sync_date` TIMESTAMP COMMENT 'Last synchronization timestamp',
					`flerOrder_state` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci  COMMENT 'Fler order state', 
					PRIMARY KEY (`id`)
				)  CHARACTER SET utf8 COLLATE utf8_general_ci COMMENT 'Fler order LIST with status VYRIZENA and UHRAZENA'
			"
		)
	);
	
	/**
	 * Paid statuses 
	 */
	private $FpaidStatuses = Array (
		"UHRAZENA" => "proforma", // Bežná
		"VYRIZENA" => "proforma", // Zálohová faktura
		"ODMITNUTA" => "proforma", // Dobropis
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
	public function __construct($publicKey, $secretKey) {
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

		if( ($response->http_code == 200 && empty($response->data["error"]) ) || $response->http_code == 301) {
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
			die("Api Invoice error (".(empty($response->data["error_number"]) ? "No error code" : $response->data["error_number"]).") with  http code (".$response->http_code."): \"".(empty($response->data["error"]) ? "No error message" : $response->data["error"])."\"\n");
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
					'issued_by' => COMPANY_issued_by,
					'issued_by_phone' => COMPANY_issued_by_phone,
					'issued_by_email' => COMPANY_issued_by_email,
					'issued_by_web' => COMPANY_issued_by_web,
					'order_no' => $invoiceInfo["id_order"],
					'invoice_no_formatted' => $invoiceInfo["evid_num"],
					'created' => $invoiceInfo["date_created"],
					'delivery' => $invoiceInfo["date_issue"],
					'due' => $invoiceInfo["date_due"],
					'payment_type' => $invoicePayment["method"],
					'variable' => $invoicePayment["variable_symbol"], //variable symbol / reference
					'constant' => $invoicePayment["constant_symbol"], //constant symbol
					'specific' => $invoicePayment["specific_symbol"], //specific symbol
					'already_paid' => ($orderInfo["state"] == "UHRAZENA" || $orderInfo["state"] == "VYRIZENA" ? true : false), //has the invoices been already paid?
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
					"flerOrder_id" => $invoiceInfo["id_order"], //Fler Invoice ID
					"flerOrder_cid" => $orderBuyerID, //Fler Client (BUYER) ID
					"flerOrder_created" => $orderCreated, //Fler Order Created
					"superfaktura_paid_type" => $orderState, //Invoice type
				);
				var_dump($infoDB);
				//save data to superfaktura but only paid orders
				if($orderStateNormal == "UHRAZENA" || $orderStateNormal == "VYRIZENA") {
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

		// response object contains data about created invoices, or error messages respectively
		if($response->error == 0 || $response->error_message->invoice_no_formatted !== "Doklad nebyl vytvořený, protože se stejným číslem už existuje jiný doklad") {
			//complete information about created invoice
			// save syncroniation data
			echo "inserting <strong>invoice</strong> order: " .$infoDB["flerOrder_id"]."<br>";
			try {
				$result = $this->database->prepare("INSERT INTO `".$this->databasePrefix."superfakturaSync` (
						`id` ,
						`flerOrder_id`,
						`flerOrder_cid`,
						`flerOrder_created`,
						`sync_date`,
						`superfaktura_create_date` ,
						`superfaktura_update_date` ,
						`superfaktura_invoice_id` ,
						`superfaktura_cid` ,
						`superfaktura_paid_type`
					)VALUES (
						NULL , 
						'".$infoDB["flerOrder_id"]."', 
						'".$infoDB["flerOrder_cid"]."', 
						'".$infoDB["flerOrder_created"]."' , 
						CURRENT_TIMESTAMP , 
						NOW( ) , 
						NULL , 
						'".$response->data->Invoice->id."', 
						'".$response->data->Invoice->client_id."', 
						'regular'
					);"
				);
				$result->execute();
			} catch (Exception $e) {
				die("Insert Sync invoice error: \n" . $e);
			}

			return 0;
		} else {
			//error descriptions
			echo "<pre>\n";
			var_dump($response->error_message);
			echo "</pre><hr>";
			return $response->error;
		}
		unset($response, $infoDB, $clientData, $setInvoice, $addInvoiceItem, $invoiceDetail, $superfakturaAPI);
	}

}
