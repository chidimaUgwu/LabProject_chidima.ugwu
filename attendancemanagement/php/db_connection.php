<?php
// Database configuration - using direct values for now
$host = 'localhost';
$user = 'root'; // Default XAMPP username
$password = ''; // Default XAMPP password (usually empty)
$database = 'attendancemanagement'; // Your database name

// Create connection
$conn = new mysqli($host, $user, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8
$conn->set_charset("utf8mb4");
?>


