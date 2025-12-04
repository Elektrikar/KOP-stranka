document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    
    form.addEventListener('submit', function(e) {
        let valid = true;
        
        // Clear previous errors
        document.querySelectorAll('.error').forEach(el => el.remove());
        
        // Password strength check
        if (password.value.length < 8) {
            showError(password, 'Heslo musí mať aspoň 8 znakov.');
            valid = false;
        }
        
        // Password match check
        if (password.value !== confirmPassword.value) {
            showError(confirmPassword, 'Heslá sa nezhodujú.');
            valid = false;
        }
        
        if (!valid) {
            e.preventDefault();
        }
    });
    
    // Real-time password confirmation check
    confirmPassword.addEventListener('input', function() {
        if (password.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Heslá sa nezhodujú.');
        } else {
            confirmPassword.setCustomValidity('');
        }
    });
    
    function showError(input, message) {
        const error = document.createElement('div');
        error.className = 'error-message';
        error.textContent = message;
        error.style.color = 'red';
        error.style.fontSize = '0.9em';
        error.style.marginTop = '5px';
        input.parentNode.appendChild(error);
    }
});