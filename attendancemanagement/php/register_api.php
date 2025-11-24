<?php
header("Content-Type: application/json");
session_start();
require_once 'db_connection.php';

// Response helper
function respond($success, $message) {
    echo json_encode(["success" => $success, "message" => $message]);
    exit;
}

// Check request
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Invalid request method.");
}

// Collect fields
$fname = trim($_POST['fname'] ?? '');
$lname = trim($_POST['lname'] ?? '');
$dob = $_POST['dob'] ?? '';
$gender = $_POST['gender'] ?? '';
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$role = $_POST['role'] ?? '';
$password = $_POST['password'] ?? '';

// Validate
if (!$fname || !$lname || !$dob || !$gender || !$email || !$phone || !$address || !$role || !$password) {
    respond(false, "All fields are required.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, "Invalid email format.");
}

if (strlen($password) < 6) {
    respond(false, "Password must be at least 6 characters.");
}

// Check if email exists
$check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    respond(false, "Email already registered.");
}

$check->close();

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert user
$sql = "INSERT INTO users (first_name, last_name, dob, gender, email, phone, address, role, password_hash)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssssssss",
    $fname, $lname, $dob, $gender, $email, $phone, $address, $role, $hashed_password
);

if ($stmt->execute()) {
    respond(true, "Registration successful!");
} else {
    respond(false, "Database error: " . $stmt->error);
}

$stmt->close();
$conn->close();
?>
