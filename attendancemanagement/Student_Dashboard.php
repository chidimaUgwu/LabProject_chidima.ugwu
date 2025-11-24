<?php
require_once 'php/auth_check.php';
checkRole('student');

// Get student data from database
require_once 'php/db_connection.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get student statistics
$total_courses = 0;
$upcoming_sessions = 0;
$average_grade = 0;

// Fetch total courses
$course_sql = "SELECT COUNT(*) as total FROM course_student_list WHERE student_id = ?";
$course_stmt = $conn->prepare($course_sql);
$course_stmt->bind_param("i", $user_id);
$course_stmt->execute();
$course_result = $course_stmt->get_result();
if ($course_row = $course_result->fetch_assoc()) {
    $total_courses = $course_row['total'];
}
$course_stmt->close();

// Fetch upcoming sessions (next 7 days)
$session_sql = "SELECT COUNT(*) as upcoming FROM sessions s 
                JOIN course_student_list csl ON s.course_id = csl.course_id 
                WHERE csl.student_id = ? AND s.date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
$session_stmt = $conn->prepare($session_sql);
$session_stmt->bind_param("i", $user_id);
$session_stmt->execute();
$session_result = $session_stmt->get_result();
if ($session_row = $session_result->fetch_assoc()) {
    $upcoming_sessions = $session_row['upcoming'];
}
$session_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/Student_Dashboard.css">
</head>
<body>
    <div class="top">
        <div class="logo-container">
            <img src="images/logo.png" alt="Company Logo">
            <h4 class="dashboard-title">STUDENT DASHBOARD</h4>
        </div>
    </div>
    <div class="board">
        <nav>
            <a href="Student_Dashboard.php" class="active"><i>📊</i> Dashboard</a>
            <a href="Student_Courses.php"><i>📚</i> My Courses</a>
            <a href="Student_sessionSchedule.php"><i>📅</i> Session Schedule</a>
            <a href="GradesReports.php"><i>🏆</i> Grade/Report</a>
            <a href="Student_Profile.php"><i>👤</i> Profile</a>
            <a href="logout.php"><i>🚪</i> Logout</a>
        </nav>

        <div id="welcomeboard">
            <div class="welcome-section">
                <h1>Welcome back <?php echo htmlspecialchars($username); ?>!</h1>
                <div class="stats-container">
                    <div class="stat-card">
                        <div id="tete">
                           <h3>Total Courses Enrolled</h3>
                        </div>
                        <div class="number"><?php echo $total_courses; ?></div>
                    </div>
                    <div class="stat-card">
                        <div id="tete" style="background-color: #344F1F; border:#344F1F;">
                            <h3>Upcoming Sessions</h3>
                        </div>
                        <div class="number"><?php echo $upcoming_sessions; ?></div>
                    </div>
                    <div class="stat-card">
                        <div id="tete"><h3>Average Grade</h3></div>
                        <div class="number"><?php echo $average_grade; ?>%</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>