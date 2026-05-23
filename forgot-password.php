<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/mailer.php';

$connect = hms_db_connect();
$step = 1;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'verify_nat_id') {
        $nat_id = trim($_POST['nat_id'] ?? '');
        if ($nat_id === '') {
            $error = 'National ID is required.';
        } else {
            $stmt = $connect->prepare("SELECT uid, fullName, nat_id, email FROM users WHERE nat_id = ? LIMIT 1");
            $stmt->bind_param("s", $nat_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $user = $res->fetch_assoc();
                $_SESSION['reset_uid'] = $user['uid'];
                $_SESSION['reset_name'] = $user['fullName'];
                $_SESSION['reset_email'] = $user['email'];
                $step = 2;
            } else {
                $error = 'No account found with this National ID.';
            }
            $stmt->close();
        }
    } elseif ($action === 'send_otp') {
        $step = 2;
        $uid = $_SESSION['reset_uid'] ?? 0;
        $email = $_SESSION['reset_email'] ?? '';
        $name = $_SESSION['reset_name'] ?? '';

        if ($uid <= 0) {
            $error = 'Session expired. Please start over.';
            $step = 1;
        } elseif (empty($email)) {
            $error = 'No email address is linked to this account. Please contact reception.';
        } else {
            // Generate OTP (6 digits)
            $otpCode = sprintf("%06d", mt_rand(100000, 999999));
            $otpExpiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            // We use activation_code for both admin code and email OTP
            $upd = $connect->prepare("UPDATE users SET activation_code = ?, activation_expiry = ? WHERE uid = ?");
            $upd->bind_param("ssi", $otpCode, $otpExpiry, $uid);
            
            if ($upd->execute()) {
                if (hms_send_otp_email($email, $otpCode, $name)) {
                    $success = 'An OTP has been sent to your email address.';
                } else {
                    $error = 'Failed to send OTP email. Please check server configuration or use an Activation Code from reception.';
                }
            } else {
                $error = 'Failed to generate OTP.';
            }
            $upd->close();
        }
    } elseif ($action === 'reset_password') {
        $step = 2;
        $code = trim($_POST['activation_code'] ?? '');
        $new_pass = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';
        $uid = $_SESSION['reset_uid'] ?? 0;
        
        if ($uid <= 0) {
            $error = 'Session expired. Please start over.';
            $step = 1;
        } elseif ($code === '') {
            $error = 'Please enter your Activation Code or OTP.';
        } elseif ($new_pass === '' || $new_pass !== $confirm_pass) {
            $error = 'Passwords do not match.';
        } elseif (strlen($new_pass) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            // Check the activation code
            $stmt = $connect->prepare("SELECT activation_code, activation_expiry FROM users WHERE uid = ? LIMIT 1");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $res = $stmt->get_result();
            $userRow = $res->fetch_assoc();
            $stmt->close();
            
            $dbCode = $userRow['activation_code'] ?? null;
            $dbExp = $userRow['activation_expiry'] ?? null;
            
            if ($dbCode !== null && $code === $dbCode && strtotime($dbExp) > time()) {
                $upd = $connect->prepare("UPDATE users SET password = ?, activation_code = NULL, activation_expiry = NULL WHERE uid = ?");
                $upd->bind_param("si", $new_pass, $uid);
                if ($upd->execute()) {
                    $success = 'Password reset successfully! You can now log in.';
                    $step = 3; // Success state
                    unset($_SESSION['reset_uid'], $_SESSION['reset_name'], $_SESSION['reset_email']);
                } else {
                    $error = 'Failed to update password.';
                }
                $upd->close();
            } else {
                $error = 'Invalid or expired Activation Code/OTP.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password - HMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    body { background: #f8fbff; font-family: 'DM Sans', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
    .card { border: none; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.05); overflow: hidden; width: 100%; max-width: 450px; }
    .card-header { background: linear-gradient(135deg, #0ea5e9, #14b8a6); color: white; padding: 30px 20px; text-align: center; border: none; }
    .card-body { padding: 40px 30px; background: white; }
    .form-control { border-radius: 12px; padding: 12px 15px; border: 1px solid #dbeafe; background: #f8fbff; }
    .form-control:focus { border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1); background: white; }
    .btn-primary { border-radius: 12px; padding: 12px; font-weight: bold; background: #0ea5e9; border: none; width: 100%; margin-top: 15px; transition: all 0.2s; }
    .btn-primary:hover { background: #0284c7; transform: translateY(-1px); }
    .alert { border-radius: 12px; font-size: 0.9rem; }
    .back-link { text-align: center; display: block; margin-top: 20px; color: #64748b; text-decoration: none; font-size: 0.9rem; }
    .back-link:hover { color: #0f172a; }
</style>
</head>
<body>

<div class="card">
    <div class="card-header">
        <h3 class="mb-0 font-bold"><i class="bi bi-shield-lock"></i> Account Recovery</h3>
    </div>
    <div class="card-body">
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <p class="text-gray-500 text-sm mb-4">Enter your National ID to begin the password reset process.</p>
            <form method="POST">
                <input type="hidden" name="action" value="verify_nat_id">
                <div class="mb-3">
                    <label class="form-label text-sm font-bold text-gray-700">National ID</label>
                    <input type="text" name="nat_id" class="form-control" placeholder="e.g. 29010101234567" required>
                </div>
                <button type="submit" class="btn btn-primary">Find My Account</button>
            </form>

        <?php elseif ($step === 2): ?>
            <div class="bg-blue-50 p-3 rounded-lg border border-blue-100 mb-4 text-sm text-blue-800">
                Hi, <strong><?= htmlspecialchars($_SESSION['reset_name']) ?></strong>.<br>
                Please enter the OTP sent to your email or the Activation Code obtained from the reception.
            </div>

            <?php if (!empty($_SESSION['reset_email'])): ?>
                <?php 
                    // Obfuscate email
                    $parts = explode('@', $_SESSION['reset_email']);
                    $obfuscated = substr($parts[0], 0, 2) . '***@' . $parts[1];
                ?>
                <form method="POST" class="mb-4 text-center">
                    <input type="hidden" name="action" value="send_otp">
                    <p class="text-xs text-gray-500 mb-2">Have access to your email (<?= htmlspecialchars($obfuscated) ?>)?</p>
                    <button type="submit" class="btn btn-outline-primary btn-sm rounded-pill font-bold" style="border: 2px solid #0ea5e9; color: #0ea5e9;">
                        <i class="bi bi-envelope"></i> Send OTP to Email
                    </button>
                </form>
                <div class="text-center text-gray-400 text-xs mb-4">— OR —</div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="reset_password">
                <div class="mb-3">
                    <label class="form-label text-sm font-bold text-gray-700 text-danger">Activation Code / OTP</label>
                    <input type="text" name="activation_code" class="form-control border-danger" placeholder="6-digit code" required autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="form-label text-sm font-bold text-gray-700">New Password</label>
                    <input type="password" name="new_password" class="form-control" placeholder="At least 6 characters" required>
                </div>
                <div class="mb-3">
                    <label class="form-label text-sm font-bold text-gray-700">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Confirm password" required>
                </div>
                <button type="submit" class="btn btn-primary">Reset Password</button>
            </form>
        <?php elseif ($step === 3): ?>
            <div class="text-center">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                <h4 class="mt-3">All Done!</h4>
                <p class="text-gray-500 text-sm">Your password has been successfully reset.</p>
                <a href="/index.php" class="btn btn-primary mt-2">Go to Login</a>
            </div>
        <?php endif; ?>

        <?php if ($step !== 3): ?>
            <a href="/index.php" class="back-link"><i class="bi bi-arrow-left"></i> Back to Login</a>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
