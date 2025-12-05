<?php
// C:\xampp\htdocs\Attandance\student\enroll.php
require_once '../config/session.php';
require_once '../config/database.php';
requireAuth();

// Check if user has student role
if (!isStudent()) {
    header("Location: ../auth/logout.php");
    exit();
}

// Handle enrollment request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_course'])) {
    $course_id = intval($_POST['course_id']);
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Check if already enrolled
        $checkQuery = "SELECT id FROM am_enrollments WHERE student_id = ? AND course_id = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$_SESSION['user_id'], $course_id]);
        
        if ($checkStmt->rowCount() > 0) {
            $_SESSION['error_message'] = "You have already requested enrollment in this course!";
        } else {
            // Check if course has available seats
            $courseQuery = "SELECT 
                               c.*,
                               COUNT(e.id) as enrolled_count
                            FROM am_courses c
                            LEFT JOIN am_enrollments e ON c.id = e.course_id AND e.status = 'approved'
                            WHERE c.id = ? AND c.status = 'active'
                            GROUP BY c.id";
            $courseStmt = $db->prepare($courseQuery);
            $courseStmt->execute([$course_id]);
            $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$course) {
                $_SESSION['error_message'] = "Course not found or not active!";
            } elseif ($course['enrolled_count'] >= $course['max_students']) {
                $_SESSION['error_message'] = "Course is full! Maximum " . $course['max_students'] . " students allowed.";
            } else {
                // Create enrollment request
                $insertQuery = "INSERT INTO am_enrollments (student_id, course_id, status) 
                                VALUES (?, ?, 'pending')";
                $insertStmt = $db->prepare($insertQuery);
                $insertStmt->execute([$_SESSION['user_id'], $course_id]);
                
                $_SESSION['success_message'] = "Enrollment request sent successfully! Waiting for faculty approval.";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error enrolling in course: " . $e->getMessage();
    }
    
    header("Location: enroll.php");
    exit();
}

