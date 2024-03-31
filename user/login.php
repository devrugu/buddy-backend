<?php
require '../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable("../");
$dotenv->load();

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");

include '../database/db_connection.php';

// Gelen verileri al
$username = $_POST['username'];
$password = $_POST['password'];

// Kullanıcı adına göre kullanıcı bilgilerini al
$query = "SELECT user_id, password FROM Users WHERE username = ? AND is_deleted = 0";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Kullanıcı adı bulunamadı
    echo json_encode(['status' => 'error', 'message' => 'Username is incorrect']);
} else {
    $user = $result->fetch_assoc();
    // Şifre doğruluğunu kontrol et
    if (password_verify($password, $user['password'])) {
        // Giriş başarılı, JWT oluştur
        $key = $_ENV['JWT_SECRET_KEY'];
        $payload = [
            "iss" => "your_issuer",
            "aud" => "your_audience",
            "iat" => time(),
            "exp" => time() + $_ENV['JWT_EXPIRATION'],
            "data" => [
                "user_id" => $user['user_id']
            ]
        ];
        $jwt = JWT::encode($payload, $key, $_ENV['JWT_ALGORITHM']);

        $user_id = $user['user_id'];

        $query = 'INSERT INTO UserLoginRecords (user_id, login_status) VALUES (?, ?)';
        $stmt = $conn->prepare($query);
        $login_status = 1; // 1: başarılı, 0: başarısız
        $stmt->bind_param('ii', $user_id, $login_status);
        $stmt->execute();

        // Eksik profil bilgilerini kontrol et
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
            if ($result->num_rows == 0) {
                $missing_info[] = strtolower(str_replace('User', '', $table));
            }
        }

        // Token ile birlikte giriş başarılı mesajı ve eksik bilgiler gönder
        echo json_encode(['status' => 'success', 'message' => 'Login successful', 'token' => $jwt, 'profile_status' => empty($missing_info), 'missing_info' => $missing_info]);
    } else {
        // Şifre yanlış
        echo json_encode(['status' => 'error', 'message' => 'Password is incorrect']);
    }
}

$stmt->close();
$conn->close();
