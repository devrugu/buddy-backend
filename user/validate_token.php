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

        $query = "SELECT user_id, role_id, is_deleted FROM users WHERE user_id = ? AND is_deleted = 0";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception('User not found or deleted');
        }

        $user = $result->fetch_assoc();
        echo json_encode([
            'error' => false,
            'user_id' => $user['user_id'],
            'role_id' => $user['role_id']
        ]);
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
