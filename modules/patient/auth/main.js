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


// -------------------------------------------------
function checkPasswordStrength() {
    let password = document.querySelector("#passwordInput").value;
    let strengthBadge = document.getElementById("passwordStrength");
    
    let hasUpperCase = /[A-Z]/.test(password); // يحتوي على حروف كبيرة
    let hasLowerCase = /[a-z]/.test(password); // يحتوي على حروف صغيرة
    let hasNumber = /\d/.test(password); // يحتوي على أرقام
    let hasSymbols = /[!@#$%^&,*_\-,/]/.test(password); // يحتوي على رموز خاصة

    // تحديث واجهة المستخدم بناءً على قوة كلمة المرور
    if (password.length === 0) {
        strengthBadge.innerText = "";
    } else if (!hasUpperCase || !hasLowerCase || !hasNumber || !hasSymbols) {
        strengthBadge.innerText = "The password must contain at least  :\n one uppercase letter,\n one lowercase letter,\n one number,\n and one spescial character.";
        strengthBadge.style.color = "red";
    } else {
        strengthBadge.innerText = "Strong";
        strengthBadge.style.color = "green";
    }
}

document.getElementById("passwordInput").addEventListener("input", checkPasswordStrength);

checkPasswordStrength(); // Initial check


// Get the password field and the element triggering the click

// Function to confirm password match
function ConfirmPass() {
    let passwordOne = document.querySelector(".pass1");
    let passwordTwo = document.querySelector(".pass2");
    let ConfirmMessage = document.querySelector("#confirmMessage");

    // Add event listener to the second password input field
    passwordTwo.addEventListener("input", function () {
        if (passwordOne.value === passwordTwo.value) {
            ConfirmMessage.innerText = "Passwords match";
            ConfirmMessage.style.color = "green";
        } else {
            ConfirmMessage.innerText = "Passwords Doesn't match";
            ConfirmMessage.style.color = "red";
        }
    });
}

ConfirmPass();


// Validate email function
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(String(email).toLowerCase());
}


// Confirm password function
function confirmPassword() {
    const pass1 = document.querySelector('.pass1');
    const pass2 = document.querySelector('.pass2');
    const message = document.getElementById('confirmMessage');
    const goodColor = "#66cc66";
    const badColor = "#ff6666";

    if (pass1.value === pass2.value) {
        pass2.style.backgroundColor = goodColor;
        message.style.color = goodColor;
        message.innerHTML = "Passwords Match!";
    } else {
        pass2.style.backgroundColor = badColor;
        message.style.color = badColor;
        message.innerHTML = "Passwords Do Not Match!";
    }
}

// Event listener for sign up form submission
document.querySelector('.sign-up form').addEventListener('submit', function (e) {
    const firstName = document.getElementsByName('text')[0].value;
    const lastName = document.getElementsByName('text')[1].value;
    const email = document.getElementsByName('email')[0].value;
    const password = document.getElementsByName('password')[0].value;

    // Check if data types are strings
    if (typeof firstName !== 'string' || typeof lastName !== 'string' || typeof email !== 'string' || typeof password !== 'string') {
        e.preventDefault();
        alert("Please enter valid data.");
    }

    // Validate email format
    if (!validateEmail(email)) {
        e.preventDefault();
        alert("Please enter a valid email address.");
    }

    // Validate password strength
    const strength = validatePasswordStrength(password);
    const strengthMessage = document.getElementById('passwordStrength');
    strengthMessage.innerHTML = "Password Strength: " + strength;

    // Confirm password
    confirmPassword();
});

// Event listener for login form submission
document.querySelector('.sign-in form').addEventListener('submit', function (e) {
    const email = document.getElementsByName('email')[0].value;
    const password = document.getElementsByName('password')[0].value;

    // Check if data types are strings
    if (typeof email !== 'string' || typeof password !== 'string') {
        e.preventDefault();
        alert("Please enter valid data.");
    }

    // Validate email format
    if (!validateEmail(email)) {
        e.preventDefault();
        alert("Please enter a valid email address.");
    }
});

// Event listener for password confirmation
document.querySelector('.pass2').addEventListener('keyup', confirmPassword);

/////////////////////////

const passwordInputSignIn = document.getElementById('passwordInputSignIn');
const togglePassword = document.getElementById('togglePassword');

togglePassword.addEventListener('click', function() {
    const type = passwordInputSignIn.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInputSignIn.setAttribute('type', type);

    // تغيير الأيقونة
    this.classList.toggle('fa-eye');
    this.classList.toggle('fa-eye-slash');
});





// Show patient info form after sign in

/*1*/