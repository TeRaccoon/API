<?php

require_once 'cors_config.php';

class UserDatabase
{
    private $db_utility;

    public function __construct($db_utility)
    {
        $this->db_utility = $db_utility;
    }

    function get_user_password($username)
    {
        $query = 'SELECT password FROM users WHERE BINARY username = ?';
        $params = [
            ['type' => 's', 'value' => $username]
        ];
        $password_hash = $this->db_utility->execute_query($query, $params, 'assoc-array');
        if (key_exists('password', $password_hash)) {
            return $password_hash['password'];
        }
        return '';
    }

    function get_access_level($username)
    {
        $query = 'SELECT level FROM users WHERE username = ?';
        $params = [
            ['type' => 's', 'value' => $username]
        ];
        $access_level = $this->db_utility->execute_query($query, $params, 'assoc-array')['level'];
        return $access_level;
    }

    function change_password($username, $password)
    {
        $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

        $query = 'UPDATE users SET password = ? WHERE username = ?';

        $params = [
            ['type' => 's', 'value' => $password_hash],
            ['type' => 's', 'value' => $username],
        ];

        // Execute the query
        $this->db_utility->execute_query($query, $params, false);
    }

    function user_exists($username)
    {
        $query = 'SELECT username FROM users WHERE username = ?';
        $params = [
            ['type' => 's', 'value' => $username]
        ];
        $row_count = $this->db_utility->execute_query($query, $params, 'row-count');
        return $row_count;
    }
}

class AllDatabases
{
    private $db_utility;
    public function __construct($db_utility)
    {
        $this->db_utility = $db_utility;
    }

    public function get_next_id($table_name)
    {
        $query = 'ANALYZE TABLE ' . $table_name;
        $this->db_utility->execute_query($query, null, false);

        $query = 'SELECT AUTO_INCREMENT AS next_id FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "' . $table_name . '"';
        $next_id = $this->db_utility->execute_query($query, null, 'assoc-array')['next_id'];
        return $next_id;
    }

    public function get_invoiced_item($id)
    {
        $query = 'SELECT * FROM invoiced_items WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $id]
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }

    public function get_next_supplier_account_code($table_name)
    {
        $query = 'ANALYZE TABLE suppliers';
        $this->db_utility->execute_query($query, null, false);

        $query = 'SELECT AUTO_INCREMENT AS next_id FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "' . $table_name . '"';
        $next_id = $this->db_utility->execute_query($query, null, 'assoc-array')['next_id'];
        return $next_id;
    }

    public function get_vat_returns()
    {
        $query = 'SELECT * FROM vat_returns';
        return $this->db_utility->execute_query($query, null, 'assoc-array');
    }

    public function get_vat_history_by_group_id($vat_group_id)
    {
        $query = 'SELECT * FROM vat_returns WHERE vat_group_id = ?';
        $params = [
            ['type' => 's', 'value' => $vat_group_id]
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }

    public function delete_vat_history_by_group_id($vat_group_id)
    {
        $query = 'DELETE FROM vat_returns WHERE vat_group_id = ?';
        $params = [
            ['type' => 's', 'value' => $vat_group_id]
        ];
        return $this->db_utility->execute_query($query, $params, false);
    }

    public function get_vat_groups()
    {
        $query = 'SELECT DISTINCT vat_group_id FROM vat_returns';
        return $this->db_utility->execute_query($query, null, 'array');
    }

    public function get_stock_data_from_item_id($item_id)
    {
        $query = 'SELECT si.id, (CASE 
            WHEN si.packing_format = "Individual" THEN si.quantity
            WHEN si.packing_format = "Box" THEN si.quantity * i.box_size
            WHEN si.packing_format = "Pallet" THEN si.quantity * i.pallet_size
        END) AS quantity, (
            CASE 
            WHEN si.packing_format = "Individual" THEN 1
            WHEN si.packing_format = "Box" THEN i.box_size
            WHEN si.packing_format = "Pallet" THEN i.pallet_size
        END) AS modifier, si.expiry_date AS expiry_date
        FROM stocked_items si
        JOIN 
            items i ON si.item_id = i.id
        WHERE item_id = ? ORDER BY si.expiry_date';

        $params = [
            ['type' => 'i', 'value' => $item_id]
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }

    public function update_stock_quantity_from_id($id, $quantity)
    {
        $query = 'UPDATE stocked_items SET quantity = ? WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $quantity],
            ['type' => 'i', 'value' => $id]
        ];

        return $this->db_utility->execute_query($query, $params, false);
    }

    public function action_stock_control($stock_id, $invoiced_item_id, $quantity, $action = 'insert')
    {
        switch ($action) {
            case 'insert':
                $query = 'INSERT INTO stock_keys (stock_id, invoiced_item_id, quantity) VALUES (?, ?, ?)';
                break;

            default:
                $query = 'INSERT INTO stock_keys (stock_id, invoiced_item_id, quantity) VALUES (?, ?, ?)';
                break;
        }

        $params = [
            ['type' => 'i', 'value' => $stock_id],
            ['type' => 'i', 'value' => $invoiced_item_id],
            ['type' => 'i', 'value' => $quantity],
        ];

        return $this->db_utility->execute_query($query, $params, false);
    }

    public function get_stock_key_data_from_invoiced_item_id($id)
    {
        $query = 'SELECT * FROM stock_keys WHERE invoiced_item_id = ?';
        $params = [
            ['type' => 'i', 'value' => $id]
        ];

        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }

    public function revert_stock_key_from_id($id, $quantity)
    {
        $query = 'SELECT quantity FROM stocked_items WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $id]
        ];

        $quantity += $this->db_utility->execute_query($query, $params, 'array')[0];

