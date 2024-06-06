<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require '../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable("../../");
$dotenv->load();

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

include '../../database/db_connection.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $authHeader = getAuthorizationHeader();
    if (!$authHeader) {
        throw new Exception('Authorization header is missing');
    }

    list($jwt) = sscanf($authHeader, 'Bearer %s');
    if (!$jwt) {
        throw new Exception('JWT token is missing');
    }

    $decoded = JWT::decode($jwt, new Key(getenv('JWT_SECRET_KEY'), 'HS256'));
    $sender_id = $decoded->data->user_id;

    $data = json_decode(file_get_contents('php://input'), true);
    $receiver_id = $data['receiver_id'];

    if (!isset($receiver_id)) {
        throw new Exception('Receiver ID is missing');
    }

    sendInvitation($conn, $sender_id, $receiver_id);

    echo json_encode(['error' => false, 'message' => 'Invitation sent successfully']);
} catch (Exception $e) {
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
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

function sendInvitation($conn, $sender_id, $receiver_id) {
    $stmt = $conn->prepare("
        INSERT INTO GuideRequests (sender_id, receiver_id, status, request_timestamp) 
        VALUES (?, ?, 'pending', NOW())
    ");
    $stmt->bind_param('ii', $sender_id, $receiver_id);
    if (!$stmt->execute()) {
        throw new Exception('Error sending invitation: ' . $stmt->error);
    }
}
