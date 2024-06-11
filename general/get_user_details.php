<?php
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once '../vendor/autoload.php';
include '../database/db_connection.php';

$userId = $_GET['user_id'];

$query = "
    SELECT userprofiles.name, userprofiles.surname, userroles.role_name AS role, userpictures.picture_path
    FROM userprofiles
    JOIN users ON userprofiles.user_id = users.user_id
    JOIN userroles ON users.role_id = userroles.role_id
    LEFT JOIN userpictures ON userprofiles.user_id = userpictures.user_id AND userpictures.is_profile_picture = 1
    WHERE userprofiles.user_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$userDetails = $result->fetch_assoc();

echo json_encode($userDetails);

$stmt->close();
$conn->close();
?>
