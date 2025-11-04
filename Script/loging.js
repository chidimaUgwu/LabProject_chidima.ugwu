document.getElementById('logingForm').addEventListener('submit', function(event) {
  event.preventDefault();

  let email = document.getElementById('signinEmail').value.trim();
  let password = document.getElementById('signinPassword').value;
  let role = document.getElementById('role').value;
  let error = document.getElementById('signinError');

  error.textContent = "";

  // Check if empty
  if (email === "" || password === "") {
    error.textContent = "Email and password are required!";
    return;
  }

  // Check valid email format
  let emailPattern = /^[^ ]+@[^ ]+\.[a-z]{2,3}$/;
  if (!email.match(emailPattern)) {
    error.textContent = "Invalid email format!";
    return;
  }

  // Strong password rule
  let strongPasswordPattern =
    /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;

  if (!strongPasswordPattern.test(password)) {
    error.textContent =
      "Password must have at least 8 characters, including uppercase, lowercase, number, and symbol.";
    return;
  }

  // Success message
  alert("Sign in successful!");

 // Redirect user to corresponding dashboard
  if (role === "student") 
  {
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
