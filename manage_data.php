<?php

require_once 'cors_config.php';

session_start();

if (!isset($_SESSION['CREATED'])) {
    $_SESSION['CREATED'] = time();
} else if (time() - $_SESSION['CREATED'] > 18000) {
    session_regenerate_id();
    session_destroy();
    $_SESSION['CREATED'] = time();
}


require_once 'dbh.php';
require_once 'database_functions.php';
require_once 'database_utility.php';
require_once 'sync.php';

$input_data = file_get_contents("php://input");
$data = json_decode($input_data, true);

$_POST['id'] = isset($data['id']) ? $data['id'] : '';
$_POST['action'] = $data['action'];

if (isset($data['action'])) {

    $database = new DatabaseConnection();

    $database->connect(false);

    $database_utility = new DatabaseUtility($database);
    $user_database = new UserDatabase($database_utility);
    $customer_database = new CustomerDatabase($database_utility);

    switch ($data['action']) {
        case 'add':
            $response = insert($database, $database_utility, $data);
            break;

        case 'append':
            $response = append($database, $database_utility, $user_database, $customer_database, $data);
            break;

        case 'delete':
            $response = drop($database, $data);
            break;

        case 'login':
            login($user_database, $data);
            break;

        case 'customer-login':
            customer_login($customer_database, $data);
            break;

        case 'logout':
            $response = logout();
            break;

        case 'check-login':
            $response = check_login($user_database);
            break;

        case 'check-login-customer':
            $response = check_login_customer();
            break;

        case 'change-password':
            $response = change_password($user_database, $data, $database);
            break;

        default:
            $response = array('success' => false, 'message' => 'Unknown API endpoint!');
            break;
    }
    echo json_encode($response);
    $database->close_connection();
    exit();
} else {
    echo("ERROR: Inconclusive call! Please contact the administrator!" . "other" . "E_SQL-MD-001");
    exit();
}

function insert($conn, $database_utility, $data)
{
    if (array_key_exists('password', $data) && $data['password']) {
        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 10]);
    }

    $table_name = $data['table_name'];
    $field_names = get_field_names($conn, $table_name);
    $submitted_data = construct_submitted_data($database_utility, $field_names, $table_name, $data);
    $query = $database_utility->construct_insert_query($table_name, $field_names, $submitted_data, $data);

    return synchronise($conn, $table_name, null, $query, $data);
}
function append($conn, $database_utility, $user_database, $customer_database, $data)
{
    if ($data['table_name'] == 'users') {
        $data = append_user($user_database, $data['username'], $data);
    }
    if ($data['table_name'] == 'customers') {
        $data = append_customer($customer_database, $data['id'], $data);
    }

    $table_name = $data['table_name'];
    $field_names = get_field_names($conn, $table_name);
    $submitted_data = construct_submitted_data($database_utility, $field_names, $table_name, $data);
    $query = $database_utility->construct_append_query($table_name, $field_names, $submitted_data);

    return synchronise($conn, $table_name, $data['id'], $query, $data);
}

function append_user($user_database, $username, $data)
{
    $current_password = $user_database->get_user_password($username);

    if ($current_password != $data['password']) {
        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 10]);
    }

    return $data;
}

function append_customer($customer_database, $customer_id, $data)
{
    $current_password = $customer_database->get_customer_password($customer_id);
    if ($current_password != $data['password'] && $data['password'] != '') {
        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 10]);
    } else {
        $data['password'] = $current_password;
    }

    return $data;
}

function drop($conn, $data)
{
    $table_name = $data['table_name'];
    $ids = $data['id'];

    if (str_contains($ids, ",")) {
        $ids = rtrim($ids, ',');
        $query = 'DELETE FROM ' . $table_name . ' WHERE ID IN (' . $ids . ')';
        $ids = explode(',', $ids);
    } else {
        $query = 'DELETE FROM ' . $table_name . ' WHERE ID = ' . $ids;
    }

    return synchronise($conn, $table_name, $ids, $query, $data);
}

