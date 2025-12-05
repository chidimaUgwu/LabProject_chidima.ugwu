<?php
// C:\xampp\htdocs\Attandance\index.php
require_once 'config/session.php';

// If user is logged in, redirect to their dashboard
if (isLoggedIn()) {
    switch($_SESSION['role']) {
        case 'student':
            header("Location: student/dashboard.php");
            break;
        case 'faculty':
            header("Location: fi/dashboard.php");
            break;
        case 'instructor':
            header("Location: faculty/dashboard.php");
            break;
        default:
            header("Location: auth/login.php");
    }
    exit();
} else {
    // If not logged in, redirect to login page
    header("Location: auth/login.php");
    exit();
}
?>