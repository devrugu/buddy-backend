<?php
require_once '../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Content-Type: application/json");

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

        // Eğitim seviyesi ekleme işlemi
        if (!empty($data['selectedEducationLevelId'])) {
            $query = "INSERT INTO UserEducationLevels (user_id, education_level_id) VALUES (?, ?)";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                $response['errors'][] = "Prepare failed for education level insertion: " . $conn->error;
            } else {
                $stmt->bind_param("ii", $user_id, $data['selectedEducationLevelId']);
                if (!$stmt->execute()) {
                    $response['errors'][] = "Execution failed for education level: " . $stmt->error;
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
                        continue;
                    }
                    $langInsStmt->bind_param("iis", $user_id, $languageId, $level);
                    if (!$langInsStmt->execute()) {
                        $response['errors'][] = "Execution failed for language: " . $langInsStmt->error;
                    }
                } else {
                    $response['errors'][] = "Language not found: $languageName";
                }
            }
        }

        // Konumu kaydetme işlemi
        if (!empty($data['selectedLocationId'])) {
            $locQuery = "INSERT INTO UserLocations (user_id, location_id) VALUES (?, ?)";
            $locStmt = $conn->prepare($locQuery);
            if (!$locStmt) {
                $response['errors'][] = "Prepare failed for location insertion: " . $conn->error;
            } else {
                $locStmt->bind_param("ii", $user_id, $data['selectedLocationId']);
                if (!$locStmt->execute()) {
                    $response['errors'][] = "Execution failed for location: " . $locStmt->error;
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
                        continue;
                    }
                    $profInsStmt->bind_param("iii", $user_id, $professionId, $years);
                    if (!$profInsStmt->execute()) {
                        $response['errors'][] = "Execution failed for profession: " . $profInsStmt->error;
                    }
                } else {
                    $response['errors'][] = "Profession not found: $professionName";
                }
            }
        }

    } catch (Exception $e) {
        $response['status'] = "error";
        $response['message'] = 'JWT decoding error: ' . $e->getMessage();
    }
} else {
    $response['status'] = "error";
    $response['message'] = 'Access denied. No JWT token provided.';
}

echo json_encode($response);

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
