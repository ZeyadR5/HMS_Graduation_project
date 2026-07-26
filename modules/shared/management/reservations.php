<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../../includes/appointment-helpers.php';

$pageTitle = $pageTitle ?? 'Reservations';
$returnPath = $returnPath ?? hms_management_current_path();

$connect = hms_management_connect();

// Get dates from request
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

$dateCondition = "appointmentDate = CURRENT_DATE()";
$titleDate = "Today (" . date('Y-m-d') . ")";

if ($startDate !== '' && $endDate !== '') {
    $startDateEsc = mysqli_real_escape_string($connect, $startDate);
    $endDateEsc = mysqli_real_escape_string($connect, $endDate);
    $dateCondition = "appointmentDate BETWEEN '$startDateEsc' AND '$endDateEsc'";
    $titleDate = "from " . htmlspecialchars($startDate) . " to " . htmlspecialchars($endDate);
} elseif ($startDate !== '') {
    $startDateEsc = mysqli_real_escape_string($connect, $startDate);
    $dateCondition = "appointmentDate = '$startDateEsc'";
    $titleDate = "for " . htmlspecialchars($startDate);
} elseif ($endDate !== '') {
    $endDateEsc = mysqli_real_escape_string($connect, $endDate);
    $dateCondition = "appointmentDate = '$endDateEsc'";
    $titleDate = "for " . htmlspecialchars($endDate);
}

// Status filter
$statusFilter = $_GET['status'] ?? 'all';
$statusWhere = '';
switch ($statusFilter) {
    case 'active':
        $statusWhere = 'AND appointment.userStatus = 1 AND appointment.doctorStatus = 1';
        break;
    case 'cancelled':
        $statusWhere = 'AND (appointment.userStatus = 0 OR appointment.doctorStatus = 0)';
        break;
    case 'done':
        $statusWhere = 'AND appointment.userStatus = 2 AND appointment.doctorStatus = 2';
        break;
    default:
        $statusFilter = 'all';
        break;
}

