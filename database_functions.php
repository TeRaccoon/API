<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET,POST,PUT,DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

class UserDatabase {
    private $db_utility;

    public function __construct($db_utility) {
        $this->db_utility = $db_utility;
    }

    function get_user_password($username) {
        $query = 'SELECT password FROM users WHERE username = ?';
        $params = [
            ['type' => 's', 'value' => $username]
        ];
        $password_hash = $this->db_utility->execute_query($query, $params, 'assoc-array')['password'];
        return $password_hash;
    }

    function get_access_level($username) {
        $query = 'SELECT level FROM users WHERE username = ?';
        $params = [
            ['type' => 's', 'value' => $username]
        ];
        $access_level = $this->db_utility->execute_query($query, $params, 'assoc-array')['level'];
        return $access_level;
    }

    function user_exists($username) {
        $query = 'SELECT username FROM users WHERE username = ?';
        $params = [
            ['type' => 's', 'value' => $username]
        ];
        $row_count = $this->db_utility->execute_query($query, $params, 'row-count');
        return $row_count;
    }
}

class AllDatabases {
    private $db_utility;
    public function __construct($db_utility) {
        $this->db_utility = $db_utility;
    }
    function get_table_data($table_name) {
        $query = 'SELECT * FROM '.$table_name;
        $data = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $data;
    }

    function get_columns($table_name) {
        $query = 'SHOW FULL COLUMNS FROM '.$table_name;
        $data = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $data;
    }

    function get_associative_data($table_name) {
        $query = 'SELECT TABLE_NAME, REFERENCED_TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_NAME IS NOT NULL AND TABLE_SCHEMA = "hellenic" AND TABLE_NAME = "'.$table_name.'"';
        $data = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $data;
    }

    function get_customers_name() {
        $query = 'SELECT `id`, CONCAT(`forename`, " ", `surname`) AS full_name FROM `customers`';
        $names = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $this->format_data("full_name", $names);
    }

    function get_invoice_titles() {
        $query = 'SELECT id, title FROM invoices';
        $titles = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $this->format_data('title', $titles);
    }

    function format_data($key, $incoming_data) {
        $data = [];
        foreach ($incoming_data as $row) {
            $data[$row["id"]] = $row[$key];
        }
        return $data;
    }
}

class CustomerDatabase {
    private $db_utility;

    public function __construct($db_utility) {
        $this->db_utility = $db_utility;
    }

    function get_customer_password($id) {
        $query = 'SELECT password FROM customers WHERE id = ?';
        $params = [
            ['type' => 's', 'value' => $id]
        ];
        $password_hash = $this->db_utility->execute_query($query, $params, 'assoc-array')['password'];
        return $password_hash;
    }

    function get_customer_details($id) {
        $query = 'SELECT forename, surname, email, phone_number_primary, phone_number_secondary FROM customers WHERE id = ?';
        $params = [
            ['type' => 's', 'value' => $id]
        ];
        $details = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $details;
    }

    function get_customer_id_from_email($email) {
        $query = 'SELECT id FROM customers WHERE email = ?';
        $params = [
            ['type' => 's', 'value' => $email]
        ];
        $id = $this->db_utility->execute_query($query, $params,  'assoc-array')['id'];
        return $id;
    }

    function get_customer_discount($customer_id) {
        $query = 'SELECT discount FROM customers WHERE id = ?';
        $params = [
            ['type' => 's', 'value' => $customer_id]
        ];
        $discount = $this->db_utility->execute_query($query, $params, 'assoc-array')['discount'];
        return $discount;
    }
    function set_invoice_values($net_value, $vat, $total, $id) {
        $query = 'UPDATE invoices SET net_value = ?, VAT = ?, total = ? WHERE id = ?';
        $params = [
            ['type' => 'd', 'value' => $net_value],
            ['type' => 'd', 'value' => $vat],
            ['type' => 'd', 'value' => $total],
            ['type' => 'i', 'value' => $id]
        ];
        $this->db_utility->execute_query($query, $params, false);
    }

