<?php
// C:\xampp\htdocs\Attandance\FI_attendance.php
require_once '../config/session.php';
require_once '../config/database.php';
requireAuth();

if (!hasRole('faculty')) {
    header("Location: ../auth/logout.php");
    exit();
}

// Initialize variables
$course_filter = isset($_GET['course']) ? intval($_GET['course']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$reports = [];
$courses = [];
$filtered = false;

try {
    $database = new Database();
    $db = $database->getConnection();
    $user_id = $_SESSION['user_id'];
    
    // Get faculty's courses for filter dropdown
    $coursesQuery = "SELECT c.id, c.course_code, c.course_name 
                     FROM am_courses c
                     WHERE c.faculty_intern_id = ?
                     ORDER BY c.course_code";
    $coursesStmt = $db->prepare($coursesQuery);
    $coursesStmt->execute([$user_id]);
    $courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build query for attendance reports
    $reportQuery = "SELECT 
                       u.id as student_id,
                       u.fname,
                       u.lname,
                       u.userid,
                       c.course_code,
                       c.course_name,
                       COUNT(DISTINCT s.id) as total_sessions,
                       SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
                       SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                       SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count
                    FROM am_enrollments e
                    JOIN am_users u ON e.student_id = u.id
                    JOIN am_courses c ON e.course_id = c.id
                    LEFT JOIN am_sessions s ON c.id = s.course_id
                    LEFT JOIN am_attendance a ON s.id = a.session_id AND a.student_id = u.id
                    WHERE e.status = 'approved'
                    AND c.faculty_intern_id = ?";
    
    $params = [$user_id];
    
    // Apply filters
    if ($course_filter) {
        $reportQuery .= " AND c.id = ?";
        $params[] = $course_filter;
        $filtered = true;
    }
    
    if ($date_from) {
        $reportQuery .= " AND s.session_date >= ?";
        $params[] = $date_from;
        $filtered = true;
    }
    
    if ($date_to) {
        $reportQuery .= " AND s.session_date <= ?";
        $params[] = $date_to;
        $filtered = true;
    }
    
    $reportQuery .= " GROUP BY u.id, c.id
                      ORDER BY c.course_code, u.lname, u.fname";
    
    $reportStmt = $db->prepare($reportQuery);
    $reportStmt->execute($params);
    $reports = $reportStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Attendance reports error: " . $e->getMessage());
    $reports = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/style.css">
    <style>
        /* Same CSS as dashboard.php - just extract the nav and layout styles */
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
    margin-left: 250px;
    padding: 40px 50px;            /* Reduced from 100px */
    background-color: #F2EAD3;

    max-width: calc(100vw - 250px); /* Prevent overflow */
    overflow-x: hidden;
}

.welcome-section {
    background: white;
    border-radius: 10px;
    padding: 30px 35px;
    box-shadow: 4px 4px 12px rgba(59, 2, 112, 0.3);
    margin: auto;                    /* Center content */
    
    max-width: 1200px;               /* LIMIT SIZE SO IT DOES NOT STRETCH TOO LARGE */
    width: 100%;
}

        
        .welcome-section h1 {
            margin-bottom: 20px;
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
        
        .btn-success {
            background-color: #28a745;
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        .btn-warning {
            background-color: #ffc107;
            color: #000;
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
        }
        
        .btn-primary {
            background-color: #007bff;
        }
        
        .btn-primary:hover {
            background-color: #0069d9;
        }
        
        .filter-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 4px solid #3B0270;
        }
        
        .card-header {
            font-weight: bold;
            color: #344F1F;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 5px;
            font-weight: 500;
            color: #344F1F;
        }
        
        .form-group select,
        .form-group input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
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
        
        .attendance-percentage {
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 15px;
            text-align: center;
            display: inline-block;
            min-width: 60px;
        }
        
        .percentage-high {
            background: #d4edda;
            color: #155724;
        }
        
        .percentage-medium {
            background: #fff3cd;
            color: #856404;
        }
        
        .percentage-low {
            background: #f8d7da;
            color: #721c24;
        }
        
        .export-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid #e9ecef;
        }
        
        .stat-item .value {
            font-size: 24px;
            font-weight: bold;
            color: #3B0270;
            margin-bottom: 5px;
        }
        
        .stat-item .label {
            color: #666;
            font-size: 14px;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .table-container {
    max-width: 1000px;
    margin: auto;
}

        @media (max-width: 768px) {
            nav {
                width: 200px;
            }
            
            #welcomeboard {
                margin-left: 200px;
                padding: 20px;
            }
            
            .welcome-section {
                padding: 20px;
                margin: 10px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .export-actions {
                flex-direction: column;
            }
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
            <a href="sessions.php" data-section="sessions"><i class="fas fa-calendar-alt"></i> <span>Session Overview</span></a>
            <a href="attendance_reports.php" class="active" data-section="attendance"><i class="fas fa-clipboard-check"></i> <span>Attendance Reports</span></a>
            <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>  
        </nav>

        <div id="welcomeboard">
            <div class="welcome-section">
                <h1><i class="fas fa-clipboard-check"></i> Attendance Reports</h1>
                
                <!-- Filters -->
                <div class="filter-card">
                    <div class="card-header">Filters</div>
                    <form method="GET" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="course">Course</label>
                                <select id="course" name="course">
                                    <option value="">All Courses</option>
                                    <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" <?php echo ($course_filter == $course['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_code']); ?> - <?php echo htmlspecialchars($course['course_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="date-from">From Date</label>
                                <input type="date" id="date-from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="form-group">
                                <label for="date-to">To Date</label>
                                <input type="date" id="date-to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <?php if ($filtered): ?>
                        <a href="attendance_reports.php" class="btn" style="margin-left: 10px;">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <!-- Summary Statistics -->
                <?php if (!empty($reports)): 
                    $totalStudents = count($reports);
                    $avgAttendance = 0;
                    $totalPresent = 0;
                    $totalSessions = 0;
                    
                    foreach ($reports as $report) {
                        $totalSessions += $report['total_sessions'];
                        $totalPresent += $report['present_count'];
                    }
                    
                    if ($totalSessions > 0) {
                        $avgAttendance = round(($totalPresent / ($totalSessions * $totalStudents)) * 100);
                    }
                ?>
                <div class="stats-summary">
                    <div class="stat-item">
                        <div class="value"><?php echo $totalStudents; ?></div>
                        <div class="label">Total Students</div>
                    </div>
                    <div class="stat-item">
                        <div class="value"><?php echo $totalSessions; ?></div>
                        <div class="label">Total Sessions</div>
                    </div>
                    <div class="stat-item">
                        <div class="value"><?php echo $totalPresent; ?></div>
                        <div class="label">Total Present</div>
                    </div>
                    <div class="stat-item">
                        <div class="value"><?php echo $avgAttendance; ?>%</div>
                        <div class="label">Average Attendance</div>
                    </div>
                </div>
                
                <!-- Export Actions -->
                <div class="export-actions">
                    <button class="btn btn-success" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </button>
                    <button class="btn btn-warning" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </button>
                </div>
                
                <!-- Reports Table -->
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Student ID</th>
                                <th>Course</th>
                                <th>Total Sessions</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Late</th>
                                <th>Attendance %</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): 
                                $total = $report['total_sessions'];
                                $present = $report['present_count'];
                                $absent = $report['absent_count'];
                                $late = $report['late_count'];
                                $percentage = $total > 0 ? round(($present / $total) * 100) : 0;
                                $percentageClass = $percentage >= 80 ? 'percentage-high' : ($percentage >= 60 ? 'percentage-medium' : 'percentage-low');
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($report['fname'] . ' ' . $report['lname']); ?></td>
                                <td><?php echo htmlspecialchars($report['userid']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($report['course_code']); ?><br>
                                    <small><?php echo htmlspecialchars($report['course_name']); ?></small>
                                </td>
                                <td><?php echo $total; ?></td>
                                <td><span style="color: #28a745; font-weight: bold;"><?php echo $present; ?></span></td>
                                <td><span style="color: #dc3545;"><?php echo $absent; ?></span></td>
                                <td><span style="color: #ffc107;"><?php echo $late; ?></span></td>
                                <td>
                                    <span class="attendance-percentage <?php echo $percentageClass; ?>">
                                        <?php echo $percentage; ?>%
                                    </span>
                                </td>
                                <td>
                                    <a href="student_attendance.php?student_id=<?php echo $report['student_id']; ?>&course_id=<?php echo $course_filter ?: ''; ?>" 
                                       class="btn" style="padding: 5px 10px; font-size: 12px;">
                                        <i class="fas fa-eye"></i> Details
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-clipboard-check fa-3x" style="margin-bottom: 20px; color: #ddd;"></i>
                    <h3>No Attendance Data Found</h3>
                    <p>
                        <?php echo $filtered ? 'No records match your filters.' : 'No attendance records available yet.'; ?>
                    </p>
                    <?php if ($filtered): ?>
                    <a href="attendance_reports.php" class="btn">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                    <?php else: ?>
                    <a href="sessions.php" class="btn">
                        <i class="fas fa-calendar-plus"></i> Schedule a Session
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function exportToPDF() {
            alert('PDF export functionality will be implemented soon!');
            // Future implementation: Generate PDF using jsPDF or server-side PDF generation
        }
        
        function exportToExcel() {
            // Create a simple CSV export
            let csv = 'Student Name,Student ID,Course,Total Sessions,Present,Absent,Late,Attendance %\n';
            
            document.querySelectorAll('tbody tr').forEach(row => {
                const cells = row.querySelectorAll('td');
                const rowData = [
                    cells[0].textContent.trim(),
                    cells[1].textContent.trim(),
                    cells[2].querySelector('small') ? 
                        cells[2].textContent.replace(/\n/g, ' ').trim() : 
                        cells[2].textContent.trim(),
                    cells[3].textContent.trim(),
                    cells[4].textContent.trim(),
                    cells[5].textContent.trim(),
                    cells[6].textContent.trim(),
                    cells[7].textContent.trim()
                ];
                csv += rowData.map(cell => `"${cell}"`).join(',') + '\n';
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'attendance_report_<?php echo date('Y-m-d'); ?>.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
        
        // Set default date range to last 30 days
        document.addEventListener('DOMContentLoaded', function() {
            const dateFrom = document.getElementById('date-from');
            const dateTo = document.getElementById('date-to');
            
            if (!dateFrom.value) {
                const thirtyDaysAgo = new Date();
                thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
                dateFrom.valueAsDate = thirtyDaysAgo;
            }
            
            if (!dateTo.value) {
                dateTo.valueAsDate = new Date();
            }
        });
    </script>
</body>
</html>
