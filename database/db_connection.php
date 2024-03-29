<?php
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");

$servername = "34.32.35.30"; // Google Cloud SQL instance'ınızın adresi
$username = "root";
$password = "$\QDm%4|)X>1ax2-";
$dbname = "buddy_database";

// MySQL'e bağlan
$conn = new mysqli($servername, $username, $password, $dbname);

// Bağlantı kontrolü
if ($conn->connect_error) {
    die("Bağlantı hatası: " . $conn->connect_error);
}