        $query = 'UPDATE stocked_items SET quantity = ? WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $quantity],
            ['type' => 'i', 'value' => $id]
        ];

        return $this->db_utility->execute_query($query, $params, false);
    }

    public function delete_stock_key_from_stock_id($invoiced_item_id)
    {
        $query = 'DELETE FROM stock_keys WHERE invoiced_item_id = ?';
        $params = [
            ['type' => 'i', 'value' => $invoiced_item_id]
        ];

        return $this->db_utility->execute_query($query, $params, false);
    }

    public function get_total_stock()
    {
        $query = 'SELECT 
        si.item_id, 
        SUM(
            CASE 
                WHEN si.packing_format = "Individual" THEN si.quantity
                WHEN si.packing_format = "Box" THEN si.quantity * i.box_size
                WHEN si.packing_format = "Pallet" THEN si.quantity * i.pallet_size
            END
        ) AS total_quantity 
        FROM 
            stocked_items si
        JOIN 
            items i ON si.item_id = i.id
        GROUP BY 
            si.item_id';

        $results = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $results;
    }

    public function get_total_stock_by_id($item_id)
    {
        $query = 'SELECT 
        SUM(
            CASE 
                WHEN si.packing_format = "Individual" THEN si.quantity
                WHEN si.packing_format = "Box" THEN si.quantity * i.box_size
                WHEN si.packing_format = "Pallet" THEN si.quantity * i.pallet_size
            END
        ) AS total_quantity
        FROM 
            stocked_items si
        JOIN 
            items i ON si.item_id = i.id
        WHERE
            si.item_id = ?';

        $params = [
            ['type' => 'i', 'value' => $item_id]
        ];

        $results = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $results;
    }

    public function get_stock_by_id($item_id)
    {
        $query = 'SELECT 
        si.packing_format,
        SUM(
            CASE 
                WHEN si.packing_format = "Individual" THEN si.quantity
                WHEN si.packing_format = "Box" THEN si.quantity * i.box_size
                WHEN si.packing_format = "Pallet" THEN si.quantity * i.pallet_size
            END
        ) AS total_quantity
        FROM 
            stocked_items si
        JOIN 
            items i ON si.item_id = i.id
        WHERE
            si.item_id = ?
        GROUP BY
            si.packing_format';

        $params = [
            ['type' => 'i', 'value' => $item_id]
        ];

        $results = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $results;
    }

    public function get_customer_address()
    {
        $query = 'SELECT id, CONCAT_WS(", ", delivery_address_one, delivery_address_two, delivery_address_three, delivery_address_four, delivery_postcode) AS address FROM customer_address';
        $addresses = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $this->format_data('address', $addresses);
    }

    public function get_customer_billing_address()
    {
        $query = 'SELECT id, CONCAT_WS(", ", invoice_address_one, invoice_address_two, invoice_address_three, invoice_address_four, delivery_postcode) AS address FROM customer_address';
        $addresses = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $this->format_data('address', $addresses);
    }

    public function get_offer_id_name()
    {
        $query = 'SELECT id, name AS replacement FROM offers ORDER BY replacement ASC';
        $results = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $results;
    }

    public function get_coordinates_from_postcode($postcode)
    {
        $query = "SELECT latitude, longitude FROM postcodelatlng WHERE REPLACE(postcode, ' ', '') = ?";
        $params = [
            ['type' => 's', 'value' => $postcode]
        ];
        $coordinates = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $coordinates;
    }

    public function get_warehouse_id_names()
    {
        $query = 'SELECT id, name AS replacement FROM warehouse ORDER BY replacement ASC';
        $data = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $data;
    }

    public function get_supplier_invoice_id_reference()
    {
        $query = 'SELECT id, reference AS replacement FROM supplier_invoices';
        $data = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $data;
    }

    public function get_customer_address_id_address()
    {
        $query = 'SELECT ca.id, CONCAT_WS(": ", COALESCE(customers.account_name, "N/A"), CONCAT_WS(", ", delivery_address_one, delivery_address_two, delivery_address_three, delivery_address_four, delivery_postcode)) AS replacement FROM customer_address AS ca LEFT JOIN customers ON ca.customer_id = customers.id';
        $data = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $data;
    }

    public function get_customer_billing_address_id_address()
    {
        $query = 'SELECT ca.id, CONCAT_WS(": ", COALESCE(customers.account_name, "N/A"), CONCAT_WS(", ", invoice_address_one, invoice_address_two, invoice_address_three, invoice_address_four, invoice_postcode)) AS replacement FROM customer_address AS ca LEFT JOIN customers ON ca.customer_id = customers.id';
        $data = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $data;
    }

    public function get_warehouse_name_from_id($warehouse_id)
    {
        $query = 'SELECT name FROM warehouse WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $warehouse_id]
        ];
        $warehouse_name = $this->db_utility->execute_query($query, $params, 'assoc-array')['name'];
        return $warehouse_name;
    }

    public function get_customer_postcode_from_id($customer_id)
    {
        $query = 'SELECT postcode FROM customers WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $customer_id]
        ];
        $postcode = $this->db_utility->execute_query($query, $params, 'assoc-array')['postcode'];
        return str_replace(' ', '', $postcode);
    }

    public function get_postcode_from_warehouse_id($warehouse_id)
    {
        $query = 'SELECT postcode FROM warehouse WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $warehouse_id]
        ];
        $postcode = $this->db_utility->execute_query($query, $params, 'assoc-array')['postcode'];
        return str_replace(' ', '', $postcode);
    }

    public function append_or_add($table, $id, $column)
    {
        $query = 'SELECT * FROM ' . $table . ' WHERE ' . $column . ' = ?';
        $params = [
            ['type' => 'i', 'value' => $id],
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }
    public function delete_image_by_file_name($image_file_name)
    {
        $query = 'DELETE FROM retail_item_images WHERE image_file_name = ?';
        $params = [
            ['type' => 's', 'value' => $image_file_name]
        ];

        $file = '../uploads/' . basename($image_file_name);

        if (file_exists($file) && is_writable($file)) {
            if (unlink($file)) {
                $response = array('success' => false, 'message' => 'The file could not be deleted!');
            } else {
                $response = array('success' => false, 'message' => 'The file could not be deleted!');
            }
        } else {
            $response = array('success' => false, 'message' => 'File does not exist or is not writable!');
        }

        $this->db_utility->execute_query($query, $params, false);
        return $response;
    }
    function get_tables()
    {
        $query = 'SHOW TABLES';
        $tables = $this->db_utility->execute_query($query, null, 'array');
        return $tables;
    }
    function get_table_data($table_name)
    {
        $query = 'SELECT * FROM ' . $table_name;
        $data = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $data;
    }

    function get_page_section_id_names()
    {
        $query = 'SELECT id, name AS replacement FROM page_sections ORDER BY replacement ASC';
        $data = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $data;
    }

    function get_retail_item_id_names()
    {
        $query = 'SELECT ri.id, i.item_name AS replacement FROM retail_items AS ri INNER JOIN items AS i ON ri.item_id = i.id ORDER BY replacement ASC';
        $data = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $data;
    }

    function get_supplier_id_name_code()
    {
        $query = 'SELECT id, CONCAT(account_name, " - ", account_code) AS replacement FROM suppliers';
        $data = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $data;
    }

    function get_columns($table_name)
    {
        $query = 'SHOW FULL COLUMNS FROM ' . $table_name;
        $data = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $data;
    }

    function get_associative_data($table_name)
    {
        $query = 'SELECT TABLE_NAME, REFERENCED_TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_NAME IS NOT NULL AND TABLE_SCHEMA = "hellenic" AND TABLE_NAME = "' . $table_name . '"';
        $data = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $data;
    }

    function get_customers_name()
    {
        $query = 'SELECT `id`, `account_name` AS full_name FROM `customers`';
        $names = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $this->format_data("full_name", $names);
    }

    function get_suppliers_name()
    {
        $query = 'SELECT id, account_name FROM suppliers';
        $names = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $this->format_data("account_name", $names);
    }

    function get_page_section_names()
    {
        $query = 'SELECT id, name FROM page_sections';
        $names = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $this->format_data('name', $names);
    }

    function get_warehouse_names()
    {
        $query = 'SELECT id, name FROM warehouse';
        $names = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $this->format_data('name', $names);
    }

    function get_invoice_titles()
    {
        $query = 'SELECT id, title FROM invoices';
        $titles = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $this->format_data('title', $titles);
    }

    function get_item_names()
    {
        $query = 'SELECT id, item_name FROM items';
        $names = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $this->format_data('item_name', $names);
    }

    function get_retail_item_names()
    {
        $query = 'SELECT ri.id, i.item_name FROM `items` AS i INNER JOIN `retail_items` AS ri ON ri.item_id = i.id';
        $names = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $this->format_data('item_name', $names);
    }

    function format_data($key, $incoming_data)
    {
        $data = [];
        if (!array_key_exists(0, $incoming_data)) {
            $incoming_data = [$incoming_data];
        }
        foreach ($incoming_data as $row) {
            $data[$row["id"]] = $row[$key];
        }
        return $data;
    }

    function get_profit_loss($startDate, $endDate)
    {
        $query = 'SELECT
        total_profit,
        total_cost,
        total_profit - total_cost AS gross_profit,
        total_expenses,
        total_profit - total_expenses - total_cost AS net_profit
    FROM
        (
            SELECT
                c.discount,
                SUM(ii.quantity * it.retail_price * (1 - c.discount / 100)) AS total_profit,
                SUM(ii.quantity * it.unit_cost) AS total_cost,
                (SELECT SUM(amount) FROM payments WHERE (category = "Expense" OR category = "Salary") AND date BETWEEN ? AND ?) AS total_expenses
            FROM
                invoices AS i
                INNER JOIN customers AS c ON i.customer_id = c.id
                INNER JOIN invoiced_items AS ii ON i.id = ii.invoice_id
                INNER JOIN items AS it ON ii.item_id = it.id
            WHERE
                ii.created_at BETWEEN ? AND ?
            GROUP BY
                c.discount
        ) AS derived_table';

        $params = [
            ['type' => 's', 'value' => $startDate],
            ['type' => 's', 'value' => $endDate],
            ['type' => 's', 'value' => $startDate],
            ['type' => 's', 'value' => $endDate],
        ];

        $profit_loss_data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $profit_loss_data;
    }

    function get_images_from_item_id($item_id)
    {
        $query = 'SELECT image_file_name FROM retail_item_images WHERE item_id = ?';
        $params = [
            ['type' => 'i', 'value' => $item_id],
        ];
        $item_locations = $this->db_utility->execute_query($query, $params, 'array');
        return $item_locations;
    }

    function get_images_from_page_section_id($page_section_id)
    {
        $query = 'SELECT image_file_name FROM image_locations WHERE page_section_id = ?';
        $params = [
            ['type' => 'i', 'value' => $page_section_id],
        ];
        return $this->db_utility->execute_query($query, $params, 'array');
    }

    function get_images_count_from_item_id($item_id)
    {
        $query = 'SELECT COUNT(rii.image_file_name) AS count FROM retail_item_images AS rii WHERE rii.item_id = ?';
        $params = [
            ['type' => 'i', 'value' => $item_id],
        ];
        $item_locations = $this->db_utility->execute_query($query, $params, 'assoc-array')['count'];
        return $item_locations;
    }

    function get_image_count_from_page_section_id($page_section_id)
    {
        $query = 'SELECT COUNT(image_file_name) AS count FROM image_locations WHERE page_section_id = ?';
        $params = [
            ['type' => 'i', 'value' => $page_section_id],
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array')['count'];
    }
}

class CustomerDatabase
{
    private $db_utility;

    public function __construct($db_utility)
    {
        $this->db_utility = $db_utility;
    }

    function get_customer($id)
    {
        $query = 'SELECT * FROM customers WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $id]
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }

    function get_outstanding_balance($customer_id)
    {
        $query = 'SELECT outstanding_balance FROM customers WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $customer_id]
        ];
        return $this->db_utility->execute_query($query, $params, 'array')[0];
    }

    public function update_outstanding_balance($balance, $id)
    {
        $query = 'UPDATE customers SET outstanding_balance = ? WHERE id = ?';
        $params = [
            ['type' => 'd', 'value' => $balance],
            ['type' => 'i', 'value' => $id]
        ];
        return $this->db_utility->execute_query($query, $params, false);
    }

    public function sync_outstanding_balance($id)
    {
        $query = 'UPDATE customers SET outstanding_balance = (SELECT SUM(total) FROM invoices WHERE customer_id = ?) WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $id],
            ['type' => 'i', 'value' => $id]
        ];
        return $this->db_utility->execute_query($query, $params, false);
    }

    function get_customer_password($id)
    {
        $query = 'SELECT password FROM customers WHERE id = ?';
        $params = [
            ['type' => 's', 'value' => $id]
        ];
        $password_hash = $this->db_utility->execute_query($query, $params, 'assoc-array')['password'];
        return $password_hash;
    }

    function get_password_from_email($id)
    {
        $query = 'SELECT password FROM customers WHERE email = ?';
        $params = [
            ['type' => 's', 'value' => $id]
        ];
        $password_hash = $this->db_utility->execute_query($query, $params, 'assoc-array')['password'];
        return $password_hash;
    }

    function get_customer_details_from_id($id)
    {
        $query = 'SELECT forename, surname, email, phone_number_primary, phone_number_secondary FROM customers WHERE id = ?';
        $params = [
            ['type' => 's', 'value' => $id]
        ];
        $details = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $details;
    }

    function get_customer_wishlist_from_id($id)
    {
        $query = 'SELECT 
        w.id AS id,
        i.item_name AS name, 
        i.retail_price AS price,
        i.discount AS discount,
        SUM(
            CASE 
                WHEN si.packing_format = "Individual" THEN si.quantity
                WHEN si.packing_format = "Box" THEN si.quantity * i.box_size
                WHEN si.packing_format = "Pallet" THEN si.quantity * i.pallet_size
            END
        ) AS total_quantity 
        FROM 
            items AS i 
        INNER JOIN 
            wishlist AS w ON i.id = w.item_id 
        INNER JOIN 
            customers AS c ON w.customer_id = c.id
        LEFT JOIN 
            stocked_items AS si ON i.id = si.item_id
        WHERE 
            c.id = ?
        GROUP BY 
            w.id,
            i.item_name, 
            i.retail_price';
        $params = [
            ['type' => 'i', 'value' => $id]
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }

    function get_order_history($user_id)
    {
        $query = 'SELECT title, status, gross_value, VAT, total, payment_status FROM invoices WHERE customer_id = ?';
        $params = [
            ['type' => 'i', 'value' => $user_id]
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }

    function get_addresses_by_address_id($address_id)
    {
        $query = 'SELECT id,
        invoice_address_one, 
        invoice_address_two, 
        invoice_address_three,
        invoice_address_four,
        invoice_postcode, 
        delivery_address_one, 
        delivery_address_two, 
        delivery_address_three, 
        delivery_address_four, 
        delivery_postcode FROM customer_address WHERE id = ?';

        $params = [
            ['type' => 'i', 'value' => $address_id]
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }

    function get_address_from_customer_id($customer_id)
    {
        $query = 'SELECT 
            id,
            invoice_address_one, 
            invoice_address_two, 
            invoice_address_three,
            invoice_address_four,
            invoice_postcode, 
            delivery_address_one, 
            delivery_address_two, 
            delivery_address_three, 
            delivery_address_four, 
            delivery_postcode 
        FROM 
            customer_address 
        WHERE 
            customer_id = ?';
        $params = [
            ['type' => 'i', 'value' => $customer_id],
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }
    function get_customer_id_from_email($email)
    {
        $query = 'SELECT id FROM customers WHERE email = ?';
        $params = [
            ['type' => 's', 'value' => $email]
        ];
        $id = $this->db_utility->execute_query($query, $params,  'array');
        return $id;
    }

    function get_customer_type_from_email($email)
    {
        $query = 'SELECT id, customer_type FROM customers WHERE email = ?';
        $params = [
            ['type' => 's', 'value' => $email]
        ];
        return $this->db_utility->execute_query($query, $params,  'assoc-array');
    }

    function get_customer_discount($customer_id)
    {
        $query = 'SELECT discount FROM customers WHERE id = ?';
        $params = [
            ['type' => 's', 'value' => $customer_id]
        ];
        $discount = $this->db_utility->execute_query($query, $params, 'assoc-array')['discount'];
        return $discount;
    }
    function set_invoice_values($gross_value, $vat, $total, $id)
    {
        $query = 'UPDATE invoices SET gross_value = ?, VAT = ?, total = ? WHERE id = ?';
        $params = [
            ['type' => 'd', 'value' => $gross_value],
            ['type' => 'd', 'value' => $vat],
            ['type' => 'd', 'value' => $total],
            ['type' => 'i', 'value' => $id]
        ];
        $this->db_utility->execute_query($query, $params, false);
    }

    function get_new_customers()
    {
        $query = 'SELECT COUNT(*) AS count FROM customers WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())';
        $customer_count = $this->db_utility->execute_query($query, null, 'assoc-array')['count'];
        return $customer_count;
    }

    function get_total_customers()
    {
        $query = 'SELECT COUNT(*) AS count FROM customers';
        $customer_count = $this->db_utility->execute_query($query, null, 'assoc-array')['count'];
        return $customer_count;
    }

    function get_id_names()
    {
        $query = 'SELECT id, account_name AS replacement FROM customers ORDER BY replacement ASC';
        $data = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $data;
    }

    function get_id_names_codes()
    {
        $query = 'SELECT id, CONCAT(account_name, " - ", account_number) AS replacement FROM customers ORDER BY replacement ASC';
        $data = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $data;
    }

    public function get_customer_delivery_info($invoice_id)
    {
        $query = 'SELECT 
        da.delivery_address_one AS delivery_address_one,
        da.delivery_address_two AS delivery_address_two,
        da.delivery_address_three AS delivery_address_three,
        da.delivery_address_four AS delivery_address_four,
        da.delivery_postcode AS delivery_postcode,
        ba.invoice_address_one AS billing_address_one,
        ba.invoice_address_two AS billing_address_two,
        ba.invoice_address_three AS billing_address_three,
        ba.invoice_address_four AS billing_address_four,
        ba.invoice_postcode AS billing_postcode
    FROM 
        invoices i
    LEFT JOIN 
        customer_address da ON i.address_id = da.id
    LEFT JOIN 
        customer_address ba ON i.billing_address_id = ba.id
    WHERE 
        i.id = ?';
        $params = [
            ['type' => 'i', 'value' => $invoice_id]
        ];
        $customer_address_info = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $customer_address_info;
    }
}
class ItemDatabase
{
    private $db_utility;

    public function __construct($db_utility)
    {
        $this->db_utility = $db_utility;
    }

    function get_products_expiring_soon()
    {
        $query = 'SELECT expiry_warning_days FROM settings';
        $expiry_days = $this->db_utility->execute_query($query, null, 'assoc-array')['expiry_warning_days'];

        $query = 'SELECT i.item_name, si.expiry_date, si.quantity, wh.name FROM stocked_items AS si INNER JOIN items AS i ON si.item_id = i.id INNER JOIN warehouse AS wh ON si.warehouse_id = wh.id WHERE si.expiry_date < (CURDATE() + ?)';
        $params = [
            ['type' => 'i', 'value' => $expiry_days]
        ];

        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }

    function get_low_stock_items()
    {
        $query = 'SELECT i.item_name, si.quantity, wh.name FROM stocked_items AS si INNER JOIN items AS i ON si.item_id = i.id INNER JOIN warehouse AS wh ON si.warehouse_id = wh.id WHERE si.quantity < 10';
        $item_data = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $item_data;
    }

    function get_most_income_item()
    {
        $query = 'SELECT 
        item_name, 
        unit_cost, 
        retail_price, 
        wholesale_price, 
        ((retail_price - unit_cost) / retail_price) AS retail_margin, 
        ((wholesale_price - unit_cost) / wholesale_price) AS wholesale_margin, 
        total_sold, 
        total_sold * (retail_price - unit_cost) AS total_income 
      FROM 
        items 
      ORDER BY 
        total_income DESC
      LIMIT 1';

        $item_data = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $item_data;
    }

    function get_stock_from_item_id($item_id)
    {
        $query = 'SELECT
        si.id,
        items.item_name,
        si.quantity,
        si.expiry_date,
        si.packing_format,
        si.barcode,
        wh.name AS warehouse_name
      FROM 
        stocked_items AS si
      INNER JOIN
        items
      ON
        items.id = si.item_id
      INNER JOIN
        warehouse AS wh
      ON
        si.warehouse_id = wh.id
      WHERE
        si.item_id = ?';

        $params = [
            ['type' => 'i', 'value' => $item_id]
        ];

        $stock_data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $stock_data;
    }

    function get_stock_from_invoice_id($invoice_id)
    {
        $query = 'SELECT
        si.id,
        items.item_name,
        items.image_file_name,
        si.purchase_price,
        si.purchase_date,
        si.quantity,
        si.expiry_date,
        si.packing_format,
        si.barcode,
        wh.name AS warehouse_name
      FROM 
        stocked_items AS si
      INNER JOIN
        items
      ON
        items.id = si.item_id
      LEFT JOIN
        warehouse AS wh
      ON
        si.warehouse_id = wh.id
      INNER JOIN
        supplier_invoices AS sui
      ON
        si.supplier_invoice_id = sui.id
      WHERE
        si.supplier_invoice_id = ?';

        $params = [
            ['type' => 'i', 'value' => $invoice_id]
        ];

        $stock_data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $stock_data;
    }

    function get_total_stock_from_item_id($item_id)
    {
        $query = 'SELECT 
        si.item_id, 
        SUM(
            CASE 
                WHEN si.packing_format = "Individual" THEN si.quantity
                WHEN si.packing_format = "Box" THEN si.quantity * i.box_size
                WHEN si.packing_format = "Pallet" THEN si.quantity * i.pallet_size
            END
        ) AS total_quantity 
        FROM 
            stocked_items si
        JOIN 
            items i ON si.item_id = i.id
        WHERE
            si.item_id = ?
        GROUP BY 
            si.item_id';

        $params = [
            ['type' => 'i', 'value' => $item_id]
        ];

        $total_stock = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $total_stock;
    }

    function get_least_income_item()
    {
        $query = 'SELECT 
        item_name, 
        unit_cost, 
        retail_price, 
        wholesale_price, 
        ((retail_price - unit_cost) / retail_price) AS retail_margin, 
        ((wholesale_price - unit_cost) / wholesale_price) AS wholesale_margin, 
        total_sold, 
        total_sold * (retail_price - unit_cost) AS total_income 
      FROM 
        items 
      ORDER BY 
        total_income ASC
      LIMIT 1';

        $item_data = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $item_data;
    }

    function get_last_purchase_price($item_id)
    {
        $query = 'SELECT purchase_price FROM stocked_items WHERE purchase_date = (SELECT MAX(purchase_date) FROM stocked_items) AND item_id = ?';
        $params = [
            ['type' => 'i', 'value' => $item_id]
        ];

        return $this->db_utility->execute_query($query, $params, 'array'); 
    }

    function get_least_purchased_item()
    {
        $query = 'SELECT item_name, unit_cost, retail_price, wholesale_price, ((retail_price - unit_cost) / retail_price) AS retail_margin, ((wholesale_price - unit_cost) / wholesale_price) AS wholesale_margin, total_sold, total_sold * (retail_price - unit_cost) AS total_income  FROM items WHERE total_sold = ( SELECT MIN(total_sold) FROM items) LIMIT 1';

        $item_data = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $item_data;
    }

    public function categories()
    {
        $query = 'SELECT DISTINCT name FROM categories';
        return $this->db_utility->execute_query($query, null, 'array');
    }

    public function sub_categories()
    {
        $query = 'SELECT DISTINCT name FROM sub_categories';
        return $this->db_utility->execute_query($query, null, 'array');
    }

    function get_top_selling_item()
    {
        $query = 'SELECT item_name, unit_cost, retail_price, wholesale_price, ((retail_price - unit_cost) / retail_price) AS retail_margin, ((wholesale_price - unit_cost) / wholesale_price) AS wholesale_margin, total_sold, total_sold * (retail_price - unit_cost) AS total_income  FROM items WHERE total_sold = ( SELECT MAX(total_sold) FROM items) LIMIT 1';

        $item_data = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $item_data;
    }

    public function get_images_from_stocked_items()
    {
        $query = 'SELECT it.item_name AS item_id, it.image_file_name AS file_name FROM stocked_items AS si INNER JOIN items AS it ON si.item_id = it.id';
        return $this->db_utility->execute_query($query, null, 'assoc-array');
    }

    function get_total_sold($item_id)
    {
        $query = 'SELECT total_sold FROM items WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $item_id]
        ];
        $total_sold = $this->db_utility->execute_query($query, $params, 'assoc-array')['total_sold'];
        return $total_sold;
    }
    function get_calculated_total_sold($item_id)
    {
        $query = 'SELECT SUM(quantity) AS total_sold FROM invoiced_items WHERE item_id = ?';
        $params = [
            ['type' => 'i', 'value' => $item_id]
        ];
        $total_sold = $this->db_utility->execute_query($query, $params, 'assoc-array')['total_sold'];
        return $total_sold ?? 0;
    }
    function get_list_price($item_id)
    {
        $query = 'SELECT retail_price FROM items WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $item_id]
        ];
        $list_price = $this->db_utility->execute_query($query, $params, 'assoc-array')['list_price'];
        return $list_price;
    }
    function get_invoiced_item_total($invoiced_item_id)
    {
        $query = 'SELECT (ii.quantity * i.retail_price) AS invoiced_item_total FROM invoiced_items AS ii INNER JOIN items AS i ON ii.item_id = i.id WHERE ii.id = ?';
        $params = [
            ['type' => 'i', 'value' => $invoiced_item_id]
        ];
        $invoiced_item_total = $this->db_utility->execute_query($query, $params, 'assoc-array')['invoiced_item_total'];
        return $invoiced_item_total;
    }
    function get_invoice_total($invoice_id)
    {
        $query = 'SELECT SUM(invoiced_items.quantity * items.retail_price) AS total FROM invoiced_items INNER JOIN items ON item_id = items.id WHERE invoice_id = ?';
        $params = [
            ['type' => 'i', 'value' =>  $invoice_id]
        ];
        $invoice_total = $this->db_utility->execute_query($query, $params, 'assoc-array')['total'];
        return $invoice_total;
    }
    function get_invoiced_items_data($invoiced_item_id)
    {
        $query = 'SELECT item_id, quantity, settings.vat_charge, invoice_id FROM invoiced_items INNER JOIN settings WHERE invoiced_items.id = ?';
        $params = [
            ['type' => 'i', 'value' => $invoiced_item_id]
        ];
        $invoiced_item_data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $invoiced_item_data;
    }
    function set_total_sold($total_sold, $item_id)
    {
        $query = 'UPDATE items SET total_sold = ? WHERE ID = ?';
        $params = [
            ['type' => 'i', 'value' => $total_sold],
            ['type' => 'i', 'value' => $item_id]
        ];
        $this->db_utility->execute_query($query, $params, false);
    }

    function get_id_names()
    {
        $query = 'SELECT id, item_name AS replacement FROM items ORDER BY replacement ASC';
        $data = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $data;
    }

    function get_id_names_sku()
    {
        $query = 'SELECT id, CONCAT(item_name, " - ", stock_code) AS replacement FROM items ORDER BY replacement ASC';
        $data = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $data;
    }
}

class CustomerPaymentsDatabase
{
    private $db_utility;

    public function __construct($db_utility)
    {
        $this->db_utility = $db_utility;
    }
    public function get_total_invoice_payments($invoice_id)
    {
        $query = 'SELECT SUM(amount) AS total FROM customer_payments WHERE invoice_id = ?';
        $params = [
            ['type' => 'i', 'value' => $invoice_id]
        ];
        $total_payments = $this->db_utility->execute_query($query, $params, 'assoc-array')['total'];
        return $total_payments;
    }
    public function get_payment_data($payment_id)
    {
        $query = 'SELECT * FROM customer_payments WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $payment_id]
        ];
        $payment_data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $payment_data;
    }
    public function get_total_payment_data($payment_id)
    {
        $query = 'SELECT SUM(amount) AS total FROM customer_payments WHERE id = ? OR linked_payment_id = ?';
        $params = [
            ['type' => 'i', 'value' => $payment_id],
            ['type' => 'i', 'value' => $payment_id]
        ];
        return $this->db_utility->execute_query($query, $params, 'array');
    }
    public function create_excess_payment($amount, $reference, $invoice_id, $status, $payment_id)
    {
        $query = 'INSERT INTO customer_payments (`amount`, `reference`, `invoice_id`, `type`, `status`, `linked_payment_id`) VALUES (?, ?, ?, "Credit", ?, ?)';
        $params = [
            ['type' => 'd', 'value' => $amount],
            ['type' => 's', 'value' => $reference],
            ['type' => 'i', 'value' => $invoice_id],
            ['type' => 's', 'value' => $status],
            ['type' => 'i', 'value' => $payment_id]
        ];

        return $this->db_utility->execute_query($query, $params, false);
    }

    public function get_excess_payment($id)
    {
        $query = 'SELECT * FROM customer_payments WHERE linked_payment_id = ?';
        $params = [
            ['type' => 'i', 'value' => $id]
        ];

        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }

    public function remove_linked_payment($payment_id)
    {
        $query = 'DELETE FROM customer_payments WHERE linked_payment_id = ?';
        $params = [
            ['type' => 'i', 'value' => $payment_id]
        ];

        return $this->db_utility->execute_query($query, $params, false);
    }
}

