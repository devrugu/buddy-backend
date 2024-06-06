<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

/* require '../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable("../../");
$dotenv->load(); */

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

include '../../database/db_connection.php';

$response = ['error' => false, 'message' => ''];

$authHeader = null;
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    }
}

if (!$authHeader) {
    $response['error'] = true;
    $response['message'] = 'Authorization header is missing';
    echo json_encode($response);
    exit();
}

list($jwt) = sscanf($authHeader, 'Bearer %s');

if (!$jwt) {
    $response['error'] = true;
    $response['message'] = 'JWT token is missing';
    echo json_encode($response);
    exit();
}

try {
    $decoded = JWT::decode($jwt, new Key(getenv('JWT_SECRET_KEY'), 'HS256'));
} catch (Exception $e) {
    $response['error'] = true;
    $response['message'] = 'JWT token is invalid: ' . $e->getMessage();
    echo json_encode($response);
    exit();
}

$user_id = $decoded->data->user_id;

$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $response['error'] = true;
    $response['message'] = 'Invalid JSON input';
    echo json_encode($response);
    exit();
}

$tourist_id = $input['tourist_id'];

$stmt = $conn->prepare("SELECT location_id FROM UserLocations WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$location = $result->fetch_assoc();
$location_id = $location['location_id'];

$query = "
    INSERT INTO TravelDiary (tourist_id, guide_id, visited_location_id, date_visited, request_id) 
    VALUES (?, ?, ?, NOW(), ?)
";
$stmt = $conn->prepare($query);
$stmt->bind_param("iiii", $tourist_id, $user_id, $location_id, $input['request_id']);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $response['message'] = 'Service finished and saved to travel diary';
} else {
    $response['error'] = true;
    $response['message'] = 'Failed to save service information';
}

$stmt->close();
$conn->close();

echo json_encode($response);
?>
