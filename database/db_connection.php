<?php
/* require '../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable("../");
$dotenv->load(); */

$servername = getenv('SERVER_NAME');
$username = getenv('USER_NAME');
$password = getenv('PASSWORD');
$dbname = getenv('DATABASE_NAME');

/* $servername = 'localhost';
$username = 'root';
$password = '';
$dbname = 'buddy_database'; */

// MySQL'e bağlan
$conn = new mysqli($servername, $username, $password, $dbname);
// Bağlantı kontrolü
if ($conn->connect_error) {
    die("Bağlantı hatası: " . $conn->connect_error);
}
