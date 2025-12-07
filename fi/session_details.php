<?php
// C:\xampp\htdocs\Attandance\fi\session_details.php
require_once '../config/session.php';
require_once '../config/database.php';
requireAuth();

// Check if user has faculty role
if (!isFacultyIntern()) {
    header("Location: ../auth/logout.php");
    exit();
}

// Get session ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: sessions.php");
    exit();
}

$session_id = intval($_GET['id']);

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get session details
    $sessionQuery = "SELECT 
                        s.*,
                        c.course_code,
                        c.course_name,
                        c.id as course_id,
                        u.fname as created_fname,
                        u.lname as created_lname
                     FROM am_sessions s
                     JOIN am_courses c ON s.course_id = c.id
                     JOIN am_users u ON s.created_by = u.id
                     WHERE s.id = ? AND c.faculty_intern_id = ?";
    $sessionStmt = $db->prepare($sessionQuery);
    $sessionStmt->execute([$session_id, $_SESSION['user_id']]);
    $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        header("Location: sessions.php");
        exit();
    }
    
    // Get attendance for this session
    $attendanceQuery = "SELECT 
                          a.*,
                          u.fname,
                          u.lname,
                          u.userid,
                          u.email
                        FROM am_attendance a
                        JOIN am_users u ON a.student_id = u.id
                        WHERE a.session_id = ?
                        ORDER BY a.attendance_time DESC";
    $attendanceStmt = $db->prepare($attendanceQuery);
    $attendanceStmt->execute([$session_id]);
    $attendance = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total enrolled students for this course
    $enrolledQuery = "SELECT COUNT(*) as total 
                     FROM am_enrollments 
                     WHERE course_id = ? AND status = 'approved'";
    $enrolledStmt = $db->prepare($enrolledQuery);
    $enrolledStmt->execute([$session['course_id']]);
    $enrolled = $enrolledStmt->fetch(PDO::FETCH_ASSOC);
    $totalStudents = $enrolled['total'];
    
    // Calculate attendance stats
    $presentCount = count(array_filter($attendance, function($a) { return $a['status'] === 'present'; }));
    $absentCount = count(array_filter($attendance, function($a) { return $a['status'] === 'absent'; }));
    $lateCount = count(array_filter($attendance, function($a) { return $a['status'] === 'late'; }));
    $attendancePercentage = $totalStudents > 0 ? round(($presentCount / $totalStudents) * 100) : 0;
    
} catch (PDOException $e) {
    header("Location: sessions.php");
    exit();
}

