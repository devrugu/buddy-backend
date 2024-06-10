<?php
// backend/api/recommend_guides.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require '../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable("../../");
$dotenv->load();

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

include '../../database/db_connection.php';

$response = ['error' => false, 'message' => '', 'guides' => []];

$authHeader = null;
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    }
}

if (!$authHeader) {
    $response['error'] = true;
    $response['message'] = 'Authorization header is missing';
    echo json_encode($response);
    exit;
}

list($jwt) = sscanf($authHeader, 'Bearer %s');

if (!$jwt) {
    $response['error'] = true;
    $response['message'] = 'JWT token is missing';
    echo json_encode($response);
    exit;
}

try {
    $decoded = JWT::decode($jwt, new Key($_ENV['JWT_SECRET_KEY'], 'HS256'));
} catch (Exception $e) {
    $response['error'] = true;
    $response['message'] = 'JWT token is invalid: ' . $e->getMessage();
    echo json_encode($response);
    exit;
}

$user_id = $decoded->data->user_id;

// Get user current location
$query = "SELECT latitude, longitude FROM UserCurrentLocation WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_location = $result->fetch_assoc();

if (!$user_location) {
    $response['error'] = true;
    $response['message'] = 'User location not found';
    echo json_encode($response);
    exit;
}

$user_lat = $user_location['latitude'];
$user_lng = $user_location['longitude'];

$response['message'] = "User Location: Latitude - $user_lat, Longitude - $user_lng";

$radius = 50; // Radius in kilometers

// Get user interests, activities, and activity categories
$query = "
    SELECT interest_id FROM UserInterests WHERE user_id = ? 
    UNION 
    SELECT activity_id FROM UserActivities WHERE user_id = ? 
    UNION
    SELECT category_id FROM Activities WHERE activity_id IN (SELECT activity_id FROM UserActivities WHERE user_id = ?)
";
$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_preferences = [];
while ($row = $result->fetch_assoc()) {
    $user_preferences[] = $row['interest_id'];
}

$placeholders = implode(',', array_fill(0, count($user_preferences), '?'));
$params = array_merge([$user_id, $user_id, $user_lat, $user_lng, $user_lat, $radius], $user_preferences);

// Fetch guides based on location
$query = "
    SELECT u.user_id, up.name, up.surname, ul.latitude, ul.longitude, uc.country_id, r.rating, rr.reviews, up.hourly_wage
    FROM Users u
    JOIN UserCurrentLocation ul ON u.user_id = ul.user_id
    JOIN UserCountries uc ON u.user_id = uc.user_id
    JOIN UserProfiles up ON u.user_id = up.user_id
    LEFT JOIN (SELECT receiver_id, AVG(rating) as rating FROM RatingsAndReviews GROUP BY receiver_id) r ON u.user_id = r.receiver_id
    LEFT JOIN (SELECT receiver_id, COUNT(*) as reviews FROM RatingsAndReviews GROUP BY receiver_id) rr ON u.user_id = rr.receiver_id
    WHERE u.user_id != ? 
    AND u.role_id = 2 
    AND NOT EXISTS (
        SELECT 1 
        FROM GuideRequests 
        WHERE sender_id = ? 
        AND receiver_id = u.user_id
        AND status IN ('pending', 'accepted')
    )
    AND (
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
$stmt->bind_param("iidddi", $user_id, $user_id, $user_lat, $user_lng, $user_lat, $radius);
$stmt->execute();
$result = $stmt->get_result();

$guides = [];
while ($guide = $result->fetch_assoc()) {
    $guide['images'] = getGuideImages($guide['user_id'], $conn);
    $guides[] = $guide;
}

// Separate guides into two groups
$group1 = [];
$group2 = [];
foreach ($guides as $guide) {
    // Check if the guide has similar interests or activities
    $query = "
    SELECT 1 FROM UserInterests WHERE user_id = ? AND interest_id IN ($placeholders)
    UNION
    SELECT 1 FROM UserActivities WHERE user_id = ? AND activity_id IN ($placeholders)
    UNION
    SELECT 1 FROM Activities WHERE activity_id IN (SELECT activity_id FROM UserActivities WHERE user_id = ?) AND category_id IN ($placeholders)
    ";

    $merged_params = array_merge(
        [$guide['user_id']], 
        $user_preferences, 
        [$guide['user_id']], 
        $user_preferences, 
        [$guide['user_id']], 
        $user_preferences
    );

    $stmt = $conn->prepare($query);
    $types = str_repeat('i', count($merged_params));
    $stmt->bind_param($types, ...$merged_params);
    $stmt->execute();
    $result = $stmt->get_result();

    
    if ($result->num_rows > 0) {
        $group1[] = $guide;
    } else {
        $group2[] = $guide;
    }
}

// Calculate weighted rating
function calculate_weighted_rating($guide) {
    $reviews = $guide['reviews'] ?? 0;
    $rating = $guide['rating'] ?? 0;
    return ($reviews * $rating + 5 * 10) / ($reviews + 10); // Example: using 10 as a smoothing factor
}

// Sort guides by weighted rating
usort($group1, function ($a, $b) {
    return calculate_weighted_rating($b) <=> calculate_weighted_rating($a);
});

usort($group2, function ($a, $b) {
    return calculate_weighted_rating($b) <=> calculate_weighted_rating($a);
});

// Combine groups
$combined_guides = array_merge($group1, $group2);

if (empty($combined_guides)) {
    $response['message'] .= " | No guides found within the radius.";
} else {
    $response['guides'] = $combined_guides;
    $response['message'] .= " | Guides found: " . json_encode($combined_guides);
}

$response['error'] = false;
echo json_encode($response);

function getGuideImages($user_id, $conn) {
    $query = "SELECT picture_path FROM UserPictures WHERE user_id = ?";
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
