<?php
// C:\xampp\htdocs\Attandance\faculty\courses.php
require_once '../config/session.php';
require_once '../config/database.php';
requireAuth();

// Check if user has faculty/instructor role
if (!isInstructor()) {
    header("Location: ../auth/logout.php");
    exit();
}

$message = '';
$error = '';

try {
    $database = new Database();
    $db = $database->getConnection();
    $user_id = $_SESSION['user_id'];
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            
            switch ($action) {
                case 'create':
                    // Create new course
                    $course_code = trim($_POST['course_code']);
                    $course_name = trim($_POST['course_name']);
                    $course_description = trim($_POST['course_description']);
                    $credits = intval($_POST['credits']);
                    $max_students = intval($_POST['max_students']);
                    
                    if (empty($course_code) || empty($course_name)) {
                        $error = 'Course code and name are required';
                    } else {
                        $checkQuery = "SELECT id FROM am_courses WHERE course_code = ?";
                        $checkStmt = $db->prepare($checkQuery);
                        $checkStmt->execute([$course_code]);
                        
                        if ($checkStmt->rowCount() > 0) {
                            $error = 'Course code already exists';
                        } else {
                            $insertQuery = "INSERT INTO am_courses 
                                          (course_code, course_name, course_description, credits, max_students, faculty_intern_id, status) 
                                          VALUES (?, ?, ?, ?, ?, ?, 'active')";
                            $insertStmt = $db->prepare($insertQuery);
                            $result = $insertStmt->execute([
                                $course_code,
                                $course_name,
                                $course_description,
                                $credits,
                                $max_students,
                                $user_id
                            ]);
                            
                            if ($result) {
                                $message = 'Course created successfully!';
                            } else {
                                $error = 'Failed to create course';
                            }
                        }
                    }
                    break;
                    
                case 'update':
                    // Update existing course
                    $course_id = intval($_POST['course_id']);
                    $course_code = trim($_POST['course_code']);
                    $course_name = trim($_POST['course_name']);
                    $course_description = trim($_POST['course_description']);
                    $credits = intval($_POST['credits']);
                    $max_students = intval($_POST['max_students']);
                    $status = $_POST['status'];
                    
                    $updateQuery = "UPDATE am_courses 
                                   SET course_code = ?, course_name = ?, course_description = ?, 
                                       credits = ?, max_students = ?, status = ?, updated_at = NOW()
                                   WHERE id = ? AND faculty_intern_id = ?";
                    $updateStmt = $db->prepare($updateQuery);
                    $result = $updateStmt->execute([
                        $course_code,
                        $course_name,
                        $course_description,
                        $credits,
                        $max_students,
                        $status,
                        $course_id,
                        $user_id
                    ]);
                    
                    if ($result) {
                        $message = 'Course updated successfully!';
                    } else {
                        $error = 'Failed to update course';
                    }
                    break;
                    
                case 'delete':
                    // Delete course (soft delete by changing status)
                    $course_id = intval($_POST['course_id']);
                    
                    $deleteQuery = "UPDATE am_courses SET status = 'inactive' WHERE id = ? AND faculty_intern_id = ?";
                    $deleteStmt = $db->prepare($deleteQuery);
                    $result = $deleteStmt->execute([$course_id, $user_id]);
                    
                    if ($result) {
                        $message = 'Course deactivated successfully!';
                    } else {
                        $error = 'Failed to deactivate course';
                    }
                    break;
            }
        }
    }
    
    // Get all courses for this faculty
    $coursesQuery = "SELECT 
                        c.*,
                        COUNT(DISTINCT e.id) as enrolled_count,
                        COUNT(DISTINCT s.id) as session_count
                     FROM am_courses c
                     LEFT JOIN am_enrollments e ON c.id = e.course_id AND e.status = 'approved'
                     LEFT JOIN am_sessions s ON c.id = s.course_id
                     WHERE c.faculty_intern_id = ?
                     GROUP BY c.id
                     ORDER BY c.created_at DESC";
    $coursesStmt = $db->prepare($coursesQuery);
    $coursesStmt->execute([$user_id]);
    $courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $statsQuery = "SELECT 
                      COUNT(*) as total_courses,
                      SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_courses,
                      SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_courses,
                      (SELECT COUNT(DISTINCT student_id) FROM am_enrollments e 
                       JOIN am_courses c ON e.course_id = c.id 
                       WHERE c.faculty_intern_id = ? AND e.status = 'approved') as total_students
                   FROM am_courses 
                   WHERE faculty_intern_id = ?";
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->execute([$user_id, $user_id]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Courses management error: " . $e->getMessage());
    $courses = [];
    $stats = ['total_courses' => 0, 'active_courses' => 0, 'inactive_courses' => 0, 'total_students' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Management - Faculty Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/style.css">
    <style>
        /* Same base styles as dashboard */
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
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border-left: 4px solid #3B0270;
        }
        
        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #F4991A;
            margin-bottom: 5px;
        }
        
        .stat-card .label {
            color: #666;
            font-size: 14px;
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
        
        .btn-danger {
            background-color: #dc3545;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-primary {
            background-color: #007bff;
        }
        
        .btn-primary:hover {
            background-color: #0069d9;
        }
        
        .btn-warning {
            background-color: #ffc107;
            color: #000;
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
        }
        
        .btn-small {
            padding: 5px 10px;
            font-size: 14px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h3 {
            color: #3B0270;
            margin: 0;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
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
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
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
        
        .course-status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
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
        
        .export-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .course-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid #3B0270;
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
            
            .action-buttons {
                flex-direction: column;
            }
            
            .export-actions {
                flex-direction: column;
            }
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
            <a href="courses.php" class="active" data-section="courses"><i class="fas fa-book"></i> <span>Course Management</span></a>
            <a href="sessions.php" data-section="sessions"><i class="fas fa-calendar-alt"></i> <span>Session Overview</span></a>
            <a href="attendance_reports.php" data-section="attendance"><i class="fas fa-clipboard-check"></i> <span>Attendance Reports</span></a>
            <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>  
        </nav>

        <div id="welcomeboard">
            <div class="welcome-section">
                <h1><i class="fas fa-book"></i> Course Management</h1>
                
                <?php if ($message): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <!-- Statistics -->
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="value"><?php echo $stats['total_courses']; ?></div>
                        <div class="label">Total Courses</div>
                    </div>
                    <div class="stat-card">
                        <div class="value"><?php echo $stats['active_courses']; ?></div>
                        <div class="label">Active Courses</div>
                    </div>
                    <div class="stat-card">
                        <div class="value"><?php echo $stats['inactive_courses']; ?></div>
                        <div class="label">Inactive Courses</div>
                    </div>
                    <div class="stat-card">
                        <div class="value"><?php echo $stats['total_students']; ?></div>
                        <div class="label">Total Students</div>
                    </div>
                </div>
                
                <!-- Export and Create Buttons -->
                <div class="section-header">
                    <h2><i class="fas fa-list"></i> My Courses</h2>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn btn-warning" onclick="exportCoursesToCSV()">
                            <i class="fas fa-file-excel"></i> Export CSV
                        </button>
                        <button class="btn btn-success" onclick="openCreateModal()">
                            <i class="fas fa-plus-circle"></i> Create New Course
                        </button>
                    </div>
                </div>
                
                <!-- Courses List -->
                <?php if (empty($courses)): ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-book fa-3x" style="margin-bottom: 20px; color: #ddd;"></i>
                    <h3>No Courses Found</h3>
                    <p>You haven't created any courses yet. Create your first course to get started.</p>
                    <button class="btn btn-success" onclick="openCreateModal()">
                        <i class="fas fa-plus-circle"></i> Create Your First Course
                    </button>
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Description</th>
                                <th>Students</th>
                                <th>Sessions</th>
                                <th>Credits</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                <td><?php echo htmlspecialchars(substr($course['course_description'] ?? '', 0, 50) . '...'); ?></td>
                                <td><?php echo $course['enrolled_count']; ?></td>
                                <td><?php echo $course['session_count']; ?></td>
                                <td><?php echo $course['credits']; ?></td>
                                <td>
                                    <span class="course-status status-<?php echo $course['status']; ?>">
                                        <?php echo ucfirst($course['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-primary btn-small" onclick="openEditModal(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['course_code']); ?>', '<?php echo htmlspecialchars($course['course_name']); ?>', '<?php echo htmlspecialchars($course['course_description']); ?>', <?php echo $course['credits']; ?>, <?php echo $course['max_students']; ?>, '<?php echo $course['status']; ?>')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-danger btn-small" onclick="confirmDelete(<?php echo $course['id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                        <a href="course_details.php?id=<?php echo $course['id']; ?>" class="btn btn-small" style="background-color: #6c757d;">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Create Course Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Create New Course</h3>
                <span class="close" onclick="closeCreateModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label for="course_code">Course Code *</label>
                    <input type="text" id="course_code" name="course_code" required maxlength="20" placeholder="e.g., CS101">
                </div>
                
                <div class="form-group">
                    <label for="course_name">Course Name *</label>
                    <input type="text" id="course_name" name="course_name" required maxlength="100" placeholder="e.g., Introduction to Programming">
                </div>
                
                <div class="form-group">
                    <label for="course_description">Course Description</label>
                    <textarea id="course_description" name="course_description" placeholder="Describe the course content, objectives, etc."></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label for="credits">Credits</label>
                        <input type="number" id="credits" name="credits" value="3" min="1" max="10">
                    </div>
                    
                    <div class="form-group">
                        <label for="max_students">Maximum Students</label>
                        <input type="number" id="max_students" name="max_students" value="50" min="1" max="200">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn" onclick="closeCreateModal()">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Course</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Course Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Course</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update">
                <input type="hidden" id="edit_course_id" name="course_id">
                
                <div class="form-group">
                    <label for="edit_course_code">Course Code *</label>
                    <input type="text" id="edit_course_code" name="course_code" required maxlength="20">
                </div>
                
                <div class="form-group">
                    <label for="edit_course_name">Course Name *</label>
                    <input type="text" id="edit_course_name" name="course_name" required maxlength="100">
                </div>
                
                <div class="form-group">
                    <label for="edit_course_description">Course Description</label>
                    <textarea id="edit_course_description" name="course_description"></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label for="edit_credits">Credits</label>
                        <input type="number" id="edit_credits" name="credits" min="1" max="10">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_max_students">Maximum Students</label>
                        <input type="number" id="edit_max_students" name="max_students" min="1" max="200">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_status">Course Status</label>
                    <select id="edit_status" name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Course</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h3>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" id="delete_course_id" name="course_id">
                
                <p style="text-align: center; color: #666; margin: 20px 0;">
                    Are you sure you want to deactivate this course?<br>
                    <strong>This action cannot be undone.</strong>
                </p>
                
                <div class="form-actions">
                    <button type="button" class="btn" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Deactivate Course</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Modal Functions
        function openCreateModal() {
            document.getElementById('createModal').style.display = 'block';
        }
        
        function closeCreateModal() {
            document.getElementById('createModal').style.display = 'none';
        }
        
        function openEditModal(id, code, name, description, credits, maxStudents, status) {
            document.getElementById('edit_course_id').value = id;
            document.getElementById('edit_course_code').value = code;
            document.getElementById('edit_course_name').value = name;
            document.getElementById('edit_course_description').value = description;
            document.getElementById('edit_credits').value = credits;
            document.getElementById('edit_max_students').value = maxStudents;
            document.getElementById('edit_status').value = status;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function confirmDelete(id) {
            document.getElementById('delete_course_id').value = id;
            document.getElementById('deleteModal').style.display = 'block';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
        }
        
        // Export to CSV
        function exportCoursesToCSV() {
            let csv = 'Course Code,Course Name,Description,Credits,Max Students,Status,Enrolled Students,Sessions,Created Date\n';
            
            <?php foreach ($courses as $course): ?>
            csv += '"<?php echo addslashes($course['course_code']); ?>",' +
                   '"<?php echo addslashes($course['course_name']); ?>",' +
                   '"<?php echo addslashes($course['course_description'] ?? ''); ?>",' +
                   '<?php echo $course['credits']; ?>,' +
                   '<?php echo $course['max_students']; ?>,' +
                   '"<?php echo $course['status']; ?>",' +
                   '<?php echo $course['enrolled_count']; ?>,' +
                   '<?php echo $course['session_count']; ?>,' +
                   '"<?php echo $course['created_at']; ?>"\n';
            <?php endforeach; ?>
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'courses_export_<?php echo date('Y-m-d'); ?>.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    </script>
</body>
</html>