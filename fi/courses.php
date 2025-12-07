<?php
// C:\xampp\htdocs\Attandance\fi\courses.php
require_once '../config/session.php';
require_once '../config/database.php';
requireAuth();

// Check if user has faculty role
if (!isFacultyIntern()) {
    header("Location: ../auth/logout.php");
    exit();
}

// Handle form submission for adding course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    $course_code = trim($_POST['course_code']);
    $course_name = trim($_POST['course_name']);
    $course_description = trim($_POST['course_description']);
    $credits = intval($_POST['credits']);
    $max_students = intval($_POST['max_students']);
    $status = $_POST['status'];
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Check if course code already exists
        $checkQuery = "SELECT id FROM am_courses WHERE course_code = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$course_code]);
        
        if ($checkStmt->rowCount() > 0) {
            $_SESSION['error_message'] = "Course code already exists!";
        } else {
            // Insert new course
            $query = "INSERT INTO am_courses (course_code, course_name, course_description, credits, max_students, status, faculty_intern_id) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$course_code, $course_name, $course_description, $credits, $max_students, $status, $_SESSION['user_id']]);
            
            $_SESSION['success_message'] = "Course created successfully!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error creating course: " . $e->getMessage();
    }
    
    header("Location: courses.php");
    exit();
}

// Handle delete course
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $course_id = intval($_GET['delete']);
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Check if course belongs to this FI
        $checkQuery = "SELECT id FROM am_courses WHERE id = ? AND faculty_intern_id = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$course_id, $_SESSION['user_id']]);
        
        if ($checkStmt->rowCount() > 0) {
            // Delete the course (cascade will handle related records)
            $deleteQuery = "DELETE FROM am_courses WHERE id = ?";
            $deleteStmt = $db->prepare($deleteQuery);
            $deleteStmt->execute([$course_id]);
            
            $_SESSION['success_message'] = "Course deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Course not found or you don't have permission!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error deleting course: " . $e->getMessage();
    }
    
    header("Location: courses.php");
    exit();
}

