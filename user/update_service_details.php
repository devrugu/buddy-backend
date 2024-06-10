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
$role_id = $decoded->data->role_id; // Assuming role_id is stored in JWT

$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $response['error'] = true;
    $response['message'] = 'Invalid JSON input';
    echo json_encode($response);
    exit;
}

$diary_id = $input['diary_id'];
$title = $input['title'];
$note = $input['note'];

if ($role_id == 2) { // Role ID 2 for guides
    $query = "UPDATE traveldiary SET guide_title = ?, guide_note = ? WHERE diary_id = ? AND guide_id = ?";
} else if ($role_id == 1) { // Role ID 1 for tourists
    $query = "UPDATE traveldiary SET tourist_title = ?, tourist_note = ? WHERE diary_id = ? AND tourist_id = ?";
}

$stmt = $conn->prepare($query);
$stmt->bind_param('ssii', $title, $note, $diary_id, $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $response['message'] = 'Service details updated successfully';
} else {
    $response['error'] = true;
    $response['message'] = 'Failed to update service details';
}

$stmt->close();
$conn->close();

echo json_encode($response);
?>
