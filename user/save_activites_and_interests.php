<?php
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once '../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

include '../database/db_connection.php';

$authHeader = getAuthorizationHeader();
list($jwt) = sscanf($authHeader, 'Bearer %s');

if ($jwt) {
    try {
        $key = $_ENV['JWT_SECRET_KEY'];
        $decoded = JWT::decode($jwt, new Key($key, 'HS256'));
        $user_id = $decoded->data->user_id;

        $data = json_decode(file_get_contents('php://input'), true);
        error_log("Received data: " . print_r($data, true));

        $selectedActivities = $data['selectedActivities'];
        $selectedInterests = $data['selectedInterests'];

        // Begin transaction
        $conn->begin_transaction();

        $stmtActivity = $conn->prepare("INSERT INTO UserActivities (user_id, activity_id) VALUES (?, ?)");
        foreach ($selectedActivities as $activityId) {
            $stmtActivity->bind_param("ii", $user_id, $activityId);
            if (!$stmtActivity->execute()) {
                $conn->rollback(); // Rollback the transaction on error
                error_log("Error saving selected activities: " . $stmtActivity->error);
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
            if ($interestId === null) {
                $conn->rollback(); // Rollback the transaction if the interest name does not exist
                error_log("Interest name '$interestName' does not exist in Interests table.");
                echo json_encode(['status' => 'error', 'message' => "Interest name '$interestName' does not exist in Interests table."]);
                $stmtInterest->close();
                $conn->close();
                exit;
            } else {
                $stmtInterest->bind_param("ii", $user_id, $interestId);
                if (!$stmtInterest->execute()) {
                    $conn->rollback(); // Rollback the transaction on error
                    error_log("Error saving selected interests: " . $stmtInterest->error);
                    echo json_encode(['status' => 'error', 'message' => 'Error saving selected interests.']);
                    $stmtInterest->close();
                    $conn->close();
                    exit;
                }
            }
        }

        // If all operations were successful, commit the transaction
        $conn->commit();

        // Update the missing info array
        $missing_info = checkMissingProfileInfo($conn, $user_id);

        // Create new JWT with updated missing_info
        $payload = [
            "iss" => "your_issuer",
            "aud" => "your_audience",
            "iat" => time(),
            "exp" => time() + (int)$_ENV['JWT_EXPIRATION'],
            "data" => [
                "user_id" => $user_id,
                "country_id" => $decoded->data->country_id,
                "role_id" => $decoded->data->role_id,
                "missing_info" => $missing_info
            ]
        ];
        $new_jwt = JWT::encode($payload, $key, 'HS256');

        echo json_encode(['status' => 'success', 'message' => 'Activities and interests saved successfully.', 'token' => $new_jwt]);

    } catch (Exception $e) {
        $conn->rollback(); // Rollback the transaction in case of an error
        error_log("Access denied. " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Access denied. ' . $e->getMessage()]);
    }
} else {
    error_log("Access denied. No token provided.");
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

function checkMissingProfileInfo($conn, $user_id) {
    $missing_info = [];
    $tables = [
        'UserActivities' => 'activity_id',
        'UserEducationLevels' => 'education_level_id',
        'UserInterests' => 'interest_id',
        'UserLanguages' => 'language_id',
        'UserLocations' => 'location_id',
        'UserProfessions' => 'profession_id',
    ];

    foreach ($tables as $table => $column) {
        $query = "SELECT $column FROM $table WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $missing_info[] = strtolower(str_replace('User', '', $table));
        }
    }

    // Check hourly wage
    $query = "SELECT hourly_wage FROM userprofiles WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    if (is_null($row['hourly_wage'])) {
        $missing_info[] = 'profiles';
    }
    $stmt->close();

    // Check if the user has at least one profile picture
    $query = "SELECT picture_path FROM userpictures WHERE user_id = ? AND is_profile_picture = 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $missing_info[] = 'pictures';
    }
    $stmt->close();

    return $missing_info;
}

function getAuthorizationHeader() {
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
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

$conn->close();
?>
