<?php

require_once 'cors_config.php';

require_once 'dbh.php';
require_once 'database_functions.php';
require_once 'database_utility.php';

function sync_invoiced_items_insert($database_utility, $action, $id, $item_id, $quantity) {
    $invoice_database = new InvoiceDatabase($database_utility);
    $all_database = new AllDatabases($database_utility);

    $response[] = $invoice_database->update_invoice_value_from_invoiced_item_id($id);

    $total_stock = $all_database->get_total_stock_by_id($item_id);
    if ($total_stock < $quantity) {
        return array('success' => false, 'message' => 'There is insufficient stock for this operation!');
    }

    $stock_data = $all_database->get_stock_data_from_item_id($item_id);
    $required_quantity = $quantity;
    foreach($stock_data as $stock) {
        if ($required_quantity > 0) {
            $new_quantity = ($stock['quantity'] - $required_quantity) / $stock['modifier'];
            $all_database->update_stock_quantity_from_id($stock['id'], $new_quantity);            
            $required_quantity -= $stock['quantity'];
        }
    }

    $response[] = $invoice_database->update_stock_from_invoiced_item_id($id);
}