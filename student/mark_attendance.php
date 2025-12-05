<?php
// C:\xampp\htdocs\Attandance\student\mark_attendance.php
require_once '../config/session.php';
require_once '../config/database.php';
requireAuth();

// Check if user has student role
if (!isStudent()) {
    header("Location: ../auth/logout.php");
    exit();
}

$error = '';
$success = '';
$availableSessions = [];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if form was submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance_code'])) {
        $attendanceCode = trim($_POST['attendance_code']);
        
        if (empty($attendanceCode)) {
            $error = 'Please enter an attendance code';
        } else {
            // Validate attendance code
            $query = "SELECT 
                        s.*,
                        c.course_code,
                        c.course_name,
                        a.id as attendance_id,
                        a.status as existing_status
                     FROM am_sessions s
                     JOIN am_courses c ON s.course_id = c.id
                     JOIN am_enrollments e ON c.id = e.course_id AND e.student_id = ? AND e.status = 'approved'
                     LEFT JOIN am_attendance a ON s.id = a.session_id AND a.student_id = ?
                     WHERE s.attendance_code = ?
                     AND s.status = 'upcoming'
                     AND DATE(s.session_date) = CURDATE()";
            
            $stmt = $db->prepare($query);
            $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $attendanceCode]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($session) {
                // Check if attendance is already marked
                if ($session['attendance_id']) {
                    $error = 'You have already marked attendance for this session!';
                } else {
                    // Check if session is still within valid time (within 30 minutes of start time)
                    $sessionDateTime = $session['session_date'] . ' ' . $session['session_time'];
                    $currentTime = date('Y-m-d H:i:s');
                    $sessionTime = strtotime($sessionDateTime);
                    $currentTimestamp = strtotime($currentTime);
                    
                    $timeDifference = $currentTimestamp - $sessionTime;
                    $timeDifferenceMinutes = $timeDifference / 60;
                    
                    // Determine if late (more than 5 minutes after start)
                    $status = ($timeDifferenceMinutes > 5 && $timeDifferenceMinutes <= 30) ? 'late' : 'present';
                    
                    if ($timeDifferenceMinutes > 30) {
                        $error = 'Attendance window has closed for this session (more than 30 minutes late)';
                    } elseif ($timeDifferenceMinutes < -30) {
                        $error = 'Attendance window has not opened yet (more than 30 minutes early)';
                    } else {
                        // Mark attendance
                        $insertQuery = "INSERT INTO am_attendance 
                                       (session_id, student_id, attendance_time, status, marked_by, notes) 
                                       VALUES (?, ?, NOW(), ?, ?, ?)";
                        $insertStmt = $db->prepare($insertQuery);
                        $result = $insertStmt->execute([
                            $session['id'],
                            $_SESSION['user_id'],
                            $status,
                            $_SESSION['user_id'], // Self-marked
                            "Self-marked with code: $attendanceCode"
                        ]);
                        
                        if ($result) {
                            $success = "Attendance marked successfully! Status: " . ucfirst($status);
                        } else {
                            $error = 'Failed to mark attendance. Please try again.';
                        }
                    }
                }
            } else {
                $error = 'Invalid attendance code or you are not enrolled in this course';
            }
        }
    }
    
    // Get today's upcoming sessions for the student
    $todayQuery = "SELECT 
                    s.*,
                    c.course_code,
                    c.course_name,
                    s.attendance_code,
                    a.id as attendance_id,
                    a.status as attendance_status
                  FROM am_sessions s
                  JOIN am_courses c ON s.course_id = c.id
                  JOIN am_enrollments e ON c.id = e.course_id AND e.student_id = ? AND e.status = 'approved'
                  LEFT JOIN am_attendance a ON s.id = a.session_id AND a.student_id = ?
                  WHERE s.status = 'upcoming'
                  AND DATE(s.session_date) = CURDATE()
                  ORDER BY s.session_time";
    
    $todayStmt = $db->prepare($todayQuery);
    $todayStmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $availableSessions = $todayStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    error_log("Attendance mark error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance - Student Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/style.css">
    <link rel="stylesheet" href="../styles/Student_Dashboard.css">
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
            margin-bottom: 20px;
            text-align: center;
            color: #3B0270;
        }
        
        .attendance-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .code-input-container {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .code-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
            align-items: center;
        }
        
        .code-input-group {
            width: 100%;
            max-width: 400px;
        }
        
        .code-input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #344F1F;
            text-align: left;
        }
        
        .code-input {
            width: 100%;
            padding: 15px;
            font-size: 18px;
            text-align: center;
            letter-spacing: 5px;
            border: 2px solid #ddd;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .code-input:focus {
            outline: none;
            border-color: #3B0270;
            box-shadow: 0 0 0 3px rgba(59, 2, 112, 0.1);
        }
        
        .btn {
            background-color: #F4991A;
            color: white;
            border: none;
            padding: 12px 30px;
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
        
        .btn-large {
            padding: 15px 40px;
            font-size: 18px;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            text-align: center;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .sessions-container {
            margin-top: 40px;
        }
        
        .sessions-container h2 {
            color: #344F1F;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .session-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #F4991A;
        }
        
        .session-card.completed {
            border-left-color: #28a745;
        }
        
        .session-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .session-header h3 {
            color: #3B0270;
            margin: 0;
        }
        
        .attendance-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-present {
            background: #d4edda;
            color: #155724;
        }
        
        .status-late {
            background: #ffeaa7;
            color: #e17055;
        }
        
        .session-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-item label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .detail-item span {
            font-weight: 500;
            color: #333;
        }
        
        .instructions {
            background: #e8f4f8;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .instructions h3 {
            color: #344F1F;
            margin-bottom: 15px;
        }
        
        .instructions ul {
            padding-left: 20px;
            margin-bottom: 0;
        }
        
        .instructions li {
            margin-bottom: 8px;
            color: #555;
        }
        
        .time-info {
            background: #fff8e1;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }
        
        .time-info .current-time {
            font-size: 20px;
            font-weight: bold;
            color: #3B0270;
        }
        
        .no-sessions {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .no-sessions i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #ddd;
        }
        
        @media (max-width: 768px) {
            nav {
                width: 200px;
            }
            
            #welcomeboard {
                margin-left: 200px;
                padding: 20px;
            }
            
            .session-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .code-input {
                font-size: 16px;
                padding: 12px;
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
            <a href="enroll.php" data-section="enroll"><i class="fas fa-plus-circle"></i> <span>Enroll in Courses</span></a>
            <a href="attendance.php" data-section="attendance"><i class="fas fa-clipboard-check"></i> <span>My Attendance</span></a>
            <a href="mark_attendance.php" class="active" data-section="mark_attendance"><i class="fas fa-qrcode"></i> <span>Mark Attendance</span></a>
            <a href="performance.php" data-section="performance"><i class="fas fa-chart-line"></i> <span>Performance</span></a>
            <a href="profile.php" data-section="profile"><i class="fas fa-user"></i> <span>Profile</span></a>
            <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>  
        </nav>

        <div id="welcomeboard">
            <div class="welcome-section">
                <h1><i class="fas fa-qrcode"></i> Mark Attendance</h1>
                
                <div class="attendance-container">
                    <!-- Current Time Display -->
                    <div class="time-info">
                        <p>Current Date & Time:</p>
                        <div class="current-time">
                            <i class="fas fa-clock"></i> 
                            <span id="currentTime"><?php echo date('F j, Y, h:i:s A'); ?></span>
                        </div>
                    </div>
                    
                    <?php if ($success): ?>
                    <div class="message success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Instructions -->
                    <div class="instructions">
                        <h3><i class="fas fa-info-circle"></i> How to Mark Attendance</h3>
                        <ul>
                            <li>Get the attendance code from your instructor at the beginning of the session</li>
                            <li>Enter the code exactly as provided (case-sensitive)</li>
                            <li>Attendance can only be marked during the session time ±30 minutes</li>
                            <li>Arriving more than 5 minutes late will be marked as "Late"</li>
                            <li>You can only mark attendance once per session</li>
                        </ul>
                    </div>
                    
                    <!-- Attendance Code Form -->
                    <div class="code-input-container">
                        <h2 style="color: #344F1F; margin-bottom: 25px;">
                            <i class="fas fa-key"></i> Enter Attendance Code
                        </h2>
                        
                        <form method="POST" class="code-form">
                            <div class="code-input-group">
                                <label for="attendance_code">Attendance Code</label>
                                <input 
                                    type="text" 
                                    id="attendance_code" 
                                    name="attendance_code" 
                                    class="code-input" 
                                    placeholder="Enter code here" 
                                    maxlength="20"
                                    autocomplete="off"
                                    autofocus
                                    required
                                >
                            </div>
                            
                            <button type="submit" class="btn btn-large">
                                <i class="fas fa-check"></i> Mark Attendance
                            </button>
                        </form>
                    </div>
                    
                    <!-- Today's Sessions -->
                    <div class="sessions-container">
                        <h2><i class="fas fa-calendar-day"></i> Today's Sessions</h2>
                        
                        <?php if (empty($availableSessions)): ?>
                        <div class="no-sessions">
                            <i class="fas fa-calendar-times"></i>
                            <h3>No Sessions Today</h3>
                            <p>You don't have any scheduled sessions for today.</p>
                        </div>
                        <?php else: ?>
                            <?php foreach ($availableSessions as $session): ?>
                            <div class="session-card <?php echo $session['attendance_id'] ? 'completed' : ''; ?>">
                                <div class="session-header">
                                    <h3><?php echo htmlspecialchars($session['course_code']); ?> - <?php echo htmlspecialchars($session['course_name']); ?></h3>
                                    <?php if ($session['attendance_id']): ?>
                                    <span class="attendance-status status-<?php echo $session['attendance_status']; ?>">
                                        <i class="fas fa-<?php echo $session['attendance_status'] === 'present' ? 'check-circle' : 'clock'; ?>"></i>
                                        <?php echo ucfirst($session['attendance_status']); ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="attendance-status status-pending">
                                        <i class="fas fa-hourglass-half"></i> Attendance Pending
                                    </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="session-details">
                                    <div class="detail-item">
                                        <label><i class="fas fa-calendar"></i> Date</label>
                                        <span><?php echo date('F j, Y', strtotime($session['session_date'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <label><i class="fas fa-clock"></i> Time</label>
                                        <span><?php echo date('h:i A', strtotime($session['session_time'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <label><i class="fas fa-map-marker-alt"></i> Location</label>
                                        <span><?php echo htmlspecialchars($session['location'] ?: 'Not specified'); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <label><i class="fas fa-book"></i> Topic</label>
                                        <span><?php echo htmlspecialchars($session['topic'] ?: 'General Session'); ?></span>
                                    </div>
                                    <?php if ($session['attendance_code']): ?>
                                    <div class="detail-item">
                                        <label><i class="fas fa-key"></i> Session Code</label>
                                        <span style="font-family: monospace; letter-spacing: 2px;">
                                            <?php echo htmlspecialchars($session['attendance_code']); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!$session['attendance_id']): ?>
                                <div style="margin-top: 15px; text-align: center;">
                                    <small style="color: #666;">
                                        <i class="fas fa-info-circle"></i> 
                                        Get the attendance code from your instructor during the session
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Update current time every second
        function updateCurrentTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true 
            };
            document.getElementById('currentTime').textContent = now.toLocaleDateString('en-US', options);
        }
        
        // Update time immediately and then every second
        updateCurrentTime();
        setInterval(updateCurrentTime, 1000);
        
        // Auto-uppercase and remove spaces from attendance code
        const codeInput = document.getElementById('attendance_code');
        if (codeInput) {
            codeInput.addEventListener('input', function(e) {
                this.value = this.value.toUpperCase().replace(/\s/g, '');
            });
        }
        
        // Focus on input field
        document.addEventListener('DOMContentLoaded', function() {
            if (codeInput && !codeInput.value) {
                codeInput.focus();
            }
        });
    </script>
</body>
</html>