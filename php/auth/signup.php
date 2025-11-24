<?php
header('Content-Type: application/json');
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get and validate input
$firstName = trim($_POST['fname'] ?? '');
$lastName = trim($_POST['lname'] ?? '');
$email = trim($_POST['uemail'] ?? '');
$password = $_POST['password'] ?? '';
$role = trim($_POST['role'] ?? '');
$studentId = trim($_POST['userid'] ?? '');
$dob = $_POST['dob'] ?? '';

// Basic validation
if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($role)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

try {
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        exit;
    }

    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Insert into users table
    $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password_hash, role, dob) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$firstName, $lastName, $email, $passwordHash, $role, $dob]);
    
    $userId = $pdo->lastInsertId();

    // Insert into specific role table
    if ($role === 'student') {
        $stmt = $pdo->prepare("INSERT INTO students (student_id) VALUES (?)");
        $stmt->execute([$userId]);
    } elseif ($role === 'faculty') {
        $stmt = $pdo->prepare("INSERT INTO faculty (faculty_id) VALUES (?)");
        $stmt->execute([$userId]);
    }

    echo json_encode(['success' => true, 'message' => 'Registration successful']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
