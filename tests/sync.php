<?php

use App\sync\SyncInvoicedItems;

require_once 'tests/test_dbh.php';
require_once 'database_functions.php';
require_once 'database_utility.php';
require_once 'sync.php';
require_once 'manage_data.php';

class sync extends \PHPUnit\Framework\TestCase {
    private $invoiced_items_sync;

    public function TestFixture()
    {
        $database = new DatabaseConnection();
        $database->connect(false);
        $database_utility = new DatabaseUtility($database);
        $this->invoiced_items_sync = new SyncInvoicedItems($database_utility, new InvoiceDatabase($database_utility), new AllDatabases($database_utility));
    }
    

    public function testShouldRunFunction()
    {

        $this->invoiced_items_sync->sync_invoiced_items_insert(1, 1, 5);
        $this->assertEquals(25, 25);
    }
}