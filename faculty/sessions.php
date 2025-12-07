<?php
// C:\xampp\htdocs\Attandance\faculty\sessions.php
require_once '../config/session.php';
require_once '../config/database.php';
requireAuth();

// Check if user has faculty/instructor role
if (!isInstructor()) {
    header("Location: ../auth/logout.php");
    exit();
}
$message = '';
$error = '';

try {
    $database = new Database();
    $db = $database->getConnection();
    $user_id = $_SESSION['user_id'];
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            
            switch ($action) {
                case 'create':
                    // Create new session
                    $course_id = intval($_POST['course_id']);
                    $session_date = $_POST['session_date'];
                    $session_time = $_POST['session_time'];
                    $duration_minutes = intval($_POST['duration_minutes']);
                    $topic = trim($_POST['topic']);
                    $location = trim($_POST['location']);
                    $attendance_code = trim($_POST['attendance_code']);
                    
                    // Generate random code if not provided
                    if (empty($attendance_code)) {
                        $attendance_code = strtoupper(substr(md5(uniqid()), 0, 6));
                    }
                    
                    if (empty($course_id) || empty($session_date) || empty($session_time)) {
                        $error = 'Required fields are missing';
                    } else {
                        // Check if code already exists
                        $checkQuery = "SELECT id FROM am_sessions WHERE attendance_code = ?";
                        $checkStmt = $db->prepare($checkQuery);
                        $checkStmt->execute([$attendance_code]);
                        
                        if ($checkStmt->rowCount() > 0) {
                            $error = 'Attendance code already exists. Please use a different code.';
                        } else {
                            // Check if faculty owns the course
                            $courseCheck = "SELECT id FROM am_courses WHERE id = ? AND faculty_intern_id = ?";
                            $courseStmt = $db->prepare($courseCheck);
                            $courseStmt->execute([$course_id, $user_id]);
                            
                            if ($courseStmt->rowCount() > 0) {
                                $insertQuery = "INSERT INTO am_sessions 
                                              (course_id, session_date, session_time, duration_minutes, 
                                               topic, location, attendance_code, created_by, status) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'upcoming')";
                                $insertStmt = $db->prepare($insertQuery);
                                $result = $insertStmt->execute([
                                    $course_id,
                                    $session_date,
                                    $session_time,
                                    $duration_minutes,
                                    $topic,
                                    $location,
                                    $attendance_code,
                                    $user_id
                                ]);
                                
                                if ($result) {
                                    $message = 'Session created successfully! Attendance Code: ' . $attendance_code;
                                } else {
                                    $error = 'Failed to create session';
                                }
                            } else {
                                $error = 'You do not have permission to create sessions for this course';
                            }
                        }
                    }
                    break;
                    
                case 'update_status':
                    // Update session status
                    $session_id = intval($_POST['session_id']);
                    $status = $_POST['status'];
                    
                    $updateQuery = "UPDATE am_sessions SET status = ? WHERE id = ? AND created_by = ?";
                    $updateStmt = $db->prepare($updateQuery);
                    $result = $updateStmt->execute([$status, $session_id, $user_id]);
                    
                    if ($result) {
                        $message = 'Session status updated successfully!';
                    } else {
                        $error = 'Failed to update session status';
                    }
                    break;
                    
                case 'delete':
                    // Delete session
                    $session_id = intval($_POST['session_id']);
                    
                    // Check if attendance exists
                    $attendanceCheck = "SELECT id FROM am_attendance WHERE session_id = ?";
                    $attendanceStmt = $db->prepare($attendanceCheck);
                    $attendanceStmt->execute([$session_id]);
                    
                    if ($attendanceStmt->rowCount() > 0) {
                        $error = 'Cannot delete session with attendance records. Change status instead.';
                    } else {
                        $deleteQuery = "DELETE FROM am_sessions WHERE id = ? AND created_by = ?";
                        $deleteStmt = $db->prepare($deleteQuery);
                        $result = $deleteStmt->execute([$session_id, $user_id]);
                        
                        if ($result) {
                            $message = 'Session deleted successfully!';
                        } else {
                            $error = 'Failed to delete session';
                        }
                    }
                    break;
            }
        }
    }
    
    // Get faculty's courses for dropdown
    $coursesQuery = "SELECT id, course_code, course_name FROM am_courses 
                     WHERE faculty_intern_id = ? AND status = 'active'
                     ORDER BY course_code";
    $coursesStmt = $db->prepare($coursesQuery);
    $coursesStmt->execute([$user_id]);
    $courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get sessions with filters
    $course_filter = isset($_GET['course']) ? intval($_GET['course']) : '';
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    $date_filter = isset($_GET['date']) ? $_GET['date'] : '';
    
    $sessionsQuery = "SELECT 
                         s.*,
                         c.course_code,
                         c.course_name,
                         COUNT(a.id) as attendance_count,
                         GROUP_CONCAT(DISTINCT a.status ORDER BY a.status SEPARATOR ', ') as attendance_summary
                      FROM am_sessions s
                      JOIN am_courses c ON s.course_id = c.id
                      LEFT JOIN am_attendance a ON s.id = a.session_id
                      WHERE s.created_by = ?";
    
    $params = [$user_id];
    
    if ($course_filter) {
        $sessionsQuery .= " AND s.course_id = ?";
        $params[] = $course_filter;
    }
    
    if ($status_filter) {
        $sessionsQuery .= " AND s.status = ?";
        $params[] = $status_filter;
    }
    
    if ($date_filter) {
        $sessionsQuery .= " AND DATE(s.session_date) = ?";
        $params[] = $date_filter;
    }
    
    $sessionsQuery .= " GROUP BY s.id ORDER BY s.session_date DESC, s.session_time DESC";
    
    $sessionsStmt = $db->prepare($sessionsQuery);
    $sessionsStmt->execute($params);
    $sessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get session statistics
    $statsQuery = "SELECT 
                      COUNT(*) as total_sessions,
                      SUM(CASE WHEN status = 'upcoming' THEN 1 ELSE 0 END) as upcoming_count,
                      SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                      SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
                      (SELECT COUNT(DISTINCT session_id) FROM am_attendance 
                       WHERE session_id IN (SELECT id FROM am_sessions WHERE created_by = ?)) as sessions_with_attendance
                   FROM am_sessions WHERE created_by = ?";
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->execute([$user_id, $user_id]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Sessions management error: " . $e->getMessage());
    $courses = [];
    $sessions = [];
    $stats = ['total_sessions' => 0, 'upcoming_count' => 0, 'completed_count' => 0, 'cancelled_count' => 0, 'sessions_with_attendance' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Management - Faculty Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/style.css">
    <link rel="stylesheet" href="../styles/Student_Dashboard.css">
    <link rel="stylesheet" href="../styles/sessionSchedule.css">
    <style>
        /* Base Layout Styles */
        .top {
            background-color: #F2EAD3;
            width: 100%;
            height: 90px;
            display: flex;
            align-items: center;
            padding: 0 20px;
            position: fixed;
            top: 0;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            z-index: 1000;
            justify-content: space-between;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            gap: 65px;
        }
        
        .dashboard-title {
            color: #3B0270;
            font-size: 20px;
            font-weight: bold;
        }
        
        .user-info {
            margin-left: auto;
            flex-grow: 1;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }
        
        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }
        
        .user-details h4 {
            font-size: 1rem;
            margin-bottom: 2px;
        }
        
        .user-details p {
            font-size: 0.8rem;
            color: #666;
        }
        
        .board {
            display: flex;
            min-height: 100vh;
            padding-top: 90px;
        }
        
        nav {
            width: 250px;
            background-color: #344F1F;
            display: flex;
            flex-direction: column;
            padding: 40px 0;
            position: fixed;
            height: calc(100vh - 90px);
            overflow-y: auto;
        }
        
        nav a {
            color: #F2EAD3;
            text-decoration: none;
            font-size: 16px;
            padding: 12px 35px;
            transition: background-color 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        nav a i {
            width: 20px;
            text-align: center;
        }
        
        nav a:hover {
            background-color: #6e8959;
        }
        
        nav a.active {
            background-color: #6e8959;
        }
        
        #welcomeboard {
            flex: 1;
            padding: 30px;
            margin-left: 250px;
            background-color: #F2EAD3;
            min-height: calc(100vh - 90px);
        }
        
        .welcome-section {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 5px 5px 10px rgba(59, 2, 112, 0.1);
            margin-bottom: 25px;
        }
        
        .welcome-section h1 {
            margin-bottom: 20px;
            text-align: center;
            color: #3B0270;
            border-bottom: 2px solid #F4991A;
            padding-bottom: 15px;
        }
        
        /* Stats Container */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid #e9ecef;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #F4991A;
            margin-bottom: 5px;
        }
        
        .stat-card .label {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Messages */
        .message {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            text-align: center;
            font-weight: 500;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Filter Card */
        .filter-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin: 25px 0;
            border-left: 4px solid #3B0270;
        }
        
        .card-header {
            font-weight: bold;
            color: #344F1F;
            margin-bottom: 20px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 8px;
            font-weight: 500;
            color: #344F1F;
            font-size: 14px;
        }
        
        .form-group select,
        .form-group input {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            background: white;
            transition: border-color 0.3s;
        }
        
        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #3B0270;
            box-shadow: 0 0 0 2px rgba(59, 2, 112, 0.1);
        }
        
        /* Buttons */
        .btn {
            background-color: #F4991A;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
        
        .btn:hover {
            background-color: #e68a00;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .btn-primary {
            background-color: #3B0270;
        }
        
        .btn-primary:hover {
            background-color: #2a0152;
        }
        
        .btn-success {
            background-color: #28a745;
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        .btn-warning {
            background-color: #ffc107;
            color: #000;
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
        }
        
        .btn-danger {
            background-color: #dc3545;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-small {
            padding: 8px 12px;
            font-size: 12px;
        }
        
        /* Section Header */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 30px 0 20px 0;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .section-header h2 {
            color: #344F1F;
            margin: 0;
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Table */
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            background: white;
        }
        
        thead {
            background: linear-gradient(135deg, #5e9038, #344F1F);
        }
        
        thead tr {
            height: 60px;
        }
        
        th {
            color: #F2EAD3;
            text-align: left;
            padding: 15px 20px;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        tbody tr {
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.3s;
        }
        
        tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        td {
            padding: 15px 20px;
            color: #495057;
        }
        
        /* Status Badges */
        .session-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-upcoming {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-completed {
            background: #dcfce7;
            color: #166534;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .attendance-code {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px dashed #dee2e6;
            font-weight: 600;
            color: #3B0270;
            font-size: 13px;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #dee2e6;
            opacity: 0.7;
        }
        
        .empty-state h3 {
            color: #495057;
            margin-bottom: 10px;
            font-size: 20px;
        }
        
        .empty-state p {
            color: #6c757d;
            margin-bottom: 25px;
            font-size: 16px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* MODAL STYLES - FIXED SCROLLING ISSUE */
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(3px);
            overflow-y: auto;
            padding: 20px 0;
        }
        
        body.modal-open {
            overflow: hidden;
        }
        
        .modal-content {
            background-color: white;
            margin: 20px auto;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: modalFadeIn 0.3s;
        }
        
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-50px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }
        
        .modal-header h3 {
            color: #3B0270;
            margin: 0;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .close {
            color: #6c757d;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
            line-height: 1;
            padding: 0 5px;
        }
        
        .close:hover {
            color: #343a40;
        }
        
        .modal-body {
            max-height: calc(90vh - 150px);
            overflow-y: auto;
            padding-right: 5px;
        }
        
        .modal-body::-webkit-scrollbar {
            width: 6px;
        }
        
        .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .modal-body::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        
        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            position: sticky;
            bottom: 0;
            background: white;
            z-index: 10;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            nav {
                width: 220px;
            }
            
            #welcomeboard {
                margin-left: 220px;
                padding: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .board {
                flex-direction: column;
            }
            
            nav {
                width: 100%;
                height: auto;
                position: relative;
                padding: 20px 0;
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
                gap: 5px;
            }
            
            nav a {
                padding: 10px 15px;
                font-size: 14px;
            }
            
            nav a span {
                display: none;
            }
            
            #welcomeboard {
                margin-left: 0;
                padding: 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .stats-container {
                grid-template-columns: 1fr 1fr;
            }
            
            .section-header {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .section-header > div {
                display: flex;
                gap: 10px;
            }
            
            .action-buttons {
                flex-wrap: wrap;
            }
            
            .table-container {
                font-size: 13px;
            }
            
            th, td {
                padding: 10px 12px;
            }
            
            .modal-content {
                margin: 10px auto;
                padding: 20px;
                width: 95%;
            }
            
            .form-actions {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .form-actions button {
                flex: 1;
                min-width: 120px;
            }
        }
        
        @media (max-width: 576px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .top {
                padding: 0 10px;
                height: 70px;
            }
            
            .dashboard-title {
                font-size: 16px;
            }
            
            .logo-container {
                gap: 20px;
            }
            
            .user-info img {
                width: 32px;
                height: 32px;
            }
            
            .board {
                padding-top: 70px;
            }
            
            #welcomeboard {
                padding: 15px;
            }
            
            .welcome-section {
                padding: 20px;
            }
            
            .modal-content {
                padding: 15px;
            }
            
            .modal-header {
                margin-bottom: 15px;
                padding-bottom: 10px;
            }
        }
        
        /* Utility Classes */
        .d-flex {
            display: flex;
        }
        
        .gap-2 {
            gap: 8px;
        }
        
        .gap-3 {
            gap: 12px;
        }
        
        .gap-4 {
            gap: 16px;
        }
        
        .justify-between {
            justify-content: space-between;
        }
        
        .align-center {
            align-items: center;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-muted {
            color: #6c757d;
        }
        
        .fw-bold {
            font-weight: 600;
        }
        
        .mb-3 {
            margin-bottom: 20px;
        }
        
        .mb-4 {
            margin-bottom: 30px;
        }
        
        .mt-3 {
            margin-top: 20px;
        }
        
        .mt-4 {
            margin-top: 30px;
        }
        
        .p-3 {
            padding: 20px;
        }
        
        .p-4 {
            padding: 30px;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
    </style>
</head>
<body>
    <div class="top">
        <div class="logo-container">
            <img src="../images/logo.png" alt="Company Logo" srcset="">
            <h4 class="dashboard-title">FACULTY DASHBOARD</h4>
            <div class="user-info" style="margin-left: 500px; display: flex; align-items: center; gap: 10px;">
                <div class="user-avatar"><img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['fname'] . '+' . $_SESSION['lname']); ?>&background=4361ee&color=fff" alt="User"></div>
                <div>
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['fname'] . ' ' . $_SESSION['lname']); ?></div>
                    <div class="user-role"><?php echo getRoleDisplayName($_SESSION['role']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="board">
        <!-- Sidebar Navigation -->
        <nav>
            <a href="dashboard.php" data-section="dashboard"><i class="fas fa-home"></i> <span>Dashboard</span></a>
            <a href="courses.php" data-section="courses"><i class="fas fa-book"></i> <span>Course Management</span></a>
            <a href="sessions.php" class="active" data-section="sessions"><i class="fas fa-calendar-alt"></i> <span>Session Overview</span></a>
            <a href="attendance_reports.php" data-section="attendance"><i class="fas fa-clipboard-check"></i> <span>Attendance Reports</span></a>
            <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>  
        </nav>

        <!-- Main Content Area -->
        <div id="welcomeboard">
            <div class="welcome-section">
                <h1><i class="fas fa-calendar-alt"></i> Session Management</h1>
                
                <?php if ($message): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="value"><?php echo $stats['total_sessions']; ?></div>
                        <div class="label">Total Sessions</div>
                    </div>
                    <div class="stat-card">
                        <div class="value"><?php echo $stats['upcoming_count']; ?></div>
                        <div class="label">Upcoming</div>
                    </div>
                    <div class="stat-card">
                        <div class="value"><?php echo $stats['completed_count']; ?></div>
                        <div class="label">Completed</div>
                    </div>
                    <div class="stat-card">
                        <div class="value"><?php echo $stats['sessions_with_attendance']; ?></div>
                        <div class="label">With Attendance</div>
                    </div>
                </div>
                
                <!-- Filter Section -->
                <div class="filter-card">
                    <div class="card-header">
                        <i class="fas fa-filter"></i> Session Filters
                    </div>
                    <form method="GET" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="course">Course</label>
                                <select id="course" name="course">
                                    <option value="">All Courses</option>
                                    <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" <?php echo ($course_filter == $course['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_code']); ?> - <?php echo htmlspecialchars($course['course_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="upcoming" <?php echo ($status_filter == 'upcoming') ? 'selected' : ''; ?>>Upcoming</option>
                                    <option value="completed" <?php echo ($status_filter == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo ($status_filter == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="date">Date</label>
                                <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                            </div>
                        </div>
                        <div class="d-flex gap-3 align-center">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                            <?php if ($course_filter || $status_filter || $date_filter): ?>
                            <a href="sessions.php" class="btn">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <!-- Actions Header -->
                <div class="section-header">
                    <h2><i class="fas fa-list"></i> Sessions List</h2>
                    <div class="d-flex gap-3">
                        <button class="btn btn-warning" onclick="exportSessionsToCSV()">
                            <i class="fas fa-file-excel"></i> Export CSV
                        </button>
                        <button class="btn btn-success" onclick="openCreateModal()">
                            <i class="fas fa-plus-circle"></i> Schedule Session
                        </button>
                    </div>
                </div>
                
                <!-- Sessions List -->
                <?php if (empty($sessions)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Sessions Found</h3>
                    <p><?php echo ($course_filter || $status_filter || $date_filter) ? 'No sessions match your filters.' : 'Schedule your first session to get started.'; ?></p>
                    <button class="btn btn-success" onclick="openCreateModal()">
                        <i class="fas fa-calendar-plus"></i> Schedule Your First Session
                    </button>
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Date & Time</th>
                                <th>Topic & Location</th>
                                <th>Attendance Code</th>
                                <th>Attendance</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $session): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($session['course_code']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($session['course_name']); ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo date('M j, Y', strtotime($session['session_date'])); ?></div>
                                    <small class="text-muted"><?php echo date('h:i A', strtotime($session['session_time'])); ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($session['topic'] ?: 'General Session'); ?></div>
                                    <small class="text-muted"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($session['location'] ?: 'Not specified'); ?></small>
                                </td>
                                <td>
                                    <span class="attendance-code"><?php echo htmlspecialchars($session['attendance_code']); ?></span>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo $session['attendance_count']; ?> students</div>
                                    <?php if ($session['attendance_summary']): ?>
                                    <small class="text-muted"><?php echo htmlspecialchars($session['attendance_summary']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="session-status status-<?php echo $session['status']; ?>">
                                        <?php echo ucfirst($session['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-primary btn-small" onclick="openStatusModal(<?php echo $session['id']; ?>, '<?php echo $session['status']; ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-danger btn-small" onclick="confirmDelete(<?php echo $session['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <a href="session_attendance.php?id=<?php echo $session['id']; ?>" class="btn btn-small" style="background-color: #6c757d; color: white;">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Create Session Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Schedule New Session</h3>
                <span class="close" onclick="closeCreateModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group mb-3">
                        <label for="course_id">Course *</label>
                        <select id="course_id" name="course_id" required>
                            <option value="">Select a course</option>
                            <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>">
                                <?php echo htmlspecialchars($course['course_code']); ?> - <?php echo htmlspecialchars($course['course_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row mb-3">
                        <div class="form-group">
                            <label for="session_date">Date *</label>
                            <input type="date" id="session_date" name="session_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="session_time">Time *</label>
                            <input type="time" id="session_time" name="session_time" required>
                        </div>
                    </div>
                    
                    <div class="form-row mb-3">
                        <div class="form-group">
                            <label for="duration_minutes">Duration (minutes)</label>
                            <input type="number" id="duration_minutes" name="duration_minutes" value="60" min="15" max="240">
                        </div>
                        
                        <div class="form-group">
                            <label for="attendance_code">Attendance Code</label>
                            <div style="position: relative;">
                                <input type="text" id="attendance_code" name="attendance_code" maxlength="20" placeholder="Leave empty for auto-generate" style="width: 100%; padding-right: 40px;">
                                <button type="button" onclick="generateAttendanceCode()" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #6c757d; cursor: pointer;" title="Generate Code">
                                    <i class="fas fa-redo"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="topic">Topic</label>
                        <input type="text" id="topic" name="topic" maxlength="200" placeholder="Session topic or title">
                    </div>
                    
                    <div class="form-group mb-4">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" maxlength="100" placeholder="Classroom or venue">
                    </div>
                </form>
            </div>
            <div class="form-actions">
                <button type="button" class="btn" onclick="closeCreateModal()">Cancel</button>
                <button type="submit" class="btn btn-success" onclick="document.querySelector('#createModal form').submit()">Schedule Session</button>
            </div>
        </div>
    </div>
    
    <!-- Update Status Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Update Session Status</h3>
                <span class="close" onclick="closeStatusModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" id="status_session_id" name="session_id">
                    
                    <div class="form-group mb-4">
                        <label for="status">Status</label>
                        <select id="status" name="status" required class="form-control">
                            <option value="upcoming">Upcoming</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="form-actions">
                <button type="button" class="btn" onclick="closeStatusModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" onclick="document.querySelector('#statusModal form').submit()">Update Status</button>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h3>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" id="delete_session_id" name="session_id">
                    
                    <div class="text-center p-4">
                        <i class="fas fa-exclamation-circle fa-3x text-danger mb-3" style="opacity: 0.7;"></i>
                        <h4 class="mb-2">Are you sure?</h4>
                        <p class="text-muted">This action cannot be undone. The session will be permanently deleted.</p>
                        <p class="text-muted"><small>Note: Sessions with attendance records cannot be deleted.</small></p>
                    </div>
                </form>
            </div>
            <div class="form-actions">
                <button type="button" class="btn" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" class="btn btn-danger" onclick="document.querySelector('#deleteModal form').submit()">Delete Session</button>
            </div>
        </div>
    </div>
    
    <script>
        // Modal Functions - FIXED SCROLLING ISSUE
        function openCreateModal() {
            // Set default date to tomorrow
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            document.getElementById('session_date').valueAsDate = tomorrow;
            
            // Set default time to next hour
            const nextHour = new Date();
            nextHour.setHours(nextHour.getHours() + 1, 0, 0, 0);
            document.getElementById('session_time').value = nextHour.toTimeString().substr(0, 5);
            
            document.getElementById('createModal').style.display = 'block';
            document.body.classList.add('modal-open');
        }
        
        function closeCreateModal() {
            document.getElementById('createModal').style.display = 'none';
            document.body.classList.remove('modal-open');
        }
        
        function openStatusModal(id, currentStatus) {
            document.getElementById('status_session_id').value = id;
            document.getElementById('status').value = currentStatus;
            document.getElementById('statusModal').style.display = 'block';
            document.body.classList.add('modal-open');
        }
        
        function closeStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
            document.body.classList.remove('modal-open');
        }
        
        function confirmDelete(id) {
            document.getElementById('delete_session_id').value = id;
            document.getElementById('deleteModal').style.display = 'block';
            document.body.classList.add('modal-open');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            document.body.classList.remove('modal-open');
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target == modal) {
                    modal.style.display = 'none';
                    document.body.classList.remove('modal-open');
                }
            });
        }
        
        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
                document.body.classList.remove('modal-open');
            }
        });
        
        // Export to CSV
        function exportSessionsToCSV() {
            let csv = 'Course Code,Course Name,Date,Time,Duration,Topic,Location,Attendance Code,Status,Attendance Count\n';
            
            <?php foreach ($sessions as $session): ?>
            csv += '"<?php echo addslashes($session['course_code']); ?>",' +
                   '"<?php echo addslashes($session['course_name']); ?>",' +
                   '"<?php echo $session['session_date']; ?>",' +
                   '"<?php echo $session['session_time']; ?>",' +
                   '<?php echo $session['duration_minutes']; ?>,' +
                   '"<?php echo addslashes($session['topic'] ?? ''); ?>",' +
                   '"<?php echo addslashes($session['location'] ?? ''); ?>",' +
                   '"<?php echo $session['attendance_code']; ?>",' +
                   '"<?php echo $session['status']; ?>",' +
                   '<?php echo $session['attendance_count']; ?>\n';
            <?php endforeach; ?>
            
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'sessions_export_<?php echo date('Y-m-d'); ?>.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
        
        // Generate random attendance code
        function generateAttendanceCode() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let code = '';
            for (let i = 0; i < 6; i++) {
                code += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('attendance_code').value = code;
        }
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Set filter dates
            const dateInput = document.getElementById('date');
            if (dateInput && !dateInput.value) {
                dateInput.valueAsDate = new Date();
            }
            
            // Add animation to table rows
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach((row, index) => {
                row.style.animationDelay = `${index * 0.05}s`;
                row.style.animation = 'fadeIn 0.3s ease forwards';
                row.style.opacity = '0';
            });
            
            // Add CSS for fadeIn animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(10px); }
                    to { opacity: 1; transform: translateY(0); }
                }
            `;
            document.head.appendChild(style);
            
            // Trigger animations
            setTimeout(() => {
                tableRows.forEach(row => {
                    row.style.opacity = '1';
                });
            }, 100);
        });
    </script>
</body>
</html>
