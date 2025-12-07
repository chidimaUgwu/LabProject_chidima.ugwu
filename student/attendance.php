<?php
// C:\xampp\htdocs\Attandance\student\attendance.php
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
    
    // Get all attendance records
    $attendanceQuery = "SELECT 
                           a.*,
                           s.session_date,
                           s.session_time,
                           s.topic,
                           s.location,
                           c.course_code,
                           c.course_name,
                           c.id as course_id
                        FROM am_attendance a
                        JOIN am_sessions s ON a.session_id = s.id
                        JOIN am_courses c ON s.course_id = c.id
                        WHERE a.student_id = ?
                        ORDER BY a.attendance_time DESC";
    $attendanceStmt = $db->prepare($attendanceQuery);
    $attendanceStmt->execute([$_SESSION['user_id']]);
    $attendanceRecords = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get attendance statistics by course
    $statsQuery = "SELECT 
                      c.course_code,
                      c.course_name,
                      c.id as course_id,
                      COUNT(*) as total_sessions,
                      SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
                      SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                      SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count
                   FROM am_attendance a
                   JOIN am_sessions s ON a.session_id = s.id
                   JOIN am_courses c ON s.course_id = c.id
                   WHERE a.student_id = ?
                   GROUP BY c.id
                   ORDER BY c.course_code";
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->execute([$_SESSION['user_id']]);
    $courseStats = $statsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate overall statistics
    $overallStats = [
        'total' => 0,
        'present' => 0,
        'absent' => 0,
        'late' => 0,
        'rate' => 0
    ];
    
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
    $attendanceRecords = [];
    $courseStats = [];
    $overallStats = ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0, 'rate' => 0];
    error_log("Attendance error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance - Student Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/style.css">
    <link rel="stylesheet" href="../styles/Student_Dashboard.css">
    <link rel="stylesheet" href="../styles/sessionSchedule.css">
    <style>
        /* * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
        }
        
        .top {
            background: linear-gradient(135deg, #344F1F 0%, #2a3f19 100%);
            width: 100%;
            height: 80px;
            display: flex;
            align-items: center;
            padding: 0 30px;
            position: fixed;
            top: 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            z-index: 1000;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            gap: 20px;
            flex: 1;
        }
        
        .logo-container img {
            height: 45px;
            width: auto;
        }
        
        .dashboard-title {
            color: #F2EAD3;
            font-size: 22px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info img {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: 2px solid #F4991A;
            object-fit: cover;
        }
        
        .user-details h4 {
            font-size: 15px;
            margin-bottom: 3px;
            color: #F2EAD3;
            font-weight: 600;
        }
        
        .user-details p {
            font-size: 12px;
            color: #BDC3C7;
        }
        
        .board {
            display: flex;
            min-height: 100vh;
            padding-top: 80px;
        }
        
        nav {
            width: 260px;
            background: linear-gradient(180deg, #2C3E50 0%, #34495E 100%);
            display: flex;
            flex-direction: column;
            padding: 30px 0;
            position: fixed;
            height: calc(100vh - 80px);
            overflow-y: auto;
            box-shadow: 2px 0 15px rgba(0,0,0,0.1);
        }
        
        nav a {
            color: #ECF0F1;
            text-decoration: none;
            font-size: 15px;
            padding: 14px 30px;
            margin: 5px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        nav a i {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }
        
        nav a:hover {
            background: rgba(236, 240, 241, 0.15);
            transform: translateX(5px);
        }
        
        nav a.active {
            background: #F4991A;
            color: white;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(244, 153, 26, 0.3);
        }
         */
        #welcomeboard {
            flex: 1;
            padding: 30px;
            margin-left: 260px;
            background-color: #f5f7fa;
        }
        
        .welcome-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
        }
        
        .welcome-section h1 {
            margin-bottom: 25px;
            text-align: center;
            color: #344F1F;
            font-size: 28px;
            font-weight: 700;
            position: relative;
            padding-bottom: 15px;
        }
        
        .welcome-section h1:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, #F4991A, #FFB74D);
            border-radius: 2px;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-item {
            text-align: center;
            padding: 25px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        
        .summary-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .summary-item h4 {
            color: #5a6c7d;
            margin-bottom: 12px;
            font-size: 15px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .summary-item .value {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 8px;
        }
        
        .summary-item.rate .value { 
            color: #4361ee;
            background: linear-gradient(135deg, #4361ee, #3a56d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .summary-item.present .value { color: #2e7d32; }
        .summary-item.absent .value { color: #dc3545; }
        .summary-item.late .value { color: #ff9800; }
        
        .summary-item small {
            color: #6c757d;
            font-size: 13px;
            display: block;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        
        .stat-header {
            background: linear-gradient(135deg, #3B0270 0%, #5a1b9a 100%);
            padding: 20px;
            text-align: center;
        }
        
        .stat-card:nth-child(2) .stat-header { background: linear-gradient(135deg, #2e7d32 0%, #4caf50 100%); }
        .stat-card:nth-child(3) .stat-header { background: linear-gradient(135deg, #dc3545 0%, #e53935 100%); }
        .stat-card:nth-child(4) .stat-header { background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%); }
        
        .stat-header h3 {
            color: white;
            margin: 0;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .stat-number {
            padding: 25px 20px;
            text-align: center;
            font-size: 42px;
            font-weight: 800;
            color: #2C3E50;
            background: white;
        }
        
        .btn-container {
            text-align: center;
            margin: 30px 0;
        }
        
        .btn {
            background: linear-gradient(135deg, #F4991A 0%, #FF9800 100%);
            color: white;
            border: none;
            padding: 14px 35px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(244, 153, 26, 0.3);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(244, 153, 26, 0.4);
            background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .section-header h2 {
            color: #344F1F;
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-header h2 i {
            color: #F4991A;
        }
        
        .filter-section {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .filter-select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
            color: #333;
            font-size: 14px;
            min-width: 160px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: #F4991A;
            box-shadow: 0 0 0 3px rgba(244, 153, 26, 0.1);
        }
        
        .export-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 15px;
            border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            border-radius: 10px;
            overflow: hidden;
        }
        
        thead tr {
            background: linear-gradient(135deg, #5e9038 0%, #344F1F 100%);
            color: white;
        }
        
        th {
            padding: 18px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 15px;
            border-bottom: 2px solid #4a7a2d;
        }
        
        td {
            padding: 16px 20px;
            border-bottom: 1px solid #e9ecef;
            background: white;
        }
        
        tbody tr {
            transition: all 0.2s;
        }
        
        tbody tr:hover {
            background-color: #f8f9fa;
            transform: scale(1.002);
        }
        
        .course-link {
            color: #4361ee;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }
        
        .course-link:hover {
            color: #3a56d4;
            text-decoration: underline;
        }
        
        .attendance-status {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            letter-spacing: 0.3px;
        }
        
        .status-present {
            background: rgba(46, 125, 50, 0.1);
            color: #2e7d32;
            border: 1px solid rgba(46, 125, 50, 0.2);
        }
        
        .status-absent {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }
        
        .status-late {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.2);
        }
        
        .progress-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .progress-bar {
            flex: 1;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        .progress-good { background: linear-gradient(90deg, #2e7d32, #4caf50); }
        .progress-warning { background: linear-gradient(90deg, #ffc107, #ffb300); }
        .progress-danger { background: linear-gradient(90deg, #dc3545, #e53935); }
        
        .btn-small {
            padding: 7px 15px;
            font-size: 13px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            font-weight: 500;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4361ee 0%, #3a56d4 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
        }
        
        .tips-container {
            background: linear-gradient(135deg, #f0f7ff 0%, #e3f2fd 100%);
            border-left: 5px solid #4361ee;
            padding: 25px;
            border-radius: 12px;
            margin-top: 20px;
        }
        
        .tips-container h3 {
            color: #4361ee;
            margin-bottom: 20px;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .tips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .tip-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .tip-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .tip-card h4 {
            color: #344F1F;
            margin-bottom: 10px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .tip-card p {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: #666;
            margin-bottom: 10px;
            font-size: 22px;
        }
        
        .empty-state p {
            color: #888;
            margin-bottom: 25px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }
        
        .pagination-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-top: 20px;
            border: 1px solid #e9ecef;
        }
        
        @media (max-width: 1024px) {
            nav {
                width: 220px;
            }
            
            #welcomeboard {
                margin-left: 220px;
                padding: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .top {
                padding: 0 15px;
            }
            
            .logo-container {
                gap: 10px;
            }
            
            .dashboard-title {
                font-size: 18px;
            }
            
            nav {
                width: 70px;
                padding: 30px 0;
            }
            
            nav a span {
                display: none;
            }
            
            nav a {
                padding: 15px;
                justify-content: center;
                margin: 5px 10px;
            }
            
            #welcomeboard {
                margin-left: 70px;
                padding: 15px;
            }
            
            .welcome-section {
                padding: 20px;
                margin-bottom: 15px;
            }
            
            .summary-grid,
            .stats-container {
                grid-template-columns: 1fr 1fr;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .filter-section {
                width: 100%;
                flex-wrap: wrap;
            }
        }
        
        @media (max-width: 480px) {
            .summary-grid,
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .user-info .user-details {
                display: none;
            }
            
            .btn {
                padding: 12px 25px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="top">
        <div class="logo-container">
            <img src="../images/logo.png" alt="Company Logo">
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
            <a href="attendance.php" class="active" data-section="attendance"><i class="fas fa-clipboard-check"></i> <span>My Attendance</span></a>
            <a href="mark_attendance.php" data-section="mark_attendance"><i class="fas fa-qrcode"></i> <span>Mark Attendance</span></a>
            <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>  
        </nav>

        <div id="welcomeboard">
            <!-- Overall Statistics -->
            <div class="welcome-section">
                <h1>My Attendance Overview</h1>
                
                <!-- Summary Grid -->
                <div class="summary-grid">
                    <div class="summary-item rate">
                        <h4>Overall Attendance Rate</h4>
                        <div class="value"><?php echo $overallStats['rate']; ?>%</div>
                        <small>Based on <?php echo $overallStats['total']; ?> sessions</small>
                    </div>
                    <div class="summary-item present">
                        <h4>Present Sessions</h4>
                        <div class="value"><?php echo $overallStats['present']; ?></div>
                        <small><?php echo $overallStats['total'] > 0 ? round(($overallStats['present'] / $overallStats['total']) * 100) : 0; ?>% of total</small>
                    </div>
                    <div class="summary-item absent">
                        <h4>Absent Sessions</h4>
                        <div class="value"><?php echo $overallStats['absent']; ?></div>
                        <small><?php echo $overallStats['total'] > 0 ? round(($overallStats['absent'] / $overallStats['total']) * 100) : 0; ?>% of total</small>
                    </div>
                    <div class="summary-item late">
                        <h4>Late Arrivals</h4>
                        <div class="value"><?php echo $overallStats['late']; ?></div>
                        <small><?php echo $overallStats['total'] > 0 ? round(($overallStats['late'] / $overallStats['total']) * 100) : 0; ?>% of total</small>
                    </div>
                </div>
                
                
                <!-- Mark Attendance Button -->
                <div class="btn-container">
                    <a href="mark_attendance.php" class="btn">
                        <i class="fas fa-qrcode"></i> Mark Attendance Now
                    </a>
                    <p style="color: #666; margin-top: 15px; font-size: 14px;">
                        Use attendance codes provided by your instructor to mark your attendance
                    </p>
                </div>
            </div>

            <!-- Course-wise Statistics -->
            <div class="welcome-section">
                <div class="section-header">
                    <h2><i class="fas fa-chart-bar"></i> Attendance by Course</h2>
                    <div class="filter-section">
                        <button class="export-btn">
                            <i class="fas fa-download"></i> Export Report
                        </button>
                    </div>
                </div>
                
                <?php if (empty($courseStats)): ?>
                <div class="empty-state">
                    <i class="fas fa-chart-bar"></i>
                    <h3>No Attendance Records Yet</h3>
                    <p>Your attendance records will appear here once you start attending sessions.</p>
                    <a href="dashboard.php" class="btn">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Total Sessions</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Late</th>
                                <th>Attendance Rate</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courseStats as $stat): ?>
                            <?php 
                            $courseRate = $stat['total_sessions'] > 0 
                                ? round(($stat['present_count'] / $stat['total_sessions']) * 100) 
                                : 0;
                            $progressClass = $courseRate >= 80 ? 'progress-good' : ($courseRate >= 60 ? 'progress-warning' : 'progress-danger');
                            ?>
                            <tr>
                                <td>
                                    <a href="course_details.php?id=<?php echo $stat['course_id']; ?>" class="course-link">
                                        <strong><?php echo htmlspecialchars($stat['course_code']); ?></strong>
                                    </a><br>
                                    <small><?php echo htmlspecialchars($stat['course_name']); ?></small>
                                </td>
                                <td><strong><?php echo $stat['total_sessions']; ?></strong></td>
                                <td>
                                    <span class="attendance-status status-present">
                                        <i class="fas fa-check-circle"></i> <?php echo $stat['present_count']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="attendance-status status-absent">
                                        <i class="fas fa-times-circle"></i> <?php echo $stat['absent_count']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="attendance-status status-late">
                                        <i class="fas fa-clock"></i> <?php echo $stat['late_count']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="progress-container">
                                        <span style="font-weight: 600;"><?php echo $courseRate; ?>%</span>
                                        <div class="progress-bar">
                                            <div class="progress-fill <?php echo $progressClass; ?>" style="width: <?php echo $courseRate; ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <a href="course_details.php?id=<?php echo $stat['course_id']; ?>" class="btn-small btn-primary">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Detailed Attendance Records -->
            <div class="welcome-section">
                <div class="section-header">
                    <h2><i class="fas fa-list"></i> Detailed Attendance Records</h2>
                    <div class="filter-section">
                        <select class="filter-select">
                            <option value="">All Courses</option>
                            <?php foreach ($courseStats as $stat): ?>
                            <option value="<?php echo $stat['course_id']; ?>">
                                <?php echo htmlspecialchars($stat['course_code']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <select class="filter-select">
                            <option value="">All Status</option>
                            <option value="present">Present</option>
                            <option value="absent">Absent</option>
                            <option value="late">Late</option>
                        </select>
                    </div>
                </div>
                
                <?php if (empty($attendanceRecords)): ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>No Detailed Records Found</h3>
                    <p>Detailed attendance records will appear here once they are recorded.</p>
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Course</th>
                                <th>Topic</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Time Marked</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendanceRecords as $record): ?>
                            <?php 
                            $statusClass = 'status-' . $record['status'];
                            $statusText = ucfirst($record['status']);
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo date('M j, Y', strtotime($record['session_date'])); ?></strong><br>
                                    <small style="color: #666;"><?php echo date('D', strtotime($record['session_date'])); ?></small>
                                </td>
                                <td>
                                    <a href="course_details.php?id=<?php echo $record['course_id']; ?>" class="course-link">
                                        <?php echo htmlspecialchars($record['course_code']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($record['topic'] ?: 'General Session'); ?></td>
                                <td><?php echo htmlspecialchars($record['location'] ?: 'Not Specified'); ?></td>
                                <td>
                                    <span class="attendance-status <?php echo $statusClass; ?>">
                                        <i class="fas fa-<?php echo $record['status'] === 'present' ? 'check-circle' : ($record['status'] === 'absent' ? 'times-circle' : 'clock'); ?>"></i>
                                        <?php echo $statusText; ?>
                                    </span>
                                </td>
                                <td><strong><?php echo date('h:i A', strtotime($record['attendance_time'])); ?></strong></td>
                                <td>
                                    <?php if ($record['notes']): ?>
                                    <span title="<?php echo htmlspecialchars($record['notes']); ?>" style="cursor: help; color: #4361ee;">
                                        <i class="fas fa-sticky-note"></i> Has notes
                                    </span>
                                    <?php else: ?>
                                    <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination summary -->
                <div class="pagination-summary">
                    <p style="margin: 0; color: #666;">
                        Showing <strong><?php echo count($attendanceRecords); ?></strong> attendance records
                        <?php if (count($attendanceRecords) > 10): ?>
                        • <a href="#" style="color: #4361ee; text-decoration: none; font-weight: 600;">Load more records</a>
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Attendance Tips -->
            <div class="tips-container">
                <h3><i class="fas fa-lightbulb"></i> Attendance Tips</h3>
                <div class="tips-grid">
                    <div class="tip-card">
                        <h4>
                            <i class="fas fa-bullseye" style="color: #F4991A;"></i> 
                            Target 80%+ Attendance
                        </h4>
                        <p>Aim for at least 80% attendance rate for optimal learning and academic performance.</p>
                    </div>
                    <div class="tip-card">
                        <h4>
                            <i class="fas fa-clock" style="color: #ffc107;"></i> 
                            Avoid Being Late
                        </h4>
                        <p>Being consistently late affects your attendance record and learning experience.</p>
                    </div>
                    <div class="tip-card">
                        <h4>
                            <i class="fas fa-question-circle" style="color: #28a745;"></i> 
                            Need Help?
                        </h4>
                        <p>Contact your Faculty Intern if you have questions about your attendance records.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const courseFilter = document.querySelectorAll('.filter-select')[0];
            const statusFilter = document.querySelectorAll('.filter-select')[1];
            
            if (courseFilter && statusFilter) {
                function filterTable() {
                    const selectedCourse = courseFilter.value;
                    const selectedStatus = statusFilter.value;
                    const rows = document.querySelectorAll('tbody tr');
                    
                    rows.forEach(row => {
                        let showRow = true;
                        
                        // Filter by course
                        if (selectedCourse) {
                            const courseCell = row.querySelector('td:nth-child(2) a');
                            if (!courseCell || !courseCell.href.includes('id=' + selectedCourse)) {
                                showRow = false;
                            }
                        }
                        
                        // Filter by status
                        if (selectedStatus) {
                            const statusSpan = row.querySelector('.attendance-status');
                            if (!statusSpan || !statusSpan.classList.contains('status-' + selectedStatus)) {
                                showRow = false;
                            }
                        }
                        
                        row.style.display = showRow ? '' : 'none';
                    });
                }
                
                courseFilter.addEventListener('change', filterTable);
                statusFilter.addEventListener('change', filterTable);
            }
            
            // Export button functionality
            const exportBtn = document.querySelector('.export-btn');
            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    alert('Export functionality will be implemented in the next update!');
                });
            }
            
            // Add smooth scroll animation
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
            
            // Add hover effect to table rows
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8f9fa';
                    this.style.transition = 'background-color 0.2s';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });
        });
    </script>
</body>
</html>
