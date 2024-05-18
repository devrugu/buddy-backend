<?php
require_once '../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Content-Type: application/json");

include '../database/db_connection.php';

$authHeader = getAuthorizationHeader();
list($jwt) = sscanf($authHeader, 'Bearer %s');

if ($jwt) {
    try {
        $key = $_ENV['JWT_SECRET_KEY'];
        $decoded = JWT::decode($jwt, new Key($key, 'HS256'));
        $user_id = $decoded->data->user_id;

        $data = json_decode(file_get_contents('php://input'), true);
        $selectedActivities = $data['selectedActivities'];
        $selectedInterests = $data['selectedInterests'];

        // Begin transaction
        $conn->begin_transaction();

        $stmtActivity = $conn->prepare("INSERT INTO UserActivities (user_id, activity_id) VALUES (?, ?)");
        foreach ($selectedActivities as $activityId) {
            $stmtActivity->bind_param("ii", $user_id, $activityId);
            if(!$stmtActivity->execute()) {
                $conn->rollback(); // Rollback the transaction on error
                echo json_encode(['status' => 'error', 'message' => 'Error saving selected activities.']);
                $stmtActivity->close();
                $conn->close();
                exit;
            }
        }

        $stmtInterest = $conn->prepare("INSERT INTO UserInterests (user_id, interest_id) VALUES (?, ?)");
        foreach ($selectedInterests as $interestName) {
            // Look up the interest_id based on the interest name
            $interestId = getInterestId($conn, $interestName);
            if($interestId === null) {
                $conn->rollback(); // Rollback the transaction if the interest name does not exist
                echo json_encode(['status' => 'error', 'message' => "Interest name '$interestName' does not exist in Interests table."]);
                $stmtInterest->close();
                $conn->close();
                exit;
            } else {
                $stmtInterest->bind_param("ii", $user_id, $interestId);
                $stmtInterest->execute();
            }
        }

        // If all operations were successful, commit the transaction
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Activities and interests saved successfully.']);

    } catch (Exception $e) {
        $conn->rollback(); // Rollback the transaction in case of an error
        echo json_encode(['status' => 'error', 'message' => 'Access denied. ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Access denied. No token provided.']);
}

function getInterestId($conn, $interestName) {
    $checkQuery = "SELECT interest_id FROM Interests WHERE interest_name = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("s", $interestName);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    if ($checkResult->num_rows > 0) {
        $row = $checkResult->fetch_assoc();
        return $row['interest_id'];
    }
    return null;
}

function getAuthorizationHeader() {
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    return $headers;
}

$conn->close();