// Handle manual attendance marking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    $student_id = intval($_POST['student_id']);
    $status = $_POST['status'];
    $notes = trim($_POST['notes'] ?? '');
    
    try {
        // Check if attendance already exists
        $checkQuery = "SELECT id FROM am_attendance WHERE session_id = ? AND student_id = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$session_id, $student_id]);
        
        if ($checkStmt->rowCount() > 0) {
            // Update existing attendance
            $updateQuery = "UPDATE am_attendance 
                           SET status = ?, notes = ?, marked_by = ?
                           WHERE session_id = ? AND student_id = ?";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->execute([$status, $notes, $_SESSION['user_id'], $session_id, $student_id]);
            
            $_SESSION['success_message'] = "Attendance updated successfully!";
        } else {
            // Insert new attendance
            $insertQuery = "INSERT INTO am_attendance (session_id, student_id, status, notes, marked_by) 
                           VALUES (?, ?, ?, ?, ?)";
            $insertStmt = $db->prepare($insertQuery);
            $insertStmt->execute([$session_id, $student_id, $status, $notes, $_SESSION['user_id']]);
            
            $_SESSION['success_message'] = "Attendance marked successfully!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error marking attendance: " . $e->getMessage();
    }
    
    header("Location: session_details.php?id=" . $session_id);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Details - Faculty Intern Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/style.css">
    <link rel="stylesheet" href="../styles/Student_Dashboard.css">
    <link rel="stylesheet" href="../styles/sessionSchedule.css">
    <link rel="stylesheet" href="../styles/FI_Dashboard.css">
    <style>
        .session-header {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .session-header h1 {
            color: #3B0270;
            margin-bottom: 10px;
        }
        
        .session-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 15px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
        }
        
        .meta-item i {
            color: #F4991A;
        }
        
        .attendance-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .stat-card h3 {
            color: #344F1F;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .stat-card .number {
            font-size: 28px;
            font-weight: bold;
            color: #F4991A;
        }
        
        .stat-present { color: #2e7d32; }
        .stat-absent { color: #dc3545; }
        .stat-late { color: #ffc107; }
        .stat-percentage { color: #4361ee; }
        
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
            padding: 12px 15px;
            border-bottom: 1px solid #e1e1e1;
            text-align: left;
        }
        
        tbody tr:hover {
            background-color: #e8f0f8;
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
            padding: 5px 10px;
            font-size: 0.85rem;
            border-radius: 5px;
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
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .attendance-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #344F1F;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .attendance-code-box {
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
        }
        
        .attendance-code {
            font-size: 2rem;
            font-weight: bold;
            letter-spacing: 2px;
            margin: 10px 0;
        }
        
        .attendance-code-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }
    </style>
</head>
<body>
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
            <a href="dashboard.php" data-section="dashboard"><i class="fas fa-home"></i> <span>Dashboard</span></a>
            <a href="courses.php" data-section="courses"><i class="fas fa-book"></i> <span>Course Management</span></a>
            <a href="sessions.php" class="active" data-section="sessions"><i class="fas fa-calendar-alt"></i> <span>Session Overview</span></a>
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

            <!-- Session Header -->
            <div class="session-header">
                <h1><?php echo htmlspecialchars($session['course_code']); ?> - <?php echo htmlspecialchars($session['topic'] ?: 'Session'); ?></h1>
                <p><?php echo htmlspecialchars($session['course_name']); ?></p>
                
                <div class="session-meta">
                    <div class="meta-item">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo date('F j, Y', strtotime($session['session_date'])); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-clock"></i>
                        <span><?php echo date('h:i A', strtotime($session['session_time'])); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-hourglass-half"></i>
                        <span><?php echo $session['duration_minutes']; ?> minutes</span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?php echo htmlspecialchars($session['location'] ?: 'Location TBA'); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-user-graduate"></i>
                        <span>Created by: <?php echo htmlspecialchars($session['created_fname'] . ' ' . $session['created_lname']); ?></span>
                    </div>
                </div>
                
                <!-- Display attendance code if exists -->
                <?php if ($session['attendance_code']): ?>
                <div class="attendance-code-box">
                    <div class="attendance-code-label">
                        <i class="fas fa-key"></i> Attendance Code
                    </div>
                    <div class="attendance-code"><?php echo htmlspecialchars($session['attendance_code']); ?></div>
                    <small>Share this code with students to mark attendance</small>
                </div>
                <?php endif; ?>
            </div>

            <!-- Attendance Statistics -->
            <div class="attendance-stats">
                <div class="stat-card">
                    <h3><i class="fas fa-user-check"></i> Present</h3>
                    <div class="number stat-present"><?php echo $presentCount; ?></div>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-user-times"></i> Absent</h3>
                    <div class="number stat-absent"><?php echo $absentCount; ?></div>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-user-clock"></i> Late</h3>
                    <div class="number stat-late"><?php echo $lateCount; ?></div>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-chart-line"></i> Attendance Rate</h3>
                    <div class="number stat-percentage"><?php echo $attendancePercentage; ?>%</div>
                </div>
            </div>

            <!-- Mark Attendance Form -->
            <div class="welcome-section">
                <h3 class="section-title">
                    <i class="fas fa-edit"></i> Mark Attendance Manually
                </h3>
                
                <?php 
                // Get enrolled students who haven't been marked yet
                $enrolledStudentsQuery = "SELECT 
                                            u.id, u.userid, u.fname, u.lname
                                          FROM am_enrollments e
                                          JOIN am_users u ON e.student_id = u.id
                                          WHERE e.course_id = ? 
                                            AND e.status = 'approved'
                                            AND u.id NOT IN (
                                                SELECT student_id 
                                                FROM am_attendance 
                                                WHERE session_id = ?
                                            )";
                $enrolledStudentsStmt = $db->prepare($enrolledStudentsQuery);
                $enrolledStudentsStmt->execute([$session['course_id'], $session_id]);
                $unmarkedStudents = $enrolledStudentsStmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                
                <?php if (!empty($unmarkedStudents)): ?>
                <div class="attendance-form">
                    <form method="POST" action="session_details.php?id=<?php echo $session_id; ?>">
                        <input type="hidden" name="mark_attendance" value="1">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="student_id">Select Student *</label>
                                <select id="student_id" name="student_id" class="form-control" required>
                                    <option value="">Choose a student</option>
                                    <?php foreach ($unmarkedStudents as $student): ?>
                                    <option value="<?php echo $student['id']; ?>">
                                        <?php echo htmlspecialchars($student['userid'] . ' - ' . $student['fname'] . ' ' . $student['lname']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="status">Attendance Status *</label>
                                <select id="status" name="status" class="form-control" required>
                                    <option value="present">Present</option>
                                    <option value="absent">Absent</option>
                                    <option value="late">Late</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="notes">Notes (Optional)</label>
                            <textarea id="notes" name="notes" class="form-control" rows="2" placeholder="Additional notes..."></textarea>
                        </div>
                        <button type="submit" class="btn">
                            <i class="fas fa-check-circle"></i> Mark Attendance
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-check-circle fa-2x" style="margin-bottom: 10px; display: block; color: #2e7d32;"></i>
                    All enrolled students have been marked for attendance
                </div>
                <?php endif; ?>
            </div>

            <!-- Attendance Records -->
            <div class="welcome-section">
                <h3 class="section-title">
                    <i class="fas fa-clipboard-list"></i> Attendance Records
                </h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Time Marked</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($attendance)): ?>
                            <tr>
                                <td colspan="5" class="no-data">
                                    <i class="fas fa-clipboard fa-2x" style="margin-bottom: 10px; display: block; color: #ccc;"></i>
                                    No attendance records yet
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($attendance as $record): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['userid']); ?></td>
                                <td><?php echo htmlspecialchars($record['fname'] . ' ' . $record['lname']); ?></td>
                                <td>
                                    <?php 
                                    $statusClass = 'status-' . $record['status'];
                                    $statusText = ucfirst($record['status']);
                                    echo '<span class="attendance-status ' . $statusClass . '">' . $statusText . '</span>';
                                    ?>
                                </td>
                                <td><?php echo date('h:i A', strtotime($record['attendance_time'])); ?></td>
                                <td><?php echo htmlspecialchars($record['notes'] ?: '-'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="btn-group">
                <a href="sessions.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Sessions
                </a>
                <a href="course_details.php?id=<?php echo $session['course_id']; ?>" class="btn">
                    <i class="fas fa-book"></i> View Course
                </a>
            </div>
        </div>
    </div>
</body>
</html>
