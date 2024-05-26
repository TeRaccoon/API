<?php

require_once 'cors_config.php';

require_once 'dbh.php';
require_once 'database_functions.php';
require_once 'database_utility.php';

class SyncInvoicedItems
{
    private $db_utility;
    private $invoice_database;
    private $all_database;
    private $customer_database;

    public function __construct($db_utility, $invoice_database, $all_database)
    {
        $this->db_utility = $db_utility;
        $this->invoice_database = $invoice_database;
        $this->all_database = $all_database;
        $this->customer_database = new CustomerDatabase($db_utility);
    }

    function sync_invoice_value($invoice_id)
    {
        if (!$this->invoice_database->update_invoice_value_from_invoiced_item_id($invoice_id)) {
            return array('success' => false, 'message' => 'There was an issue updating the invoice value!');
        }

        return true;
    }

    function check_stock_availability($item_id, $quantity)
    {
        $total_stock = $this->all_database->get_total_stock_by_id($item_id)['total_quantity'];
        if ($total_stock < $quantity) {
            return array('success' => false, 'message' => 'There is insufficient stock for this operation!');
        }

        return true;
    }

    function sync_invoiced_items_insert($id, $item_id, $quantity)
    {
        $invoice_id = $this->invoice_database->get_invoice_id_from_invoiced_item_id($id)[0];
        $response[] = $this->check_stock_availability($item_id, $quantity);
        if (!in_array(true, $response)) {
            return $response;
        }

        $stock_data = $this->all_database->get_stock_data_from_item_id($item_id);
        $stock_data = is_array($stock_data) ? $stock_data : [$stock_data];

        $response[] = $this->update_stock_and_keys_from_stock_data($stock_data, $quantity, $id);
        $response[] = $this->sync_invoice_value($invoice_id);

        $response[] = $this->customer_database->sync_outstanding_balance($id);

        return in_array(false, $response) ? array('success' => false, 'message' => 'There was an issue updating this stock! Please verify the integrity of the stock data!')
            :
            array('success' => true, 'message' => 'Invoiced item added successfully!');
    }

    function sync_invoiced_items_append($id, $item_id, $quantity)
    {
        $invoice_id = $this->invoice_database->get_invoice_id_from_invoiced_item_id($id)[0];
        $response[] = $this->check_stock_availability( $item_id, $quantity);
        if (!in_array(true, $response)) {
            return $response;
        }

        $response[] = $this->revert_stock_key($id);

        $stock_data = $this->all_database->get_stock_data_from_item_id($item_id);
        $stock_data = is_array($stock_data) ? $stock_data : [$stock_data];

        $response[] = $this->update_stock_and_keys_from_stock_data($stock_data, $quantity, $id);
        $response[] = $this->sync_invoice_value($invoice_id);

        $response[] = $this->customer_database->sync_outstanding_balance($id);

        return in_array(false, $response) ? array('success' => false, 'message' => 'There was an issue updating this stock! Please verify the integrity of the stock data!')
            :
            array('success' => true, 'message' => 'Invoiced item added successfully!');
    }

    function sync_invoiced_items_delete($id, $query_string)
    {
        $invoice_id = $this->invoice_database->get_invoice_id_from_invoiced_item_id($id)[0];
        $response[] = $this->revert_stock_key($id);
        $response[] = $this->db_utility->execute_query($query_string, null, false);
        $response[] = $this->sync_invoice_value($invoice_id);

        $response[] = $this->customer_database->sync_outstanding_balance($id);

        return in_array(false, $response) ? array('success' => false, 'message' => 'There was an issue updating this stock! Please verify the integrity of the stock data!')
            :
            array('success' => true, 'message' => 'Invoiced item added successfully!');
    }

    function update_stock_and_keys_from_stock_data($stock_data, $required_quantity, $id)
    {
        $response = [];
        foreach ($stock_data as $stock) {
            if ($required_quantity > 0) {
                $new_quantity = ($stock['quantity'] - $required_quantity) / $stock['modifier'];
                $altered_quantity = $stock['quantity'] - $new_quantity;
                $new_quantity = $new_quantity < 1 ? 0 : $new_quantity;
                $altered_quantity = $altered_quantity < $stock['quantity'] ? $altered_quantity : $stock['quantity'];

                $response[] = $this->all_database->update_stock_quantity_from_id($stock['id'], $new_quantity);
                $required_quantity -= $stock['quantity'];

                $response[] = $this->all_database->action_stock_control($stock['id'], $id, $altered_quantity);
            }
        }

        return $response;
    }

    function revert_stock_key($invoiced_item_id)
    {
        $stock_key_data = $this->all_database->get_stock_key_data_from_invoiced_item_id($invoiced_item_id);
        $stock_key_data = array_key_exists('id', $stock_key_data) ? [$stock_key_data] : $stock_key_data;

        foreach ($stock_key_data as $stock) {
            $response[] = $this->all_database->revert_stock_key_from_id($stock['stock_id'], $stock['quantity']);
        }
        $response[] = $this->all_database->delete_stock_key_from_stock_id($invoiced_item_id);

        return $response;
    }
}

class SyncCustomerPayments
{
    private $db_utility;
    private $invoice_database;
    private $all_database;
    private $customer_payments_database;
    private $customer_database;

    public function __construct($db_utility, $invoice_database, $all_database, $customer_payments_database, $customer_database)
    {
        $this->db_utility = $db_utility;
        $this->invoice_database = $invoice_database;
        $this->all_database = $all_database;
        $this->customer_payments_database = $customer_payments_database;
        $this->customer_database = $customer_database;
    }

    function sync_customer_payments_insert($id, $amount, $invoice_id, $status)
    {
        $customer_id = $this->invoice_database->get_customer_id($invoice_id);
        $invoice_outstanding_balance = $this->invoice_database->get_total($invoice_id)[0];
        
        $new_invoice_outstanding_balance = $invoice_outstanding_balance - $amount;
        $response[] = $this->invoice_database->update_outstanding_balance($new_invoice_outstanding_balance < 0 ? 0 : $new_invoice_outstanding_balance, $invoice_id);
        if ($new_invoice_outstanding_balance == 0) 
        {
            $response[] = $this->invoice_database->set_invoice_payment_status($invoice_id, 'Yes');
        }
        else if ($new_invoice_outstanding_balance < 0)
        {
            $excess = $amount - $invoice_outstanding_balance;
            $reference = "Credit from payment $id for invoice $invoice_id";
            $response[] = $this->customer_payments_database->create_excess_payment($excess, $reference, $invoice_id, $status);
        }

        $customer_outstanding_balance = $this->customer_database->get_outstanding_balance($customer_id)[0];
        $new_customer_outstanding_balance = $customer_outstanding_balance - $amount;
        $response[] = $this->customer_database->update_outstanding_balance($new_customer_outstanding_balance, $customer_id);
        return in_array(false, $response) ? array('success' => false, 'message' => 'There was an issue syncing this payment! Please verify the integrity of the payments!')
        :
        array('success' => true, 'message' => 'Customer payment added successfully!');
    }
}