<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require '../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable("../");
$dotenv->load();

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

include '../database/db_connection.php';

$response = ['error' => false, 'hasReview' => false, 'rating' => 0, 'review' => ''];

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
$diary_id = $_GET['diary_id'];

$query = "SELECT rating, review_text FROM ratingsandreviews WHERE diary_id = ? AND sender_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $diary_id, $user_id);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($rating, $review_text);

if ($stmt->num_rows > 0) {
    $stmt->fetch();
    $response['hasReview'] = true;
    $response['rating'] = $rating;
    $response['review'] = $review_text;
}

$stmt->close();
$conn->close();

echo json_encode($response);
?>
