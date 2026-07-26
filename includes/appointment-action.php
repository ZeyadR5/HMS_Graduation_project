<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/secure-token.php';
require_once __DIR__ . '/../includes/mailer.php';

if (!isset($_SESSION['logged_in'])) {
    header("location: /index.php");
    exit();
}

$connect = hms_db_connect();
if ($connect->connect_error) {
    die("Connection failed");
}

$role = $_SESSION['role'] ?? '';
$action = $_GET['action'] ?? '';
$ref = $_GET['ref'] ?? '';
$apid = hms_decrypt_id($ref) ?: 0;
$return = $_GET['return'] ?? hms_role_home($role);

$stmt = $connect->prepare("
    SELECT appointment.*,
           doctors.doctorName,
           doctors.specilization AS docSpec,
           users.fullName AS patientFullName
    FROM appointment
    JOIN doctors ON doctors.id = appointment.doctorId
    LEFT JOIN users ON users.uid = appointment.userId
    WHERE apid = ?
");
$stmt->bind_param("i", $apid);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    hms_redirect_with_popup('Appointment not found.', $return);
}

$appt = $res->fetch_assoc();

if ($role === 'Patient') {
    if ((int)$appt['userId'] !== (int)($_SESSION['uid'] ?? 0)) {
        hms_redirect_with_popup('Access denied.', hms_role_home($role));
    }
    $canEdit = true;
    $canCancel = true;
} elseif ($role === 'Doctor') {
    if ((int)$appt['doctorId'] !== (int)($_SESSION['id'] ?? 0)) {
        hms_redirect_with_popup('Access denied.', hms_role_home($role));
    }
    $canEdit = false;
    $canCancel = true;
} elseif (in_array($role, ['Admin', 'System Admin', 'User'], true)) {
    $canEdit = in_array($role, ['Admin', 'System Admin'], true);
    $canCancel = true;
} else {
    hms_redirect_with_popup('You do not have permission to modify appointments.', hms_role_home($role));
}

