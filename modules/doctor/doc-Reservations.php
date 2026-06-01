<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/secure-token.php';
require_once "../../includes/appointment-helpers.php";

$connect = hms_db_connect();
if ($connect->connect_error) {
    die("Connection failed: " . $connect->connect_error);
}

// Auto-reschedule patients who are 15+ minutes late
require_once __DIR__ . '/../../includes/auto-reschedule.php';
hms_check_late_patients($connect);
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Flow & Queue Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" href="../../assets/images/echol.png">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/edit-button.css">

    <style>
        body { font-family: 'Tajawal', sans-serif; background-color: #f1f5f9; }
        .status-pill { padding: 4px 12px; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .status-waiting { background: #e2e8f0; color: #475569; }
        .status-progress { background: #dcfce7; color: #166534; animation: pulse 2s infinite; }
        .status-done { background: #f1f5f9; color: #94a3b8; }
        .priority-urgent { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        .card-patient { transition: all 0.2s; border: none; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .card-patient:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
    </style>
</head>

<body>
    <div class="min-h-full">
        <?php $activePage = 'reservations'; require_once __DIR__ . '/../../includes/nav.php'; ?>

        <header class="bg-white border-b border-gray-200">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 flex flex-col sm:flex-row justify-between items-center gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold tracking-tight text-gray-900 flex items-center gap-3">
                        <i class="bi bi-people-fill text-indigo-600"></i> Patient Flow Console
                    </h1>
                    <p class="text-gray-500 mt-1">Manage your today's queue and triage priorities.</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="/modules/shared/queue-screen.php" target="_blank" class="flex items-center gap-2 rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-black transition-all">
                        <i class="bi bi-display"></i> Launch Queue Board
                    </a>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl py-8 sm:px-6 lg:px-8">
            
            <!-- Quick Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
                <?php
                $docid = $_SESSION['id'];
                $statsSql = mysqli_query($connect, "SELECT 
                    COUNT(CASE WHEN patient_status='waiting' THEN 1 END) as waiting,
                    COUNT(CASE WHEN patient_status='in progress' THEN 1 END) as active,
                    COUNT(CASE WHEN priority='urgent' THEN 1 END) as urgent
                    FROM appointment WHERE doctorId='$docid' AND appointmentDate = CURRENT_DATE() AND userStatus=1");
                $stats = mysqli_fetch_assoc($statsSql);
                ?>
                <div class="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-slate-300">
                    <span class="text-xs font-bold text-slate-500 uppercase">Waiting</span>
                    <h2 class="text-3xl font-black text-slate-800 mt-1"><?= $stats['waiting'] ?></h2>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-emerald-500">
                    <span class="text-xs font-bold text-emerald-500 uppercase">Currently In Visit</span>
                    <h2 class="text-3xl font-black text-emerald-800 mt-1"><?= $stats['active'] ?></h2>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-rose-500">
                    <span class="text-xs font-bold text-rose-500 uppercase">Urgent Cases</span>
                    <h2 class="text-3xl font-black text-rose-800 mt-1"><?= $stats['urgent'] ?></h2>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm overflow-x-auto ring-1 ring-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-gray-500 text-xs font-bold uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-4">Priority & Status</th>
                            <th class="px-6 py-4">Patient Details</th>
                            <th class="px-6 py-4">Appointment Info</th>
                            <th class="px-6 py-4 text-center">Triage Actions</th>
                            <th class="px-6 py-4 text-right">Workflow</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <?php
                        $sql = mysqli_query($connect, "SELECT appointment.*, users.gender, users.PatientContno, users.uid as patient_uid 
                                                    FROM appointment 
                                                    JOIN users ON users.uid = appointment.userId 
                                                    WHERE doctorId='$docid' AND appointmentDate = CURRENT_DATE() AND userStatus IN (1, 2) 
                                                    ORDER BY (priority = 'urgent') DESC, (patient_status = 'in progress') DESC, postingDate ASC");
                        $qNum = 1;
                        while ($row = mysqli_fetch_array($sql)) {
                            $isUrgent = ($row['priority'] === 'urgent');
                            $status = $row['patient_status'];
                            $isProgress = ($status === 'in progress');
                            $isDone = ($status === 'done');
                            
                            $rowClass = "bg-white";
                            if ($isUrgent) $rowClass = "bg-rose-50/50 border-r-4 border-rose-500 shadow-sm";
                            if ($isProgress) $rowClass = "bg-emerald-50/50 border-r-4 border-emerald-500 shadow-md ring-1 ring-emerald-200 ring-inset";
                            if ($isDone) $rowClass = "bg-slate-50 opacity-75 shadow-none";
                        ?>
                        <tr class="<?= $rowClass ?> transition-all duration-300">
                            <td class="px-6 py-5 whitespace-nowrap">
                                <div class="flex flex-col gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="w-8 h-8 rounded-lg bg-slate-900 text-white flex items-center justify-center font-black text-xs">
                                            <?= $qNum++ ?>
                                        </span>
                                        <select onchange="updatePriority(<?= $row['apid'] ?>, this.value)" 
                                                class="text-[10px] uppercase font-black rounded-lg border-gray-200 px-2 py-1 shadow-sm <?= $isUrgent ? 'bg-rose-500 text-white ring-2 ring-rose-200' : 'bg-white text-gray-500 hover:border-indigo-300' ?>">
                                            <option value="normal" <?= !$isUrgent ? 'selected' : '' ?>>Normal</option>
                                            <option value="urgent" <?= $isUrgent ? 'selected' : '' ?>>⚠️ Urgent</option>
                                        </select>
                                    </div>
                                    <span class="status-pill status-<?= str_replace(' ', '', $status) ?> inline-block w-full text-center shadow-sm">
                                        <i class="bi <?= $isProgress ? 'bi-broadcast' : ($isDone ? 'bi-check-all' : 'bi-clock') ?> me-1"></i>
                                        <?= $status ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-lg font-bold <?= $row['gender'] === 'Male' ? 'bg-blue-100 text-blue-600' : 'bg-pink-100 text-pink-600' ?>">
                                        <i class="bi <?= $row['gender'] === 'Male' ? 'bi-gender-male' : 'bi-gender-female' ?>"></i>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-base font-extrabold text-gray-900 decoration-indigo-500 decoration-2 <?= $isProgress ? 'underline underline-offset-4' : '' ?>">
                                            <?= htmlspecialchars($row['patient_Name']) ?>
                                        </span>
                                        <span class="text-xs text-gray-500 font-bold uppercase tracking-tighter">Patient ID: #<?= $row['patient_uid'] ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                <div class="flex flex-col">
                                    <span class="text-sm font-black text-indigo-700 bg-indigo-50 px-2 py-1 rounded-md w-fit mb-1">
                                        <i class="bi bi-alarm me-1"></i> <?= $row['appointmentTime'] ?: '—' ?>
                                    </span>
                                    <span class="text-[10px] text-gray-400 font-black uppercase tracking-widest"><?= $row['doctorSpecialization'] ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                <div class="flex justify-center items-center gap-2">
                                    <?php if (!$isDone): ?>
                                    <button onclick="updateStatus(<?= $row['apid'] ?>, 'waiting')" class="w-10 h-10 rounded-xl bg-white border border-gray-200 hover:bg-gray-100 text-gray-400 transition-all shadow-sm" title="Revert to Waiting"><i class="bi bi-hourglass-top"></i></button>
                                    <button onclick="updateStatus(<?= $row['apid'] ?>, 'in progress')" class="w-10 h-10 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white transition-all shadow-md active:scale-90 shadow-emerald-200" title="Start Session"><i class="bi bi-play-fill text-lg"></i></button>
                                    <?php else: ?>
                                    <span class="inline-flex items-center gap-1 text-xs font-bold text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-lg"><i class="bi bi-check-circle-fill"></i> Visit Completed</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-5 text-right">
                                <?php 
                                $isReportFiled = (($row['userStatus'] == 2) && ($row['doctorStatus'] == 2));
                                ?>
                                <?php if ($isReportFiled): ?>
                                    <div class="flex flex-col items-end gap-1.5">
                                        <span class="inline-flex items-center gap-1 text-xs font-bold text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-lg">
                                            <i class="bi bi-file-earmark-check-fill"></i> Report Written
                                        </span>
                                        <a href="./doc-write.php?ref=<?= urlencode(hms_encrypt_id((int)$row['apid'])) ?>&edit=1" 
                                           class="Btn mt-2">
                                            Edit 
                                            <svg class="svg" viewBox="0 0 512 512">
                                                <path d="M410.3 231l11.3-11.3-33.9-33.9-62.1-62.1L291.7 89.8l-11.3 11.3-22.6 22.6L58.6 322.9c-10.4 10.4-18 23.3-22.2 37.4L1 480.7c-2.5 8.4-.2 17.5 6.1 23.7s15.3 8.5 23.7 6.1l120.3-35.4c14.1-4.2 27-11.8 37.4-22.2L387.7 253.7 410.3 231zM160 399.4l-9.1 22.7c-4 3.1-8.5 5.4-13.3 6.9L59.4 452l23-78.1c1.4-4.9 3.8-9.4 6.9-13.3l22.7-9.1v32c0 8.8 7.2 16 16 16h32zM362.7 18.7L348.3 33.2 325.7 55.8 314.3 67.1l33.9 33.9 62.1 62.1 33.9 33.9 11.3-11.3 22.6-22.6 14.5-14.5c25-25 25-65.5 0-90.5L453.3 18.7c-25-25-65.5-25-90.5 0zm-47.4 168l-144 144c-6.2 6.2-16.4 6.2-22.6 0s-6.2-16.4 0-22.6l144-144c6.2-6.2 16.4-6.2 22.6 0s6.2 16.4 0 22.6z"></path>
                                            </svg>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <a href="./doc-write.php?ref=<?= urlencode(hms_encrypt_id((int)$row['apid'])) ?>" 
                                       class="inline-flex items-center rounded-xl px-5 py-2.5 text-xs font-black uppercase tracking-wider text-white shadow-lg transition-all active:scale-95 bg-slate-900 hover:bg-black shadow-slate-200">
                                        <i class="bi bi-journal-medical me-2"></i> Write
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <p class="text-center text-xs text-gray-400 mt-6 font-medium">
                <i class="bi bi-info-circle me-1"></i> Appointments are ordered by priority first, then by booking time.
            </p>
        </main>
    </div>

    <script>
    const HMS_CSRF_TOKEN = '<?= htmlspecialchars(hms_csrf_token(), ENT_QUOTES, 'UTF-8') ?>';

    async function updateStatus(apid, status) {
        try {
            const res = await fetch('./queue-api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'update_status', apid: apid, value: status, csrf_token: HMS_CSRF_TOKEN })
            });
            const data = await res.json();
            if (data.success) location.reload();
            else alert('Error: ' + data.error);
        } catch (err) { alert('Request failed'); }
    }

    async function updatePriority(apid, priority) {
        try {
            const res = await fetch('./queue-api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'update_priority', apid: apid, value: priority, csrf_token: HMS_CSRF_TOKEN })
            });
            const data = await res.json();
            if (data.success) location.reload();
            else alert('Error: ' + data.error);
        } catch (err) { alert('Request failed'); }
    }
    </script>
    <script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>
