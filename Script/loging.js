// C:\xampp\htdocs\Attandance\Script\login.js

document.getElementById('loginForm')?.addEventListener('submit', function(event) {
  // Only run if form exists on the page
  if (!this) return;
  
  let email = document.getElementById('signinEmail').value.trim();
  let password = document.getElementById('signinPassword').value;
  let error = document.getElementById('signinError');

  error.textContent = "";

  // Check if empty
  if (email === "" || password === "") {
    error.textContent = "Email and password are required!";
    event.preventDefault();
    return false;
  }

  // Check valid email format
  let emailPattern = /^[^ ]+@[^ ]+\.[a-z]{2,3}$/;
  if (!email.match(emailPattern)) {
    error.textContent = "Invalid email format!";
    event.preventDefault();
    return false;
  }

  // All validations passed
  return true;
});