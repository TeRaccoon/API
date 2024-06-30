<?php

require_once 'cors_config.php';
    
if (isset($_GET["query"])) {
    run_query();
}

function run_query() {    
    require_once 'dbh.php';
    require_once 'database_utility.php';
    require_once 'database_functions.php';

    $query = $_GET["query"];

    $conn = new DatabaseConnection();
    $database_utility = new DatabaseUtility($conn);
    $all_databases = new AllDatabases($database_utility);
    $invoice_database = new InvoiceDatabase($database_utility);
    $customer_database = new CustomerDatabase($database_utility);
    $retail_items_database = new RetailItemsDatabase($database_utility);
    $image_locations_database = new ImageLocationsDatabase($database_utility);
    $page_sections_database = new PageSectionsDatabase($database_utility);
    $items_database = new ItemDatabase($database_utility);
    $ledger_database = new LedgerDatabase($database_utility);
    $statistics = new Statistics($database_utility);

    $conn->connect();
    $results = null;
    $filter = key_exists('filter', $_GET) ? urldecode($_GET['filter']) : null;
    switch ($query) {
        case 'table':
            $results = construct_table($all_databases);
            break;

        case 'tables':
            $results = $all_databases->get_tables();
            break;

        case 'total-invoices-month':
            $results = $invoice_database->get_total_invoices_month();
            break;

        case 'vat-returns':
            $results = $all_databases->get_vat_returns();
            break;

        case 'vat-history-by-group-id':
            $results = $all_databases->get_vat_history_by_group_id($filter);
            break;
        
        case 'vat-groups':
            $results = $all_databases->get_vat_groups();
            break;

        case 'delete-vat-returns-by-group-id':
            $results = $all_databases->delete_vat_history_by_group_id($filter);
            break;

        case 'invoice':
            $results = $invoice_database->get_invoice($filter);
            break;

        case 'total-invoices-per-month':
            $monthStart = urldecode($_GET['monthStart']);
            $monthEnd = urldecode($_GET['monthEnd']);
            $year = urldecode($_GET['year']);
            $results = $statistics->get_total_invoices($monthStart, $monthEnd, $year, 'month');
            break;
    
        case 'total-invoices-per-day':
            $dayStart = urldecode($_GET['dayStart']);
            $dayEnd = urldecode($_GET['dayEnd']);
            $month = urldecode($_GET['month']);
            $year = urldecode($_GET['year']);
            $results = $statistics->get_total_invoices($dayStart, $dayEnd, $year, 'day', $month);
            break;

        case 'total-invoice-value-per-month':
            $monthStart = urldecode($_GET['monthStart']);
            $monthEnd = urldecode($_GET['monthEnd']);
            $year = urldecode($_GET['year']);
            $results = $statistics->get_total_invoice_value($monthStart, $monthEnd, $year, 'month');
            break;

        case 'total-invoice-value-per-day':
            $dayStart = urldecode($_GET['dayStart']);
            $dayEnd = urldecode($_GET['dayEnd']);
            $month = urldecode($_GET['month']);
            $year = urldecode($_GET['year']);
            $results = $statistics->get_total_invoice_value($dayStart, $dayEnd, $year, 'day', $month);
            break;

        case 'vat-data':
            $startDate = urldecode($_GET['start-date']);
            $endDate = urldecode($_GET['end-date']);
            $results = $invoice_database->get_vat_data($startDate, $endDate);
            break;

        case 'average-invoice-value-per-month':
            $monthStart = urldecode($_GET['monthStart']);
            $monthEnd = urldecode($_GET['monthEnd']);
            $year = urldecode($_GET['year']);
            $results = $statistics->get_average_invoice_value($monthStart, $monthEnd, $year, 'month');
            break;

        case 'average-invoice-value-per-day':
            $dayStart = urldecode($_GET['dayStart']);
            $dayEnd = urldecode($_GET['dayEnd']);
            $month = urldecode($_GET['month']);
            $year = urldecode($_GET['year']);
            $results = $statistics->get_average_invoice_value($dayStart, $dayEnd, $year, 'day', $month);
            break;

        case 'top-selling-item-per-month':
            $monthStart = urldecode($_GET['monthStart']);
            $monthEnd = urldecode($_GET['monthEnd']);
            $year = urldecode($_GET['year']);
            $results = $statistics->get_top_selling_products($monthStart, $monthEnd, $year, 'month');
            break;

        case 'top-selling-item-per-day':
            $dayStart = urldecode($_GET['dayStart']);
            $dayEnd = urldecode($_GET['dayEnd']);
            $month = urldecode($_GET['month']);
            $year = urldecode($_GET['year']);
            $results = $statistics->get_top_selling_products($dayStart, $dayEnd, $year, 'day', $month);
            break;

        case 'recurring-customers-day':
            $dayStart = urldecode($_GET['dayStart']);
            $dayEnd = urldecode($_GET['dayEnd']);
            $month = urldecode($_GET['month']);
            $year = urldecode($_GET['year']);
            $results = $statistics->get_recurring_customers($dayStart, $dayEnd, $year, 'day', $month);
            break;

        case 'recurring-customers-month':
            $monthStart = urldecode($_GET['monthStart']);
            $monthEnd = urldecode($_GET['monthEnd']);
            $year = urldecode($_GET['year']);
            $results = $statistics->get_recurring_customers($monthStart, $monthEnd, $year, 'month');
            break;

        case 'non-recurring-customers-day':
            $dayStart = urldecode($_GET['dayStart']);
            $dayEnd = urldecode($_GET['dayEnd']);
            $month = urldecode($_GET['month']);
            $year = urldecode($_GET['year']);
            $results = $statistics->get_non_recurring_customers($dayStart, $dayEnd, $year, 'day', $month);
            break;

        case 'non-recurring-customers-month':
            $monthStart = urldecode($_GET['monthStart']);
            $monthEnd = urldecode($_GET['monthEnd']);
            $year = urldecode($_GET['year']);
            $results = $statistics->get_non_recurring_customers($monthStart, $monthEnd, $year, 'month');
            break;

        case 'invoices-due-today':
            $results = $invoice_database->get_invoices_due_today();
            break;

        case 'total-customers':
            $results = $customer_database->get_total_customers();
            break;

        case 'new-customers':
            $results = $customer_database->get_new_customers();
            break;

        case 'next-id':
            $results = $all_databases->get_next_id($filter);
            break;

        case 'next-supplier-account-code':
            $results = $all_databases->get_next_supplier_account_code($filter);
            break;

        case 'account-balances':
            $results = $ledger_database->get_account_balance($_GET['start-date'], $_GET['end-date']);
            break;

        case 'creditor':
            $results = $invoice_database->get_creditor_data($_GET['start-day'], $_GET['end-day']);
            break;

        case 'debtor':
            $results = $invoice_database->get_debtor_data($_GET['start-day'], $_GET['end-day']);
            break;

        case 'edit-form-data':
            $results = construct_edit_form($filter, $all_databases);
            break;

        case 'profit-loss':
            $results = $all_databases->get_profit_loss($_GET['start-date'], $_GET['end-date']);
            break;

        case 'total-stock':
            $results = $all_databases->get_total_stock();
            break;

        case 'invoices-due':
            $results = $invoice_database->get_invoices_due($filter);
            break;

        case 'invoice-month-totals':
            $results = $invoice_database->get_month_totals($filter);
            break;

        case 'total-invoices-month-profit':
            $results = $invoice_database->get_total_invoices_month_profit($_GET['month'], $_GET['year']);
            break;

        case 'invoiced-item-month-totals':
            $results = $invoice_database->get_item_month_totals($_GET['month'], $_GET['year']);
            break;

        case 'stocked-items':
            $results = $items_database->get_stock_from_item_id($filter);
            break;

        case 'total-stock-from-item-id':
            $results = $items_database->get_total_stock_from_item_id($filter);
            break;

        case 'stocked-item-images':
            $results = $items_database->get_images_from_stocked_items();
            break;

        case 'last-purchase-price':
            $results = $items_database->get_last_purchase_price($filter);
            break;

        case 'invoice-info':
            $results = $invoice_database->get_invoice_info($filter);
            break;

        case 'invoices-due-today-basic':
            $results = $invoice_database->get_invoices_due_today_basic();
            break;

        case 'delivery-info':
            $results = get_delivery_info($all_databases, $invoice_database, $customer_database, $filter);
            break;

        case 'invoice-products':
            $results = $invoice_database->get_invoice_products($filter);
            break;

        case 'items_id_name':
            $results = $items_database->get_id_names();
            break;

        case 'items_id_name_sku':
            $results = $items_database->get_id_names_sku();
            break;

        case 'offer_id_name':
            $results = $all_databases->get_offer_id_name();
            break;

        case 'customers_id_name':
            $results = $customer_database->get_id_names();
            break;

        case 'customers_id_name_code':
            $results = $customer_database->get_id_names_codes();
            break;

        case 'page_section_id_name':
            $results = $all_databases->get_page_section_id_names();
            break;

        case 'warehouse_id_name':
            $results = $all_databases->get_warehouse_id_names();
            break;

        case 'supplier_invoice_id_reference':
            $results = $all_databases->get_supplier_invoice_id_reference();
            break;

        case 'customer_address_id_full':
            $results = $all_databases->get_customer_address_id_address();
            break;

        case 'customer_billing_address_id_full':
            $results = $all_databases->get_customer_billing_address_id_address();
            break;

        case 'retail_item_id_name':
            $results = $all_databases->get_retail_item_id_names();
            break;

        case 'supplier_id_name_code':
            $results = $all_databases->get_supplier_id_name_code();
            break;

        case 'invoice_id_title':
            $results = $invoice_database->get_invoice_id_titles();
            break;

        case 'top-selling-item':
            $results = $items_database->get_top_selling_item();
            break;

        case 'least-purchased-item':
            $results = $items_database->get_least_purchased_item();
            break;

        case 'most-income-item':
            $results = $items_database->get_most_income_item();
            break;

        case 'least-income-item':
            $results = $items_database->get_least_income_item();
            break;

        case 'low-stock':
            $results = $items_database->get_low_stock_items();
            break;

        case 'products-expiring-soon':
            $results = $items_database->get_products_expiring_soon();
            break;

        case 'invoiced-items':
            if ($filter) 
            {
                $id = $filter;
            } else 
            {
                $id = urldecode($_GET['id']) ?? $filter;
                $complex = urldecode($_GET['complex']);
            }
            $results = $invoice_database->get_invoiced_items_from_id($id, $complex ?? false);
            break;

        case 'stocked-items-invoice':
            $id = isset($_GET['id']) ? urldecode($_GET['id']) : $filter;
            $results = $items_database->get_stock_from_invoice_id($id);
            break;

        case 'invoiced-items-basic-ids':
            $invoice_ids = urldecode($_GET['ids']);
            $results = $invoice_database->get_basic_invoiced_item_from_ids($invoice_ids);
            break;

        case 'images-from-item-id':
            $results = $all_databases->get_images_from_item_id($filter);
            break;

        case 'images-from-page-section-id':
            $results = $all_databases->get_images_from_page_section_id($filter);
            break;

        case 'image-count-from-item-id':
            $results = $all_databases->get_images_count_from_item_id($filter);
            break;

        case 'image-count-from-page-section-id':
            $results = $all_databases->get_image_count_from_page_section_id($filter);
            break;
        
        case 'append-or-add':
            $table = urldecode($_GET['table']);
            $id = urldecode($_GET['id']);
            $column = urldecode($_GET['column']);
            $results = $all_databases->append_or_add($table, $id, $column);
            break;

        case 'brands':
            $results = $retail_items_database->brands();
            break;

        case 'categories':
            $results = $items_database->categories();
            break;

        case 'sub-categories':
            $results = $items_database->sub_categories();
            break;

        case 'customer-addresses':
            $results = $customer_database->get_addresses_by_address_id($filter);
            break;

        case 'customer-addresses-by-id':
            $results = $customer_database->get_address_from_customer_id($filter);
            break;

        case 'calculate-distance':
            $customer_id = urldecode($_GET['customer_id']);
            $warehouse_id = urldecode($_GET['warehouse_id']);
            $results = get_warehouse_customer_coordinates($all_databases, $customer_id, $warehouse_id);
            break;

        case 'delete-image':
            $results = $all_databases->delete_image_by_file_name($filter);
            break;

        case 'set-to-printed':
            $invoice_database->set_invoice_to_printed($filter);
            break;
    }
    echo json_encode($results);
}

