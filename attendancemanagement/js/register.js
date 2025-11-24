// REGISTER FORM SUBMISSION
document.getElementById('registerForm').addEventListener('submit', async function(event) { 
  event.preventDefault(); // stop auto-submit

  // GET FORM INPUTS
  let fname = document.getElementById('fname');
  let lname = document.getElementById('lname');
  let phone = document.getElementById('phone').value.trim();
  let password = document.getElementById('password').value;
  let confirmPassword = document.getElementById('confirmPassword').value;
  let role = document.getElementById('role').value;
  let email = document.getElementById('uemail').value.trim();
  let dob = document.getElementById('dob').value;
  let gender = document.getElementById('gender').value;
  let address = document.getElementById('address').value.trim();
  let errorElement = document.getElementById('registerError');

  // Reset error message
  errorElement.textContent = "";
  errorElement.style.display = "none";

  // Validate required fields
  if (!fname.value || !lname.value || !phone || !email || !dob || !gender || !address || !role || !password) {
    showError("All fields are required!");
    return;
  }

  // FORMAT NAMES
  fname.value = fname.value.replace(/\b\w/g, char => char.toUpperCase());
  lname.value = lname.value.replace(/\b\w/g, char => char.toUpperCase());

  // Phone validation - digits only
  if (!/^\d+$/.test(phone)) {
    showError("Phone number must contain digits only.");
    return;
  }

  // Password strength - Updated to match PHP (6 characters minimum)
  if (password.length < 6) {
    showError("Password must be at least 6 characters.");
    return;
  }

  // Confirm password
  if (password !== confirmPassword) {
    showError("Passwords do not match!");
    return;
  }

  // Email validation
  const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailPattern.test(email)) {
    showError("Please enter a valid email address.");
    return;
  }

  // PREPARE SEND DATA - Remove countryCode since it's not defined
  const formData = new FormData();
  formData.append('fname', fname.value);
  formData.append('lname', lname.value);
  formData.append('phone', phone); // Just use the phone as entered
  formData.append('password', password);
  formData.append('role', role);
  formData.append('email', email);
  formData.append('dob', dob);
  formData.append('gender', gender);
  formData.append('address', address);

  // SUBMIT FORM (AJAX)
  try {
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;

    submitBtn.textContent = "Registering...";
    submitBtn.disabled = true;

    const response = await fetch('php/register_api.php', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    // SUCCESS
    if (result.success) {
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'success',
          title: 'Success!',
          text: result.message,
          timer: 3000,
          showConfirmButton: false
        }).then(() => {
          window.location.href = 'login.php'; // Fixed: login.php instead of login.html
        });
      } else {
        alert(result.message);
        window.location.href = 'login.php'; // Fixed: login.php instead of login.html
      }
    } 
    
    // ERROR
    else {
      showError(result.message);

      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'error',
          title: 'Error!',
          text: result.message
        });
      }
    }

  } catch (error) {
    console.error("Registration error:", error);
    showError("An error occurred during registration. Please try again.");

    if (typeof Swal !== 'undefined') {
      Swal.fire({
        icon: 'error',
        title: 'Network Error',
        text: 'Unable to connect to server. Please check your connection.'
      });
    }
  } 
  
  finally {
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.textContent = "Register";
    submitBtn.disabled = false;
  }
});

// Helper function to show errors
function showError(message) {
  const errorElement = document.getElementById('registerError');
  errorElement.textContent = message;
  errorElement.style.display = "block";
}