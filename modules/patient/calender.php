<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/secure-token.php';
require_once __DIR__ . '/../../includes/appointment-helpers.php';

ini_set("display_errors", 0);
$connect = hms_db_connect();
if ($connect->connect_error) {
    die("Connection failed: " . $connect->connect_error);
}

// ── Cancel via calender page ──────────────────────────────────────────────────
// Route through the optimized cancel.php which:
//   1. Disables mirror_email for staff (no synchronous SMTP loop = no page hang)
//   2. Uses a direct PHP header() redirect instead of blocking JS alert()
if (isset($_GET['cancel'], $_GET['id'])) {
    require_once __DIR__ . '/../../includes/secure-token.php';
    $apid = (int)$_GET['id'];
    $ref  = urlencode(hms_encrypt_id($apid));
    $csrf = hms_csrf_query();
    // Redirect to the fast, dedicated cancel handler and come back to calender
    $return = urlencode('./calender.php?cancelled=1');
    header("Location: /modules/patient/cancel.php?ref={$ref}&return={$return}&{$csrf}");
    exit();
}

// Get dates from request
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

$dateCondition = "";
if ($startDate !== '' && $endDate !== '') {
    $startDateEsc = mysqli_real_escape_string($connect, $startDate);
    $endDateEsc = mysqli_real_escape_string($connect, $endDate);
    $dateCondition = "AND appointmentDate BETWEEN '$startDateEsc' AND '$endDateEsc'";
} elseif ($startDate !== '') {
    $startDateEsc = mysqli_real_escape_string($connect, $startDate);
    $dateCondition = "AND appointmentDate = '$startDateEsc'";
} elseif ($endDate !== '') {
    $endDateEsc = mysqli_real_escape_string($connect, $endDate);
    $dateCondition = "AND appointmentDate = '$endDateEsc'";
}

// Status Filter
$statusFilter = $_GET['status'] ?? 'all';
$statusWhere = "";
if ($statusFilter === 'active') {
    $statusWhere = "AND userStatus = 1 AND doctorStatus = 1";
} elseif ($statusFilter === 'cancelled') {
    $statusWhere = "AND (userStatus = 0 OR doctorStatus = 0)";
} elseif ($statusFilter === 'done') {
    $statusWhere = "AND (userStatus = 2 AND doctorStatus = 2)";
}

