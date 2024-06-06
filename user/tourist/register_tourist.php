<?php
/* require_once '../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load(); */

include __DIR__ . '/../../database/db_connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");

// Gelen verileri al
$name = $_POST['name'];
$surname = $_POST['surname'];
$username = $_POST['username'];
$email = $_POST['email'];
$password = $_POST['password'];
$phoneNumber = $_POST['phoneNumber'];
$dateOfBirth = $_POST['dateOfBirth'];
$country = $_POST['country'];
$role_id = 1;
$is_deleted = 0;

// Ülke ID'sini bul
$query = "SELECT country_id FROM Countries WHERE country_name = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $country);
$stmt->execute();
$resultCountry = $stmt->get_result();
if ($countryRow = $resultCountry->fetch_assoc()) {
    $country_id = $countryRow['country_id'];
} else {
    echo json_encode(['error' => true, 'message' => 'Country not found.']);
    exit;
}

// Username, e-mail ve telefon numarası kontrolü
$queries = [
    "SELECT user_id FROM Users WHERE username = ?" => $username,
    "SELECT user_id FROM Users WHERE email = ?" => $email,
    "SELECT profile_id FROM UserProfiles WHERE phone_number = ?" => $phoneNumber
];

foreach ($queries as $query => $param) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $param);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $errorMessage = match ($param) {
            $username => 'Username already exists.',
            $email => 'Email already exists.',
            $phoneNumber => 'Phone number already exists.',
            default => 'Duplicate entry found.'
        };
        echo json_encode(['error' => true, 'message' => $errorMessage]);
        $stmt->close();
        $conn->close();
        exit;
    }
}

$queryUser = "INSERT INTO Users (username, email, password, role_id, is_deleted) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($queryUser);
$hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]); // random cost degeri verilebilir
$stmt->bind_param("ssssi", $username, $email, $hashed_password, $role_id, $is_deleted);

if ($stmt->execute()) {
    $last_id = $stmt->insert_id;

    $queryCountryUser = "INSERT INTO UserCountries (user_id, country_id) VALUES (?, ?)";
    $stmt = $conn->prepare($queryCountryUser);
    $stmt->bind_param("ii", $last_id, $country_id);
    if (!$stmt->execute()) {
        echo json_encode(['error' => true, 'message' => 'Error on country association.']);
        exit;
    }

    $queryProfile = "INSERT INTO UserProfiles (user_id, name, surname, phone_number, birth_date) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($queryProfile);
    $stmt->bind_param("issss", $last_id, $name, $surname, $phoneNumber, $dateOfBirth);
    if ($stmt->execute()) {
        echo json_encode(['error' => false, 'message' => 'User registered successfully']);
    } else {
        echo json_encode(['error' => true, 'message' => 'Error on profile creation: ' . $stmt->error]);
    }
} else {
    echo json_encode(['error' => true, 'message' => 'Error on user creation: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
