<?php
require_once '../database/db_connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$query = "SELECT language_id, language_name FROM Languages ORDER BY language_name";
$result = $conn->query($query);

$languages = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $languages[] = $row;
    }
}

echo json_encode($languages);

$conn->close();
