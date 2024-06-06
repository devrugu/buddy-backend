<?php
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once '../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/* $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load(); */

include '../database/db_connection.php';

$authHeader = getAuthorizationHeader();
list($jwt) = sscanf($authHeader, 'Bearer %s');

$key = getenv('JWT_SECRET_KEY');
$decoded = JWT::decode($jwt, new Key($key, 'HS256'));
$country_id = $decoded->data->country_id;

$query = "SELECT location_id, location_name FROM locations WHERE country_id=$country_id ORDER BY location_name";
$result = $conn->query($query);

$locations = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $locations[] = $row;
    }
}

echo json_encode($locations);

function getAuthorizationHeader() {
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    return $headers;
}

$conn->close();
