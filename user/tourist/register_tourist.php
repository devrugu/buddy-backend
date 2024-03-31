<?php
require_once __DIR__ . '/../../database/db_connection.php';

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
$country_id = $resultCountry->fetch_assoc()['country_id'];

// Kullanıcıyı Users tablosuna ekle
$queryUser = "INSERT INTO Users (username, email, password, role_id, is_deleted) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($queryUser);
$hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$stmt->bind_param("ssssi", $username, $email, $hashed_password, $role_id, $is_deleted);

if ($stmt->execute()) {
  $last_id = $stmt->insert_id;

  // Kullanıcının ülke bilgisini UserCountries tablosuna ekle
  $queryCountryUser = "INSERT INTO UserCountries (user_id, country_id) VALUES (?, ?)";
  $stmt = $conn->prepare($queryCountryUser);
  $stmt->bind_param("ii", $last_id, $country_id);
  $stmt->execute();

  // Kullanıcının profilini UserProfiles tablosuna ekle
  $queryProfile = "INSERT INTO UserProfiles (user_id, name, surname, phone_number, birth_date) VALUES (?, ?, ?, ?, ?)";
  $stmt = $conn->prepare($queryProfile);
  $stmt->bind_param("issss", $last_id, $name, $surname, $phoneNumber, $dateOfBirth);
  if ($stmt->execute()) {
    echo "User registered successfully";
  } else {
    echo "Error on profile creation: " . $stmt->error;
  }
} else {
  echo "Error on user creation: " . $stmt->error;
}

$stmt->close();
$conn->close();