<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="css/style.css" />
  <link href="https://fonts.cdnfonts.com/css/East-Bouvent" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <title>Login</title>
</head>
<body>

  <img src="images/logo.png" alt="Company Logo">

  <div id="container">
    <h2>Welcome Back to ATTENDIFY</h2>
    
    <div id="info">
      <p>" Your attendance management system.<br>
      Please <b>log in</b> to continue. "</p>
    </div>

    <form class="forms" id="logingForm" method="POST">
      
      <input class="inputs" type="email" placeholder="Email" id="signinEmail" name="email" required>
      <input class="inputs" type="password" placeholder="Password" name="password" id="signinPassword" required>

      <button type="submit">LOG IN</button>
      <p id="signinError" style="color: red; margin-top: 10px;"></p>

      <a href="#">Forgot Password?</a>
      <p>Don't have an account? <a href="register.php">Sign Up</a></p>
    </form>
  </div>

  <script src="js/loging.js"></script>


</body>
</html>
