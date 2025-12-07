<?php
// C:\xampp\htdocs\Attandance\student\course_details.php
require_once '../config/session.php';
require_once '../config/database.php';
requireAuth();

// Check if user has student role
if (!isStudent()) {
    header("Location: ../auth/logout.php");
    exit();
}

// Get course ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: courses.php");
    exit();
}

$course_id = intval($_GET['id']);

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get course details and check enrollment - FIXED QUERY
    $courseQuery = "SELECT 
                        c.*,
                        u.fname as faculty_fname,
                        u.lname as faculty_lname,
                        u.email as faculty_email,
                        e.status as enrollment_status,
                        e.approved_at
                     FROM am_courses c
                     JOIN am_users u ON c.faculty_intern_id = u.id
                     LEFT JOIN am_enrollments e ON c.id = e.course_id AND e.student_id = ?
                     WHERE c.id = ? AND c.status = 'active'";
    $courseStmt = $db->prepare($courseQuery);
    $courseStmt->execute([$_SESSION['user_id'], $course_id]);
    $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$course || $course['enrollment_status'] !== 'approved') {
        header("Location: courses.php");
        exit();
    }
    
    // Get sessions for this course
    $sessionsQuery = "SELECT 
                         s.*,
                         a.status as attendance_status,
                         a.attendance_time
                      FROM am_sessions s
                      LEFT JOIN am_attendance a ON s.id = a.session_id AND a.student_id = ?
                      WHERE s.course_id = ?
                      ORDER BY s.session_date DESC, s.session_time DESC";
    $sessionsStmt = $db->prepare($sessionsQuery);
    $sessionsStmt->execute([$_SESSION['user_id'], $course_id]);
    $sessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate attendance for this course
    $attendanceQuery = "SELECT 
                           COUNT(*) as total_sessions,
                           SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
                           SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                           SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count
                        FROM am_sessions s
                        LEFT JOIN am_attendance a ON s.id = a.session_id AND a.student_id = ?
                        WHERE s.course_id = ?";
    $attendanceStmt = $db->prepare($attendanceQuery);
    $attendanceStmt->execute([$_SESSION['user_id'], $course_id]);
    $attendanceStats = $attendanceStmt->fetch(PDO::FETCH_ASSOC);
    
    $totalSessions = $attendanceStats['total_sessions'] ?? 0;
    $presentCount = $attendanceStats['present_count'] ?? 0;
    $absentCount = $attendanceStats['absent_count'] ?? 0;
    $lateCount = $attendanceStats['late_count'] ?? 0;
    $attendanceRate = $totalSessions > 0 ? round(($presentCount / $totalSessions) * 100) : 0;
    
    // Get performance data if any
    $performanceQuery = "SELECT * FROM am_performance 
                        WHERE student_id = ? AND course_id = ?";
    $performanceStmt = $db->prepare($performanceQuery);
    $performanceStmt->execute([$_SESSION['user_id'], $course_id]);
    $performance = $performanceStmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Course details error: " . $e->getMessage());
    header("Location: courses.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['course_code']); ?> - Student Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/style.css">
    <link rel="stylesheet" href="../styles/Student_Dashboard.css">
    <link rel="stylesheet" href="../styles/sessionSchedule.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
        }
        
        .top {
            background: linear-gradient(135deg, #344F1F 0%, #2a3f19 100%);
            width: 100%;
            height: 80px;
            display: flex;
            align-items: center;
            padding: 0 30px;
            position: fixed;
            top: 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            z-index: 1000;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            gap: 20px;
            flex: 1;
        }
        
        .logo-container img {
            height: 45px;
            width: auto;
        }
        
        .dashboard-title {
            color: #F2EAD3;
            font-size: 22px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info img {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: 2px solid #F4991A;
            object-fit: cover;
        }
        
        .user-details h4 {
            font-size: 15px;
            margin-bottom: 3px;
            color: #F2EAD3;
            font-weight: 600;
        }
        
        .user-details p {
            font-size: 12px;
            color: #BDC3C7;
        }
        
        .board {
            display: flex;
            min-height: 100vh;
            padding-top: 80px;
        }
        
        nav {
            width: 260px;
            background: linear-gradient(180deg, #2C3E50 0%, #34495E 100%);
            display: flex;
            flex-direction: column;
            padding: 30px 0;
            position: fixed;
            height: calc(100vh - 80px);
            overflow-y: auto;
            box-shadow: 2px 0 15px rgba(0,0,0,0.1);
        }
        
        nav a {
            color: #ECF0F1;
            text-decoration: none;
            font-size: 15px;
            padding: 14px 30px;
            margin: 5px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        nav a i {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }
        
        nav a:hover {
            background: rgba(236, 240, 241, 0.15);
            transform: translateX(5px);
        }
        
        nav a.active {
            background: #F4991A;
            color: white;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(244, 153, 26, 0.3);
        }
        
        #welcomeboard {
            flex: 1;
            padding: 30px;
            margin-left: 260px;
            background-color: #f5f7fa;
        }
        
        .welcome-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
        }
        
        .welcome-section h1 {
            margin-bottom: 15px;
            text-align: center;
            color: #344F1F;
            font-size: 28px;
            font-weight: 700;
            position: relative;
            padding-bottom: 15px;
        }
        
        .welcome-section h1:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, #F4991A, #FFB74D);
            border-radius: 2px;
        }
        
        .course-description {
            text-align: center;
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
            padding: 0 20px;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        
        .stat-header {
            background: linear-gradient(135deg, #3B0270 0%, #5a1b9a 100%);
            padding: 20px;
            text-align: center;
        }
        
        .stat-card:nth-child(2) .stat-header { background: linear-gradient(135deg, #344F1F 0%, #4a7a2d 100%); }
        .stat-card:nth-child(3) .stat-header { background: linear-gradient(135deg, #2e7d32 0%, #4caf50 100%); }
        .stat-card:nth-child(4) .stat-header { background: linear-gradient(135deg, #F4991A 0%, #FF9800 100%); }
        
        .stat-header h3 {
            color: white;
            margin: 0;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .stat-number {
            padding: 25px 20px;
            text-align: center;
            font-size: 42px;
            font-weight: 800;
            color: #2C3E50;
            background: white;
        }
        
        .info-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 25px;
            border-radius: 12px;
            margin-top: 20px;
            border-left: 5px solid #4361ee;
        }
        
        .info-section h3 {
            color: #344F1F;
            margin-bottom: 20px;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .info-item h4 {
            color: #3B0270;
            margin-bottom: 8px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-item p {
            color: #444;
            font-size: 16px;
            font-weight: 500;
            margin: 0;
        }
        
        .status-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-active {
            background: rgba(46, 125, 50, 0.1);
            color: #2e7d32;
            border: 1px solid rgba(46, 125, 50, 0.2);
        }
        
        .status-inactive {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }
        
        .performance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .performance-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-top: 4px solid;
        }
        
        .performance-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .performance-card h4 {
            margin-bottom: 15px;
            font-size: 15px;
            color: #5a6c7d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .performance-value {
            font-size: 36px;
            font-weight: 800;
        }
        
        .feedback-section {
            background: linear-gradient(135deg, #f0f7ff 0%, #e3f2fd 100%);
            padding: 25px;
            border-radius: 12px;
            margin-top: 20px;
            border-left: 5px solid #2196F3;
        }
        
        .feedback-section h4 {
            color: #1565c0;
            margin-bottom: 15px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .feedback-section p {
            color: #37474f;
            line-height: 1.6;
            font-size: 15px;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 15px;
            border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            border-radius: 10px;
            overflow: hidden;
        }
        
        thead tr {
            background: linear-gradient(135deg, #5e9038 0%, #344F1F 100%);
            color: white;
        }
        
        th {
            padding: 18px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 15px;
            border-bottom: 2px solid #4a7a2d;
        }
        
        td {
            padding: 16px 20px;
            border-bottom: 1px solid #e9ecef;
            background: white;
        }
        
        tbody tr {
            transition: all 0.2s;
        }
        
        tbody tr:hover {
            background-color: #f8f9fa;
            transform: scale(1.002);
        }
        
        .attendance-status {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            letter-spacing: 0.3px;
        }
        
        .status-present {
            background: rgba(46, 125, 50, 0.1);
            color: #2e7d32;
            border: 1px solid rgba(46, 125, 50, 0.2);
        }
        
        .status-absent {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }
        
        .status-late {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.2);
        }
        
        .status-completed {
            background: rgba(46, 125, 50, 0.1);
            color: #2e7d32;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-today {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-upcoming {
            background: rgba(33, 150, 243, 0.1);
            color: #2196F3;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
        }
        
        .btn-container {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .btn {
            background: linear-gradient(135deg, #F4991A 0%, #FF9800 100%);
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(244, 153, 26, 0.3);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(244, 153, 26, 0.4);
            background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #5a6268 0%, #495057 100%);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.4);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: #666;
            margin-bottom: 10px;
            font-size: 22px;
        }
        
        .empty-state p {
            color: #888;
            margin-bottom: 25px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }
        
        @media (max-width: 1024px) {
            nav {
                width: 220px;
            }
            
            #welcomeboard {
                margin-left: 220px;
                padding: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .top {
                padding: 0 15px;
            }
            
            .logo-container {
                gap: 10px;
            }
            
            .dashboard-title {
                font-size: 18px;
            }
            
            nav {
                width: 70px;
                padding: 30px 0;
            }
            
            nav a span {
                display: none;
            }
            
            nav a {
                padding: 15px;
                justify-content: center;
                margin: 5px 10px;
            }
            
            #welcomeboard {
                margin-left: 70px;
                padding: 15px;
            }
            
            .welcome-section {
                padding: 20px;
                margin-bottom: 15px;
            }
            
            .stats-container,
            .info-grid,
            .performance-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .btn-container {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .stats-container,
            .info-grid,
            .performance-grid {
                grid-template-columns: 1fr;
            }
            
            .user-info .user-details {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="top">
        <div class="logo-container">
            <img src="../images/logo.png" alt="Company Logo" srcset="">
            <h4 class="dashboard-title">STUDENT DASHBOARD</h4>
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
        <nav>
            <a href="dashboard.php" data-section="dashboard"><i class="fas fa-home"></i> <span>Dashboard</span></a>
            <a href="courses.php" data-section="courses"><i class="fas fa-book"></i> <span>My Courses</span></a>
            <a href="enroll.php" data-section="enroll"><i class="fas fa-plus-circle"></i> <span>Enroll in Courses</span></a>
            <a href="attendance.php" data-section="attendance"><i class="fas fa-clipboard-check"></i> <span>My Attendance</span></a>
            <a href="mark_attendance.php" data-section="mark_attendance"><i class="fas fa-qrcode"></i> <span>Mark Attendance</span></a>
            <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>  
        </nav>

        <div id="welcomeboard">
            <!-- Course Header -->
            <div class="welcome-section">
                <h1><?php echo htmlspecialchars($course['course_code']); ?>: <?php echo htmlspecialchars($course['course_name']); ?></h1>
                
                <div class="course-description">
                    <?php echo htmlspecialchars($course['course_description']); ?>
                </div>
                
                <!-- Course Stats -->
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3>Attendance Rate</h3>
                        </div>
                        <div class="stat-number"><?php echo $attendanceRate; ?>%</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3>Total Sessions</h3>
                        </div>
                        <div class="stat-number"><?php echo $totalSessions; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3>Attended</h3>
                        </div>
                        <div class="stat-number"><?php echo $presentCount; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3>Credits</h3>
                        </div>
                        <div class="stat-number"><?php echo $course['credits']; ?></div>
                    </div>
                </div>
                
                <!-- Course Information -->
                <div class="info-section">
                    <h3><i class="fas fa-info-circle"></i> Course Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <h4>Instructor</h4>
                            <p>
                                <?php 
                                if (!empty($course['faculty_fname']) && !empty($course['faculty_lname'])) {
                                    echo htmlspecialchars($course['faculty_fname'] . ' ' . $course['faculty_lname']);
                                } else {
                                    echo 'Not assigned';
                                }
                                ?>
                            </p>
                        </div>
                        <div class="info-item">
                            <h4>Enrollment Date</h4>
                            <p><?php echo date('F j, Y', strtotime($course['approved_at'])); ?></p>
                        </div>
                        <div class="info-item">
                            <h4>Max Students</h4>
                            <p><?php echo $course['max_students']; ?></p>
                        </div>
                        <div class="info-item">
                            <h4>Course Status</h4>
                            <p>
                                <span class="status-<?php echo $course['status'] === 'active' ? 'active' : 'inactive'; ?>">
                                    <?php echo ucfirst($course['status']); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Summary -->
            <?php if ($performance): ?>
            <div class="welcome-section">
                <h2><i class="fas fa-chart-line"></i> Performance Summary</h2>
                
                <div class="performance-grid">
                    <div class="performance-card" style="border-color: #2e7d32;">
                        <h4>Assignments</h4>
                        <div class="performance-value" style="color: #2e7d32;">
                            <?php echo $performance['assignment_score']; ?>%
                        </div>
                    </div>
                    <div class="performance-card" style="border-color: #1565c0;">
                        <h4>Midterm</h4>
                        <div class="performance-value" style="color: #1565c0;">
                            <?php echo $performance['midterm_score']; ?>%
                        </div>
                    </div>
                    <div class="performance-card" style="border-color: #ef6c00;">
                        <h4>Final</h4>
                        <div class="performance-value" style="color: #ef6c00;">
                            <?php echo $performance['final_score']; ?>%
                        </div>
                    </div>
                    <div class="performance-card" style="border-color: #6a1b9a;">
                        <h4>Overall Grade</h4>
                        <div class="performance-value" style="color: #6a1b9a;">
                            <?php echo $performance['overall_grade']; ?>%
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($performance['feedback'])): ?>
                <div class="feedback-section">
                    <h4><i class="fas fa-comment"></i> Instructor Feedback</h4>
                    <p><?php echo nl2br(htmlspecialchars($performance['feedback'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Session History -->
            <div class="welcome-section">
                <h2><i class="fas fa-history"></i> Session History</h2>
                
                <?php if (empty($sessions)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar"></i>
                    <h3>No Sessions Scheduled</h3>
                    <p>There are no sessions scheduled for this course yet.</p>
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Topic</th>
                                <th>Location</th>
                                <th>Attendance</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $session): 
                                $today = date('Y-m-d');
                                $sessionDate = $session['session_date'];
                                
                                // Determine session status
                                if ($sessionDate < $today) {
                                    $statusClass = 'status-completed';
                                    $statusText = 'Completed';
                                } elseif ($sessionDate == $today) {
                                    $statusClass = 'status-today';
                                    $statusText = 'Today';
                                } else {
                                    $statusClass = 'status-upcoming';
                                    $statusText = 'Upcoming';
                                }
                            ?>
                            <tr>
                                <td><strong><?php echo date('M j, Y', strtotime($session['session_date'])); ?></strong></td>
                                <td><?php echo date('h:i A', strtotime($session['session_time'])); ?></td>
                                <td><?php echo htmlspecialchars($session['topic'] ?: 'General Session'); ?></td>
                                <td><?php echo htmlspecialchars($session['location'] ?: 'Not specified'); ?></td>
                                <td>
                                    <?php if ($session['attendance_status']): ?>
                                    <span class="attendance-status status-<?php echo $session['attendance_status']; ?>">
                                        <i class="fas fa-<?php echo $session['attendance_status'] === 'present' ? 'check-circle' : ($session['attendance_status'] === 'late' ? 'clock' : 'times-circle'); ?>"></i>
                                        <?php echo ucfirst($session['attendance_status']); ?>
                                    </span>
                                    <?php else: ?>
                                    <span style="color: #888; font-style: italic;">
                                        <i class="fas fa-minus-circle"></i> Not marked
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="<?php echo $statusClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Navigation Buttons -->
            <div class="btn-container">
                <a href="courses.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to My Courses
                </a>
                <a href="dashboard.php" class="btn">
                    <i class="fas fa-home"></i> Go to Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effect to table rows
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8f9fa';
                    this.style.transition = 'background-color 0.2s';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });
            
            // Add smooth scroll animation for links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
            
            // Add animation to stats cards on load
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>
