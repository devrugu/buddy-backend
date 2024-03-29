<?php
require_once '../database/db_connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$query = "SELECT education_level_id, education_level_name FROM EducationLevels ORDER BY education_level_name";
$result = $conn->query($query);

$educationLevels = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $educationLevels[] = $row;
    }
}

echo json_encode($educationLevels);

$conn->close();
