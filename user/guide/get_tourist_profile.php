<?php
// backend/api/get_tourist_profile.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require '../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable("../../");
$dotenv->load();

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

include '../../database/db_connection.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

    $decoded = JWT::decode($jwt, new Key($_ENV['JWT_SECRET_KEY'], 'HS256'));

    if (!isset($decoded->tourist_id)) {
        throw new Exception('Tourist ID is missing in JWT token');
    }

    $tourist_id = $decoded->tourist_id;

    $profile = fetchTouristProfile($conn, $tourist_id);

    echo json_encode(['error' => false, 'profile' => $profile]);
} catch (Exception $e) {
    error_log('Error: ' . $e->getMessage());
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

function fetchTouristProfile($conn, $tourist_id) {
    $stmt = $conn->prepare("
        SELECT up.*, 
               GROUP_CONCAT(DISTINCT l.language_name) AS languages,
               GROUP_CONCAT(DISTINCT a.activity_name) AS activities,
               GROUP_CONCAT(DISTINCT p.profession_name, ' (', uprof.years_of_experience, ' years)') AS professions,
               AVG(r.rating) AS average_rating,
               COUNT(r.rating_id) AS review_count,
               GROUP_CONCAT(DISTINCT ur.review_text ORDER BY ur.timestamp DESC SEPARATOR '|||') AS review_texts,
               GROUP_CONCAT(DISTINCT ur.rating ORDER BY ur.timestamp DESC SEPARATOR '|||') AS review_ratings
        FROM UserProfiles up
        LEFT JOIN UserLanguages ul ON up.user_id = ul.user_id
        LEFT JOIN Languages l ON ul.language_id = l.language_id
        LEFT JOIN UserActivities ua ON up.user_id = ua.user_id
        LEFT JOIN Activities a ON ua.activity_id = a.activity_id
        LEFT JOIN UserProfessions uprof ON up.user_id = uprof.user_id
        LEFT JOIN Professions p ON uprof.profession_id = p.profession_id
        LEFT JOIN RatingsAndReviews r ON up.user_id = r.receiver_id
        LEFT JOIN RatingsAndReviews ur ON up.user_id = ur.receiver_id
        WHERE up.user_id = ?
        GROUP BY up.user_id
    ");
    $stmt->bind_param('i', $tourist_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $profile = $result->fetch_assoc();
        $profile['images'] = getTouristImages($tourist_id, $conn);
        return $profile;
    } else {
        throw new Exception('Tourist not found');
    }
}

function getTouristImages($user_id, $conn) {
    $stmt = $conn->prepare("SELECT picture_path FROM UserPictures WHERE user_id = ? AND is_profile_picture = 0");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $images = [];
    while ($row = $result->fetch_assoc()) {
        $images[] = $row['picture_path'];
    }
    return $images;
}