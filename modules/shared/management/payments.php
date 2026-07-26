<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../../includes/payment-config.php';
require_once __DIR__ . '/../../../includes/secure-token.php';
ini_set("display_errors", 0);

$connect = hms_management_connect();
$employId = intval($_GET['employId'] ?? 0);
$today = date('Y-m-d');

$startDate = isset($_GET['fromDate']) ? trim($_GET['fromDate']) : '';
$endDate = isset($_GET['toDate']) ? trim($_GET['toDate']) : '';

if ($startDate === '' && $endDate === '') {
    $startDate = $today;
    $endDate = $today;
}

$dateCondition = "appointmentDate = '{$today}'";
$titleDate = "for today";

if ($startDate !== '' && $endDate !== '') {
    $startDateEsc = mysqli_real_escape_string($connect, $startDate);
    $endDateEsc = mysqli_real_escape_string($connect, $endDate);
    $dateCondition = "appointmentDate BETWEEN '$startDateEsc' AND '$endDateEsc'";
    if ($startDate === $today && $endDate === $today) {
        $titleDate = "for today";
    } else {
        $titleDate = "from " . htmlspecialchars($startDate) . " to " . htmlspecialchars($endDate);
    }
} elseif ($startDate !== '') {
    $startDateEsc = mysqli_real_escape_string($connect, $startDate);
    $dateCondition = "appointmentDate = '$startDateEsc'";
    if ($startDate === $today) {
        $titleDate = "for today";
    } else {
        $titleDate = "for " . htmlspecialchars($startDate);
    }
} elseif ($endDate !== '') {
    $endDateEsc = mysqli_real_escape_string($connect, $endDate);
    $dateCondition = "appointmentDate = '$endDateEsc'";
    if ($endDate === $today) {
        $titleDate = "for today";
    } else {
        $titleDate = "for " . htmlspecialchars($endDate);
    }
}

$filterSql = " AND " . $dateCondition;
if ($employId > 0) {
    $filterSql .= " AND employId = {$employId}";
}

