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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>
    <link rel="icon" href="../../assets/images/echol.png">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>

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
                    FROM appointment WHERE doctorId='$docid' AND appointmentDate = CURRENT_DATE() AND userStatus=1
                    AND (
                        consultancyFees <= 0
                        OR deposit_status = 'paid'
                        OR (COALESCE(paid,0) + COALESCE(discount,0) + CASE WHEN deposit_status='paid' THEN COALESCE(deposit_amount,0) ELSE 0 END) >= consultancyFees
                    )");
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
                            <th class="px-6 py-4 text-right">Medical Report</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <?php
                        // Payment condition: fees=0 OR deposit paid OR (paid+discount+deposit) >= fees
                        $paidCondition = "(
                            appointment.consultancyFees <= 0
                            OR appointment.deposit_status = 'paid'
                            OR (COALESCE(appointment.paid,0) + COALESCE(appointment.discount,0) + CASE WHEN appointment.deposit_status='paid' THEN COALESCE(appointment.deposit_amount,0) ELSE 0 END) >= appointment.consultancyFees
                        )";

                        $countQuery = mysqli_query($connect, "SELECT COUNT(*) as total
                                                    FROM appointment 
                                                    JOIN users ON users.uid = appointment.userId 
                                                    WHERE doctorId='$docid' AND appointmentDate = CURRENT_DATE() AND userStatus IN (1, 2)
                                                    AND $paidCondition");
                        $totalRows = 0;
                        if ($countQuery) {
                            $totalRows = (int)($countQuery->fetch_assoc()['total'] ?? 0);
                        }

                        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                        $perPage = 20;
                        $totalPages = ceil($totalRows / $perPage);
                        $offset = ($page - 1) * $perPage;

                        $sql = mysqli_query($connect, "SELECT appointment.*, users.gender, users.PatientContno, users.uid as patient_uid 
                                                    FROM appointment 
                                                    JOIN users ON users.uid = appointment.userId 
                                                    WHERE doctorId='$docid' AND appointmentDate = CURRENT_DATE() AND userStatus IN (1, 2)
                                                    AND $paidCondition
                                                    ORDER BY (priority = 'urgent') DESC, (patient_status = 'in progress') DESC, appointmentTime ASC, postingDate ASC
                                                    LIMIT $perPage OFFSET $offset");
                        $qNum = 1 + $offset;
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
                                        <?php if ($isUrgent): ?>
                                            <span class="text-[10px] uppercase font-black rounded-lg bg-rose-500 text-white px-2 py-1 shadow-sm ring-2 ring-rose-200 inline-block text-center">⚠️ Urgent</span>
                                        <?php else: ?>
                                            <span class="text-[10px] uppercase font-black rounded-lg bg-slate-100 text-gray-500 px-2 py-1 border border-slate-200 inline-block text-center">Normal</span>
                                        <?php endif; ?>
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
                            <td class="px-6 py-5 text-right">
                                <?php 
                                $isReportFiled = (($row['userStatus'] == 2) && ($row['doctorStatus'] == 2));
                                
                                // Payment check
                                $fees = (int)($row['consultancyFees'] ?? 0);
                                $paid = (int)($row['paid'] ?? 0);
                                $depositStatus = $row['deposit_status'] ?? 'none';
                                $depositAmount = (int)($row['deposit_amount'] ?? 0);
                                $discount = (int)($row['discount'] ?? 0);
                                $totalCollected = $paid + $discount + ($depositStatus === 'paid' ? $depositAmount : 0);
                                $isPaid = ($fees <= 0 || $totalCollected >= $fees || $depositStatus === 'paid');
                                ?>
                                <?php if ($isReportFiled): ?>
                                    <div class="flex flex-col items-end gap-1.5">
                                        <span class="inline-flex items-center gap-1 text-xs font-bold text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-lg">
                                            <i class="bi bi-file-earmark-check-fill"></i> Report Written
                                        </span>
                                        <a href="./doc-write.php?ref=<?= urlencode(hms_encrypt_id((int)$row['apid'])) ?>&edit=1" 
                                           class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-700/10">
                                            Update Report
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <?php if ($isPaid): ?>
                                        <a href="./doc-write.php?ref=<?= urlencode(hms_encrypt_id((int)$row['apid'])) ?>" 
                                           class="inline-flex items-center rounded-xl px-5 py-2.5 text-xs font-black uppercase tracking-wider text-white shadow-lg transition-all active:scale-95 bg-slate-900 hover:bg-black shadow-slate-200">
                                            <i class="bi bi-journal-medical me-2"></i> Write Report
                                        </a>
                                    <?php else: ?>
                                        <div class="flex flex-col items-end gap-1">
                                            <span class="inline-flex items-center gap-1.5 text-xs font-black uppercase tracking-wider text-rose-700 bg-rose-50 border border-rose-200 px-4 py-2.5 rounded-xl shadow-sm">
                                                <i class="bi bi-cash-stack"></i> Unpaid - Reception
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination UI -->
            <?php if (isset($totalPages) && $totalPages > 1): ?>
            <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 mt-4 rounded-2xl shadow-sm">
                <div class="flex flex-1 justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing <span class="font-medium"><?= $offset + 1 ?></span> to <span class="font-medium"><?= min($offset + $perPage, $totalRows) ?></span> of <span class="font-medium"><?= $totalRows ?></span> results
                        </p>
                    </div>
                    <div>
                        <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50"><i class="bi bi-chevron-left"></i></a>
                            <?php endif; ?>
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <a href="?page=<?= $p ?>" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?= $p === $page ? 'bg-blue-600 text-white' : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50' ?>"><?= $p ?></a>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50"><i class="bi bi-chevron-right"></i></a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <p class="text-center text-xs text-gray-400 mt-6 font-medium">
                <i class="bi bi-info-circle me-1"></i> Appointments are ordered by priority first, then by appointment time.
            </p>
        </main>
    </div>

    <script>
    const HMS_CSRF_TOKEN = '<?= htmlspecialchars(hms_csrf_token(), ENT_QUOTES, 'UTF-8') ?>';
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>
