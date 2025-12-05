// C:\xampp\htdocs\Attandance\Script\register.js

document.getElementById('registerForm')?.addEventListener('submit', function(event) {
  // Only run if form exists on the page
  if (!this) return;
  
  let fname = document.getElementById('fname');
  let lname = document.getElementById('lname');
  let userId = document.getElementById('userid').value.trim();
  let phone = document.getElementById('phone').value.trim();
  let countryCode = document.getElementById('countryCode').value;
  let password = document.getElementById('password').value;
  let confirmPassword = document.getElementById('confirmPassword').value;
  let role = document.getElementById('role').value;
  let error = document.getElementById('registerError');

  error.textContent = "";

  // Auto Capitalize First and Last Names
  if (fname) fname.value = fname.value.replace(/\b\w/g, char => char.toUpperCase());
  if (lname) lname.value = lname.value.replace(/\b\w/g, char => char.toUpperCase());

  // Student ID validation (exactly 8 digits)
  if (!/^\d{8}$/.test(userId)) {
    error.textContent = "Student ID must contain exactly 8 digits.";
    event.preventDefault();
    return false;
  }

  // Phone number digits only
  if (!/^\d+$/.test(phone)) {
    error.textContent = "Phone number must contain digits only.";
    event.preventDefault();
    return false;
  }

  // Strong password validation
  let strongPasswordPattern =
    /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
  if (!strongPasswordPattern.test(password)) {
    error.textContent =
      "Password must be at least 8 characters, with uppercase, lowercase, number, and symbol.";
    event.preventDefault();
    return false;
  }

  // Confirm password check
  if (password !== confirmPassword) {
    error.textContent = "Passwords do not match!";
    event.preventDefault();
    return false;
  }

  // All validations passed
  return true;
});

  // If all validations pass, form will submit normally
  // Registration will go to login page, NOT dashboard
  // The alert and redirect in the original code were wrong
  
  // Remove this old code that was causing the issue:
  /*
  // SUCCESS → Redirect based on role
  let fullPhone = `${countryCode}${phone}`;

  alert(`Registration successful for ${fname.value} (${role})!\nPhone: ${fullPhone}`);

  // Redirect user to corresponding dashboard
  if (role === "student") {
    window.location.href = "Student_Dashboard.html";
  } else if (role === "faculty") {
    window.location.href = "FI_Dashboard.html";
  } else if (role === "instructor") {
    window.location.href = "Faculty_DashB.html";
  }
  else {
    error.textContent = "Invalid role!" <br> "Please select a valid role.";
    return;
  }
  */
  
  // Allow form to submit to PHP