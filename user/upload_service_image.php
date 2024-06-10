<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require '../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable("../");
$dotenv->load();

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

include '../database/db_connection.php';

$response = ['error' => false, 'message' => ''];

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

if (!$authHeader) {
    $response['error'] = true;
    $response['message'] = 'Authorization header is missing';
    echo json_encode($response);
    exit;
}

list($jwt) = sscanf($authHeader, 'Bearer %s');

if (!$jwt) {
    $response['error'] = true;
    $response['message'] = 'JWT token is missing';
    echo json_encode($response);
    exit;
}

try {
    $decoded = JWT::decode($jwt, new Key($_ENV['JWT_SECRET_KEY'], 'HS256'));
} catch (Exception $e) {
    $response['error'] = true;
    $response['message'] = 'JWT token is invalid: ' . $e->getMessage();
    echo json_encode($response);
    exit;
}

$user_id = $decoded->data->user_id;

$diary_id = $_POST['diary_id'];
$target_dir = "../uploads/service/";
$target_file = $target_dir . basename($_FILES["image"]["name"]);
$imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
    $file_path = basename($_FILES["image"]["name"]);
    $query = "INSERT INTO traveldiaryphotos (diary_id, photo_path) VALUES (?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('is', $diary_id, $file_path);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $response['message'] = 'Image uploaded successfully';
    } else {
        $response['error'] = true;
        $response['message'] = 'Failed to save image information to database';
    }

    $stmt->close();
} else {
    $response['error'] = true;
    $response['message'] = 'Failed to upload image';
}

$conn->close();

echo json_encode($response);
?>
