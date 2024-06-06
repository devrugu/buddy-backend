<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require '../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable("../");
$dotenv->load();

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

include '../database/db_connection.php';

$authHeader = null;
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    }
}

error_log('Authorization Header in update_current_location.php: ' . $authHeader); // Debugging

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
    $decoded = JWT::decode($jwt, new Key(getenv('JWT_SECRET_KEY'), 'HS256'));
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

// Use INSERT ... ON DUPLICATE KEY UPDATE
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