function get_delivery_info($all_databases, $invoice_database, $customer_database, $invoice_id) {
    $customer_id = $invoice_database->get_customer_id($invoice_id);
    $warehouse_id = $invoice_database->get_warehouse_id($invoice_id);
    $warehouse_name = $all_databases->get_warehouse_name_from_id($warehouse_id);

    $address_data = $invoice_database->get_addresses($invoice_id);
    $customer_delivery_info = $customer_database->get_customer_delivery_info($invoice_id);
    $account_name = $customer_database->get_customer($customer_id)['account_name'];

    $delivery_date = $invoice_database->get_delivery_date_from_id($invoice_id);

    $warehouse_postcode = $all_databases->get_postcode_from_warehouse_id($warehouse_id);
    $customer_coordinates = $all_databases->get_coordinates_from_postcode(str_replace(' ', '', $address_data['delivery_postcode']));
    $warehouse_coordinates = $all_databases->get_coordinates_from_postcode($warehouse_postcode);

    return array(
        'invoice_id' => $invoice_id,
        'account_name' => $account_name,
        'delivery_info' => $customer_delivery_info,
        'delivery_date' => $delivery_date,
        'customer_postcode' => $address_data['delivery_postcode'],
        'customer_coordinates' => $customer_coordinates,
        'warehouse_postcode' => $warehouse_postcode,
        'warehouse_name' => $warehouse_name,
        'warehouse_coordinates' => $warehouse_coordinates);
}

