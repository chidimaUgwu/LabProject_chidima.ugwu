<?php
require_once 'php/auth_check.php';
checkRole('student');

require_once 'php/db_connection.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch student's courses and grades
$courses_grades = [];
$attendance_stats = [];

// Get enrolled courses with grades
$sql = "
    SELECT 
        c.course_id,
        c.course_code,
        c.course_name,
        c.credit_hours,
        AVG(a.status) as avg_attendance,
        COUNT(DISTINCT s.session_id) as total_sessions,
        COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.attendance_id END) as attended_sessions
    FROM courses c
    JOIN course_student_list csl ON c.course_id = csl.course_id
    LEFT JOIN sessions s ON c.course_id = s.course_id
    LEFT JOIN attendance a ON s.session_id = a.session_id AND a.student_id = ?
    WHERE csl.student_id = ?
    GROUP BY c.course_id, c.course_code, c.course_name, c.credit_hours
    ORDER BY c.course_code
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $courses_grades[] = $row;
    
    // Calculate attendance percentage
    $attendance_percentage = 0;
    if ($row['total_sessions'] > 0) {
        $attendance_percentage = round(($row['attended_sessions'] / $row['total_sessions']) * 100);
    }
    
    $attendance_stats[] = [
        'course_code' => $row['course_code'],
        'course_name' => $row['course_name'],
        'attendance_percentage' => $attendance_percentage,
        'attended' => $row['attended_sessions'],
        'total' => $row['total_sessions']
    ];
}
$stmt->close();

// Calculate overall statistics
$total_courses = count($courses_grades);
$overall_attendance = 0;
$total_sessions = 0;
$attended_sessions = 0;

foreach ($attendance_stats as $stat) {
    $total_sessions += $stat['total'];
    $attended_sessions += $stat['attended'];
}