// Count matching rows
$countQuery = mysqli_query($connect, "SELECT COUNT(*) as total FROM appointment 
    WHERE $dateCondition {$statusWhere}");
$totalRows = 0;
if ($countQuery) {
    $totalRows = (int)($countQuery->fetch_assoc()['total'] ?? 0);
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$totalPages = ceil($totalRows / $perPage);
$offset = ($page - 1) * $perPage;

$appointments = mysqli_query($connect, "SELECT appointment.*, doctors.doctorName FROM appointment 
    JOIN doctors ON doctors.id = appointment.doctorId 
    WHERE $dateCondition {$statusWhere} 
    ORDER BY postingDate DESC
    LIMIT $perPage OFFSET $offset");

// Count by status for badges
$countRes = mysqli_query($connect, "SELECT 
    SUM(CASE WHEN userStatus = 1 AND doctorStatus = 1 THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN userStatus = 0 OR doctorStatus = 0 THEN 1 ELSE 0 END) as cancelled_count,
    SUM(CASE WHEN userStatus = 2 AND doctorStatus = 2 THEN 1 ELSE 0 END) as done_count,
    COUNT(*) as total_count
    FROM appointment WHERE $dateCondition");
$counts = mysqli_fetch_assoc($countRes);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>
<link rel="icon" href="/assets/images/echol.png">
    <link rel="stylesheet" href="/assets/css/responsive.css">
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
        .cancel-reason-tooltip {
            position: relative; cursor: help;
        }
        .cancel-reason-tooltip .reason-popup {
            display: none; position: absolute; z-index: 50;
            bottom: 100%; left: 50%; transform: translateX(-50%);
            background: #1e293b; color: white; padding: 0.5rem 0.75rem;
            border-radius: 8px; font-size: 0.78rem;
            max-width: 280px; box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            margin-bottom: 6px;
        }
        .cancel-reason-tooltip:hover .reason-popup { display: block; }
        .reason-popup::after {
            content: ''; position: absolute; top: 100%; left: 50%; transform: translateX(-50%);
            border: 6px solid transparent; border-top-color: #1e293b;
        }
    </style>
</head>
<body>
    <div class="min-h-full">
        <?php $activePage = 'reservations'; require __DIR__ . '/../../../includes/nav.php'; ?>

        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900"><?= htmlspecialchars($pageTitle) ?></h1>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-sm font-semibold bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-700/10">
                    <i class="bi bi-calendar3"></i> Reservations <?= $titleDate ?>
                </span>
            </div>
        </header>

        <main>
            <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                <!-- Search & Date Filter Bar -->
                <div class="flex flex-wrap items-end justify-between gap-4 p-4 mb-6 bg-gray-50 rounded-2xl border border-gray-200 shadow-sm">
                    <!-- Text Search Form -->
                    <form class="flex items-end gap-3 flex-wrap" action="/includes/search-res.php" method="POST">
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
                    <form class="flex items-end gap-3 flex-wrap" method="GET" action="<?= htmlspecialchars($returnPath) ?>">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
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
                                <a href="<?= htmlspecialchars($returnPath) ?>?status=<?= urlencode($statusFilter) ?>" 
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
                                <th><p class="text-lg font-bold text-gray-900 text-center">ID</p></th>
                                <th><p class="text-lg font-bold text-gray-900 text-center">Patient Name</p></th>
                                <th><p class="text-lg font-bold text-gray-900 text-center">Doctor</p></th>
                                <th><p class="text-lg font-bold text-gray-900 text-center">Date</p></th>
                                <th><p class="text-lg font-bold text-gray-900 text-center">Status</p></th>
                                <th><p class="text-lg font-bold text-gray-900 text-center">Payment</p></th>
                                <th><p class="text-lg font-bold text-gray-900 text-center">By</p></th>
                                <th><p class="text-lg font-bold text-gray-900 text-center">Actions</p></th>
                            </tr>
                        </thead>
                        <tbody class="table-group-divider">
                            <?php if ($appointments && mysqli_num_rows($appointments) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($appointments)): ?>
                                    <tr>
                                        <td><p class="text-base font-bold text-gray-900 text-center"><?= htmlspecialchars((string)$row['apid']) ?></p></td>
                                        <td><p class="text-base font-bold text-gray-900 text-center"><?= htmlspecialchars($row['patient_Name']) ?></p></td>
                                        <td><p class="text-base font-bold text-gray-900 text-center"><?= htmlspecialchars($row['doctorName']) ?></p></td>
                                        <td>
                                            <p class="text-base font-bold text-indigo-600 text-center mb-0"><?= htmlspecialchars($row['appointmentDate']) ?></p>
                                            <p class="text-xs text-gray-400 text-center mb-0"><?= htmlspecialchars($row['appointmentTime'] ?: '—') ?></p>
                                        </td>
                                        <td class="text-center align-middle">
                                            <?= get_detailed_status_badge($row) ?>
                                            <?php
                                            $cancelReason = trim($row['cancel_reason'] ?? '');
                                            $isCancelled = ((int)($row['userStatus'] ?? 1) === 0 || (int)($row['doctorStatus'] ?? 1) === 0);
                                            if ($isCancelled): ?>
                                                <?= appt_cancelled_by($row) ?>
                                                <?php if ($cancelReason !== ''): ?>
                                                    <span class="cancel-reason-tooltip block mt-1">
                                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-orange-100 text-orange-700 cursor-help" style="font-size: 0.7rem;">
                                                            <i class="bi bi-chat-left-text me-1"></i>Reason
                                                        </span>
                                                        <span class="reason-popup"><?= htmlspecialchars($cancelReason) ?></span>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center align-middle"><?= appt_payment_badge($row) ?></td>
                                        <?php
                                        $byName = trim($row['employname'] ?? '');
                                        // قيم قديمة زي 0 أو رقم بس → Patient
                                        if ($byName === '' || $byName === '0' || ctype_digit($byName)) {
                                            $byName = 'Patient';
                                        }
                                        ?>
                                        <td><p class="text-base font-bold text-gray-900 text-center"><?= htmlspecialchars($byName) ?></p></td>
                                        <td class="text-center align-middle"><?= appt_action_buttons($row, $returnPath . '?status=' . urlencode($statusFilter) . '&start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate)) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8">
                                        <p class="text-center text-gray-500 py-6 mb-0 font-bold">
                                            <?php if ($statusFilter !== 'all'): ?>
                                                No <?= htmlspecialchars($statusFilter) ?> appointments found <?= htmlspecialchars($titleDate) ?>.
                                            <?php else: ?>
                                                No appointments found <?= htmlspecialchars($titleDate) ?>.
                                            <?php endif; ?>
                                        </p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination UI -->
                <?php if (isset($totalPages) && $totalPages > 1): ?>
                <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 mt-4">
                    <div class="flex flex-1 justify-between sm:hidden">
                        <?php if ($page > 1): ?>
                            <a href="?status=<?= urlencode($statusFilter) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&page=<?= $page - 1 ?>" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?status=<?= urlencode($statusFilter) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&page=<?= $page + 1 ?>" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
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
                                    <a href="?status=<?= urlencode($statusFilter) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&page=<?= $page - 1 ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50"><i class="bi bi-chevron-left"></i></a>
                                <?php endif; ?>
                                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <a href="?status=<?= urlencode($statusFilter) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&page=<?= $p ?>" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?= $p === $page ? 'bg-blue-600 text-white' : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50' ?>"><?= $p ?></a>
                                <?php endfor; ?>
                                <?php if ($page < $totalPages): ?>
                                    <a href="?status=<?= urlencode($statusFilter) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&page=<?= $page + 1 ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50"><i class="bi bi-chevron-right"></i></a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </main>
    </div>
    <script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>
