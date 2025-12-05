<?php
// C:\xampp\htdocs\Attandance\auth\process_login.php

require_once '../config/database.php';
require_once '../config/session.php';

// Redirect if already logged in
redirectIfLoggedIn();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

// Get form data
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validate data
if (empty($email) || empty($password)) {
    header("Location: login.php?error=Email and password are required.");
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: login.php?error=Invalid email format.");
    exit();
}

// Check credentials
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT * FROM am_users WHERE email = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() === 1) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fname'] = $user['fname'];
            $_SESSION['lname'] = $user['lname'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['userid'] = $user['userid'];
            $_SESSION['phone'] = $user['phone'];
            $_SESSION['gender'] = $user['gender'];
            $_SESSION['dob'] = $user['dob'];
            $_SESSION['address'] = $user['address'];
            
            // Redirect based on role from database
            switch($user['role']) {
                case 'student':
                    header("Location: ../student/dashboard.php");
                    break;
                case 'faculty':
                    header("Location: ../fi/dashboard.php");
                    break;
                case 'instructor':
                    header("Location: ../faculty/dashboard.php");
                    break;
                default:
                    header("Location: login.php?error=Invalid user role in database.");
            }
            exit();
        } else {
            header("Location: login.php?error=Invalid password.");
            exit();
        }
    } else {
        header("Location: login.php?error=No account found with this email.");
        exit();
    }
    
} catch (PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    header("Location: login.php?error=Login failed. Please try again.");
    exit();
}
?>