class LedgerDatabase
{
    private $db_utility;

    public function __construct($db_utility)
    {
        $this->db_utility = $db_utility;
    }

    public function get_account_balance($startDate, $endDate)
    {
        $query = 'SELECT account_code, SUM(debit) AS total_debit, SUM(credit) AS total_credit, SUM(debit) - SUM(credit) AS balance FROM general_ledger WHERE date >= ? AND date <= ? GROUP BY account_code';
        $params = [
            ['type' => 's', 'value' => $startDate],
            ['type' => 's', 'value' => $endDate]
        ];
        $account_balance = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $account_balance;
    }
}

class RetailItemsDatabase
{
    private $db_utility;
    public function __construct($db_utility)
    {
        $this->db_utility = $db_utility;
    }

    public function get_items_from_category($category)
    {
        $query = 'SELECT i.item_name AS item_name, i.retail_price AS price, i.stock_code AS stock_code FROM items AS i WHERE category = ?';
        $params = [
            ['type' => 's', 'value' => $category]
        ];
        $item_data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $item_data;
    }

    public function get_offer_from_item($item_id)
    {
        $query = 'SELECT off.* FROM offers AS off INNER JOIN retail_items AS ri ON ri.offer_id = off.id WHERE ri.item_id = ?';
        $params = [
            ['type' => 'i', 'value' => $item_id]
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }

    public function set_offer_quantity_limit($item_id, $quantity)
    {
        $query = 'UPDATE offers AS off SET off.quantity_limit = ? WHERE off.id IN (SELECT ri.offer_id FROM retail_items AS ri WHERE ri.item_id = ?)';
        $params = [
            ['type' => 'i', 'value' => $quantity],
            ['type' => 'i', 'value' => $item_id]
        ];
        $this->db_utility->execute_query($query, $params, false);
    }

    public function reset_offer_from_item_id($item_id)
    {
        $query = 'UPDATE offers AS off SET off.quantity_limit = null, off.offer_start = null, off.offer_end = null, off.active = "No" WHERE off.id IN (SELECT ri.offer_id FROM retail_items AS ri WHERE ri.item_id = ?)';
        $params = [
            ['type' => 'i', 'value' => $item_id]
        ];
        $this->db_utility->execute_query($query, $params, false);
    }

    public function brands()
    {
        $query = 'SELECT DISTINCT brand FROM items WHERE brand IS NOT NULL';
        return $this->db_utility->execute_query($query, null, 'array');
    }
    public function get_visible_categories()
    {
        $query = 'SELECT name, image_file_name AS location FROM categories WHERE visible = "Yes"';
        $categories = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $categories;
    }

    public function get_subcategories()
    {
        $query = 'SELECT DISTINCT sub_category FROM items WHERE sub_category IS NOT NULL ORDER BY sub_category';
        $subcategories = $this->db_utility->execute_query($query, null, 'array');
        return $subcategories;
    }

    public function get_product_from_id($id)
    {
        $query = 'SELECT item_name AS name, retail_price, discount, image_file_name AS image_location FROM items WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $id]
        ];
        $product = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $product;
    }
    public function get_top_products($limit, $customer_type)
    {
        if ($customer_type == 'Retail') {
            $query = 'SELECT i.id, i.item_name AS name, i.retail_price AS price, off.offer_start, off.offer_end, i.discount, i.image_file_name AS image_location FROM items AS i LEFT JOIN offers AS off ON i.offer_id = off.id WHERE i.visible = "Yes" AND (visibility = "Retail" OR visibility = "Both") ORDER BY i.total_sold DESC LIMIT 0, ?';
        } else {
            $query = 'SELECT i.id, i.item_name AS name, i.wholesale_price AS price, i.box_price AS box_price, i.pallet_price AS pallet_price, off.offer_start, off.offer_end, i.discount, i.image_file_name AS image_location FROM items AS i LEFT JOIN offers AS off ON i.offer_id = off.id WHERE i.visible = "Yes" AND (visibility = "Wholesale" OR visibility = "Both") ORDER BY i.total_sold DESC LIMIT 0, ?';
        }
        $params = [
            ['type' => 'i', 'value' => $limit]
        ];
        $products = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $products;
    }

