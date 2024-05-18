<?php

require_once 'cors_config.php';

require_once 'dbh.php';
require_once 'database_functions.php';
require_once 'database_utility.php';

function sync_invoice_value($id, $invoice_database)
{
    if (!$invoice_database->update_invoice_value_from_invoiced_item_id($id)) {
        return array('success' => false, 'message' => 'There was an issue updating the invoice value!');
    }

    return true;
}

function check_stock_availability($all_database, $item_id, $quantity)
{
    $total_stock = $all_database->get_total_stock_by_id($item_id)['total_quantity'];
    if ($total_stock < $quantity) {
        return array('success' => false, 'message' => 'There is insufficient stock for this operation!');
    }

    return true;
}

function sync_invoiced_items_insert($database_utility, $id, $item_id, $quantity)
{
    $invoice_database = new InvoiceDatabase($database_utility);
    $all_database = new AllDatabases($database_utility);

    $response[] = check_stock_availability($all_database, $item_id, $quantity);
    if (!in_array(true, $response)) {
        return $response;
    }

    $stock_data = $all_database->get_stock_data_from_item_id($item_id);
    $stock_data = is_array($stock_data) ? $stock_data : [$stock_data];

    $response[] = update_stock_and_keys_from_stock_data($all_database, $stock_data, $quantity, $id);
    $response[] = sync_invoice_value($id, $invoice_database);

    return in_array(false, $response) ? array('success' => false, 'message' => 'There was an issue updating this stock! Please verify the integrity of the stock data!')
        :
        array('success' => true, 'message' => 'Invoiced item added successfully!');
}

function sync_invoiced_items_append($database_utility, $id, $item_id, $quantity)
{
    $invoice_database = new InvoiceDatabase($database_utility);
    $all_database = new AllDatabases($database_utility);

    $response[] = check_stock_availability($all_database, $item_id, $quantity);
    if (!in_array(true, $response)) {
        return $response;
    }

    $response[] = revert_stock_key($all_database, $id);

    $stock_data = $all_database->get_stock_data_from_item_id($item_id);
    $stock_data = is_array($stock_data) ? $stock_data : [$stock_data];

    $response[] = update_stock_and_keys_from_stock_data($all_database, $stock_data, $quantity, $id);
    $response[] = sync_invoice_value($id, $invoice_database);

    return in_array(false, $response) ? array('success' => false, 'message' => 'There was an issue updating this stock! Please verify the integrity of the stock data!')
        :
        array('success' => true, 'message' => 'Invoiced item added successfully!');
}

function sync_invoiced_items_delete($database_utility, $id)
{
    $invoice_database = new InvoiceDatabase($database_utility);
    $all_database = new AllDatabases($database_utility);

    $response[] = revert_stock_key($all_database, $id);
    $response[] = sync_invoice_value($id, $invoice_database);

    return in_array(false, $response) ? array('success' => false, 'message' => 'There was an issue updating this stock! Please verify the integrity of the stock data!')
        :
        array('success' => true, 'message' => 'Invoiced item added successfully!');
}

function update_stock_and_keys_from_stock_data($all_database, $stock_data, $required_quantity, $id)
{
    $response = [];
    foreach ($stock_data as $stock) {
        if ($required_quantity > 0) {
            $new_quantity = ($stock['quantity'] - $required_quantity) / $stock['modifier'];
            $altered_quantity = $stock['quantity'] - $new_quantity;
            $new_quantity = $new_quantity < 1 ? 0 : $new_quantity;
            $altered_quantity = $altered_quantity < $stock['quantity'] ? $altered_quantity : $stock['quantity'];

            $response[] = $all_database->update_stock_quantity_from_id($stock['id'], $new_quantity);
            $required_quantity -= $stock['quantity'];

            $response[] = $all_database->action_stock_control($stock['id'], $id, $altered_quantity);
        }
    }

    return $response;
}

function revert_stock_key($all_database, $invoiced_item_id)
{
    $stock_key_data = $all_database->get_stock_key_data_from_invoiced_item_id($invoiced_item_id);
    $stock_key_data = array_key_exists('id', $stock_key_data) ? [$stock_key_data] : $stock_key_data;
    
    foreach ($stock_key_data as $stock)
    {
        $response[] = $all_database->revert_stock_key_from_id($stock['stock_id'], $stock['quantity']);
    }
    $response[] = $all_database->delete_stock_key_from_stock_id($invoiced_item_id);

    return $response;
}
