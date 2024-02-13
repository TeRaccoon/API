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
    $retail_user_database = new RetailUserDatabase($database_utility);
    $items_database = new ItemDatabase($database_utility);
    $ledger_database = new LedgerDatabase($database_utility);

    $conn->connect();
    $results = null;
    switch ($query) {
        case "table":
            $results = construct_table($all_databases);
            break;

        case "tables":
            $results = $all_databases->get_tables();
            break;

        case "total-invoices-month":
            $results = $invoice_database->get_total_invoices_month();
            break;

        case "invoices-due-today":
            $results = $invoice_database->get_invoices_due_today();
            break;

        case "invoices-due-today-ids":
            $results = $invoice_database->get_invoices_due_today_ids();
            break;

        case "total-customers":
            $results = $customer_database->get_total_customers();
            break;

        case "new-customers":
            $results = $customer_database->get_new_customers();
            break;

        case "account-balances":
            $results = $ledger_database->get_account_balance($_GET['start-date'], $_GET['end-date']);
            break;

        case "creditor":
            $results = $invoice_database->get_creditor_data($_GET['start-day'], $_GET['end-day']);
            break;

        case "debtor":
            if ($_GET['end-day'] != "null") {
                $results = $invoice_database->get_debtor_data($_GET['start-day'], $_GET['end-day']);
            } else {
                $results = $invoice_database->get_debtor_data_limitless($_GET['start-day']);
            }
            break;

        case "profit-loss":
            $results = $all_databases->get_profit_loss($_GET['start-date'], $_GET['end-date']);
            break;

        case "invoices-due":
            $age = urldecode($_GET['filter']);
            $results = $invoice_database->get_invoices_due($age);
            break;

        case "invoice-month-totals":
            $year = urldecode($_GET['filter']);
            $results = $invoice_database->get_month_totals($year);
            break;

        case "total-invoices-month-profit":
            $results = $invoice_database->get_total_invoices_month_profit($_GET['month'], $_GET['year']);
            break;

        case "invoiced-item-month-totals":
            $results = $invoice_database->get_item_month_totals($_GET['month'], $_GET['year']);
            break;

        case "invoice-info":
            $invoice_id = urldecode($_GET['filter']);
            $results = $invoice_database->get_invoice_info($invoice_id);
            break;

        case "invoice-products":
            $invoice_id = urldecode($_GET['filter']);
            $results = $invoice_database->get_invoice_products($invoice_id);
            break;

        case "items_id_name":
            $results = $items_database->get_id_names();
            break;

        case "customers_id_name":
            $results = $customer_database->get_id_names();
            break;

        case "page_section_id_name":
            $results = $all_databases->get_page_section_id_names();
            break;

        case "retail_item_id_name":
            $results = $all_databases->get_retail_item_id_names();
            break;

        case "supplier_id_name":
            $results = $all_databases->get_supplier_id_names();
            break;

        case "top-selling-item":
            $results = $items_database->get_top_selling_item();
            break;

        case "least-purchased-item":
            $results = $items_database->get_least_purchased_item();
            break;

        case "most-income-item":
            $results = $items_database->get_most_income_item();
            break;

        case "least-income-item":
            $results = $items_database->get_least_income_item();
            break;

        case "low-stock":
            $results = $items_database->get_low_stock_items();
            break;

        case "invoiced-items-basic-id":
            $invoice_id = urldecode($_GET['filter']);
            $results = $invoice_database->get_basic_invoiced_item_from_id($invoice_id);
            break;

        case "invoiced-items-basic-ids":
            $invoice_ids = urldecode($_GET['ids']);
            $results = $invoice_database->get_basic_invoiced_item_from_ids($invoice_ids);
            break;

        case "images-from-item-id":
            $item_id = urldecode($_GET['filter']);
            $results = $all_databases->get_images_from_item_id($item_id);
            break;

        case "image-count-from-item-id":
            $item_id = urldecode($_GET['filter']);
            $results = $all_databases->get_images_count_from_item_id($item_id);
            break;
    }
    echo json_encode($results);
}

function construct_table($all_databases) {
    $table_name = urldecode($_GET["filter"]);
    $formatted_names = [];
    $edittable_display_names = [];
    $edittable_data_types = [];
    $edittable_required = [];
    $edittable_field_names = [];

    $table_data = $all_databases->get_table_data($table_name);
    $table_columns = $all_databases->get_columns($table_name);
    foreach ($table_columns as $key => $column) {
        $formatted_names[] = $column['Comment'];
        $data_types[] = $column['Field'] == 'image_file_name' ? 'file' : $column['Type'];
        if ($column['Extra'] == null) {
            $edittable_display_names[] = $column['Comment'];
            $edittable_field_names[] = $column['Field'];
            if ($column['Field'] == 'image_file_name') {
                $edittable_data_types[] = 'file';
            } else {
                $edittable_data_types[] = $column['Type'];
            }
            if ($column['Null'] == "NO") {
                $edittable_required[] = true;
            } else {
                $edittable_required[] = false;
            }
        }
    }

    $display_data = get_display_data($table_data, $table_name, $all_databases);
    $edittable_columns = get_edittable_columns($table_name, $all_databases);

    return [
        'display_data' => $display_data,
        'display_names' => $formatted_names,
        'data' => $table_data,
        'types' => $data_types,
        'edittable' => [
            'columns' => $edittable_columns,
            'fields' => $edittable_field_names,
            'types' => $edittable_data_types,
            'names' => $edittable_display_names,
            'required' => $edittable_required
        ]
    ];
}

function get_edittable_columns($table_name, $all_databases) {
    $edittable_columns = [];
    $columns = $all_databases->get_columns($table_name);
    foreach($columns as $column) {
        if ($column['Extra'] == null) {
            $edittable_columns[] = $column['Field'];
        }
    }
    return $edittable_columns;
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
                case "customers":
                    $assoc_table_data = $all_databases->get_customers_name();
                    break;

                case "invoices":
                    $assoc_table_data = $all_databases->get_invoice_titles();
                    break;
                
                case "items":
                    $assoc_table_data = $all_databases->get_item_names();
                    break;

                case "retail_items":
                    $assoc_table_data = $all_databases->get_retail_item_names();
                    break;
                
                case "page_sections":
                    $assoc_table_data = $all_databases->get_page_section_names();
                    break;
                
                // case "retail_items":
                //     $alt_query = $conn->query("SELECT ri.id, i.item_name FROM `items` AS i INNER JOIN `retail_items` AS ri ON ri.item_id = i.id");
                //     break;
                
                // case "offers":
                //     $alt_query = $conn->query("SELECT `id`, `name` FROM `offers`");
                //     break;
                
                // case "page_sections":
                //     $alt_query = $conn->query("SELECT `id`, `name` FROM `page_sections`");
                //     break;
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

    // $display_data[$key][$item_key][$assoc_column] = ;

    return $display_data;
}

?>