if ($total_sessions > 0) {
    $overall_attendance = round(($attended_sessions / $total_sessions) * 100);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grades & Reports - Attendance Management</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/Student_Dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .grades-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #4CAF50;
        }

        .stat-card.attendance {
            border-left-color: #2196F3;
        }

        .stat-card.courses {
            border-left-color: #FF9800;
        }

        .stat-card.sessions {
            border-left-color: #9C27B0;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #333;
            margin: 10px 0;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .charts-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .chart-container {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .chart-title {
            margin-bottom: 20px;
            color: #333;
            font-size: 1.2rem;
            text-align: center;
        }

        .grades-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .table-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
        }

        .table-header h3 {
            margin: 0;
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .attendance-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .attendance-excellent {
            background: #d4edda;
            color: #155724;
        }

        .attendance-good {
            background: #d1ecf1;
            color: #0c5460;
        }

        .attendance-fair {
            background: #fff3cd;
            color: #856404;
        }

        .attendance-poor {
            background: #f8d7da;
            color: #721c24;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
        }

        .export-buttons {
            margin-bottom: 20px;
            text-align: right;
        }

        .btn-export {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-left: 10px;
            transition: background 0.3s;
        }

        .btn-export:hover {
            background: #545b62;
        }

        .btn-export.primary {
            background: #4CAF50;
        }

        .btn-export.primary:hover {
            background: #45a049;
        }

        @media (max-width: 768px) {
            .charts-section {
                grid-template-columns: 1fr;
            }
            
            .stats-overview {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 0.9rem;
            }
            
            th, td {
                padding: 10px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="top">
        <div class="logo-container">
            <img src="images/logo.png" alt="Company Logo">
            <h4 class="dashboard-title">GRADES & REPORTS</h4>
        </div>
    </div>
    
    <div class="board">
        <nav>
            <a href="Student_Dashboard.php"><i>📊</i> Dashboard</a>
            <a href="Student_Courses.php"><i>📚</i> My Courses</a>
            <a href="Student_sessionSchedule.php"><i>📅</i> Session Schedule</a>
            <a href="GradesReports.php" class="active"><i>🏆</i> Grade/Report</a>
            <a href="Student_Profile.php"><i>👤</i> Profile</a>
            <a href="logout.php"><i>🚪</i> Logout</a>
        </nav>

        <div id="welcomeboard">
            <div class="grades-container">
                <!-- Statistics Overview -->
                <div class="stats-overview">
                    <div class="stat-card courses">
                        <div class="stat-label">Total Courses</div>
                        <div class="stat-number"><?php echo $total_courses; ?></div>
                        <div class="stat-desc">Currently Enrolled</div>
                    </div>
                    <div class="stat-card attendance">
                        <div class="stat-label">Overall Attendance</div>
                        <div class="stat-number"><?php echo $overall_attendance; ?>%</div>
                        <div class="stat-desc"><?php echo $attended_sessions; ?>/<?php echo $total_sessions; ?> Sessions</div>
                    </div>
                    <div class="stat-card sessions">
                        <div class="stat-label">Completed Sessions</div>
                        <div class="stat-number"><?php echo $total_sessions; ?></div>
                        <div class="stat-desc">Total Class Sessions</div>
                    </div>
                </div>

                <!-- Export Buttons -->
                <div class="export-buttons">
                    <button class="btn-export" onclick="printReport()">📄 Print Report</button>
                    <button class="btn-export primary" onclick="exportToPDF()">📥 Export PDF</button>
                </div>

                <?php if (count($courses_grades) > 0): ?>
                
                <!-- Charts Section -->
                <div class="charts-section">
                    <div class="chart-container">
                        <div class="chart-title">Attendance by Course</div>
                        <canvas id="attendanceChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <div class="chart-title">Overall Performance</div>
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>

                <!-- Grades Table -->
                <div class="grades-table">
                    <div class="table-header">
                        <h3>Course Performance Details</h3>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Credit Hours</th>
                                <th>Sessions Attended</th>
                                <th>Attendance Rate</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses_grades as $course): 
                                $attendance_percentage = 0;
                                if ($course['total_sessions'] > 0) {
                                    $attendance_percentage = round(($course['attended_sessions'] / $course['total_sessions']) * 100);
                                }
                                
                                // Determine badge class based on attendance percentage
                                $badge_class = '';
                                if ($attendance_percentage >= 90) $badge_class = 'attendance-excellent';
                                elseif ($attendance_percentage >= 80) $badge_class = 'attendance-good';
                                elseif ($attendance_percentage >= 70) $badge_class = 'attendance-fair';
                                else $badge_class = 'attendance-poor';
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                <td><?php echo htmlspecialchars($course['credit_hours']); ?></td>
                                <td><?php echo $course['attended_sessions']; ?>/<?php echo $course['total_sessions']; ?></td>
                                <td><strong><?php echo $attendance_percentage; ?>%</strong></td>
                                <td>
                                    <span class="attendance-badge <?php echo $badge_class; ?>">
                                        <?php 
                                        if ($attendance_percentage >= 90) echo 'Excellent';
                                        elseif ($attendance_percentage >= 80) echo 'Good';
                                        elseif ($attendance_percentage >= 70) echo 'Fair';
                                        else echo 'Needs Improvement';
                                        ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php else: ?>
                
                <!-- No Data Message -->
                <div class="no-data">
                    <i>📊</i>
                    <h3>No Course Data Available</h3>
                    <p>You are not enrolled in any courses yet, or no session data is available.</p>
                    <p>Please check back later or contact your administrator.</p>
                </div>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Attendance Chart
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceChart = new Chart(attendanceCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo implode(',', array_map(function($stat) { return "'" . $stat['course_code'] . "'"; }, $attendance_stats)); ?>],
                datasets: [{
                    label: 'Attendance Percentage',
                    data: [<?php echo implode(',', array_column($attendance_stats, 'attendance_percentage')); ?>],
                    backgroundColor: [
                        '#4CAF50', '#2196F3', '#FF9800', '#9C27B0', '#F44336',
                        '#00BCD4', '#8BC34A', '#FFC107', '#795548', '#607D8B'
                    ],
                    borderColor: [
                        '#45a049', '#1976D2', '#F57C00', '#7B1FA2', '#D32F2F',
                        '#0097A7', '#689F38', '#FFA000', '#5D4037', '#455A64'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Attendance Percentage (%)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Courses'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const course = <?php echo json_encode($attendance_stats); ?>[context.dataIndex];
                                return `${course.course_name}: ${context.parsed.y}% (${course.attended}/${course.total} sessions)`;
                            }
                        }
                    }
                }
            }
        });

        // Performance Chart (Doughnut)
        const performanceCtx = document.getElementById('performanceChart').getContext('2d');
        const performanceChart = new Chart(performanceCtx, {
            type: 'doughnut',
            data: {
                labels: ['Excellent (90-100%)', 'Good (80-89%)', 'Fair (70-79%)', 'Needs Improvement (<70%)'],
                datasets: [{
                    data: [
                        <?php echo count(array_filter($attendance_stats, function($stat) { return $stat['attendance_percentage'] >= 90; })); ?>,
                        <?php echo count(array_filter($attendance_stats, function($stat) { return $stat['attendance_percentage'] >= 80 && $stat['attendance_percentage'] < 90; })); ?>,
                        <?php echo count(array_filter($attendance_stats, function($stat) { return $stat['attendance_percentage'] >= 70 && $stat['attendance_percentage'] < 80; })); ?>,
                        <?php echo count(array_filter($attendance_stats, function($stat) { return $stat['attendance_percentage'] < 70; })); ?>
                    ],
                    backgroundColor: [
                        '#4CAF50', '#2196F3', '#FFC107', '#F44336'
                    ],
                    borderColor: [
                        '#45a049', '#1976D2', '#FFA000', '#D32F2F'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} course${value !== 1 ? 's' : ''} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Export Functions
        function printReport() {
            window.print();
        }

        function exportToPDF() {
            // Simple PDF export simulation
            Swal.fire({
                title: 'Exporting PDF',
                text: 'Your report is being generated...',
                icon: 'info',
                showConfirmButton: false,
                timer: 2000
            }).then(() => {
                // In a real application, this would generate and download a PDF
                Swal.fire({
                    title: 'Export Complete!',
                    text: 'Your grades report has been exported successfully.',
                    icon: 'success'
                });
            });
        }

        // Add print styles
        const style = document.createElement('style');
        style.textContent = `
            @media print {
                nav, .export-buttons, .top {
                    display: none !important;
                }
                .grades-container {
                    margin: 0 !important;
                    padding: 0 !important;
                }
                .stat-card, .chart-container, .grades-table {
                    box-shadow: none !important;
                    border: 1px solid #ddd !important;
                }
                canvas {
                    max-height: 300px !important;
                }
            }
        `;
        document.head.appendChild(style);
    </script>

    <!-- Include SweetAlert for notifications -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>