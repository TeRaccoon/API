<?php

use App\sync\SyncInvoicedItems;
use PHPUnit\Framework\TestCase;

require_once 'tests/test_dbh.php';
require_once 'database_functions.php';
require_once 'database_utility.php';
require_once 'sync.php';

class sync extends TestCase {
    private $invoicedItemsSync;
    private $database;

    protected function setUp(): void
    {
        $this->database = new DatabaseConnection();
        $this->database->connect(true);
        $databaseUtility = new DatabaseUtility($this->database);
        $this->invoicedItemsSync = new SyncInvoicedItems($databaseUtility, new InvoiceDatabase($databaseUtility), new AllDatabases($databaseUtility), new CustomerDatabase(($databaseUtility)));
    }

    private function get_row_contents($query_string)
    {
        $query = $this->database->query($query_string);
        $contents = $query->fetch_all(MYSQLI_NUM);
        return $contents;
    }

    private function setup_invoice()
    {
        $query = "INSERT INTO invoices (title, customer_id, status, delivery_date, printed, type, delivery_type, payment_status, address_id, billing_address_id) VALUES ('INV167', '1', 'Pending', '2024-06-20', 'No', 'Retail', 'Delivery', 'No', '4', '4')";
        $this->database->query($query);
        $id = $this->get_row_contents("SELECT AUTO_INCREMENT FROM information_schema.tables WHERE table_name = 'invoices' AND table_schema = DATABASE()")[0][0] - 1;
        return $id;
    }

    private function setup_invoiced_item($invoice_id)
    {
        $query = "INSERT INTO invoiced_items (invoice_id, item_id, quantity) VALUES ('" . $invoice_id . "', '49', '5')";
        $this->database->query($query);
        $id = $this->get_row_contents("SELECT AUTO_INCREMENT FROM information_schema.tables WHERE table_name = 'invoiced_items' AND table_schema = DATABASE()")[0][0] - 1;
        return $id;
    }

    private function setup_stock($item_id)
    {
        $query = "INSERT INTO stocked_items(item_id,quantity,warehouse_id,expiry_date,last_purchase_date,barcode,packing_format) VALUES ('". $item_id . "','15','1','2024-06-20','2024-06-20','asd','Individual')";
        $this->database->query($query);
        $id = $this->get_row_contents("SELECT AUTO_INCREMENT FROM information_schema.tables WHERE table_name = 'invoiced_items' AND table_schema = DATABASE()")[0][0] - 1;
        return $id;
    }

    private function setup_item()
    {
        $
    }

    // private function should

    public function testShouldReturnErrorWhenNoStock()
    {
        $invoice_id = $this->setup_invoice();
        $invoiced_item_id = $this->setup_invoiced_item($invoice_id);
        $response = $this->invoicedItemsSync->sync_invoiced_items_insert($invoiced_item_id, 49, 5);
        
        $this->assertEquals($response['success'], false);
        $this->assertEquals($response['message'], 'There is insufficient stock for this operation!');
    }

    public function testShouldReturnSuccessWhenStock()
    {
        $invoice_id = $this->setup_invoice();
        $invoiced_item_id = $this->setup_invoiced_item($invoice_id);
        $response = $this->invoicedItemsSync->sync_invoiced_items_insert($invoiced_item_id, 49, 5);
        $this->setup_stock(49);

        $this->assertEquals($response['success'], false);
        $this->assertEquals($response['message'], 'There is insufficient stock for this operation!');
    }
}
