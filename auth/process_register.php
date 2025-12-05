<?php
// C:\xampp\htdocs\Attandance\auth\process_register.php

require_once '../config/database.php';
require_once '../config/session.php';

// Redirect if already logged in
redirectIfLoggedIn();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
    exit();
}

// Get form data
$fname = trim($_POST['fname'] ?? '');
$lname = trim($_POST['lname'] ?? '');
$userid = trim($_POST['userid'] ?? '');
$dob = $_POST['dob'] ?? '';
$gender = $_POST['gender'] ?? '';
$email = trim($_POST['email'] ?? '');
$country_code = $_POST['country_code'] ?? '+233';
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$role = $_POST['role'] ?? '';
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirmPassword'] ?? '';

// Validate data
$errors = [];

// Auto Capitalize First and Last Names
$fname = ucwords(strtolower($fname));
$lname = ucwords(strtolower($lname));

// Student ID validation (exactly 8 digits)
if (!preg_match('/^\d{8}$/', $userid)) {
    $errors[] = "Student ID must contain exactly 8 digits.";
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format.";
}

// Phone number digits only
if (!preg_match('/^\d+$/', $phone)) {
    $errors[] = "Phone number must contain digits only.";
}

// Strong password validation
$strongPasswordPattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';
if (!preg_match($strongPasswordPattern, $password)) {
    $errors[] = "Password must be at least 8 characters, with uppercase, lowercase, number, and symbol.";
}

// Confirm password check
if ($password !== $confirmPassword) {
    $errors[] = "Passwords do not match!";
}

// Validate role
$validRoles = ['student', 'faculty', 'instructor'];
if (!in_array($role, $validRoles)) {
    $errors[] = "Please select a valid role.";
}

// If there are errors, redirect back with error message
if (!empty($errors)) {
    $errorMessage = urlencode(implode(" ", $errors));
    header("Location: register.php?error=" . $errorMessage);
    exit();
}

// Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Combine country code and phone
$fullPhone = $country_code . $phone;

// Insert into database
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if userid or email already exists
    $checkQuery = "SELECT id FROM am_users WHERE userid = ? OR email = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$userid, $email]);
    
    if ($checkStmt->rowCount() > 0) {
        header("Location: register.php?error=User ID or Email already exists.");
        exit();
    }
    
    // Insert new user
    $query = "INSERT INTO am_users (fname, lname, userid, dob, gender, email, phone, address, role, password) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        $fname, $lname, $userid, $dob, $gender, $email, 
        $fullPhone, $address, $role, $hashedPassword
    ]);
    
    echo "Parameters: ";
    print_r($params);
    echo "<br>";
    
    $result = $stmt->execute($params);
    
    if ($result) {
        $lastId = $db->lastInsertId();
        echo "Registration successful! User ID: $lastId<br>";
        
        // Show what was inserted
        $showQuery = "SELECT * FROM am_users WHERE id = ?";
        $showStmt = $db->prepare($showQuery);
        $showStmt->execute([$lastId]);
        $newUser = $showStmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<h3>User inserted successfully:</h3>";
        echo "<pre>";
        print_r($newUser);
        echo "</pre>";
        
        // Start session with success message (BUT DON'T LOG THEM IN!)
        $_SESSION['success_message'] = "Registration successful! Please login with your credentials.";
        header("Location: login.php");
        exit();
        
    } else {
        echo "Registration failed!<br>";
        echo "Error info: ";
        print_r($stmt->errorInfo());
        echo '<br><a href="register.php">Go back to registration</a>';
        exit();
    }
    
} catch (PDOException $e) {
    error_log("Registration error: " . $e->getMessage());
    header("Location: register.php?error=Registration failed. Please try again.");
    exit();
}
?>