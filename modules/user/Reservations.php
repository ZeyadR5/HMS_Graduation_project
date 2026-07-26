<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/appointment-helpers.php';

ini_set("display_errors", 0);
$connect = hms_db_connect();
if ($connect->connect_error) {
    die("Connection failed: " . $connect->connect_error);
}

// Get dates from request
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

$dateCondition = "appointmentDate = CURRENT_DATE()";
$titleDate = "Today's Reservations (" . date('Y-m-d') . ")";

if ($startDate !== '' && $endDate !== '') {
    $startDateEsc = mysqli_real_escape_string($connect, $startDate);
    $endDateEsc = mysqli_real_escape_string($connect, $endDate);
    $dateCondition = "appointmentDate BETWEEN '$startDateEsc' AND '$endDateEsc'";
    $titleDate = "Reservations from " . htmlspecialchars($startDate) . " to " . htmlspecialchars($endDate);
} elseif ($startDate !== '') {
    $startDateEsc = mysqli_real_escape_string($connect, $startDate);
    $dateCondition = "appointmentDate = '$startDateEsc'";
    $titleDate = "Reservations for " . htmlspecialchars($startDate);
} elseif ($endDate !== '') {
    $endDateEsc = mysqli_real_escape_string($connect, $endDate);
    $dateCondition = "appointmentDate = '$endDateEsc'";
    $titleDate = "Reservations for " . htmlspecialchars($endDate);
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations</title>
    <link rel="stylesheet" href="../css/Reservations.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>
<link rel="icon" href="../../assets/images/l-gh.png">
    <link rel="stylesheet" href="/assets/css/responsive.css">
</head>
<body>
    <div class="min-h-full">
        <?php $activePage = 'reservations'; require_once __DIR__ . '/../../includes/nav.php'; ?>

        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">Reservations</h1>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-sm font-semibold bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-700/10">
                    <i class="bi bi-calendar3"></i> <?= $titleDate ?>
                </span>
            </div>
        </header>

        <main>
            <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                
                <!-- Search & Date Filter Bar -->
                <div class="flex flex-wrap items-end justify-between gap-4 p-4 mb-6 bg-gray-50 rounded-2xl border border-gray-200 shadow-sm">
                    <!-- Text Search Form -->
                    <form class="flex items-end gap-3 flex-wrap" action="./search-res.php" method="POST">
                        <div>
                            <label for="Search" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Search Patient</label>
                            <input id="Search" name="input" type="search" placeholder="Name, ID or Number..." required 
                                   class="block font-bold w-64 rounded-xl border border-gray-300 px-4 py-2 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <button name="search" type="submit" 
                                class="inline-flex items-center justify-center rounded-xl bg-slate-900 hover:bg-black px-5 py-2.5 text-sm font-black uppercase tracking-wider text-white shadow-md transition-all active:scale-95">
                            <i class="bi bi-search me-1.5"></i> Search
                        </button>
                    </form>

                    <!-- Date Filter Form -->
                    <form class="flex items-end gap-3 flex-wrap" method="GET" action="./Reservations.php">
                        <div>
                            <label for="start_date" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">From Date</label>
                            <input type="date" id="start_date" name="start_date" 
                                   value="<?= htmlspecialchars($startDate) ?>"
                                   class="block font-bold rounded-xl border border-gray-300 px-4 py-2 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="end_date" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">To Date</label>
                            <input type="date" id="end_date" name="end_date" 
                                   value="<?= htmlspecialchars($endDate) ?>"
                                   class="block font-bold rounded-xl border border-gray-300 px-4 py-2 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" 
                                    class="inline-flex items-center justify-center rounded-xl bg-blue-600 hover:bg-blue-700 px-5 py-2.5 text-sm font-black uppercase tracking-wider text-white shadow-md transition-all active:scale-95">
                                <i class="bi bi-funnel-fill me-1.5"></i> Filter
                            </button>
                            <?php if ($startDate !== '' || $endDate !== ''): ?>
                                <a href="./Reservations.php" 
                                   class="inline-flex items-center justify-center rounded-xl bg-gray-200 hover:bg-gray-300 px-4 py-2.5 text-sm font-bold text-gray-700 shadow-sm transition-all active:scale-95">
                                    <i class="bi bi-x-lg me-1.5"></i> Reset
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="overflow-x-auto rounded-2xl shadow-sm border border-gray-200 bg-white">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 text-gray-500 text-xs font-bold uppercase tracking-wider">
                            <tr>
                                <th scope="col" class="px-6 py-4 text-center">Patient ID</th>
                                <th scope="col" class="px-6 py-4 text-center">Patient Name</th>
                                <th scope="col" class="px-6 py-4 text-center">Reservation</th>
                                <th scope="col" class="px-6 py-4 text-center">Doctor</th>
                                <th scope="col" class="px-6 py-4 text-center">Appointment Date</th>
                                <th scope="col" class="px-6 py-4 text-center">Priority</th>
                                <th scope="col" class="px-6 py-4 text-center">Status</th>
                                <th scope="col" class="px-6 py-4 text-center">Payment</th>
                                <th scope="col" class="px-6 py-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <?php
                            $sql = mysqli_query($connect, "SELECT appointment.*, doctors.doctorName FROM appointment
                                JOIN doctors ON doctors.id = appointment.doctorId
                                WHERE $dateCondition
                                ORDER BY (priority = 'urgent') DESC, appointmentTime ASC, apid DESC");

                            $hasRows = false;
                            while ($row = mysqli_fetch_assoc($sql)):
                                $hasRows = true;
                                $isUrgent = ($row['priority'] === 'urgent');
                                $rowClass = $isUrgent ? "bg-rose-50/50 hover:bg-rose-50" : "hover:bg-gray-50/50";
                            ?>
                                <tr class="<?= $rowClass ?> transition-colors">
                                    <td class="px-6 py-4 text-center align-middle text-sm font-bold text-gray-900"><?= htmlspecialchars((string)$row['apid']); ?></td>
                                    <td class="px-6 py-4 text-center align-middle text-sm font-bold text-gray-900"><?= htmlspecialchars($row['patient_Name']); ?></td>
                                    <td class="px-6 py-4 text-center align-middle text-sm font-semibold text-gray-600"><?= htmlspecialchars($row['doctorSpecialization']); ?></td>
                                    <td class="px-6 py-4 text-center align-middle text-sm font-semibold text-gray-800"><?= htmlspecialchars($row['doctorName']); ?></td>
                                    <td class="px-6 py-4 text-center align-middle text-sm font-semibold text-indigo-600">
                                        <div class="flex flex-col items-center">
                                            <span class="font-bold"><?= htmlspecialchars($row['appointmentDate']); ?></span>
                                            <span class="text-xs text-gray-400"><?= htmlspecialchars($row['appointmentTime'] ?: '—'); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 class text-center align-middle">
                                        <?php 
                                        $status = $row['patient_status'] ?? 'waiting';
                                        $isFinishedOrProgress = in_array($status, ['in progress', 'done']);
                                        ?>
                                        <select onchange="updatePriority(<?= $row['apid'] ?>, this.value)" 
                                                <?= $isFinishedOrProgress ? 'disabled' : '' ?>
                                                class="text-xs font-bold rounded-lg border-gray-300 px-2 py-1 shadow-sm <?= $isUrgent ? 'bg-rose-500 text-white ring-2 ring-rose-200' : 'bg-white text-gray-700 border hover:border-gray-400' ?>">
                                            <option value="normal" <?= !$isUrgent ? 'selected' : '' ?>>Normal</option>
                                            <option value="urgent" <?= $isUrgent ? 'selected' : '' ?>>⚠️ Urgent</option>
                                        </select>
                                    </td>
                                    <td class="px-6 py-4 text-center align-middle"><?= appt_status_badge($row); ?></td>
                                    <td class="px-6 py-4 text-center align-middle"><?= appt_payment_badge($row); ?></td>
                                    <td class="px-6 py-4 text-center align-middle"><?= appt_action_buttons($row, '/modules/user/Reservations.php'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                            
                            <?php if (!$hasRows): ?>
                                <tr>
                                    <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <i class="bi bi-calendar-x text-4xl text-gray-300"></i>
                                            <span class="font-bold text-base">No reservations found for the selected date range.</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
    const HMS_CSRF_TOKEN = '<?= htmlspecialchars(hms_csrf_token(), ENT_QUOTES, 'UTF-8') ?>';

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
