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
$userId = intval($_SESSION['id']);
$msg = "";
$error = "";

if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($new_password !== $confirm_password) {
        $error = "New password and Confirm password do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters.";
    } else {
        if ($role === 'Doctor') {
            $stmt = $connect->prepare("SELECT password FROM doctors WHERE id = ?");
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="icon" href="/assets/images/echol.png">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <style>
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
                            <?php 
                                $nameParts = explode(' ', $_SESSION['username']);
                                $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
                            ?>
                            <div class="flex justify-center mb-4">
                                <div class="profile-avatar">
                                    <?= htmlspecialchars($initials) ?>
                                </div>
                            </div>
                            <h2 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($_SESSION['username']) ?></h2>
                            <p class="text-sm font-semibold text-indigo-600 mb-1"><?= htmlspecialchars($_SESSION['role']) ?></p>
                            <p class="text-sm text-gray-500 mb-6"><?= htmlspecialchars($_SESSION['login']) ?></p>

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

                                <form class="space-y-6" method="POST" action="">
                                    <div class="grid grid-cols-3 gap-4 items-center">
                                        <label for="current_password" class="block text-sm font-medium text-gray-700 col-span-1 text-right pr-4">Current Password</label>
                                        <div class="col-span-2">
                                            <input type="password" name="current_password" id="current_password" required class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md border p-2.5">
                                        </div>
                                    </div>

                                    <div class="border-t border-gray-100 pt-6"></div>

                                    <div class="grid grid-cols-3 gap-4 items-center">
                                        <label for="new_password" class="block text-sm font-medium text-gray-700 col-span-1 text-right pr-4">New Password</label>
                                        <div class="col-span-2">
                                            <input type="password" name="new_password" id="new_password" required class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md border p-2.5">
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-3 gap-4 items-center mt-4">
                                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 col-span-1 text-right pr-4">Confirm Password</label>
                                        <div class="col-span-2">
                                            <input type="password" name="confirm_password" id="confirm_password" required class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md border p-2.5">
                                        </div>
                                    </div>

                                    <div class="pt-4 flex justify-end">
                                        <button type="submit" name="change_password" class="inline-flex items-center justify-center px-6 py-2.5 border border-transparent font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm shadow-sm transition-colors duration-200">
                                            <i class="bi bi-shield-lock mr-2"></i> Update Password
                                        </button>
                                    </div>
                                </form>
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
