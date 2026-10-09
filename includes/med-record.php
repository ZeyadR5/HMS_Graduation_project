<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/secure-token.php';
/**
 * med-record.php — صفحة Medical Record موحدة لكل الأدوار
 * بتعرض كل المرضى الفريدين: ID + Name + View (لبروفايل المريض)
 */
ini_set("display_errors", 0);

$connect = hms_db_connect();
if ($connect->connect_error) die("Connection failed: " . $connect->connect_error);

$role = $_SESSION['role'] ?? '';
$activePage = 'medical-record';

$whereExtra = '';
if ($role === 'Doctor') {
    $docid = intval($_SESSION['id'] ?? 0);
    $whereExtra = "AND appointment.doctorId = '$docid'";
} elseif ($role === 'Patient') {
    $uid = intval($_SESSION['uid'] ?? 0);
    $ref = hms_encrypt_id($uid);
    header("Location: /includes/patient-profile.php?ref=" . urlencode($ref));
    exit();
}

$search = mysqli_real_escape_string($connect, $_GET['q'] ?? '');
$searchWhere = $search ? "AND (
    users.fullName LIKE '%$search%' 
    OR users.uid LIKE '%$search%'
    OR users.nat_id LIKE '%$search%'
    OR users.PatientContno LIKE '%$search%'
    OR appointment.patient_Num LIKE '%$search%'
)" : '';

// Count distinct matching rows
$countQuery = mysqli_query(
    $connect,
    "SELECT COUNT(DISTINCT appointment.userId) as total
     FROM appointment
     LEFT JOIN users ON users.uid = appointment.userId
     WHERE appointment.userStatus IN (1,2)
       AND appointment.userId IS NOT NULL
       AND appointment.userId <> 0
     $whereExtra
     $searchWhere"
);
$totalRows = 0;
if ($countQuery) {
    $totalRows = (int)($countQuery->fetch_assoc()['total'] ?? 0);
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 30;
$totalPages = ceil($totalRows / $perPage);
$offset = ($page - 1) * $perPage;

$sql = mysqli_query(
    $connect,
    "SELECT appointment.userId as uid,
            COALESCE(users.fullName, appointment.patient_Name) as fullName,
            MAX(appointment.appointmentDate) as maxDate,
            MAX(appointment.postingDate) as maxPostingDate
     FROM appointment
     LEFT JOIN users ON users.uid = appointment.userId
     WHERE appointment.userStatus IN (1,2)
       AND appointment.userId IS NOT NULL
       AND appointment.userId <> 0
     $whereExtra
     $searchWhere
     GROUP BY appointment.userId, COALESCE(users.fullName, appointment.patient_Name)
     ORDER BY maxDate DESC, maxPostingDate DESC
     LIMIT $perPage OFFSET $offset"
);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Record</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>
    <link rel="icon" href="/assets/images/echol.png">
    <link rel="stylesheet" href="/assets/css/responsive.css">
</head>
<body>
<div class="min-h-full">

    <?php require_once __DIR__ . "/nav.php"; ?>

    <header class="bg-white shadow">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">Medical Record</h1>
        </div>
    </header>

    <main>
        <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">

            <form method="GET" action="/includes/med-record.php" class="flex flex-wrap gap-3 p-3 mb-4">
                <input name="q" type="text" placeholder="Search by name, ID, national ID or phone..."
                    value="<?= htmlspecialchars($search) ?>"
                    class="font-bold rounded-md border-2 px-4 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600 sm:text-sm">
                <button type="submit"
                    class="rounded-md bg-blue-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500">Search</button>
                <?php if ($search): ?>
                    <a href="/includes/med-record.php" class="rounded-md bg-gray-200 px-4 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-300">Clear</a>
                <?php endif; ?>
            </form>

            <table class="table table-striped table-hover table-bordered border-gray-400 w-full">
                <thead>
                    <tr>
                        <th><p class="text-lg font-bold text-gray-900 text-center">Patient ID</p></th>
                        <th><p class="text-lg font-bold text-gray-900 text-center">Patient Name</p></th>
                        <th><p class="text-lg font-bold text-gray-900 text-center">Profile</p></th>
                    </tr>
                </thead>
                <tbody class="table-group-divider">
                <?php if ($sql && $sql->num_rows > 0): ?>
                    <?php while ($row = $sql->fetch_assoc()): ?>
                    <tr>
                        <td><p class="text-lg font-bold text-gray-900 text-center"><?= $row['uid'] ?></p></td>
                        <td><p class="text-lg font-bold text-gray-900 text-center"><?= htmlspecialchars($row['fullName'] ?? 'N/A') ?></p></td>
                        <td class="text-center">
                            <a href="/includes/patient-profile.php?ref=<?= urlencode(hms_encrypt_id((int)$row['uid'])) ?>"
                                class="inline-flex items-center rounded-md bg-blue-50 px-3 py-1 text-sm font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20 hover:bg-blue-100">
                                <i class="bi bi-person-lines-fill me-1"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="text-center text-gray-500 py-6">No patients found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination UI -->
            <?php if (isset($totalPages) && $totalPages > 1): ?>
            <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 mt-4">
                <div class="flex flex-1 justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                        <a href="?q=<?= urlencode($search) ?>&page=<?= $page - 1 ?>" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?q=<?= urlencode($search) ?>&page=<?= $page + 1 ?>" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
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
                                <a href="?q=<?= urlencode($search) ?>&page=<?= $page - 1 ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50"><i class="bi bi-chevron-left"></i></a>
                            <?php endif; ?>
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <a href="?q=<?= urlencode($search) ?>&page=<?= $p ?>" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?= $p === $page ? 'bg-blue-600 text-white' : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50' ?>"><?= $p ?></a>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="?q=<?= urlencode($search) ?>&page=<?= $page + 1 ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50"><i class="bi bi-chevron-right"></i></a>
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