function get_warehouse_customer_coordinates($all_databases, $customer_id, $warehouse_id) {
    $customer_postcode = $all_databases->get_customer_postcode_from_id($customer_id);
    $warehouse_postcode = $all_databases->get_postcode_from_warehouse_id($warehouse_id);

    $customer_coordinates = $all_databases->get_coordinates_from_postcode($customer_postcode);
    $warehouse_coordinates = $all_databases->get_coordinates_from_postcode($warehouse_postcode);

    return array('customer_postcode' => $customer_postcode, 'customer_coordinates' => $customer_coordinates, 'warehouse_postcode' => $warehouse_postcode, 'warehouse_coordinates' => $warehouse_coordinates);
}

function construct_edit_form($table_name, $all_databases) {
    $editable_display_names = [];
    $editable_data_types = [];
    $editable_required = [];
    $editable_field_names = [];

    $table_columns = $all_databases->get_columns($table_name);
    foreach ($table_columns as $key => $column) {
        if ($column['Extra'] == null) {
            $editable_display_names[] = $column['Comment'];
            $editable_field_names[] = $column['Field'];
            if ($column['Field'] == 'image_file_name') {
                $editable_data_types[] = 'file';
            } else {
                $editable_data_types[] = $column['Type'];
            }
            if ($column['Null'] == 'NO') {
                $editable_required[] = true;
            } else {
                $editable_required[] = false;
            }
        }
    }

    $editable_columns = get_editable_columns($table_name, $all_databases);
    return [
        'columns' => $editable_columns,
        'fields' => $editable_field_names,
        'types' => $editable_data_types,
        'names' => $editable_display_names,
        'required' => $editable_required
    ];
}

