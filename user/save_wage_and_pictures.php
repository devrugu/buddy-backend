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

        $profile_picture_name = $_POST['profile_picture'] ?? null;
        $wage = ($role_id == 2) ? ($_POST['wage'] ?? null) : null;

        if (($role_id == 2 && !$wage) || !$profile_picture_name) {
            echo json_encode(['error' => true, 'message' => 'Hourly wage and profile picture are required.']);
            exit;
        }

        $pictures = $_FILES['pictures'] ?? null;
        if (!$pictures) {
            echo json_encode(['error' => true, 'message' => 'At least one picture is required.']);
            exit;
        }

        // Use the new directory under the volume
        $base_upload_dir = '/app/updates/images/';
        $upload_dir = ($role_id == 1) ? $base_upload_dir . "tourist/" : $base_upload_dir . "guide/";

        // Ensure upload directory exists and set permissions
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true) && !is_dir($upload_dir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $upload_dir));
            }
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
        $uploaded_files = [];
        for ($i = 0; $i < count($pictures['name']); $i++) {
            $tmp_name = $pictures['tmp_name'][$i];
            $original_name = basename($pictures['name'][$i]);
            $unique_name = uniqid() . '_' . $original_name;
            $upload_file = $upload_dir . $unique_name;

            if (move_uploaded_file($tmp_name, $upload_file)) {
                $is_profile_picture = ($unique_name === $profile_picture_name) ? 1 : 0;
                $stmt = $conn->prepare("INSERT INTO userpictures (user_id, picture_path, is_profile_picture) VALUES (?, ?, ?)");
                $stmt->bind_param("isi", $user_id, $upload_file, $is_profile_picture);
                if (!$stmt->execute()) {
                    $conn->rollback();
                    echo json_encode(['error' => true, 'message' => 'Failed to save pictures.']);
                    exit;
                }
                $uploaded_files[] = $upload_file;
            } else {
                $conn->rollback();
                echo json_encode(['error' => true, 'message' => 'Failed to upload pictures.']);
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
