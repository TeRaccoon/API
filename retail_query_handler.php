<?php

require_once 'cors_config.php';

$input_data = file_get_contents("php://input");
$data = json_decode($input_data, true);
    
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
    $retail_items_database = new RetailItemsDatabase($database_utility);
    $image_locations_database = new ImageLocationsDatabase($database_utility);
    $page_sections_database = new PageSectionsDatabase($database_utility);
    $items_database = new ItemDatabase($database_utility);
    $customer_database = new CustomerDatabase($database_utility);

    $conn->connect();
    $results = null;
    switch ($query) {
        case 'categories':
            $results = $items_database->categories();
            break;

        case 'visible-categories':
            $results = $retail_items_database->get_visible_categories();
            break;

        case 'subcategories':
            $results = $retail_items_database->get_subcategories();
            break;

        case 'items-category':
            $category = urldecode($_GET["filter"]);
            $results = $retail_items_database->get_items_from_category($category);
            break;

        case 'top-products':
            $limit = urldecode($_GET['limit']);
            $customer_type = urldecode($_GET['queryType']);
            $results = $retail_items_database->get_top_products($limit, $customer_type);
            break;

        case "product-view-details":
            $product_name = urldecode($_GET['productName']);
            $customer_type = urldecode($_GET['queryType']);
            $results = $retail_items_database->get_product_from_name($product_name, $customer_type);
            break;

        case "featured":
            $limit = urldecode($_GET['limit']);
            $customer_type = urldecode($_GET['queryType']);
            $results = $retail_items_database->get_featured($limit, $customer_type);
            break;

        case 'products-from-category':
            $category = urldecode($_GET['category']);
            $customer_type = urldecode($_GET['queryType']);
            $results = $retail_items_database->get_products_from_category($category, $customer_type);
            break;

        case 'product-from-id':
            $id = urldecode($_GET['filter']);
            $results = $retail_items_database->get_product_from_id($id);
            break;

        case 'products':
            $customer_type = urldecode($_GET['queryType']);
            $results = $retail_items_database->get_products($customer_type);
            break;
            
        case 'home-slideshow':
            $results = $image_locations_database->get_home_slideshow_images();
            break;

        case 'home-signup':
            $results = $image_locations_database->get_home_signup_image();
            break;

        case 'section-data':
            $section_name = urldecode($_GET['filter']);
            $results = $page_sections_database->get_section_data($section_name);
            break;

        case "section-image":
            $section_name = urldecode($_GET['filter']);
            $results = $page_sections_database->get_section_image($section_name);
            break;

        case "product-view":
            $product_name = urldecode($_GET['filter']);
            $results = $retail_items_database->get_product_view($product_name);
            break;

        case "product-view-images":
            $retail_item_id = urldecode($_GET['filter']);
            $results = $retail_items_database->get_product_view_images($retail_item_id);
            break;

        case "is-product-in-wishlist":
            $id = urldecode($_GET['id']);
            $product_id = urldecode($_GET['product_id']);
            $results = $retail_items_database->get_is_product_in_wishlist($id, $product_id);
            break;

        case "user-id-from-email":
            $email = urldecode($_GET['filter']);
            $results = $customer_database->get_customer_id_from_email($email);
            break;

        case "user-details-from-id":
            $id = urldecode($_GET['filter']);
            $results = $customer_database->get_customer_details_from_id($id);
            break;

        case "wishlist-from-id":
            $id = urldecode($_GET['filter']);
            $results = $customer_database->get_customer_wishlist_from_id($id);
            break;

        case "total-stock-by-id":
            $item_id = urldecode($_GET['filter']);
            $results = $all_databases->get_total_stock_by_id($item_id);
            break;

        case "order-history":
            $user_id = urldecode($_GET['filter']);
            $results = $customer_database->get_order_history($user_id);
            break;
    }
    echo json_encode($results);
}

function login($retail_user_database, $customer_database, $email, $password) {
    $password_hash = $retail_user_database->get_password($email, $password);
    if ($password_hash == null || !password_verify($password, $password_hash)) { 
        return 'Username or password is incorrect!';
    } else {
        return $customer_database->get_customer_id_from_email($email);
    }
}
