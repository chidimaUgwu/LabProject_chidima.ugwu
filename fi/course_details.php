<?php
// C:\xampp\htdocs\Attandance\fi\course_details.php
require_once '../config/session.php';
require_once '../config/database.php';
requireAuth();

// Check if user has faculty role
if (!isFacultyIntern()) {
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
    
    // Get course details
    $courseQuery = "SELECT c.*, u.fname, u.lname 
                   FROM am_courses c
                   JOIN am_users u ON c.faculty_intern_id = u.id
                   WHERE c.id = ? AND c.faculty_intern_id = ?";
    $courseStmt = $db->prepare($courseQuery);
    $courseStmt->execute([$course_id, $_SESSION['user_id']]);
    $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$course) {
        header("Location: courses.php");
        exit();
    }
    
    // Get enrolled students
    $studentsQuery = "SELECT 
                        e.*,
                        u.fname,
                        u.lname,
                        u.email,
                        u.userid
                      FROM am_enrollments e
                      JOIN am_users u ON e.student_id = u.id
                      WHERE e.course_id = ?
                      ORDER BY e.status, u.fname";
    $studentsStmt = $db->prepare($studentsQuery);
    $studentsStmt->execute([$course_id]);
    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get sessions for this course
    $sessionsQuery = "SELECT s.*, COUNT(a.id) as attendance_count
                      FROM am_sessions s
                      LEFT JOIN am_attendance a ON s.id = a.session_id
                      WHERE s.course_id = ?
                      GROUP BY s.id
                      ORDER BY s.session_date DESC";
    $sessionsStmt = $db->prepare($sessionsQuery);
    $sessionsStmt->execute([$course_id]);
    $sessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get enrollment requests
    $pendingQuery = "SELECT COUNT(*) as count FROM am_enrollments 
                    WHERE course_id = ? AND status = 'pending'";
    $pendingStmt = $db->prepare($pendingQuery);
    $pendingStmt->execute([$course_id]);
    $pending = $pendingStmt->fetch(PDO::FETCH_ASSOC);
    $pendingCount = $pending['count'];
    
} catch (PDOException $e) {
    header("Location: courses.php");
    exit();
}

