<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../../includes/payment-config.php';
require_once __DIR__ . '/../../../includes/secure-token.php';
ini_set("display_errors", 0);

$connect = hms_management_connect();
$employId = intval($_GET['employId'] ?? 0);
$today = date('Y-m-d');
$filterSql = " AND appointmentDate = '{$today}'";
if ($employId > 0) {
    $filterSql .= " AND employId = {$employId}";
}

$titleSuffix = $employId > 0 ? " for selected user today" : " for today";
$total = 0;
$employees = $connect->query("SELECT id, username FROM employ ORDER BY username ASC");
$totalQuery = $connect->query("SELECT SUM(paid) AS total FROM appointment WHERE userStatus IN (1,2) AND doctorStatus IN (1,2) AND paid > 0 {$filterSql}");
if ($totalQuery && ($row = $totalQuery->fetch_assoc())) {
    $total = (int)($row['total'] ?? 0);
}
$payments = $connect->query("SELECT * FROM appointment WHERE userStatus IN (1,2) AND doctorStatus IN (1,2) AND (paid > 0 OR deposit_status = 'paid') {$filterSql} ORDER BY postingDate DESC");

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <link rel="icon" href="/assets/images/echol.png">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
                <form class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4 items-end p-4 mb-6 bg-white rounded-lg shadow-sm" action="<?= htmlspecialchars($searchPath) ?>" method="POST">
                    <div>
                        <label for="input" class="block text-sm font-medium text-gray-900">Patient / Invoice</label>
                        <div class="mt-1">
                            <input id="input" name="input" type="search" class="block w-full rounded-md border-2 px-4 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 font-bold" placeholder="Patient name, ID, phone, invoice">
                        </div>
                    </div>
                    <div>
                        <label for="employId" class="block text-sm font-medium text-gray-900">User</label>
                        <div class="mt-1">
                            <select id="employId" name="employId" class="block w-full rounded-md border-2 px-4 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300">
                                <option value="">All users</option>
                                <?php if ($employees): ?>
                                    <?php while ($employee = $employees->fetch_assoc()): ?>
                                        <option value="<?= (int)$employee['id'] ?>" <?= $employId === (int)$employee['id'] ? 'selected' : '' ?>><?= htmlspecialchars($employee['username']) ?></option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="fromDate" class="block text-sm font-medium text-gray-900">From</label>
                        <div class="mt-1">
                            <input id="fromDate" name="fromDate" type="date" value="<?= htmlspecialchars($today) ?>" class="block w-full rounded-md border-2 px-4 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300">
                        </div>
                    </div>
                    <div>
                        <label for="toDate" class="block text-sm font-medium text-gray-900">To</label>
                        <div class="mt-1">
                            <input id="toDate" name="toDate" type="date" value="<?= htmlspecialchars($today) ?>" class="block w-full rounded-md border-2 px-4 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300">
                        </div>
                    </div>
                    <div>
                        <button type="submit" class="w-full flex justify-center rounded-md bg-blue-600 px-4 py-2.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-blue-500 transition-all">Search</button>
                    </div>
                </form>

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
                                    <th><p class="text-lg font-bold text-gray-900 text-center"><?= htmlspecialchars($row['employname']) ?></p></th>
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
            </div>
        </main>
    </div>
    <script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>

