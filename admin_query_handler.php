<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET,POST,PUT,DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

    
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

    $conn->connect();
    $results = null;
    switch ($query) {
        case "table":
            $results = construct_table($all_databases);
            break;

        case "total-invoices-month":
            $results = $invoice_database->get_total_invoices_month();
            break;

        case "invoices-due-today":
            $results = $invoice_database->get_invoices_due_today();
            break;

        case "total-customers":
            $results = $customer_database->get_total_customers();
            break;

        case "new-customers":
            $results = $customer_database->get_new_customers();
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
        if ($column['Extra'] == null) {
            $edittable_display_names[] = $column['Comment'];
            $edittable_data_types[] = $column['Type'];
            $edittable_field_names[] = $column['Field'];
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
                
                // case "items":
                //     $alt_query = $conn->query("SELECT `id`, `item_name` FROM `items`");
                //     break;
                
                // case "invoices":
                //     $alt_query = $conn->query("SELECT `id`, `title` FROM `invoices`");
                //     break;
                
                // case "suppliers":
                //     $alt_query = $conn->query("SELECT `id`, CONCAT(`forename`, ' ', `surname`) AS full_name FROM `suppliers`");
                //     break;
                
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
                    if (array_key_exists($display_data[$key][$assoc_column], $assoc_table_data)) {
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