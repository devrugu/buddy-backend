<?php
require_once '../database/db_connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");

// Aktivite kategorilerini ve her kategoriye ait aktiviteleri çekme
$query = "
    SELECT ac.category_id, ac.category_name, a.activity_id, a.activity_name
    FROM activitycategories ac
    LEFT JOIN activities a ON ac.category_id = a.category_id
    ORDER BY ac.category_id, a.activity_name";

$result = $conn->query($query);

$activities = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $categoryId = $row['category_id'];
        $categoryName = $row['category_name'];
        $activityId = $row['activity_id'];
        $activityName = $row['activity_name'];

        // Kategoriye ilk defa rastlanıyorsa, kategoriyi ve ilk aktiviteyi ekle
        if (!isset($activities[$categoryId])) {
            $activities[$categoryId] = [
                'category_id' => $categoryId,
                'category_name' => $categoryName,
                'activities' => []
            ];
        }
        // Aktivite varsa, mevcut kategoriye ekle
        if ($activityId !== null) {
            $activities[$categoryId]['activities'][] = [
                'activity_id' => $activityId,
                'activity_name' => $activityName
            ];
        }
    }
}

echo json_encode(array_values($activities));

$conn->close();