if ($action === 'cancel') {
    if (!$canCancel) {
        hms_redirect_with_popup('Permission denied.', $return);
    }

    if ((int)$appt['userStatus'] === 0 || (int)$appt['doctorStatus'] === 0) {
        hms_redirect_with_popup('Appointment already cancelled.', $return);
    }

    // For User/Admin/System Admin: require cancel reason
    $needsReason = in_array($role, ['User', 'Admin', 'System Admin'], true);
    $cancelReason = trim($_POST['cancel_reason'] ?? $_GET['cancel_reason'] ?? '');

    if ($needsReason && $cancelReason === '' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        // Show cancel reason form
        ?>
        <!DOCTYPE html>
        <html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Cancel Appointment</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>
            <link rel="icon" href="/assets/images/echol.png">
            <link rel="stylesheet" href="/assets/css/responsive.css">
        </head>
        <body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-lg p-8 w-full max-w-md">
                <div class="flex items-center gap-3 mb-6">
                    <a href="<?= htmlspecialchars($return) ?>" class="text-gray-400 hover:text-gray-700">
                        <i class="bi bi-arrow-left text-xl"></i>
                    </a>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Cancel Appointment</h1>
                        <p class="text-xs text-gray-400">#<?= $apid ?></p>
                    </div>
                </div>

                <div class="bg-red-50 rounded-xl p-4 mb-6 border border-red-100">
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div><span class="font-semibold text-gray-700">Patient:</span> <?= htmlspecialchars($appt['patientFullName'] ?: $appt['patient_Name']) ?></div>
                        <div><span class="font-semibold text-gray-700">Doctor:</span> <?= htmlspecialchars($appt['doctorName']) ?></div>
                        <div><span class="font-semibold text-gray-700">Date:</span> <?= htmlspecialchars($appt['appointmentDate']) ?></div>
                        <div><span class="font-semibold text-gray-700">Time:</span> <?= htmlspecialchars($appt['appointmentTime']) ?></div>
                    </div>
                </div>

                <form method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                    <?= hms_csrf_field() ?>
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="bi bi-chat-left-text me-1"></i>Cancellation Reason <span class="text-red-500">*</span>
                        </label>
                        <textarea name="cancel_reason" rows="3" required placeholder="Please enter the reason for cancellation..." 
                            class="w-full border-2 border-gray-200 rounded-lg px-4 py-2 text-gray-800 focus:border-red-400 focus:outline-none resize-none"
                            minlength="5"></textarea>
                        <p class="text-xs text-gray-400 mt-1">This reason will be recorded and visible to management.</p>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 rounded-lg transition">
                            <i class="bi bi-x-circle me-1"></i>Confirm Cancel
                        </button>
                        <a href="<?= htmlspecialchars($return) ?>" class="flex-1 text-center bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2.5 rounded-lg transition">
                            Go Back
                        </a>
                    </div>
                </form>
            </div>
            <script src="/assets/js/responsive-nav.js" defer></script>
        </body>
        </html>
        <?php
        exit;
    }

    // Validate CSRF for POST submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        hms_require_csrf($return);
    } else {
        hms_require_csrf($return);
    }

    // Validate reason is provided for staff
    if ($needsReason && $cancelReason === '') {
        hms_redirect_with_popup('Cancellation reason is required.', $return);
    }

    if ($role === 'Patient') {
        $cancelledBy = 'Patient: ' . ($_SESSION['username'] ?? 'Patient');
        $userStatus = 0;
        $doctorStatus = (int)$appt['doctorStatus'];
    } elseif ($role === 'Doctor') {
        $cancelledBy = 'Doctor: ' . ($_SESSION['username'] ?? 'Doctor');
        $userStatus = (int)$appt['userStatus'];
        $doctorStatus = 0;
    } else {
        $cancelledBy = $role . ': ' . ($_SESSION['username'] ?? 'Staff');
        $userStatus = 0;
        $doctorStatus = (int)$appt['doctorStatus'];
    }

    require_once __DIR__ . '/notification-api.php';

    $update = $connect->prepare("UPDATE appointment SET userStatus = ?, doctorStatus = ?, cancelledBy = ?, cancel_reason = ? WHERE apid = ?");
    $update->bind_param("iissi", $userStatus, $doctorStatus, $cancelledBy, $cancelReason, $apid);
    
    if ($update->execute()) {
        $patientName = $appt['patientFullName'] ?: $appt['patient_Name'];
        $doctorId = (int)$appt['doctorId'];
        $doctorName = $appt['doctorName'];
        $date = $appt['appointmentDate'];
        $time = $appt['appointmentTime'];
        $reasonNote = $cancelReason ? " Reason: $cancelReason" : '';

        // 1. If Patient or Admin cancels, notify Doctor
        if ($role !== 'Doctor') {
            hms_create_notification($connect, [
                'recipient_type' => 'doctor',
                'recipient_id' => $doctorId,
                'title' => 'Appointment Cancelled — ' . $patientName,
                'message' => "The appointment for $patientName on $date at $time has been cancelled by $cancelledBy.$reasonNote",
                'type' => 'cancellation',
                'related_doctor_id' => $doctorId,
                'related_appointment_id' => $apid
            ]);
        }

        // 2. If Doctor or Admin cancels, notify Patient
        if ($role !== 'Patient' && !empty($appt['userId'])) {
            hms_create_notification($connect, [
                'recipient_type' => 'patient',
                'recipient_id' => (int)$appt['userId'],
                'title' => 'Appointment Cancelled — ' . $doctorName,
                'message' => "Your appointment with $doctorName on $date at $time has been cancelled by $cancelledBy.$reasonNote",
                'type' => 'cancellation',
                'related_doctor_id' => $doctorId,
                'related_appointment_id' => $apid
            ]);
        }

        // 3. Notify Reception (Employee role) and Admins
        error_log("HMS: Notifying staff about cancellation. Patient: $patientName");
        $staffStmt = $connect->prepare("SELECT id, role FROM employ WHERE role IN ('User', 'Admin', 'System Admin')");
        $staffStmt->execute();
        $staffRes = $staffStmt->get_result();
        while ($staff = $staffRes->fetch_assoc()) {
            $isRelReception = ($staff['role'] === 'User');
            hms_create_notification($connect, [
                'recipient_type' => $isRelReception ? 'employee' : 'admin',
                'recipient_id' => (int)$staff['id'],
                'title' => 'Appointment Cancelled — ' . $patientName,
                'message' => "Appointment for $patientName with $doctorName on $date has been cancelled by $cancelledBy.$reasonNote",
                'type' => 'cancellation',
                'related_doctor_id' => $doctorId,
                'related_appointment_id' => $apid,
                'mirror_email' => false
            ]);
        }
        $staffStmt->close();
    }

    hms_redirect_with_popup('Appointment cancelled successfully.', $return);
}

