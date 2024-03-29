<?php

require_once 'cors_config.php';

require_once('config.php');

class DatabaseConnection {
    private $server_name;
    private $dB_username;
    private $dB_password;
    private $dB_name;
    private $conn;

    public function __construct() {
        $this->server_name = DatabaseConfig::$server_name;
        $this->dB_username = DatabaseConfig::$dB_username;
        $this->dB_password = DatabaseConfig::$dB_password;
        $this->dB_name = DatabaseConfig::$dB_name;
    }

    public function connect($auto_commit = true) {
        $this->conn = new mysqli($this->server_name, $this->dB_username, $this->dB_password, $this->dB_name);

        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }

        $this->conn->autocommit($auto_commit);
    }

    public function get_connection() {
        return $this->conn;
    }

    public function close_connection() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
    public function auto_commit($mode) {
        return $this->conn->autocommit($mode);
    }
    public function prepare_statement($sql) {
        return $this->conn->prepare($sql);
    }
    public function query($query) {
        try {
            $result = $this->conn->query($query);
    
            if ($result === false) {
                echo json_encode(array('success' => false, 'message' => 'There was an error querying the database! ' . $this->conn->error));
                $this->abort();
            }
    
            return $result;
        } catch (Exception $e) {
            echo json_encode(array('success' => false, 'message' => 'There was an error querying the database! ' . $this->conn->error));
            $this->abort();            
        }
    }
    public function execute($stmt) {
        return $stmt->execute();
    }
    public function commit() {
        return $this->conn->commit();
    }
    public function abort() {
        $this->conn->rollback();
        $this->close_connection();
        exit();
    }
}