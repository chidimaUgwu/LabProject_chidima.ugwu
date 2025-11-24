document.getElementById('logingForm').addEventListener('submit', async function (event) {
    event.preventDefault();

    const email = document.getElementById('signinEmail').value.trim();
    const password = document.getElementById('signinPassword').value;
    const error = document.getElementById('signinError');

    error.textContent = "";

    if (email === "" || password === "") {
        error.textContent = "All fields are required!";
        return;
    }

    const formData = new FormData();
    formData.append('email', email);
    formData.append('password', password);

    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Signing in...';
    submitBtn.disabled = true;

    try {
        const response = await fetch('php/login_api.php', {
            method: 'POST',
            body: formData
        });

        // First, check if response is OK
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const text = await response.text();
        console.log('Raw response:', text); // Debug log

        let result;
        try {
            result = JSON.parse(text);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            throw new Error('Invalid server response');
        }

        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Login Successful!',
                text: 'Welcome back, ' + result.username + '!',
                showConfirmButton: false,
                timer: 1500
            }).then(() => {
                // Redirect based on role - use consistent role names
                const redirects = {
                    'student': 'Student_Dashboard.php',
                    'faculty': 'Faculty_Dashboard.php',
                    'instructor': 'Instructor_Dashboard.php', // Changed from 'lecturer' to 'instructor'
                    'admin': 'Admin_Dashboard.php'
                };
                
                const redirectUrl = redirects[result.role] || 'Student_Dashboard.php';
                console.log('Redirecting to:', redirectUrl); // Debug log
                window.location.href = redirectUrl;
            });

        } else {
            // Show error message
            error.textContent = result.message;
            Swal.fire({
                icon: 'error',
                title: 'Login Failed',
                text: result.message || 'Invalid email or password'
            });
        }

    } catch (err) {
        console.error('Login error:', err);
        error.textContent = "Login failed. Please try again.";
        Swal.fire({
            icon: 'error',
            title: 'Network Error',
            text: 'Unable to connect to server. Please check your connection and try again.'
        });
    } finally {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
});