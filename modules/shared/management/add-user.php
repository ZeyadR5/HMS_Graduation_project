<?php
require_once __DIR__ . '/bootstrap.php';

$pageTitle = $pageTitle ?? 'Add New User';
$roleOptions = $roleOptions ?? ['System Admin', 'Admin', 'User'];

if (isset($_POST['Add'])) {
    hms_require_csrf(hms_management_current_path());

    $name = trim($_POST['name'] ?? '');
    $role = trim($_POST['Role'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['Password'] ?? '');

    $connect = hms_management_connect();

    $checkStmt = $connect->prepare("SELECT id FROM employ WHERE email = ? LIMIT 1");
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $existing = $checkStmt->get_result();

    if ($existing->num_rows > 0) {
        $checkStmt->close();
        $connect->close();
        echo "<script>alert('Email already exists!');</script>";
    } elseif ($name === '' || $role === '' || $email === '' || $password === '') {
        $checkStmt->close();
        $connect->close();
        echo "<script>alert('Please fill all fields.');</script>";
    } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password)) {
        $checkStmt->close();
        $connect->close();
        echo "<script>alert('Password must be at least 8 characters long and contain at least one uppercase letter.');</script>";
    } elseif (!in_array($role, $roleOptions, true)) {
        $checkStmt->close();
        $connect->close();
        echo "<script>alert('Selected role is not allowed here.');</script>";
    } else {
        $checkStmt->close();
        $insertStmt = $connect->prepare("
            INSERT INTO employ (`username`, `role`, `email`, `password`)
            VALUES (?, ?, ?, ?)
        ");
        $insertStmt->bind_param("ssss", $name, $role, $email, $password);

        if ($insertStmt->execute()) {
            hms_audit_log($connect, 'employee.created', [
                'description' => 'A new employee account was created.',
                'entity_type' => 'employee',
                'entity_id' => (string)$connect->insert_id,
                'details' => [
                    'username' => $name,
                    'role' => $role,
                    'email' => $email,
                ],
            ]);
            echo "<script>alert('User Added Successfully');</script>";
        } else {
            echo "<script>alert('Something Wrong');</script>";
        }

        $insertStmt->close();
        $connect->close();
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>
<link rel="icon" href="/assets/images/echol.png">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <link rel="stylesheet" href="/assets/css/add-button.css">
</head>
<body>
    <div class="min-h-full">
        <?php $activePage = 'add-user'; require __DIR__ . '/../../../includes/nav.php'; ?>

        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-gray-700"><?= htmlspecialchars($pageTitle) ?></h1>
            </div>
        </header>

        <main>
            <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                <form action="<?= htmlspecialchars(hms_management_current_path()) ?>" method="POST" class="mx-auto max-w-xl sm:mt-20">
                    <?= hms_csrf_field() ?>
                    <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="name" class="block text-sm font-semibold leading-6 text-gray-900">User name</label>
                            <div class="mt-2.5">
                                <input type="text" name="name" id="name" class="block w-full rounded-md border-2 px-3.5 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                            </div>
                        </div>
                        <div class="sm:col-span-2">
                            <div class="mt-2.5">
                                <label for="Role" class="block text-sm font-semibold leading-6 text-gray-900">Role</label>
                                <div class="mt-2">
                                    <select id="Role" name="Role" class="block w-full rounded-md border-2 px-3.5 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                        <?php $defaultRole = end($roleOptions); reset($roleOptions); ?>
                                        <?php foreach ($roleOptions as $roleOption): ?>
                                            <option value="<?= htmlspecialchars($roleOption) ?>" <?= $roleOption === $defaultRole ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($roleOption) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="email" class="block text-sm font-semibold leading-6 text-gray-900">Email</label>
                            <div class="mt-2.5">
                                <input type="email" name="email" id="email" class="block w-full rounded-md border-2 px-3.5 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                            </div>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="Password" class="block text-sm font-semibold leading-6 text-gray-900">Password</label>
                            <div class="mt-2.5 relative">
                                <input type="password" name="Password" id="Password" style="padding-right: 40px;" class="block w-full rounded-md border-2 px-3.5 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" required>
                                <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 toggle-password-btn" data-target="Password" tabindex="-1">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <!-- Password Requirements Checklist -->
                            <div class="mt-3 text-xs">
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
                    </div>
                    <div class="mt-10">
                        <button type="submit" name="Add" class="hms-add-btn">
                            <span class="hms-add-btn__text">Add User</span>
                            <span class="hms-add-btn__icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" viewBox="0 0 24 24" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" stroke="currentColor" height="24" fill="none"><line y2="19" y1="5" x2="12" x1="12"></line><line y2="12" y1="12" x2="19" x1="5"></line></svg></span>
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const passwordInput = document.getElementById('Password');
        const reqLength = document.getElementById('req-length');
        const iconLength = document.getElementById('icon-length');
        const reqUppercase = document.getElementById('req-uppercase');
        const iconUppercase = document.getElementById('icon-uppercase');
        const form = document.querySelector('form');

        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const val = passwordInput.value;
                const isLengthValid = val.length >= 8;
                const isUppercaseValid = /[A-Z]/.test(val);

                if (isLengthValid) {
                    reqLength.classList.remove('text-gray-500', 'text-red-500');
                    reqLength.classList.add('text-green-600');
                    iconLength.className = 'bi bi-check-circle-fill';
                } else {
                    reqLength.classList.remove('text-green-600');
                    reqLength.classList.add('text-gray-500');
                    iconLength.className = 'bi bi-circle';
                }

                if (isUppercaseValid) {
                    reqUppercase.classList.remove('text-gray-500', 'text-red-500');
                    reqUppercase.classList.add('text-green-600');
                    iconUppercase.className = 'bi bi-check-circle-fill';
                } else {
                    reqUppercase.classList.remove('text-green-600');
                    reqUppercase.classList.add('text-gray-500');
                    iconUppercase.className = 'bi bi-circle';
                }
            });

            form.addEventListener('submit', function(e) {
                const val = passwordInput.value;
                if (val.length < 8 || !/[A-Z]/.test(val)) {
                    e.preventDefault();
                    if (val.length < 8) reqLength.classList.add('text-red-500');
                    if (!/[A-Z]/.test(val)) reqUppercase.classList.add('text-red-500');
                    alert('Password does not meet requirements!');
                }
            });
        }

        // Toggle Passwords
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
    });
    </script>
    <script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>