    function get_new_customers() {
        $query = 'SELECT COUNT(*) AS count FROM customers WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())';
        $customer_count = $this->db_utility->execute_query($query, null, 'assoc-array')['count'];
        return $customer_count;
    }

    function get_total_customers() {
        $query = 'SELECT COUNT(*) AS count FROM customers';
        $customer_count = $this->db_utility->execute_query($query, null, 'assoc-array')['count'];
        return $customer_count;
    }
}
class ItemDatabase {
    private $conn;
    private $db_utility;

    public function __construct($conn, $db_utility) {
        $this->conn = $conn;
        $this->db_utility = $db_utility;
    }

    function get_total_sold($item_id) {
        $query = 'SELECT total_sold FROM items WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $item_id]
        ];
        $total_sold = $this->db_utility->execute_query($query, $params, 'assoc-array')['total_sold'];
        return $total_sold;
    }
    function get_calculated_total_sold($item_id) {
        $query = 'SELECT SUM(quantity) AS total_sold FROM invoiced_items WHERE item_id = ?';
        $params = [
            ['type' => 'i', 'value' => $item_id]
        ];
        $total_sold = $this->db_utility->execute_query($query, $params, 'assoc-array')['total_sold'];
        return $total_sold ?? 0;
    }
    function get_list_price($item_id) {
        $query = 'SELECT list_price FROM items WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $item_id]
        ];
        $list_price = $this->db_utility->execute_query($query, $params, 'assoc-array')['list_price'];
        return $list_price;
    }
    function get_invoiced_item_total($invoiced_item_id) {
        $query = 'SELECT (ii.quantity * i.list_price) AS invoiced_item_total FROM invoiced_items AS ii INNER JOIN items AS i ON ii.item_id = i.id WHERE ii.id = ?';
        $params = [
            ['type' => 'i', 'value' => $invoiced_item_id]
        ];
        $invoiced_item_total = $this->db_utility->execute_query($query, $params, 'assoc-array')['invoiced_item_total'];
        return $invoiced_item_total;
    }
    function get_invoice_total($invoice_id) {
        $query = 'SELECT SUM(invoiced_items.quantity * items.list_price) AS total FROM invoiced_items INNER JOIN items ON item_id = items.id WHERE invoice_id = ?';
        $params = [
            ['type' => 'i', 'value' =>  $invoice_id]
        ];
        $invoice_total = $this->db_utility->execute_query($query, $params, 'assoc-array')['total'];
        return $invoice_total;
    }
    function get_invoiced_items_data($invoiced_item_id) {
        $query = 'SELECT item_id, quantity, vat_charge, invoice_id FROM invoiced_items WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $invoiced_item_id]
        ];
        $invoiced_item_data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $invoiced_item_data;
    }
    function set_total_sold($total_sold, $item_id) {
        $query = 'UPDATE items SET total_sold = ? WHERE ID = ?';
        $params = [
            ['type' => 'i', 'value' => $total_sold],
            ['type' => 'i', 'value' => $item_id]
        ];
        $this->db_utility->execute_query($query, $params, false);
    }
}

class CustomerPaymentsDatabase {
    private $conn;
    private $db_utility;

    public function __construct($conn, $db_utility) {
        $this->conn = $conn;
        $this->db_utility = $db_utility;
    }
    public function get_total_invoice_payments($invoice_id) {
        $query = 'SELECT SUM(amount) AS total FROM customer_payments WHERE invoice_id = ?';
        $params = [
            ['type' => 'i', 'value' => $invoice_id]
        ];
        $total_payments = $this->db_utility->execute_query($query, $params, 'assoc-array')['total'];
        return $total_payments;
    }
    public function get_payment_data($payment_id) {
        $query = 'SELECT customer_id, amount, invoice_id, status, type, date FROM customer_payments WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $payment_id]
        ];
        $payment_data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $payment_data;
    }
}

