<?php
// C:\xampp\htdocs\Attandance\fi\sessions.php
require_once '../config/session.php';
require_once '../config/database.php';
requireAuth();

// Check if user has faculty role
if (!isFacultyIntern()) {
    header("Location: ../auth/logout.php");
    exit();
}

// Handle form submission for adding session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_session'])) {
    $course_id = intval($_POST['course_id']);
    $session_date = $_POST['session_date'];
    $session_time = $_POST['session_time'];
    $duration_minutes = intval($_POST['duration_minutes']);
    $topic = trim($_POST['topic']);
    $location = trim($_POST['location']);
    $attendance_code = trim($_POST['attendance_code']);
    
    // Validate attendance code (optional, 6-10 chars)
    if (!empty($attendance_code) && (strlen($attendance_code) < 4 || strlen($attendance_code) > 20)) {
        $_SESSION['error_message'] = "Attendance code must be between 4 and 20 characters!";
        header("Location: sessions.php");
        exit();
    }
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Check if course belongs to this FI
        $checkQuery = "SELECT id FROM am_courses WHERE id = ? AND faculty_intern_id = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$course_id, $_SESSION['user_id']]);
        
        if ($checkStmt->rowCount() === 0) {
            $_SESSION['error_message'] = "Invalid course selection!";
        } else {
            // Insert new session
            $query = "INSERT INTO am_sessions (course_id, session_date, session_time, duration_minutes, topic, location, attendance_code, created_by) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$course_id, $session_date, $session_time, $duration_minutes, $topic, $location, $attendance_code, $_SESSION['user_id']]);
            
            $_SESSION['success_message'] = "Session created successfully!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error creating session: " . $e->getMessage();
    }
    
    header("Location: sessions.php");
    exit();
}

