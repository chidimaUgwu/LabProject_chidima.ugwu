<?php
// Ensure we return JSON even on errors
header('Content-Type: application/json');

// Add CORS headers if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
session_start();
require_once 'db_connection.php';


header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check database connection
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

try {
    $sql = "SELECT user_id, first_name, last_name, email, password_hash, role 
            FROM users WHERE email = ? ";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    // REMOVE THIS LINE: echo $result;

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['logged_in'] = true;
            $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_id'] = $user['user_id'];

            echo json_encode([
                'success' => true,
                'role' => $user['role'],
                'username' => $_SESSION['username'],
                'redirect' => getRedirectUrl($user['role'])
            ]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
    
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function getRedirectUrl($role) 
{
    switch ($role) {
        case 'student':
            return 'Student_Dashboard.php';
        case 'faculty':
            return 'Faculty_Dashboard.php';
        case 'instructor':
            return 'Instructor_Dashboard.php';
        case 'admin':
            return 'Admin_Dashboard.php';
        default:
            return 'login.php';
    }
}
$stmt->close();
$conn->close();
?>


