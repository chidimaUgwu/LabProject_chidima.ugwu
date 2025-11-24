<?php
require_once 'php/auth_check.php';
checkRole('student');

require_once 'php/db_connection.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get filter parameters
$course_filter = $_GET['course'] ?? '';
$date_filter = $_GET['date'] ?? '';

// Build query for sessions
$sql = "
    SELECT 
        s.session_id,
        s.course_id,
        s.topic,
        s.location,
        s.start_time,
        s.end_time,
        s.date,
        c.course_code,
        c.course_name,
        u.first_name,
        u.last_name,
        a.status as attendance_status,
        a.check_in_time
    FROM sessions s
    JOIN courses c ON s.course_id = c.course_id
    JOIN faculty f ON c.faculty_id = f.faculty_id
    JOIN users u ON f.faculty_id = u.user_id
    JOIN course_student_list csl ON c.course_id = csl.course_id
    LEFT JOIN attendance a ON s.session_id = a.session_id AND a.student_id = ?
    WHERE csl.student_id = ?
";

$params = [$user_id, $user_id];
$types = "ii";

// Add filters
if (!empty($course_filter)) {
    $sql .= " AND s.course_id = ?";
    $params[] = $course_filter;
    $types .= "i";
}

if (!empty($date_filter)) {
    $sql .= " AND s.date = ?";
    $params[] = $date_filter;
    $types .= "s";
}

$sql .= " ORDER BY s.date DESC, s.start_time ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$sessions = [];
$upcoming_sessions = [];
$past_sessions = [];

while ($row = $result->fetch_assoc()) {
    $session_date = new DateTime($row['date']);
    $today = new DateTime();
    
    $row['is_upcoming'] = $session_date >= $today;
    $row['formatted_date'] = $session_date->format('F j, Y');
    $row['day_name'] = $session_date->format('l');
    $row['time_range'] = date('g:i A', strtotime($row['start_time'])) . ' - ' . date('g:i A', strtotime($row['end_time']));
    
    if ($row['is_upcoming']) {
        $upcoming_sessions[] = $row;
    } else {
        $past_sessions[] = $row;
    }
    
    $sessions[] = $row;
}
$stmt->close();

// Get courses for filter dropdown
$courses_sql = "
    SELECT c.course_id, c.course_code, c.course_name 
    FROM courses c
    JOIN course_student_list csl ON c.course_id = csl.course_id
    WHERE csl.student_id = ?
    ORDER BY c.course_code
";
$courses_stmt = $conn->prepare($courses_sql);
$courses_stmt->bind_param("i", $user_id);
$courses_stmt->execute();
$courses_result = $courses_stmt->get_result();

