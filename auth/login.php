<?php
// C:\xampp\htdocs\Attandance\auth\login.php
require_once '../config/session.php';
redirectIfLoggedIn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="../styles/style.css" />
  <link href="https://fonts.cdnfonts.com/css/East-Bouvent" rel="stylesheet" />
  <title>Login - ATTENDIFY</title>
</head>
<body>
  <img src="../images/logo.png" alt="Company Logo">

  <div id="container">
    <h2>Welcome Back to ATTENDIFY</h2>
    <div id="info">
      <p>
        " Your attendance management system.<br>
        Please <b>log in</b> to continue. "
      </p>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
      <div style="color: green; margin: 10px 0; padding: 10px; background: #d4edda; border-radius: 5px;">
        <?php 
        echo htmlspecialchars($_SESSION['success_message']);
        unset($_SESSION['success_message']);
        ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
      <div style="color: red; margin: 10px 0; padding: 10px; background: #f8d7da; border-radius: 5px;">
        <?php echo htmlspecialchars($_GET['error']); ?>
      </div>
    <?php endif; ?>

    <form class="forms" id="loginForm" action="process_login.php" method="POST">
      <input class="inputs" type="email" name="email" placeholder="Email" id="signinEmail" required>
      <input class="inputs" type="password" name="password" placeholder="Password" id="signinPassword" required>
    
      <button type="submit">LOG IN</button>

      <!-- Error message placeholder -->
      <p id="signinError" style="color: red; margin-top: 10px;"></p>

      <a href="forgot_password.php">Forgot Password?</a>
      <p>Don't have an account? <a id="signup-link" href="register.php">Sign Up</a></p>
    </form>
  </div>

  <!-- Link your JavaScript at the bottom -->
  <script src="../Script/login.js"></script>
</body>
</html>