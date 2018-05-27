<?php
/**
 * Kubis Group F2FS API connector
 * 
 * Connect your Fler.cz eshop with superfaktura.cz. 
 * Its simple, you need only API keys not more because it is fully automated
 */

// Fler.cz api driver
require_once(__DIR__.'/flerAPI/Fler_API_REST_Driver.php');

// SuperFaktura.cz api driver
//require_once(__DIR__.'/vendor/superfaktura/apiclient/SFAPIclient/SFAPIclient.php');

//composer autoloader
require_once(__DIR__.'/vendor/autoload.php');

class flerConnector_O {
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

	private $needStatuses = Array (
		"UHRAZENA", // Bežná
		"VYRIZENA",
		"ODMITNUTA"
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
		// list of orders
		// /api/rest/seller/orders/list
		foreach($this->needStatuses as $status) {
			for($month = 1; $month <= 12; $month++) {
				unset($response);
				$date = date("Y")."-".str_pad($month, 2, "0", STR_PAD_LEFT);
				$response = $this->flerAPI->request('GET', '/api/rest/seller/orders/list', array('month'=>$date, 'state'=>$status));

				if( ($response->http_code == 200 && empty($response->data["error"]) ) || $response->http_code == 301) {
					//read all invoices
					$ordersJsonDecoded = json_decode($response->plain, true);
					for($inv = 0; $inv < count($ordersJsonDecoded); $inv++) {
						$order = $ordersJsonDecoded[$inv];

						//if is order with status UHRAZENA or VYRIZENA
						if($order["state"] == "UHRAZENA" || $order["state"] == "VYRIZENA" || $order["state"] == "ZAMITNUTA")
						{
							//check data if is in database
							$resultTest = $this->database->prepare("SELECT 1 FROM `".$this->databasePrefix."flerOrders` WHERE `flerOrder_id` = ?;");
							$resultTest->execute(
								array (
									$order["id"]
								)
							);
							$data = $resultTest->fetch();
							//if isn't in database that we add new row
							if (!$data) {
								try {
									$result = $this->database->prepare("INSERT INTO `".$this->databasePrefix."flerOrders` (`id`, `flerOrder_id`, `flerBuyer_id`, `flerOrder_created`, `sync_date`, `flerOrder_state`) VALUES 
									(NULL, ?, ?, ?, NOW(), ?);");
									$result->execute(array(
										$order["id"],
										$order["buyer_uid"],
										$order["date_created"],
										$order["state"]
									));
									$this->getOrderDetail($order["id"]);

								} catch (Exception $e) {
									die("Insert order error: \n" . $e);
								}
							}
						}
					}
				}
				else {
					die("Api Order error (".(empty($response->data["error_number"]) ? "No error code" : $response->data["error_number"]).") with  http code (".$response->http_code."): \"".(empty($response->data["error"]) ? "No error message" : $response->data["error"])."\"\n");
				}
			}
		}
	}

	public function getOrderDetail2($orderID) {
		//check data if is in database
							$resultTest = $this->database->prepare("SELECT 1 FROM `".$this->databasePrefix."flerOrders` WHERE `flerOrder_id` = ?;");
							$resultTest->execute(
								array (
									$orderID
								)
							);
							$data = $resultTest->fetch();
							//if isn't in database that we add new row
							if (!$data) {
								try {
									$result = $this->database->prepare("INSERT INTO `".$this->databasePrefix."flerOrders` (`id`, `flerOrder_id`, `flerBuyer_id`, `flerOrder_created`, `sync_date`, `flerOrder_state`) VALUES 
									(NULL, ?, ?, ?, NOW(), ?);");
									$result->execute(array(
										$orderID,
										1,
										date("Y-m-d H:s:i"),
										1
									));
									echo "Run getOrderDetail\n";
									$this->getOrderDetail($orderID);

								} catch (Exception $e) {
									die("Insert order error: \n" . $e);
								}
							} else {
								echo "Order ID: " . $orderID . " is in database\n";
							}
	}


	private function getOrderDetail($orderID) {
		// detail of order by order ID
		// /api/rest/seller/orders/detail/<INVOICE_ID>
		unset($infoDB, $clientData, $setorder, $addorderItem, $orderDetail);
		echo "Get order " . $orderID . " detail information\n";
		$orderDetail = $this->flerAPI->request('GET', '/api/rest/seller/orders/detail/'.$orderID);
		
		if($orderDetail->http_code == 200 && empty($orderDetail->data["error"]) || $orderDetail->http_code == 301 ) {
				$orderInfo = $orderDetail->data["order"]; //info about order
				//$orderPaymentSummary = $orderDetail->data["payment_summary"];
				//$orderProvider = $orderDetail->data["provider"];
				$orderRecipient = $orderDetail->data["address_billing"]; //client
				$orderItems = $orderDetail->data["items"];
				//$orderVatSummary = $orderDetail->data["vat_summary"];
				$orderPayment = $orderDetail->data["info"];
				//$orderText = $orderDetail->data["texts"];

				//order informations
				$orderState = ( $orderInfo["state"] == "UHRAZENA" || $orderInfo["state"] == "VYRIZENA"  || $orderInfo["state"] == "ODMITNUTA" ? "proforma" : "TATO objednavka (order id: ".$orderInfo["id"].") NEBUDE POSLANA DO SUPERFAKTURY" ) ;
				$orderStateNormal = $orderInfo["state"];
				$orderCreated = $orderInfo["date_created"];
				$orderBuyerID = $orderInfo["buyer_uid"];
				/* superfaktura add order */
				//Client information
				$clientData = array(
						'name'    => (!empty($orderRecipient["company"]) ? $orderRecipient["company"]." | " : "") . $orderRecipient["name"],
						'company'    => (!empty($orderRecipient["company"]) ? $orderRecipient["company"] : ""),
						'ico'     => (!empty($orderRecipient["business_id"]) ? $orderRecipient["business_id"] : ""),
						'dic'     => (!empty($orderRecipient["vat_id"]) ? $orderRecipient["vat_id"] : ""),
						'email'   => (!empty($orderRecipient["email"]) ? $orderRecipient["email"] : ""),
						'address' => (!empty($orderRecipient["address"]) ? $orderRecipient["address"] : ""),
						'city'    => (!empty($orderRecipient["city"]) ? $orderRecipient["city"] : ""),
						'country' => (!empty($orderRecipient["country_name"]) ? $orderRecipient["country_name"] : ""),
						'zip'     => (!empty($orderRecipient["zip"]) ? $orderRecipient["zip"] : ""),
						'phone'   => (!empty($orderRecipient["phone"]) ? str_replace(" ", "", $orderRecipient["phone"]) : ""),
						'currency' => (!empty($orderInfo["currency"]) ? $orderInfo["currency"] : "czk") 
				);
				//bank information
				/*$bkAccount = Array();
				foreach($orderPayment["bank_accounts"] as $bankAccount) {
					$bkAccount[] = array(
						'bank_name' => $bankAccount["bank_name"],
						'account' => $bankAccount["account_number"],
						'bank_code' => $bankAccount["bank_code"],
	repair bugs					'iban' => $bankAccount["iban"],
						'swift' => $bankAccount["swift"],
					);
				}*/
				//Invoice information
				$setInvoice = Array (
					//all items are optional, if not used, they will be filled automatically
					'name' => 'Fler.cz objednávka č.'.$orderInfo["id"],
					'issued_by' => COMPANY_issued_by,
					'issued_by_phone' => COMPANY_issued_by_phone,
					'issued_by_email' => COMPANY_issued_by_email,
					'issued_by_web' => COMPANY_issued_by_web,
					'order_no' => $orderInfo["id"],
					//'invoice_no_formatted' => $invoiceInfo["evid_num"],
					'created' => $orderInfo["date_created"],
					//'delivery' => $invoiceInfo["date_issue"],
					//'due' => $invoiceInfo["date_due"],
					'payment_type' => $orderPayment["payment_method_label"],
					'variable' => $orderInfo["id"], //variable symbol / reference
					//'constant' => $invoicePayment["constant_symbol"], //constant symbol
					//'specific' => $invoicePayment["specific_symbol"], //specific symbol
					'already_paid' => false, //has the invoices been already paid?
					'comment' => 'Fler.cz objednávka č.'.$orderInfo["id"].'\n',
					'invoice_currency' => $orderInfo["currency"],
					 'type' => 'proforma',
					/*'bank_accounts' => array(
						$bkAccount
					)*/
				);
				//add invoice item, this can be called multiple times
				//if you are not a VAT registered, use tax = 0
				$addInvoiceItem;
				foreach($orderItems as $items) {
					$addInvoiceItem[] = array( 
						'name' => $items["product_name"], 
						//'description' => $items["description"], 
						'quantity' => 1,//$items["unit_number"], //množství 
						'unit' => "ks",//$items["unit_name"], //jednotka 
						'unit_price' => $items["price"], //cena bez DPH, resp. celková cena, pokud nejste platci DPH 
						'tax' => 0, //sazba DPH, pokud nejste plátcem DPH, zadajte 0 
						'stock_item_id' => $items["id_item"], //id skladové položky 
						//'sku' => '', //skladové označení 
						//'discount' => 0, //Sleva na položku v %
						//'discount_description' => '',
						'load_data_from_stock' => false //Načíst nevyplněné údaje položky ze skladu 
					);
				}
				//database informations
				$infoDB = Array (
					"flerOrder_id" => $orderInfo["id"], //Fler Invoice ID
					"flerOrder_cid" => $orderBuyerID, //Fler Client (BUYER) ID
					"flerOrder_order_created" => $orderCreated, //Fler Order Created
					"superfaktura_paid_type" => $orderState, //Invoice type
				);
				//save data to superfaktura but only paid orders

				if($orderStateNormal == "UHRAZENA" || $orderStateNormal == "VYRIZENA" || $orderStateNormal == "ODMITNUTA") {
					echo "Run superFakturaAdd\n";
					$this->superFacturaAdd($infoDB, $clientData, $setInvoice, $addInvoiceItem);
					unset($infoDB, $clientData, $setInvoice, $addInvoiceItem, $orderDetail);
				}
		}
		else {
			echo "<pre><h1>error order detail</h1> \n";
			var_dump($orderDetail);
			echo "</pre><hr>";
			die();
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
			echo "inserting order: " .$infoDB["flerOrder_id"]."<br>";
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
						'".$infoDB["flerOrder_order_created"]."', 
						CURRENT_TIMESTAMP, 
						NOW(), 
						NULL, 
						'".$response->data->Invoice->id."', 
						'".$response->data->Invoice->client_id."', 
						'regular'
					);"
				);
				$result->execute();
				echo "Add to Super Faktura has been done with order ID: ". $infoDB["flerOrder_id"]."\n";
			} catch (Exception $e) {
				die("Insert Sync superfaktura order error: \n" . $e);
			}

			return 0;
		} else {
			//error descriptions
			echo "<pre> <h1>Error Invoice SF</h1>\n";
			var_dump($response->error_message);
			echo "</pre><hr>";
			return $response->error;
		}
		unset($response, $infoDB, $clientData, $setInvoice, $addInvoiceItem, $invoiceDetail, $superfakturaAPI);
	}
}
