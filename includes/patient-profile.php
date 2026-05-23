<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/secure-token.php';

ini_set("display_errors", 0);

$connect = hms_db_connect();
if ($connect->connect_error) {
    die("Connection failed: " . $connect->connect_error);
}
$connect->set_charset('utf8mb4');

$role = $_SESSION['role'] ?? '';
$activePage = 'medical-record';

function hms_fix_mojibake(?string $value): string
{
    $value = (string)$value;
    if ($value === '') {
        return '';
    }

    $looksBroken = str_contains($value, 'Ã')
        || str_contains($value, 'â')
        || str_contains($value, 'Ø')
        || str_contains($value, 'Ù');

    if (!$looksBroken) {
        return $value;
    }

    if (!function_exists('mb_convert_encoding')) {
        return $value;
    }

    $fixed = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    if (!is_string($fixed) || $fixed === '' || !preg_match('//u', $fixed)) {
        return $value;
    }

    $needsSecondPass = str_contains($fixed, 'Ã')
        || str_contains($fixed, 'â')
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

function hms_text(?string $value, string $fallback = '—'): string
{
    $value = trim(hms_fix_mojibake($value));
    return $value !== '' ? $value : $fallback;
}

// ✅ Secure token: decrypt the ref parameter to get the real UID
$uid = -1;
if (!empty($_GET['ref'])) {
    $uid = hms_decrypt_id($_GET['ref']);
    if ($uid === null) {
        die("Invalid or tampered link.");
    }
} elseif (isset($_GET['uid'])) {
    // Backward compatibility — redirect to secure URL
    $uid = (int)$_GET['uid'];
    if ($uid >= 0) {
        $ref = hms_encrypt_id($uid);
        header("Location: /includes/patient-profile.php?ref=" . urlencode($ref));
        exit();
    }
}

if ($uid < 0) {
    header("Location: /includes/med-record.php");
    exit();
}

if ($role === 'Patient') {
    $sessionUid = (int)($_SESSION['uid'] ?? 0);
    if ($sessionUid <= 0) {
        header("Location: /index.php");
        exit();
    }

    if ($uid !== $sessionUid) {
        $ref = hms_encrypt_id($sessionUid);
        header("Location: /includes/patient-profile.php?ref=" . urlencode($ref));
        exit();
    }
}

$patientSql = mysqli_query($connect, "SELECT * FROM users WHERE uid = '{$uid}'");
$patient = $patientSql ? $patientSql->fetch_assoc() : null;

if (!$patient) {
    header("Location: /includes/med-record.php");
    exit();
}

if ($role === 'Doctor') {
    $docid = (int)($_SESSION['id'] ?? 0);
    $accessStmt = $connect->prepare("SELECT apid FROM appointment WHERE userId = ? AND doctorId = ? LIMIT 1");
    $accessStmt->bind_param("ii", $uid, $docid);
    $accessStmt->execute();
    $hasAccess = $accessStmt->get_result()->num_rows > 0;
    $accessStmt->close();

    if (!$hasAccess) {
        header("Location: /includes/med-record.php");
        exit();
    }
}

$reportsSql = mysqli_query(
    $connect,
    "SELECT
        tblmedicalhistory.*,
        appointment.appointmentDate,
        appointment.doctorSpecialization,
        appointment.apid AS appt_id,
        appointment.doctorId,
        doctors.doctorName
     FROM tblmedicalhistory
     JOIN appointment ON appointment.apid = tblmedicalhistory.apid
     LEFT JOIN doctors ON doctors.id = appointment.doctorId
     WHERE tblmedicalhistory.userId = '{$uid}'
     ORDER BY appointment.appointmentDate DESC, tblmedicalhistory.ID DESC"
);

$docid = (int)($_SESSION['id'] ?? 0);
$doctorFilter = ($role === 'Doctor') ? "AND appointment.doctorId = '{$docid}'" : '';

$pendingAppointmentsSql = mysqli_query(
    $connect,
    "SELECT
        appointment.apid,
        appointment.appointmentDate,
        appointment.doctorSpecialization,
        doctors.doctorName
     FROM appointment
     LEFT JOIN doctors ON doctors.id = appointment.doctorId
     WHERE appointment.userId = '{$uid}'
       AND appointment.userStatus IN (1,2)
       AND appointment.apid NOT IN (SELECT apid FROM tblmedicalhistory WHERE userId = '{$uid}')
       {$doctorFilter}
     ORDER BY appointment.appointmentDate DESC"
);

$writeReportBase = '/modules/doctor/doc-write.php';

if ($role === 'Patient') {
    $backUrl = '/modules/patient/calender.php';
    $backLabel = 'Back to Calendar';
} elseif ($role === 'System Admin') {
    $backUrl = '/modules/super-admin/super-med-record.php';
    $backLabel = 'Back to Medical Record';
} elseif ($role === 'Admin') {
    $backUrl = '/modules/admin/admin-med-record.php';
    $backLabel = 'Back to Medical Record';
} else {
    $backUrl = '/includes/med-record.php';
    $backLabel = 'Back to Medical Record';
}

$patientName = hms_text($patient['fullName'] ?? '', 'Unknown Patient');
$patientGender = hms_text($patient['gender'] ?? '', '—');
$patientEmail = hms_text($patient['email'] ?? '', '—');
$patientNotes = hms_text($patient['PatientMedhis'] ?? '', '—');
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Profile - <?= htmlspecialchars($patientName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" href="/assets/images/echol.png">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <style>
        .hms-report-text {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            word-break: break-word;
            line-height: 1.7;
        }
    </style>
</head>
<body>
<div class="min-h-full">

    <?php require_once __DIR__ . "/nav.php"; ?>

    <header class="bg-white shadow">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 flex items-center justify-between">
            <div>
                <a href="<?= htmlspecialchars($backUrl) ?>" class="text-sm text-blue-600 hover:underline mb-1 inline-block"><?= htmlspecialchars($backLabel) ?></a>
                <h1 class="text-3xl font-bold tracking-tight text-gray-900"><?= htmlspecialchars($patientName) ?></h1>
            </div>
        </div>
    </header>

    <main>
        <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">

            <div class="bg-white rounded-xl shadow ring-1 ring-gray-200 p-6 mb-8 grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div>
                    <p class="text-xs text-gray-500 font-medium uppercase">Patient ID</p>
                    <p class="text-lg font-bold text-gray-900"><?= (int)$patient['uid'] ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium uppercase">Full Name</p>
                    <p class="text-lg font-bold text-gray-900" dir="auto"><?= htmlspecialchars($patientName) ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium uppercase">Gender</p>
                    <p class="text-lg font-bold text-gray-900" dir="auto"><?= htmlspecialchars($patientGender) ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium uppercase">Age</p>
                    <p class="text-lg font-bold text-gray-900"><?= !empty($patient['p_age']) ? (int)$patient['p_age'] : '—' ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium uppercase">Phone</p>
                    <p class="text-lg font-bold text-gray-900"><?= !empty($patient['PatientContno']) ? htmlspecialchars((string)$patient['PatientContno']) : '—' ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium uppercase">Email</p>
                    <p class="text-lg font-bold text-gray-900 hms-report-text" dir="auto"><?= htmlspecialchars($patientEmail) ?></p>
                </div>
                <?php if ($role === 'Admin' || $role === 'System Admin' || $role === 'User'): ?>
                <div class="col-span-2 sm:col-span-4 border-t pt-4 mt-2">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-blue-50 p-4 rounded-xl border border-blue-100">
                        <div>
                            <h3 class="text-sm font-bold text-blue-900"><i class="bi bi-shield-lock me-1"></i> Account Recovery / Activation</h3>
                            <p class="text-xs text-blue-700 mt-1">Generate a secure code for the patient to reset their password or activate a new account with this National ID.</p>
                            <?php if (!empty($patient['activation_code']) && !empty($patient['activation_expiry']) && strtotime($patient['activation_expiry']) > time()): ?>
                                <p class="text-xs text-green-700 font-bold mt-2"><i class="bi bi-check-circle"></i> An active code exists (Expires: <?= date('M j, Y h:i A', strtotime($patient['activation_expiry'])) ?>)</p>
                            <?php endif; ?>
                        </div>
                        <div class="flex-shrink-0 text-center">
                            <button type="button" id="btnGenerateCode" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg text-sm shadow transition-all focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                Generate Code
                            </button>
                            <div id="codeDisplayArea" class="hidden mt-3">
                                <span class="text-xs text-gray-500 uppercase tracking-widest block mb-1">Activation Code</span>
                                <span id="theActivationCode" class="font-mono text-2xl font-black text-gray-900 tracking-widest bg-white px-4 py-2 rounded-lg border-2 border-dashed border-gray-300 block select-all"></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-bold text-gray-900">
                        <i class="bi bi-clipboard2-pulse me-2 text-blue-600"></i>Medical Reports
                    </h2>
                    <?php if ($role === 'Doctor' && $reportsSql && $reportsSql->num_rows > 0): ?>
                    <button type="button" id="summarizeHistory" 
                        class="inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700 ring-1 ring-inset ring-indigo-700/20 hover:bg-indigo-100 transition-all">
                        <i class="bi bi-magic"></i> AI Clinical Summary
                    </button>
                    <div id="aiStatus" class="text-[10px] text-indigo-600 font-bold hidden">
                        <span class="flex items-center gap-1">
                            <div class="animate-spin rounded-full h-2 w-2 border-b-2 border-indigo-600"></div>
                            AI Analyzing...
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php
                $pendingCount = $pendingAppointmentsSql ? $pendingAppointmentsSql->num_rows : 0;
                if ($role === 'Doctor' && $pendingCount > 0):
                ?>
                <div class="dropdown">
                    <button class="inline-flex items-center gap-2 rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-500 dropdown-toggle"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-plus-circle"></i> Add New Report
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg">
                        <li><h6 class="dropdown-header">Select Appointment</h6></li>
                        <?php
                        $pendingAppointmentsSql->data_seek(0);
                        while ($appt = $pendingAppointmentsSql->fetch_assoc()):
                        ?>
                        <li>
                            <a class="dropdown-item py-2" href="<?= $writeReportBase ?>?ref=<?= urlencode(hms_encrypt_id((int)$appt['apid'])) ?>">
                                <span class="font-semibold" dir="auto"><?= htmlspecialchars(hms_text($appt['doctorSpecialization'] ?? '', '—')) ?></span>
                                <br><small class="text-gray-500" dir="auto"><?= htmlspecialchars(hms_text($appt['appointmentDate'] ?? '', '—')) ?> - Dr. <?= htmlspecialchars(hms_text($appt['doctorName'] ?? '', '—')) ?></small>
                            </a>
                        </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
                <?php elseif ($role === 'Doctor'): ?>
                <span class="text-sm text-gray-400 italic">No pending appointments without reports</span>
                <?php endif; ?>
            </div>

            <?php if ($reportsSql && $reportsSql->num_rows > 0): ?>
                <div class="space-y-4">
                <?php while ($report = $reportsSql->fetch_assoc()): ?>
                    <?php
                    $reportSpecialization = hms_text($report['doctorSpecialization'] ?? '', '—');
                    $reportDoctor = hms_text($report['doctorName'] ?? '', '—');
                    $reportDate = hms_text($report['appointmentDate'] ?? '', '—');
                    $reportPrescription = hms_text($report['prescription'] ?? '', '');
                    $reportDescription = hms_text($report['description'] ?? '', '');
                    $reportScan = hms_text($report['Scan'] ?? '', '');
                    ?>
                    <div class="bg-white rounded-xl shadow ring-1 ring-gray-200 overflow-hidden">
                        <div class="flex items-center justify-between bg-blue-50 px-6 py-3 border-b border-blue-100">
                            <div>
                                <span class="font-bold text-blue-800 text-base" dir="auto"><?= htmlspecialchars($reportSpecialization) ?></span>
                                <span class="mx-2 text-gray-400">|</span>
                                <span class="text-sm text-gray-600" dir="auto">Dr. <?= htmlspecialchars($reportDoctor) ?></span>
                            </div>
                            <div class="flex items-center gap-3 flex-wrap">
                                <span class="text-sm text-gray-500" dir="auto"><?= htmlspecialchars($reportDate) ?></span>

                                <a href="/modules/<?= strtolower(str_replace(' ', '-', $role === 'System Admin' ? 'super-admin' : ($role === 'Doctor' ? 'doctor' : ($role === 'Patient' ? 'patient' : ($role === 'User' ? 'user' : 'admin'))))) ?>/report.php?ref=<?= urlencode(hms_encrypt_id((int)$report['apid'])) ?>"
                                   class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-sm font-medium text-green-700 ring-1 ring-inset ring-green-600/20 hover:bg-green-100">
                                    <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                                </a>

                                <?php if ($role === 'Doctor' && (int)($_SESSION['id'] ?? 0) === (int)($report['doctorId'] ?? 0)): ?>
                                    <a href="<?= $writeReportBase ?>?edit=1&id=<?= (int)$report['appt_id'] ?>"
                                       class="inline-flex items-center rounded-md bg-yellow-50 px-2 py-1 text-sm font-medium text-yellow-700 ring-1 ring-inset ring-yellow-600/20 hover:bg-yellow-100">
                                        <i class="bi bi-pencil-square me-1"></i> Edit
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="px-6 py-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <?php if ($reportPrescription !== ''): ?>
                            <div class="sm:col-span-1">
                                <p class="text-xs text-gray-500 font-medium uppercase mb-1">Treatment / Prescription</p>
                                <p class="text-sm text-gray-800 hms-report-text" dir="auto"><?= htmlspecialchars($reportPrescription) ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($reportDescription !== ''): ?>
                            <div class="sm:col-span-1">
                                <p class="text-xs text-gray-500 font-medium uppercase mb-1">Report / Description</p>
                                <p class="text-sm text-gray-800 hms-report-text" dir="auto"><?= htmlspecialchars($reportDescription) ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($reportScan !== '' && $reportScan !== 'Does not need'): ?>
                            <div class="sm:col-span-2">
                                <p class="text-xs text-gray-500 font-medium uppercase mb-1">Scan Notes</p>
                                <p class="text-sm text-gray-800 hms-report-text" dir="auto"><?= htmlspecialchars($reportScan) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
                </div>

            <?php else: ?>
                <div class="text-center py-16 text-gray-400">
                    <i class="bi bi-clipboard2-x text-5xl block mb-3"></i>
                    <p class="text-lg">No medical reports found for this patient.</p>
                    <?php if ($role === 'Doctor' && $pendingCount > 0): ?>
                        <p class="text-sm mt-2">Use the <strong>Add New Report</strong> button above to add the first report.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
    </main>
</div>

<script src="/assets/js/responsive-nav.js" defer></script>

<!-- AI Summary Modal -->
<div id="historyModal" class="fixed inset-0 z-50 flex items-center justify-center hidden bg-black/40 backdrop-blur-sm p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-2xl max-h-[85vh] overflow-hidden flex flex-col scale-in-center">
        <div class="p-6 border-b flex justify-between items-center bg-indigo-600">
            <h3 class="text-xl font-bold text-white flex items-center gap-2">
                <i class="bi bi-robot"></i> AI Patient Summary
            </h3>
            <button type="button" onclick="document.getElementById('historyModal').classList.add('hidden')" class="text-indigo-100 hover:text-white transition-colors">
                <i class="bi bi-x-lg text-xl"></i>
            </button>
        </div>
        <div class="p-8 overflow-y-auto" id="historySummaryContent">
            <!-- AI Content here -->
        </div>
        <div class="p-4 border-t bg-gray-50 flex justify-end">
            <button type="button" onclick="document.getElementById('historyModal').classList.add('hidden')" 
                class="px-8 py-2.5 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 transition-all shadow-md active:scale-95">
                Got it
            </button>
        </div>
    </div>
</div>

<style>
@keyframes scale-in-center {
    0% { transform: scale(0.9); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}
.scale-in-center { animation: scale-in-center 0.25s cubic-bezier(0.250, 0.460, 0.450, 0.940) both; }
.ai-prose { line-height: 1.8; color: #334155; font-size: 0.95rem; }
.ai-prose b { color: #4f46e5; }
</style>

<?php if ($role === 'Doctor'): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const summarizeBtn = document.getElementById('summarizeHistory');
    const aiStatus = document.getElementById('aiStatus');
    const patientId = <?= $uid ?>;

    if (summarizeBtn) {
        summarizeBtn.addEventListener('click', async () => {
            aiStatus.classList.remove('hidden');
            summarizeBtn.disabled = true;

            try {
                const res = await fetch('/modules/doctor/doctor-ai-api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ action: 'summarize_history', patient_id: patientId })
                });
                const data = await res.json();
                
                if (data.error) throw new Error(data.error);

                document.getElementById('historySummaryContent').innerHTML = `
                    <div class="ai-prose">
                        ${data.summary.replace(/\*\*(.*?)\*\*/g, '<b>$1</b>').replace(/\n/g, '<br>')}
                    </div>`;
                document.getElementById('historyModal').classList.remove('hidden');

            } catch (err) {
                alert('AI Summary Error: ' + err.message);
            } finally {
                aiStatus.classList.add('hidden');
                summarizeBtn.disabled = false;
            }
        });
    }
});
</script>
<?php endif; ?>

<?php if ($role !== 'Patient'): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const btnGenerate = document.getElementById('btnGenerateCode');
    const displayArea = document.getElementById('codeDisplayArea');
    const codeSpan = document.getElementById('theActivationCode');
    
    if (btnGenerate) {
        btnGenerate.addEventListener('click', async () => {
            if (!confirm("Are you sure you want to generate a new Activation Code? Any existing unused code will be overwritten.")) {
                return;
            }
            
            btnGenerate.disabled = true;
            btnGenerate.innerHTML = '<div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white inline-block"></div> Generating...';
            
            try {
                const res = await fetch('/includes/api-generate-activation.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ uid: <?= (int)$uid ?> })
                });
                
                const data = await res.json();
                if (data.status === 'success') {
                    codeSpan.textContent = data.code;
                    displayArea.classList.remove('hidden');
                    btnGenerate.classList.add('hidden');
                } else {
                    alert('Error: ' + data.message);
                    btnGenerate.disabled = false;
                    btnGenerate.textContent = 'Generate Code';
                }
            } catch (err) {
                alert('Request Failed: ' + err.message);
                btnGenerate.disabled = false;
                btnGenerate.textContent = 'Generate Code';
            }
        });
    }
});
</script>
<?php endif; ?>

</body>
</html>