class LedgerDatabase {
    private $db_utility;

    public function __construct($db_utility) {
        $this->db_utility = $db_utility;
    }

    public function get_account_balance($startDate, $endDate) {
        $query = 'SELECT account_code, SUM(debit) AS total_debit, SUM(credit) AS total_credit, SUM(debit) - SUM(credit) AS balance FROM general_ledger WHERE date >= ? AND date <= ? GROUP BY account_code';
        $params = [
            ['type' => 's', 'value' => $startDate],
            ['type' => 's', 'value' => $endDate]
        ];
        $account_balance = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $account_balance;
    }
}

class RetailItemsDatabase {
    private $db_utility;
    public function __construct($db_utility) {
        $this->db_utility = $db_utility;
    }
    
    public function get_items_from_category($category) {
        $query = 'SELECT ri.*, i.item_name AS item_name, i.retail_price AS price, i.stock_code AS stock_code FROM retail_items AS ri INNER JOIN items AS i ON ri.item_id = i.id WHERE category = ?';
        $params = [
            ['type' => 's', 'value' => $category]
        ];
        $item_data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $item_data;
    }
    public function get_categories() {
        $query = 'SELECT DISTINCT category FROM retail_items ORDER BY category';
        $categories = $this->db_utility->execute_query($query, null, 'array');
        return $categories;
    }

    public function get_subcategories() {
        $query = 'SELECT DISTINCT sub_category FROM retail_items WHERE sub_category IS NOT NULL ORDER BY sub_category';
        $subcategories = $this->db_utility->execute_query($query, null, 'array');
        return $subcategories;
    }


    public function get_top_products($limit) {
        $query = 'SELECT i.item_name AS item_name, i.retail_price AS price, ri.offer_start, ri.offer_end, ri.discount, ri.image_file_name AS image_location FROM retail_items AS ri INNER JOIN items AS i ON ri.item_id = i.id ORDER BY i.total_sold DESC LIMIT 0, ?';
        $params = [
            ['type' => 'i', 'value' => $limit]
        ];
        $products = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $products;
    }

    public function get_featured($limit) {
        $query = "SELECT i.item_name AS item_name, i.stock_code AS stock_code, ri.image_file_name, ri.discount, i.retail_price AS price FROM retail_items AS ri INNER JOIN items AS i ON ri.item_id = i.id WHERE featured = 'Yes' AND visible = 'Yes' LIMIT 0, ?";
        $params = [
            ['type' => 'i', 'value' => $limit]
        ];
        $products = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $products;
    }

    public function get_products_from_category($category) {
        $query = 'SELECT ri.image_file_name AS image_location, ri.brand, ri.discount, i.item_name AS name, i.retail_price AS price FROM retail_items AS ri INNER JOIN items AS i ON ri.item_id = i.id WHERE ri.category = ? OR ri.sub_category = ?';
        $params = [
            ['type' => 's', 'value' => $category],
            ['type' => 's', 'value' => $category]
        ];
        $products = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $products;
    }

    public function get_products() {
        $query = 'SELECT ri.image_file_name AS image_location, ri.brand, ri.discount, ri.category, i.item_name AS name, i.retail_price AS price FROM retail_items AS ri INNER JOIN items AS i ON ri.item_id = i.id';
        $product_names = $this->db_utility->execute_query($query, null, 'assoc-array');
        return $product_names;
    }

    public function get_product_view($product_name) {
        $query = 'SELECT ri.image_file_name AS primary_image, ri.id, ri.discount FROM retail_items as ri INNER JOIN items AS i ON ri.item_id = i.id  WHERE i.item_name = ?';
        $params = [
            ['type' => 's', 'value'=> $product_name]
        ];
        $product = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $product;
    }

