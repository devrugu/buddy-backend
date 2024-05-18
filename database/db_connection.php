<?php
/* require '../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable("../");
$dotenv->load(); */

/* $servername = $_ENV['SERVER_N'];
$username = $_ENV['USER_NAME'];
$password = $_ENV['PASSWORD'];
$dbname = $_ENV['DATABASE_NAME']; */

$servername = '34.32.35.30';
$username = 'root';
$password = '159753';
$dbname = 'buddy_database';

// MySQL'e bağlan
$conn = new mysqli($servername, $username, $password, $dbname);
// var_dump($_ENV);
// Bağlantı kontrolü
if ($conn->connect_error) {
    die("Bağlantı hatası: " . $conn->connect_error);
}