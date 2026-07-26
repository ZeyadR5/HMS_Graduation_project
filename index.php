<?php
date_default_timezone_set('Africa/Cairo');
define('HMS_SKIP_AUTO_CONNECT', true);
require_once __DIR__ . '/includes/config.php';
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
require_once __DIR__ . '/includes/audit.php';

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("location: ./modules/dashboard.php");
    exit();
}

$error        = "";
$signup_error = "";
$signup_ok    = "";
$showRegister = false;

$connect = hms_db_connect();

function hms_index_csrf_token(): string
{
    if (empty($_SESSION['index_csrf_token'])) {
        $_SESSION['index_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['index_csrf_token'];
}

function hms_index_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(hms_index_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function hms_index_csrf_valid(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token)
        && isset($_SESSION['index_csrf_token'])
        && hash_equals($_SESSION['index_csrf_token'], $token);
}

// ── SIGN IN ──────────────────────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $role       = $_POST['role']       ?? '';
    $password   = $_POST['password']   ?? '';
    $identifier = trim($_POST['identifier'] ?? '');
    if (!hms_index_csrf_valid()) {
        $error = "Security check failed. Please try again.";
    } else {
    $now = time();

    $_SESSION['login_attempts'] = array_values(array_filter(
        $_SESSION['login_attempts'] ?? [],
        fn($attemptTime) => is_int($attemptTime) && ($now - $attemptTime) < 900
    ));

    if (count($_SESSION['login_attempts']) >= 10) {
        $error = "Too many login attempts. Please try again later.";
    } else {

    $stmt = $connect->prepare("SELECT * FROM `employ` WHERE `email` = ? AND `password` = ?");
    $stmt->bind_param("ss", $identifier, $password);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row  = $result->fetch_assoc();
        if ((int)$row['is_active'] === 0) {
            $error = "Your account has been disabled. Please contact the administrator.";
        } else {
            $date = date("Y-m-d");
            session_regenerate_id(true);
            $_SESSION['login_attempts'] = [];
            $_SESSION['logged_in'] = true;
            $_SESSION['role']      = $row['role'];
            $_SESSION['login']     = $identifier;
            $_SESSION['id']        = $row['id'];
            $_SESSION['username']  = $row['username'];
            $connect->query("UPDATE employ SET employ_statue = 1 , updationDate = '$date' WHERE id = " .$row['id']);
            hms_audit_log($connect, 'auth.login.success', [
                'entity_type' => 'auth',
                'entity_id' => strtolower(str_replace(' ', '-', $row['role'])),
                'description' => 'Successful login',
                'details' => [
                    'role' => $row['role'],
                    'identifier' => $identifier,
                ],
            ]);
            header("location: ./modules/dashboard.php");
            exit();
        }
    }

    $stmt = $connect->prepare("SELECT * FROM `doctors` WHERE `docEmail` = ? AND `password` = ?");
    $stmt->bind_param("ss", $identifier, $password);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ((int)$row['is_active'] === 0) {
            $error = "Your account has been disabled. Please contact the administrator.";
        } else {
            session_regenerate_id(true);
            $_SESSION['login_attempts'] = [];
            $_SESSION['logged_in'] = true;
            $_SESSION['role']      = 'Doctor';
            $_SESSION['login']     = $identifier;
            $_SESSION['id']        = $row['id'];
            $_SESSION['username']  = $row['doctorName'];
            $connect->query("UPDATE doctors SET statue = 1 WHERE id = " . intval($row['id']));
            hms_audit_log($connect, 'auth.login.success', [
                'entity_type' => 'auth',
                'entity_id' => 'doctor',
                'description' => 'Successful login',
                'details' => [
                    'role' => 'Doctor',
                    'identifier' => $identifier,
                ],
            ]);
            header("location: ./modules/dashboard.php"); exit();
        }
    }

    $stmt = $connect->prepare("SELECT * FROM `users` WHERE (`nat_id` = ? OR `email` = ?) AND `password` = ?");
    $stmt->bind_param("sss", $identifier, $identifier, $password);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        session_regenerate_id(true);
        $_SESSION['login_attempts'] = [];
        $_SESSION['logged_in'] = true;
        $_SESSION['role']      = 'Patient';
        $_SESSION['login']     = $identifier;
        $_SESSION['uid']       = $row['uid'];
        $_SESSION['username']  = $row['fullName'];
        hms_audit_log($connect, 'auth.login.success', [
            'entity_type' => 'auth',
            'entity_id' => 'patient',
            'description' => 'Successful login',
            'details' => [
                'role' => 'Patient',
                'identifier' => $identifier,
            ],
        ]);
        header("location: ./modules/dashboard.php"); exit();
    }

    hms_audit_log($connect, 'auth.login.failed', [
        'entity_type' => 'auth',
        'entity_id' => 'unknown',
        'description' => 'Failed login attempt',
        'details' => [
            'identifier' => $identifier,
        ],
    ]);
    $_SESSION['login_attempts'][] = $now;
    $error = "Email/National ID or Password is incorrect.";
    }
    }
}