$courses = [];
while ($course = $courses_result->fetch_assoc()) {
    $courses[] = $course;
}
$courses_stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Schedule - Attendance Management</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/Student_Dashboard.css">
    <style>
        .schedule-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .filter-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        .form-group select,
        .form-group input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .btn-filter {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-filter:hover {
            background: #45a049;
        }

        .btn-reset {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-reset:hover {
            background: #545b62;
        }

        .sessions-tabs {
            margin-bottom: 20px;
        }

        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .tab-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            background: #f8f9fa;
            color: #333;
            transition: all 0.3s;
        }

        .tab-btn.active {
            background: #4CAF50;
            color: white;
        }

        .tab-btn:hover:not(.active) {
            background: #e9ecef;
        }

        .sessions-grid {
            display: grid;
            gap: 20px;
        }

        .session-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
            border-left: 4px solid #4CAF50;
            transition: transform 0.3s ease;
        }

        .session-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }

        .session-card.upcoming {
            border-left-color: #2196F3;
        }

        .session-card.past {
            border-left-color: #6c757d;
        }

        .session-header {
            display: flex;
            justify-content: between;
            align-items: start;
            margin-bottom: 15px;
        }

        .session-title {
            flex: 1;
        }

        .session-topic {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .session-course {
            color: #4CAF50;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .session-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-upcoming {
            background: #e3f2fd;
            color: #1976d2;
        }

        .status-present {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .status-absent {
            background: #ffebee;
            color: #c62828;
        }

        .status-late {
            background: #fff3e0;
            color: #ef6c00;
        }

        .status-not-recorded {
            background: #f5f5f5;
            color: #666;
        }

        .session-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.8rem;
            color: #888;
            margin-bottom: 3px;
            font-weight: 600;
        }

        .detail-value {
            font-size: 1rem;
            color: #333;
            font-weight: 600;
        }

        .session-instructor {
            color: #2196F3;
        }

        .session-date {
            color: #FF9800;
        }

        .session-time {
            color: #4CAF50;
        }

        .session-location {
            color: #9C27B0;
        }

        .no-sessions {
            text-align: center;
            padding: 60px 20px;
            color: #666;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .no-sessions i {
            font-size: 4rem;
            margin-bottom: 20px;
            display: block;
            opacity: 0.5;
        }

        .calendar-view {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 30px;
        }

        .calendar-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 20px;
        }

        .calendar-nav {
            display: flex;
            gap: 10px;
        }

        .calendar-nav-btn {
            background: #f8f9fa;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #e0e0e0;
            border: 1px solid #e0e0e0;
        }

        .calendar-day {
            background: white;
            padding: 10px;
            min-height: 80px;
            font-size: 0.9rem;
        }

        .calendar-day.header {
            background: #f8f9fa;
            font-weight: bold;
            text-align: center;
            padding: 15px 10px;
        }

        .day-number {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .session-indicator {
            background: #4CAF50;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.7rem;
            margin-bottom: 2px;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .session-details {
                grid-template-columns: 1fr;
            }
            
            .calendar-grid {
                grid-template-columns: repeat(7, 1fr);
                font-size: 0.8rem;
            }
            
            .calendar-day {
                min-height: 60px;
                padding: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="top">
        <div class="logo-container">
            <img src="images/logo.png" alt="Company Logo">
            <h4 class="dashboard-title">SESSION SCHEDULE</h4>
        </div>
    </div>
    
    <div class="board">
        <nav>
            <a href="Student_Dashboard.php"><i>📊</i> Dashboard</a>
            <a href="Student_Courses.php"><i>📚</i> My Courses</a>
            <a href="Student_sessionSchedule.php" class="active"><i>📅</i> Session Schedule</a>
            <a href="GradesReports.php"><i>🏆</i> Grade/Report</a>
            <a href="Student_Profile.php"><i>👤</i> Profile</a>
            <a href="logout.php"><i>🚪</i> Logout</a>
        </nav>

        <div id="welcomeboard">
            <div class="schedule-container">
                <div class="page-header">
                    <h1>Class Session Schedule</h1>
                    <p>View and manage your class schedules</p>
                </div>

                <!-- Filters Section -->
                <div class="filters-section">
                    <form method="GET" class="filter-form">
                        <div class="form-group">
                            <label for="course">Filter by Course</label>
                            <select id="course" name="course">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['course_id']; ?>" 
                                    <?php echo ($course_filter == $course['course_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date">Filter by Date</label>
                            <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn-filter">Apply Filters</button>
                        </div>
                    </form>
                    <?php if (!empty($course_filter) || !empty($date_filter)): ?>
                    <div style="margin-top: 15px; text-align: center;">
                        <a href="Student_sessionSchedule.php" class="btn-reset">Reset Filters</a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Tabs -->
                <div class="sessions-tabs">
                    <div class="tab-buttons">
                        <button class="tab-btn active" onclick="showTab('upcoming')">
                            📅 Upcoming Sessions (<?php echo count($upcoming_sessions); ?>)
                        </button>
                        <button class="tab-btn" onclick="showTab('past')">
                            📋 Past Sessions (<?php echo count($past_sessions); ?>)
                        </button>
                        <button class="tab-btn" onclick="showTab('all')">
                            📊 All Sessions (<?php echo count($sessions); ?>)
                        </button>
                    </div>

                    <!-- Upcoming Sessions -->
                    <div id="upcoming-tab" class="tab-content">
                        <?php if (count($upcoming_sessions) > 0): ?>
                        <div class="sessions-grid">
                            <?php foreach ($upcoming_sessions as $session): ?>
                            <div class="session-card upcoming">
                                <div class="session-header">
                                    <div class="session-title">
                                        <div class="session-topic"><?php echo htmlspecialchars($session['topic']); ?></div>
                                        <div class="session-course"><?php echo htmlspecialchars($session['course_code'] . ' - ' . $session['course_name']); ?></div>
                                    </div>
                                    <div class="session-status status-upcoming">Upcoming</div>
                                </div>
                                <div class="session-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Date</span>
                                        <span class="detail-value session-date">
                                            <?php echo $session['formatted_date']; ?> (<?php echo $session['day_name']; ?>)
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Time</span>
                                        <span class="detail-value session-time"><?php echo $session['time_range']; ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Location</span>
                                        <span class="detail-value session-location"><?php echo htmlspecialchars($session['location']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Instructor</span>
                                        <span class="detail-value session-instructor">
                                            <?php echo htmlspecialchars($session['first_name'] . ' ' . $session['last_name']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="no-sessions">
                            <i>📅</i>
                            <h3>No Upcoming Sessions</h3>
                            <p>There are no upcoming class sessions scheduled.</p>
                            <p>Check back later or contact your instructor for updates.</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Past Sessions -->
                    <div id="past-tab" class="tab-content" style="display: none;">
                        <?php if (count($past_sessions) > 0): ?>
                        <div class="sessions-grid">
                            <?php foreach ($past_sessions as $session): 
                                $status_class = 'status-not-recorded';
                                $status_text = 'Not Recorded';
                                
                                if ($session['attendance_status']) {
                                    $status_class = 'status-' . $session['attendance_status'];
                                    $status_text = ucfirst($session['attendance_status']);
                                }
                            ?>
                            <div class="session-card past">
                                <div class="session-header">
                                    <div class="session-title">
                                        <div class="session-topic"><?php echo htmlspecialchars($session['topic']); ?></div>
                                        <div class="session-course"><?php echo htmlspecialchars($session['course_code'] . ' - ' . $session['course_name']); ?></div>
                                    </div>
                                    <div class="session-status <?php echo $status_class; ?>"><?php echo $status_text; ?></div>
                                </div>
                                <div class="session-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Date</span>
                                        <span class="detail-value session-date">
                                            <?php echo $session['formatted_date']; ?> (<?php echo $session['day_name']; ?>)
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Time</span>
                                        <span class="detail-value session-time"><?php echo $session['time_range']; ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Location</span>
                                        <span class="detail-value session-location"><?php echo htmlspecialchars($session['location']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Instructor</span>
                                        <span class="detail-value session-instructor">
                                            <?php echo htmlspecialchars($session['first_name'] . ' ' . $session['last_name']); ?>
                                        </span>
                                    </div>
                                    <?php if ($session['attendance_status'] && $session['check_in_time']): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Check-in Time</span>
                                        <span class="detail-value"><?php echo date('g:i A', strtotime($session['check_in_time'])); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="no-sessions">
                            <i>📋</i>
                            <h3>No Past Sessions</h3>
                            <p>There are no past class sessions recorded.</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- All Sessions -->
                    <div id="all-tab" class="tab-content" style="display: none;">
                        <?php if (count($sessions) > 0): ?>
                        <div class="sessions-grid">
                            <?php foreach ($sessions as $session): 
                                $status_class = $session['is_upcoming'] ? 'status-upcoming' : 'status-not-recorded';
                                $status_text = $session['is_upcoming'] ? 'Upcoming' : 'Not Recorded';
                                
                                if (!$session['is_upcoming'] && $session['attendance_status']) {
                                    $status_class = 'status-' . $session['attendance_status'];
                                    $status_text = ucfirst($session['attendance_status']);
                                }
                            ?>
                            <div class="session-card <?php echo $session['is_upcoming'] ? 'upcoming' : 'past'; ?>">
                                <div class="session-header">
                                    <div class="session-title">
                                        <div class="session-topic"><?php echo htmlspecialchars($session['topic']); ?></div>
                                        <div class="session-course"><?php echo htmlspecialchars($session['course_code'] . ' - ' . $session['course_name']); ?></div>
                                    </div>
                                    <div class="session-status <?php echo $status_class; ?>"><?php echo $status_text; ?></div>
                                </div>
                                <div class="session-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Date</span>
                                        <span class="detail-value session-date">
                                            <?php echo $session['formatted_date']; ?> (<?php echo $session['day_name']; ?>)
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Time</span>
                                        <span class="detail-value session-time"><?php echo $session['time_range']; ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Location</span>
                                        <span class="detail-value session-location"><?php echo htmlspecialchars($session['location']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Instructor</span>
                                        <span class="detail-value session-instructor">
                                            <?php echo htmlspecialchars($session['first_name'] . ' ' . $session['last_name']); ?>
                                        </span>
                                    </div>
                                    <?php if (!$session['is_upcoming'] && $session['attendance_status'] && $session['check_in_time']): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Check-in Time</span>
                                        <span class="detail-value"><?php echo date('g:i A', strtotime($session['check_in_time'])); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="no-sessions">
                            <i>📊</i>
                            <h3>No Sessions Found</h3>
                            <p>No class sessions found matching your criteria.</p>
                            <p>Try adjusting your filters or check back later.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.style.display = 'none';
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab and activate button
            document.getElementById(tabName + '-tab').style.display = 'block';
            event.currentTarget.classList.add('active');
        }

        // Add some interactive features
        document.addEventListener('DOMContentLoaded', function() {
            // Add click animation to session cards
            const sessionCards = document.querySelectorAll('.session-card');
            sessionCards.forEach(card => {
                card.addEventListener('click', function() {
                    this.style.transform = 'scale(0.98)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });

            // Auto-refresh page every 5 minutes for upcoming sessions
            setInterval(() => {
                const activeTab = document.querySelector('.tab-btn.active');
                if (activeTab && activeTab.textContent.includes('Upcoming')) {
                    location.reload();
                }
            }, 300000); // 5 minutes
        });

        // Filter form auto-submit on change
        document.getElementById('course').addEventListener('change', function() {
            this.form.submit();
        });

        document.getElementById('date').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>