    public function get_featured($limit, $customer_type)
    {
        if ($customer_type == 'Retail') {
            $query = 'SELECT i.id, i.item_name AS name, i.retail_price AS price, off.offer_start, off.offer_end, i.discount, i.image_file_name AS image_location FROM items AS i LEFT JOIN offers AS off ON i.offer_id = off.id WHERE i.visible = "Yes" AND (visibility = "Retail" OR visibility = "Both") AND i.featured = "Yes" ORDER BY i.total_sold DESC LIMIT 0, ?';
        } else {
            $query = 'SELECT i.id, i.item_name AS name, i.wholesale_price AS price, i.box_price AS box_price, i.pallet_price AS pallet_price, off.offer_start, off.offer_end, i.discount, i.image_file_name AS image_location FROM items AS i LEFT JOIN offers AS off ON i.offer_id = off.id WHERE i.visible = "Yes" AND (visibility = "Wholesale" OR visibility = "Both") AND i.featured = "Yes" ORDER BY i.total_sold DESC LIMIT 0, ?';
        }
        $params = [
            ['type' => 'i', 'value' => $limit]
        ];
        $products = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $products;
    }

    public function get_products_from_category($category, $customer_type)
    {
        if ($customer_type == 'Retail') {
            $query = 'SELECT i.id, i.item_name AS name, i.retail_price AS price, off.offer_start, off.offer_end, i.discount, i.image_file_name AS image_location FROM items AS i LEFT JOIN offers AS off ON i.offer_id = off.id WHERE visible = "Yes" AND (visibility = "Retail" OR visibility = "Both") AND (category = ? OR sub_category = ?)';
        } else {
            $query = 'SELECT i.id, i.item_name AS name, i.wholesale_price AS price, i.box_price AS box_price, i.pallet_price AS pallet_price, off.offer_start, off.offer_end, i.discount, i.image_file_name AS image_location FROM items AS i LEFT JOIN offers AS off ON i.offer_id = off.id WHERE i.visible = "Yes" AND (visibility = "Wholesale" OR visibility = "Both") AND (category = ? OR sub_category = ?)';
        }
        $params = [
            ['type' => 's', 'value' => $category],
            ['type' => 's', 'value' => $category]
        ];
        $products = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $products;
    }

