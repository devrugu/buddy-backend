<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

include '../database/db_connection.php';

$response = ['error' => false, 'message' => '', 'languages' => []];

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$query = isset($_GET['query']) ? $_GET['query'] : '';

$stmt = $conn->prepare("SELECT language_name FROM languages WHERE language_name LIKE ?");
$searchTerm = "%$query%";
$stmt->bind_param("s", $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $response['languages'][] = $row['language_name'];
}

echo json_encode($response);
?>