function construct_table($all_databases) {
    $table_name = urldecode($_GET['filter']);
    $formatted_names = [];
    $editable_display_names = [];
    $editable_data_types = [];
    $editable_required = [];
    $editable_field_names = [];

    $table_data = $all_databases->get_table_data($table_name);
    $table_columns = $all_databases->get_columns($table_name);
    foreach ($table_columns as $key => $column) {
        $formatted_names[] = $column['Comment'];
        $data_types[] = $column['Field'] == 'image_file_name' ? 'file' : $column['Type'];
        if ($column['Extra'] == null) {
            $editable_display_names[] = $column['Comment'];
            $editable_field_names[] = $column['Field'];
            if ($column['Field'] == 'image_file_name') {
                $editable_data_types[] = 'file';
            } else {
                $editable_data_types[] = $column['Type'];
            }
            if ($column['Null'] == 'NO') {
                $editable_required[] = true;
            } else {
                $editable_required[] = false;
            }
        }
    }

    $display_data = get_display_data($table_data, $table_name, $all_databases);
    $editable_columns = get_editable_columns($table_name, $all_databases);

    return [
        'display_data' => $display_data,
        'display_names' => $formatted_names,
        'data' => $table_data,
        'types' => $data_types,
        'editable' => [
            'columns' => $editable_columns,
            'fields' => $editable_field_names,
            'types' => $editable_data_types,
            'names' => $editable_display_names,
            'required' => $editable_required
        ]
    ];
}

