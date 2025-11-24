async function logout() {
    try {
        const response = await fetch('php/auth/logout.php');
        const result = await response.json();
        
        if (result.logout) {
            Swal.fire({
                icon: 'success',
                title: 'Logged Out!',
                text: 'You have been successfully logged out',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.href = 'login.php';
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'An error occurred during logout'
        });
    }
}

// Attach logout function to all logout buttons
document.addEventListener('DOMContentLoaded', function() {
    const logoutButtons = document.querySelectorAll('a[href="#"]');
    logoutButtons.forEach(button => {
        if (button.textContent.includes('Logout')) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                logout();
            });
        }
    });
});