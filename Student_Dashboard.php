<?php require_once 'php/auth/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="Student_Dashboard.css">
</head>
<body>
    <!-- Rest of your HTML content -->
    <div class="top">
        <div class="logo-container">
            <img src="/images/logo.png" alt="Company Logo" srcset="">
            <h4 class="dashboard-title">STUDENT DASHBOARD</h4>
        </div>
            <div class="user-info">
                <div class="user-avatar"><img src="https://ui-avatars.com/api/?name=Faculty+User&background=4361ee&color=fff" alt="User"></div>
                <div >
                <div class="user-name">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></div>
                <div class="user-role">Student</div>
            </div>
                
            </div>
        </div>
    </div>
    <div class="board">
        <nav>
            <a href="Student_Dashboard.html" class="active"><i>📊</i> Dashboard</a>
            <a href="MyCourses.html"><i>📚</i> My Courses</a>
            <a href="GradesReports.html"><i>📅</i> session schedule</a>
            <a href="GradesReports.html"><i>🏆</i> Grade/Report</a>
            <a href="#"><i>👤</i> Profile</a>
            <a href="#"><i>🚪</i> Logout</a>
        </nav>

        <div id="welcomeboard">
            <div class="welcome-section">
                <h1>Welcome back Chidima Praise Jude! </h1>
                <div class="stats-container">
                    <div class="stat-card">
                        <div id="tete">
                           <h3>Total Courses Enrolled</h3>
                        </div>
                        <div class="number">6</div>
                    </div>
                    <div class="stat-card">
                        <div id="tete" style="background-color: #344F1F ; border:#344F1F;">
                            <h3>Upcoming Sessions</h3>
                        </div>
                        <div class="number">3</div>
                    </div>
                    <div class="stat-card">
                        <div id="tete"><h3>Average Grade</h3></div>
                        <div class="number">85%</div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    
</body>
</html>