// Get available courses (not already enrolled in)
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $coursesQuery = "SELECT 
                        c.*,
                        u.fname as faculty_fname,
                        u.lname as faculty_lname,
                        COUNT(e.id) as enrolled_count,
                        CASE 
                            WHEN EXISTS (SELECT 1 FROM am_enrollments WHERE student_id = ? AND course_id = c.id) THEN 1
                            ELSE 0
                        END as already_requested
                     FROM am_courses c
                     JOIN am_users u ON c.faculty_intern_id = u.id
                     LEFT JOIN am_enrollments e ON c.id = e.course_id AND e.status = 'approved'
                     WHERE c.status = 'active'
                     GROUP BY c.id
                     HAVING already_requested = 0
                     ORDER BY c.course_code";
    
    $coursesStmt = $db->prepare($coursesQuery);
    $coursesStmt->execute([$_SESSION['user_id']]);
    $availableCourses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get pending enrollment requests
    $pendingQuery = "SELECT 
                        e.*,
                        c.course_code,
                        c.course_name,
                        c.credits
                     FROM am_enrollments e
                     JOIN am_courses c ON e.course_id = c.id
                     WHERE e.student_id = ? AND e.status = 'pending'
                     ORDER BY e.requested_at DESC";
    $pendingStmt = $db->prepare($pendingQuery);
    $pendingStmt->execute([$_SESSION['user_id']]);
    $pendingRequests = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get enrolled courses
    $enrolledQuery = "SELECT 
                         c.*,
                         e.approved_at
                      FROM am_courses c
                      JOIN am_enrollments e ON c.id = e.course_id
                      WHERE e.student_id = ? AND e.status = 'approved'
                      ORDER BY c.course_code";
    $enrolledStmt = $db->prepare($enrolledQuery);
    $enrolledStmt->execute([$_SESSION['user_id']]);
    $enrolledCourses = $enrolledStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $availableCourses = [];
    $pendingRequests = [];
    $enrolledCourses = [];
    error_log("Enrollment error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Enrollment - Student Dashboard</title>
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
        
        .alert {
            padding: 12px 20px;
            border-radius: 5px;
            margin: 15px 0;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .course-grid {
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
            margin-bottom: 15px;
        }
        
        .course-stats {
            display: flex;
            justify-content: space-between;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
        }
        
        .stat-value {
            font-weight: bold;
            color: #F4991A;
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
            padding: 6px 12px;
            font-size: 14px;
        }
        
        .btn-full {
            width: 100%;
            justify-content: center;
        }
        
        .btn-success {
            background-color: #28a745;
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        .btn-disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        
        .btn-disabled:hover {
            background-color: #6c757d;
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
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
        
        .enrollment-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .info-box {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .info-icon {
            width: 40px;
            height: 40px;
            background: #F4991A;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        @media (max-width: 768px) {
            nav {
                width: 200px;
            }
            
            #welcomeboard {
                margin-left: 200px;
            }
            
            .course-grid {
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
            <a href="courses.php" data-section="courses"><i class="fas fa-book"></i> <span>My Courses</span></a>
            <a href="enroll.php" class="active" data-section="enroll"><i class="fas fa-plus-circle"></i> <span>Enroll in Courses</span></a>
            <a href="attendance.php" data-section="attendance"><i class="fas fa-clipboard-check"></i> <span>My Attendance</span></a>
            <a href="mark_attendance.php" data-section="mark_attendance"><i class="fas fa-qrcode"></i> <span>Mark Attendance</span></a>
            <a href="performance.php" data-section="performance"><i class="fas fa-chart-line"></i> <span>Performance</span></a>
            <a href="profile.php" data-section="profile"><i class="fas fa-user"></i> <span>Profile</span></a>
            <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>  
        </nav>

        <div id="welcomeboard">
            <!-- Enrollment Information -->
            <div class="enrollment-info">
                <h2 style="color: #3B0270; margin-bottom: 20px;">
                    <i class="fas fa-graduation-cap"></i> Course Enrollment
                </h2>
                <div class="info-box">
                    <div class="info-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0 0 5px 0; color: #344F1F;">How it works:</h4>
                        <p style="margin: 0; color: #666;">
                            1. Browse available courses below<br>
                            2. Request enrollment in courses you want to join<br>
                            3. Wait for Faculty Intern approval<br>
                            4. Start attending sessions once approved
                        </p>
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php 
                    echo htmlspecialchars($_SESSION['success_message']);
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php 
                    echo htmlspecialchars($_SESSION['error_message']);
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Pending Enrollment Requests -->
            <?php if (!empty($pendingRequests)): ?>
            <div class="welcome-section">
                <div class="section-header">
                    <h2><i class="fas fa-clock"></i> Pending Enrollment Requests</h2>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Credits</th>
                                <th>Requested On</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingRequests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['course_code']); ?></td>
                                <td><?php echo htmlspecialchars($request['course_name']); ?></td>
                                <td><?php echo $request['credits']; ?></td>
                                <td><?php echo date('M j, Y', strtotime($request['requested_at'])); ?></td>
                                <td><span class="status-badge status-pending">Pending Approval</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Available Courses -->
            <div class="welcome-section">
                <div class="section-header">
                    <h2><i class="fas fa-book-open"></i> Available Courses</h2>
                    <div style="color: #666; font-size: 14px;">
                        Showing <?php echo count($availableCourses); ?> available courses
                    </div>
                </div>
                
                <?php if (empty($availableCourses)): ?>
                <div class="no-data">
                    <i class="fas fa-book fa-2x" style="margin-bottom: 10px; display: block; color: #ccc;"></i>
                    No available courses at the moment.
                    <?php if (!empty($enrolledCourses)): ?>
                    <p style="margin-top: 10px;">You are already enrolled in all available courses.</p>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="course-grid">
                    <?php foreach ($availableCourses as $course): ?>
                    <div class="course-card">
                        <h3><?php echo htmlspecialchars($course['course_code']); ?></h3>
                        <p><?php echo htmlspecialchars($course['course_name']); ?></p>
                        
                        <div class="course-meta">
                            <span><i class="fas fa-user-graduate"></i> <?php echo htmlspecialchars($course['faculty_fname'] . ' ' . $course['faculty_lname']); ?></span>
                            <span><i class="fas fa-graduation-cap"></i> <?php echo $course['credits']; ?> Credits</span>
                        </div>
                        
                        <div class="course-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $course['enrolled_count']; ?>/<?php echo $course['max_students']; ?></div>
                                <div class="stat-label">Students</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $course['credits']; ?></div>
                                <div class="stat-label">Credits</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">
                                    <?php 
                                    $seatsLeft = $course['max_students'] - $course['enrolled_count'];
                                    echo $seatsLeft > 0 ? $seatsLeft . ' left' : 'Full';
                                    ?>
                                </div>
                                <div class="stat-label">Availability</div>
                            </div>
                        </div>
                        
                        <?php if ($course['enrolled_count'] >= $course['max_students']): ?>
                        <button class="btn btn-full btn-disabled" disabled>
                            <i class="fas fa-times-circle"></i> Course Full
                        </button>
                        <?php else: ?>
                        <form method="POST" action="enroll.php" style="margin-top: 10px;">
                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                            <input type="hidden" name="enroll_course" value="1">
                            <button type="submit" class="btn btn-full btn-success">
                                <i class="fas fa-plus-circle"></i> Request Enrollment
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Already Enrolled Courses -->
            <?php if (!empty($enrolledCourses)): ?>
            <div class="welcome-section">
                <div class="section-header">
                    <h2><i class="fas fa-check-circle"></i> Already Enrolled Courses</h2>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Credits</th>
                                <th>Approved On</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enrolledCourses as $course): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                <td><?php echo $course['credits']; ?></td>
                                <td><?php echo date('M j, Y', strtotime($course['approved_at'])); ?></td>
                                <td><span class="status-badge status-approved">Enrolled</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div style="text-align: center; margin-top: 20px;">
                    <a href="dashboard.php" class="btn">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>