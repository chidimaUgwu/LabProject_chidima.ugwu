<?php
require_once 'php/auth_check.php';
checkRole('student');

require_once 'php/db_connection.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch enrolled courses with details
$enrolled_courses = [];
$sql = "
    SELECT 
        c.course_id,
        c.course_code,
        c.course_name,
        c.description,
        c.credit_hours,
        u.first_name,
        u.last_name,
        COUNT(DISTINCT s.session_id) as total_sessions,
        COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.attendance_id END) as attended_sessions
    FROM courses c
    JOIN course_student_list csl ON c.course_id = csl.course_id
    JOIN faculty f ON c.faculty_id = f.faculty_id
    JOIN users u ON f.faculty_id = u.user_id
    LEFT JOIN sessions s ON c.course_id = s.course_id
    LEFT JOIN attendance a ON s.session_id = a.session_id AND a.student_id = ?
    WHERE csl.student_id = ?
    GROUP BY c.course_id, c.course_code, c.course_name, c.description, c.credit_hours, u.first_name, u.last_name
    ORDER BY c.course_code
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Calculate attendance percentage
    $attendance_percentage = 0;
    if ($row['total_sessions'] > 0) {
        $attendance_percentage = round(($row['attended_sessions'] / $row['total_sessions']) * 100);
    }
    
    $row['attendance_percentage'] = $attendance_percentage;
    $enrolled_courses[] = $row;
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Attendance Management</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/Student_Dashboard.css">
    <style>
        .courses-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #666;
            font-size: 1.1rem;
        }

        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .course-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 25px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 5px solid #4CAF50;
            position: relative;
            overflow: hidden;
        }

        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .course-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4CAF50, #45a049);
        }

        .course-header {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .course-code {
            font-size: 0.9rem;
            color: #666;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .course-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .course-description {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 15px;
        }

        .course-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.8rem;
            color: #888;
            margin-bottom: 3px;
            font-weight: 600;
        }

        .detail-value {
            font-size: 1rem;
            color: #333;
            font-weight: 600;
        }

        .attendance-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .attendance-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 10px;
        }

        .attendance-title {
            font-weight: 600;
            color: #333;
        }

        .attendance-percentage {
            font-weight: bold;
            font-size: 1.1rem;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin: 8px 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #45a049);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .attendance-stats {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: #666;
        }

        .no-courses {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .no-courses i {
            font-size: 4rem;
            margin-bottom: 20px;
            display: block;
            opacity: 0.5;
        }

        .no-courses h3 {
            margin-bottom: 10px;
            color: #333;
        }

        .instructor-name {
            color: #4CAF50;
            font-weight: 600;
        }

        .course-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-action {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
            text-align: center;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .btn-schedule {
            background: #2196F3;
            color: white;
        }

        .btn-schedule:hover {
            background: #1976D2;
        }

        .btn-grades {
            background: #FF9800;
            color: white;
        }

        .btn-grades:hover {
            background: #F57C00;
        }

        @media (max-width: 768px) {
            .courses-grid {
                grid-template-columns: 1fr;
            }
            
            .course-details {
                grid-template-columns: 1fr;
            }
            
            .course-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="top">
        <div class="logo-container">
            <img src="images/logo.png" alt="Company Logo">
            <h4 class="dashboard-title">MY COURSES</h4>
        </div>
    </div>
    
    <div class="board">
        <nav>
            <a href="Student_Dashboard.php"><i>📊</i> Dashboard</a>
            <a href="Student_Courses.php" class="active"><i>📚</i> My Courses</a>
            <a href="Student_sessionSchedule.php"><i>📅</i> Session Schedule</a>
            <a href="GradesReports.php"><i>🏆</i> Grade/Report</a>
            <a href="Student_Profile.php"><i>👤</i> Profile</a>
            <a href="logout.php"><i>🚪</i> Logout</a>
        </nav>

        <div id="welcomeboard">
            <div class="courses-container">
                <div class="page-header">
                    <h1>My Enrolled Courses</h1>
                    <p>Manage and track your course progress</p>
                </div>

                <?php if (count($enrolled_courses) > 0): ?>
                
                <div class="courses-grid">
                    <?php foreach ($enrolled_courses as $course): ?>
                    <div class="course-card">
                        <div class="course-header">
                            <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                            <h3 class="course-title"><?php echo htmlspecialchars($course['course_name']); ?></h3>
                        </div>
                        
                        <?php if (!empty($course['description'])): ?>
                        <div class="course-description">
                            <?php echo htmlspecialchars($course['description']); ?>
                        </div>
                        <?php endif; ?>

                        <div class="course-details">
                            <div class="detail-item">
                                <span class="detail-label">Credit Hours</span>
                                <span class="detail-value"><?php echo htmlspecialchars($course['credit_hours']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Instructor</span>
                                <span class="detail-value instructor-name">
                                    <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>
                                </span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Total Sessions</span>
                                <span class="detail-value"><?php echo $course['total_sessions']; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Sessions Attended</span>
                                <span class="detail-value"><?php echo $course['attended_sessions']; ?></span>
                            </div>
                        </div>

                        <div class="attendance-section">
                            <div class="attendance-header">
                                <span class="attendance-title">Attendance</span>
                                <span class="attendance-percentage"><?php echo $course['attendance_percentage']; ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $course['attendance_percentage']; ?>%"></div>
                            </div>
                            <div class="attendance-stats">
                                <span><?php echo $course['attended_sessions']; ?> attended</span>
                                <span><?php echo $course['total_sessions']; ?> total</span>
                            </div>
                        </div>

                        <div class="course-actions">
                            <a href="Student_sessionSchedule.php?course=<?php echo $course['course_id']; ?>" class="btn-action btn-schedule">
                                📅 View Schedule
                            </a>
                            <a href="GradesReports.php" class="btn-action btn-grades">
                                📊 View Grades
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php else: ?>
                
                <div class="no-courses">
                    <i>📚</i>
                    <h3>No Courses Enrolled</h3>
                    <p>You are not currently enrolled in any courses.</p>
                    <p>Please contact your administrator or faculty to get enrolled.</p>
                </div>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Add animation to progress bars
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => {
                    bar.style.width = width;
                }, 100);
            });
        });

        // Add hover effects
        const courseCards = document.querySelectorAll('.course-card');
        courseCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>