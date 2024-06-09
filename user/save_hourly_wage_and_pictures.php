<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once '../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$dotenv = Dotenv\Dotenv::createImmutable("../");
$dotenv->load();

include '../database/db_connection.php';

$authHeader = getAuthorizationHeader();
list($jwt) = sscanf($authHeader, 'Bearer %s');

if ($jwt) {
    try {
        $key = $_ENV['JWT_SECRET_KEY'];
        $decoded = JWT::decode($jwt, new Key($key, 'HS256'));
        $user_id = $decoded->data->user_id;
        $role_id = $decoded->data->role_id;

        $uploadDir = '../uploads/';
        if ($role_id == 1) {
            $uploadDir .= 'tourist/';
        } else if ($role_id == 2) {
            $uploadDir .= 'guide/';
        }

        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if ($role_id == 2 && isset($_POST['hourly_wage'])) {
            $hourly_wage = $_POST['hourly_wage'];
            $stmt = $conn->prepare("UPDATE userprofiles SET hourly_wage = ? WHERE user_id = ?");
            $stmt->bind_param("si", $hourly_wage, $user_id);
            $stmt->execute();
        }

        $uploadedFiles = [];
        foreach ($_FILES['pictures']['tmp_name'] as $key => $tmpName) {
            $filename = uniqid() . '-' . basename($_FILES['pictures']['name'][$key]);
            $filepath = $uploadDir . $filename;

            if (move_uploaded_file($tmpName, $filepath)) {
                $uploadedFiles[] = $filepath;

                $isProfilePicture = $_FILES['pictures']['name'][$key] == $_POST['profile_picture'] ? 1 : 0;
                $stmt = $conn->prepare("INSERT INTO userpictures (user_id, picture_path, is_profile_picture) VALUES (?, ?, ?)");
                $stmt->bind_param("isi", $user_id, $filename, $isProfilePicture);
                $stmt->execute();
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

        $key = $_ENV['JWT_SECRET_KEY'];
        $new_jwt = JWT::encode($payload, $key, 'HS256');

        echo json_encode(['error' => false, 'message' => 'Data saved successfully', 'role_id' => $role_id, 'token' => $new_jwt, 'missing_info' => $missing_info,]);
    } catch (Exception $e) {
        echo json_encode(['error' => true, 'message' => 'Access denied. ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => true, 'message' => 'Access denied. No token provided.']);
}

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
    } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
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
