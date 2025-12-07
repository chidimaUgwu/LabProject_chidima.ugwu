<?php
// C:\xampp\htdocs\Attandance\student\dashboard.php
require_once '../config/session.php';
require_once '../config/database.php';
requireAuth();

// Check if user has student role
if (!isStudent()) {
    header("Location: ../auth/logout.php");
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get student's enrolled courses
    $coursesQuery = "SELECT 
                        c.*,
                        e.status as enrollment_status,
                        e.approved_at
                     FROM am_courses c
                     JOIN am_enrollments e ON c.id = e.course_id
                     WHERE e.student_id = ? AND e.status = 'approved'
                     ORDER BY c.course_code";
    $coursesStmt = $db->prepare($coursesQuery);
    $coursesStmt->execute([$_SESSION['user_id']]);
    $courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get upcoming sessions for enrolled courses
    $upcomingQuery = "SELECT 
                         s.*,
                         c.course_code,
                         c.course_name
                      FROM am_sessions s
                      JOIN am_courses c ON s.course_id = c.id
                      JOIN am_enrollments e ON c.id = e.course_id
                      WHERE e.student_id = ? 
                        AND e.status = 'approved'
                        AND s.session_date >= CURDATE()
                        AND s.status = 'upcoming'
                      ORDER BY s.session_date, s.session_time
                      LIMIT 5";
    $upcomingStmt = $db->prepare($upcomingQuery);
    $upcomingStmt->execute([$_SESSION['user_id']]);
    $upcomingSessions = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent attendance
    $attendanceQuery = "SELECT 
                           a.*,
                           s.session_date,
                           s.topic,
                           c.course_code,
                           c.course_name
                        FROM am_attendance a
                        JOIN am_sessions s ON a.session_id = s.id
                        JOIN am_courses c ON s.course_id = c.id
                        WHERE a.student_id = ?
                        ORDER BY a.attendance_time DESC
                        LIMIT 10";
    $attendanceStmt = $db->prepare($attendanceQuery);
    $attendanceStmt->execute([$_SESSION['user_id']]);
    $recentAttendance = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate attendance statistics
    $statsQuery = "SELECT 
                      COUNT(*) as total_sessions,
                      SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
                      SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                      SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count
                   FROM am_attendance a
                   JOIN am_sessions s ON a.session_id = s.id
                   WHERE a.student_id = ?";
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->execute([$_SESSION['user_id']]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    $totalSessions = $stats['total_sessions'] ?? 0;
    $presentCount = $stats['present_count'] ?? 0;
    $attendanceRate = $totalSessions > 0 ? round(($presentCount / $totalSessions) * 100) : 0;
    
} catch (PDOException $e) {
    $courses = [];
    $upcomingSessions = [];
    $recentAttendance = [];
    $attendanceRate = 0;
    error_log("Student dashboard error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - ATTENDIFY</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/style.css">
    <link rel="stylesheet" href="../styles/Student_Dashboard.css">
    <link rel="stylesheet" href="../styles/sessionSchedule.css">
    <style>
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
            margin-left: 90%;
            flex-grow: 1;
            flex-shrink: 0;
            display: flex;
            align-items: center;
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
            height: calc(100vh - 70px);
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
            cursor: pointer;
        }
        
        #welcomeboard {
            flex: 1;
            padding: 50px;
            margin-left: 250px;
            background-color: #F2EAD3;
        }
        
        .welcome-section {
            background: white;
            border-radius: 10px;
            padding: 35px;
            box-shadow: 5px 5px 10px #3B0270;
            margin: 25px;
        }
        
        .welcome-section h1 {
            margin-bottom: 10px;
            text-align: center;
            color: #3B0270;
        }
        
        .stats-container {
            margin-top: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            transition: transform 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            cursor: pointer;
        }
        
        #tete {
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            background-color: #3B0270;
            padding: 15px;
        }
        
        .stat-card h3 {
            color: #F2EAD3;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #F4991A;
            border-bottom-right-radius: 10px;
            border-bottom-left-radius: 10px;
            padding: 20px;
            box-shadow: 2px 3px 10px rgba(0,0,0,0.1);
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
        }
        
        .btn {
            background-color: #F4991A;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 15px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn:hover {
            background-color: #e68a00;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 2px solid #344F1F;
            color: #344F1F;
        }
        
        .btn-outline:hover {
            background-color: #344F1F;
            color: white;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 15px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 16px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
        }
        
        thead tr {
            background: linear-gradient(135deg, #5e9038, #344F1F);
            color: #F2EAD3;
            text-align: left;
        }
        
        th, td {
            padding: 15px 20px;
            border-bottom: 1px solid #e1e1e1;
            text-align: left;
        }
        
        tbody tr:hover {
            background-color: #e8f0f8;
        }
        
        .status-completed {
            background-color: #dcfce7;
            color: #166534;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-upcoming {
            background-color: #dbeafe;
            color: #1e40af;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-Late {
            background-color: rgb(251, 251, 227);
            color: rgb(217, 217, 4);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-Absent {
            background-color: rgb(245, 177, 177);
            color: red;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            display: inline-block;
        }
        
        .attendance-status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        
        .status-present {
            background: #d4edda;
            color: #155724;
        }
        
        .status-absent {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-late {
            background: #fff3cd;
            color: #856404;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
        
        .course-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .course-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 2px solid #e1e1e1;
            transition: transform 0.3s, border-color 0.3s;
        }
        
        .course-card:hover {
            transform: translateY(-5px);
            border-color: #F4991A;
        }
        
        .course-card h3 {
            color: #3B0270;
            margin-bottom: 10px;
        }
        
        .course-card p {
            color: #666;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .course-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #888;
        }
        
        @media (max-width: 768px) {
            nav {
                width: 200px;
            }
            
            #welcomeboard {
                margin-left: 200px;
            }
            
            .stats-container {
                flex-direction: column;
            }
            
            .course-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
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
<!-- <body>
    <div class="top">
        <div class="logo-container">
            <img src="../images/logo.png" alt="Company Logo" srcset="">
            <h4 class="dashboard-title">STUDENT DASHBOARD</h4>
            <div class="user-info">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['fname'] . '+' . $_SESSION['lname']); ?>&background=4361ee&color=fff" alt="User">
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($_SESSION['fname'] . ' ' . $_SESSION['lname']); ?></h4>
                    <p><?php echo getRoleDisplayName($_SESSION['role']); ?></p>
                </div>
            </div>
        </div>
    </div> -->
    
    <div class="board">
        <nav>
            <a href="dashboard.php" class="active" data-section="dashboard"><i class="fas fa-home"></i> <span>Dashboard</span></a>
            <a href="courses.php" data-section="courses"><i class="fas fa-book"></i> <span>My Courses</span></a>
            <a href="enroll.php" data-section="enroll"><i class="fas fa-plus-circle"></i> <span>Enroll in Courses</span></a>
            <a href="mark_attendance.php" data-section="mark_attendance"><i class="fas fa-qrcode"></i> <span>Mark Attendance</span></a>
            <a href="attendance.php" data-section="attendance"><i class="fas fa-clipboard-check"></i> <span>My Attendance</span></a>
            <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>  
        </nav>

        <div id="welcomeboard">
            <!-- Welcome Section -->
            <div class="welcome-section">
                <h1>Welcome, <?php echo htmlspecialchars($_SESSION['fname']); ?>!</h1>
                <p style="text-align: center; color: #666; margin-bottom: 30px;">
                    Student ID: <?php echo htmlspecialchars($_SESSION['userid']); ?> | 
                    Email: <?php echo htmlspecialchars($_SESSION['email']); ?>
                </p>
                
                <!-- Stats Overview -->
                <div class="stats-container">
                    <div class="stat-card">
                        <div id="tete">
                            <h3>My Courses</h3>
                        </div>
                        <div class="number"><?php echo count($courses); ?></div>
                    </div>
                    <div class="stat-card">
                        <div id="tete" style="background-color: #344F1F;">
                            <h3>Upcoming Sessions</h3>
                        </div>
                        <div class="number"><?php echo count($upcomingSessions); ?></div>
                    </div>
                    <div class="stat-card">
                        <div id="tete">
                            <h3>Attendance Rate</h3>
                        </div>
                        <div class="number"><?php echo $attendanceRate; ?>%</div>
                    </div>
                    <div class="stat-card">
                        <div id="tete">
                            <h3>Total Sessions</h3>
                        </div>
                        <div class="number"><?php echo $totalSessions; ?></div>
                    </div>
                </div>
            </div>

            <!-- My Courses Section -->
            <div class="welcome-section">
                <div class="section-header">
                    <h2><i class="fas fa-book"></i> My Courses</h2>
                    <a href="enroll.php" class="btn">
                        <i class="fas fa-plus"></i> Enroll in More Courses
                    </a>
                </div>
                
                <?php if (empty($courses)): ?>
                <div class="no-data">
                    <i class="fas fa-book fa-2x" style="margin-bottom: 10px; display: block; color: #ccc;"></i>
                    You are not enrolled in any courses yet.
                    <div style="margin-top: 15px;">
                        <a href="enroll.php" class="btn">Browse Available Courses</a>
                    </div>
                </div>
                <?php else: ?>
                <div class="course-grid">
                    <?php foreach ($courses as $course): ?>
                    <div class="course-card">
                        <h3><?php echo htmlspecialchars($course['course_code']); ?></h3>
                        <p><?php echo htmlspecialchars($course['course_name']); ?></p>
                        <div class="course-meta">
                            <span><i class="fas fa-graduation-cap"></i> <?php echo $course['credits']; ?> Credits</span>
                            <span><i class="fas fa-calendar"></i> Enrolled: <?php echo date('M j, Y', strtotime($course['approved_at'])); ?></span>
                        </div>
                        <div style="margin-top: 15px;">
                            <a href="course_details.php?id=<?php echo $course['id']; ?>" class="btn" style="padding: 8px 15px; font-size: 14px;">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Upcoming Sessions -->
            <div class="welcome-section">
                <div class="section-header">
                    <h2><i class="fas fa-calendar-alt"></i> Upcoming Sessions</h2>
                </div>
                
                <?php if (empty($upcomingSessions)): ?>
                <div class="no-data">
                    <i class="fas fa-calendar fa-2x" style="margin-bottom: 10px; display: block; color: #ccc;"></i>
                    No upcoming sessions scheduled
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Course</th>
                                <th>Topic</th>
                                <th>Location</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcomingSessions as $session): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($session['session_date'])); ?></td>
                                <td><?php echo date('H:i', strtotime($session['session_time'])); ?></td>
                                <td><?php echo htmlspecialchars($session['course_code']); ?></td>
                                <td><?php echo htmlspecialchars($session['topic'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($session['location'] ?: 'TBA'); ?></td>
                                <td>
                                    <?php 
                                    $today = date('Y-m-d');
                                    $sessionDate = $session['session_date'];
                                    
                                    if ($sessionDate == $today) {
                                        echo '<span class="status-Late">Today</span>';
                                    } else {
                                        echo '<span class="status-upcoming">Upcoming</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Recent Attendance -->
            <div class="welcome-section">
                <div class="section-header">
                    <h2><i class="fas fa-clipboard-check"></i> Recent Attendance</h2>
                    <a href="attendance.php" class="btn-outline">View All</a>
                </div>
                
                <?php if (empty($recentAttendance)): ?>
                <div class="no-data">
                    <i class="fas fa-clipboard fa-2x" style="margin-bottom: 10px; display: block; color: #ccc;"></i>
                    No attendance records yet
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Course</th>
                                <th>Topic</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentAttendance as $attendance): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($attendance['session_date'])); ?></td>
                                <td><?php echo htmlspecialchars($attendance['course_code']); ?></td>
                                <td><?php echo htmlspecialchars($attendance['topic'] ?: 'N/A'); ?></td>
                                <td>
                                    <?php 
                                    $statusClass = 'status-' . $attendance['status'];
                                    $statusText = ucfirst($attendance['status']);
                                    echo '<span class="attendance-status ' . $statusClass . '">' . $statusText . '</span>';
                                    ?>
                                </td>
                                <td><?php echo date('h:i A', strtotime($attendance['attendance_time'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
