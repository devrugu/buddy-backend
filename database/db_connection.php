<?php

$servername = getenv('SERVER_NAME');
$username = getenv('USER_NAME');
$password = getenv('PASSWORD');
$dbname = getenv('DATABASE_NAME');
$port = getenv('PORT');

// MySQL'e bağlan
$conn = new mysqli($servername, $username, $password, $dbname, $port);
// Bağlantı kontrolü
if ($conn->connect_error) {
    die("Bağlantı hatası: " . $conn->connect_error);
}
