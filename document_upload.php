<?php

require_once 'cors_config.php';

if ($_FILES['document']) {
    $document_name = $_FILES['document']['name'];
    $document_tmp_name = $_FILES['document']['tmp_name'];
    $error = $_FILES['document']['error'];
    if ($error > 0) {
        $response = array('success' => false, 'message' => 'There was an error processing the file! The file type may not be valid. Please try a different document or changing the type of the document.');
    } else {
        $uploadDir = '../uploads/';
        $uploadFile = $uploadDir . basename($document_name);

        if (move_uploaded_file($document_tmp_name, $uploadFile)) {
            $response = array('success' => true, 'message' => 'Document was uploaded successfully!');
        } else {
            $response = array('success' => false, 'message' => 'There was an error uploading the file! The file type may not be valid!');
        }
    }
} else {
    $response = array('success' => false, 'message' => 'Document was not received by server!');
}

echo json_encode($response);