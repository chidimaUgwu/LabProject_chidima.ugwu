<?php
// C:\xampp\htdocs\Attandance\faculty\dashboard.php
require_once '../config/session.php';
require_once '../config/database.php';
requireAuth();

// Check if user has faculty/instructor role
if (!isInstructor()) {
    header("Location: ../auth/logout.php");
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $user_id = $_SESSION['user_id'];
    
    // Get faculty statistics
    $statsQuery = "SELECT 
                      COUNT(DISTINCT c.id) as total_courses,
                      COUNT(DISTINCT s.id) as total_sessions,
                      COUNT(DISTINCT e.student_id) as total_students,
                      (SELECT COUNT(*) FROM am_sessions WHERE created_by = ? AND DATE(session_date) = CURDATE()) as today_sessions
                   FROM am_courses c
                   LEFT JOIN am_sessions s ON c.id = s.course_id
                   LEFT JOIN am_enrollments e ON c.id = e.course_id AND e.status = 'approved'
                   WHERE c.faculty_intern_id = ? OR s.created_by = ?";
    
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->execute([$user_id, $user_id, $user_id]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent courses
    $coursesQuery = "SELECT 
                        c.*,
                        COUNT(DISTINCT e.id) as enrolled_students,
                        COUNT(DISTINCT s.id) as session_count
                     FROM am_courses c
                     LEFT JOIN am_enrollments e ON c.id = e.course_id AND e.status = 'approved'
                     LEFT JOIN am_sessions s ON c.id = s.course_id
                     WHERE c.faculty_intern_id = ?
                     GROUP BY c.id
                     ORDER BY c.created_at DESC
                     LIMIT 5";
    
    $coursesStmt = $db->prepare($coursesQuery);
    $coursesStmt->execute([$user_id]);
    $recentCourses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get today's sessions
    $todayQuery = "SELECT 
                      s.*,
                      c.course_code,
                      c.course_name,
                      COUNT(a.id) as attendance_count
                   FROM am_sessions s
                   JOIN am_courses c ON s.course_id = c.id
                   LEFT JOIN am_attendance a ON s.id = a.session_id
                   WHERE s.created_by = ? 
                   AND DATE(s.session_date) = CURDATE()
                   AND s.status = 'upcoming'
                   GROUP BY s.id
                   ORDER BY s.session_time";
    
    $todayStmt = $db->prepare($todayQuery);
    $todayStmt->execute([$user_id]);
    $todaySessions = $todayStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent attendance records
    $attendanceQuery = "SELECT 
                           a.*,
                           s.session_date,
                           s.session_time,
                           s.topic,
                           c.course_code,
                           u.fname as student_fname,
                           u.lname as student_lname
                        FROM am_attendance a
                        JOIN am_sessions s ON a.session_id = s.id
                        JOIN am_courses c ON s.course_id = c.id
                        JOIN am_users u ON a.student_id = u.id
                        WHERE s.created_by = ?
                        ORDER BY a.attendance_time DESC
                        LIMIT 10";
    
    $attendanceStmt = $db->prepare($attendanceQuery);
    $attendanceStmt->execute([$user_id]);
    $recentAttendance = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Faculty dashboard error: " . $e->getMessage());
    $stats = ['total_courses' => 0, 'total_sessions' => 0, 'total_students' => 0, 'today_sessions' => 0];
    $recentCourses = [];
    $todaySessions = [];
    $recentAttendance = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/style.css">
    <link rel="stylesheet" href="../styles/Student_Dashboard.css">
    <link rel="stylesheet" href="../styles/sessionSchedule.css">
    <style>
        /* Reuse your existing styles with some modifications */
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
        
        .course-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid #3B0270;
        }
        
        .session-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #F4991A;
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
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 30px;
        }
        
        .action-card {
            background: #344F1F;
            color: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .action-card:hover {
            background: #5e9038;
            transform: translateY(-5px);
        }
        
        .action-card i {
            font-size: 32px;
            margin-bottom: 15px;
        }
        
        .action-card h3 {
            margin: 0;
            font-size: 18px;
        }
        
        @media (max-width: 768px) {
            nav {
                width: 200px;
            }
            
            #welcomeboard {
                margin-left: 200px;
                padding: 20px;
            }
            
            .welcome-section {
                padding: 20px;
                margin: 10px;
            }
            
            .stats-container {
                grid-template-columns: 1fr 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
        
        .welcome-message {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 18px;
        }
        
        .date-display {
            text-align: center;
            color: #344F1F;
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 14px;
        }
    </style>
</head>

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
        <nav>
            <a href="dashboard.php" class="active" data-section="dashboard"><i class="fas fa-home"></i> <span>Dashboard</span></a>
            <a href="courses.php" data-section="courses"><i class="fas fa-book"></i> <span>Course Management</span></a>
            <a href="sessions.php" data-section="sessions"><i class="fas fa-calendar-alt"></i> <span>Session Overview</span></a>
            <a href="attendance_reports.php" data-section="attendance"><i class="fas fa-clipboard-check"></i> <span>Attendance Reports</span></a>
            <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>  
        </nav>

        <div id="welcomeboard">
            <div class="welcome-section">
                <h1>Welcome, <?php echo htmlspecialchars($_SESSION['fname']); ?>!</h1>
                <p class="welcome-message">Faculty Dashboard Overview</p>
                <div class="date-display">
                    <i class="fas fa-calendar"></i> Today: <?php echo date('F j, Y'); ?>
                </div>
                
                <!-- Statistics -->
                <div class="stats-container">
                    <div class="stat-card">
                        <div id="tete">
                            <h3>Total Courses</h3>
                        </div>
                        <div class="number"><?php echo $stats['total_courses'] ?? 0; ?></div>
                    </div>
                    <div class="stat-card">
                        <div id="tete" style="background-color: #2e7d32;">
                            <h3>Total Sessions</h3>
                        </div>
                        <div class="number"><?php echo $stats['total_sessions'] ?? 0; ?></div>
                    </div>
                    <div class="stat-card">
                        <div id="tete" style="background-color: #4361ee;">
                            <h3>Total Students</h3>
                        </div>
                        <div class="number"><?php echo $stats['total_students'] ?? 0; ?></div>
                    </div>
                    <div class="stat-card">
                        <div id="tete" style="background-color: #F4991A;">
                            <h3>Today's Sessions</h3>
                        </div>
                        <div class="number"><?php echo $stats['today_sessions'] ?? 0; ?></div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="quick-actions">
                    <a href="courses.php" class="action-card">
                        <i class="fas fa-plus-circle"></i>
                        <h3>Create New Course</h3>
                    </a>
                    <a href="sessions.php" class="action-card">
                        <i class="fas fa-calendar-plus"></i>
                        <h3>Schedule Session</h3>
                    </a>
                    <a href="attendance_reports.php" class="action-card">
                        <i class="fas fa-chart-bar"></i>
                        <h3>View Reports</h3>
                    </a>
                    <a href="performance.php" class="action-card">
                        <i class="fas fa-graduation-cap"></i>
                        <h3>Record Performance</h3>
                    </a>
                </div>
                
                <!-- Today's Sessions -->
                <div class="section-header" style="margin-top: 40px;">
                    <h2><i class="fas fa-calendar-day"></i> Today's Sessions</h2>
                    <a href="sessions.php" class="btn">
                        <i class="fas fa-eye"></i> View All
                    </a>
                </div>
                
                <?php if (empty($todaySessions)): ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-calendar-times fa-2x"></i>
                    <p>No sessions scheduled for today</p>
                    <a href="sessions.php" class="btn">
                        <i class="fas fa-calendar-plus"></i> Schedule a Session
                    </a>
                </div>
                <?php else: ?>
                    <?php foreach ($todaySessions as $session): ?>
                    <div class="session-card">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h4 style="margin: 0; color: #3B0270;">
                                    <?php echo htmlspecialchars($session['course_code']); ?>: <?php echo htmlspecialchars($session['topic'] ?: 'General Session'); ?>
                                </h4>
                                <p style="margin: 5px 0 0 0; color: #666;">
                                    <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($session['session_time'])); ?>
                                    | <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($session['location'] ?: 'Not specified'); ?>
                                </p>
                            </div>
                            <div>
                                <span style="background: #e3f2fd; color: #1565c0; padding: 5px 15px; border-radius: 20px;">
                                    <i class="fas fa-users"></i> <?php echo $session['attendance_count']; ?> marked
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Recent Courses -->
                <div class="section-header" style="margin-top: 40px;">
                    <h2><i class="fas fa-book"></i> My Recent Courses</h2>
                    <a href="courses.php" class="btn">
                        <i class="fas fa-list"></i> View All Courses
                    </a>
                </div>
                
                <?php if (empty($recentCourses)): ?>
                <div style="text-align: center; padding: 30px; color: #666;">
                    <i class="fas fa-book fa-2x"></i>
                    <p>No courses created yet</p>
                    <a href="courses.php" class="btn">
                        <i class="fas fa-plus-circle"></i> Create Your First Course
                    </a>
                </div>
                <?php else: ?>
                    <?php foreach ($recentCourses as $course): ?>
                    <div class="course-card">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h4 style="margin: 0; color: #344F1F;">
                                    <?php echo htmlspecialchars($course['course_code']); ?> - <?php echo htmlspecialchars($course['course_name']); ?>
                                </h4>
                                <p style="margin: 5px 0 0 0; color: #666;">
                                    <?php echo htmlspecialchars(substr($course['course_description'] ?? '', 0, 100)); ?>...
                                </p>
                            </div>
                            <div style="text-align: right;">
                                <div style="display: flex; gap: 15px;">
                                    <div style="text-align: center;">
                                        <div style="font-weight: bold; color: #3B0270;"><?php echo $course['enrolled_students']; ?></div>
                                        <small style="color: #666;">Students</small>
                                    </div>
                                    <div style="text-align: center;">
                                        <div style="font-weight: bold; color: #F4991A;"><?php echo $course['session_count']; ?></div>
                                        <small style="color: #666;">Sessions</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div style="margin-top: 15px; display: flex; gap: 10px;">
                            <a href="course_details.php?id=<?php echo $course['id']; ?>" class="btn" style="padding: 8px 15px; font-size: 14px;">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            <a href="sessions.php?course=<?php echo $course['id']; ?>" class="btn" style="background-color: #2e7d32; padding: 8px 15px; font-size: 14px;">
                                <i class="fas fa-calendar-alt"></i> Manage Sessions
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Recent Attendance -->
                <div class="section-header" style="margin-top: 40px;">
                    <h2><i class="fas fa-history"></i> Recent Attendance Records</h2>
                    <a href="attendance_reports.php" class="btn">
                        <i class="fas fa-chart-bar"></i> View Reports
                    </a>
                </div>
                
                <?php if (empty($recentAttendance)): ?>
                <div style="text-align: center; padding: 30px; color: #666;">
                    <i class="fas fa-clipboard-check fa-2x"></i>
                    <p>No attendance records yet</p>
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Course</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentAttendance as $record): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['student_fname'] . ' ' . $record['student_lname']); ?></td>
                                <td><?php echo htmlspecialchars($record['course_code']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($record['session_date'])); ?></td>
                                <td>
                                    <span class="attendance-status status-<?php echo $record['status']; ?>">
                                        <?php echo ucfirst($record['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('h:i A', strtotime($record['attendance_time'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh dashboard every 30 seconds for real-time updates
        setTimeout(function() {
            window.location.reload();
        }, 30000);
    </script>
</body>
</html>
