document.getElementById('registerForm').addEventListener('submit', function(event) {
  event.preventDefault(); // prevent automatic submission

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
  fname.value = fname.value.replace(/\b\w/g, char => char.toUpperCase());
  lname.value = lname.value.replace(/\b\w/g, char => char.toUpperCase());

  // Student ID validation (exactly 8 digits)
  if (!/^\d{8}$/.test(userId)) {
    error.textContent = "Student ID must contain exactly 8 digits.";
    return;
  }

  // Phone number digits only
  if (!/^\d+$/.test(phone)) {
    error.textContent = "Phone number must contain digits only.";
    return;
  }

  // Strong password validation
  let strongPasswordPattern =
    /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
  if (!strongPasswordPattern.test(password)) {
    error.textContent =
      "Password must be at least 8 characters, with uppercase, lowercase, number, and symbol.";
    return;
  }

  // Confirm password check
  if (password !== confirmPassword) {
    error.textContent = "Passwords do not match!";
    return;
  }

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
});