// ── SIGN UP (Patient only) ────────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'register') {
    $showRegister = true;
    $fullName  = trim($_POST['fullName']   ?? '');
    $email     = trim($_POST['reg_email']  ?? '');
    $nat_id    = trim($_POST['nat_id']     ?? '');
    $pass1     = $_POST['reg_pass']        ?? '';
    $pass2     = $_POST['reg_pass2']       ?? '';
    $gender    = trim($_POST['gender']     ?? '');
    $age       = intval($_POST['age']      ?? 0);

    if (!hms_index_csrf_valid()) {
        $signup_error = "Security check failed. Please try again.";
    } elseif ($fullName === '' || $email === '' || $nat_id === '') {
        $signup_error = "Please fill all required fields.";
    } elseif (strlen($fullName) < 3) {
        $signup_error = "Full Name must be at least 3 characters.";
    } elseif (!preg_match('/^[0-9]{14}$/', $nat_id)) {
        $signup_error = "National ID must be exactly 14 digits and contain only numbers.";
    } elseif ($age <= 0 || $age > 150) {
        $signup_error = "Age must be a valid number between 1 and 150.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $signup_error = "Invalid email format.";
    } elseif ($pass1 !== $pass2) {
        $signup_error = "Passwords do not match.";
    } elseif (strlen($pass1) < 8 || !preg_match('/[A-Z]/', $pass1)) {
        $signup_error = "Password must be at least 8 characters long and contain at least one uppercase letter.";
    } else {
        // Check whether email already belongs to another record
        $chkEmail = $connect->prepare("SELECT uid, nat_id, email, password FROM `users` WHERE `email` = ? LIMIT 1");
        $chkEmail->bind_param("s", $email);
        $chkEmail->execute();
        $emailRes = $chkEmail->get_result();
        $emailRow = $emailRes->fetch_assoc();
        $chkEmail->close();

        // Check whether national ID already exists
        $chkNat = $connect->prepare("SELECT uid, fullName, nat_id, email, password, PatientContno, gender, p_age, activation_code, activation_expiry FROM `users` WHERE `nat_id` = ? LIMIT 1");
        $chkNat->bind_param("s", $nat_id);
        $chkNat->execute();
        $natRes = $chkNat->get_result();
        $natRow = $natRes->fetch_assoc();
        $chkNat->close();

        // If email exists in another different record, reject
        if ($emailRow && (!$natRow || intval($emailRow['uid']) !== intval($natRow['uid']))) {
            $signup_error = "This email is already registered.";
        } else {
            $newPassword = $pass1;

            if ($natRow) {
                $existingUid      = intval($natRow['uid']);
                $existingEmail    = trim($natRow['email'] ?? '');
                $existingPassword = trim($natRow['password'] ?? '');
                $existingActCode  = $natRow['activation_code'] ?? null;
                $existingActExp   = $natRow['activation_expiry'] ?? null;

                // Whether it's a real account or an offline hospital record, require an activation code to link it.
                $submittedCode = trim($_POST['activation_code'] ?? '');
                
                if ($submittedCode === '') {
                    $signup_error = "This National ID is already registered in our system. To link this account securely, please enter the Activation Code obtained from the reception.";
                    $showActivationCodeField = true;
                } else {
                    if (!isset($_SESSION['activation_attempts'])) {
                        $_SESSION['activation_attempts'] = 0;
                    }

                    if ($_SESSION['activation_attempts'] >= 5) {
                        $signup_error = "Too many failed attempts. Please contact reception to request a new Activation Code.";
                        $showActivationCodeField = true;
                    } else {
                        if ($existingActCode !== null && hash('sha256', $submittedCode) === $existingActCode && strtotime($existingActExp) > time()) {
                            // Valid Code! Update account
                            $upd = $connect->prepare("
                                UPDATE `users`
                                SET `fullName` = ?,
                                    `email`    = ?,
                                    `password` = ?,
                                    `gender`   = CASE WHEN ? <> '' THEN ? ELSE `gender` END,
                                    `p_age`    = CASE WHEN ? > 0 THEN ? ELSE `p_age` END,
                                    `activation_code` = NULL,
                                    `activation_expiry` = NULL
                                WHERE `uid` = ?
                            ");
                            $upd->bind_param("sssssiii", $fullName, $email, $newPassword, $gender, $gender, $age, $age, $existingUid);
                            
                            if ($upd->execute()) {
                                hms_audit_log($connect, 'auth.account.recovered', [
                                    'entity_type' => 'user',
                                    'entity_id' => (string)$existingUid,
                                    'description' => 'Account recovered or activated using Activation Code',
                                ]);
                                $signup_ok = "Account activated successfully! You can now sign in.";
                                $showActivationCodeField = false;
                                unset($_SESSION['activation_attempts']);
                                $showRegister = false;
                            } else {
                                $signup_error = "Failed to update account. Please try again.";
                                $showActivationCodeField = true;
                            }
                            $upd->close();
                        } else {
                            $_SESSION['activation_attempts']++;
                            $remaining = 5 - $_SESSION['activation_attempts'];
                            if ($remaining <= 0) {
                                $signup_error = "Too many failed attempts. Please contact reception to request a new Activation Code.";
                            } else {
                                $signup_error = "Invalid or expired Activation Code. Remaining attempts: " . $remaining;
                            }
                            $showActivationCodeField = true;
                        }
                    }
                }
            } else {
                // Completely new patient
                $ins = $connect->prepare("
                    INSERT INTO `users`
                    (`fullName`,`email`,`nat_id`,`password`,`gender`,`p_age`,`PatientContno`,`PatientMedhis`)
                    VALUES (?,?,?,?,?,?,0,'')
                ");
                $ins->bind_param("sssssi", $fullName, $email, $nat_id, $newPassword, $gender, $age);

                if ($ins->execute()) {
                    $newUserId = $connect->insert_id;
                    hms_audit_log($connect, 'auth.register.success', [
                        'entity_type' => 'user',
                        'entity_id' => (string)$newUserId,
                        'description' => 'New patient account created',
                        'details' => [
                            'nat_id' => $nat_id,
                            'email' => $email,
                        ],
                    ]);
                    $signup_ok = "Account created! You can now sign in.";
                    $showRegister = false;
                } else {
                    hms_audit_log($connect, 'auth.register.failed', [
                        'entity_type' => 'user',
                        'entity_id' => 'new',
                        'description' => 'Failed to create patient account',
                        'details' => [
                            'nat_id' => $nat_id,
                            'email' => $email,
                        ],
                    ]);
                    $signup_error = "Registration failed. Please try again.";
                }
                $ins->close();
            }
        }
    }
}

$sel = $_POST['role'] ?? 'Patient';
$showRegister = isset($_POST['action']) && $_POST['action'] === 'register';
if (isset($signup_ok)) {
    $showRegister = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HMS - Portal</title>
<link rel="icon" href="./assets/images/echol.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
input[type="password"]::-ms-reveal, input[type="password"]::-ms-clear { display: none; }

:root {
  --blue:    #2d7dd2;
  --blue2:   #1a5fad;
  --light:   #f0f4f8;
  --muted:   #8a9ab5;
  --dark:    #1c2637;
  --white:   #ffffff;
  --panel-w: 42%;
  --radius:  28px;
  --speed:   0.65s;
}

body {
  font-family: 'DM Sans', sans-serif;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #dde8f5;
  background: radial-gradient(ellipse at 20% 80%, #c5d8f0 0%, #dde8f5 50%, #e8eef7 100%);
  padding: 20px;
}

/* ── WRAPPER ── */
.wrapper {
  position: relative;
  width: 960px;
  max-width: 100%;
  height: 650px; /* Increased from 620px to prevent scrolling */
  background: var(--white);
  border-radius: var(--radius);
  box-shadow: 0 32px 80px rgba(28,38,55,0.18), 0 8px 24px rgba(28,38,55,0.08);
  overflow: hidden;
}

/*
  ANIMATION STRATEGY:
  - Panel   : absolute, left:0,   width:42%  → slides RIGHT by 58% of WRAPPER
  - signin  : absolute, left:42%, width:58%  → slides LEFT  by 58% of WRAPPER
  - signup  : absolute, right:0,  width:58%  → starts translated right, slides to 0

  KEY: use vw or px won't work — we use a CSS custom property set by JS
  to avoid % ambiguity (% in translateX = % of element itself, not wrapper)
*/

/* ── PANEL ── */
.panel {
  position: absolute;
  top: 0; left: 0;
  width: 42%;
  height: 100%;
  border-radius: var(--radius) 0 0 var(--radius);
  overflow: hidden;
  transition: left var(--speed) cubic-bezier(.77,0,.18,1),
              border-radius var(--speed) cubic-bezier(.77,0,.18,1);
  z-index: 10;
}
.wrapper.active .panel {
  left: 58%;
  border-radius: 0 var(--radius) var(--radius) 0;
}

/* ── FORMS ── */
.form-box {
  position: absolute;
  top: 0;
  height: 100%;
  width: 58%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  padding: 20px 40px; /* Reduced vertical padding to save space */
  transition: left var(--speed) cubic-bezier(.77,0,.18,1),
              opacity calc(var(--speed)*0.4) ease;
  z-index: 1;
  overflow-y: auto;
  overflow-x: hidden;
}

/* Custom modern scrollbar for the form boxes */
.form-box::-webkit-scrollbar {
  width: 4px;
}
.form-box::-webkit-scrollbar-track {
  background: transparent;
}
.form-box::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}
.form-box::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

/* signin: sits on the right, visible */
.form-box.signin {
  left: 42%;
  opacity: 1;
  pointer-events: all;
}

/* signup: hidden off the right edge */
.form-box.signup {
  left: 100%;
  opacity: 0;
  pointer-events: none;
}

/* ACTIVE */
.wrapper.active .form-box.signin {
  left: -58%;
  opacity: 0;
  pointer-events: none;
}
.wrapper.active .form-box.signup {
  left: 0;
  opacity: 1;
  pointer-events: all;
}

.panel-img {
  position: absolute;
  inset: 0;
  background: url('./assets/images/patient-landing/doctor.jpg') center/cover no-repeat,
              url('./assets/images/doctor_2732252.png') center/cover no-repeat;
  filter: brightness(.75) saturate(1.1);
}

.panel-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(160deg,
    rgba(13,30,70,0.55) 0%,
    rgba(29,95,180,0.4) 60%,
    rgba(13,30,70,0.7) 100%);
}

.panel-content {
  position: relative;
  z-index: 1;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 36px 32px;
  color: #fff;
}

.panel-logo {
  display: flex;
  align-items: center;
  gap: 10px;
}
.panel-logo img { width: 36px; height: 36px; object-fit: contain; filter: brightness(10); }
.panel-logo span { font-family: 'Playfair Display', serif; font-size: 17px; font-weight: 700; letter-spacing: .3px; }

.panel-main { text-align: center; }
.panel-main h2 {
  font-family: 'Playfair Display', serif;
  font-size: 30px;
  font-weight: 700;
  line-height: 1.2;
  margin-bottom: 12px;
  text-shadow: 0 2px 12px rgba(0,0,0,.3);
}
.panel-main p {
  font-size: 13.5px;
  color: rgba(255,255,255,.75);
  line-height: 1.6;
  margin-bottom: 28px;
}

.panel-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 11px 28px;
  border: 2px solid rgba(255,255,255,.8);
  border-radius: 50px;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: .8px;
  text-transform: uppercase;
  cursor: pointer;
  background: transparent;
  transition: background .25s, border-color .25s;
}
.panel-btn:hover { background: rgba(255,255,255,.15); border-color: #fff; }

.panel-footer { font-size: 11.5px; color: rgba(255,255,255,.4); text-align: center; }

/* ── FORM INNER ── */
.form-inner { width: 100%; max-width: 340px; margin: auto 0; }
.form-inner h1 {
  font-family: 'Playfair Display', serif;
  font-size: 28px;
  color: var(--dark);
  margin-bottom: 6px;
}
.form-inner .sub {
  font-size: 13px;
  color: var(--muted);
  margin-bottom: 24px;
}

/* Role pills */
.roles-label { font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .6px; margin-bottom: 9px; }
.roles {
  display: flex;
  flex-wrap: wrap;
  gap: 7px;
  margin-bottom: 22px;
}
.role-btn {
  flex: 1 1 auto;
  min-width: 70px;
  padding: 8px 6px;
  border-radius: 10px;
  border: 1.5px solid #dce5f0;
  background: var(--light);
  color: var(--muted);
  font-size: 11.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all .22s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
}
.role-btn i { font-size: 12px; }
.role-btn:hover { border-color: var(--blue); color: var(--blue); background: #edf4fc; }
.role-btn.active {
  border-color: var(--blue);
  background: linear-gradient(135deg,#eaf2fd,#d9eafa);
  color: var(--blue);
  box-shadow: 0 3px 10px rgba(45,125,210,.15);
}
.role-btn.super { flex: 1 1 100%; }

/* Fields */
.field { margin-bottom: 13px; position: relative; }
.field label { display: block; font-size: 11.5px; font-weight: 600; color: var(--dark); margin-bottom: 6px; letter-spacing: .3px; }
.field .ico { position: absolute; left: 13px; bottom: 13px; color: var(--muted); font-size: 13px; pointer-events:none; }
.field input, .field select {
  width: 100%;
  padding: 11px 14px 11px 38px;
  background: var(--light);
  border: 1.5px solid #dce5f0;
  border-radius: 10px;
  color: var(--dark);
  font-size: 13.5px;
  font-family: 'DM Sans', sans-serif;
  outline: none;
  transition: border-color .2s, background .2s, box-shadow .2s;
  appearance: none;
}
.field input::placeholder { color: #b0bdd0; }
.field input:focus, .field select:focus {
  border-color: var(--blue);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(45,125,210,.1);
}

/* Two-col fields */
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

/* Submit */
.btn-submit {
  width: 100%; padding: 13px;
  background: linear-gradient(135deg, var(--blue), var(--blue2));
  border: none; border-radius: 11px;
  color: #fff; font-size: 14px; font-weight: 700;
  font-family: 'DM Sans', sans-serif;
  letter-spacing: .3px; cursor: pointer; margin-top: 6px;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: transform .15s, box-shadow .2s, opacity .2s;
  box-shadow: 0 6px 20px rgba(45,125,210,.3);
}
.btn-submit:hover  { transform: translateY(-1px); box-shadow: 0 10px 28px rgba(45,125,210,.38); }
.btn-submit:active { transform: translateY(0); }

/* Alert */
.alert {
  padding: 10px 13px; border-radius: 9px;
  font-size: 12.5px; margin-bottom: 14px;
  display: flex; align-items: center; gap: 8px;
}
.alert.err  { background:#fef0ef; border:1px solid #fbbdb9; color:#c0392b; }
.alert.ok   { background:#edfaf3; border:1px solid #a3e6c0; color:#1a7a4a; }

/* divider */
.divider { display:flex; align-items:center; gap:10px; margin:16px 0 12px; }
.divider span { font-size:11px; color:#c0cdd8; white-space:nowrap; }
.divider::before,.divider::after { content:''; flex:1; height:1px; background:#dce5f0; }

/* ── RESPONSIVE ── */
@media(max-width: 680px) {
  body { padding: 0; }
  .wrapper {
    height: auto;
    min-height: 100vh;
    border-radius: 0;
    width: 100%;
    overflow-x: hidden;
  }
  .panel { display: none; }
  .form-box {
    position: relative !important;
    left: 0 !important;
    width: 100% !important;
    transform: none !important;
    opacity: 1 !important;
    padding: 32px 20px;
    box-sizing: border-box;
  }
  .form-box.signup { display: none; pointer-events: none; }
  .wrapper.active .form-box.signin  { display: none; }
  .wrapper.active .form-box.signup  { display: flex; }
  .form-inner {
    max-width: 100% !important;
    width: 100% !important;
  }
  .roles {
    gap: 6px;
  }
  .role-btn {
    min-width: 0;
    padding: 8px 4px;
    font-size: 11px;
  }
  .role-btn.super {
    flex: 1 1 100%;
  }
  .field input, .field select {
    font-size: 14px;
    padding: 10px 12px 10px 36px;
  }
  .field-row {
    grid-template-columns: 1fr 1fr;
    gap: 8px;
  }
  .btn-submit {
    font-size: 13.5px;
    padding: 12px;
  }
  .form-inner h1 {
    font-size: 24px;
  }
}
</style>

<link rel="stylesheet" href="/assets/css/responsive.css">

    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>
</head>
<body>

<div class="wrapper <?= $showRegister ? 'active' : '' ?>" id="wrapper">

  <!-- ── IMAGE PANEL ── -->
  <div class="panel" id="panel">
    <div class="panel-img"></div>
    <div class="panel-overlay"></div>
    <div class="panel-content">

      <div class="panel-logo">
        <img src="./assets/images/echol.png" alt="" onerror="this.style.display='none'">
        <span>HMS Portal</span>
      </div>

      <div class="panel-main" id="panel-main">
        <h2 id="panel-title">Welcome<br>Back!</h2>
        <p id="panel-desc">Sign in with your credentials<br>to access your dashboard.</p>
        <button class="panel-btn" id="panel-cta" onclick="togglePanel()">
          <i class="fa fa-user-plus" id="panel-icon"></i>
          <span id="panel-cta-text">Register</span>
        </button>
      </div>

      <div class="panel-footer">Hospital Management System © 2026</div>
    </div>
  </div>

  <!-- ── SIGN IN FORM ── -->
  <div class="form-box signin">
    <div class="form-inner">
      <h1>Sign In</h1>
      <p class="sub">Enter your credentials to access your dashboard</p>

      <?php if ($error): ?>
      <div class="alert err"><i class="fa fa-circle-exclamation"></i><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if (isset($signup_ok) && $signup_ok): ?>
      <div class="alert ok"><i class="fa fa-circle-check"></i><?= htmlspecialchars($signup_ok) ?></div>
      <?php endif; ?>

      <form method="POST" id="login-form">
        <?= hms_index_csrf_field() ?>
        <input type="hidden" name="action" value="login">

        <div class="field">
          <label id="id-label">Email / National ID</label>
          <i class="fa fa-user ico" id="id-icon"></i>
          <input type="text" name="identifier" id="identifier"
                 placeholder="Enter Email or National ID"
                 value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                 autocomplete="username" required>
        </div>

        <div class="field">
          <label>Password</label>
          <i class="fa fa-lock ico"></i>
          <input type="password" name="password" id="loginPassword" placeholder="Enter your password"
                 autocomplete="current-password" style="padding-right: 40px;" required>
          <button type="button" class="toggle-password-btn" data-target="loginPassword" style="position: absolute; right: 12px; bottom: 11px; background: none; border: none; cursor: pointer; color: var(--muted); padding: 0; z-index: 10;" tabindex="-1">
              <i class="fa fa-eye"></i>
          </button>
        </div>
        
        <div style="text-align: right; margin-top: -5px; margin-bottom: 15px;">
            <a href="/forgot-password.php" style="color: var(--blue); font-size: 0.8rem; font-weight: 600; text-decoration: none;">Forgot Password?</a>
        </div>

        <button type="submit" class="btn-submit">
          <i class="fa fa-right-to-bracket"></i> Sign In
        </button>
      </form>

      <div class="divider"><span>New patient?</span></div>
      <button class="btn-submit" onclick="togglePanel()"
              style="background:transparent;color:var(--blue);box-shadow:none;border:1.5px solid var(--blue);margin-top:0;">
        <i class="fa fa-user-plus"></i> Create Account
      </button>
    </div>
  </div>

  <!-- ── SIGN UP FORM ── -->
  <div class="form-box signup">
    <div class="form-inner">
      <h1>Create Account</h1>
      <p class="sub">Register as a new patient</p>

      <?php if ($signup_error): ?>
      <div class="alert err"><i class="fa fa-circle-exclamation"></i><?= htmlspecialchars($signup_error) ?></div>
      <?php endif; ?>
      <?php if ($signup_ok): ?>
      <div class="alert ok"><i class="fa fa-circle-check"></i><?= htmlspecialchars($signup_ok) ?></div>
      <?php endif; ?>

      <form method="POST" id="reg-form">
        <?= hms_index_csrf_field() ?>
        <input type="hidden" name="action" value="register">

        <div class="field">
          <label>Full Name</label>
          <i class="fa fa-user ico"></i>
          <input type="text" name="fullName" placeholder="Patient Name" required minlength="3"
                 value="<?= htmlspecialchars($_POST['fullName'] ?? '') ?>">
        </div>

        <div class="field-row">
          <div class="field">
            <label>Age</label>
            <i class="fa fa-hourglass-half ico"></i>
            <input type="number" name="age" id="age" placeholder="Age" min="1" max="150" required
                   oninput="if(this.value.length > 3) this.value = this.value.slice(0,3);"
                  value="<?= htmlspecialchars($_POST['age'] ?? '') ?>">
          </div>

          <div class="field">
            <label>Gender</label>
            <i class="fa fa-venus-mars ico"></i>
            <select name="gender" id="gender" required>
              <option value="">Select Gender</option>
              <option value="Male" <?= (($_POST['gender'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option>
              <option value="Female" <?= (($_POST['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
            </select>
          </div>
        </div>

        <div class="field-row">
          <div class="field">
            <label>National ID</label>
            <i class="fa fa-id-card ico"></i>
            <input type="text" name="nat_id" placeholder="National ID" required
                   pattern="[0-9]{14}" maxlength="14" minlength="14" title="National ID must be exactly 14 digits / يجب أن يكون الرقم القومي مكوناً من 14 رقماً" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                   value="<?= htmlspecialchars($_POST['nat_id'] ?? '') ?>">
          </div>

          <div class="field">
            <label>Email</label>
            <i class="fa fa-envelope ico"></i>
            <input type="email" name="reg_email" placeholder="your@email.com" required
                   value="<?= htmlspecialchars($_POST['reg_email'] ?? '') ?>">
          </div>
        </div>

        <div class="field-row">
          <div class="field">
            <label>Password</label>
            <i class="fa fa-lock ico"></i>
            <input type="password" name="reg_pass" id="pass1" placeholder="Password" style="padding-right: 40px;" required>
            <button type="button" class="toggle-password-btn" data-target="pass1" style="position: absolute; right: 12px; bottom: 11px; background: none; border: none; cursor: pointer; color: var(--muted); padding: 0; z-index: 10;" tabindex="-1">
                <i class="fa fa-eye"></i>
            </button>
          </div>
          <div class="field">
            <label>Confirm Password</label>
            <i class="fa fa-lock ico"></i>
            <input type="password" name="reg_pass2" id="pass2" placeholder="Confirm" style="padding-right: 40px;" required>
            <button type="button" class="toggle-password-btn" data-target="pass2" style="position: absolute; right: 12px; bottom: 11px; background: none; border: none; cursor: pointer; color: var(--muted); padding: 0; z-index: 10;" tabindex="-1">
                <i class="fa fa-eye"></i>
            </button>
          </div>
        </div>

        <!-- Password Requirements Checklist -->
        <div class="field" style="margin-top: -5px; margin-bottom: 15px;">
            <div style="background: var(--light); padding: 10px 14px; border: 1.5px solid #dce5f0; border-radius: 10px; font-size: 0.72rem; color: var(--muted); text-align: left;">
                <div style="font-weight: 600; color: var(--dark); margin-bottom: 4px;">Password requirements / شروط كلمة المرور:</div>
                <div id="req-length" style="display: flex; align-items: center; gap: 6px; margin-bottom: 3px; transition: color 0.2s;">
                    <i class="fa fa-circle" id="icon-length" style="font-size: 0.65rem;"></i> At least 8 characters / 8 حروف على الأقل
                </div>
                <div id="req-uppercase" style="display: flex; align-items: center; gap: 6px; transition: color 0.2s;">
                    <i class="fa fa-circle" id="icon-uppercase" style="font-size: 0.65rem;"></i> At least 1 uppercase letter / حرف كابيتال واحد على الأقل
                </div>
            </div>
            <div id="matchMessage" style="font-size: 0.72rem; font-weight: 600; margin-top: 5px; text-align: left;"></div>
        </div>

        <?php if (isset($showActivationCodeField) && $showActivationCodeField): ?>
        <div class="field">
          <label style="color: #dc2626;">Activation Code (Required)</label>
          <i class="fa fa-shield-halved ico" style="color: #dc2626;"></i>
          <input type="text" name="activation_code" placeholder="Enter 6-digit code" autocomplete="off" style="border-color: #fca5a5;">
        </div>
        <p style="font-size: 0.75rem; color: #dc2626; margin-bottom: 10px; margin-top: -5px; line-height: 1.2;">* This National ID is already registered. To link this new device/email, please enter the Activation Code obtained from the reception.</p>
        <?php endif; ?>

        <button type="submit" class="btn-submit">
          <i class="fa fa-user-plus"></i> Sign Up
        </button>
      </form>

      <div class="divider"><span>Already have an account?</span></div>
      <button class="btn-submit" onclick="togglePanel()"
              style="background:transparent;color:var(--blue);box-shadow:none;border:1.5px solid var(--blue);margin-top:0;">
        <i class="fa fa-right-to-bracket"></i> Sign In
      </button>
    </div>
  </div>

</div><!-- /wrapper -->

<script>
const wrapper  = document.getElementById('wrapper');
const idLabel  = document.getElementById('id-label');
const idIcon   = document.getElementById('id-icon');
const identInp = document.getElementById('identifier');

const panelStates = {
  login: {
    title: 'Welcome<br>Back!',
    desc:  'Sign in with your credentials<br>to access your dashboard.',
    cta:   'Register',
    icon:  'fa-user-plus',
  },
  register: {
    title: 'Hello,<br>Friend!',
    desc:  'Register with your personal details<br>to use all site features.',
    cta:   'Sign In',
    icon:  'fa-right-to-bracket',
  }
};

function updatePanel(state) {
  const s = panelStates[state];
  document.getElementById('panel-title').innerHTML      = s.title;
  document.getElementById('panel-desc').innerHTML       = s.desc;
  document.getElementById('panel-cta-text').textContent = s.cta;
  const ico = document.getElementById('panel-icon');
  ico.className = 'fa ' + s.icon;
}

function togglePanel() {
  const isActive = wrapper.classList.toggle('active');
  updatePanel(isActive ? 'register' : 'login');
}



if (wrapper.classList.contains('active')) updatePanel('register');

// Toggle password handler
document.addEventListener('click', function(e) {
  const btn = e.target.closest('.toggle-password-btn');
  if (btn) {
    const targetId = btn.getAttribute('data-target');
    const input = document.getElementById(targetId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
      input.type = 'text';
      icon.className = 'fa fa-eye-slash';
    } else {
      input.type = 'password';
      icon.className = 'fa fa-eye';
    }
  }
});

// Dynamic checklist & matching verification
const pass1 = document.getElementById('pass1');
const pass2 = document.getElementById('pass2');
const reqLength = document.getElementById('req-length');
const iconLength = document.getElementById('icon-length');
const reqUppercase = document.getElementById('req-uppercase');
const iconUppercase = document.getElementById('icon-uppercase');
const matchMessage = document.getElementById('matchMessage');
const regForm = document.getElementById('reg-form');

if (pass1 && pass2) {
  function validate() {
    const val = pass1.value;
    const isLengthValid = val.length >= 8;
    const isUppercaseValid = /[A-Z]/.test(val);

    if (isLengthValid) {
      reqLength.style.color = '#27ae60';
      iconLength.className = 'fa fa-circle-check';
    } else {
      reqLength.style.color = '';
      iconLength.className = 'fa fa-circle';
    }

    if (isUppercaseValid) {
      reqUppercase.style.color = '#27ae60';
      iconUppercase.className = 'fa fa-circle-check';
    } else {
      reqUppercase.style.color = '';
      iconUppercase.className = 'fa fa-circle';
    }

    if (pass2.value) {
      if (pass1.value === pass2.value) {
        matchMessage.textContent = 'Passwords match / كلمات المرور متطابقة';
        matchMessage.style.color = '#27ae60';
        pass2.style.borderColor = '#27ae60';
      } else {
        matchMessage.textContent = 'Passwords do not match / كلمات المرور غير متطابقة';
        matchMessage.style.color = '#e74c3c';
        pass2.style.borderColor = '#e74c3c';
      }
    } else {
      matchMessage.textContent = '';
      pass2.style.borderColor = '';
    }

    return isLengthValid && isUppercaseValid;
  }

  pass1.addEventListener('input', validate);
  pass2.addEventListener('input', validate);

  if (regForm) {
    regForm.addEventListener('submit', function(e) {
      const isValid = validate();
      const isMatch = pass1.value === pass2.value;

      if (!isValid) {
        e.preventDefault();
        alert('Password does not meet requirements!');
      } else if (!isMatch) {
        e.preventDefault();
        alert('Passwords do not match!');
      }
    });
  }
}
</script>

<script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>

