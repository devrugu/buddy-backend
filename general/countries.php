<?php
// Veritabanı bağlantı dosyanızı dahil edin
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");

require_once __DIR__ . '/../database/db_connection.php';

$query = "SELECT country_name FROM Countries"; // Ülkelerin olduğu sütunu ve tabloyu doğrula
$result = mysqli_query($conn, $query);

$countries = array();

if (mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) {
        $countries[] = $row['country_name'];
    }

    // JSON formatında ülkeleri döndür
    echo json_encode($countries);
} else {
    echo "No countries found";
}