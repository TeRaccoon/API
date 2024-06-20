<?php

namespace App\sync;

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

    function sync_invoice_outstanding_balance($invoice_id, $invoice_total)
    {
        $total_payments = $this->invoice_database->get_total_payments($invoice_id)[0] ?? 0;
        $outstanding_balance = $invoice_total - $total_payments;

        return $this->invoice_database->set_outstanding_balance($invoice_id, $outstanding_balance);
    }

    function check_stock_availability($item_id, $quantity, $invoiced_item_id = null)
    {
        $total_stock = $this->all_database->get_total_stock_by_id($item_id)['total_quantity'];
        $current_quantity = $invoiced_item_id == null ? 0 : $this->all_database->get_invoiced_item($invoiced_item_id)['quantity'];
        if ($total_stock + $current_quantity <= $quantity) {
            return array('success' => false, 'message' => 'There is insufficient stock for this operation!');
        }

        return true;
    }

    function sync_invoiced_items_insert($id, $item_id, $quantity)
    {
        $invoice_id = $this->invoice_database->get_invoice_id_from_invoiced_item_id($id)[0];
        $customer_id = $this->invoice_database->get_invoice($invoice_id)['customer_id'];

        $in_stock = $this->check_stock_availability($item_id, $quantity);
        if ($in_stock !== true) {
            return $in_stock;
        }

        $stock_data = $this->all_database->get_stock_data_from_item_id($item_id);
        $stock_data = key_exists(0, $stock_data) ? $stock_data : [$stock_data];

        $response[] = $this->update_stock_and_keys_from_stock_data($stock_data, $quantity, $id);

        $invoice_totals = $this->invoice_database->calculate_invoice_totals($invoice_id);
        $total = $invoice_totals['net'] * 1.2;

        $response[] = $this->invoice_database->update_invoice_value($invoice_id, $invoice_totals['net'], $invoice_totals['gross']);

        $response[] = $this->sync_invoice_outstanding_balance($invoice_id, $total);
        $response[] = $this->customer_database->sync_outstanding_balance($customer_id);

        return in_array(false, $response) ? array('success' => false, 'message' => 'There was an issue updating this stock! Please verify the integrity of the stock data!', 'data' => $response)
            :
            array('success' => true, 'message' => 'Invoiced item added successfully!');
    }

    function sync_invoiced_items_append($id, $item_id, $quantity)
    {
        echo $id;
        $invoice_id = $this->invoice_database->get_invoice_id_from_invoiced_item_id($id)[0];
        $customer_id = $this->invoice_database->get_invoice($invoice_id)['customer_id'];

        $in_stock = $this->check_stock_availability($item_id, $quantity, $id);
        if ($in_stock !== true) {
            return $in_stock;
        }

        $response[] = $this->revert_stock_key($id);

        $stock_data = $this->all_database->get_stock_data_from_item_id($item_id);
        $stock_data = key_exists(0, $stock_data) ? $stock_data : [$stock_data];

        $response[] = $this->update_stock_and_keys_from_stock_data($stock_data, $quantity, $id);
        
        $invoice_totals = $this->invoice_database->calculate_invoice_totals($invoice_id);
        $total = $invoice_totals['net'] * 1.2;

        $response[] = $this->invoice_database->update_invoice_value($invoice_id, $invoice_totals['net'], $invoice_totals['gross']);

        $response[] = $this->sync_invoice_outstanding_balance($invoice_id, $total);
        $response[] = $this->customer_database->sync_outstanding_balance($customer_id);

        return in_array(false, $response) ? array('success' => false, 'message' => 'There was an issue updating this stock! Please verify the integrity of the stock data!')
            :
            array('success' => true, 'message' => 'Invoiced item appended successfully!');
    }

    function sync_invoiced_items_delete($id, $query_string)
    {
        $invoice_id = $this->invoice_database->get_invoice_id_from_invoiced_item_id($id)[0];
        $customer_id = $this->invoice_database->get_invoice($invoice_id)['customer_id'];

        $response[] = $this->revert_stock_key($id);
        $response[] = $this->db_utility->execute_query($query_string, null, false);

        $invoice_totals = $this->invoice_database->calculate_invoice_totals($invoice_id);
        $total = $invoice_totals['net'] * 1.2;

        $response[] = $this->invoice_database->update_invoice_value($invoice_id, $invoice_totals['net'], $invoice_totals['gross']);

        $response[] = $this->sync_invoice_outstanding_balance($invoice_id, $total);
        $response[] = $this->customer_database->sync_outstanding_balance($customer_id);

        return in_array(false, $response) ? array('success' => false, 'message' => 'There was an issue updating this stock! Please verify the integrity of the stock data!')
            :
            array('success' => true, 'message' => 'Invoiced item dropped successfully!');
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

        foreach ($stock_key_data as $stock)
        {
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
        if ($amount >= $invoice_outstanding_balance) 
        {
            $response[] = $this->invoice_database->set_invoice_payment_status($invoice_id, 'Yes');
        }

        if ($new_invoice_outstanding_balance < 0)
        {
            $excess = $amount - $invoice_outstanding_balance;
            $reference = "Credit from payment $id for invoice $invoice_id";
            $response[] = $this->customer_payments_database->create_excess_payment($excess, $reference, $invoice_id, $status, $id);
        }

        $customer_outstanding_balance = $this->customer_database->get_outstanding_balance($customer_id);
        $new_customer_outstanding_balance = $customer_outstanding_balance - $amount;
        $response[] = $this->customer_database->update_outstanding_balance($new_customer_outstanding_balance, $customer_id);
        return in_array(false, $response) ? array('success' => false, 'message' => 'There was an issue syncing this payment! Please verify the integrity of the payments!')
        :
        array('success' => true, 'message' => 'Customer payment added successfully!');
    }

    function sync_customer_payments_append($id, $amount, $invoice_id, $status)
    {
        $customer_id = $this->invoice_database->get_customer_id($invoice_id);
        $invoice_outstanding_balance = $this->invoice_database->get_invoice($invoice_id)['outstanding_balance'];
        $customer_outstanding_balance = $this->customer_database->get_outstanding_balance($customer_id);

        $old_payment = $this->customer_payments_database->get_payment_data($id)['amount'];
        $excess = $this->customer_payments_database->get_excess_payment($id);
        $excess = array_key_exists('amount', $excess) ? $excess['amount'] : 0;

        $invoice_outstanding_balance = $invoice_outstanding_balance + $old_payment - $excess;
        $customer_outstanding_balance = $customer_outstanding_balance + $old_payment - $excess;

        $response[] = $this->invoice_database->update_outstanding_balance($invoice_outstanding_balance, $invoice_id);
        $response[] = $this->customer_database->update_outstanding_balance($customer_outstanding_balance, $customer_id);

        $response[] = $this->customer_payments_database->remove_linked_payment($id);

        if (in_array(false, $response)) {
            return array('success' => false, 'message' => 'Failed to revert payment. Please verify the integrity of payments!');
        }


        $new_invoice_outstanding_balance = $invoice_outstanding_balance - $amount;

        $response[] = $this->invoice_database->update_outstanding_balance($new_invoice_outstanding_balance < 0 ? 0 : $new_invoice_outstanding_balance, $invoice_id);
        if ($amount >= $invoice_outstanding_balance) 
        {
            $response[] = $this->invoice_database->set_invoice_payment_status($invoice_id, 'Yes');
        } 
        else 
        {
            $response[] = $this->invoice_database->set_invoice_payment_status($invoice_id, 'No');
        }

        if ($new_invoice_outstanding_balance < 0)
        {
            $excess = $amount - $invoice_outstanding_balance;
            $reference = "Credit from payment $id for invoice $invoice_id";
            $response[] = $this->customer_payments_database->create_excess_payment($excess, $reference, $invoice_id, $status, $id);
        }

        $new_customer_outstanding_balance = $customer_outstanding_balance - $amount;
        $response[] = $this->customer_database->update_outstanding_balance($new_customer_outstanding_balance, $customer_id);

        return in_array(false, $response) ? array('success' => false, 'message' => 'There was an issue syncing this payment! Please verify the integrity of the payments!')
        :
        array('success' => true, 'message' => 'Customer payment appended successfully!');
    }

    function sync_customer_payments_drop($id, $query_string)
    {
        $invoice_id = $this->customer_payments_database->get_payment_data($id)['invoice_id'];
        $customer_id = $this->invoice_database->get_customer_id($invoice_id);

        $response[] = $this->sync_customer_outstanding_balance($id, $customer_id);
        if (in_array(false, $this->sync_invoice_outstanding_balance($id, $invoice_id)))
        {
            return array('success' => false, 'message' => 'Failed to revert payment. Please verify the integrity of payments!');
        }
        
        $response[] = $this->customer_payments_database->remove_linked_payment($id);
        $response[] = $this->db_utility->execute_query($query_string, null, false);

        return in_array(false, $response) ? array('success' => false, 'message' => 'There was an issue syncing this payment! Please verify the integrity of the payments!')
        :
        array('success' => true, 'message' => 'Customer payment appended successfully!');
    }

    function sync_invoice_outstanding_balance($id, $invoice_id)
    {
        $old_payment = $this->customer_payments_database->get_payment_data($id)['amount'];
        $excess = $this->customer_payments_database->get_excess_payment($id);
        $excess = array_key_exists('amount', $excess) ? $excess['amount'] : 0;

        $invoice_outstanding_balance = $this->invoice_database->get_invoice($invoice_id)['outstanding_balance'];

        $original_outstanding_balance = $invoice_outstanding_balance + $old_payment - $excess;

        $response[] = $this->invoice_database->set_invoice_payment_status($invoice_id, 'No');
        $response[] = $this->invoice_database->update_outstanding_balance($original_outstanding_balance, $invoice_id);

        return $response;
    }

    function sync_customer_outstanding_balance($id, $customer_id)
    {
        $old_payment = $this->customer_payments_database->get_payment_data($id)['amount'];
        
        $outstanding_balance = $this->customer_database->get_outstanding_balance($customer_id);
        $new_outstanding_balance = $outstanding_balance + $old_payment;
        
        return $this->customer_database->update_outstanding_balance($new_outstanding_balance, $customer_id);
    }
}
