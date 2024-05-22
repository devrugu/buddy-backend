<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require '../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable("../");
$dotenv->load();

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

include '../database/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? null;
    $password = $_POST['password'] ?? null;

    if (!$username || !$password) {
        echo json_encode(['error' => true, 'message' => 'Username and password are required.']);
        exit;
    }

    $query = "SELECT user_id, password, role_id FROM Users WHERE username = ? AND is_deleted = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['error' => true, 'message' => 'Username is incorrect or not found.']);
    } else {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $query = "SELECT country_id FROM UserCountries WHERE user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $user['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $country_id = $result->fetch_assoc()['country_id'];

            $key = $_ENV['JWT_SECRET_KEY'];
            $payload = [
                "iss" => "your_issuer",
                "aud" => "your_audience",
                "iat" => time(),
                "exp" => time() + (int)$_ENV['JWT_EXPIRATION'],
                "data" => [
                    "user_id" => $user['user_id'],
                    "country_id" => $country_id,
                    "role_id" => $user['role_id']
                ]
            ];
            $jwt = JWT::encode($payload, $key, 'HS256');

            $user_id = $user['user_id'];
            $login_status = 1; // 1: successful, 0: failed
            $login_timestamp = date("Y-m-d H:i:s"); // Current timestamp for login
            $query = 'INSERT INTO UserLoginRecords (user_id, login_timestamp, login_status) VALUES (?, ?, ?)';
            $stmt = $conn->prepare($query);
            $stmt->bind_param('isi', $user_id, $login_timestamp, $login_status);
            $stmt->execute();

            $missing_info = checkMissingProfileInfo($conn, $user_id);

            echo json_encode([
                'error' => false,
                'message' => 'Login successful',
                'token' => $jwt,
                'role_id' => $user['role_id'],
                'profile_status' => empty($missing_info),
                'missing_info' => $missing_info
            ]);
        } else {
            echo json_encode(['error' => true, 'message' => 'Password is incorrect.']);
        }
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['error' => true, 'message' => 'Invalid request method.']);
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
    return $missing_info;
}