<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./zxc.css">
    <title>Login Page</title>

    <link rel="stylesheet" href="/assets/css/responsive.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body>


    
    <div class="container" id="container">

         <!--Sign In Form -->
        <div class="form-container sign-in">
            <div class="form-bg"></div>
            <form method="POST">
                <h1>Sign In</h1>

        
            


                <!-- User Type Buttons -->
                <div class="user-type-buttons">
                    <button type="button" class="user-btn" data-role="doctor">Doctor</button>
                    <button type="button" class="user-btn" data-role="patient">Patient</button>
                    <button type="button" class="user-btn" data-role="employee">Employee</button>
                    <button type="button" class="user-btn" data-role="admin">Admin</button>
                </div>




            <!---------->

            <!-- Patient Info Form (Hidden Initially) -->



  <!---------->


                <!-- Input Fields -->
                <div id="input-fields">
                    <!-- Default for Patient -->
                  <input type="text" name="national_id" placeholder="National ID" required>
                    <input type="password" name="password" placeholder="Password" required>
                </div>

                <a href="#">Forget Your Password?</a>
                <button name="submit">Sign In</button>
            </form>

            <?php
            if (!empty($error)) {
                echo "<p style='color: red;'>$error</p>";
            }
            ?>
        </div>

        <!-- Sign Up Form -->
        <div class="form-container sign-up">
             <div class="form-bg"></div>
            <form method="post">
                <h1>Create Account</h1>
         
          
               

                <!-- Patient Registration -->
                <input type="text" name="first-name" placeholder="Patient Name" required>
                <input type="text" name="national_id" placeholder="National ID" required>
                <input type="password" name="password_r" placeholder="Password" id="passwordInput" class="pass1" required>
                <div id="passwordStrength"></div>
                <input type="password" name="password" placeholder="Confirm Password" class="pass2" required>
                <div id="confirmMessage"></div>

                <button name="submit_r">sign up</button>
            </form>
        </div>

        <!-- Toggle Container -->
        <div class="toggle-container">
            <div class="toggle">
                <div class="toggle-panel toggle-left">
                    <h1><b>Hello, Friend!</b></h1>
                    <p>Register with your personal details to use all of site features</p>
                    <button class="hidden" id="login">Register</button>
                </div>
                <div class="toggle-panel toggle-right">
                    <h1><b>Welcome Back!</b></h1>
                    <p>Enter your personal details to use all of site features</p>
                    <button class="hidden" id="register">Register</button>
                </div>
            </div>
        </div>

    </div>

    <script src="./main.js"></script>

    <script>
        // Switch input fields based on user type
        const userButtons = document.querySelectorAll('.user-btn');
        const inputFields = document.getElementById('input-fields');

        userButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const role = btn.getAttribute('data-role');
                inputFields.innerHTML = '';

                if (role === 'patient') {
                    inputFields.innerHTML = `
                        <input type="text" name="national_id" placeholder="National ID" required>
                        <input type="password" name="password" placeholder="Password" required>
                    `;
                } else {
                    inputFields.innerHTML = `
                        <input type="text" name="username" placeholder="Username" required>
                        <input type="password" name="password" placeholder="Password" required>
                    `;
                }
            });
        });
    </script>


    <script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>

