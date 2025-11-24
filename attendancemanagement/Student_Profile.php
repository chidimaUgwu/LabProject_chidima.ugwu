<?php
require_once 'php/auth_check.php';
checkRole('student');

require_once 'php/db_connection.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch student data
$student_data = [];
$sql = "SELECT u.*, s.student_id 
        FROM users u 
        LEFT JOIN students s ON u.user_id = s.student_id 
        WHERE u.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $student_data = $result->fetch_assoc();
}
$stmt->close();

// Handle profile update
$update_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $gender = $_POST['gender'];
    $dob = $_POST['dob'];
    $address = trim($_POST['address']);
    
    // Validate required fields
    if (!empty($first_name) && !empty($last_name)) {
        $update_sql = "UPDATE users SET first_name = ?, last_name = ?, phone = ?, gender = ?, dob = ?, address = ? WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssssssi", $first_name, $last_name, $phone, $gender, $dob, $address, $user_id);
        
        if ($update_stmt->execute()) {
            $update_message = '<div class="success-message">Profile updated successfully!</div>';
            // Update session username
            $_SESSION['username'] = $first_name . ' ' . $last_name;
            // Refresh data
            $student_data['first_name'] = $first_name;
            $student_data['last_name'] = $last_name;
            $student_data['phone'] = $phone;
            $student_data['gender'] = $gender;
            $student_data['dob'] = $dob;
            $student_data['address'] = $address;
        } else {
            $update_message = '<div class="error-message">Error updating profile. Please try again.</div>';
        }
        $update_stmt->close();
    } else {
        $update_message = '<div class="error-message">First name and last name are required.</div>';
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile - Attendance Management</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/Student_Dashboard.css">
    <style>
        .profile-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .profile-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #4CAF50;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 15px;
        }

        .profile-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-group textarea {
            height: 80px;
            resize: vertical;
        }

        .readonly-field {
            background-color: #f5f5f5;
            color: #666;
        }

        .btn-update {
            background: #4CAF50;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
        }

        .btn-update:hover {
            background: #45a049;
        }

        .profile-info {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .info-item {
            margin-bottom: 10px;
            padding: 8px;
            border-bottom: 1px solid #eee;
        }

        .info-label {
            font-weight: bold;
            color: #333;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .profile-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="top">
        <div class="logo-container">
            <img src="images/logo.png" alt="Company Logo">
            <h4 class="dashboard-title">STUDENT PROFILE</h4>
        </div>
    </div>
    
    <div class="board">
        <nav>
            <a href="Student_Dashboard.php"><i>📊</i> Dashboard</a>
            <a href="Student_Courses.php"><i>📚</i> My Courses</a>
            <a href="Student_sessionSchedule.php"><i>📅</i> Session Schedule</a>
            <a href="GradesReports.php"><i>🏆</i> Grade/Report</a>
            <a href="Student_Profile.php" class="active"><i>👤</i> Profile</a>
            <a href="logout.php"><i>🚪</i> Logout</a>
        </nav>

        <div id="welcomeboard">
            <div class="profile-container">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php 
                        $initials = substr($student_data['first_name'] ?? '', 0, 1) . substr($student_data['last_name'] ?? '', 0, 1);
                        echo strtoupper($initials);
                        ?>
                    </div>
                    <h1>My Profile</h1>
                    <p>Manage your personal information</p>
                </div>

                <?php echo $update_message; ?>

                <!-- Profile Information Display -->
                <div class="profile-info">
                    <h3>Account Information</h3>
                    <div class="info-item">
                        <span class="info-label">Student ID:</span> 
                        <?php echo htmlspecialchars($student_data['student_id'] ?? 'N/A'); ?>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email:</span> 
                        <?php echo htmlspecialchars($student_data['email'] ?? ''); ?>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Role:</span> 
                        <?php echo ucfirst(htmlspecialchars($student_data['role'] ?? '')); ?>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Member Since:</span> 
                        <?php echo date('F j, Y', strtotime($student_data['created_at'] ?? '')); ?>
                    </div>
                </div>

                <!-- Profile Edit Form -->
                <form method="POST" action="">
                    <div class="profile-form">
                        <div class="form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($student_data['first_name'] ?? ''); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($student_data['last_name'] ?? ''); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" 
                                   value="<?php echo htmlspecialchars($student_data['email'] ?? ''); ?>" 
                                   class="readonly-field" readonly>
                            <small style="color: #666;">Email cannot be changed</small>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($student_data['phone'] ?? ''); ?>" 
                                   placeholder="Enter your phone number">
                        </div>

                        <div class="form-group">
                            <label for="dob">Date of Birth</label>
                            <input type="date" id="dob" name="dob" 
                                   value="<?php echo htmlspecialchars($student_data['dob'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender">
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo (isset($student_data['gender']) && $student_data['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (isset($student_data['gender']) && $student_data['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo (isset($student_data['gender']) && $student_data['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" placeholder="Enter your complete address"><?php echo htmlspecialchars($student_data['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group full-width" style="text-align: center;">
                            <button type="submit" name="update_profile" class="btn-update">
                                Update Profile
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Add some client-side validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            
            if (!firstName || !lastName) {
                e.preventDefault();
                alert('Please fill in both first name and last name.');
                return false;
            }
        });

        // Format phone number input
        document.getElementById('phone')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                value = '+' + value;
            }
            e.target.value = value;
        });
    </script>
</body>
</html>