$titleSuffix = " " . $titleDate . ($employId > 0 ? " (selected user)" : "");
$total = 0;
$employees = $connect->query("SELECT id, username FROM employ ORDER BY username ASC");
$totalQuery = $connect->query("SELECT SUM(paid) AS total FROM appointment WHERE userStatus IN (1,2) AND doctorStatus IN (1,2) AND paid > 0 {$filterSql}");
if ($totalQuery && ($row = $totalQuery->fetch_assoc())) {
    $total = (int)($row['total'] ?? 0);
}
// Count matching rows
$countQuery = $connect->query("SELECT COUNT(*) AS total FROM appointment WHERE userStatus IN (1,2) AND doctorStatus IN (1,2) AND (paid > 0 OR deposit_status = 'paid') {$filterSql}");
$totalRows = 0;
if ($countQuery && ($row = $countQuery->fetch_assoc())) {
    $totalRows = (int)($row['total'] ?? 0);
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$totalPages = ceil($totalRows / $perPage);
$offset = ($page - 1) * $perPage;

$payments = $connect->query("SELECT * FROM appointment WHERE userStatus IN (1,2) AND doctorStatus IN (1,2) AND (paid > 0 OR deposit_status = 'paid') {$filterSql} ORDER BY postingDate DESC LIMIT $perPage OFFSET $offset");

// Calculate deposit totals
$depositTotal = 0;
$depositQuery = $connect->query("SELECT SUM(deposit_amount) AS dtotal FROM appointment WHERE userStatus IN (1,2) AND doctorStatus IN (1,2) AND deposit_status = 'paid' {$filterSql}");
if ($depositQuery && ($dRow = $depositQuery->fetch_assoc())) {
    $depositTotal = (int)($dRow['dtotal'] ?? 0);
}
$grandTotal = $total + $depositTotal;

$searchPath = hms_management_sibling_path('search-pay.php');
$receiptPath = hms_management_sibling_path('receipt.php');
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <link rel="icon" href="/assets/images/echol.png">

    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>
</head>
<body>
    <div class="min-h-full">
        <?php $activePage = 'payments'; require __DIR__ . '/../../../includes/nav.php'; ?>

        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">Payments<?= htmlspecialchars($titleSuffix) ?></h1>
            </div>
        </header>

        <main>
            <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-end justify-between gap-4 p-4 mb-6 bg-gray-50 rounded-2xl border border-gray-200 shadow-sm">
                    <!-- Text Search Form -->
                    <form class="flex items-end gap-3 flex-wrap" action="<?= htmlspecialchars($searchPath) ?>" method="POST">
                        <div>
                            <label for="Search" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Search Patient</label>
                            <input id="Search" name="input" type="search" placeholder="Patient name, ID, phone, invoice..." required 
                                   class="block font-bold w-64 rounded-xl border border-gray-300 px-4 py-2 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <button name="search" type="submit" 
                                class="inline-flex items-center justify-center rounded-xl bg-slate-900 hover:bg-black px-5 py-2.5 text-sm font-black uppercase tracking-wider text-white shadow-md transition-all active:scale-95">
                            <i class="bi bi-search me-1.5"></i> Search
                        </button>
                    </form>

                    <!-- Date & User Filter Form -->
                    <form class="flex items-end gap-3 flex-wrap" method="GET" action="">
                        <div>
                            <label for="employId" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">User</label>
                            <select id="employId" name="employId" class="block font-bold rounded-xl border border-gray-300 px-4 py-2 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">All users</option>
                                <?php if ($employees): ?>
                                    <?php while ($employee = $employees->fetch_assoc()): ?>
                                        <option value="<?= (int)$employee['id'] ?>" <?= $employId === (int)$employee['id'] ? 'selected' : '' ?>><?= htmlspecialchars($employee['username']) ?></option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div>
                            <label for="fromDate" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">From Date</label>
                            <input type="date" id="fromDate" name="fromDate" 
                                   value="<?= htmlspecialchars($startDate) ?>"
                                   class="block font-bold rounded-xl border border-gray-300 px-4 py-2 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="toDate" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">To Date</label>
                            <input type="date" id="toDate" name="toDate" 
                                   value="<?= htmlspecialchars($endDate) ?>"
                                   class="block font-bold rounded-xl border border-gray-300 px-4 py-2 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" 
                                    class="inline-flex items-center justify-center rounded-xl bg-blue-600 hover:bg-blue-700 px-5 py-2.5 text-sm font-black uppercase tracking-wider text-white shadow-md transition-all active:scale-95">
                                <i class="bi bi-funnel-fill me-1.5"></i> Filter
                            </button>
                            <?php if ($startDate !== $today || $endDate !== $today || $employId > 0): ?>
                                <a href="?" 
                                   class="inline-flex items-center justify-center rounded-xl bg-gray-200 hover:bg-gray-300 px-4 py-2.5 text-sm font-bold text-gray-700 shadow-sm transition-all active:scale-95">
                                    <i class="bi bi-x-lg me-1.5"></i> Reset
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="overflow-x-auto w-full bg-white rounded-lg shadow-sm ring-1 ring-gray-200">
                    <table class="table table-striped table-hover table-bordered border-gray-400 mb-0 min-w-full">
                    <caption class="caption-bottom border-2 border-gray-400 bg-gray-300">
                        <div class="p-2 flex flex-wrap justify-center gap-6">
                            <p class="text-base font-bold text-black">Cash/Counter: <span class="text-green-700"><?= number_format($total) ?> <?= HMS_CURRENCY ?></span></p>
                            <p class="text-base font-bold text-black">Online Deposits: <span class="text-blue-700"><?= number_format($depositTotal) ?> <?= HMS_CURRENCY ?></span></p>
                            <p class="text-lg font-bold text-black">Grand Total: <span class="text-indigo-700"><?= number_format($grandTotal) ?> <?= HMS_CURRENCY ?></span></p>
                        </div>
                    </caption>
                    <thead>
                        <tr>
                            <th><p class="text-lg font-bold text-gray-900 text-center">Patient ID</p></th>
                            <th><p class="text-lg font-bold text-gray-900 text-center">Patient Name</p></th>
                            <th><p class="text-lg font-bold text-gray-900 text-center">Reservation</p></th>
                            <th><p class="text-lg font-bold text-gray-900 text-center">Deposit</p></th>
                            <th><p class="text-lg font-bold text-gray-900 text-center">Paid</p></th>
                            <th><p class="text-lg font-bold text-gray-900 text-center">By</p></th>
                            <th><p class="text-lg font-bold text-gray-900 text-center">Print</p></th>
                        </tr>
                    </thead>
                    <tbody class="table-group-divider">
                        <?php if ($payments && $payments->num_rows > 0): ?>
                            <?php while ($row = $payments->fetch_assoc()): ?>
                                <tr>
                                    <th><p class="text-lg font-bold text-gray-900 text-center"><?= htmlspecialchars((string)$row['userId']) ?></p></th>
                                    <th><p class="text-lg font-bold text-gray-900 text-center"><?= htmlspecialchars($row['patient_Name']) ?></p></th>
                                    <th><p class="text-lg font-bold text-gray-900 text-center"><?= htmlspecialchars($row['doctorSpecialization']) ?></p></th>
                                    <th class="text-center">
                                        <?php
                                        $depStatus = $row['deposit_status'] ?? 'none';
                                        $depAmount = (int)($row['deposit_amount'] ?? 0);
                                        if ($depStatus === 'paid' && $depAmount > 0): ?>
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-blue-100 text-blue-700">
                                                <?= number_format($depAmount) ?> <span class="ms-1 text-blue-400"><?= hms_payment_channel_label($row['deposit_channel'] ?? '') ?></span>
                                            </span>
                                        <?php elseif ($depStatus === 'pending'): ?>
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-yellow-100 text-yellow-700">Pending</span>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs">—</span>
                                        <?php endif; ?>
                                    </th>
                                    <th>
                                        <p class="text-lg font-bold text-gray-900 text-center">
                                            <?= htmlspecialchars((string)$row['paid']) ?>
                                            <?php if ((int)($row['discount'] ?? 0) > 0): ?>
                                                <span class="block text-xs font-medium text-blue-600" title="Reason: <?= htmlspecialchars($row['discount_reason'] ?? 'N/A') ?>">
                                                    (-<?= number_format($row['discount']) ?> Discount)
                                                </span>
                                            <?php endif; ?>
                                        </p>
                                    </th>
                                    <?php
                                    $byName = trim($row['employname'] ?? '');
                                    if ($byName === '' || $byName === '0' || ctype_digit($byName)) {
                                        $byName = 'Patient';
                                    }
                                    ?>
                                    <th><p class="text-lg font-bold text-gray-900 text-center"><?= htmlspecialchars($byName) ?></p></th>
                                    <th><p class="text-lg font-bold text-gray-900 text-center"><a href="<?= htmlspecialchars($receiptPath) ?>?ref=<?= urlencode(hms_encrypt_id((int)$row['apid'])) ?>" class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-lg font-medium text-green-700 ring-1 ring-inset ring-green-600/20">View</a></p></th>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7"><p class="text-center text-gray-500 py-4 mb-0">No payments found for today.</p></td>
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
                            <a href="?employId=<?= $employId ?>&fromDate=<?= urlencode($startDate) ?>&toDate=<?= urlencode($endDate) ?>&page=<?= $page - 1 ?>" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?employId=<?= $employId ?>&fromDate=<?= urlencode($startDate) ?>&toDate=<?= urlencode($endDate) ?>&page=<?= $page + 1 ?>" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
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
                                    <a href="?employId=<?= $employId ?>&fromDate=<?= urlencode($startDate) ?>&toDate=<?= urlencode($endDate) ?>&page=<?= $page - 1 ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50"><i class="bi bi-chevron-left"></i></a>
                                <?php endif; ?>
                                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <a href="?employId=<?= $employId ?>&fromDate=<?= urlencode($startDate) ?>&toDate=<?= urlencode($endDate) ?>&page=<?= $p ?>" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?= $p === $page ? 'bg-blue-600 text-white' : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50' ?>"><?= $p ?></a>
                                <?php endfor; ?>
                                <?php if ($page < $totalPages): ?>
                                    <a href="?employId=<?= $employId ?>&fromDate=<?= urlencode($startDate) ?>&toDate=<?= urlencode($endDate) ?>&page=<?= $page + 1 ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50"><i class="bi bi-chevron-right"></i></a>
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

