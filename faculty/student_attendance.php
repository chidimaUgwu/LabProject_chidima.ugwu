<?php
// C:\xampp\htdocs\Attandance\faculty\student_attendance.php
require_once '../config/session.php';
require_once '../config/database.php';
requireAuth();

// Check if user has faculty/instructor role
if (!isInstructor()) {
    header("Location: ../auth/logout.php");
    exit();
}

if (!isset($_GET['student_id']) || !is_numeric($_GET['student_id'])) {
    header("Location: attendance_reports.php");
    exit();
}

$student_id = intval($_GET['student_id']);
$course_id = isset($_GET['course_id']) && is_numeric($_GET['course_id']) ? intval($_GET['course_id']) : 0;

try {
    $database = new Database();
    $db = $database->getConnection();
    $user_id = $_SESSION['user_id'];
    
    // Get student information
    $studentQuery = "SELECT id, fname, lname, userid, email, phone FROM am_users 
                     WHERE id = ? AND role = 'student'";
    $studentStmt = $db->prepare($studentQuery);
    $studentStmt->execute([$student_id]);
    $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        header("Location: attendance_reports.php");
        exit();
    }
    
    // Get faculty's courses
    $coursesQuery = "SELECT id, course_code, course_name FROM am_courses 
                     WHERE faculty_intern_id = ? AND status = 'active'
                     ORDER BY course_code";
    $coursesStmt = $db->prepare($coursesQuery);
    $coursesStmt->execute([$user_id]);
    $faculty_courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get student's attendance details
    $attendanceQuery = "SELECT 
                           a.*,
                           s.session_date,
                           s.session_time,
                           s.topic,
                           s.location,
                           s.attendance_code,
                           c.course_code,
                           c.course_name,
                           c.id as course_id
                        FROM am_attendance a
                        JOIN am_sessions s ON a.session_id = s.id
                        JOIN am_courses c ON s.course_id = c.id
                        WHERE a.student_id = ?
                        AND s.created_by = ?";
    
    $params = [$student_id, $user_id];
    
    if ($course_id > 0) {
        $attendanceQuery .= " AND c.id = ?";
        $params[] = $course_id;
    }
    
    $attendanceQuery .= " ORDER BY s.session_date DESC, s.session_time DESC";
    
    $attendanceStmt = $db->prepare($attendanceQuery);
    $attendanceStmt->execute($params);
    $attendanceRecords = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get attendance statistics
    $statsQuery = "SELECT 
                      c.id as course_id,
                      c.course_code,
                      c.course_name,
                      COUNT(s.id) as total_sessions,
                      SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
                      SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                      SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count
                   FROM am_courses c
                   LEFT JOIN am_sessions s ON c.id = s.course_id
                   LEFT JOIN am_attendance a ON s.id = a.session_id AND a.student_id = ?
                   WHERE c.faculty_intern_id = ?
                   GROUP BY c.id
                   ORDER BY c.course_code";
    
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->execute([$student_id, $user_id]);
    $courseStats = $statsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate overall statistics
    $overallStats = ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0, 'rate' => 0];
    foreach ($courseStats as $stat) {
        $overallStats['total'] += $stat['total_sessions'];
        $overallStats['present'] += $stat['present_count'];
        $overallStats['absent'] += $stat['absent_count'];
        $overallStats['late'] += $stat['late_count'];
    }
    
    if ($overallStats['total'] > 0) {
        $overallStats['rate'] = round(($overallStats['present'] / $overallStats['total']) * 100);
    }
    
} catch (PDOException $e) {
    error_log("Student attendance error: " . $e->getMessage());
    header("Location: attendance_reports.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Attendance Details - Faculty Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/style.css">
    <style>
        /* Reuse the base styles from courses.php */
        .student-header {
            background: linear-gradient(135deg, #5e9038, #344F1F);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .student-info h2 {
            margin: 0 0 10px 0;
            color: white;
        }
        
        .student-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .detail-box {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 8px;
        }
        
        .detail-box .label {
            font-size: 12px;
            opacity: 0.8;
            margin-bottom: 5px;
        }
        
        .detail-box .value {
            font-weight: bold;
            font-size: 16px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid #e9ecef;
        }
        
        .stat-box .value {
            font-size: 28px;
            font-weight: bold;
            color: #3B0270;
            margin-bottom: 5px;
        }
        
        .stat-box .label {
            color: #666;
            font-size: 14px;
        }
        
        .course-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .course-stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #3B0270;
        }
        
        .course-stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .attendance-bar {
            height: 10px;
            background: #e9ecef;
            border-radius: 5px;
            margin-top: 10px;
            overflow: hidden;
        }
        
        .attendance-fill {
            height: 100%;
            transition: width 0.3s;
        }
        
        .attendance-good { background: #28a745; }
        .attendance-warning { background: #ffc107; }
        .attendance-danger { background: #dc3545; }
        
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
        
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="top">
        <div class="logo-container">
            <img src="../images/logo.png" alt="Company Logo" srcset="">
            <h4 class="dashboard-title">FACULTY DASHBOARD</h4>
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
            <a href="courses.php" data-section="courses"><i class="fas fa-book"></i> <span>Course Management</span></a>
            <a href="sessions.php" data-section="sessions"><i class="fas fa-calendar-alt"></i> <span>Session Overview</span></a>
            <a href="attendance_reports.php" class="active" data-section="attendance"><i class="fas fa-clipboard-check"></i> <span>Attendance Reports</span></a>
            <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>  
        </nav>

        <div id="welcomeboard">
            <div class="welcome-section">
                <!-- Student Header -->
                <div class="student-header">
                    <div class="student-info">
                        <h2>
                            <i class="fas fa-user-graduate"></i> 
                            <?php echo htmlspecialchars($student['fname'] . ' ' . $student['lname']); ?>
                        </h2>
                        <div class="student-details">
                            <div class="detail-box">
                                <div class="label">Student ID</div>
                                <div class="value"><?php echo htmlspecialchars($student['userid']); ?></div>
                            </div>
                            <div class="detail-box">
                                <div class="label">Email</div>
                                <div class="value"><?php echo htmlspecialchars($student['email']); ?></div>
                            </div>
                            <div class="detail-box">
                                <div class="label">Phone</div>
                                <div class="value"><?php echo htmlspecialchars($student['phone']); ?></div>
                            </div>
                            <div class="detail-box">
                                <div class="label">Overall Attendance</div>
                                <div class="value"><?php echo $overallStats['rate']; ?>%</div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <a href="attendance_reports.php" class="btn" style="background: white; color: #344F1F;">
                            <i class="fas fa-arrow-left"></i> Back to Reports
                        </a>
                    </div>
                </div>
                
                <!-- Overall Statistics -->
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="value"><?php echo $overallStats['total']; ?></div>
                        <div class="label">Total Sessions</div>
                    </div>
                    <div class="stat-box">
                        <div class="value"><?php echo $overallStats['present']; ?></div>
                        <div class="label">Present</div>
                    </div>
                    <div class="stat-box">
                        <div class="value"><?php echo $overallStats['absent']; ?></div>
                        <div class="label">Absent</div>
                    </div>
                    <div class="stat-box">
                        <div class="value"><?php echo $overallStats['late']; ?></div>
                        <div class="label">Late Arrivals</div>
                    </div>
                </div>
                
                <!-- Course Filter -->
                <div class="filter-section">
                    <form method="GET" action="" class="filter-form">
                        <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                        <div class="form-group">
                            <label for="course_filter">Filter by Course</label>
                            <select id="course_filter" name="course_id">
                                <option value="">All Courses</option>
                                <?php foreach ($faculty_courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>" <?php echo ($course_id == $course['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_code']); ?> - <?php echo htmlspecialchars($course['course_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filter
                        </button>
                        <?php if ($course_id): ?>
                        <a href="student_attendance.php?student_id=<?php echo $student_id; ?>" class="btn">
                            <i class="fas fa-times"></i> Clear Filter
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <!-- Export Button -->
                <div class="action-buttons">
                    <button class="btn btn-warning" onclick="exportAttendanceToCSV()">
                        <i class="fas fa-file-excel"></i> Export to CSV
                    </button>
                </div>
                
                <!-- Course-wise Statistics -->
                <?php if (!empty($courseStats)): ?>
                <div class="section-header">
                    <h2><i class="fas fa-chart-bar"></i> Attendance by Course</h2>
                </div>
                
                <div class="course-stats">
                    <?php foreach ($courseStats as $stat): 
                        $total = $stat['total_sessions'];
                        $present = $stat['present_count'];
                        $absent = $stat['absent_count'];
                        $late = $stat['late_count'];
                        $percentage = $total > 0 ? round(($present / $total) * 100) : 0;
                        $attendanceClass = $percentage >= 80 ? 'attendance-good' : ($percentage >= 60 ? 'attendance-warning' : 'attendance-danger');
                    ?>
                    <div class="course-stat-card">
                        <div class="course-stat-header">
                            <h3 style="margin: 0; color: #344F1F;">
                                <?php echo htmlspecialchars($stat['course_code']); ?>
                            </h3>
                            <span style="font-weight: bold; color: #3B0270;"><?php echo $percentage; ?>%</span>
                        </div>
                        <p style="color: #666; margin: 0 0 10px 0;">
                            <?php echo htmlspecialchars($stat['course_name']); ?>
                        </p>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span style="color: #28a745;"><i class="fas fa-check-circle"></i> <?php echo $present; ?> Present</span>
                            <span style="color: #dc3545;"><i class="fas fa-times-circle"></i> <?php echo $absent; ?> Absent</span>
                            <span style="color: #ffc107;"><i class="fas fa-clock"></i> <?php echo $late; ?> Late</span>
                        </div>
                        <div class="attendance-bar">
                            <div class="attendance-fill <?php echo $attendanceClass; ?>" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Detailed Attendance Records -->
                <div class="section-header">
                    <h2><i class="fas fa-list"></i> Attendance Records</h2>
                </div>
                
                <?php if (empty($attendanceRecords)): ?>
                <div class="no-data">
                    <i class="fas fa-clipboard-check fa-3x" style="margin-bottom: 20px; color: #ddd;"></i>
                    <h3>No Attendance Records Found</h3>
                    <p>
                        <?php echo $course_id ? 'No attendance records for this course.' : 'No attendance records available for this student.'; ?>
                    </p>
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Course</th>
                                <th>Topic</th>
                                <th>Time</th>
                                <th>Location</th>
                                <th>Attendance Code</th>
                                <th>Status</th>
                                <th>Marked Time</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendanceRecords as $record): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($record['session_date'])); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($record['course_code']); ?><br>
                                    <small><?php echo htmlspecialchars($record['course_name']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($record['topic'] ?: 'General Session'); ?></td>
                                <td><?php echo date('h:i A', strtotime($record['session_time'])); ?></td>
                                <td><?php echo htmlspecialchars($record['location'] ?: 'Not specified'); ?></td>
                                <td><code><?php echo htmlspecialchars($record['attendance_code']); ?></code></td>
                                <td>
                                    <span class="attendance-status status-<?php echo $record['status']; ?>">
                                        <?php echo ucfirst($record['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('h:i A', strtotime($record['attendance_time'])); ?></td>
                                <td>
                                    <?php if ($record['notes']): ?>
                                    <span title="<?php echo htmlspecialchars($record['notes']); ?>" style="cursor: help; color: #666;">
                                        <i class="fas fa-sticky-note"></i>
                                    </span>
                                    <?php else: ?>
                                    <span style="color: #ccc;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div style="text-align: center; margin-top: 20px; color: #666; font-size: 14px;">
                    Showing <?php echo count($attendanceRecords); ?> attendance records
                    <?php if ($course_id): ?>
                    for <?php echo htmlspecialchars($attendanceRecords[0]['course_code'] ?? ''); ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Export to CSV
        function exportAttendanceToCSV() {
            let csv = 'Date,Course Code,Course Name,Topic,Time,Location,Attendance Code,Status,Marked Time,Notes\n';
            
            <?php foreach ($attendanceRecords as $record): ?>
            csv += '"<?php echo date('Y-m-d', strtotime($record['session_date'])); ?>",' +
                   '"<?php echo addslashes($record['course_code']); ?>",' +
                   '"<?php echo addslashes($record['course_name']); ?>",' +
                   '"<?php echo addslashes($record['topic'] ?? ''); ?>",' +
                   '"<?php echo date('H:i', strtotime($record['session_time'])); ?>",' +
                   '"<?php echo addslashes($record['location'] ?? ''); ?>",' +
                   '"<?php echo $record['attendance_code']; ?>",' +
                   '"<?php echo $record['status']; ?>",' +
                   '"<?php echo date('H:i', strtotime($record['attendance_time'])); ?>",' +
                   '"<?php echo addslashes($record['notes'] ?? ''); ?>"\n';
            <?php endforeach; ?>
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'attendance_<?php echo $student['userid']; ?>_<?php echo date('Y-m-d'); ?>.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
        
        // Tooltip for notes
        document.addEventListener('DOMContentLoaded', function() {
            const noteElements = document.querySelectorAll('[title]');
            noteElements.forEach(el => {
                el.addEventListener('mouseenter', function(e) {
                    const tooltip = document.createElement('div');
                    tooltip.className = 'tooltip';
                    tooltip.textContent = this.title;
                    tooltip.style.cssText = `
                        position: absolute;
                        background: #333;
                        color: white;
                        padding: 5px 10px;
                        border-radius: 4px;
                        font-size: 12px;
                        z-index: 1000;
                        max-width: 200px;
                        word-wrap: break-word;
                    `;
                    document.body.appendChild(tooltip);
                    
                    const rect = this.getBoundingClientRect();
                    tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + 'px';
                    tooltip.style.left = (rect.left + rect.width/2 - tooltip.offsetWidth/2) + 'px';
                    
                    this._tooltip = tooltip;
                });
                
                el.addEventListener('mouseleave', function() {
                    if (this._tooltip) {
                        document.body.removeChild(this._tooltip);
                        this._tooltip = null;
                    }
                });
            });
        });
    </script>
</body>
</html>