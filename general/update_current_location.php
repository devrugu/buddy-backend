<?php
require '../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable("../");
$dotenv->load();

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");

include '../database/db_connection.php';

$headers = apache_request_headers();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;

if (!$authHeader) {
    echo json_encode(['error' => true, 'message' => 'Authorization header is missing']);
    exit;
}

list($jwt) = sscanf($authHeader, 'Bearer %s');

if (!$jwt) {
    echo json_encode(['error' => true, 'message' => 'JWT token is missing']);
    exit;
}

try {
    $decoded = JWT::decode($jwt, new Key($_ENV['JWT_SECRET_KEY'], 'HS256'));
} catch (Exception $e) {
    echo json_encode(['error' => true, 'message' => 'JWT token is invalid']);
    exit;
}

$user_id = $decoded->data->user_id;
$data = json_decode(file_get_contents('php://input'), true);

$latitude = $data['latitude'] ?? null;
$longitude = $data['longitude'] ?? null;

if (!$latitude || !$longitude) {
    echo json_encode(['error' => true, 'message' => 'Latitude and longitude are required']);
    exit;
}

$query = "INSERT INTO UserCurrentLocation (user_id, latitude, longitude) VALUES (?, ?, ?)
          ON DUPLICATE KEY UPDATE latitude = VALUES(latitude), longitude = VALUES(longitude)";
$stmt = $conn->prepare($query);
$stmt->bind_param("idd", $user_id, $latitude, $longitude);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['error' => false, 'message' => 'Location updated successfully']);
} else {
    echo json_encode(['error' => true, 'message' => 'Failed to update location']);
}

$stmt->close();
$conn->close();
?>
