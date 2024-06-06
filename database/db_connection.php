<?php

/* $servername = getenv('SERVER_NAME'); */
$servername = 'monorail.proxy.rlwy.net';
$username = getenv('USER_NAME');
$password = getenv('PASSWORD');
$dbname = getenv('DATABASE_NAME');
$port = getenv('PORT');

// Debugging: Print environment variables
/* echo "Server Name: $servername<br>";
echo "Username: $username<br>";
echo "Password: $password<br>";
echo "Database Name: $dbname<br>";
echo "Port: $port<br>"; */

// MySQL'e bağlan
$conn = new mysqli($servername, $username, $password, $dbname, $port);
// Bağlantı kontrolü
if ($conn->connect_error) {
    die("Bağlantı hatası: " . $conn->connect_error);
}