function check_date($original_date)
{
    if ($original_date == null) {
        return null;
    }
    return date('Y-m-d', strtotime($original_date));
}
function synchronise($conn, $table_name, $id, $query_string, $data)
{
    require_once 'database_functions.php';
    require_once 'database_utility.php';

    $database_utility = new DatabaseUtility($conn);

    $action = $_POST['action'];
    $query_ran = false;

    if ($id == null) {
        $id = get_row_contents($conn, "SELECT auto_increment from information_schema.tables WHERE table_name = '" . $table_name . "' AND table_schema = DATABASE()")[0][0] - 1;
    }
    
    $id = is_array($id) ? $id : [$id];

    $response = true;
    switch ($table_name) {
        case 'invoiced_items':
            $query_ran = $action != 'delete';
            $response = sync_invoiced_items($conn, $database_utility, $id[0], $action, $data, $query_string);
            break;

        case 'customer_payments':
            $response = sync_customer_payments($database_utility, $id[0], $action, $data, $query_string);
            break;

        default:
            $conn->query($query_string);
            $response = array('success' => true, 'message' => 'Record actioned successfully', 'id' => $id[0]);
            $query_ran = true;
            break;
    }

    if (!$query_ran) {
        $conn->query($query_string);
    }

    if ($response === true || is_array($response) && array_key_exists('success', $response) && $response['success'] === true)
    {
        if (!$conn->commit())
        {
            echo('ERROR: ' . $action . ' failed, synchronisation aborted! Please contact administrator!' . 'other' . 'F_SQL-MD-0004' . $conn->error);
        }
    }

    return $response;
}

function sync_invoiced_items($conn, $database_utility, $id, $action, $data, $query_string) {
    $invoiced_items_sync = new SyncInvoicedItems($database_utility, new InvoiceDatabase($database_utility), new AllDatabases($database_utility));
    switch ($action) {
        case "add":
            $conn->query($query_string);
            return $invoiced_items_sync->sync_invoiced_items_insert($id + 1, $data['item_id'], $data['quantity']);

        case "append":
            $conn->query($query_string);
            return $invoiced_items_sync->sync_invoiced_items_append($id + 1, $data['item_id'], $data['quantity']);

        case "delete":
            return $invoiced_items_sync->sync_invoiced_items_delete($id, $query_string);
        
        default:
            return array('success' => true, 'message' => 'Record actioned successfully', 'id' => $id[0]);
    }
}

function sync_customer_payments($database_utility, $id, $action, $data, $query_string)
{
    $customer_payments_sync = new SyncCustomerPayments($database_utility, new InvoiceDatabase($database_utility), new AllDatabases($database_utility), new CustomerPaymentsDatabase($database_utility), new CustomerDatabase($database_utility));
    switch ($action)
    {
        case "add":
            return $customer_payments_sync->sync_customer_payments_insert($id, $data['amount'], $data['invoice_id'], $data['status']);

        case "append":
            return $customer_payments_sync->sync_customer_payments_append($id, $data['amount'], $data['invoice_id'], $data['status']);

        case "delete":
            return $customer_payments_sync->sync_customer_payments_drop($id, $query_string);

        default:
            return array('success' => true, 'message' => 'Record actioned successfully', 'id' => $id[0]);
    }
}

function manage_offers($item_database, $retail_item_database, $item_id, $new_total_sold) {
    $current_total_sold = $item_database->get_total_sold($item_id);
    $sold_difference = $new_total_sold - $current_total_sold;
    $offer_data = $retail_item_database->get_offer_from_item($item_id);
    
    if ($offer_data == null || $offer_data['quantity_limit'] == 0 || $offer_data['quantity_limit'] == 0 || $offer_data['offer_end'] == null) {
        return;
    }

    $new_offer_quantity_limit = $offer_data['quantity_limit'] - $sold_difference;
    if ($new_offer_quantity_limit > 0) 
    {
        $retail_item_database->set_offer_quantity_limit($item_id, $new_offer_quantity_limit);
    }
    else
    {
        $retail_item_database->reset_offer_from_item_id($item_id);
    }
}

function get_invoice_value($item_database, $item_data, $discount)
{
    $invoice_id = $item_data["invoice_id"];
    $vat_charge = $item_data["vat_charge"];

    $invoiced_item_total = $item_database->get_invoice_total($invoice_id);


    $net = $discount == 0 ? $invoiced_item_total : $invoiced_item_total * (1 - $discount / 100);
    $vat = $vat_charge == "Yes" ? $net * 0.2 : 0;
    $total = $net + $vat;

    return [
        0 => round($net, 2),
        1 => round($vat, 2),
        2 => round($total, 2),
    ];
}
function get_row_contents($conn, $query_string)
{
    $query = $conn->query($query_string);
    $contents = $query->fetch_all();
    return $contents;
}

function check_login($user_database) {
    if (isset($_SESSION['user']) && $_SESSION['user']) {
        $access_level = $user_database->get_access_level($_SESSION['username']);
        $response = array('success' => true, 'message' => 'User logged in', 'data' => $access_level);
    }
    else {
        $response = array('success' => false, 'message' => 'No previous logins');
    }
    
    return $response;
}

