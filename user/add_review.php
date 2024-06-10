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
$role_id = $decoded->data->role_id;

$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $response['error'] = true;
    $response['message'] = 'Invalid JSON input';
    echo json_encode($response);
    exit;
}

$diary_id = $input['diary_id'];
$rating = $input['rating'];
$review = $input['review'];

if ($role_id == 1) {
    $query = "SELECT guide_id FROM traveldiaries WHERE diary_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $diary_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $query_array = $result->fetch_assoc();
    $receiver_id = $query_array['guide_id'];
} else if ($role_id == 2) {
    $query = "SELECT tourist_id FROM traveldiaries WHERE diary_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $diary_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $query_array = $result->fetch_assoc();
    $receiver_id = $query_array['tourist_id'];
}


$query = "INSERT INTO ratingsandreviews (diary_id, sender_id, receiver_id, rating, review_text) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($query);
$stmt->bind_param('iiiis', $diary_id, $user_id, $receiver_id, $rating, $review);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $response['message'] = 'Review submitted successfully';
} else {
    $response['error'] = true;
    $response['message'] = 'Failed to submit review';
}

$stmt->close();
$conn->close();

echo json_encode($response);
?>
