<?php
// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Debug script loaded...<br>";

// Test database connection
require_once '../config/database.php';
require_once '../config/session.php';

echo "Files included...<br>";

try {
    echo "Creating Database object...<br>";
    $database = new Database();
    echo "Getting connection...<br>";
    $db = $database->getConnection();
    echo "✅ Database connection successful!<br>";
    
    // Test query
    echo "Testing query...<br>";
    $stmt = $db->query("SELECT COUNT(*) as count FROM am_users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Found " . $result['count'] . " users in am_users table<br>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Full error details: <pre>";
    print_r($e);
    echo "</pre>";
}
?>