// Count by status for badges
$userId = (int)($_SESSION['uid'] ?? 0);
$countRes = mysqli_query($connect, "SELECT 
    SUM(CASE WHEN userStatus = 1 AND doctorStatus = 1 THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN userStatus = 0 OR doctorStatus = 0 THEN 1 ELSE 0 END) as cancelled_count,
    SUM(CASE WHEN userStatus = 2 AND doctorStatus = 2 THEN 1 ELSE 0 END) as done_count,
    COUNT(*) as total_count
    FROM appointment WHERE userId = {$userId} $dateCondition");
$counts = mysqli_fetch_assoc($countRes);

?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>
<link rel="icon" href="/assets/images/echol.png">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <link rel="stylesheet" href="./mobile-header-fix.css">
    <style>
        .filter-tabs { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
        .filter-tab {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.5rem 1.25rem; border-radius: 12px;
            font-size: 0.85rem; font-weight: 700;
            text-decoration: none; transition: all 0.2s;
            border: 2px solid transparent;
        }
        .filter-tab:hover { transform: translateY(-1px); }
        .filter-tab.active { box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .filter-tab.all { background: #f1f5f9; color: #475569; }
        .filter-tab.all.active { background: #334155; color: white; border-color: #1e293b; }
        .filter-tab.tab-active { background: #eff6ff; color: #2563eb; }
        .filter-tab.tab-active.active { background: #2563eb; color: white; border-color: #1d4ed8; }
        .filter-tab.tab-cancelled { background: #fef2f2; color: #dc2626; }
        .filter-tab.tab-cancelled.active { background: #dc2626; color: white; border-color: #b91c1c; }
        .filter-tab.tab-done { background: #f0fdf4; color: #16a34a; }
        .filter-tab.tab-done.active { background: #16a34a; color: white; border-color: #15803d; }
        .filter-badge { font-size: 0.7rem; padding: 2px 7px; border-radius: 6px; background: rgba(0,0,0,0.08); font-weight: 800; }
        .filter-tab.active .filter-badge { background: rgba(255,255,255,0.2); }
    </style>
</head>
<body>
    <div class="min-h-full">
        <?php $activePage = 'calendar'; require_once __DIR__ . '/../../includes/nav.php'; ?>

        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">Calendar</h1>
            </div>
        </header>

        <main>
            <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                <!-- Date Filter Bar -->
                <div class="flex flex-wrap items-end justify-between gap-4 p-4 mb-6 bg-gray-50 rounded-2xl border border-gray-200 shadow-sm">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-sm font-semibold bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-700/10">
                            <i class="bi bi-calendar3"></i> 
                            <?php 
                            if ($startDate !== '' && $endDate !== '') {
                                echo "Appointments from " . htmlspecialchars($startDate) . " to " . htmlspecialchars($endDate);
                            } elseif ($startDate !== '') {
                                echo "Appointments from " . htmlspecialchars($startDate);
                            } elseif ($endDate !== '') {
                                echo "Appointments up to " . htmlspecialchars($endDate);
                            } else {
                                echo "All Appointments";
                            }
                            ?>
                        </span>
                    </div>

                    <form class="flex items-end gap-3 flex-wrap" method="GET" action="./calender.php">
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
                                    class="inline-flex items-center justify-center rounded-xl bg-indigo-600 hover:bg-indigo-700 px-5 py-2.5 text-sm font-black uppercase tracking-wider text-white shadow-md transition-all active:scale-95">
                                <i class="bi bi-funnel-fill me-1.5"></i> Filter
                            </button>
                            <?php if ($startDate !== '' || $endDate !== ''): ?>
                                <a href="./calender.php" 
                                   class="inline-flex items-center justify-center rounded-xl bg-gray-200 hover:bg-gray-300 px-4 py-2.5 text-sm font-bold text-gray-700 shadow-sm transition-all active:scale-95">
                                    <i class="bi bi-x-lg me-1.5"></i> Reset
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Status Filter Tabs -->
                <div class="filter-tabs">
                    <a href="?status=all&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" class="filter-tab all <?= $statusFilter === 'all' ? 'active' : '' ?>">
                        <i class="bi bi-list-ul"></i> All
                        <span class="filter-badge"><?= (int)($counts['total_count'] ?? 0) ?></span>
                    </a>
                    <a href="?status=active&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" class="filter-tab tab-active <?= $statusFilter === 'active' ? 'active' : '' ?>">
                        <i class="bi bi-check-circle"></i> Active
                        <span class="filter-badge"><?= (int)($counts['active_count'] ?? 0) ?></span>
                    </a>
                    <a href="?status=cancelled&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" class="filter-tab tab-cancelled <?= $statusFilter === 'cancelled' ? 'active' : '' ?>">
                        <i class="bi bi-x-circle"></i> Cancelled
                        <span class="filter-badge"><?= (int)($counts['cancelled_count'] ?? 0) ?></span>
                    </a>
                    <a href="?status=done&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" class="filter-tab tab-done <?= $statusFilter === 'done' ? 'active' : '' ?>">
                        <i class="bi bi-check-circle-fill"></i> Done
                        <span class="filter-badge"><?= (int)($counts['done_count'] ?? 0) ?></span>
                    </a>
                </div>

                <div class="overflow-x-auto rounded-2xl shadow-sm border border-gray-200 bg-white">
                    <table class="table table-striped table-hover table-bordered border-gray-400 mb-0">
                        <thead>
                            <tr>
                                <th scope="col"><p class="text-lg font-bold text-gray-900 text-center">Dr Name</p></th>
                                <th scope="col"><p class="text-lg font-bold text-gray-900 text-center">Specialization</p></th>
                                <th scope="col"><p class="text-lg font-bold text-gray-900 text-center">Appointment Date</p></th>
                                <th scope="col"><p class="text-lg font-bold text-gray-900 text-center">Posting Date</p></th>
                                <th scope="col"><p class="text-lg font-bold text-gray-900 text-center">Price</p></th>
                                <th scope="col"><p class="text-lg font-bold text-gray-900 text-center">Status</p></th>
                                <th scope="col"><p class="text-lg font-bold text-gray-900 text-center">Actions</p></th>
                            </tr>
                        </thead>
                        <tbody class="table-group-divider">
                            <?php
                            $userId = (int)($_SESSION['uid'] ?? 0);

                            // Count matching rows for pagination
                            $countQuery = mysqli_query($connect, "SELECT COUNT(*) as total FROM appointment WHERE userId = {$userId} {$dateCondition} {$statusWhere}");
                            $totalRows = 0;
                            if ($countQuery) {
                                $totalRows = (int)($countQuery->fetch_assoc()['total'] ?? 0);
                            }

                            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                            $perPage = 20;
                            $totalPages = ceil($totalRows / $perPage);
                            $offset = ($page - 1) * $perPage;

                            $sql = "SELECT doctors.doctorName AS docname, appointment.*
                                    FROM appointment
                                    JOIN doctors ON doctors.id = appointment.doctorId
                                    WHERE appointment.userId = {$userId} {$dateCondition} {$statusWhere}
                                    ORDER BY appointmentDate DESC, appointmentTime DESC, apid DESC
                                    LIMIT {$perPage} OFFSET {$offset}";
                            $allQuery = mysqli_query($connect, $sql);
                            if ($allQuery && mysqli_num_rows($allQuery) > 0):
                                while ($row = mysqli_fetch_assoc($allQuery)):
                            ?>
                                <tr>
                                    <th scope="col"><p class="text-lg font-bold text-gray-900 text-center"><?php echo htmlspecialchars($row['docname']); ?></p></th>
                                    <th scope="col"><p class="text-lg font-bold text-gray-900 text-center"><?php echo htmlspecialchars($row['doctorSpecialization']); ?></p></th>
                                    <th scope="col">
                                        <p class="text-lg font-bold text-indigo-600 text-center mb-0"><?php echo htmlspecialchars($row['appointmentDate']); ?></p>
                                        <p class="text-xs text-gray-400 text-center mb-0"><?php echo htmlspecialchars($row['appointmentTime'] ?: '—'); ?></p>
                                    </th>
                                    <th scope="col"><p class="text-lg font-bold text-gray-900 text-center"><?php echo htmlspecialchars($row['postingDate'] ?: '-'); ?></p></th>
                                    <th scope="col"><p class="text-lg font-bold text-gray-900 text-center"><?php echo htmlspecialchars((string)$row['consultancyFees']); ?></p></th>
                                    <th scope="col" class="text-center align-middle">
                                        <?php echo get_detailed_status_badge($row); ?>
                                        <?php echo appt_cancelled_by($row); ?>
                                    </th>
                                    <th scope="col" class="text-center align-middle">
                                        <?php echo appt_action_buttons($row, '/modules/patient/calender.php'); ?>
                                    </th>
                                </tr>
                            <?php
                                endwhile;
                            else:
                            ?>
                                <tr>
                                    <td colspan="8">
                                        <p class="text-center text-gray-500 py-6 mb-0 font-bold">No reservations found.</p>
                                    </td>
                                </tr>
                            <?php
                            endif;
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination UI -->
                <?php if (isset($totalPages) && $totalPages > 1): ?>
                <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 mt-4">
                    <div class="flex flex-1 justify-between sm:hidden">
                        <?php if ($page > 1): ?>
                            <a href="?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&page=<?= $page - 1 ?>" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&page=<?= $page + 1 ?>" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
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
                                    <a href="?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&page=<?= $page - 1 ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50"><i class="bi bi-chevron-left"></i></a>
                                <?php endif; ?>
                                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <a href="?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&page=<?= $p ?>" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?= $p === $page ? 'bg-indigo-600 text-white' : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50' ?>"><?= $p ?></a>
                                <?php endfor; ?>
                                <?php if ($page < $totalPages): ?>
                                    <a href="?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&page=<?= $page + 1 ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50"><i class="bi bi-chevron-right"></i></a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>

        <script src="/assets/js/responsive-nav.js" defer></script>
    </div>
</body>
</html>