if ($action !== 'edit') {
    hms_redirect_with_popup('Invalid action.', $return);
}

if (!$canEdit) {
    hms_redirect_with_popup('Permission denied.', $return);
}

if ((int)$appt['userStatus'] === 0 || (int)$appt['doctorStatus'] === 0) {
    hms_redirect_with_popup('Cannot edit a cancelled appointment.', $return);
}

$error = '';
$isPrivileged = in_array($role, ['Admin', 'System Admin'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    hms_require_csrf($return);

    $newDate = trim($_POST['appointmentDate'] ?? '');
    $newTime = trim($_POST['appointmentTime'] ?? '');
    $newDocId = intval($_POST['doctorId'] ?? 0);
    $newPaid = trim($_POST['paid'] ?? '');

    if ($newDate === '' || $newTime === '' || $newDocId < 1) {
        $error = 'Please fill all required fields.';
    } elseif ($role === 'Patient' && $newDate < date('Y-m-d')) {
        $error = 'Date cannot be in the past.';
    } elseif ($newPaid !== '' && !is_numeric($newPaid)) {
        $error = 'Paid amount must be numeric.';
    }

    if ($error === '') {
        // Server-side availability check
        $phpDow = (int)date('w', strtotime($newDate));
        $dayMap = [6 => 0, 0 => 1, 1 => 2, 2 => 3, 3 => 4, 4 => 5, 5 => 6];
        $checkDay = $dayMap[$phpDow];

        // Check override
        $ovChk = $connect->prepare("SELECT status FROM doctor_day_overrides WHERE doctor_id = ? AND override_date = ?");
        $ovChk->bind_param("is", $newDocId, $newDate);
        $ovChk->execute();
        $ovRow = $ovChk->get_result()->fetch_assoc();
        $ovChk->close();

        if ($ovRow && $ovRow['status'] === 'off') {
            $error = 'Doctor is not available on this date (day override).';
        }

        // Check schedule exists
        if ($error === '') {
            $schChk = $connect->prepare("SELECT COUNT(*) as cnt FROM doctor_schedules WHERE doctor_id = ?");
            $schChk->bind_param("i", $newDocId);
            $schChk->execute();
            $schCnt = $schChk->get_result()->fetch_assoc()['cnt'];
            $schChk->close();

            if ((int)$schCnt > 0) {
                $dayChk = $connect->prepare("SELECT status FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ? AND status = 'available'");
                $dayChk->bind_param("ii", $newDocId, $checkDay);
                $dayChk->execute();
                if ($dayChk->get_result()->num_rows === 0 && !($ovRow && $ovRow['status'] === 'custom')) {
                    $dayNames = ['Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'];
                    $error = 'Doctor is not available on ' . $dayNames[$checkDay] . 's.';
                }
                $dayChk->close();
            }
        }

        // Check for double booking (same doctor, same date, same time, excluding current appointment)
        if ($error === '') {
            $dblChk = $connect->prepare("SELECT apid FROM appointment WHERE doctorId = ? AND appointmentDate = ? AND appointmentTime = ? AND apid != ? AND userStatus IN (1,2) AND doctorStatus IN (1,2)");
            $dblChk->bind_param("issi", $newDocId, $newDate, $newTime, $apid);
            $dblChk->execute();
            if ($dblChk->get_result()->num_rows > 0) {
                $error = 'This time slot is already booked. Please choose another time.';
            }
            $dblChk->close();
        }
    }

    if ($error === '') {
        $doctorStmt = $connect->prepare("SELECT id, specilization, docFees FROM doctors WHERE id = ?");
        $doctorStmt->bind_param("i", $newDocId);
        $doctorStmt->execute();
        $doctorRes = $doctorStmt->get_result();

        if ($doctorRes->num_rows === 0) {
            $error = 'Doctor not found.';
        } else {
            $doctorRow = $doctorRes->fetch_assoc();
            $doctorSpec = $doctorRow['specilization'];
            $doctorFees = (int)$doctorRow['docFees'];
            $paidValue = $isPrivileged ? (($newPaid === '') ? 0 : (int)$newPaid) : (int)($appt['paid'] ?? 0);

            $update = $connect->prepare("
                UPDATE appointment
                SET appointmentDate = ?,
                    appointmentTime = ?,
                    doctorId = ?,
                    doctorSpecialization = ?,
                    consultancyFees = ?,
                    paid = ?
                WHERE apid = ?
            ");
            $update->bind_param(
                "ssisiii",
                $newDate,
                $newTime,
                $newDocId,
                $doctorSpec,
                $doctorFees,
                $paidValue,
                $apid
            );
            $update->execute();

            // ── Email: Reschedule notification ────────────────────────────
            if (!empty($appt['userId'])) {
                $rEmailStmt = $connect->prepare("SELECT email, fullName FROM users WHERE uid = ? AND email IS NOT NULL AND email != '' LIMIT 1");
                $rEmailStmt->bind_param("i", $appt['userId']);
                $rEmailStmt->execute();
                $rEmailRow = $rEmailStmt->get_result()->fetch_assoc();
                $rEmailStmt->close();
                if ($rEmailRow && !empty($rEmailRow['email'])) {
                    $rDoctorStmt = $connect->prepare("SELECT doctorName FROM doctors WHERE id = ? LIMIT 1");
                    $rDoctorStmt->bind_param("i", $newDocId);
                    $rDoctorStmt->execute();
                    $rDoctorRow = $rDoctorStmt->get_result()->fetch_assoc();
                    $rDoctorStmt->close();
                    hms_send_appointment_rescheduled_email(
                        $rEmailRow['email'],
                        $rEmailRow['fullName'] ?: ($appt['patientFullName'] ?: $appt['patient_Name']),
                        $rDoctorRow['doctorName'] ?? $appt['doctorName'],
                        $appt['appointmentDate'],
                        $appt['appointmentTime'],
                        $newDate,
                        $newTime
                    );
                }
            }

            hms_redirect_with_popup('Appointment updated successfully.', $return);
        }
    }
}

$specs = $connect->query("SELECT specilization FROM doctorspecilization ORDER BY specilization");

// Preload ALL doctors grouped by specialization into PHP array
$allDocsRes = $connect->query("SELECT id, doctorName, docFees, specilization FROM doctors ORDER BY doctorName");
$doctorsBySpec = [];
while ($d = $allDocsRes->fetch_assoc()) {
    $doctorsBySpec[$d['specilization']][] = [
        'id'    => (int)$d['id'],
        'name'  => $d['doctorName'],
        'fees'  => (int)$d['docFees'],
    ];
}

// Current appointment doctor spec (from doctors table directly — most reliable)
$currentSpec = $appt['docSpec'] ?? $appt['doctorSpecialization'] ?? '';
$currentDocId = (int)$appt['doctorId'];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Appointment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>
    <link rel="icon" href="/assets/images/echol.png">
    <link rel="stylesheet" href="/assets/css/responsive.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-lg p-8 w-full max-w-lg">
        <div class="flex items-center gap-3 mb-6">
            <a href="<?= htmlspecialchars($return) ?>" class="text-gray-400 hover:text-gray-700">
                <i class="bi bi-arrow-left text-xl"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Edit Appointment</h1>
                <p class="text-xs text-gray-400">Role: <?= htmlspecialchars($role) ?></p>
            </div>
        </div>

        <div class="bg-blue-50 rounded-xl p-4 mb-6 border border-blue-100 grid grid-cols-2 gap-2 text-sm">
            <div><span class="font-semibold text-gray-700">Patient:</span> <?= htmlspecialchars($appt['patientFullName'] ?: $appt['patient_Name']) ?></div>
            <div><span class="font-semibold text-gray-700">Current Doctor:</span> <?= htmlspecialchars($appt['doctorName']) ?></div>
            <div><span class="font-semibold text-gray-700">Specialization:</span> <?= htmlspecialchars($appt['doctorSpecialization']) ?></div>
            <div><span class="font-semibold text-gray-700">Fees:</span> <?= htmlspecialchars((string)$appt['consultancyFees']) ?> EGP</div>
            <div><span class="font-semibold text-gray-700">Paid:</span> <?= htmlspecialchars((string)($appt['paid'] ?? 0)) ?> EGP</div>
            <div><span class="font-semibold text-gray-700">Date:</span> <?= htmlspecialchars($appt['appointmentDate']) ?></div>
        </div>

        <?php if ($error !== ''): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 mb-4 text-sm">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?= hms_csrf_field() ?>
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Specialization</label>
                <select id="sel_spec" class="w-full border-2 border-gray-200 rounded-lg px-4 py-2 text-gray-800 focus:border-blue-500 focus:outline-none">
                    <option value="">Select specialization</option>
                    <?php while ($spec = $specs->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($spec['specilization']) ?>" <?= $spec['specilization'] === $currentSpec ? 'selected' : '' ?>>
                            <?= htmlspecialchars($spec['specilization']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Doctor</label>
                <select id="sel_doc" name="doctorId" required class="w-full border-2 border-gray-200 rounded-lg px-4 py-2 text-gray-800 focus:border-blue-500 focus:outline-none">
                    <option value="">Loading...</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Consultation Fees (EGP)</label>
                <input type="text" id="fees_display" readonly class="w-full border-2 border-gray-100 bg-gray-50 rounded-lg px-4 py-2 text-gray-600" value="<?= htmlspecialchars((string)$appt['consultancyFees']) ?>">
            </div>

            <?php if ($isPrivileged): ?>
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Paid Amount (EGP)</label>
                    <input type="number" min="0" step="1" name="paid" value="<?= htmlspecialchars((string)($appt['paid'] ?? 0)) ?>" class="w-full border-2 border-gray-200 rounded-lg px-4 py-2 text-gray-800 focus:border-blue-500 focus:outline-none">
                </div>
            <?php else: ?>
                <input type="hidden" name="paid" value="<?= htmlspecialchars((string)($appt['paid'] ?? 0)) ?>">
            <?php endif; ?>

            <!-- Availability Alert -->
            <div id="editAvailAlert" style="display:none;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:10px;padding:0.7rem 1rem;font-size:0.85rem;font-weight:600;margin-bottom:0.75rem;"></div>

            <!-- Doctor Info Panel -->
            <div id="editDocInfo" style="display:none;background:linear-gradient(135deg,#eff6ff,#f0fdf4);border:1px solid #bfdbfe;border-radius:12px;padding:0.6rem 1rem;margin-bottom:0.75rem;font-size:0.82rem;">
                <span><strong>Session:</strong> <span id="editSlotDur"></span></span>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Date</label>
                <input type="date" name="appointmentDate" id="editDate" value="<?= htmlspecialchars($appt['appointmentDate']) ?>" <?= $role === 'Patient' ? 'min="' . date('Y-m-d') . '"' : '' ?> required class="w-full border-2 border-gray-200 rounded-lg px-4 py-2 text-gray-800 focus:border-blue-500 focus:outline-none" onchange="loadEditSlots()">
            </div>

            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Available Time Slots</label>
                <select name="appointmentTime" id="editTimeSlot" required class="w-full border-2 border-gray-200 rounded-lg px-4 py-2 text-gray-800 focus:border-blue-500 focus:outline-none">
                    <option value="<?= htmlspecialchars($appt['appointmentTime']) ?>"><?= htmlspecialchars($appt['appointmentTime']) ?> (current)</option>
                </select>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-lg transition">
                    <i class="bi bi-check-circle me-1"></i> Save Changes
                </button>
                <a href="<?= htmlspecialchars($return) ?>" class="flex-1 text-center bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 rounded-lg transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <script>
        // All doctors preloaded from PHP — no AJAX needed
        const doctorsBySpec = <?= json_encode($doctorsBySpec, JSON_UNESCAPED_UNICODE) ?>;
        const selectedDoctorId = <?= $currentDocId ?>;
        const currentSpec     = <?= json_encode($currentSpec) ?>;

        const doctorSelect       = document.getElementById('sel_doc');
        const specializationSelect = document.getElementById('sel_spec');
        const feesDisplay        = document.getElementById('fees_display');

        function populateDoctors(spec, preferDoctorId) {
            const doctors = doctorsBySpec[spec] || [];
            if (!spec || doctors.length === 0) {
                doctorSelect.innerHTML = '<option value="">No doctors for this specialization</option>';
                feesDisplay.value = '';
                return;
            }
            let html = '<option value="">Select doctor</option>';
            doctors.forEach(d => {
                const sel = d.id === preferDoctorId ? ' selected' : '';
                html += `<option value="${d.id}" data-fees="${d.fees}"${sel}>${d.name}</option>`;
            });
            doctorSelect.innerHTML = html;
            refreshFees();
            loadEditSlots();
        }

        function refreshFees() {
            const opt = doctorSelect.options[doctorSelect.selectedIndex];
            feesDisplay.value = opt ? (opt.dataset.fees || '') : '';
        }

        // Populate on spec change
        specializationSelect.addEventListener('change', function () {
            populateDoctors(this.value, null);
        });

        doctorSelect.addEventListener('change', () => { refreshFees(); loadEditSlots(); });

        // Populate on page load with the current doctor pre-selected
        populateDoctors(currentSpec, selectedDoctorId);

        // ===== Availability slot loading for Edit form =====
        function loadEditSlots() {
            const docId = doctorSelect.value;
            const dateVal = document.getElementById('editDate').value;
            const slotSelect = document.getElementById('editTimeSlot');
            const alert = document.getElementById('editAvailAlert');
            const info = document.getElementById('editDocInfo');

            if (!docId || !dateVal) {
                slotSelect.innerHTML = '<option value="">Select doctor & date</option>';
                alert.style.display = 'none';
                info.style.display = 'none';
                return;
            }

            fetch('/includes/get_available_slots.php?doctor_id=' + docId + '&date=' + dateVal)
            .then(r => r.json())
            .then(data => {
                if (data.doctor_info && data.doctor_info.slot_duration) {
                    document.getElementById('editSlotDur').textContent = data.doctor_info.slot_duration + ' min';
                    info.style.display = 'block';
                } else {
                    info.style.display = 'none';
                }

                if (!data.available && !data.no_schedule) {
                    alert.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> ' + (data.message || 'Not available');
                    alert.style.display = 'block';
                    slotSelect.innerHTML = '<option value="">Not available</option>';
                    return;
                }

                alert.style.display = 'none';

                if (data.no_schedule) {
                    alert.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> ' + (data.message || 'This doctor has not set up their schedule yet. Booking is not available.');
                    alert.style.display = 'block';
                    slotSelect.innerHTML = '<option value="">Schedule not set</option>';
                    return;
                }

                let html = '<option value="">Select Time Slot</option>';
                data.slots.forEach(s => {
                    if (s.available) {
                        html += '<option value="' + s.time + '">' + s.display + '</option>';
                    } else {
                        const reason = s.booked ? ' (Booked)' : ' (Past)';
                        html += '<option value="" disabled style="color:#999;">' + s.display + reason + '</option>';
                    }
                });
                slotSelect.innerHTML = html;
            })
            .catch(() => {
                slotSelect.innerHTML = '<option value="">Error loading slots</option>';
            });
        }

        // Reload slots when doctor or date changes
        doctorSelect.addEventListener('change', () => { refreshFees(); loadEditSlots(); });
        document.getElementById('editDate').addEventListener('change', loadEditSlots);

        // Load slots on page load
        setTimeout(loadEditSlots, 800);
    </script>
    <script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>
