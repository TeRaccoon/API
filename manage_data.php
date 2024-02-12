<?php

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("Access-Control-Allow-Origin: http://localhost:4200");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
    header("Access-Control-Max-Age: 3600");
    header("Access-Control-Allow-Credentials: true");
    exit;
}

header("Access-Control-Allow-Origin: http://localhost:4200");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET,POST,PUT,DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

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
require_once 'error-handler.php';

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
            insert($database, $database_utility, $data);
            $response = array('success' => true, 'message' => 'Record added successfully');
            break;

        case 'append':
            append($database, $database_utility, $user_database, $customer_database, $data);
            $response = array('success' => true, 'message' => 'Record appended successfully');
            break;

        case 'delete':
            drop($database, $data);
            $response = array('success' => true, 'message' => 'Record dropped successfully');
            break;

        case 'login':
            login($user_database, $data);
            break;

        case 'logout':
            logout();
            break;

        case 'check-login':
            check_login();
            break;

        case 'change-password':
            $response = change_password($user_database, $data, $database);
            break;
    }
    echo json_encode($response);
    $database->close_connection();
    exit();
} else {
    echo("ERROR: Inconclusive call! Please contact administrator!" . "other" . "E_SQL-MD-001");
    echo "Ruh roh";
    exit();
}

function insert($conn, $database_utility, $data)
{
    $table_name = $data['table_name'];
    $field_names = get_field_names($conn, $table_name);
    $submitted_data = construct_submitted_data($database_utility, $field_names, $table_name, $data);
    $query = $database_utility->construct_insert_query($table_name, $field_names, $submitted_data, $data);

    $conn->query($query);
    $conn->commit();

    synchronise($conn, $table_name, null, null);
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

    $conn->query($query);
    $conn->commit();

    synchronise($conn, $table_name, $data['id'], $query);
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

    if ($current_password != $data['password']) {
        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 10]);
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

    synchronise($conn, $table_name, $ids, $query);
}

function check_date($original_date)
{
    if ($original_date == null) {
        return null;
    }
    return date('Y-m-d', strtotime($original_date));
}
function synchronise($conn, $table_name, $id, $query_string)
{
    require_once 'database_functions.php';
    require_once 'database_utility.php';

    $database_utility = new DatabaseUtility($conn);
    $customer_database = new CustomerDatabase($database_utility);
    $item_database = new ItemDatabase($database_utility);
    $invoice_database = new InvoiceDatabase($database_utility);
    $customer_payments_database = new CustomerPaymentsDatabase($database_utility);

    $action = $_POST['action'];

    if ($id == null) {
        $id = get_row_contents($conn, "SELECT auto_increment from information_schema.tables WHERE table_name = '" . $table_name . "' AND table_schema = DATABASE()")[0][0] - 1;
    }

    $id = is_array($id) ? $id : [$id];

    switch ($table_name) {
        case "invoiced_items":
            foreach ($id as $item_id) {
                $invoiced_item_data = $item_database->get_invoiced_items_data($item_id);

                if ($action == "delete") { // Need to retrieve data from what was deleted before deleting
                    $database_utility->execute_delete_query($table_name, $item_id);
                }
                $customer_id = $invoice_database->get_customer_id($invoiced_item_data['invoice_id']);

                update_invoiced_item($customer_database, $item_database, $invoiced_item_data, $customer_id);
            }

            if (!$conn->commit()) {
                echo('ERROR: ' . $action . ' failed, synchronisation aborted! Please contact administrator!'. 'other'. 'F_SQL-MD-0002'. $conn->error);
                $conn->abort();
            }
            $debt = $invoice_database->get_customer_debt($customer_id);

            $conn->query("UPDATE customers SET outstanding_balance = '" . $debt . "' WHERE id = '" . $customer_id . "'");
            break;

        case "customer_payments":
            foreach ($id as $payment_id) {
                $payment_data = $customer_payments_database->get_payment_data($payment_id);
                update_customer_payment($invoice_database, $customer_database, $customer_payments_database, $payment_data, $payment_id);
            }
            break;

        default:
            if (!$conn->commit()) {
                echo('ERROR: ' . $action . ' failed, synchronisation aborted! Please contact administrator!' . 'other' . 'F_SQL-MD-0003' . $conn->error);
                echo "Ruh roh";
            }
            break;
    }

    if ($query_string != null) {
        $conn->query($query_string);
    }

    if (!$conn->commit()) {
        echo('ERROR: ' . $action . ' failed, synchronisation aborted! Please contact administrator!' . 'other' . 'F_SQL-MD-0004' . $conn->error);
    }
}

function update_customer_payment($invoice_database, $customer_database, $customer_payments_database, $payment_data, $id)
{
    $payment_status = $payment_data['status'];
    $invoice_id = $payment_data['invoice_id'];
    $customer_id = $payment_data['customer_id'];

    if ($payment_status == 'Processed' || $_POST['status'] == 'Processed') {
        $total_invoice_payments = $customer_payments_database->get_total_invoice_payments($invoice_id);
    }
}
function update_invoiced_item($customer_database, $item_database, $invoiced_item_data, $customer_id)
{
    $discount = $customer_database->get_customer_discount($customer_id);
    $invoice_values = get_invoice_value($item_database, $invoiced_item_data, $discount);

    $item_id = $invoiced_item_data["item_id"];

    //Ammending item_invoice total to applied invoice
    $customer_database->set_invoice_values($invoice_values[0], $invoice_values[1], $invoice_values[2], $invoiced_item_data["invoice_id"]);

    //Appending total sold
    $total_sold = $item_database->get_calculated_total_sold($item_id);
    $item_database->set_total_sold($total_sold, $item_id);
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

function check_login() {
    if (isset($_SESSION['user']) && $_SESSION['user']) {
        $response = array('success' => true, 'message' => 'User logged in');
    }
    else {
        $response = array('success' => false, 'message' => 'No previous logins');
    }
    
    echo json_encode($response);
    exit();
}

function logout() {
    session_destroy();
    return "Session cleared successfully!";
}

function login($user_database, $data)
{
    $username = $data['username'];
    $password = $data['password'];

    $password_hash = $user_database->get_user_password($username);
    if ($password_hash != null && password_verify($password, $password_hash)) {
        $_SESSION['user'] = 'authenticated';
        $_SESSION['username'] = $username;
        $response = array('success' => true, 'message' => 'Login successful');
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
            $date = check_date($data[$field_name]);
            if ($date != null) {
                $submitted_data[$field_name] = $date;
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
            $submitted_data[$field_name] = $data[$field_name];
        }
    }
    return $submitted_data;
}
