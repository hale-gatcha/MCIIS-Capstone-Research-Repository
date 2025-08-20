<?php
require_once '../config.php'; // Use the config file for DB connection

// Hash the passwords before inserting
$adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
$staffPassword = password_hash('staff123', PASSWORD_DEFAULT);

// Prepare the SQL statement
$stmt = $conn->prepare("INSERT INTO User (
    studentID, firstName, middleName, lastName, contactNumber, email, role, password
) VALUES
    (NULL, ?, ?, ?, ?, ?, ?, ?),
    (NULL, ?, ?, ?, ?, ?, ?, ?)
");

// Assign values to variables for binding
$firstName1 = 'Elah Marvinelie';
$middleName1 = 'D.';
$lastName1 = 'Menil';
$contactNumber1 = '09123456789';
$email1 = 'emdmenil00759@usep.edu.ph';
$role1 = 'Administrator';

$firstName2 = 'Gloren Joy';
$middleName2 = 'E.';
$lastName2 = 'Roque';
$contactNumber2 = '09987654321';
$email2 = 'gjeroque00800@usep.edu.ph';
$role2 = 'MCIIS Staff';

// Bind parameters using variables
$stmt->bind_param(
    "sssssss" . "sssssss",
    $firstName1, $middleName1, $lastName1, $contactNumber1, $email1, $role1, $adminPassword,
    $firstName2, $middleName2, $lastName2, $contactNumber2, $email2, $role2, $staffPassword
);

if ($stmt->execute()) {
    echo "User table populated successfully.<br>";
} else {
    echo "Error inserting into User table: " . $stmt->error . "<br>";
}
$stmt->close();

$conn->close();
?>