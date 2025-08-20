<?php
$servername = "localhost";
$username = "root";
$password = ""; // Changed to your MySQL root password
$dbname = 'repositups'; 
$port = 3306; 

// Create PDO connection
$dsn = "mysql:host=$servername;dbname=$dbname;port=$port;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Add MySQLi connection for scripts that use $conn
$conn = new mysqli($servername, $username, $password, $dbname, $port);
if ($conn->connect_error) {
    die("MySQLi connection failed: " . $conn->connect_error);
}
?>