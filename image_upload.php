<?php

require_once 'cors_config.php';

if ($_FILES['image']) {
    $image_name = $_FILES['image']['name'];
    $image_tmp_name = $_FILES['image']['tmp_name'];
    $error = $_FILES['image']['error'];
    if ($error > 0) {
        $response = array(
            "status" => "error",
            "error" => true,
            "message" => "Error uploading the file!"
        );
    } else {
        $uploadDir = '../uploads/';
        $uploadFile = $uploadDir . basename($image_name);

        if (move_uploaded_file($image_tmp_name, $uploadFile)) {
            $response = array('success' => true, 'message' => 'Image was uploaded successfully!');
        } else {
            $response = array('success' => false, 'message' => 'There was an error uploading the file! The file type may not be valid!');
        }
    }
} else {
    $response = array('success' => false, 'message' => 'Image was not received by server!');
}

echo json_encode($response);