function get_editable_columns($table_name, $all_databases) {
    $editable_columns = [];
    $columns = $all_databases->get_columns($table_name);
    foreach($columns as $column) {
        if ($column['Extra'] == null) {
            $editable_columns[] = $column['Field'];
        }
    }
    return $editable_columns;
}

function get_display_data($data, $table_name, $all_databases) {
    $display_data = $data;
    $associative_data = $all_databases->get_associative_data($table_name);
    if ($associative_data != null) {
        if (!array_key_exists(0, $associative_data)) {
            $associative_data = [$associative_data];
        }
        foreach($associative_data as $assoc_data) {
            $assoc_table_data = null;
            $assoc_table = $assoc_data['REFERENCED_TABLE_NAME'];
            $assoc_column = $assoc_data['COLUMN_NAME'];
    
            switch ($assoc_table) {
                case 'customers':
                    $assoc_table_data = $all_databases->get_customers_name();
                    break;

                case 'customer_address':
                    if ($assoc_column == 'address_id')
                    {
                        $assoc_table_data = $all_databases->get_customer_address();
                    }
                    else
                    {
                        $assoc_table_data = $all_databases->get_customer_billing_address();
                    }
                    break;

                case 'invoices':
                    $assoc_table_data = $all_databases->get_invoice_titles();
                    break;

                case 'suppliers':
                    $assoc_table_data = $all_databases->get_suppliers_name();
                    break;
                
                case 'items':
                    $assoc_table_data = $all_databases->get_item_names();
                    break;

                case 'retail_items':
                    $assoc_table_data = $all_databases->get_retail_item_names();
                    break;
                
                case 'page_sections':
                    $assoc_table_data = $all_databases->get_page_section_names();
                    break;

                case 'warehouse':
                    $assoc_table_data = $all_databases->get_warehouse_names();
                    break;
            }
            if (!array_key_exists(0, $display_data)) {
                $display_data = [$display_data];
            }

            if ($assoc_table_data != null) {
                foreach($display_data as $key => $data_item) {
                    if (array_key_exists($assoc_column, $display_data[$key]) && array_key_exists($display_data[$key][$assoc_column], $assoc_table_data)) {
                        $display_data[$key][$assoc_column] = $assoc_table_data[$display_data[$key][$assoc_column]];
                    }
                }
            }
        }
    }

    return $display_data;
}