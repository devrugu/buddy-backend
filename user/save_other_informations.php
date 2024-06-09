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

$response = ["status" => "success", "message" => "Information successfully updated.", "errors" => []];

$authHeader = getAuthorizationHeader();
list($jwt) = sscanf($authHeader, 'Bearer %s');

if ($jwt) {
    try {
        $key = $_ENV['JWT_SECRET_KEY'];
        $decoded = JWT::decode($jwt, new Key($key, $_ENV['JWT_ALGORITHM']));
        $user_id = $decoded->data->user_id;

        $data = json_decode(file_get_contents('php://input'), true);
        error_log("Received data: " . print_r($data, true));

        // Eğitim seviyesi ekleme işlemi
        if (!empty($data['selectedEducationLevelId'])) {
            $query = "INSERT INTO UserEducationLevels (user_id, education_level_id) VALUES (?, ?)";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                $response['errors'][] = "Prepare failed for education level insertion: " . $conn->error;
                error_log("Prepare failed for education level insertion: " . $conn->error);
            } else {
                $stmt->bind_param("ii", $user_id, $data['selectedEducationLevelId']);
                if (!$stmt->execute()) {
                    $response['errors'][] = "Execution failed for education level: " . $stmt->error;
                    error_log("Execution failed for education level: " . $stmt->error);
                }
            }
        }

        // Dilleri ekleme işlemi
        if (!empty($data['selectedLanguages'])) {
            foreach ($data['selectedLanguages'] as $languageName => $level) {
                $langQuery = "SELECT language_id FROM Languages WHERE language_name = ?";
                $langStmt = $conn->prepare($langQuery);
                if (!$langStmt) {
                    $response['errors'][] = "Prepare failed for language selection: " . $conn->error;
                    error_log("Prepare failed for language selection: " . $conn->error);
                    continue;
                }
                $langStmt->bind_param("s", $languageName);
                $langStmt->execute();
                $langResult = $langStmt->get_result();
                if ($langRow = $langResult->fetch_assoc()) {
                    $languageId = $langRow['language_id'];
                    $langInsQuery = "INSERT INTO UserLanguages (user_id, language_id, language_level) VALUES (?, ?, ?)";
                    $langInsStmt = $conn->prepare($langInsQuery);
                    if (!$langInsStmt) {
                        $response['errors'][] = "Prepare failed for language insertion: " . $conn->error;
                        error_log("Prepare failed for language insertion: " . $conn->error);
                        continue;
                    }
                    $langInsStmt->bind_param("iis", $user_id, $languageId, $level);
                    if (!$langInsStmt->execute()) {
                        $response['errors'][] = "Execution failed for language: " . $langInsStmt->error;
                        error_log("Execution failed for language: " . $langInsStmt->error);
                    }
                } else {
                    $response['errors'][] = "Language not found: $languageName";
                    error_log("Language not found: $languageName");
                }
            }
        }

        // Konumu kaydetme işlemi
        if (!empty($data['selectedLocationId'])) {
            $locQuery = "INSERT INTO UserLocations (user_id, location_id) VALUES (?, ?)";
            $locStmt = $conn->prepare($locQuery);
            if (!$locStmt) {
                $response['errors'][] = "Prepare failed for location insertion: " . $conn->error;
                error_log("Prepare failed for location insertion: " . $conn->error);
            } else {
                $locStmt->bind_param("ii", $user_id, $data['selectedLocationId']);
                if (!$locStmt->execute()) {
                    $response['errors'][] = "Execution failed for location: " . $locStmt->error;
                    error_log("Execution failed for location: " . $locStmt->error);
                }
            }
        }

        // Meslekleri kaydetme işlemi
        if (!empty($data['selectedProfessions'])) {
            foreach ($data['selectedProfessions'] as $professionName => $years) {
                $profQuery = "SELECT profession_id FROM Professions WHERE profession_name = ?";
                $profStmt = $conn->prepare($profQuery);
                if (!$profStmt) {
                    $response['errors'][] = "Prepare failed for profession selection: " . $conn->error;
                    error_log("Prepare failed for profession selection: " . $conn->error);
                    continue;
                }
                $profStmt->bind_param("s", $professionName);
                $profStmt->execute();
                $profResult = $profStmt->get_result();
                if ($profRow = $profResult->fetch_assoc()) {
                    $professionId = $profRow['profession_id'];
                    $profInsQuery = "INSERT INTO UserProfessions (user_id, profession_id, years_of_experience) VALUES (?, ?, ?)";
                    $profInsStmt = $conn->prepare($profInsQuery);
                    if (!$profInsStmt) {
                        $response['errors'][] = "Prepare failed for profession insertion: " . $conn->error;
                        error_log("Prepare failed for profession insertion: " . $conn->error);
                        continue;
                    }
                    $profInsStmt->bind_param("iii", $user_id, $professionId, $years);
                    if (!$profInsStmt->execute()) {
                        $response['errors'][] = "Execution failed for profession: " . $profInsStmt->error;
                        error_log("Execution failed for profession: " . $profInsStmt->error);
                    }
                } else {
                    $response['errors'][] = "Profession not found: $professionName";
                    error_log("Profession not found: $professionName");
                }
            }
        }

        $role_id = $decoded->data->role_id;
        // Update the missing info array
        $missing_info = checkMissingProfileInfo($conn, $user_id, $role_id);

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

        echo json_encode(['status' => 'success', 'message' => 'Information successfully updated.', 'token' => $new_jwt]);

    } catch (Exception $e) {
        $response['status'] = "error";
        $response['message'] = 'JWT decoding error: ' . $e->getMessage();
        error_log('JWT decoding error: ' . $e->getMessage());
    }
} else {
    $response['status'] = "error";
    $response['message'] = 'Access denied. No JWT token provided.';
    error_log('Access denied. No JWT token provided.');
}

echo json_encode($response);

function checkMissingProfileInfo($conn, $user_id, $role_id) {
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

    if ($role_id === 2) {
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
    }
    

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
