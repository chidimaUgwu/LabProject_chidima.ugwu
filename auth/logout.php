<?php
// C:\xampp\htdocs\Attandance\auth\logout.php

require_once '../config/session.php';

// Clear all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>