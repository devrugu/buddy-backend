<?php
require_once '../database/db_connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Hobileri çek
$query = "SELECT interest_id, interest_name FROM interests ORDER BY interest_name";
$result = $conn->query($query);

$interests = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $interests[] = $row;
    }
}

echo json_encode($interests);

$conn->close();
