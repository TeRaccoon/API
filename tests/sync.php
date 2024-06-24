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

    private function setup_customer()
    {
        $query = "INSERT INTO customers(forename,surname,phone_number_primary,email,customer_type,discount,outstanding_balance,last_payment_date,password,account_status,currency_code,address_line_1,postcode,vat_type) VALUES ('Test','Test','07306823956','test@test.com','Retail','5','0','2024-06-22','','Credit Account','GBP','test','test','UK Standard')";
        $this->database->query($query);
        $id = $this->get_row_contents("SELECT AUTO_INCREMENT FROM information_schema.tables WHERE table_name = 'invoices' AND table_schema = DATABASE()")[0][0] - 1;
        return $id;
    }

    private function setup_customer_address()
    {
        $query = "INSERT INTO customer_address(customer_id,invoice_address_one,invoice_postcode,delivery_address_one,delivery_postcode) VALUES ('1','test','test','test','test')";
        $this->database->query($query);
        $id = $this->get_row_contents("SELECT AUTO_INCREMENT FROM information_schema.tables WHERE table_name = 'invoices' AND table_schema = DATABASE()")[0][0] - 1;
        return $id;
    }

    private function setup_invoice()
    {
        $query = "INSERT INTO invoices (title, customer_id, status, delivery_date, printed, type, delivery_type, payment_status, address_id, billing_address_id) VALUES ('INV167', '1', 'Pending', '2024-06-20', 'No', 'Retail', 'Delivery', 'No', '1', '1')";
        $this->database->query($query);
        $id = $this->get_row_contents("SELECT AUTO_INCREMENT FROM information_schema.tables WHERE table_name = 'invoices' AND table_schema = DATABASE()")[0][0] - 1;
        return $id;
    }

    private function setup_invoiced_item($invoice_id)
    {
        $query = "INSERT INTO invoiced_items (invoice_id, item_id, quantity) VALUES ('" . $invoice_id . "', '1', '5')";
        $this->database->query($query);
        $id = $this->get_row_contents("SELECT AUTO_INCREMENT FROM information_schema.tables WHERE table_name = 'invoiced_items' AND table_schema = DATABASE()")[0][0] - 1;
        return $id;
    }

    private function setup_stock($item_id)
    {
        $query = "INSERT INTO stocked_items(item_id,quantity,warehouse_id,expiry_date,purchase_date,purchase_price,barcode,packing_format) VALUES ('". $item_id . "','15','1','2024-06-20','2024-06-20','0','asd','Individual')";
        $this->database->query($query);
        $id = $this->get_row_contents("SELECT AUTO_INCREMENT FROM information_schema.tables WHERE table_name = 'invoiced_items' AND table_schema = DATABASE()")[0][0] - 1;
        return $id;
    }

    private function setup_item()
    {
        $query = "INSERT INTO items(item_name,retail_price,wholesale_price,unit_cost,stock_code,vat_rate,box_size,box_price,pallet_size,pallet_price,visible,featured,discount,visibility)VALUES('TestItem','1','0.8','0.2','test-item','UK Standard Rate','5','5','25','25','Yes','Yes','0','Retail')";
        $this->database->query($query);
        $id = $this->get_row_contents("SELECT AUTO_INCREMENT FROM information_schema.tables WHERE table_name = 'invoiced_items' AND table_schema = DATABASE()")[0][0] - 1;
        return $id;
    }

    private function reset_database()
    {
        $query = "TRUNCATE test.stock_keys";
        $this->database->query($query);
        $query = "TRUNCATE stocked_items";
        $this->database->query($query);
        $query = "TRUNCATE invoiced_items";
        $this->database->query($query);
        $query = "TRUNCATE customer_address";
        $this->database->query($query);
        $query = "TRUNCATE invoices";
        $this->database->query($query);
        $query = "TRUNCATE customers";
        $this->database->query($query);
        $query = "TRUNCATE items";
        $this->database->query($query);
    }

    // private function should

    public function testShouldReturnErrorWhenNoStock()
    {
        $this->reset_database();
        $this->setup_customer();
        $this->setup_customer_address();
        $this->setup_item();
        $invoice_id = $this->setup_invoice();
        $invoiced_item_id = $this->setup_invoiced_item(1);
        $response = $this->invoicedItemsSync->sync_invoiced_items_insert(1, 1, 5);
        
        $this->assertEquals($response['success'], false);
        $this->assertEquals($response['message'], 'There is insufficient stock for this operation!');
    }

    // public function testShouldReturnSuccessWhenStock()
    // {
    //     $invoice_id = $this->setup_invoice();
    //     $invoiced_item_id = $this->setup_invoiced_item($invoice_id);
    //     $response = $this->invoicedItemsSync->sync_invoiced_items_insert($invoiced_item_id, 49, 5);
    //     $this->setup_stock(49);

    //     $this->assertEquals($response['success'], false);
    //     $this->assertEquals($response['message'], 'There is insufficient stock for this operation!');
    // }
}
