<?php

require_once 'cors_config.php';

class DatabaseUtility {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function execute_query($query, $params, $results_format) {
        $stmt = $this->conn->prepare_statement($query);

        if ($stmt) {
            $paramTypes = "";
            $paramValues = [];
            if ($params != null) {
                foreach ($params as $param) {
                    $paramTypes .= $param['type'];
                    $paramValues[] = &$param['value'];
                }
                array_unshift($paramValues, $paramTypes);
                call_user_func_array([$stmt, 'bind_param'], $paramValues);
            }
            $this->conn->execute($stmt);

            switch ($results_format) {
                case 'assoc-array':
                    return $this->bind_results($stmt);

                case 'array':
                    return $this->results_as_array($stmt);

                case 'row-count':
                    return $stmt->num_rows;
                    
                default:
                    return true;
            }

        } else {
            return false; 
        }
    }

    public function results_as_array($stmt) {
        $meta = $stmt->result_metadata();
        $fieldName = $meta->fetch_field()->name;
        
        $results = $stmt->get_result();
        $array = array();
        while ($row = $results->fetch_assoc()) {
            $array[] = $row[$fieldName];
        }

        return $array;
    }
    public function bind_results($stmt) {
        $meta = $stmt->result_metadata();
        $result = array();
    
        while ($field = $meta->fetch_field()) {
            $result[$field->name] = null;
            $bindParams[] = &$result[$field->name];
        }
    
        call_user_func_array(array($stmt, 'bind_result'), $bindParams);
    
        $rows = array();

        while ($stmt->fetch()) {
            $row = array();
            foreach ($result as $key => $value) {
                $row[$key] = $value;
            }
            $rows[] = $row;
        }

        if (sizeof($rows) == 1) {
            return $rows[0];
        }

        return $rows;
    }
    public function construct_insert_query($table_name, $field_names, $submitted_data, $data) {
        $nonEmptyFields = array_filter($field_names, function($field) use ($data) {
            return isset($data[$field]) && $data[$field] !== ''; // Check for empty string
        });
        $fields_string = implode(', ', $nonEmptyFields);
        
        $nonEmptyValues = array_map(function($field) use ($submitted_data) {
            return isset($submitted_data[$field]) ? "'" . $submitted_data[$field] . "'" : 'NULL';
        }, $nonEmptyFields);
    
        $values_string = implode(', ', $nonEmptyValues);
        
        $query = "INSERT INTO $table_name ($fields_string) VALUES ($values_string)";
        return $query;
    }
    public function construct_append_query($table_name, $field_names, $submitted_data) {
        $set_string = implode(', ', array_map(function($field) use ($submitted_data) {
            $fieldValue = isset($submitted_data[$field]) ? $submitted_data[$field] : "";
            return "$field = " . ($fieldValue === "" ? "NULL" : "'$fieldValue'");
        }, $field_names));
    
        $query = 'UPDATE ' . $table_name . ' SET ' . $set_string . ' WHERE ID = ' . $_POST['id'];
        return $query;
    }    

    public function execute_delete_query($table_name, $id) {
        $query = 'DELETE FROM ' . $table_name . ' WHERE ID = (?)';
        $params = [
            ['type' => 'i', 'value' => $id]
        ];
        $this->execute_query($query, $params, false);
    }

    public function get_current_id($table_name) {
        $query = 'SELECT id FROM ? ORDER BY id DESC LIMIT 1';
        $params = [
            ['type' => 's', 'value' => $table_name]
        ];
        $id = $this->execute_query($query, $params, 'assoc-array')['id'];
        return $id;
    }

    public function get_type_from_field($table_name, $field_name) {
        $query = 'SHOW FULL COLUMNS FROM ' . $table_name . ' WHERE Field = "' . $field_name . '"';
        $type = $this->execute_query($query, null, 'assoc-array')['Type'];
        return $type;
    }

    public function recover_retail_image($id) {
        $query = 'SELECT image_file_name FROM retail_items WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $id]
        ];
        $image_file_location = $this->execute_query($query, $params, 'assoc-array')['image_file_name'];
        return $image_file_location;
    }
}
?>