// Get courses for dropdown
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get courses for this FI
    $coursesQuery = "SELECT id, course_code, course_name FROM am_courses 
                    WHERE faculty_intern_id = ? AND status = 'active'
                    ORDER BY course_code";
    $coursesStmt = $db->prepare($coursesQuery);
    $coursesStmt->execute([$_SESSION['user_id']]);
    $courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get sessions
    $sessionsQuery = "SELECT 
                        s.*,
                        c.course_code,
                        c.course_name,
                        u.fname as created_fname,
                        u.lname as created_lname,
                        COUNT(DISTINCT a.id) as attendance_count,
                        (SELECT COUNT(DISTINCT student_id) 
                         FROM am_enrollments 
                         WHERE course_id = c.id AND status = 'approved') as total_students
                      FROM am_sessions s
                      JOIN am_courses c ON s.course_id = c.id
                      JOIN am_users u ON s.created_by = u.id
                      LEFT JOIN am_attendance a ON s.id = a.session_id
                      WHERE c.faculty_intern_id = ?
                      GROUP BY s.id
                      ORDER BY s.session_date DESC, s.session_time DESC";
    
    $sessionsStmt = $db->prepare($sessionsQuery);
    $sessionsStmt->execute([$_SESSION['user_id']]);
    $sessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $courses = [];
    $sessions = [];
    error_log("Sessions error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Overview - Faculty Intern Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/style.css">
    <link rel="stylesheet" href="../styles/Student_Dashboard.css">
    <link rel="stylesheet" href="../styles/sessionSchedule.css">
    <link rel="stylesheet" href="../styles/FI_Dashboard.css">
    <style>
        .attendance-percentage {
            font-weight: bold;
            color: #2e7d32;
        }
        
        .no-attendance {
            color: #757575;
            font-style: italic;
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
        
        .welcome-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-header h2 {
            color: #3B0270;
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
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h2 {
            font-size: 1.5rem;
            color: #3B0270;
            margin: 0;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #344F1F;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: #F4991A;
            outline: none;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .form-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
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
            <div class="welcome-section">
                <div class="section-header">
                    <h2>Session Overview</h2>
                    <button class="btn" id="addSessionBtn">
                        <i class="fas fa-plus"></i> Schedule Session
                    </button>
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

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Course</th>
                                <th>Topic</th>
                                <th>Location</th>
                                <th>Attendance</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sessions)): ?>
                            <tr>
                                <td colspan="8" class="no-data">
                                    <i class="fas fa-calendar fa-2x" style="margin-bottom: 10px; display: block; color: #ccc;"></i>
                                    No sessions scheduled yet
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($sessions as $session): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($session['session_date'])); ?></td>
                                <td><?php echo date('H:i', strtotime($session['session_time'])); ?></td>
                                <td><?php echo htmlspecialchars($session['course_code']); ?></td>
                                <td><?php echo htmlspecialchars($session['topic'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($session['location'] ?: 'TBA'); ?></td>
                                <td>
                                    <?php if ($session['total_students'] > 0): ?>
                                    <span class="attendance-percentage">
                                        <?php echo $session['attendance_count']; ?>/<?php echo $session['total_students']; ?>
                                        (<?php echo round(($session['attendance_count'] / $session['total_students']) * 100); ?>%)
                                    </span>
                                    <?php else: ?>
                                    <span class="no-attendance">No students enrolled</span>
                                    <?php endif; ?>
                                </td>
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
                                <td>
                                    <a href="session_details.php?id=<?php echo $session['id']; ?>" class="btn-small btn-outline" title="View Details">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if ($session['attendance_code']): ?>
                                    <button class="btn-small" style="background-color: #4361ee; color: white;" onclick="showAttendanceCode('<?php echo htmlspecialchars($session['attendance_code']); ?>')" title="Show Attendance Code">
                                        <i class="fas fa-key"></i> Code
                                    </button>
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
    
    <!-- Add Session Modal -->
    <div class="modal" id="addSessionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Schedule New Session</h2>
                <button class="close-btn">&times;</button>
            </div>
            <form id="sessionForm" method="POST" action="sessions.php">
                <input type="hidden" name="add_session" value="1">
                <div class="form-group">
                    <label for="course_id">Course *</label>
                    <select id="course_id" name="course_id" class="form-control" required>
                        <option value="">Select a course</option>
                        <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course['id']; ?>">
                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="session_date">Date *</label>
                        <input type="date" id="session_date" name="session_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="session_time">Time *</label>
                        <input type="time" id="session_time" name="session_time" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="duration_minutes">Duration (minutes)</label>
                    <input type="number" id="duration_minutes" name="duration_minutes" class="form-control" min="30" max="240" value="60">
                </div>
                <div class="form-group">
                    <label for="topic">Topic</label>
                    <input type="text" id="topic" name="topic" class="form-control" placeholder="e.g., Introduction to Algorithms">
                </div>
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" class="form-control" placeholder="e.g., Room 101">
                </div>
                <div class="form-group">
                    <label for="attendance_code">Attendance Code (Optional)</label>
                    <input type="text" id="attendance_code" name="attendance_code" class="form-control" placeholder="e.g., ALGO2024" maxlength="20">
                    <small class="form-text">Students will use this code to mark attendance</small>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn">
                        <i class="fas fa-calendar-plus"></i> Schedule Session
                    </button>
                    <button type="button" class="btn btn-outline" id="cancelSessionBtn">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functionality
        const addSessionBtn = document.getElementById('addSessionBtn');
        const sessionModal = document.getElementById('addSessionModal');
        const sessionCloseBtn = sessionModal.querySelector('.close-btn');
        const cancelSessionBtn = document.getElementById('cancelSessionBtn');

        addSessionBtn.onclick = () => sessionModal.style.display = 'flex';
        sessionCloseBtn.onclick = cancelSessionBtn.onclick = () => sessionModal.style.display = 'none';
        window.onclick = e => { if (e.target === sessionModal) sessionModal.style.display = 'none'; }

        // Form validation
        document.getElementById('sessionForm').addEventListener('submit', function(e) {
            const courseId = document.getElementById('course_id').value;
            const sessionDate = document.getElementById('session_date').value;
            const sessionTime = document.getElementById('session_time').value;
            
            if (!courseId || !sessionDate || !sessionTime) {
                e.preventDefault();
                alert('Please fill all required fields!');
                return false;
            }
            
            // Validate date is not in the past
            const today = new Date().toISOString().split('T')[0];
            if (sessionDate < today) {
                e.preventDefault();
                alert('Session date cannot be in the past!');
                return false;
            }
            
            return true;
        });

        // Show attendance code
        function showAttendanceCode(code) {
            alert('Attendance Code: ' + code + '\n\nShare this code with students for attendance.');
        }

        // Set default time to next hour
        window.onload = function() {
            const now = new Date();
            now.setHours(now.getHours() + 1);
            now.setMinutes(0);
            const timeString = now.toTimeString().substring(0, 5);
            if (document.getElementById('session_time')) {
                document.getElementById('session_time').value = timeString;
            }
        };
    </script>
</body>
</html>
