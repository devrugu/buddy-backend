<?php
require_once '../database/db_connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$query = "SELECT location_id, location_name FROM Locations ORDER BY location_name";
$result = $conn->query($query);

$locations = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $locations[] = $row;
    }
}

echo json_encode($locations);

$conn->close();
