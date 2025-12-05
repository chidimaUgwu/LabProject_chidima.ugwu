<?php
// C:\xampp\htdocs\Attandance\config\session.php

session_start();

// Define base URL
define('BASE_URL', 'http://localhost/Attandance/');
define('BASE_PATH', dirname(__DIR__) . '/');

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user has specific role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Redirect to login if not authenticated
function requireAuth() {
    if (!isLoggedIn()) {
        // Store the page they tried to access
        $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
        header("Location: " . BASE_URL . "auth/login.php");
        exit();
    }
}

// Redirect to dashboard if already logged in
function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        $role = $_SESSION['role'] ?? 'student';
        $dashboard = getDashboardForRole($role);
        header("Location: " . BASE_URL . $dashboard);
        exit();
    }
}

// Get dashboard URL for a role
function getDashboardForRole($role) {
    switch($role) {
        case 'student':
            return 'student/dashboard.php';
        case 'faculty':
            return 'fi/dashboard.php';
        case 'instructor':
            return 'faculty/dashboard.php';
        default:
            return 'auth/login.php';
    }
}

// Get user's full name
function getUserFullName() {
    if (isset($_SESSION['fname']) && isset($_SESSION['lname'])) {
        return $_SESSION['fname'] . ' ' . $_SESSION['lname'];
    }
    return 'User';
}

// Get user's role display name
function getRoleDisplayName($role) {
    $roles = [
        'student' => 'Student',
        'faculty' => 'Faculty Intern',
        'instructor' => 'Instructor'
    ];
    return $roles[$role] ?? 'User';
}

// Redirect back after login
function redirectAfterLogin() {
    if (isset($_SESSION['redirect_to'])) {
        $redirect = $_SESSION['redirect_to'];
        unset($_SESSION['redirect_to']);
        header("Location: " . $redirect);
        exit();
    } else {
        // Default to dashboard
        redirectIfLoggedIn();
    }
}

// Helper functions for role checks
function isFacultyIntern() {
    return hasRole('faculty');
}

function isStudent() {
    return hasRole('student');
}

function isInstructor() {
    return hasRole('instructor');
}
?>