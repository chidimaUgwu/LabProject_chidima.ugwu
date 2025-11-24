<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Optional: Role-based access control
$currentPage = basename($_SERVER['PHP_SELF']);
$userRole = $_SESSION['role'] ?? '';

// Prevent students from accessing faculty pages and vice versa
if (strpos($currentPage, 'Faculty') !== false && $userRole !== 'faculty') {
    header('Location: unauthorized.php');
    exit;
}

if (strpos($currentPage, 'Student') !== false && $userRole !== 'student') {
    header('Location: unauthorized.php');
    exit;
}
?>