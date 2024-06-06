<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
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

$response = ['error' => false, 'message' => '', 'requests' => []];

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
    $decoded = JWT::decode($jwt, new Key(getenv('JWT_SECRET_KEY'), 'HS256'));
} catch (Exception $e) {
    $response['error'] = true;
    $response['message'] = 'JWT token is invalid: ' . $e->getMessage();
    echo json_encode($response);
    exit;
}

$user_id = $decoded->data->user_id;
$status = $_GET['status'];

$query = "
    SELECT gr.request_id, gr.sender_id AS tourist_id, up.name, up.surname, gr.status,
           (SELECT AVG(rating) FROM RatingsAndReviews WHERE receiver_id = gr.sender_id) AS rating,
           (SELECT COUNT(*) FROM RatingsAndReviews WHERE receiver_id = gr.sender_id) AS reviews,
           td.diary_id IS NOT NULL AS service_finished
    FROM GuideRequests gr
    JOIN Users u ON gr.sender_id = u.user_id
    JOIN UserProfiles up ON gr.sender_id = up.user_id
    LEFT JOIN TravelDiary td ON gr.request_id = td.request_id
    WHERE gr.receiver_id = ? AND gr.status = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("is", $user_id, $status);
$stmt->execute();
$result = $stmt->get_result();

$requests = [];
while ($row = $result->fetch_assoc()) {
    $row['pictures'] = getTouristPictures($row['tourist_id'], $conn);
    $requests[] = $row;
}

$response['requests'] = $requests;

$stmt->close();
$conn->close();

echo json_encode($response);

function getTouristPictures($user_id, $conn) {
    $query = "SELECT picture_path FROM UserPictures WHERE user_id = ? AND is_profile_picture = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pictures = [];
    while ($row = $result->fetch_assoc()) {
        $pictures[] = $row['picture_path'];
    }
    return $pictures;
}
