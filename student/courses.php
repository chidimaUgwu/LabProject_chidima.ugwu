<?php
// student/courses.php
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
                        u.fname as faculty_fname,
                        u.lname as faculty_lname,
                        e.status as enrollment_status,
                        e.approved_at,
                        (SELECT COUNT(*) FROM am_sessions WHERE course_id = c.id) as total_sessions,
                        (SELECT COUNT(*) FROM am_attendance a 
                         JOIN am_sessions s ON a.session_id = s.id 
                         WHERE s.course_id = c.id AND a.student_id = ? AND a.status = 'present') as attended_sessions
                     FROM am_courses c
                     JOIN am_users u ON c.faculty_intern_id = u.id
                     JOIN am_enrollments e ON c.id = e.course_id
                     WHERE e.student_id = ? AND e.status = 'approved'
                     ORDER BY c.course_code";
    $coursesStmt = $db->prepare($coursesQuery);
    $coursesStmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate attendance for each course
    foreach ($courses as &$course) {
        $course['attendance_rate'] = ($course['total_sessions'] > 0) 
            ? round(($course['attended_sessions'] / $course['total_sessions']) * 100) 
            : 0;
    }
    
} catch (PDOException $e) {
    $courses = [];
    error_log("Courses page error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Student Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/style.css">
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
        
        .courses-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .summary-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .summary-card .number {
            font-size: 36px;
            font-weight: bold;
            color: #F4991A;
        }
        
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
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
            margin-bottom: 5px;
            font-size: 18px;
        }
        
        .course-card .course-code {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .course-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #888;
            margin-bottom: 15px;
        }
        
        .attendance-progress {
            margin-bottom: 15px;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #F4991A, #FF9800);
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        .attendance-label {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #666;
            margin-top: 5px;
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
        
        .btn-small {
            padding: 8px 15px;
            font-size: 14px;
            width: 100%;
            justify-content: center;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
        
        .stats-row {
            display: flex;
            justify-content: space-around;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #F4991A;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        @media (max-width: 768px) {
            nav {
                width: 200px;
            }
            
            #welcomeboard {
                margin-left: 200px;
            }
            
            .courses-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
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
    </div>
    
    <div class="board">
        <nav>
            <a href="dashboard.php" data-section="dashboard"><i class="fas fa-home"></i> <span>Dashboard</span></a>
            <a href="courses.php" class="active" data-section="courses"><i class="fas fa-book"></i> <span>My Courses</span></a>
            <a href="enroll.php" data-section="enroll"><i class="fas fa-plus-circle"></i> <span>Enroll in Courses</span></a>
            <a href="attendance.php" data-section="attendance"><i class="fas fa-clipboard-check"></i> <span>My Attendance</span></a>
            <a href="mark_attendance.php" data-section="mark_attendance"><i class="fas fa-qrcode"></i> <span>Mark Attendance</span></a>
            <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>  
        </nav>

        <div id="welcomeboard">
            <div class="welcome-section">
                <h1>My Courses</h1>
                
                <?php if (empty($courses)): ?>
                <div class="no-data">
                    <i class="fas fa-book fa-2x" style="margin-bottom: 10px; display: block; color: #ccc;"></i>
                    You are not enrolled in any courses yet.
                    <div style="margin-top: 15px;">
                        <a href="enroll.php" class="btn">Browse Available Courses</a>
                    </div>
                </div>
                <?php else: ?>
                <!-- Summary Stats -->
                <div class="stats-row">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo count($courses); ?></div>
                        <div class="stat-label">Total Courses</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">
                            <?php 
                            $totalCredits = array_sum(array_column($courses, 'credits'));
                            echo $totalCredits;
                            ?>
                        </div>
                        <div class="stat-label">Total Credits</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">
                            <?php 
                            $avgAttendance = count($courses) > 0 
                                ? round(array_sum(array_column($courses, 'attendance_rate')) / count($courses)) 
                                : 0;
                            echo $avgAttendance . '%';
                            ?>
                        </div>
                        <div class="stat-label">Avg Attendance</div>
                    </div>
                </div>
                
                <!-- Course Grid -->
                <div class="courses-grid">
                    <?php foreach ($courses as $course): ?>
                    <div class="course-card">
                        <h3><?php echo htmlspecialchars($course['course_name']); ?></h3>
                        <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                        
                        <p style="color: #666; font-size: 14px; margin-bottom: 15px; line-height: 1.4;">
                            <?php echo htmlspecialchars($course['course_description']); ?>
                        </p>
                        
                        <div class="course-meta">
                            <span><i class="fas fa-user-graduate"></i> 
                                <?php echo htmlspecialchars($course['faculty_fname'] . ' ' . $course['faculty_lname']); ?>
                            </span>
                            <span><i class="fas fa-graduation-cap"></i> <?php echo $course['credits']; ?> Credits</span>
                        </div>
                        
                        <div class="attendance-progress">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                <span style="font-size: 14px; color: #666;">Attendance Rate</span>
                                <span style="font-weight: bold; color: #F4991A;"><?php echo $course['attendance_rate']; ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo min($course['attendance_rate'], 100); ?>%;"></div>
                            </div>
                            <div class="attendance-label">
                                <span><?php echo $course['attended_sessions']; ?> attended</span>
                                <span><?php echo $course['total_sessions']; ?> total sessions</span>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 10px; margin-top: 15px;">
                            <a href="course_details.php?id=<?php echo $course['id']; ?>" class="btn btn-small">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            <a href="attendance.php?course_id=<?php echo $course['id']; ?>" class="btn btn-small btn-outline">
                                <i class="fas fa-clipboard-check"></i> Attendance
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Actions -->
                <div style="display: flex; justify-content: space-between; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                    <a href="dashboard.php" class="btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <a href="enroll.php" class="btn">
                        <i class="fas fa-plus-circle"></i> Enroll in More Courses
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add animation to course cards
            const courseCards = document.querySelectorAll('.course-card');
            courseCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
            
            // Add click animation
            courseCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    if (!e.target.closest('a')) {
                        const viewBtn = this.querySelector('a[href*="course_details"]');
                        if (viewBtn) {
                            viewBtn.style.transform = 'scale(0.95)';
                            setTimeout(() => {
                                viewBtn.style.transform = '';
                            }, 200);
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>
