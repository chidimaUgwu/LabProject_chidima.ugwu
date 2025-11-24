document.getElementById('registerForm').addEventListener('submit', async function(event) {
  event.preventDefault(); // prevent automatic submission

  let fname = document.getElementById('fname');
  let lname = document.getElementById('lname');
  let userId = document.getElementById('userid').value.trim();
  let phone = document.getElementById('phone').value.trim();
  let countryCode = document.getElementById('countryCode').value;
  let password = document.getElementById('password').value;
  let confirmPassword = document.getElementById('confirmPassword').value;
  let role = document.getElementById('role').value;
  let email = document.getElementById('uemail').value.trim();
  let dob = document.getElementById('dob').value;
  let gender = document.getElementById('gender').value;
  let address = document.getElementById('address').value;
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

  // Validate required fields
  if (!email || !dob || !gender || !address) {
    error.textContent = "All fields are required!";
    return;
  }

  let fullPhone = `${countryCode}${phone}`;

  // Create form data for AJAX submission
  const formData = new FormData();
  formData.append('fname', fname.value);
  formData.append('lname', lname.value);
  formData.append('userid', userId);
  formData.append('phone', fullPhone);
  formData.append('password', password);
  formData.append('role', role);
  formData.append('email', email);
  formData.append('dob', dob);
  formData.append('gender', gender);
  formData.append('address', address);

  try {
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Registering...';
    submitBtn.disabled = true;

    const response = await fetch('php/auth/signup.php', {
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
          timer: 3000,
          showConfirmButton: false
        }).then(() => {
          // Redirect to login page
          window.location.href = 'login.html';
        });
      } else {
        // Fallback to regular alert if SweetAlert not available
        alert(result.message);
        window.location.href = 'login.html';
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
    console.error('Registration error:', error);
    error.textContent = 'An error occurred during registration. Please try again.';
    
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: 'An error occurred during registration'
      });
    }
  } finally {
    // Reset button state
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.textContent = 'Register';
    submitBtn.disabled = false;
  }
});