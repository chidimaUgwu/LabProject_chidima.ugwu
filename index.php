<?php
// index.php
require_once 'config/session.php';

// If user is logged in, redirect to their dashboard
if (isLoggedIn()) {
    switch($_SESSION['role']) {
        case 'student':
            header("Location: " . BASE_URL . "student/dashboard.php");
            break;
        case 'faculty':
            header("Location: " . BASE_URL . "fi/dashboard.php");
            break;
        case 'instructor':
            header("Location: " . BASE_URL . "faculty/dashboard.php");
            break;
        default:
            header("Location: " . BASE_URL . "auth/login.php");
    }
    exit();
} else {
    // If not logged in, redirect to login page
    header("Location: " . BASE_URL . "auth/login.php");
    exit();
}
?>