function check_login_customer() {
    if (isset($_SESSION['customer']) && $_SESSION['customer'] && isset($_SESSION['customer_type'])) {
        $response = array('success' => true, 'message' => 'User logged in', 'data' => array('id' => $_SESSION['id'], 'customer_type' => $_SESSION['customer_type']));
    } else {
        $response = array('success' => false, 'message' => 'No previous logins');
    }

    return $response;
}

function logout() {
    session_destroy();
    return array('success' => true, 'message' => 'Logout successful');
}

function login($user_database, $data)
{
    $username = $data['username'];
    $password = $data['password'];

    $password_hash = $user_database->get_user_password($username);
    if ($password_hash != null && password_verify($password, $password_hash)) {
        $access_level = $user_database->get_access_level($username);
        
        $_SESSION['user'] = 'authenticated';
        $_SESSION['username'] = $username;
        $response = array('success' => true, 'message' => 'Login successful', 'data' => $access_level);
    } else {
        $response = array('success' => false, 'message' => 'Invalid credentials');
    }

    echo json_encode($response);
    exit();
}

function customer_login($customer_database, $data) {
    $email = $data['email'];
    $password = $data['password'];

    $password_hash = $customer_database->get_password_from_email($email);
    if ($password_hash != null && password_verify($password, $password_hash)) {
        $userInfo = $customer_database->get_customer_type_from_email($email);
        $_SESSION['customer'] = 'authenticated';
        $_SESSION['id'] = $userInfo['id'];
        $_SESSION['customer_type'] = $userInfo['customer_type'];

        $response = array('success' => true, 'message' => 'Login successful', 'data' => $userInfo);
    } else {
        $response = array('success' => false, 'message' => 'Invalid credentials');
    }

    echo json_encode($response);
    exit();
}

function change_password($user_database, $data, $conn) {
    if (key_exists('current_password', $data) && key_exists('new_password', $data) && key_exists('username', $data)) {
        $current_password = $data['current_password'];
        $current_password_hash = $user_database->get_user_password($data['username']);
        if ($current_password_hash != null && password_verify($current_password, $current_password_hash)) {
            $user_database->change_password($data['username'], $data['new_password']);
            $conn->commit();
            return array('success' => true, 'message' => 'Password changed successfully!');
        } else {
            return array('success' => false, 'message' => 'The password was incorrect!');
        }

    } else {
        return array('success' => false, 'message' => 'There was data missing! Please login again');
    }
}
function create_account($user_database)
{
    require 'dbh.php';
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT, ['cost' => 10]);
    $access_level = $_POST['level'];
    $user_database->user_exists($username);
    if ($rows != 0) {
        $_SESSION['mysql_error'] =  "Error: A user with that username is taken!";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (`username`, `password`, `level`) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $password, $access_level);
        $stmt->execute();
        exit();
    }
}

function get_field_names($conn, $table_name)
{
    $query = $conn->query('SHOW FULL COLUMNS FROM ' . $table_name);
    while ($row = $query->fetch_assoc()) {
        if ($row['Extra'] == null) {
            $field_names[] = $row['Field'];
        }
    }
    return $field_names;
}

function construct_submitted_data($db_utility, $field_names, $table_name, $data)
{
    $submitted_data = [];
    foreach ($field_names as $field_name) {
        $type = $db_utility->get_type_from_field($table_name, $field_name);
        if ($type == 'date') {
            if (array_key_exists($field_name, $data)) {
                $date = check_date($data[$field_name]);
                if ($date != null) {
                    $submitted_data[$field_name] = $date;
                } else {
                    $submitted_data[$field_name] = NULL;
                }
            }
            else {
                $submitted_data[$field_name] = NULL;
            }
        } else {
            if ($field_name == "image_file_name" && array_key_exists('image_file_name', $_FILES)) {
                if ($_FILES[$field_name]['name'] == null) {
                    $file_name = $db_utility->recover_retail_image($data['id']);
                    $data[$field_name] = $file_name;
                } else {
                    $data[$field_name] = $_FILES[$field_name]['name'];
                    echo  $_FILES[$field_name]['name'];
                }
            }
            if (array_key_exists($field_name, $data)) {
                $submitted_data[$field_name] = $data[$field_name];
            } else {
                $submitted_data[$field_name] = NULL;
            }
        }
    }
    return $submitted_data;
}
