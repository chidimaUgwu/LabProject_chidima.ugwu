<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/register.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

  <img src="images/logo.png" alt="Company Logo">
   
  <div id="container">
    <h2>Create your account</h2>

    <!-- Error display area -->
    <div id="registerError"           class="error-message" style="display: none;"></div>

    <form class="forms" id="registerForm" method="POST">
      <!-- PERSONAL INFO -->
      <fieldset>
        <legend>Personal Information</legend>
        <input class="inputs" type="text" id="fname" name="fname" placeholder="Enter your first name" required>
        <input class="inputs" type="text" id="lname" name="lname" placeholder="Enter your last name" required>
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
        <input class="inputs" type="tel" id="phone" name="phone" placeholder="Phone number (e.g., 1234567890)" required>
        <input class="inputs" type="text" id="address" name="address" placeholder="Enter your complete address" required>
      </fieldset>
      
      <!-- ACCOUNT INFO -->
      <fieldset>
        <legend>Account Information</legend>
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

      <p style="text-align: center;">Already have an account? <a href="login.php">Log In</a></p>
    </form>
  </div>

  <script src="js/register.js"></script>
</body>
</html>