<?php
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once '../vendor/autoload.php';
include '../database/db_connection.php';

$userId = $_GET['user_id'];

// Fetch basic contact information
$query = "
    SELECT DISTINCT 
        CASE 
            WHEN sender_id = ? THEN receiver_id 
            ELSE sender_id 
        END AS contact_id,
        userprofiles.name AS contact_name,
        userprofiles.surname AS contact_surname,
        userroles.role_name AS role
    FROM messages
    JOIN userprofiles ON userprofiles.user_id = CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END
    JOIN users ON users.user_id = userprofiles.user_id
    JOIN userroles ON userroles.role_id = users.role_id
    WHERE sender_id = ? OR receiver_id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("iiii", $userId, $userId, $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();

$contacts = [];
while ($row = $result->fetch_assoc()) {
    $contacts[] = $row;
}

$stmt->close();

// Fetch profile pictures
foreach ($contacts as &$contact) {
    $contactId = $contact['contact_id'];
    $query = "
        SELECT picture_path
        FROM userpictures
        WHERE user_id = ? AND is_profile_picture = 1
        LIMIT 1
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $contactId);
    $stmt->execute();
    $result = $stmt->get_result();
    $picture = $result->fetch_assoc();
    $contact['picture_path'] = $picture ? $picture['picture_path'] : null;
    $stmt->close();
}

// Fetch last messages
foreach ($contacts as &$contact) {
    $contactId = $contact['contact_id'];
    $query = "
        SELECT content, timestamp
        FROM messages
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
        ORDER BY timestamp DESC
        LIMIT 1
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiii", $userId, $contactId, $contactId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $message = $result->fetch_assoc();
    $contact['last_message'] = $message ? $message['content'] : null;
    $contact['last_message_timestamp'] = $message ? $message['timestamp'] : null;
    $stmt->close();
}

echo json_encode($contacts);

$conn->close();
?>
