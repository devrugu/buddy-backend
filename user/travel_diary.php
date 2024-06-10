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

$response = ['error' => false, 'message' => '', 'services' => []];

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

if ($role_id == 2) { // Role ID 2 for guides
    $query = "
        SELECT td.*, l.location_name, u.name AS tourist_name, u.surname AS tourist_surname
        FROM traveldiary td
        JOIN locations l ON td.visited_location_id = l.location_id
        JOIN userprofiles u ON td.tourist_id = u.user_id
        WHERE td.guide_id = ?
    ";
} else if ($role_id == 1) { // Role ID 1 for tourists
    $query = "
        SELECT td.*, l.location_name, u.name AS guide_name, u.surname AS guide_surname
        FROM traveldiary td
        JOIN locations l ON td.visited_location_id = l.location_id
        JOIN userprofiles u ON td.guide_id = u.user_id
        WHERE td.tourist_id = ?
    ";
}

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $row['images'] = getTravelDiaryImages($row['diary_id'], $conn);
    $response['services'][] = $row;
}

echo json_encode($response);

function getTravelDiaryImages($diary_id, $conn) {
    $query = "SELECT photo_path FROM traveldiaryphotos WHERE diary_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $diary_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $images = [];
    while ($row = $result->fetch_assoc()) {
        $images[] = $row['photo_path'];
    }
    return $images;
}
?>
