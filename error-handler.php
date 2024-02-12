<?php
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("Access-Control-Allow-Origin: http://158.220.83.74");
//header("Access-Control-Allow-Origin: http://localhost:4200");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
    header("Access-Control-Max-Age: 3600");
    header("Access-Control-Allow-Credentials: true");
    exit;
}

header("Access-Control-Allow-Origin: http://158.220.83.74");
//header("Access-Control-Allow-Origin: http://localhost:4200");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET,POST,PUT,DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

class ErrorHandler {
    public static function set_error($message, $type, $code, $description) {
        $_SESSION['error'] = $message;
        $_SESSION['error_type'] = $type;
        $_SESSION['error_code'] = $code;
        $_SESSION['error_description'] = $description;
    }

    public static function clear_error() {
        unset($_SESSION['error']);
        unset($_SESSION['error_type']);
        unset($_SESSION['error_code']);
        unset($_SESSION['error_description']);
    }
}

?>