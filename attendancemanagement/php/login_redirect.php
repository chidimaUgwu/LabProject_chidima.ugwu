<?php
session_start();

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    switch ($_SESSION['role']) {
        case 'student':
            header('Location: ../Student_Dashboard.php');
            break;
        case 'faculty':
            header('Location: ../Faculty_Dashboard.php');
            break;
        case 'instructor':
            header('Location: ../Instructor_Dashboard.php');
            break;
        case 'admin':
            header('Location: ../Admin_Dashboard.php');
            break;
        default:
            header('Location: ../login.php');
    }
    exit;
}
?>