    public function get_product_view_images($retail_item_id) {
        $query = 'SELECT ri.image_file_name AS image
        FROM retail_item_images AS rii
        INNER JOIN retail_items AS ri ON rii.retail_item_id = ri.id
        WHERE rii.retail_item_id = ?
        UNION
        SELECT rii.image_file_name AS image
        FROM retail_item_images AS rii
        WHERE rii.retail_item_id = ?';
        $params = [
            ['type' => 'i', 'value' => $retail_item_id],
            ['type' => 'i', 'value' => $retail_item_id]
        ];
        $product_images = $this->db_utility->execute_query($query, $params, 'array');
        return $product_images;
    }

    public function get_product_from_name($product_name) {
        $query = 'SELECT ri.id, ri.category, ri.sub_category, ri.description, ri.brand, ri.discount, ri.item_id, i.item_name AS name, i.retail_price AS price FROM retail_items AS ri INNER JOIN items as i ON ri.item_id = i.id WHERE i.item_name = ?';
        $params = [
            ['type' => 's', 'value' => $product_name]
        ];
        $product = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $product;
    }
}

class RetailUserDatabase {
    private $db_utility;
    public function __construct($db_utility) {
        $this->db_utility = $db_utility;
    }
    public function get_password($email) {
        $query = 'SELECT password FROM customers WHERE email = ?';
        $params = [
            ['type' => 's', 'value' => $email]
        ];
        $passwordHash = $this->db_utility->execute_query($query, $params, 'assoc-array')['password'];
        return $passwordHash;
    }
}

class ImageLocationsDatabase {
    private $db_utility;
    public function __construct($db_utility) {
        $this->db_utility = $db_utility;
    }
    public function get_home_slideshow_images() {
        $query = 'SELECT image_file_name FROM image_locations WHERE page_section_id = 1 AND visible = "Yes"';
        $image_names = $this->db_utility->execute_query($query, null, 'array');
        return $image_names;
    }

    public function get_home_signup_image() {
        $query = 'SELECT image_file_name FROM image_locations WHERE page_section_id = 2 AND visible = "Yes"';
        $image_name = $this->db_utility->execute_query($query, null, 'array');
        return $image_name;
    }
}

class PageSectionsDatabase {
    private $db_utility;
    public function __construct($db_utility) {
        $this->db_utility = $db_utility;
    }
    public function get_section_data($section_name) {
        $query = "SELECT pst.*, il.*, ps.name FROM page_section_text AS pst INNER JOIN page_sections AS ps ON pst.page_section_id = ps.id INNER JOIN image_locations AS il ON il.page_section_id = ps.id  WHERE ps.name = ? AND il.visible = 'Yes'";
        $params = [
            ['type' => 's', 'value' => $section_name]
        ];
        $section_data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $section_data;
    }

    public function get_section_image($section_name) {
        $query = "SELECT il.image_file_name AS image FROM image_locations AS il INNER JOIN page_sections AS ps ON il.page_section_id = ps.id WHERE il.visible = 'Yes' AND ps.name = ?";
        $params = [
            ['type' => 's', 'value' => $section_name]
        ];
        $section_image = $this->db_utility->execute_query($query, $params, 'array');
        return $section_image;
    }
}

class InvoiceDatabase {
    private $db_utility;

    public function __construct($db_utility) {
        $this->db_utility = $db_utility;
    }

    public function get_invoices_due($age) {
        $query = 'SELECT id, title FROM invoices WHERE due_date >= DATE_SUB(NOW(), INTERVAL ? DAY)';
        $params = [
            ['type' => 'i', 'value' => $age]
        ];
        $invoice_data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $invoice_data;
    }

