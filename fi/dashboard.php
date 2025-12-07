<?php
// C:\xampp\htdocs\Attandance\fi\dashboard.php
require_once '../config/session.php';
require_once '../config/database.php';
requireAuth();

// Check if user has faculty role
if (!isFacultyIntern()) {
    header("Location: ../auth/logout.php");
    exit();
}

// Get statistics for dashboard
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get total courses for this FI
    $coursesQuery = "SELECT COUNT(*) as total FROM am_courses WHERE faculty_intern_id = ? AND status = 'active'";
    $coursesStmt = $db->prepare($coursesQuery);
    $coursesStmt->execute([$_SESSION['user_id']]);
    $courses = $coursesStmt->fetch(PDO::FETCH_ASSOC);
    $totalCourses = $courses['total'] ?? 0;
    
    // Get upcoming sessions
    $sessionsQuery = "SELECT COUNT(*) as total FROM am_sessions 
                     WHERE created_by = ? AND status = 'upcoming' AND session_date >= CURDATE()";
    $sessionsStmt = $db->prepare($sessionsQuery);
    $sessionsStmt->execute([$_SESSION['user_id']]);
    $sessions = $sessionsStmt->fetch(PDO::FETCH_ASSOC);
    $upcomingSessions = $sessions['total'] ?? 0;
    
    // Get total students in all courses
    $studentsQuery = "SELECT COUNT(DISTINCT e.student_id) as total 
                     FROM am_enrollments e 
                     JOIN am_courses c ON e.course_id = c.id 
                     WHERE c.faculty_intern_id = ? AND e.status = 'approved'";
    $studentsStmt = $db->prepare($studentsQuery);
    $studentsStmt->execute([$_SESSION['user_id']]);
    $students = $studentsStmt->fetch(PDO::FETCH_ASSOC);
    $totalStudents = $students['total'] ?? 0;
    
    // Get attendance rate (average)
    $attendanceQuery = "SELECT AVG(a.attendance_rate) as rate FROM (
        SELECT (COUNT(CASE WHEN status = 'present' THEN 1 END) * 100.0 / COUNT(*)) as attendance_rate
        FROM am_attendance att
        JOIN am_sessions s ON att.session_id = s.id
        JOIN am_courses c ON s.course_id = c.id
        WHERE c.faculty_intern_id = ?
        GROUP BY att.student_id
    ) as subquery";
    $attendanceStmt = $db->prepare($attendanceQuery);
    $attendanceStmt->execute([$_SESSION['user_id']]);
    $attendance = $attendanceStmt->fetch(PDO::FETCH_ASSOC);
    $attendanceRate = round($attendance['rate'] ?? 0);
    
    // Get recent activity
    $activityQuery = "SELECT 
        DATE(s.session_date) as date,
        CONCAT('Session: ', c.course_name) as activity,
        c.course_code as course,
        CASE 
            WHEN s.session_date < CURDATE() THEN 'completed'
            ELSE 'upcoming'
        END as status
        FROM am_sessions s
        JOIN am_courses c ON s.course_id = c.id
        WHERE c.faculty_intern_id = ?
        ORDER BY s.session_date DESC
        LIMIT 5";
    $activityStmt = $db->prepare($activityQuery);
    $activityStmt->execute([$_SESSION['user_id']]);
    $recentActivities = $activityStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Initialize defaults if error
    $totalCourses = 0;
    $upcomingSessions = 0;
    $totalStudents = 0;
    $attendanceRate = 0;
    $recentActivities = [];
    error_log("Dashboard error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Intern Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/style.css">
    <link rel="stylesheet" href="../styles/Student_Dashboard.css">
    <link rel="stylesheet" href="../styles/FI_Dashboard.css">
    <link rel="stylesheet" href="../styles/faculty.css">
    <link rel="stylesheet" href="../styles/sessionSchedule.css">

    <style>
    /* WELCOME BOARD */
    #welcomeboard {
        padding: 20px;
        background: #f5f6fa;
        width: 100%;
    }

    .welcome-section {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 0 8px rgba(0,0,0,0.1);
    }

    /* STAT CARDS */
    .stats-container {
        display: flex;
        gap: 20px;
        margin-bottom: 25px;
        flex-wrap: wrap;
    }

    .stat-card {
        flex: 1;
        min-width: 220px;
        background: #ffffff;
        border-radius: 10px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    #tete {
        background-color: #1F4F7B;
        padding: 12px;
        text-align: center;
        color: white;
        border-bottom: 3px solid #143554;
    }

    /* NUMBER */
    .number {
        font-size: 32px;
        font-weight: bold;
        text-align: center;
        padding: 15px;
        color: #2c3e50;
    }

    /* HEADER SECTION */
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .section-header h2 {
        font-size: 22px;
        color: #1f2d3d;
    }

    /* BUTTON */
    .btn-outline {
        padding: 8px 16px;
        border: 1px solid #1F4F7B;
        background: white;
        color: #1F4F7B;
        cursor: pointer;
        border-radius: 6px;
        transition: 0.3s;
    }

    .btn-outline:hover {
        background: #1F4F7B;
        color: white;
    }

    /* TABLE */
    .table-container {
        overflow-x: auto;
        margin-top: 20px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background: white;
    }

    table th {
        background: #1F4F7B;
        padding: 12px;
        color: white;
        text-align: left;
        font-weight: 600;
    }

    table td {
        padding: 10px;
        border-bottom: 1px solid #ddd;
        color: #333;
    }

    /* STATUS BADGES */
    .status-completed {
        padding: 5px 10px;
        background: #27ae60;
        color: white;
        border-radius: 20px;
        font-size: 12px;
    }

    .status-upcoming {
        padding: 5px 10px;
        background: #e67e22;
        color: white;
        border-radius: 20px;
        font-size: 12px;
    }

    /* LOGOUT LINK */
    nav a {
        color: #c0392b;
        font-size: 16px;
        text-decoration: none;
        padding: 8px 10px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: 0.3s;
    }

    nav a:hover {
        color: #e74c3c;
    }
