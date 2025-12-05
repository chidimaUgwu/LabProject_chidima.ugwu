<?php
// C:\xampp\htdocs\Attandance\auth\register.php
require_once '../config/session.php';
redirectIfLoggedIn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - ATTENDIFY</title>
  <link rel="stylesheet" href="../styles/style.css">
  <link rel="stylesheet" href="../styles/register.css">
</head>
<body>
  <img src="../images/logo.png" alt="Company Logo">

  <div id="container">
    <h2>Create your account</h2>

    <form class="forms" id="registerForm" action="process_register.php" method="POST">
      <!-- PERSONAL INFO -->
      <fieldset>
        <legend>Personal Information</legend>
        <input class="inputs" type="text" id="fname" name="fname" placeholder="Enter your first name" required>
        <input class="inputs" type="text" id="lname" name="lname" placeholder="Enter your last name" required>
        <input class="inputs" type="text" id="userid" name="userid" placeholder="Enter your student ID" required>
        <input class="inputs" type="date" id="dob" name="dob" required>

        <select class="inputs" id="gender" name="gender" required>
          <option value="">Select Gender</option>
          <option value="Male">Male</option>
          <option value="Female">Female</option>
          <option value="Other">Other</option>
        </select>
      </fieldset>

      <!-- CONTACT INFO -->
      <fieldset>
        <legend>Contact Information</legend>
        <input class="inputs" type="email" id="uemail" name="email" placeholder="Enter your email" required>

        <!-- Country code + phone -->
        <div style="display: flex; gap: 5px;">
          <select id="countryCode" name="country_code" class="inputs" style="width: 25%;">
            <option value="+233">🇬🇭 +233</option>
            <option value="+234">🇳🇬 +234</option>
            <option value="+225">🇨🇮 +225</option>
            <option value="+226">🇧🇫 +226</option>
          </select>
          <input class="inputs" style="width: 75%;" type="tel" id="phone" name="phone" placeholder="Enter your phone number" required>
        </div>

        <input class="inputs" type="text" id="address" name="address" placeholder="Enter your address" required>
      </fieldset>

      <!-- ACCOUNT INFO -->
      <fieldset>
        <legend>Account Information</legend>
        <label for="role">Role Selection</label>
        <select class="inputs" id="role" name="role" required>
          <option value="">Select your role</option>
          <option value="student">Student</option>
          <option value="faculty">Faculty Intern</option>
          <option value="instructor">Instructor</option>
        </select>

        <input class="inputs" type="password" id="password" name="password" placeholder="Create a password" required>
        <input class="inputs" type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm your password" required>
      </fieldset>

      <button type="submit">Register</button>
      <p id="registerError" style="color:red; margin-top:10px;">
        <?php 
        if (isset($_GET['error'])) {
            echo htmlspecialchars($_GET['error']);
        }
        ?>
      </p>

      <p>Already have an account? <a id="signup-link" href="login.php">Log In</a></p>
    </form>
  </div>

  <script src="../Script/register.js"></script>
</body>
</html>