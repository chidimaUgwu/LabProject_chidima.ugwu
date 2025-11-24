<?php
// Start session
session_start();

// Destroy all session data
session_unset();
session_destroy();

// Set content type to JSON
header('Content-Type: application/json');

// Return success response
echo json_encode(['logout' => true]);
?>