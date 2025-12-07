<?php
// C:\xampp\htdocs\Attandance\faculty\courses.php
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
                    // Create new course
                    $course_code = trim($_POST['course_code']);
                    $course_name = trim($_POST['course_name']);
                    $course_description = trim($_POST['course_description']);
                    $credits = intval($_POST['credits']);
                    $max_students = intval($_POST['max_students']);
                    
                    if (empty($course_code) || empty($course_name)) {
                        $error = 'Course code and name are required';
                    } else {
                        $checkQuery = "SELECT id FROM am_courses WHERE course_code = ?";
                        $checkStmt = $db->prepare($checkQuery);
                        $checkStmt->execute([$course_code]);
                        
                        if ($checkStmt->rowCount() > 0) {
                            $error = 'Course code already exists';
                        } else {
                            $insertQuery = "INSERT INTO am_courses 
                                          (course_code, course_name, course_description, credits, max_students, faculty_intern_id, status) 
                                          VALUES (?, ?, ?, ?, ?, ?, 'active')";
                            $insertStmt = $db->prepare($insertQuery);
                            $result = $insertStmt->execute([
                                $course_code,
                                $course_name,
                                $course_description,
                                $credits,
                                $max_students,
                                $user_id
                            ]);
                            
                            if ($result) {
                                $message = 'Course created successfully!';
                            } else {
                                $error = 'Failed to create course';
                            }
                        }
                    }
                    break;
                    
                case 'update':
                    // Update existing course
                    $course_id = intval($_POST['course_id']);
                    $course_code = trim($_POST['course_code']);
                    $course_name = trim($_POST['course_name']);
                    $course_description = trim($_POST['course_description']);
                    $credits = intval($_POST['credits']);
                    $max_students = intval($_POST['max_students']);
                    $status = $_POST['status'];
                    
                    $updateQuery = "UPDATE am_courses 
                                   SET course_code = ?, course_name = ?, course_description = ?, 
                                       credits = ?, max_students = ?, status = ?, updated_at = NOW()
                                   WHERE id = ? AND faculty_intern_id = ?";
                    $updateStmt = $db->prepare($updateQuery);
                    $result = $updateStmt->execute([
                        $course_code,
                        $course_name,
                        $course_description,
                        $credits,
                        $max_students,
                        $status,
                        $course_id,
                        $user_id
                    ]);
                    
                    if ($result) {
                        $message = 'Course updated successfully!';
                    } else {
                        $error = 'Failed to update course';
                    }
                    break;
                    
                case 'delete':
                    // Delete course (soft delete by changing status)
                    $course_id = intval($_POST['course_id']);
                    
                    $deleteQuery = "UPDATE am_courses SET status = 'inactive' WHERE id = ? AND faculty_intern_id = ?";
                    $deleteStmt = $db->prepare($deleteQuery);
                    $result = $deleteStmt->execute([$course_id, $user_id]);
                    
                    if ($result) {
                        $message = 'Course deactivated successfully!';
                    } else {
                        $error = 'Failed to deactivate course';
                    }
                    break;
            }
        }
    }
    
    // Get all courses for this faculty
    $coursesQuery = "SELECT 
                        c.*,
                        COUNT(DISTINCT e.id) as enrolled_count,
                        COUNT(DISTINCT s.id) as session_count
                     FROM am_courses c
                     LEFT JOIN am_enrollments e ON c.id = e.course_id AND e.status = 'approved'
                     LEFT JOIN am_sessions s ON c.id = s.course_id
                     WHERE c.faculty_intern_id = ?
                     GROUP BY c.id
                     ORDER BY c.created_at DESC";
    $coursesStmt = $db->prepare($coursesQuery);
    $coursesStmt->execute([$user_id]);
    $courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $statsQuery = "SELECT 
                      COUNT(*) as total_courses,
                      SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_courses,
                      SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_courses,
                      (SELECT COUNT(DISTINCT student_id) FROM am_enrollments e 
                       JOIN am_courses c ON e.course_id = c.id 
                       WHERE c.faculty_intern_id = ? AND e.status = 'approved') as total_students
                   FROM am_courses 
                   WHERE faculty_intern_id = ?";
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->execute([$user_id, $user_id]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Courses management error: " . $e->getMessage());
    $courses = [];
    $stats = ['total_courses' => 0, 'active_courses' => 0, 'inactive_courses' => 0, 'total_students' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Management - Faculty Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/style.css">
    <link rel="stylesheet" href="../styles/Student_Dashboard.css">
    <link rel="stylesheet" href="../styles/sessionSchedule.css">
    <style>
        /* Same base styles as dashboard */
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
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .section-header h2 {
            color: #344F1F;
            margin: 0;
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
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
        
        .btn-success {
            background-color: #28a745;
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        .btn-danger {
            background-color: #dc3545;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-primary {
            background-color: #007bff;
        }
        
        .btn-primary:hover {
            background-color: #0069d9;
        }
        
        .btn-warning {
            background-color: #ffc107;
            color: #000;
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
        }
        
        .btn-small {
            padding: 8px 12px;
            font-size: 12px;
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #344F1F;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
            background: white;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3B0270;
            box-shadow: 0 0 0 2px rgba(59, 2, 112, 0.1);
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
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
        
        /* Table Styles */
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
        .course-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-active {
            background: #dcfce7;
            color: #166534;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
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
            <a href="courses.php" class="active" data-section="courses"><i class="fas fa-book"></i> <span>Course Management</span></a>
            <a href="sessions.php" data-section="sessions"><i class="fas fa-calendar-alt"></i> <span>Session Overview</span></a>
            <a href="attendance_reports.php" data-section="attendance"><i class="fas fa-clipboard-check"></i> <span>Attendance Reports</span></a>
            <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>  
        </nav>

        <!-- Main Content Area -->
        <div id="welcomeboard">
            <div class="welcome-section">
                <h1><i class="fas fa-book"></i> Course Management</h1>
                
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
                
                <!-- Statistics -->
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="value"><?php echo $stats['total_courses']; ?></div>
                        <div class="label">Total Courses</div>
                    </div>
                    <div class="stat-card">
                        <div class="value"><?php echo $stats['active_courses']; ?></div>
                        <div class="label">Active Courses</div>
                    </div>
                    <div class="stat-card">
                        <div class="value"><?php echo $stats['inactive_courses']; ?></div>
                        <div class="label">Inactive Courses</div>
                    </div>
                    <div class="stat-card">
                        <div class="value"><?php echo $stats['total_students']; ?></div>
                        <div class="label">Total Students</div>
                    </div>
                </div>
                
                <!-- Export and Create Buttons -->
                <div class="section-header">
                    <h2><i class="fas fa-list"></i> My Courses</h2>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn btn-warning" onclick="exportCoursesToCSV()">
                            <i class="fas fa-file-excel"></i> Export CSV
                        </button>
                        <button class="btn btn-success" onclick="openCreateModal()">
                            <i class="fas fa-plus-circle"></i> Create New Course
                        </button>
                    </div>
                </div>
                
                <!-- Courses List -->
                <?php if (empty($courses)): ?>
                <div class="empty-state">
                    <i class="fas fa-book fa-3x"></i>
                    <h3>No Courses Found</h3>
                    <p>You haven't created any courses yet. Create your first course to get started.</p>
                    <button class="btn btn-success" onclick="openCreateModal()">
                        <i class="fas fa-plus-circle"></i> Create Your First Course
                    </button>
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Description</th>
                                <th>Students</th>
                                <th>Sessions</th>
                                <th>Credits</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                <td><?php echo htmlspecialchars(substr($course['course_description'] ?? '', 0, 50) . '...'); ?></td>
                                <td><?php echo $course['enrolled_count']; ?></td>
                                <td><?php echo $course['session_count']; ?></td>
                                <td><?php echo $course['credits']; ?></td>
                                <td>
                                    <span class="course-status status-<?php echo $course['status']; ?>">
                                        <?php echo ucfirst($course['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-primary btn-small" onclick="openEditModal(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['course_code']); ?>', '<?php echo htmlspecialchars($course['course_name']); ?>', '<?php echo htmlspecialchars($course['course_description']); ?>', <?php echo $course['credits']; ?>, <?php echo $course['max_students']; ?>, '<?php echo $course['status']; ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-danger btn-small" onclick="confirmDelete(<?php echo $course['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <a href="course_details.php?id=<?php echo $course['id']; ?>" class="btn btn-small" style="background-color: #6c757d; color: white;">
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
    
    <!-- Create Course Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Create New Course</h3>
                <span class="close" onclick="closeCreateModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group">
                        <label for="course_code">Course Code *</label>
                        <input type="text" id="course_code" name="course_code" required maxlength="20" placeholder="e.g., CS101">
                    </div>
                    
                    <div class="form-group">
                        <label for="course_name">Course Name *</label>
                        <input type="text" id="course_name" name="course_name" required maxlength="100" placeholder="e.g., Introduction to Programming">
                    </div>
                    
                    <div class="form-group">
                        <label for="course_description">Course Description</label>
                        <textarea id="course_description" name="course_description" placeholder="Describe the course content, objectives, etc."></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="credits">Credits</label>
                            <input type="number" id="credits" name="credits" value="3" min="1" max="10">
                        </div>
                        
                        <div class="form-group">
                            <label for="max_students">Maximum Students</label>
                            <input type="number" id="max_students" name="max_students" value="50" min="1" max="200">
                        </div>
                    </div>
                </form>
            </div>
            <div class="form-actions">
                <button type="button" class="btn" onclick="closeCreateModal()">Cancel</button>
                <button type="submit" class="btn btn-success" onclick="document.querySelector('#createModal form').submit()">Create Course</button>
            </div>
        </div>
    </div>
    
    <!-- Edit Course Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Course</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" id="edit_course_id" name="course_id">
                    
                    <div class="form-group">
                        <label for="edit_course_code">Course Code *</label>
                        <input type="text" id="edit_course_code" name="course_code" required maxlength="20">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_course_name">Course Name *</label>
                        <input type="text" id="edit_course_name" name="course_name" required maxlength="100">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_course_description">Course Description</label>
                        <textarea id="edit_course_description" name="course_description"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_credits">Credits</label>
                            <input type="number" id="edit_credits" name="credits" min="1" max="10">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_max_students">Maximum Students</label>
                            <input type="number" id="edit_max_students" name="max_students" min="1" max="200">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_status">Course Status</label>
                        <select id="edit_status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="form-actions">
                <button type="button" class="btn" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" onclick="document.querySelector('#editModal form').submit()">Update Course</button>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Confirm Deactivation</h3>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" id="delete_course_id" name="course_id">
                    
                    <div class="text-center p-4">
                        <i class="fas fa-exclamation-circle fa-3x text-danger mb-3" style="opacity: 0.7;"></i>
                        <h4 class="mb-2">Are you sure?</h4>
                        <p class="text-muted">You are about to deactivate this course. Students will no longer be able to enroll.</p>
                        <p class="text-muted"><small>Note: Existing enrollments will not be affected.</small></p>
                    </div>
                </form>
            </div>
            <div class="form-actions">
                <button type="button" class="btn" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" class="btn btn-danger" onclick="document.querySelector('#deleteModal form').submit()">Deactivate Course</button>
            </div>
        </div>
    </div>
    
    <script>
        // Modal Functions - FIXED SCROLLING ISSUE
        function openCreateModal() {
            document.getElementById('createModal').style.display = 'block';
            document.body.classList.add('modal-open');
        }
        
        function closeCreateModal() {
            document.getElementById('createModal').style.display = 'none';
            document.body.classList.remove('modal-open');
        }
        
        function openEditModal(id, code, name, description, credits, maxStudents, status) {
            document.getElementById('edit_course_id').value = id;
            document.getElementById('edit_course_code').value = code;
            document.getElementById('edit_course_name').value = name;
            document.getElementById('edit_course_description').value = description;
            document.getElementById('edit_credits').value = credits;
            document.getElementById('edit_max_students').value = maxStudents;
            document.getElementById('edit_status').value = status;
            document.getElementById('editModal').style.display = 'block';
            document.body.classList.add('modal-open');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.body.classList.remove('modal-open');
        }
        
        function confirmDelete(id) {
            document.getElementById('delete_course_id').value = id;
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
        function exportCoursesToCSV() {
            let csv = 'Course Code,Course Name,Description,Credits,Max Students,Status,Enrolled Students,Sessions,Created Date\n';
            
            <?php foreach ($courses as $course): ?>
            csv += '"<?php echo addslashes($course['course_code']); ?>",' +
                   '"<?php echo addslashes($course['course_name']); ?>",' +
                   '"<?php echo addslashes($course['course_description'] ?? ''); ?>",' +
                   '<?php echo $course['credits']; ?>,' +
                   '<?php echo $course['max_students']; ?>,' +
                   '"<?php echo $course['status']; ?>",' +
                   '<?php echo $course['enrolled_count']; ?>,' +
                   '<?php echo $course['session_count']; ?>,' +
                   '"<?php echo $course['created_at']; ?>"\n';
            <?php endforeach; ?>
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'courses_export_<?php echo date('Y-m-d'); ?>.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
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
