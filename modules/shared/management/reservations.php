<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../../includes/appointment-helpers.php';

$pageTitle = $pageTitle ?? 'Reservations';
$returnPath = $returnPath ?? hms_management_current_path();

$connect = hms_management_connect();

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

$appointments = mysqli_query($connect, "SELECT appointment.*, doctors.doctorName FROM appointment 
    JOIN doctors ON doctors.id = appointment.doctorId 
    WHERE appointmentDate = CURRENT_DATE() {$statusWhere} 
    ORDER BY postingDate DESC");

// Count by status for badges
$countRes = mysqli_query($connect, "SELECT 
    SUM(CASE WHEN userStatus = 1 AND doctorStatus = 1 THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN userStatus = 0 OR doctorStatus = 0 THEN 1 ELSE 0 END) as cancelled_count,
    SUM(CASE WHEN userStatus = 2 AND doctorStatus = 2 THEN 1 ELSE 0 END) as done_count,
    COUNT(*) as total_count
    FROM appointment WHERE appointmentDate = CURRENT_DATE()");
$counts = mysqli_fetch_assoc($countRes);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="icon" href="/assets/images/echol.png">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <style>
        .filter-tabs { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .filter-tab {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.45rem 1rem; border-radius: 10px;
            font-size: 0.85rem; font-weight: 600;
            text-decoration: none; transition: all 0.2s;
            border: 2px solid transparent;
        }
        .filter-tab:hover { transform: translateY(-1px); }
        .filter-tab.active { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .filter-tab.all { background: #f1f5f9; color: #475569; }
        .filter-tab.all.active { background: #334155; color: white; border-color: #1e293b; }
        .filter-tab.tab-active { background: #eff6ff; color: #2563eb; }
        .filter-tab.tab-active.active { background: #2563eb; color: white; border-color: #1d4ed8; }
        .filter-tab.tab-cancelled { background: #fef2f2; color: #dc2626; }
        .filter-tab.tab-cancelled.active { background: #dc2626; color: white; border-color: #b91c1c; }
        .filter-tab.tab-done { background: #f0fdf4; color: #16a34a; }
        .filter-tab.tab-done.active { background: #16a34a; color: white; border-color: #15803d; }
        .filter-badge { font-size: 0.7rem; padding: 1px 6px; border-radius: 6px; background: rgba(0,0,0,0.1); }
        .cancel-reason-tooltip {
            position: relative; cursor: help;
        }
        .cancel-reason-tooltip .reason-popup {
            display: none; position: absolute; z-index: 50;
            bottom: 100%; left: 50%; transform: translateX(-50%);
            background: #1e293b; color: white; padding: 0.5rem 0.75rem;
            border-radius: 8px; font-size: 0.78rem; white-space: nowrap;
            max-width: 280px; white-space: normal; box-shadow: 0 4px 15px rgba(0,0,0,0.3);
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
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900"><?= htmlspecialchars($pageTitle) ?></h1>
            </div>
        </header>

        <main>
            <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                <!-- Search + Filter -->
                <div class="flex flex-wrap items-end gap-4 mb-4">
                    <form class="flex flex-wrap gap-2 p-3" action="/includes/search-res.php" method="POST">
                        <div>
                            <label for="Search" class="flex text-sm font-medium text-gray-900">Search</label>
                            <div class="mt-1">
                                <input id="Search" name="input" type="Search" required class="font-bold block w-48 rounded-md border-2 px-4 py-1 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                            </div>
                        </div>
                        <div class="flex items-end">
                            <button name="search" type="submit" class="flex w-20 justify-center rounded-md bg-blue-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-indigo-500">Search</button>
                        </div>
                    </form>
                </div>

                <!-- Status Filter Tabs -->
                <div class="filter-tabs">
                    <a href="?status=all" class="filter-tab all <?= $statusFilter === 'all' ? 'active' : '' ?>">
                        <i class="bi bi-list-ul"></i> All
                        <span class="filter-badge"><?= (int)$counts['total_count'] ?></span>
                    </a>
                    <a href="?status=active" class="filter-tab tab-active <?= $statusFilter === 'active' ? 'active' : '' ?>">
                        <i class="bi bi-check-circle"></i> Active
                        <span class="filter-badge"><?= (int)$counts['active_count'] ?></span>
                    </a>
                    <a href="?status=cancelled" class="filter-tab tab-cancelled <?= $statusFilter === 'cancelled' ? 'active' : '' ?>">
                        <i class="bi bi-x-circle"></i> Cancelled
                        <span class="filter-badge"><?= (int)$counts['cancelled_count'] ?></span>
                    </a>
                    <a href="?status=done" class="filter-tab tab-done <?= $statusFilter === 'done' ? 'active' : '' ?>">
                        <i class="bi bi-check-circle-fill"></i> Done
                        <span class="filter-badge"><?= (int)$counts['done_count'] ?></span>
                    </a>
                </div>

                <table class="table table-striped table-hover table-bordered border-gray-400">
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
                                    <th><p class="text-base font-bold text-gray-900 text-center"><?= htmlspecialchars((string)$row['apid']) ?></p></th>
                                    <th><p class="text-base font-bold text-gray-900 text-center"><?= htmlspecialchars($row['patient_Name']) ?></p></th>
                                    <th><p class="text-base font-bold text-gray-900 text-center"><?= htmlspecialchars($row['doctorName']) ?></p></th>
                                    <th><p class="text-base font-bold text-gray-900 text-center"><?= htmlspecialchars($row['appointmentDate']) ?></p></th>
                                    <th class="text-center align-middle">
                                        <?= appt_status_badge($row) ?>
                                        <?php
                                        $cancelReason = trim($row['cancel_reason'] ?? '');
                                        $isCancelled = ((int)($row['userStatus'] ?? 1) === 0 || (int)($row['doctorStatus'] ?? 1) === 0);
                                        if ($isCancelled): ?>
                                            <?= appt_cancelled_by($row) ?>
                                            <?php if ($cancelReason !== ''): ?>
                                                <span class="cancel-reason-tooltip">
                                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-orange-100 text-orange-700 mt-1 cursor-help" style="font-size: 0.7rem;">
                                                        <i class="bi bi-chat-left-text me-1"></i>Reason
                                                    </span>
                                                    <span class="reason-popup"><?= htmlspecialchars($cancelReason) ?></span>
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </th>
                                    <th class="text-center align-middle"><?= appt_payment_badge($row) ?></th>
                                    <th><p class="text-base font-bold text-gray-900 text-center"><?= htmlspecialchars($row['employname']) ?></p></th>
                                    <th><?= appt_action_buttons($row, $returnPath . '?status=' . urlencode($statusFilter)) ?></th>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <p class="text-center text-gray-500 py-4 mb-0">
                                        <?php if ($statusFilter !== 'all'): ?>
                                            No <?= htmlspecialchars($statusFilter) ?> appointments found for today.
                                        <?php else: ?>
                                            No appointments found for today.
                                        <?php endif; ?>
                                    </p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>

