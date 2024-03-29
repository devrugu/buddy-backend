<?php
require_once '../vendor/autoload.php'; // Ensure this path is correct
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$dotenv = Dotenv\Dotenv::createImmutable("../");
$dotenv->load();

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Content-Type: application/json");

include '../database/db_connection.php'; // Ensure this path is correct to your database connection file

function getAuthorizationHeader(){
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    }
    else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    return $headers;
}

$authHeader = getAuthorizationHeader();
list($jwt) = sscanf($authHeader, 'Bearer %s');

$response = ["status" => "success", "message" => "Activities and interests saved successfully.", "errors" => []];

if ($jwt) {
    try {
        $key = $_ENV['JWT_SECRET_KEY']; // Your secret key
        $decoded = JWT::decode($jwt, new Key($key, 'HS256'));
        $user_id = $decoded->data->user_id;

        $data = json_decode(file_get_contents('php://input'), true);

        $selectedActivities = $data['selectedActivities'];
        $selectedInterests = $data['selectedInterests'];

        foreach ($selectedActivities as $activityId) {
            $query = "INSERT INTO UserActivities (user_id, activity_id) VALUES (?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $user_id, $activityId);
            $stmt->execute();
        }

        foreach ($selectedInterests as $interestName) {
            // Look up the interest_id based on the interest name
            $checkQuery = "SELECT interest_id FROM Interests WHERE interest_name = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("s", $interestName);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $row = $checkResult->fetch_assoc();
                $interestId = $row['interest_id'];
                
                $query = "INSERT INTO UserInterests (user_id, interest_id) VALUES (?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ii", $user_id, $interestId);
                $stmt->execute();
            } else {
                // Interest name does not exist, record error
                $response["errors"][] = "Interest name '$interestName' does not exist in Interests table.";
                $response["status"] = "error";
                $response["message"] = "One or more interests could not be saved.";
            }
        }

    } catch (Exception $e) {
        http_response_code(401);
        $response = ['status' => 'error', 'message' => 'Access denied. ' . $e->getMessage(), "errors" => []];
    }
} else {
    http_response_code(401);
    $response = ['status' => 'error', 'message' => 'Access denied. No token provided.', "errors" => []];
}

echo json_encode($response);

$conn->close();
