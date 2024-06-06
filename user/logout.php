<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

/* require '../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable("../");
$dotenv->load(); */

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

include '../database/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $authHeader = getAuthorizationHeader();
        if (!$authHeader) {
            throw new Exception('Authorization header is missing');
        }

        list($jwt) = sscanf($authHeader, 'Bearer %s');
        if (!$jwt) {
            throw new Exception('JWT token is missing');
        }

        $decoded = JWT::decode($jwt, new Key(getenv('JWT_SECRET_KEY'), 'HS256'));
        $user_id = $decoded->data->user_id;

        $query = "SELECT login_timestamp FROM UserLoginRecords WHERE user_id = ? AND login_status = 1 ORDER BY login_timestamp DESC LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception('No active login session found for user');
        }

        $login_record = $result->fetch_assoc();
        $login_timestamp = $login_record['login_timestamp'];
        $logout_timestamp = date("Y-m-d H:i:s");
        $session_duration = strtotime($logout_timestamp) - strtotime($login_timestamp);

        $update_query = "UPDATE UserLoginRecords SET logout_timestamp = ?, session_duration = ?, login_status = 0 WHERE user_id = ? AND login_timestamp = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("siis", $logout_timestamp, $session_duration, $user_id, $login_timestamp);
        $stmt->execute();

        echo json_encode(['error' => false, 'message' => 'Logout successful']);
    } catch (Exception $e) {
        echo json_encode(['error' => true, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => true, 'message' => 'Invalid request method']);
}

function getAuthorizationHeader() {
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        return $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (isset($headers['Authorization'])) {
            return $headers['Authorization'];
        }
    }
    return null;
}
