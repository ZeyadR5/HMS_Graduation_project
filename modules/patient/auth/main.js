const container = document.getElementById('container');
const registerBtn = document.getElementById('register');
const loginBtn = document.getElementById('login');

registerBtn.addEventListener('click', () => {
    container.classList.add("active");
});

loginBtn.addEventListener('click', () => {
    container.classList.remove("active");
});

// -------------------------------------------------
// Password Visibility Toggle (Delegated)
// -------------------------------------------------
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.toggle-password-btn');
    if (btn) {
        const targetId = btn.getAttribute('data-target');
        const input = document.getElementById(targetId);
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    }
});

// -------------------------------------------------
// Sign-up Password Verification
// -------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
    const passwordInput = document.getElementById('passwordInput');
    const confirmPasswordInput = document.getElementById('confirmPasswordInput');
    const reqLength = document.getElementById('req-length');
    const iconLength = document.getElementById('icon-length');
    const reqUppercase = document.getElementById('req-uppercase');
    const iconUppercase = document.getElementById('icon-uppercase');
    const confirmMessage = document.getElementById('confirmMessage');
    const signUpForm = document.querySelector('.sign-up form');

    if (passwordInput && confirmPasswordInput) {
        function validatePassword() {
            const password = passwordInput.value;
            const hasLength = password.length >= 8;
            const hasUppercase = /[A-Z]/.test(password);

            // Length Check
            if (hasLength) {
                reqLength.style.color = 'green';
                iconLength.className = 'bi bi-check-circle-fill';
                iconLength.style.color = 'green';
            } else {
                reqLength.style.color = '#64748b';
                iconLength.className = 'bi bi-circle';
                iconLength.style.color = '';
            }

            // Uppercase Check
            if (hasUppercase) {
                reqUppercase.style.color = 'green';
                iconUppercase.className = 'bi bi-check-circle-fill';
                iconUppercase.style.color = 'green';
            } else {
                reqUppercase.style.color = '#64748b';
                iconUppercase.className = 'bi bi-circle';
                iconUppercase.style.color = '';
            }

            // Match Check
            if (confirmPasswordInput.value) {
                if (password === confirmPasswordInput.value) {
                    confirmMessage.innerText = "Passwords match / كلمات المرور متطابقة";
                    confirmMessage.style.color = "green";
                } else {
                    confirmMessage.innerText = "Passwords do not match / كلمات المرور غير متطابقة";
                    confirmMessage.style.color = "red";
                }
            } else {
                confirmMessage.innerText = "";
            }

            return hasLength && hasUppercase;
        }

        passwordInput.addEventListener('input', validatePassword);
        confirmPasswordInput.addEventListener('input', validatePassword);

        if (signUpForm) {
            signUpForm.addEventListener('submit', (e) => {
                const isValid = validatePassword();
                const doMatch = passwordInput.value === confirmPasswordInput.value;

                if (!isValid) {
                    e.preventDefault();
                    if (passwordInput.value.length < 8) {
                        reqLength.style.color = 'red';
                        iconLength.className = 'bi bi-x-circle-fill';
                        iconLength.style.color = 'red';
                    }
                    if (!/[A-Z]/.test(passwordInput.value)) {
                        reqUppercase.style.color = 'red';
                        iconUppercase.className = 'bi bi-x-circle-fill';
                        iconUppercase.style.color = 'red';
                    }
                    alert('Password does not meet requirements!');
                } else if (!doMatch) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                }
            });
        }
    }
});