    public function get_products($customer_type)
    {
        if ($customer_type == 'Retail') {
            $query = 'SELECT i.id, i.item_name AS name, i.retail_price AS price, off.offer_start, off.offer_end, i.discount, i.image_file_name AS image_location FROM items AS i LEFT JOIN offers AS off ON i.offer_id = off.id WHERE visible = "Yes" AND (visibility = "Retail" OR visibility = "Both")';
        } else {
            $query = 'SELECT i.id, i.item_name AS name, i.wholesale_price AS price, i.box_price AS box_price, i.pallet_price AS pallet_price, off.offer_start, off.offer_end, i.discount, i.image_file_name AS image_location FROM items AS i LEFT JOIN offers AS off ON i.offer_id = off.id WHERE i.visible = "Yes" AND (visibility = "Wholesale" OR visibility = "Both")';
        }
        $product_names = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $product_names;
    }

    public function get_product_view($product_name)
    {
        $query = 'SELECT image_file_name AS primary_image, id, discount FROM items WHERE item_name = ?';
        $params = [
            ['type' => 's', 'value' => $product_name]
        ];
        $product = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $product;
    }

    public function get_product_view_images($retail_item_id)
    {
        $query = 'SELECT image_file_name FROM items WHERE id = ? UNION SELECT rii.image_file_name FROM retail_item_images AS rii INNER JOIN items AS i ON rii.item_id = i.id WHERE i.id = ?';
        $params = [
            ['type' => 'i', 'value' => $retail_item_id],
            ['type' => 'i', 'value' => $retail_item_id]
        ];
        $product_images = $this->db_utility->execute_query($query, $params, 'array');
        return $product_images;
    }