</style>

</head>
<body>
    <div class="top">
        <div class="logo-container">
            <img src="images/logo.png" alt="Company Logo" srcset="">
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
            <a href="attendance.php" data-section="attendance"><i class="fas fa-clipboard-check"></i> <span>Attendance Reports</span></a>
            <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>  
        </nav>

        <div id="welcomeboard">
            <div class="welcome-section" >
                <div class="stats-container">
                    <div class="stat-card">
                        <div id="tete">
                           <h3>Total Courses</h3>
                        </div>
                        <div class="number"><?php echo $totalCourses; ?></div>
                    </div>
                    <div class="stat-card">
                        <div id="tete" style="background-color: #344F1F ; border:#344F1F;">
                            <h3>Upcoming Sessions</h3>
                        </div>
                        <div class="number"><?php echo $upcomingSessions; ?></div>
                    </div>
                    <div class="stat-card">
                        <div id="tete"><h3>Students</h3></div>
                        <div class="number"><?php echo $totalStudents; ?></div>
                    </div>

                    <div class="stat-card">
                        <div id="tete"><h3>Attendance Rate</h3></div>
                        <div class="number"><?php echo $attendanceRate; ?>%</div>
                    </div>
                </div>
            
                
                <div class="section-header">
                    <h2>Recent Activity</h2>
                    <button class="btn btn-outline"><i class="fas fa-download"></i> Export Report</button>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Activity</th>
                                <th>Course</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentActivities)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center;">No recent activities found</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recentActivities as $activity): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($activity['date']); ?></td>
                                <td><?php echo htmlspecialchars($activity['activity']); ?></td>
                                <td><?php echo htmlspecialchars($activity['course']); ?></td>
                                <td>
                                    <?php if ($activity['status'] === 'completed'): ?>
                                    <span class="status-completed">Completed</span>
                                    <?php else: ?>
                                    <span class="status-upcoming">Upcoming</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div> 
            </div>  
        </div>
    </div>
</body>
</html>
