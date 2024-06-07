<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once '../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

include '../database/db_connection.php';

$authHeader = getAuthorizationHeader();
list($jwt) = sscanf($authHeader, 'Bearer %s');

if ($jwt) {
    try {
        $key = getenv('JWT_SECRET_KEY');
        $decoded = JWT::decode($jwt, new Key($key, 'HS256'));
        $user_id = $decoded->data->user_id;
        $role_id = $decoded->data->role_id;

        $profile_picture_index = $_POST['profile_picture_index'] ?? null;
        $wage = ($role_id == 2) ? ($_POST['wage'] ?? null) : null;

        if (($role_id == 2 && !$wage) || $profile_picture_index === null) {
            echo json_encode(['error' => true, 'message' => 'Hourly wage and profile picture are required.']);
            exit;
        }

        $pictures = $_FILES['pictures'] ?? null;
        if (!$pictures) {
            echo json_encode(['error' => true, 'message' => 'At least one picture is required.']);
            exit;
        }

        // Begin transaction
        $conn->begin_transaction();

        // Save hourly wage for guides only
        if ($role_id == 2) {
            $stmt = $conn->prepare("UPDATE userprofiles SET hourly_wage = ? WHERE user_id = ?");
            $stmt->bind_param("si", $wage, $user_id);
            if (!$stmt->execute()) {
                $conn->rollback();
                echo json_encode(['error' => true, 'message' => 'Failed to save hourly wage.']);
                exit;
            }
        }

        // Save pictures
        for ($i = 0; $i < count($pictures['name']); $i++) {
            $image_data = file_get_contents($pictures['tmp_name'][$i]);
            $is_profile_picture = ($i == $profile_picture_index) ? 1 : 0;
            $stmt = $conn->prepare("INSERT INTO userpictures (user_id, picture, is_profile_picture) VALUES (?, ?, ?)");
            $stmt->bind_param("bsi", $user_id, $null, $is_profile_picture);
            $stmt->send_long_data(1, $image_data);
            if (!$stmt->execute()) {
                $conn->rollback();
                echo json_encode(['error' => true, 'message' => 'Failed to save pictures.']);
                exit;
            }
        }

        // Commit transaction
        $conn->commit();
        echo json_encode(['error' => false, 'message' => 'Hourly wage and pictures saved successfully.', 'role_id' => $role_id]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['error' => true, 'message' => 'Access denied. ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => true, 'message' => 'Access denied. No token provided.']);
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
