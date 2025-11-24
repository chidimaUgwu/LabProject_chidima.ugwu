document.getElementById('logingForm').addEventListener('submit', async function(event) {
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

  // Create form data for AJAX submission
  const formData = new FormData();
  formData.append('email', email);
  formData.append('password', password);
  formData.append('role', role);

  try {
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Signing in...';
    submitBtn.disabled = true;

    const response = await fetch('php/auth/login.php', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    
    if (result.success) {
      // Use SweetAlert for success message
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'success',
          title: 'Success!',
          text: result.message,
          timer: 2000,
          showConfirmButton: false
        }).then(() => {
          // Redirect based on role
          if (role === "student") {
            window.location.href = "Student_Dashboard.html";
          } else if (role === "faculty") {
            window.location.href = "FI_Dashboard.html";
          } else if (role === "instructor") {
            window.location.href = "Faculty_DashB.html";
          }
        });
      } else {
        // Fallback to regular alert
        alert("Sign in successful!");
        
        // Redirect based on role
        if (role === "student") {
          window.location.href = "Student_Dashboard.html";
        } else if (role === "faculty") {
          window.location.href = "FI_Dashboard.html";
        } else if (role === "instructor") {
          window.location.href = "Faculty_DashB.html";
        }
      }
    } else {
      error.textContent = result.message;
      
      // Use SweetAlert for error if available
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'error',
          title: 'Error!',
          text: result.message
        });
      }
    }
  } catch (error) {
    console.error('Login error:', error);
    error.textContent = 'An error occurred during sign in. Please try again.';
    
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: 'An error occurred during sign in'
      });
    }
  } finally {
    // Reset button state
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.textContent = 'Sign In';
    submitBtn.disabled = false;
  }
});