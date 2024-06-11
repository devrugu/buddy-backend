<?php
require_once '../database/db_connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");

$input = json_decode(file_get_contents('php://input'), true);
$senderId = $input['sender_id'];
$receiverId = $input['receiver_id'];
$content = $input['content'];

// Veritabanında sender_id ve receiver_id varlığını kontrol et
function checkUserExists($conn, $userId) {
    $query = "SELECT COUNT(*) FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        error_log("Failed to prepare statement: " . $conn->error);
        return false;
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $count = 0; // Initialize $count with a default value
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count > 0;
}

if (!checkUserExists($conn, $senderId)) {
    error_log("Sender ID ($senderId) does not exist in users table.");
    echo json_encode(["success" => false, "message" => "Sender ID does not exist."]);
    exit();
}

if (!checkUserExists($conn, $receiverId)) {
    error_log("Receiver ID ($receiverId) does not exist in users table.");
    echo json_encode(["success" => false, "message" => "Receiver ID does not exist."]);
    exit();
}

$query = "INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)";
$stmt = $conn->prepare($query);
if ($stmt === false) {
    error_log("Failed to prepare statement: " . $conn->error);
    echo json_encode(["success" => false, "message" => "Failed to prepare statement."]);
    exit();
}
$stmt->bind_param("iis", $senderId, $receiverId, $content);
if (!$stmt->execute()) {
    error_log("Failed to execute statement: " . $stmt->error);
    echo json_encode(["success" => false, "message" => "Message could not be sent."]);
} else {
    echo json_encode(["success" => true]);
}
$stmt->close();
$conn->close();
?>