    public function get_debtor_data($startDay, $endDay) {
        $query = 'SELECT c.id, c.forename, c.surname, SUM(i.total) AS total_amount, MAX(i.created_at) AS latest_created_at
        FROM invoices AS i
        INNER JOIN customers AS c ON i.customer_id = c.id
        WHERE i.payment_status = "No" AND i.created_at >= (CURDATE()) - INTERVAL ? DAY AND i.created_at <= (CURDATE()) - INTERVAL ? DAY AND i.due_date < (CURDATE())
        GROUP BY c.id';        
        
        $params = [
            ['type' => 'i', 'value' => $endDay],
            ['type' => 'i', 'value' => $startDay]
        ];
        $debtor_data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $debtor_data;
    }

    public function get_debtor_data_limitless($startDay) {
        $query = 'SELECT c.id, c.forename, c.surname, SUM(i.total) AS total_amount, MAX(i.created_at) AS latest_created_at
        FROM invoices AS i
        INNER JOIN customers AS c ON i.customer_id = c.id
        WHERE i.payment_status = "No" AND i.created_at < (CURDATE()) - INTERVAL ? DAY AND i.due_date < (CURDATE())
        GROUP BY c.id';

        $params = [
            ['type' => 's', 'value' => $startDay]
        ];
        $debtor_data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $debtor_data;
    }

    public function get_creditor_data($startDay, $endDay) {
        $query = 'SELECT c.id, c.forename, c.surname, SUM(i.total) AS total_amount FROM invoices AS i INNER JOIN customers AS c ON i.customer_id = c.id WHERE i.created_at >= curdate() - INTERVAL ? DAY AND i.created_at < curdate() - INTERVAL ? DAY AND i.customer_id = c.id AND i.payment_status = "No" AND i.due_date < curdate() GROUP BY c.id';
        $params = [
            ['type' => 's', 'value' => $endDay],
            ['type' => 's', 'value' => $startDay]
        ];
        $creditor_data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $creditor_data;
    }

    public function get_total_invoices_month() {
        $query = 'SELECT COUNT(*) AS count FROM invoices WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())';
        $invoice_count = $this->db_utility->execute_query($query, null, 'assoc-array')['count'];
        return $invoice_count;
    }

    public function get_invoices_due_today() {
        $query = 'SELECT COUNT(*) AS count FROM invoices WHERE delivery_date = CURDATE()';
        $invoice_count = $this->db_utility->execute_query($query, null, 'assoc-array')['count'];
        return $invoice_count;
    }

    public function get_customer_id($invoice_id) {
        $query = 'SELECT customer_id FROM invoices WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $invoice_id]
        ];
        $customer_id = $this->db_utility->execute_query($query, $params, 'assoc-array')['customer_id'];
        return $customer_id;
    }
    public function get_invoice_price_data($invoice_id) {
        $query = 'SELECT net_value, VAT, total FROM invoices WHERE id = ?';
        $params = [
            ['type' => 'i', 'value' => $invoice_id]
        ];
        $price_data = $this->db_utility->execute_query($query, $params, 'assoc-array');
        return $price_data;
    }
    public function get_customer_debt($customer_id) {
        $query = 'SELECT (SELECT SUM(total) FROM invoices WHERE customer_id = ?) - COALESCE((SELECT SUM(amount) FROM customer_payments WHERE customer_id = ? AND status = \'Processed\' AND invoice_id IS NOT NULL), 0) AS total';
        $params = [
            ['type' => 'i', 'value' => $customer_id],
            ['type' => 'i', 'value' => $customer_id]
        ];
        $customer_debt = $this->db_utility->execute_query($query, $params, 'assoc-array')['total'];
        return $customer_debt;
    }
    public function set_invoice_payment($status, $invoice_id) {
        if ($status == 'Yes' || $status == 'No') {
            $query = 'UPDATE invoices SET payment_status = ? WHERE id = ?';
            $params = [
                ['type' => 's', 'value' => $status],
                ['type' => 'i', 'value' => $invoice_id]
            ];
            $this->db_utility->execute_query($query, $params, false);
        }
    }
}
?>