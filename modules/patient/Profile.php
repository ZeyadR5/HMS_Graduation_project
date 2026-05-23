<?php
require_once __DIR__ . '/../../includes/auth.php';

ini_set('display_errors', '0');

$connect = hms_db_connect();
if ($connect->connect_error) {
    die('Connection failed: ' . $connect->connect_error);
}
$connect->set_charset('utf8mb4');

$role = $_SESSION['role'] ?? '';
$activePage = 'profile';
$sessionUid = (int)($_SESSION['uid'] ?? 0);

if ($role !== 'Patient' || $sessionUid <= 0) {
    header('Location: /modules/dashboard.php');
    exit();
}

function hms_column_exists(mysqli $connect, string $table, string $column): bool
{
    $table = $connect->real_escape_string($table);
    $column = $connect->real_escape_string($column);
    $sql = "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'";
    $result = $connect->query($sql);

    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function hms_fix_mojibake(?string $value): string
{
    $value = (string)$value;
    if ($value === '') {
        return '';
    }

    $looksBroken = str_contains($value, 'Ã')
        || str_contains($value, 'Ø')
        || str_contains($value, 'Ù')
        || str_contains($value, 'â€™')
        || str_contains($value, 'â€"')
        || str_contains($value, 'â€"');

    if (!$looksBroken || !function_exists('mb_convert_encoding')) {
        return $value;
    }

    $fixed = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    if (!is_string($fixed) || $fixed === '' || !preg_match('//u', $fixed)) {
        return $value;
    }

    $needsSecondPass = str_contains($fixed, 'Ã')
        || str_contains($fixed, 'Ø')
        || str_contains($fixed, 'Ù');

    if ($needsSecondPass) {
        $fixedAgain = @mb_convert_encoding($fixed, 'UTF-8', 'Windows-1252');
        if (is_string($fixedAgain) && $fixedAgain !== '' && preg_match('//u', $fixedAgain)) {
            return $fixedAgain;
        }
    }

    return $fixed;
}

function hms_text(?string $value, string $fallback = '-'): string
{
    $value = trim(hms_fix_mojibake($value));
    return $value !== '' ? $value : $fallback;
}

function hms_fetch_one(mysqli_stmt $stmt): ?array
{
    $result = $stmt->get_result();
    if (!$result instanceof mysqli_result) {
        return null;
    }

    $row = $result->fetch_assoc();
    return is_array($row) ? $row : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['Save'])) {
    hms_require_csrf('/modules/patient/Profile.php');

    $fullName = trim((string)($_POST['User-name'] ?? ''));
    $phone = trim((string)($_POST['Phone'] ?? ''));
    $nat_id = trim((string)($_POST['nat_id'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $gender = trim((string)($_POST['Gender'] ?? ''));
    $age = trim((string)($_POST['age'] ?? ''));

    $stmt = $connect->prepare(
        'UPDATE users
         SET fullName = ?, PatientContno = ?, nat_id = ?, p_age = ?, email = ?, gender = ?
         WHERE uid = ?'
    );

    if ($stmt instanceof mysqli_stmt) {
        $stmt->bind_param('ssssssi', $fullName, $phone, $nat_id, $age, $email, $gender, $sessionUid);
        $stmt->execute();
        $stmt->close();
    }

    header('Location: /modules/patient/Profile.php?updated=1');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ChangePassword'])) {
    hms_require_csrf('/modules/patient/Profile.php');

    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if ($newPass !== '' && $newPass === $confirmPass && strlen($newPass) >= 6) {
        $stmt = $connect->prepare('UPDATE users SET password = ? WHERE uid = ?');
        if ($stmt instanceof mysqli_stmt) {
            $stmt->bind_param('si', $newPass, $sessionUid);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: /modules/patient/Profile.php?pwd_updated=1');
        exit();
    } else {
        header('Location: /modules/patient/Profile.php?pwd_error=1');
        exit();
    }
}

$patient = null;
$userStmt = $connect->prepare('SELECT * FROM users WHERE uid = ? LIMIT 1');
if ($userStmt instanceof mysqli_stmt) {
    $userStmt->bind_param('i', $sessionUid);
    $userStmt->execute();
    $patient = hms_fetch_one($userStmt);
    $userStmt->close();
}

if (!$patient) {
    header('Location: /modules/dashboard.php');
    exit();
}

$reportExpression = hms_column_exists($connect, 'tblmedicalhistory', 'description')
    ? 'mh.description'
    : (hms_column_exists($connect, 'tblmedicalhistory', 'Report') ? 'mh.Report' : "''");
$treatmentExpression = hms_column_exists($connect, 'tblmedicalhistory', 'prescription')
    ? 'mh.prescription'
    : (hms_column_exists($connect, 'tblmedicalhistory', 'treatment') ? 'mh.treatment' : "''");

$lastReportSql = "
    SELECT
        mh.ID,
        mh.BloodPressure,
        mh.BloodSugar,
        mh.Scan,
        {$reportExpression} AS report_text,
        {$treatmentExpression} AS treatment_text,
        a.apid,
        a.appointmentDate,
        a.appointmentTime,
        a.doctorSpecialization,
        d.doctorName
    FROM tblmedicalhistory mh
    LEFT JOIN appointment a ON a.apid = mh.apid
    LEFT JOIN doctors d ON d.id = a.doctorId
    WHERE mh.UserID = ?
    ORDER BY mh.ID DESC
    LIMIT 1
";

$lastReport = null;
$reportStmt = $connect->prepare($lastReportSql);
if ($reportStmt instanceof mysqli_stmt) {
    $reportStmt->bind_param('i', $sessionUid);
    $reportStmt->execute();
    $lastReport = hms_fetch_one($reportStmt);
    $reportStmt->close();
}

$patientName        = hms_text($patient['fullName'] ?? '', 'Patient');
$patientEmail       = hms_text($patient['email'] ?? '', '-');
$patientPhone       = hms_text((string)($patient['PatientContno'] ?? ''), '-');
$patientNatId       = hms_text((string)($patient['nat_id'] ?? ''), '-');
$patientGender      = hms_text($patient['gender'] ?? '', '-');
$patientAge         = trim((string)($patient['p_age'] ?? '')) !== '' ? trim((string)$patient['p_age']) : '-';
$updated            = isset($_GET['updated']) && $_GET['updated'] === '1';
$pwdUpdated         = isset($_GET['pwd_updated']) && $_GET['pwd_updated'] === '1';
$pwdError           = isset($_GET['pwd_error']) && $_GET['pwd_error'] === '1';

$lastVisitDate      = hms_text($lastReport['appointmentDate'] ?? '', '-');
$lastVisitTime      = hms_text($lastReport['appointmentTime'] ?? '', '-');
$lastDoctor         = hms_text($lastReport['doctorName'] ?? '', '-');
$lastSpecialization = hms_text($lastReport['doctorSpecialization'] ?? '', '-');
$latestTreatment    = hms_text($lastReport['treatment_text'] ?? '', '-');
$latestReportText   = hms_text($lastReport['report_text'] ?? '', '-');
$latestScan         = hms_text($lastReport['Scan'] ?? '', '-');
$bloodPressure      = hms_text($lastReport['BloodPressure'] ?? '', '-');
$bloodSugar         = hms_text($lastReport['BloodSugar'] ?? '', '-');
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" href="/assets/images/echol.png">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <style>
        .hms-copy {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            word-break: break-word;
            line-height: 1.7;
        }

        @keyframes pulse-ring {
            0%   { box-shadow: 0 0 0 0 rgba(59,130,246,0.4); }
            70%  { box-shadow: 0 0 0 10px rgba(59,130,246,0); }
            100% { box-shadow: 0 0 0 0 rgba(59,130,246,0); }
        }
        .ai-pulse { animation: pulse-ring 2s ease-out infinite; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeIn 0.5s ease forwards; }

        .ai-card {
            background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%);
            border: 1px solid #bfdbfe;
            margin-top: 1rem;
        }

        .typing-dots span {
            display: inline-block;
            width: 7px; height: 7px;
            border-radius: 50%;
            background: #3b82f6;
            margin: 0 2px;
            animation: bounce 1.2s ease-in-out infinite;
        }
        .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
        .typing-dots span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes bounce {
            0%,80%,100% { transform: translateY(0); }
            40%          { transform: translateY(-6px); }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900">
<div class="min-h-screen">
    <?php require_once __DIR__ . '/../../includes/nav.php'; ?>

    <header class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <div class="rounded-3xl border border-sky-100 bg-white/95 px-6 py-6 shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-[0.28em] text-sky-600">Patient Profile</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900" dir="auto"><?= htmlspecialchars($patientName, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="mt-2 text-sm text-slate-500">Review and update your personal details, then check your latest medical summary.</p>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 pb-10 sm:px-6 lg:px-8">
        <?php if ($updated): ?>
            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                Profile updated successfully.
            </div>
        <?php endif; ?>
        <?php if ($pwdUpdated): ?>
            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                Password changed successfully.
            </div>
        <?php endif; ?>
        <?php if ($pwdError): ?>
            <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                Failed to change password. Make sure it's at least 6 characters and matches the confirmation.
            </div>
        <?php endif; ?>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.05fr)_minmax(340px,0.95fr)]">

            <!-- ══ LEFT: Personal Info Form ══ -->
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-6 flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-100 text-sky-700">
                        <i class="bi bi-person-vcard text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">Personal Information</h2>
                        <p class="text-sm text-slate-500">Keep your profile details current for booking and reporting.</p>
                    </div>
                </div>

                <form method="post" class="grid gap-5 md:grid-cols-2">
                    <?= hms_csrf_field() ?>
                    <div class="md:col-span-2">
                        <label for="User-name" class="mb-2 block text-sm font-semibold text-slate-700">Full Name</label>
                        <input
                            id="User-name" name="User-name" type="text"
                            value="<?= htmlspecialchars($patient['fullName'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            class="block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                        >
                    </div>

                    <div>
                        <label for="Phone" class="mb-2 block text-sm font-semibold text-slate-700">Phone Number</label>
                        <input
                            id="Phone" name="Phone" type="text"
                            value="<?= htmlspecialchars((string)($patient['PatientContno'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            class="block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                        >
                    </div>

                    <div>
                        <label for="nat_id" class="mb-2 block text-sm font-semibold text-slate-700">National ID</label>
                        <input
                            id="nat_id" name="nat_id" type="text" readonly
                            value="<?= htmlspecialchars((string)($patient['nat_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            class="block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                        >
                    </div>

                    <div>
                        <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">Email</label>
                        <input
                            id="email" name="email" type="email"
                            value="<?= htmlspecialchars($patient['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            class="block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                        >
                    </div>

                    <div>
                        <label for="age" class="mb-2 block text-sm font-semibold text-slate-700">Age</label>
                        <input
                            id="age" name="age" type="text"
                            value="<?= htmlspecialchars((string)($patient['p_age'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            class="block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                        >
                    </div>

                    <div>
                        <label for="Gender" class="mb-2 block text-sm font-semibold text-slate-700">Gender</label>
                        <select
                            id="Gender" name="Gender"
                            class="block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                        >
                            <option value="">Select</option>
                            <option value="Male"   <?= (($patient['gender'] ?? '') === 'Male')   ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= (($patient['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>

                    <div class="md:col-span-2 flex justify-end pt-2">
                        <button
                            type="submit" name="Save"
                            class="inline-flex items-center gap-2 rounded-2xl bg-sky-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-500"
                        >
                            <i class="bi bi-floppy"></i> Save Changes
                        </button>
                    </div>
                </form>

                <!-- ═══ Security / Password Card ═══ -->
                <div class="mt-8 pt-8 border-t border-slate-200">
                    <div class="mb-6 flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-orange-100 text-orange-700">
                            <i class="bi bi-shield-lock text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-900">Security / Password</h2>
                            <p class="text-sm text-slate-500">Update your account password to keep it secure.</p>
                        </div>
                    </div>

                    <form method="post" class="grid gap-5 md:grid-cols-2">
                        <?= hms_csrf_field() ?>
                        <div>
                            <label for="new_password" class="mb-2 block text-sm font-semibold text-slate-700">New Password</label>
                            <input
                                id="new_password" name="new_password" type="password" required minlength="6"
                                placeholder="At least 6 characters"
                                class="block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-orange-400 focus:ring-2 focus:ring-orange-100"
                            >
                        </div>
                        <div>
                            <label for="confirm_password" class="mb-2 block text-sm font-semibold text-slate-700">Confirm Password</label>
                            <input
                                id="confirm_password" name="confirm_password" type="password" required minlength="6"
                                placeholder="Re-type new password"
                                class="block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-orange-400 focus:ring-2 focus:ring-orange-100"
                            >
                        </div>
                        <div class="md:col-span-2 flex justify-end pt-2">
                            <button
                                type="submit" name="ChangePassword"
                                class="inline-flex items-center gap-2 rounded-2xl bg-orange-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-orange-500"
                            >
                                <i class="bi bi-key"></i> Update Password
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ═══ AI Health Summary Card ═══ -->
                <?php if ($lastReport): ?>
                <div class="ai-card rounded-3xl p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <div class="ai-pulse w-9 h-9 rounded-full bg-blue-600 flex items-center justify-center text-white text-base">
                                <i class="bi bi-stars"></i>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-blue-800">الملخص الصحي بالذكاء الاصطناعي</p>
                                <p class="text-xs text-blue-500">بناءً على آخر تقرير طبي</p>
                            </div>
                        </div>
                        <button onclick="loadAISummary()" id="refresh-btn"
                            class="text-xs text-gray-500 hover:text-gray-800 border border-gray-200 rounded-lg px-3 py-1.5 flex items-center gap-1 hover:bg-gray-50 transition">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>

                    <div id="ai-content">
                        <div id="ai-loading" class="flex items-center gap-3 py-3">
                            <div class="typing-dots">
                                <span></span><span></span><span></span>
                            </div>
                            <span class="text-sm text-blue-600">جارِ تحليل تقريرك الطبي...</span>
                        </div>
                        <div id="ai-result" class="hidden fade-in"></div>
                    </div>
                </div>

                <div id="report-data"
                    data-specialization="<?php echo htmlspecialchars($lastReport['doctorSpecialization'] ?? ''); ?>"
                    data-report="<?php echo htmlspecialchars(substr($lastReport['report_text'] ?? '', 0, 1500)); ?>"
                    data-treatment="<?php echo htmlspecialchars(substr($lastReport['treatment_text'] ?? '', 0, 800)); ?>"
                    data-scan="<?php echo htmlspecialchars(($lastReport['Scan'] ?? '') !== 'Does not need' ? ($lastReport['Scan'] ?? '') : ''); ?>"
                    data-doctor="<?php echo htmlspecialchars($lastReport['doctorName'] ?? ''); ?>"
                    data-date="<?php echo htmlspecialchars($lastReport['appointmentDate'] ?? ''); ?>"
                    style="display:none;">
                </div>

                <?php else: ?>
                <div class="ai-card rounded-3xl p-5 text-center text-gray-400">
                    <i class="bi bi-clipboard2-x text-3xl block mb-2"></i>
                    <p class="text-sm">No medical reports yet.<br>Your AI health summary will appear after your first visit.</p>
                </div>
                <?php endif; ?>

            </section><!-- end left -->

            <!-- ══ RIGHT: Quick Summary + Latest Report ══ -->
            <section class="space-y-6">

                <!-- Quick Summary -->
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-100 text-indigo-700">
                            <i class="bi bi-heart-pulse text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-900">Quick Summary</h2>
                            <p class="text-sm text-slate-500">Your current contact details and latest medical snapshot.</p>
                        </div>
                    </div>

                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Patient ID</dt>
                            <dd class="mt-1 text-lg font-bold text-slate-900"><?= $sessionUid ?></dd>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Gender</dt>
                            <dd class="mt-1 text-lg font-bold text-slate-900" dir="auto"><?= htmlspecialchars($patientGender, ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Phone</dt>
                            <dd class="mt-1 text-base font-semibold text-slate-900" dir="auto"><?= htmlspecialchars($patientPhone, ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">National ID</dt>
                            <dd class="mt-1 text-base font-semibold text-slate-900" dir="auto"><?= htmlspecialchars($patientNatId, ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Email</dt>
                            <dd class="mt-1 text-base font-semibold text-slate-900 hms-copy" dir="auto"><?= htmlspecialchars($patientEmail, ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3 sm:col-span-2">
                            <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Age</dt>
                            <dd class="mt-1 text-lg font-bold text-slate-900"><?= htmlspecialchars($patientAge, ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                    </dl>
                </div>

                <!-- Latest Medical Report -->
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
                            <i class="bi bi-clipboard2-pulse text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-900">Latest Medical Report</h2>
                            <p class="text-sm text-slate-500">Normalized display for the most recent visit and report text.</p>
                        </div>
                    </div>

                    <?php if ($lastReport): ?>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-2xl bg-slate-50 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Last Visit</p>
                                <p class="mt-1 text-base font-semibold text-slate-900" dir="auto"><?= htmlspecialchars($lastVisitDate, ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($lastVisitTime !== '-' ? 'at ' . $lastVisitTime : '', ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Doctor</p>
                                <p class="mt-1 text-base font-semibold text-slate-900" dir="auto"><?= htmlspecialchars($lastDoctor, ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 px-4 py-3 sm:col-span-2">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Specialization</p>
                                <p class="mt-1 text-base font-semibold text-slate-900" dir="auto"><?= htmlspecialchars($lastSpecialization, ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Blood Pressure</p>
                                <p class="mt-1 text-base font-semibold text-slate-900" dir="auto"><?= htmlspecialchars($bloodPressure, ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Blood Sugar</p>
                                <p class="mt-1 text-base font-semibold text-slate-900" dir="auto"><?= htmlspecialchars($bloodSugar, ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 px-4 py-4 sm:col-span-2">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Treatment / Prescription</p>
                                <p class="mt-2 text-sm text-slate-700 hms-copy" dir="auto"><?= htmlspecialchars($latestTreatment, ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 px-4 py-4 sm:col-span-2">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Report / Description</p>
                                <p class="mt-2 text-sm text-slate-700 hms-copy" dir="auto"><?= htmlspecialchars($latestReportText, ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 px-4 py-4 sm:col-span-2">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Scan Notes</p>
                                <p class="mt-2 text-sm text-slate-700 hms-copy" dir="auto"><?= htmlspecialchars($latestScan, ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                            No medical reports have been added yet.
                        </div>
                    <?php endif; ?>
                </div>

            </section><!-- end right -->

        </div><!-- end grid -->
    </main>
</div>

<?php if ($lastReport): ?>
<script>
async function loadAISummary() {
    const d       = document.getElementById('report-data').dataset;
    const loading = document.getElementById('ai-loading');
    const result  = document.getElementById('ai-result');
    const btn     = document.getElementById('refresh-btn');

    loading.classList.remove('hidden');
    result.classList.add('hidden');
    result.innerHTML = '';
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> جاري التحميل...';

    const prompt = `أنت مساعد طبي يساعد المرضى على فهم تقاريرهم الطبية بلغة عربية بسيطة وواضحة.

هذه بيانات آخر تقرير طبي للمريض:
- التخصص: ${d.specialization || 'عام'}
- الطبيب: ${d.doctor || 'غير معروف'}
- تاريخ الزيارة: ${d.date || 'غير معروف'}
- التقرير الطبي: ${d.report || 'غير متوفر'}
- العلاج / الوصفة: ${d.treatment || 'غير متوفر'}
- ملاحظات الأشعة أو الفحص: ${d.scan || 'لا يوجد'}

المطلوب:
1. اكتب النتيجة باللغة العربية فقط
2. استخدم لغة مفهومة للمريض وليست معقدة جدًا
3. لا تذكر أنك نموذج ذكاء اصطناعي
4. لو كانت الحالة خطيرة استخدم status = "حرجة"
5. لو كانت تحتاج متابعة استخدم status = "تحتاج متابعة"
6. لو كانت مستقرة استخدم status = "مستقرة"
7. لو كان المريض ما زال يتلقى العلاج استخدم status = "تحت العلاج"

أرجع النتيجة بصيغة JSON فقط وبهذا الشكل بالضبط:
{
  "status": "مستقرة|تحت العلاج|تحتاج متابعة|حرجة",
  "status_color": "green|yellow|orange|red",
  "diagnosis": "جملة واحدة تصف الحالة أو التشخيص الرئيسي",
  "treatment_note": "جملة واحدة تشرح العلاج أو الخطوة التالية",
  "recommendation": "نصيحة أو توصية عملية قصيرة للمريض",
  "summary": "ملخص عام من سطرين أو ثلاثة باللغة العربية البسيطة"
}`;

    try {
        const res = await fetch('./ai-proxy.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ prompt })
        });

        const data = await res.json();
        if (data.error) throw new Error(data.error);

        const text  = data.content?.[0]?.text || '';
        const clean = text.replace(/```json|```/g, '').trim();
        const info  = JSON.parse(clean);

        const colorMap = {
            green:  { bg: '#EAF3DE', text: '#3B6D11', dot: '#639922' },
            yellow: { bg: '#FAEEDA', text: '#854F0B', dot: '#BA7517' },
            orange: { bg: '#FAECE7', text: '#993C1D', dot: '#D85A30' },
            red:    { bg: '#FCEBEB', text: '#A32D2D', dot: '#E24B4A' },
        };
        const c = colorMap[info.status_color] || colorMap['green'];

        result.innerHTML = `
<div style="margin-bottom:1rem;">
  <span style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:500;padding:5px 14px;border-radius:99px;background:${c.bg};color:${c.text};">
    <span style="width:7px;height:7px;border-radius:50%;background:${c.dot};flex-shrink:0;display:inline-block;"></span>
    ${info.status}
  </span>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
  <div>
    <p style="font-size:11px;font-weight:500;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin:0 0 4px;">التشخيص</p>
    <p style="font-size:14px;color:#111827;line-height:1.6;margin:0;">${info.diagnosis}</p>
  </div>
  <div>
    <p style="font-size:11px;font-weight:500;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin:0 0 4px;">العلاج</p>
    <p style="font-size:14px;color:#111827;line-height:1.6;margin:0;">${info.treatment_note}</p>
  </div>
</div>

<hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 1rem;">

<div style="background:#f9fafb;border-radius:8px;padding:.75rem 1rem;margin-bottom:.75rem;">
  <p style="font-size:11px;font-weight:500;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin:0 0 4px;">التوصية</p>
  <p style="font-size:14px;color:#111827;line-height:1.6;margin:0;">${info.recommendation}</p>
</div>

<p style="font-size:13px;color:#6b7280;line-height:1.7;margin:0 0 .5rem;">${info.summary}</p>

${(info.doctor || info.date) ? `<p style="font-size:11px;color:#d1d5db;margin:0;text-align:left;">${info.doctor ? 'د. ' + info.doctor : ''}${info.doctor && info.date ? ' — ' : ''}${info.date || ''}</p>` : ''}`;

        loading.classList.add('hidden');
        result.classList.remove('hidden');

        try {
            sessionStorage.setItem(
                'ai_health_summary_<?php echo intval($_SESSION["uid"]); ?>',
                JSON.stringify({
                    html:     result.innerHTML,
                    ts:       Date.now(),
                    reportId: '<?php echo $lastReport["ID"] ?? ""; ?>'
                })
            );
        } catch(e) {}

    } catch(err) {
        loading.classList.add('hidden');
        result.classList.remove('hidden');
        result.innerHTML = `
            <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:.75rem 1rem;font-size:13px;color:#b91c1c;">
                <i class="bi bi-wifi-off" style="margin-left:6px;"></i>خطأ: ${err.message}
            </div>`;
        console.error('AI Summary Error:', err);
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Refresh';
}

window.addEventListener('DOMContentLoaded', () => {
    const lastReportId = '<?php echo $lastReport["ID"] ?? ""; ?>';
    const cacheKey     = 'ai_health_summary_<?php echo intval($_SESSION["uid"]); ?>';
    const cached       = sessionStorage.getItem(cacheKey);

    if (cached) {
        try {
            const { html, ts, reportId } = JSON.parse(cached);
            if (reportId === lastReportId && Date.now() - ts < 10 * 60 * 1000) {
                document.getElementById('ai-loading').classList.add('hidden');
                const result = document.getElementById('ai-result');
                result.innerHTML = html;
                result.classList.remove('hidden');
                return;
            }
        } catch(e) {}
    }

    setTimeout(loadAISummary, 1500);
});
</script>
<?php endif; ?>

<script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>
