<?php
// Include database connection
require_once 'db_connection.php'; // Adjust path if needed

// Set content type to JSON
header('Content-Type: application/json');

// Get JSON input from the request body
$input = json_decode(file_get_contents('php://input'), true);

// Get form data
$first_name = $input['first_name'] ?? '';
$last_name = $input['last_name'] ?? '';
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';
$role = $input['role'] ?? 'student'; // Default to student
$dob = $input['dob'] ?? null;
$phone = $input['phone'] ?? null;
$gender = $input['gender'] ?? null;
$address = $input['address'] ?? null;

// Validate required fields
if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Check if email already exists
$check_email_sql = "SELECT user_id FROM users WHERE email = ?";
$check_stmt = $conn->prepare($check_email_sql);
$check_stmt->bind_param("s", $email);
$check_stmt->execute();
$check_stmt->store_result();

if ($check_stmt->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Email already exists']);
    $check_stmt->close();
    exit;
}
$check_stmt->close();

// Hash the password securely
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Insert user into database
$sql = "INSERT INTO users (first_name, last_name, email, password_hash, role, dob, phone, gender, address) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssssssss", $first_name, $last_name, $email, $password_hash, $role, $dob, $phone, $gender, $address);

if ($stmt->execute()) {
    // Get the newly created user ID
    $user_id = $stmt->insert_id;
    
    // If user is a student, add to students table
    if ($role === 'student') {
        $student_sql = "INSERT INTO students (student_id) VALUES (?)";
        $student_stmt = $conn->prepare($student_sql);
        $student_stmt->bind_param("i", $user_id);
        $student_stmt->execute();
        $student_stmt->close();
    }
    
    // If user is faculty, add to faculty table
    if ($role === 'faculty') {
        $faculty_sql = "INSERT INTO faculty (faculty_id) VALUES (?)";
        $faculty_stmt = $conn->prepare($faculty_sql);
        $faculty_stmt->bind_param("i", $user_id);
        $faculty_stmt->execute();
        $faculty_stmt->close();
    }
    
    echo json_encode(['success' => true, 'message' => 'User registered successfully', 'user_id' => $user_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>