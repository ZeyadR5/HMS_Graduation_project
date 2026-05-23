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

$sql = mysqli_query(
    $connect,
    "SELECT DISTINCT appointment.userId as uid,
            COALESCE(users.fullName, appointment.patient_Name) as fullName
     FROM appointment
     LEFT JOIN users ON users.uid = appointment.userId
     WHERE appointment.userStatus IN (1,2)
       AND appointment.userId IS NOT NULL
       AND appointment.userId <> 0
     $whereExtra
     $searchWhere
     ORDER BY postingDate DESC"
);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Record</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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

        </div>
    </main>
</div>

<script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>
