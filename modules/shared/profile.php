<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('location: /index.php');
    exit();
}

define('HMS_SKIP_AUTO_CONNECT', true);
require_once __DIR__ . '/../../includes/config.php';
$connect = hms_db_connect();

$role = $_SESSION['role'] ?? '';
$userId = ($role === 'Patient') ? intval($_SESSION['uid'] ?? 0) : intval($_SESSION['id'] ?? 0);
$msg = "";
$error = "";

if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($new_password !== $confirm_password) {
        $error = "New password and Confirm password do not match.";
    } elseif (strlen($new_password) < 8 || !preg_match('/[A-Z]/', $new_password)) {
        $error = "New password must be at least 8 characters long and contain at least one uppercase letter.";
    } else {
        if ($role === 'Doctor') {
            $stmt = $connect->prepare("SELECT password FROM doctors WHERE id = ?");
        } elseif ($role === 'Patient') {
            $stmt = $connect->prepare("SELECT password FROM users WHERE uid = ?");
        } else {
            // Admin, System Admin, User
            $stmt = $connect->prepare("SELECT password FROM employ WHERE id = ?");
        }
        
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row && $row['password'] === $current_password) {
            // Correct current password, update to new
            if ($role === 'Doctor') {
                $upd = $connect->prepare("UPDATE doctors SET password = ? WHERE id = ?");
            } elseif ($role === 'Patient') {
                $upd = $connect->prepare("UPDATE users SET password = ? WHERE uid = ?");
            } else {
                $upd = $connect->prepare("UPDATE employ SET password = ? WHERE id = ?");
            }
            $upd->bind_param("si", $new_password, $userId);
            if ($upd->execute()) {
                $msg = "Password changed successfully.";
            } else {
                $error = "Failed to update password.";
            }
            $upd->close();
        } else {
            $error = "Current password is incorrect.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>
<link rel="icon" href="/assets/images/echol.png">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <style>
        input[type="password"]::-ms-reveal, input[type="password"]::-ms-clear { display: none; }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4f46e5, #06b6d4);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);
        }
        .profile-header {
            background: linear-gradient(to right, #ffffff, #f8fafc);
            border-bottom: 1px solid #e2e8f0;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-full">
        <?php $activePage = 'profile'; require __DIR__ . '/../../includes/nav.php'; ?>

        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 flex items-center justify-between">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">My Profile</h1>
            </div>
        </header>

        <main>
            <div class="mx-auto max-w-5xl py-10 sm:px-6 lg:px-8">
                
                <div class="md:grid md:grid-cols-3 md:gap-6">
                    <!-- Profile Card -->
                    <div class="md:col-span-1">
                        <div class="bg-white shadow sm:rounded-xl overflow-hidden text-center profile-header pb-8 pt-10 px-4">
                            <div class="flex justify-center mb-4">
                                <div class="rounded-full overflow-hidden border-4 border-white ring-4 ring-indigo-100 shadow-lg flex items-center justify-center bg-gradient-to-tr from-indigo-600 to-sky-500 text-white font-bold" style="width:100px; height:100px;">
                                    <?php if ($role === 'Doctor'): ?>
                                        <img src="<?= hms_get_doctor_avatar($_SESSION['username']) ?>" alt="Doctor Avatar" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <?php
                                        // Generate initials
                                        $initials = 'HM';
                                        $nameParts = preg_split('/\s+/', trim($_SESSION['username'] ?? '')) ?: [];
                                        if (!empty($nameParts)) {
                                            $initials = strtoupper(substr($nameParts[0], 0, 1));
                                            if (isset($nameParts[1])) {
                                                $initials .= strtoupper(substr($nameParts[1], 0, 1));
                                            }
                                        }
                                        ?>
                                        <span class="text-3xl font-extrabold tracking-wider"><?= htmlspecialchars($initials) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <h2 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($_SESSION['username'] ?? '') ?></h2>
                            <p class="text-sm font-semibold text-indigo-600 mb-1"><?= htmlspecialchars($_SESSION['role'] ?? '') ?></p>
                            <p class="text-sm text-gray-500 mb-6"><?= htmlspecialchars($_SESSION['login'] ?? '') ?></p>

                            <div class="border-t border-gray-100 pt-6 mt-4">
                                <div class="flex justify-center gap-4">
                                    <span class="inline-flex items-center rounded-md bg-green-50 px-3 py-1 text-sm font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                                        <i class="bi bi-shield-check mr-1"></i> Account Active
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Change Password Form -->
                    <div class="mt-5 md:mt-0 md:col-span-2">
                        <div class="bg-white shadow sm:rounded-xl overflow-hidden">
                            <div class="px-4 py-5 sm:p-6 border-b border-gray-100 bg-white">
                                <h3 class="text-lg leading-6 font-semibold text-gray-900 flex items-center gap-2">
                                    <i class="bi bi-key-fill text-indigo-500"></i> Security Settings
                                </h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    Ensure your account is using a long, random password to stay secure.
                                </p>
                            </div>
                            
                            <div class="px-4 py-5 sm:p-6">
                                <?php if ($msg): ?>
                                    <div class="mb-5 p-4 bg-green-50 border-l-4 border-green-500 rounded-r-md">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <i class="bi bi-check-circle-fill text-green-500"></i>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-green-800"><?= htmlspecialchars($msg) ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($error): ?>
                                    <div class="mb-5 p-4 bg-red-50 border-l-4 border-red-500 rounded-r-md">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <i class="bi bi-x-circle-fill text-red-500"></i>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-red-800"><?= htmlspecialchars($error) ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <form class="space-y-6" method="POST" action="" id="passwordForm">
                                    <div class="grid grid-cols-3 gap-4 items-center">
                                        <label for="current_password" class="block text-sm font-medium text-gray-700 col-span-1 text-right pr-4">Current Password</label>
                                        <div class="col-span-2 relative">
                                            <input type="password" name="current_password" id="current_password" required class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md border p-2.5 pr-10">
                                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 toggle-password-btn" data-target="current_password" tabindex="-1">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="border-t border-gray-100 pt-6"></div>

                                    <div class="grid grid-cols-3 gap-4 items-center">
                                        <label for="new_password" class="block text-sm font-medium text-gray-700 col-span-1 text-right pr-4">New Password</label>
                                        <div class="col-span-2 relative">
                                            <input type="password" name="new_password" id="new_password" required class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md border p-2.5 pr-10">
                                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 toggle-password-btn" data-target="new_password" tabindex="-1">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Password Requirements Checklist -->
                                    <div class="grid grid-cols-3 gap-4 items-center mt-2">
                                        <div class="col-span-1"></div>
                                        <div class="col-span-2 text-xs">
                                            <div class="font-semibold text-gray-700 mb-1">Password requirements / شروط كلمة المرور:</div>
                                            <div class="flex flex-col gap-1.5">
                                                <div id="req-length" class="flex items-center gap-1.5 text-gray-500 transition-colors duration-200">
                                                    <i class="bi bi-circle" id="icon-length"></i> <span>At least 8 characters / 8 حروف على الأقل</span>
                                                </div>
                                                <div id="req-uppercase" class="flex items-center gap-1.5 text-gray-500 transition-colors duration-200">
                                                    <i class="bi bi-circle" id="icon-uppercase"></i> <span>At least 1 uppercase letter / حرف كابيتال واحد على الأقل</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-3 gap-4 items-center mt-4">
                                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 col-span-1 text-right pr-4">Confirm Password</label>
                                        <div class="col-span-2 relative">
                                            <input type="password" name="confirm_password" id="confirm_password" required class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md border p-2.5 pr-10">
                                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 toggle-password-btn" data-target="confirm_password" tabindex="-1">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-3 gap-4 items-center mt-2">
                                        <div class="col-span-1"></div>
                                        <div id="matchMessage" class="col-span-2 text-xs font-semibold"></div>
                                    </div>

                                    <div class="pt-4 flex justify-end">
                                        <button type="submit" name="change_password" class="inline-flex items-center justify-center px-6 py-2.5 border border-transparent font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm shadow-sm transition-colors duration-200">
                                            <i class="bi bi-shield-lock mr-2"></i> Update Password
                                        </button>
                                    </div>
                                </form>

                                <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    // Toggle Passwords
                                    const toggleButtons = document.querySelectorAll('.toggle-password-btn');
                                    toggleButtons.forEach(btn => {
                                        btn.addEventListener('click', function() {
                                            const targetId = this.getAttribute('data-target');
                                            const input = document.getElementById(targetId);
                                            const icon = this.querySelector('i');
                                            if (input.type === 'password') {
                                                input.type = 'text';
                                                icon.classList.replace('bi-eye', 'bi-eye-slash');
                                            } else {
                                                input.type = 'password';
                                                icon.classList.replace('bi-eye-slash', 'bi-eye');
                                            }
                                        });
                                    });

                                    // Dynamic Requirements
                                    const newPass = document.getElementById('new_password');
                                    const confirmPass = document.getElementById('confirm_password');
                                    const form = document.getElementById('passwordForm');

                                    const reqLength = document.getElementById('req-length');
                                    const iconLength = document.getElementById('icon-length');
                                    const reqUppercase = document.getElementById('req-uppercase');
                                    const iconUppercase = document.getElementById('icon-uppercase');
                                    const matchMessage = document.getElementById('matchMessage');

                                    function validatePassword() {
                                        const val = newPass.value;
                                        const isLengthValid = val.length >= 8;
                                        const isUppercaseValid = /[A-Z]/.test(val);

                                        // Length Check
                                        if (isLengthValid) {
                                            reqLength.classList.remove('text-gray-500', 'text-red-500');
                                            reqLength.classList.add('text-green-600');
                                            iconLength.className = 'bi bi-check-circle-fill';
                                        } else {
                                            reqLength.classList.remove('text-green-600');
                                            reqLength.classList.add('text-gray-500');
                                            iconLength.className = 'bi bi-circle';
                                        }

                                        // Uppercase Check
                                        if (isUppercaseValid) {
                                            reqUppercase.classList.remove('text-gray-500', 'text-red-500');
                                            reqUppercase.classList.add('text-green-600');
                                            iconUppercase.className = 'bi bi-check-circle-fill';
                                        } else {
                                            reqUppercase.classList.remove('text-green-600');
                                            reqUppercase.classList.add('text-gray-500');
                                            iconUppercase.className = 'bi bi-circle';
                                        }

                                        // Confirm Match
                                        if (confirmPass.value) {
                                            if (newPass.value === confirmPass.value) {
                                                matchMessage.textContent = 'Passwords match / كلمات المرور متطابقة';
                                                matchMessage.className = 'col-span-2 text-xs font-semibold text-green-600';
                                            } else {
                                                matchMessage.textContent = 'Passwords do not match / كلمات المرور غير متطابقة';
                                                matchMessage.className = 'col-span-2 text-xs font-semibold text-red-500';
                                            }
                                        } else {
                                            matchMessage.textContent = '';
                                        }

                                        return isLengthValid && isUppercaseValid;
                                    }

                                    newPass.addEventListener('input', validatePassword);
                                    confirmPass.addEventListener('input', validatePassword);

                                    form.addEventListener('submit', function(e) {
                                        const isValid = validatePassword();
                                        const isMatch = newPass.value === confirmPass.value;

                                        if (!isValid) {
                                            e.preventDefault();
                                            if (newPass.value.length < 8) {
                                                reqLength.classList.add('text-red-500');
                                            }
                                            if (!/[A-Z]/.test(newPass.value)) {
                                                reqUppercase.classList.add('text-red-500');
                                            }
                                            alert('Password does not meet requirements!');
                                        } else if (!isMatch) {
                                            e.preventDefault();
                                            alert('Passwords do not match!');
                                        }
                                    });
                                });
                                </script>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>
    <script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>
