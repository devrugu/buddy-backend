<?php
require_once '../database/db_connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$query = "SELECT profession_id, profession_name FROM Professions ORDER BY profession_name";
$result = $conn->query($query);

$professions = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $professions[] = $row;
    }
}

echo json_encode($professions);

$conn->close();