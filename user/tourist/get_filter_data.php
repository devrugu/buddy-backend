<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require '../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable("../../");
$dotenv->load();

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

include '../../database/db_connection.php';

$response = ['error' => false, 'message' => '', 'professions' => [], 'interests' => [], 'activities' => [], 'languages' => []];

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

// Fetch professions
$query = "SELECT profession_name FROM Professions";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$professions = [];
while ($row = $result->fetch_assoc()) {
    $professions[] = $row['profession_name'];
}
$response['professions'] = $professions;

// Fetch interests
$query = "SELECT interest_name FROM Interests";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$interests = [];
while ($row = $result->fetch_assoc()) {
    $interests[] = $row['interest_name'];
}
$response['interests'] = $interests;

// Fetch activities
$query = "SELECT activity_name FROM Activities";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$activities = [];
while ($row = $result->fetch_assoc()) {
    $activities[] = $row['activity_name'];
}
$response['activities'] = $activities;

// Fetch languages
$query = "SELECT language_name FROM Languages";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$languages = [];
while ($row = $result->fetch_assoc()) {
    $languages[] = $row['language_name'];
}
$response['languages'] = $languages;

$stmt->close();
$conn->close();

echo json_encode($response);
?>