// Handle enrollment approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_enrollment'])) {
        $enrollment_id = intval($_POST['enrollment_id']);
        try {
            $updateQuery = "UPDATE am_enrollments 
                           SET status = 'approved', 
                               approved_by = ?, 
                               approved_at = NOW() 
                           WHERE id = ? AND course_id = ?";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->execute([$_SESSION['user_id'], $enrollment_id, $course_id]);
            
            $_SESSION['success_message'] = "Enrollment approved successfully!";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error approving enrollment.";
        }
    } elseif (isset($_POST['reject_enrollment'])) {
        $enrollment_id = intval($_POST['enrollment_id']);
        try {
            $updateQuery = "UPDATE am_enrollments 
                           SET status = 'rejected', 
                               approved_by = ?, 
                               approved_at = NOW() 
                           WHERE id = ? AND course_id = ?";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->execute([$_SESSION['user_id'], $enrollment_id, $course_id]);
            
            $_SESSION['success_message'] = "Enrollment rejected successfully!";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error rejecting enrollment.";
        }
    }
    
    header("Location: course_details.php?id=" . $course_id);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['course_code']); ?> Details - Faculty Intern Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/style.css">
    <link rel="stylesheet" href="../styles/Student_Dashboard.css">
    <link rel="stylesheet" href="../styles/sessionSchedule.css">
    <link rel="stylesheet" href="../styles/FI_Dashboard.css">
    <style>
        .course-header {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .course-header h1 {
            color: #3B0270;
            margin-bottom: 10px;
        }
        
        .course-header p {
            color: #666;
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        .course-meta {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-top: 15px;
        }
        
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
        }
        
        .status-active {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .status-inactive {
            background-color: rgb(245, 177, 177);
            color: red;
        }
        
        .course-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            transition: transform 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .info-card:hover {
            transform: translateY(-5px);
            cursor: pointer;
        }
        
        .info-card h4 {
            color: #344F1F;
            margin-bottom: 10px;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .info-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #F4991A;
        }
        
        .section-title {
            color: #344F1F;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: #3B0270;
        }
        
        .welcome-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
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
        
        tbody tr {
            transition: background-color 0.2s;
        }
        
        .student-status {
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
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 0.85rem;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
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
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            color: #3B0270;
            font-weight: bold;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="top">
        <div class="logo-container">
            <img src="../images/logo.png" alt="Company Logo" srcset="">
            <h4 class="dashboard-title">FACULTY INTERN DASHBOARD</h4>
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
            <a href="courses.php" class="active" data-section="courses"><i class="fas fa-book"></i> <span>Course Management</span></a>
            <a href="sessions.php" data-section="sessions"><i class="fas fa-calendar-alt"></i> <span>Session Overview</span></a>
            <a href="attendance.php" data-section="attendance"><i class="fas fa-clipboard-check"></i> <span>Attendance Reports</span></a>
            <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>  
        </nav>

        <div id="welcomeboard">
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

            <!-- Course Header -->
            <div class="course-header">
                <h1><?php echo htmlspecialchars($course['course_code']); ?> - <?php echo htmlspecialchars($course['course_name']); ?></h1>
                <p><?php echo htmlspecialchars($course['course_description'] ?: 'No description provided.'); ?></p>
                <div class="course-meta">
                    <span class="status-badge <?php echo $course['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                        <i class="fas fa-circle"></i> <?php echo ucfirst($course['status']); ?>
                    </span>
                    <span style="color: #666;">
                        <i class="fas fa-user-graduate"></i> Created by: <?php echo htmlspecialchars($course['fname'] . ' ' . $course['lname']); ?>
                    </span>
                </div>
            </div>

            <!-- Course Stats Grid -->
            <div class="course-info-grid">
                <div class="info-card">
                    <h4><i class="fas fa-graduation-cap"></i> Credits</h4>
                    <div class="number"><?php echo $course['credits']; ?></div>
                </div>
                <div class="info-card">
                    <h4><i class="fas fa-users"></i> Students Enrolled</h4>
                    <div class="number"><?php echo count(array_filter($students, function($s) { return $s['status'] === 'approved'; })); ?>/<?php echo $course['max_students']; ?></div>
                </div>
                <div class="info-card">
                    <h4><i class="fas fa-calendar"></i> Total Sessions</h4>
                    <div class="number"><?php echo count($sessions); ?></div>
                </div>
                <div class="info-card">
                    <h4><i class="fas fa-clock"></i> Created On</h4>
                    <div class="number" style="font-size: 1rem; color: #666;"><?php echo date('F j, Y', strtotime($course['created_at'])); ?></div>
                </div>
            </div>

            <!-- Pending Enrollment Requests -->
            <?php if ($pendingCount > 0): ?>
            <div class="welcome-section">
                <h3 class="section-title">
                    <i class="fas fa-clock"></i> Pending Enrollment Requests (<?php echo $pendingCount; ?>)
                </h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Requested On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                            <?php if ($student['status'] === 'pending'): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['userid']); ?></td>
                                <td><?php echo htmlspecialchars($student['fname'] . ' ' . $student['lname']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($student['requested_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="enrollment_id" value="<?php echo $student['id']; ?>">
                                            <button type="submit" name="approve_enrollment" class="btn-small" style="background-color: #28a745; color: white;" onclick="return confirm('Approve this enrollment?')">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="enrollment_id" value="<?php echo $student['id']; ?>">
                                            <button type="submit" name="reject_enrollment" class="btn-small" style="background-color: #dc3545; color: white;" onclick="return confirm('Reject this enrollment?')">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Enrolled Students -->
            <div class="welcome-section">
                <h3 class="section-title">
                    <i class="fas fa-user-graduate"></i> Enrolled Students
                </h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Enrollment Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $approvedStudents = array_filter($students, function($s) { return $s['status'] === 'approved'; });
                            if (empty($approvedStudents)): ?>
                            <tr>
                                <td colspan="5" class="no-data">
                                    <i class="fas fa-user-graduate fa-2x" style="margin-bottom: 10px; display: block; color: #ccc;"></i>
                                    No approved students yet
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($approvedStudents as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['userid']); ?></td>
                                <td><?php echo htmlspecialchars($student['fname'] . ' ' . $student['lname']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><span class="student-status status-approved">Approved</span></td>
                                <td><?php echo date('M j, Y', strtotime($student['approved_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Course Sessions -->
            <div class="welcome-section">
                <h3 class="section-title">
                    <i class="fas fa-calendar-alt"></i> Course Sessions
                </h3>
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
                            <?php if (empty($sessions)): ?>
                            <tr>
                                <td colspan="6" class="no-data">
                                    <i class="fas fa-calendar fa-2x" style="margin-bottom: 10px; display: block; color: #ccc;"></i>
                                    No sessions scheduled yet
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($sessions as $session): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($session['session_date'])); ?></td>
                                <td><?php echo date('H:i', strtotime($session['session_time'])); ?></td>
                                <td><?php echo htmlspecialchars($session['topic'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($session['location'] ?: 'TBA'); ?></td>
                                <td><?php echo $session['attendance_count']; ?> students</td>
                                <td>
                                    <?php 
                                    $today = date('Y-m-d');
                                    $sessionDate = $session['session_date'];
                                    
                                    if ($sessionDate < $today) {
                                        echo '<span class="status-completed">Completed</span>';
                                    } elseif ($sessionDate == $today) {
                                        echo '<span class="status-Late">Today</span>';
                                    } else {
                                        echo '<span class="status-upcoming">Upcoming</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="btn-group">
                    <a href="sessions.php" class="btn">
                        <i class="fas fa-plus"></i> Schedule New Session
                    </a>
                    <a href="courses.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Courses
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>