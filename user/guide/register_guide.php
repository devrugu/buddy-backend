<?php
/* require_once '../../vendor/autoload.php'; */

/* $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load(); */

include __DIR__ . '/../../database/db_connection.php';

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
$tc_id = $_POST['tcNo']; // TC No alınıyor
$role_id = 2; // Guide rol id
$is_deleted = 0;

// Ülke ID'sini bul
$query = "SELECT country_id FROM countries WHERE country_name = ?";
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
  "SELECT user_id FROM users WHERE username = ?" => $username,
  "SELECT user_id FROM users WHERE email = ?" => $email,
  "SELECT profile_id FROM userprofiles WHERE phone_number = ?" => $phoneNumber,
  "SELECT profile_id FROM userprofiles WHERE tc_id = ?" => $tc_id
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
          $tc_id => 'TC ID already exists.',
          default => 'Duplicate entry found.'
      };
      echo json_encode(['error' => true, 'message' => $errorMessage]);
      $stmt->close();
      $conn->close();
      exit;
  }
}

// Kullanıcıyı Users tablosuna ekle
$queryUser = "INSERT INTO users (username, email, password, role_id, is_deleted) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($queryUser);
$hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$stmt->bind_param("ssssi", $username, $email, $hashed_password, $role_id, $is_deleted);

if ($stmt->execute()) {
  $last_id = $stmt->insert_id;

  // Kullanıcının ülke bilgisini UserCountries tablosuna ekle
  $queryCountryUser = "INSERT INTO usercountries (user_id, country_id) VALUES (?, ?)";
  $stmt = $conn->prepare($queryCountryUser);
  $stmt->bind_param("ii", $last_id, $country_id);
  if (!$stmt->execute()) {
      echo json_encode(['error' => true, 'message' => 'Error on country association.']);
      exit;
  }

  // Kullanıcının profilini UserProfiles tablosuna ekle
  $queryProfile = "INSERT INTO userprofiles (user_id, name, surname, phone_number, birth_date, tc_id) VALUES (?, ?, ?, ?, ?, ?)";
  $stmt = $conn->prepare($queryProfile);
  $stmt->bind_param("isssss", $last_id, $name, $surname, $phoneNumber, $dateOfBirth, $tc_id);
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