    public function get_is_product_in_wishlist($id, $product_id)
    {
        $query = 'SELECT COUNT(w.id) AS count FROM wishlist AS w INNER JOIN customers AS c ON w.customer_id = c.id WHERE c.id = ? AND w.item_id = ?';
        $params = [
            ['type' => 's', 'value' => $id],
            ['type' => 'i', 'value' => $product_id]
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array')['count'];
    }

    public function get_product_from_name($product_name, $customer_type)
    {
        if ($customer_type == 'Retail') {
            $query = 'SELECT id, category, sub_category, description, brand, discount, item_name AS name, retail_price AS price FROM items WHERE item_name = ? AND visible = "Yes" AND (visibility = "Retail" OR visibility = "Both")';
        } else {
            $query = 'SELECT id, category, sub_category, description, brand, discount, item_name AS name, wholesale_price AS price, box_price AS box_price, pallet_price AS pallet_price, box_size AS box_size, pallet_size AS pallet_size FROM items WHERE item_name = ? AND visible = "Yes" AND (visibility = "Wholesale" OR visibility = "Both")';
        }
        $params = [
            ['type' => 's', 'value' => $product_name]
        ];
        $product = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $product;
    }

    public function get_all_expired_items() 
    {
        $query = 'SELECT si.id, SUM( FROM stocked_items AS si WHERE (curdate()) > expiry_date';
        return $this->db_utility->execute_query($query, null, 'assoc-array');
    }
}

class ImageLocationsDatabase
{
    private $db_utility;
    public function __construct($db_utility)
    {
        $this->db_utility = $db_utility;
    }
    public function get_home_slideshow_images()
    {
        $query = 'SELECT image_file_name FROM image_locations WHERE page_section_id = 1 AND visible = "Yes"';
        $image_names = $this->db_utility->execute_query($query, null, 'array');
        return $image_names;
    }

    public function get_home_signup_image()
    {
        $query = 'SELECT image_file_name FROM image_locations WHERE page_section_id = 2 AND visible = "Yes"';
        $image_name = $this->db_utility->execute_query($query, null, 'array');
        return $image_name;
    }
}

class PageSectionsDatabase
{
    private $db_utility;
    public function __construct($db_utility)
    {
        $this->db_utility = $db_utility;
    }
    public function get_section_data($section_name)
    {
        $query = "SELECT pst.*, il.*, ps.name FROM page_section_text AS pst INNER JOIN page_sections AS ps ON pst.page_section_id = ps.id INNER JOIN image_locations AS il ON il.page_section_id = ps.id  WHERE ps.name = ? AND il.visible = 'Yes'";
        $params = [
            ['type' => 's', 'value' => $section_name]
        ];
        $section_data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $section_data;
    }

    public function get_section_image($section_name)
    {
        $query = "SELECT il.image_file_name AS image FROM image_locations AS il INNER JOIN page_sections AS ps ON il.page_section_id = ps.id WHERE il.visible = 'Yes' AND ps.name = ?";
        $params = [
            ['type' => 's', 'value' => $section_name]
        ];
        $section_image = $this->db_utility->execute_query($query, $params, 'array');
        return $section_image;
    }
}

class InvoiceDatabase
{
    private $db_utility;

    public function __construct($db_utility)
    {
        $this->db_utility = $db_utility;
    }

    public function get_invoice($id)
    {
        $query = 'SELECT * FROM invoices WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $id]
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }

    public function get_total_payments($id)
    {
        $query = 'SELECT SUM(amount) FROM customer_payments WHERE invoice_id = ?';
        $params = [
            ['type' => 'i', 'value' => $id]
        ];
        return $this->db_utility->execute_query($query, $params, 'array');
    }

    public function get_addresses($id)
    {
        $query = 'SELECT a.* FROM customer_address AS a INNER JOIN invoices AS i ON i.address_id = a.id WHERE i.id = ?';
        $params = [
            ['type' => 'i', 'value' => $id]
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }

    public function get_invoice_id_from_invoiced_item_id($invoiced_item_id)
    {
        $query = 'SELECT invoice_id FROM invoiced_items WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $invoiced_item_id]
        ];
        return $this->db_utility->execute_query($query, $params, 'array');
    }

    public function calculate_invoice_totals($id)
    {
        $query = 'SELECT
        SUM(
            invoiced_items.quantity *
            CASE
                WHEN price_list.price IS NOT NULL THEN price_list.price
                WHEN customers.customer_type = "Retail" THEN items.retail_price
                WHEN customers.customer_type = "Wholesale" THEN items.wholesale_price
            END *
            (1 - invoiced_items.discount / 100) *
            (1 - customers.discount / 100)
        ) AS net,
        SUM(
            invoiced_items.quantity *
            CASE
                WHEN price_list.price IS NOT NULL THEN price_list.price
                WHEN customers.customer_type = "Retail" THEN items.retail_price
                WHEN customers.customer_type = "Wholesale" THEN items.wholesale_price
            END
        ) AS gross
    FROM
        invoiced_items
    JOIN
        items ON invoiced_items.item_id = items.id
    JOIN
        invoices ON invoiced_items.invoice_id = invoices.id
    JOIN
        customers ON invoices.customer_id = customers.id
    LEFT JOIN
        price_list ON price_list.customer_id = customers.id AND price_list.item_id = items.id
    WHERE
        invoices.id = ?';
    

        $params = [
            ['type' => 'i', 'value' => $id]
        ];

        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }

    public function update_invoice_value($invoice_id, $net_total, $gross_value)
    {
        $VAT = $net_total * 0.2;
        $net = $net_total + $VAT;

        $query = 'UPDATE invoices
        SET gross_value = ?,
        VAT = ?,
        total = ?
        WHERE id = ?';

        $params = [
            ['type' => 'd', 'value' => $gross_value ?? 0],
            ['type' => 'd', 'value' => $VAT ?? 0],
            ['type' => 'd', 'value' => $net ?? 0],
            ['type' => 'i', 'value' => $invoice_id],
        ];

        return $this->db_utility->execute_query($query, $params, false);
    }

    public function set_outstanding_balance($id, $outstanding_balance)
    {
        $query = 'UPDATE invoices SET outstanding_balance = ? WHERE id = ?';
        $params = [
            ['type' => 'd', 'value' => $outstanding_balance],
            ['type' => 'i', 'value' => $id]
        ];
        return $this->db_utility->execute_query($query, $params, false);
    }

    public function update_stock_from_invoiced_item_id($id)
    {
        $query = '';
    }

    public function get_invoice_id_titles()
    {
        $query = 'SELECT id, title AS replacement FROM invoices ORDER BY replacement ASC';
        return $this->db_utility->execute_query($query, null, 'assoc-array');
    }

    public function get_delivery_date_from_id($invoice_id)
    {
        $query = 'SELECT delivery_date FROM invoices WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $invoice_id]
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array')['delivery_date'];
    }

    public function get_invoices_due_today_basic()
    {
        $query = 'SELECT i.id, c.account_name, i.printed, i.payment_status FROM invoices AS i INNER JOIN customers AS c ON i.customer_id = c.id WHERE i.delivery_date = (CURDATE())';
        return $this->db_utility->execute_query($query, null, 'assoc-array');
    }

    public function get_invoice_info($invoice_id)
    {
        $query = 'SELECT
        invoices.title,
        invoices.gross_value,
        invoices.total,
        invoices.vat,
        invoices.delivery_date,
        invoices.created_at,
        invoices.warehouse_id,
        customers.forename,
        customers.surname,
        customers.outstanding_balance,
        customers.discount
      FROM
        invoices
        INNER JOIN customers ON invoices.customer_id = customers.id
      WHERE
        invoices.id = ?';

        $params = [
            ['type' => 'i', 'value' => $invoice_id],
        ];

        $invoice_data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $invoice_data;
    }

    public function get_warehouse_id($invoice_id)
    {
        $query = 'SELECT warehouse_id FROM invoices WHERE id = ?';

        $params = [
            ['type' => 'i', 'value' => $invoice_id]
        ];

        $warehouse_id = $this->db_utility->execute_query($query, $params, 'assoc-array')['warehouse_id'];
        return $warehouse_id;
    }

    public function get_invoiced_items_from_id($invoice_id, $complex)
    {
        if ($complex)
        {
            $query = 'SELECT 
                ii.id AS id,
                it.item_name AS name,
                it.image_file_name AS image_file_name,
                ii.quantity AS quantity, 
                settings.vat_charge AS vat,
                ii.discount AS discount,
                CASE
                    WHEN cs.customer_type = "Retail" THEN it.retail_price
                    WHEN cs.customer_type = "Wholesale" THEN it.wholesale_price
                END AS price,
                CASE
                    WHEN cs.customer_type = "Retail" THEN it.retail_price * ii.quantity
                    WHEN cs.customer_type = "Wholesale" THEN it.wholesale_price * ii.quantity
                END AS total
            FROM 
                invoiced_items AS ii 
            INNER JOIN items AS it ON ii.item_id = it.id 
            INNER JOIN invoices AS inv ON ii.invoice_id = inv.id
            INNER JOIN customers AS cs ON inv.customer_id = cs.id
            INNER JOIN settings
            WHERE 
                inv.id = ?';
        }
        else
        {
            $query = 'SELECT 
                ii.id AS id,
                it.item_name AS name,
                it.image_file_name AS image_file_name,
                ii.quantity AS quantity, 
                settings.vat_charge AS vat,
                ii.discount AS discount
            FROM 
                invoiced_items AS ii 
                INNER JOIN items AS it ON ii.item_id = it.id 
                INNER JOIN invoices AS inv ON ii.invoice_id = inv.id 
                INNER JOIN settings
            WHERE 
                inv.id = ?';
        }

        $params = [
            ['type' => 'i', 'value' => $invoice_id],
        ];

        $data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $data;
    }