// Get all courses for this FI
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT 
                c.*,
                COUNT(DISTINCT e.id) as student_count,
                COUNT(DISTINCT s.id) as session_count
              FROM am_courses c
              LEFT JOIN am_enrollments e ON c.id = e.course_id AND e.status = 'approved'
              LEFT JOIN am_sessions s ON c.id = s.course_id
              WHERE c.faculty_intern_id = ?
              GROUP BY c.id
              ORDER BY c.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $courses = [];
    error_log("Course management error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Management - Faculty Intern Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/style.css">
    <link rel="stylesheet" href="../styles/Student_Dashboard.css">
    <link rel="stylesheet" href="../styles/sessionSchedule.css">


    <style>
    /* MAIN CONTAINER */
    #welcomeboard {
        padding: 20px;
        width: 100%;
        background: #f5f6fa;
    }

    .welcome-section {
        background: #ffffff;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 0 8px rgba(0,0,0,0.1);
    }

    /* HEADER */
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .section-header h2 {
        font-size: 24px;
        color: #1F4F7B;
        font-weight: bold;
    }

    /* BUTTONS */
    .btn {
        padding: 10px 15px;
        background: #1F4F7B;
        color: white;
        border: none;
        border-radius: 7px;
        cursor: pointer;
        transition: 0.3s;
        font-size: 14px;
    }

    .btn:hover {
        background: #143554;
    }

    .btn-outline {
        background: white;
        border: 1px solid #1F4F7B;
        color: #1F4F7B;
        padding: 10px 15px;
        border-radius: 7px;
        cursor: pointer;
        transition: 0.3s;
    }

    .btn-outline:hover {
        background: #1F4F7B;
        color: white;
    }

    /* TABLE */
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
        background: white;
        border-radius: 10px;
        overflow: hidden;
    }

    table thead {
        background: #1F4F7B;
        color: white;
    }

    table th, table td {
        padding: 12px;
        border-bottom: 1px solid #e0e0e0;
        text-align: left;
        font-size: 14px;
    }

    table tbody tr:hover {
        background: #f1f5fb;
    }

    /* STATUS BADGES */
    .status-completed {
        background: #27ae60;
        color: white;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 12px;
    }

    .status-Absent {
        background: #e67e22;
        color: white;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 12px;
    }

    /* ACTION BUTTONS */
    .action-buttons {
        display: flex;
        gap: 8px;
    }

    .action-btn {
        background: #eaf1f8;
        border: none;
        padding: 8px 10px;
        border-radius: 6px;
        cursor: pointer;
        transition: 0.3s;
        font-size: 14px;
        color: #1F4F7B;
    }

    .action-btn:hover {
        background: #d3e1f0;
    }

    .action-btn.delete {
        background: #f8d7da;
        color: #721c24;
    }

    .action-btn.delete:hover {
        background: #f5c6cb;
    }

    .action-btn.view {
        background: #d4edda;
        color: #155724;
        padding: 8px 12px;
        display: inline-block;
    }

    .action-btn.view:hover {
        background: #c3e6cb;
    }

    /* MODAL */
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.6);
        display: none;
        justify-content: center;
        align-items: center;
    }

    .modal-content {
        background: white;
        width: 500px;
        padding: 20px;
        border-radius: 10px;
        animation: fadeIn 0.3s ease-out;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .modal-header h2 {
        color: #1F4F7B;
        font-size: 22px;
    }

    .close-btn {
        background: none;
        border: none;
        font-size: 22px;
        cursor: pointer;
        color: #444;
    }

    .form-group {
        margin-bottom: 12px;
    }

    .form-group label {
        font-weight: 600;
        display: block;
        margin-bottom: 5px;
    }

    .form-control {
        width: 100%;
        padding: 8px;
        border-radius: 6px;
        border: 1px solid #ccc;
    }

    .form-row {
        display: flex;
        gap: 15px;
    }

    @keyframes fadeIn {
        from {opacity: 0; transform: scale(0.95);}
        to {opacity: 1; transform: scale(1);}
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
            <a href="courses.php" class="active" data-section="courses"><i class="fas fa-book"></i> <span>Course Management</span></a>
            <a href="sessions.php" data-section="sessions"><i class="fas fa-calendar-alt"></i> <span>Session Overview</span></a>
            <a href="attendance.php" data-section="attendance"><i class="fas fa-clipboard-check"></i> <span>Attendance Reports</span></a>
            <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>  
        </nav>

        <div id="welcomeboard">
            <div class="welcome-section">
                <div class="section-header">
                    <h2>Course Management</h2>
                    <button class="btn" style="width: 25% !important;" id="addCourseBtn"><i class="fas fa-plus"></i> Add New Course</button>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0;">
                        <?php 
                        echo htmlspecialchars($_SESSION['success_message']);
                        unset($_SESSION['success_message']);
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0;">
                        <?php 
                        echo htmlspecialchars($_SESSION['error_message']);
                        unset($_SESSION['error_message']);
                        ?>
                    </div>
                <?php endif; ?>

                <table>
                    <thead>
                        <tr>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>Students</th>
                            <th>Sessions</th>
                            <th>Credits</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($courses)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">No courses found. Create your first course!</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($courses as $course): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                            <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                            <td><?php echo $course['student_count']; ?></td>
                            <td><?php echo $course['session_count']; ?></td>
                            <td><?php echo $course['credits']; ?></td>
                            <td>
                                <?php if ($course['status'] === 'active'): ?>
                                <span class="status-completed">Active</span>
                                <?php else: ?>
                                <span class="status-Absent">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="action-buttons">
                                <button class="action-btn edit" onclick="editCourse(<?php echo $course['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn delete" onclick="confirmDelete(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['course_code']); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <a href="course_details.php?id=<?php echo $course['id']; ?>" class="action-btn view" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
    
    <!-- Add Course Modal -->
    <div class="modal" id="addCourseModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Course</h2>
                <button class="close-btn">&times;</button>
            </div>
            <form id="courseForm" method="POST" action="courses.php">
                <input type="hidden" name="add_course" value="1">
                <div class="form-group">
                    <label for="courseCode">Course Code *</label>
                    <input type="text" id="courseCode" name="course_code" class="form-control" required maxlength="20" placeholder="e.g., CS201">
                </div>
                <div class="form-group">
                    <label for="courseName">Course Name *</label>
                    <input type="text" id="courseName" name="course_name" class="form-control" required maxlength="100" placeholder="e.g., Data Structures & Algorithms">
                </div>
                <div class="form-group">
                    <label for="courseDescription">Course Description</label>
                    <textarea id="courseDescription" name="course_description" class="form-control" rows="3" placeholder="Brief description of the course"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="credits">Credits</label>
                        <input type="number" id="credits" name="credits" class="form-control" min="1" max="6" value="3">
                    </div>
                    <div class="form-group">
                        <label for="maxStudents">Max Students</label>
                        <input type="number" id="maxStudents" name="max_students" class="form-control" min="1" max="100" value="50">
                    </div>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="active" selected>Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn"><i class="fas fa-save"></i> Create Course</button>
                    <button type="button" class="btn btn-outline" id="cancelCourseBtn">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div> 
<script>
// Modal functionality
const addCourseBtn = document.getElementById('addCourseBtn');
const modal = document.getElementById('addCourseModal');
const closeBtn = document.querySelector('.close-btn');
const cancelBtn = document.getElementById('cancelCourseBtn');

addCourseBtn.onclick = () => modal.style.display = 'flex';
closeBtn.onclick = cancelBtn.onclick = () => modal.style.display = 'none';
window.onclick = e => { if (e.target === modal) modal.style.display = 'none'; }

// Form validation
document.getElementById('courseForm').addEventListener('submit', function(e) {
    const courseCode = document.getElementById('courseCode').value.trim();
    const courseName = document.getElementById('courseName').value.trim();
    
    if (!courseCode || !courseName) {
        e.preventDefault();
        alert('Course Code and Course Name are required!');
        return false;
    }
    
    // Validate course code format (letters + numbers)
    const codePattern = /^[A-Za-z0-9]+$/;
    if (!codePattern.test(courseCode)) {
        e.preventDefault();
        alert('Course code should contain only letters and numbers.');
        return false;
    }
    
    return true;
});

// Delete confirmation
function confirmDelete(courseId, courseCode) {
    if (confirm(`Are you sure you want to delete course "${courseCode}"?\n\nThis will also delete all associated sessions, enrollments, and attendance records!`)) {
        window.location.href = `courses.php?delete=${courseId}`;
    }
}

// Edit course (placeholder for now)
function editCourse(courseId) {
    alert('Edit functionality will be implemented in the next update!');
}
</script>
</body>
</html>
