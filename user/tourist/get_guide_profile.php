<?php
// backend/api/get_guide_profile.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require '../../vendor/autoload.php';
/* $dotenv = Dotenv\Dotenv::createImmutable("../../");
$dotenv->load(); */

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

    $decoded = JWT::decode($jwt, new Key(getenv('JWT_SECRET_KEY'), 'HS256'));

    if (!isset($decoded->data->selected_guide_id)) {
        throw new Exception('Selected guide ID is missing in JWT token');
    }

    $user_id = $decoded->data->selected_guide_id;

    error_log('Selected Guide ID: ' . $user_id); // Debugging

    $profile = fetchGuideProfile($conn, $user_id);

    echo json_encode(['error' => false, 'profile' => $profile]);
} catch (Exception $e) {
    error_log('Error: ' . $e->getMessage()); // Debugging
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

function fetchGuideProfile($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT up.*, 
               GROUP_CONCAT(DISTINCT l.language_name) AS languages,
               GROUP_CONCAT(DISTINCT a.activity_name) AS activities,
               GROUP_CONCAT(DISTINCT p.profession_name, ' (', uprof.years_of_experience, ' years)') AS professions,
               AVG(r.rating) AS average_rating,
               COUNT(r.rating_id) AS review_count,
               GROUP_CONCAT(DISTINCT ur.review_text ORDER BY ur.timestamp DESC SEPARATOR '|||') AS review_texts,
               GROUP_CONCAT(DISTINCT ur.rating ORDER BY ur.timestamp DESC SEPARATOR '|||') AS review_ratings
        FROM userprofiles up
        LEFT JOIN userlanguages ul ON up.user_id = ul.user_id
        LEFT JOIN languages l ON ul.language_id = l.language_id
        LEFT JOIN useractivities ua ON up.user_id = ua.user_id
        LEFT JOIN activities a ON ua.activity_id = a.activity_id
        LEFT JOIN userprofessions uprof ON up.user_id = uprof.user_id
        LEFT JOIN professions p ON uprof.profession_id = p.profession_id
        LEFT JOIN ratingsandreviews r ON up.user_id = r.receiver_id
        LEFT JOIN ratingsandreviews ur ON up.user_id = ur.receiver_id
        WHERE up.user_id = ?
        GROUP BY up.user_id
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $profile = $result->fetch_assoc();
        $profile['images'] = getGuideImages($user_id, $conn);
        return $profile;
    } else {
        throw new Exception('Guide not found');
    }
}

function getGuideImages($user_id, $conn) {
    $stmt = $conn->prepare("SELECT picture FROM UserPictures WHERE user_id = ? AND is_profile_picture = 0");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $images = [];
    while ($row = $result->fetch_assoc()) {
        $images[] = base64_encode($row['picture']);
    }
    return $images;
}