    public function set_invoice_to_printed($invoice_id)
    {
        $query = 'UPDATE invoices SET printed = "Yes" WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $invoice_id]
        ];
        $this->db_utility->execute_query($query, $params, false);
    }

    public function get_basic_invoiced_item_from_ids($invoice_ids)
    {
        $invoice_ids_array = explode(',', $invoice_ids);

        $placeholders = rtrim(str_repeat('?, ', count($invoice_ids_array)), ', ');

        $query = 'SELECT 
                    ii.quantity AS quantity, 
                    it.item_name AS name,
                    inv.id AS title 
                FROM 
                    invoiced_items AS ii 
                INNER JOIN 
                    items AS it ON ii.item_id = it.id 
                INNER JOIN 
                    invoices AS inv ON ii.invoice_id = inv.id 
                WHERE
                    inv.id IN (' . $placeholders . ')';

        $params = [];
        foreach ($invoice_ids_array as $id) {
            $params[] = ['type' => 's', 'value' => $id];
        }

        $data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $data;
    }

    public function get_invoice_products($invoice_id)
    {
        $query = 'SELECT
        items.item_name,
        items.image_file_name AS file_name,
        CASE
            WHEN customers.customer_type = "Retail" THEN items.retail_price
            WHEN customers.customer_type = "Wholesale" THEN items.wholesale_price
        END AS price,
        invoiced_items.quantity,
        invoiced_items.discount,
        settings.vat_charge AS vat_charge
    FROM
        invoices
    INNER JOIN invoiced_items ON invoices.id = invoiced_items.invoice_id
    INNER JOIN items ON invoiced_items.item_id = items.id
    INNER JOIN settings ON settings.id = 1
    INNER JOIN customers ON invoices.customer_id = customers.id
    WHERE
        invoices.id = ?';

        $params = [
            ['type' => 'i', 'value' => $invoice_id],
        ];

        $invoice_products = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $invoice_products;
    }

    public function get_total_invoices_month_profit($month, $year)
    {
        $query = 'SELECT 
        COUNT(*) AS total_invoices,
        SUM(total) AS month_total,
        (
            SELECT 
                COALESCE(SUM(total), 0) 
            FROM invoices 
            WHERE payment_status = "Yes" 
                AND MONTH(created_at) = ? 
                AND YEAR(created_at) = ?
        ) - (
            SELECT 
                COALESCE(SUM(ii.quantity * items.unit_cost), 0) AS total_cost 
            FROM invoiced_items AS ii 
            JOIN items ON ii.item_id = items.id 
            JOIN invoices AS i ON ii.invoice_id = i.id  
            WHERE MONTH(i.created_at) = ? 
                AND YEAR(i.created_at) = ?
        ) AS invoice_profit
    FROM invoices 
    WHERE payment_status = "Yes" 
        AND MONTH(created_at) = ?
        AND YEAR(created_at) = ?';
        $params = [
            ['type' => 'i', 'value' => $month],
            ['type' => 'i', 'value' => $year],
            ['type' => 'i', 'value' => $month],
            ['type' => 'i', 'value' => $year],
            ['type' => 'i', 'value' => $month],
            ['type' => 'i', 'value' => $year]
        ];
        $invoice_data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $invoice_data;
    }

    public function get_item_month_totals($month, $year)
    {
        $query = 'SELECT items.item_name, SUM(invoiced_items.quantity) AS total_quantity FROM items JOIN invoiced_items ON items.id = invoiced_items.item_id JOIN invoices ON invoices.id = invoiced_items.invoice_id WHERE MONTH(invoices.created_at) = ? AND YEAR(invoices.created_at) = ? GROUP BY items.id, items.item_name';
        $params = [
            ['type' => 'i', 'value' => $month],
            ['type' => 'i', 'value' => $year]
        ];
        $item_data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $item_data;
    }

    public function get_month_totals($year)
    {
        $query = 'SELECT YEAR(created_at) AS year, MONTH(created_at) AS month, SUM(total) AS total_amount FROM invoices WHERE YEAR(created_at) = ? GROUP BY YEAR(created_at), MONTH(created_at)';
        $params = [
            ['type' => 'i', 'value' => $year]
        ];
        $invoice_data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $invoice_data;
    }

    public function get_invoices_due($age)
    {
        $query = 'SELECT id, title FROM invoices WHERE delivery_date >= DATE_SUB(NOW(), INTERVAL ? DAY)';
        $params = [
            ['type' => 'i', 'value' => $age]
        ];
        $invoice_data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $invoice_data;
    }

    public function get_debtor_data($startDay, $endDay)
    {
        if ($endDay == "null") {
            $query = 'SELECT i.title, c.account_name, SUM(i.total) AS total_amount, i.created_at FROM invoices AS i INNER JOIN customers AS c ON i.customer_id = c.id WHERE i.created_at <= curdate() - INTERVAL ? DAY AND i.payment_status = "No" GROUP BY i.title, c.account_name, i.created_at';
            $params = [
                ['type' => 's', 'value' => $startDay]
            ];
        } else {
            $query = 'SELECT i.title, c.account_name, SUM(i.total) AS total_amount, i.created_at FROM invoices AS i INNER JOIN customers AS c ON i.customer_id = c.id WHERE i.created_at >= curdate() - INTERVAL ? DAY AND i.created_at < (CURDATE()) - INTERVAL ? DAY AND i.payment_status = "No" GROUP BY i.title, c.account_name, i.created_at';
            $params = [
                ['type' => 's', 'value' => $endDay],
                ['type' => 's', 'value' => $startDay]
            ];
        }
        $debtor_data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $debtor_data;
    }
    public function get_creditor_data($startDay, $endDay)
    {
        if ($endDay == "null") {
            $query = 'SELECT i.reference, s.account_name, SUM(i.total) AS total_amount, i.created_at FROM supplier_invoices AS i INNER JOIN suppliers AS s ON i.supplier_id = s.id WHERE i.created_at <= curdate() - INTERVAL ? DAY AND i.payment_status = "No" GROUP BY i.reference, s.account_name, i.created_at';
            $params = [
                ['type' => 's', 'value' => $startDay]
            ];
        } else {
            $query = 'SELECT i.reference, s.account_name, SUM(i.total) AS total_amount, i.created_at FROM supplier_invoices AS i INNER JOIN suppliers AS s ON i.supplier_id = s.id WHERE i.created_at >= curdate() - INTERVAL ? DAY AND i.created_at < (CURDATE()) - INTERVAL ? DAY AND i.payment_status = "No" GROUP BY i.reference, s.account_name, i.created_at';
            $params = [
                ['type' => 's', 'value' => $endDay],
                ['type' => 's', 'value' => $startDay]
            ];
        }
        $creditor_data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $creditor_data;
    }

    public function get_total_invoices_month()
    {
        $query = 'SELECT COUNT(*) AS count FROM invoices WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())';
        $invoice_count = $this->db_utility->execute_query($query, null, 'assoc-array')['count'];
        return $invoice_count;
    }

