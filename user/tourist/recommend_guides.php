<?php
require '../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable("../../");
$dotenv->load();

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");

include '../../database/db_connection.php';

$headers = apache_request_headers();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;

if (!$authHeader) {
    echo json_encode(['error' => true, 'message' => 'Authorization header is missing']);
    exit;
}

list($jwt) = sscanf($authHeader, 'Bearer %s');

if (!$jwt) {
    echo json_encode(['error' => true, 'message' => 'JWT token is missing']);
    exit;
}

try {
    $decoded = JWT::decode($jwt, new Key($_ENV['JWT_SECRET_KEY'], 'HS256'));
} catch (Exception $e) {
    echo json_encode(['error' => true, 'message' => 'JWT token is invalid']);
    exit;
}

$user_id = $decoded->data->user_id;

$query = "SELECT latitude, longitude FROM UserCurrentLocation WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_location = $result->fetch_assoc();

if (!$user_location) {
    echo json_encode(['error' => true, 'message' => 'User location not found']);
    exit;
}

$user_lat = $user_location['latitude'];
$user_lng = $user_location['longitude'];

$radius = 50; // Radius in kilometers

$query = "
    SELECT u.user_id, up.name, up.surname, ul.latitude, ul.longitude, uc.country_id, r.rating, rr.reviews, u.rate_per_hour
    FROM Users u
    JOIN UserCurrentLocation ul ON u.user_id = ul.user_id
    JOIN UserCountries uc ON u.user_id = uc.user_id
    JOIN UserProfiles up ON u.user_id = up.user_id
    LEFT JOIN (SELECT receiver_id, AVG(rating) as rating FROM RatingsAndReviews GROUP BY receiver_id) r ON u.user_id = r.receiver_id
    LEFT JOIN (SELECT receiver_id, COUNT(*) as reviews FROM RatingsAndReviews GROUP BY receiver_id) rr ON u.user_id = rr.receiver_id
    WHERE u.user_id != ? AND u.role_id = 2 AND (
        6371 * acos (
            cos ( radians(?) )
            * cos( radians( ul.latitude ) )
            * cos( radians( ul.longitude ) - radians(?) )
            + sin ( radians(?) )
            * sin( radians( ul.latitude ) )
        )
    ) < ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("idddi", $user_id, $user_lat, $user_lng, $user_lat, $radius);
$stmt->execute();
$result = $stmt->get_result();

$guides = [];
while ($guide = $result->fetch_assoc()) {
    $guide['images'] = getGuideImages($guide['user_id'], $conn);
    $guides[] = $guide;
}

echo json_encode(['error' => false, 'guides' => $guides]);

function getGuideImages($user_id, $conn) {
    $query = "SELECT picture_path FROM UserPictures WHERE user_id = ? AND is_profile_picture = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $images = [];
    while ($row = $result->fetch_assoc()) {
        $images[] = $row['picture_path'];
    }
    return $images;
}

$stmt->close();
$conn->close();
?>