    public function get_total_invoices_per_month($monthStart, $monthEnd, $year)
    {
        $query = 'SELECT EXTRACT(MONTH FROM created_at) AS dateKey, COUNT(*) AS total FROM invoices WHERE EXTRACT(YEAR FROM created_at) = ? AND EXTRACT(MONTH FROM created_at) BETWEEN ? AND ? GROUP BY dateKey ORDER BY dateKey';
        $params = [
            ['type' => 'i', 'value' => $year],
            ['type' => 'i', 'value' => $monthStart],
            ['type' => 'i', 'value' => $monthEnd]
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }

    public function get_total_invoices_per_day($dayStart, $dayEnd, $month, $year)
    {
        $query = 'SELECT EXTRACT(DAY FROM created_at) AS dateKey, COUNT(*) AS total FROM invoices WHERE EXTRACT(MONTH FROM created_at) = ? AND EXTRACT(YEAR FROM created_at) = ? AND DAY(created_at) BETWEEN ? AND ? GROUP BY dateKey ORDER BY dateKey';
        $params = [
            ['type' => 'i', 'value' => $month],
            ['type' => 'i', 'value' => $year],
            ['type' => 'i', 'value' => $dayStart],
            ['type' => 'i', 'value' => $dayEnd]
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }

    public function get_total_invoice_value_per_month($monthStart, $monthEnd, $year)
    {
        $query = 'SELECT EXTRACT(MONTH FROM created_at) AS dateKey, SUM(total) AS total FROM invoices WHERE EXTRACT(YEAR FROM created_at) = ? AND EXTRACT(MONTH FROM created_at) BETWEEN ? AND ? GROUP BY dateKey ORDER BY dateKey';
        $params = [
            ['type' => 'i', 'value' => $year],
            ['type' => 'i', 'value' => $monthStart],
            ['type' => 'i', 'value' => $monthEnd]
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }

    public function get_total_invoice_value_per_day($dayStart, $dayEnd, $month, $year)
    {
        $query = 'SELECT EXTRACT(DAY FROM created_at) AS dateKey, SUM(total) AS total FROM invoices WHERE EXTRACT(MONTH FROM created_at) = ? AND EXTRACT(YEAR FROM created_at) = ? AND DAY(created_at) BETWEEN ? AND ? GROUP BY dateKey ORDER BY dateKey';
        $params = [
            ['type' => 'i', 'value' => $month],
            ['type' => 'i', 'value' => $year],
            ['type' => 'i', 'value' => $dayStart],
            ['type' => 'i', 'value' => $dayEnd]
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }

    public function get_average_invoice_value_per_month($monthStart, $monthEnd, $year)
    {
        $query = 'SELECT EXTRACT(MONTH FROM created_at) AS dateKey, SUM(total) / COUNT(*) AS total FROM invoices WHERE EXTRACT(YEAR FROM created_at) = ? AND EXTRACT(MONTH FROM created_at) BETWEEN ? AND ? GROUP BY dateKey ORDER BY dateKey';
        $params = [
            ['type' => 'i', 'value' => $year],
            ['type' => 'i', 'value' => $monthStart],
            ['type' => 'i', 'value' => $monthEnd]
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }

    public function get_vat_data($startDate, $endDate)
    {
        $query = 'SELECT 
        COALESCE(SUM(output_total), 0) AS output_total, 
        COALESCE(SUM(output_vat), 0) AS output_vat, 
        COALESCE(SUM(input_total), 0) AS input_total, 
        COALESCE(SUM(input_vat), 0) AS input_vat
    FROM (
        SELECT 
            COALESCE(SUM(total), 0) AS output_total, 
            COALESCE(SUM(vat), 0) AS output_vat, 
            0 AS input_total, 
            0 AS input_vat 
        FROM 
            invoices 
        WHERE 
            created_at >= ? 
            AND created_at <= ? 
            AND payment_status = "Yes"
        UNION ALL
        SELECT 
            0 AS output_total, 
            0 AS output_vat, 
            COALESCE(SUM(total), 0) AS input_total, 
            COALESCE(SUM(vat), 0) AS input_vat 
        FROM 
            payments 
        WHERE 
            date >= ? 
            AND date <= ?
    ) AS combined_data';
        $params = [
            ['type' => 's', 'value' => $startDate],
            ['type' => 's', 'value' => $endDate],
            ['type' => 's', 'value' => $startDate],
            ['type' => 's', 'value' => $endDate],
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }

    public function get_average_invoice_value_per_day($dayStart, $dayEnd, $month, $year)
    {
        $query = 'SELECT EXTRACT(DAY FROM created_at) AS dateKey, SUM(total) / COUNT(*) AS total FROM invoices WHERE EXTRACT(MONTH FROM created_at) = ? AND EXTRACT(YEAR FROM created_at) = ? AND DAY(created_at) BETWEEN ? AND ? GROUP BY dateKey ORDER BY dateKey';
        $params = [
            ['type' => 'i', 'value' => $month],
            ['type' => 'i', 'value' => $year],
            ['type' => 'i', 'value' => $dayStart],
            ['type' => 'i', 'value' => $dayEnd]
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }

    public function get_top_selling_item_per_month($monthStart, $monthEnd, $year)
    {
        $query = 'SELECT i.item_name AS dateKey, SUM(ii.quantity) AS total FROM invoiced_items AS ii JOIN items i ON ii.item_id = i.id WHERE EXTRACT(YEAR FROM ii.created_at) = ? AND EXTRACT(MONTH FROM ii.created_at) BETWEEN ? AND ? GROUP BY i.id ORDER BY total DESC LIMIT 5';
        $params = [
            ['type' => 'i', 'value' => $year],
            ['type' => 'i', 'value' => $monthStart],
            ['type' => 'i', 'value' => $monthEnd]
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }

    public function get_top_selling_item_per_day($dayStart, $dayEnd, $month, $year)
    {
        $query = 'SELECT i.item_name AS dateKey, SUM(ii.quantity) AS total FROM invoiced_items AS ii JOIN items i ON ii.item_id = i.id WHERE EXTRACT(MONTH FROM ii.created_at) = ? AND EXTRACT(YEAR FROM ii.created_at) = ? AND DAY(ii.created_at) BETWEEN ? AND ? GROUP BY i.id ORDER BY total DESC LIMIT 5';
        $params = [
            ['type' => 'i', 'value' => $month],
            ['type' => 'i', 'value' => $year],
            ['type' => 'i', 'value' => $dayStart],
            ['type' => 'i', 'value' => $dayEnd]
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }

    public function get_recurring_customers_day($dayStart, $dayEnd, $month, $year)
    {
        $query = 'SELECT * FROM invoices WHERE customer_id IN (SELECT customer_id FROM invoices GROUP BY customer_id HAVING COUNT(*) > 1) WHERE EXTRACT(MONTH FROM ii.created_at) = ? AND EXTRACT(YEAR FROM ii.created_at) = ? AND DAY(ii.created_at) BETWEEN ? AND ? GROUP BY i.id ORDER BY total DESC LIMIT 5';
        $params = [
            ['type' => 'i', 'value' => $month],
            ['type' => 'i', 'value' => $year],
            ['type' => 'i', 'value' => $dayStart],
            ['type' => 'i', 'value' => $dayEnd]
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }

    public function get_recurring_customers_month($monthStart, $monthEnd, $year)
    {
        $query = 'SELECT * FROM invoices WHERE customer_id IN (SELECT customer_id FROM invoices GROUP BY customer_id HAVING COUNT(*) > 1) WHERE EXTRACT(YEAR FROM ii.created_at) = ? AND EXTRACT(MONTH FROM ii.created_at) BETWEEN ? AND ? GROUP BY i.id ORDER BY total DESC LIMIT 5';
        $params = [
            ['type' => 'i', 'value' => $year],
            ['type' => 'i', 'value' => $monthStart],
            ['type' => 'i', 'value' => $monthEnd]
        ];
        return $this->db_utility->execute_query($query, $params, 'assoc-array');
    }

    public function get_invoices_due_today()
    {
        $query = 'SELECT COUNT(*) AS count FROM invoices WHERE delivery_date = CURDATE()';
        $invoice_count = $this->db_utility->execute_query($query, null, 'assoc-array')['count'];
        return $invoice_count;
    }

    public function get_customer_id($invoice_id)
    {
        $query = 'SELECT customer_id FROM invoices WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $invoice_id]
        ];
        $customer_id = $this->db_utility->execute_query($query, $params, 'assoc-array')['customer_id'];
        return $customer_id;
    }
    public function get_invoice_price_data($invoice_id)
    {
        $query = 'SELECT gross_value, VAT, total FROM invoices WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $invoice_id]
        ];
        $price_data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $price_data;
    }
    public function get_customer_debt($customer_id)
    {
        $query = 'SELECT (SELECT SUM(total) FROM invoices WHERE customer_id = ?) - COALESCE((SELECT SUM(amount) FROM customer_payments WHERE customer_id = ? AND status = \'Processed\' AND invoice_id IS NOT NULL), 0) AS total';
        $params = [
            ['type' => 'i', 'value' => $customer_id],
            ['type' => 'i', 'value' => $customer_id]
        ];
        $customer_debt = $this->db_utility->execute_query($query, $params, 'assoc-array')['total'];
        return $customer_debt;
    }
    public function set_invoice_payment_status($invoice_id, $status)
    {
        if ($status == 'Yes' || $status == 'No') {
            $query = 'UPDATE invoices SET payment_status = ? WHERE id = ?';
            $params = [
                ['type' => 's', 'value' => $status],
                ['type' => 'i', 'value' => $invoice_id]
            ];
            return $this->db_utility->execute_query($query, $params, false);
        }
    }

    public function set_invoice_status($invoice_id, $status)
    {
        if ($status == 'Yes' || $status == 'No') {
            $query = 'UPDATE invoices SET payment_status = ? WHERE id = ?';
            $params = [
                ['type' => 's', 'value' => $status],
                ['type' => 'i', 'value' => $invoice_id]
            ];
            return $this->db_utility->execute_query($query, $params, false);
        }
    }

    public function get_total($invoice_id)
    {
        $query = 'SELECT outstanding_balance FROM invoices WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $invoice_id]
        ];
        return $this->db_utility->execute_query($query, $params, 'array');
    }

    public function update_outstanding_balance($new_balance, $invoice_id)
    {
        $query = 'UPDATE invoices SET outstanding_balance = ? WHERE id = ?';
        $params = [
            ['type' => 'd', 'value' => $new_balance],
            ['type' => 'i', 'value' => $invoice_id]
        ];
        return $this->db_utility->execute_query($query, $params, false);
    }
}
