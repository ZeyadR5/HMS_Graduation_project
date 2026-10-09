<?php
require_once __DIR__ . '/../includes/auth.php';
/**
 * search-res.php — صفحة سيرش Reservations موحدة لكل الأدوار
 */
require_once __DIR__ . "/appointment-helpers.php";
ini_set("display_errors", 0);

$connect = hms_db_connect();
if ($connect->connect_error) die("Connection failed: " . $connect->connect_error);

$role = $_SESSION['role'] ?? '';
$activePage = 'reservations';
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Reservations</title>
    <link rel="stylesheet" href="../css/med_record.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>
<link rel="icon" href="../../assets/images/echol.png">

    <link rel="stylesheet" href="/assets/css/responsive.css">
</head>
<body>
<div class="min-h-full">

    <?php require_once __DIR__ . "/nav.php"; ?>

    <header class="bg-white shadow">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">Search Reservations</h1>
        </div>
    </header>

    <main>
        <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
            <div class="flex flex-wrap w-full">

                <?php
                $resetUrl = match ($role) {
                    'Patient' => '/modules/patient/calender.php',
                    'Doctor' => '/modules/doctor/doc-Reservations.php',
                    'User' => '/modules/user/Reservations.php',
                    'Admin' => '/modules/admin/admin-Reservations.php',
                    'System Admin' => '/modules/super-admin/super-Reservations.php',
                    default => '/modules/dashboard.php'
                };
                ?>
                <div class="flex flex-wrap items-end justify-between gap-4 p-4 mb-6 bg-gray-50 rounded-2xl border border-gray-200 shadow-sm">
                    <form class="flex items-end gap-3 flex-wrap w-full" action="/includes/search-res.php" method="POST">
                        <div class="flex-grow max-w-md">
                            <label for="Search" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Search Reservations</label>
                            <input id="Search" name="input" type="search" placeholder="Name, ID or Number..." required
                                value="<?php echo htmlspecialchars($_REQUEST['input'] ?? $_REQUEST['q'] ?? ''); ?>"
                                class="block w-full font-bold rounded-xl border border-gray-300 px-4 py-2 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <button name="search" type="submit" 
                                class="inline-flex items-center justify-center rounded-xl bg-blue-600 hover:bg-blue-700 px-5 py-2.5 text-sm font-black uppercase tracking-wider text-white shadow-md transition-all active:scale-95">
                            <i class="bi bi-search me-1.5"></i> Search
                        </button>
                        <a href="<?= htmlspecialchars($resetUrl) ?>" class="inline-flex items-center justify-center rounded-xl bg-gray-200 hover:bg-gray-300 px-5 py-2.5 text-sm font-black uppercase tracking-wider text-gray-800 shadow-sm transition-all active:scale-95">
                            <i class="bi bi-arrow-counterclockwise me-1.5"></i> Reset
                        </a>
                    </form>
                </div>

                <table class="table table-striped table-hover table-bordered border-gray-400 w-full">
                    <thead>
                         <tr>
                            <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center">Patient ID</p></th>
                            <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center">Patient Name</p></th>
                            <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center">Reservation</p></th>
                            <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center">Doctor</p></th>
                            <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center">Appointment Date</p></th>
                            <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center">Sign Date</p></th>
                            <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center">Status</p></th>
                            <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center">By</p></th>
                            <th scope="col"><p class="text-lg font-bold text-gray-900 text-center">Payment</p></th>
                            <th scope="col"><p class="text-lg font-bold text-gray-900 text-center">Actions</p></th>
                        </tr>
                    </thead>
                    <tbody class="table-group-divider">
                        <?php
                        $search = trim($_REQUEST['input'] ?? $_REQUEST['q'] ?? '');
                        $searchEsc = mysqli_real_escape_string($connect, $search);

                        if ($search === '') {
                            echo '<tr><td colspan="10" class="text-center text-gray-500 py-6">Please enter a search query above.</td></tr>';
                        } else {
                            // لو الدكتور — بيشوف بس حجوزاته
                            $whereExtra = '';
                            if ($role === 'Doctor') {
                                $docid = intval($_SESSION['id'] ?? 0);
                                $whereExtra = "AND appointment.doctorId = '$docid'";
                            } elseif ($role === 'Patient') {
                                $uid = intval($_SESSION['uid'] ?? 0);
                                $whereExtra = "AND appointment.userId = '$uid'";
                            }

                            // Count total
                            $countQuery = mysqli_query($connect, "SELECT COUNT(*) as total FROM appointment
                                LEFT JOIN doctors ON doctors.id = appointment.doctorId
                                LEFT JOIN users ON users.uid = appointment.userId
                                WHERE (
                                    appointment.patient_Name LIKE '%$searchEsc%'
                                    OR appointment.patient_Num LIKE '%$searchEsc%'
                                    OR appointment.userId LIKE '%$searchEsc%'
                                    OR appointment.apid LIKE '%$searchEsc%'
                                    OR users.nat_id LIKE '%$searchEsc%'
                                    OR users.PatientContno LIKE '%$searchEsc%'
                                    OR users.fullName LIKE '%$searchEsc%'
                                )
                                $whereExtra");
                            $totalRows = 0;
                            if ($countQuery) {
                                $totalRows = (int)($countQuery->fetch_assoc()['total'] ?? 0);
                            }

                            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                            $perPage = 30;
                            $totalPages = ceil($totalRows / $perPage);
                            $offset = ($page - 1) * $perPage;

                            $ret = mysqli_query($connect, "SELECT appointment.*, doctors.doctorName, users.nat_id, users.PatientContno, users.fullName as userFullName FROM appointment
                                LEFT JOIN doctors ON doctors.id = appointment.doctorId
                                LEFT JOIN users ON users.uid = appointment.userId
                                WHERE (
                                    appointment.patient_Name LIKE '%$searchEsc%'
                                    OR appointment.patient_Num LIKE '%$searchEsc%'
                                    OR appointment.userId LIKE '%$searchEsc%'
                                    OR appointment.apid LIKE '%$searchEsc%'
                                    OR users.nat_id LIKE '%$searchEsc%'
                                    OR users.PatientContno LIKE '%$searchEsc%'
                                    OR users.fullName LIKE '%$searchEsc%'
                                )
                                $whereExtra
                                ORDER BY postingDate DESC
                                LIMIT $perPage OFFSET $offset");

                            if ($ret && $ret->num_rows > 0) {
                                while ($row = $ret->fetch_assoc()) {
                            ?>
                                <tr>
                                    <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center"><?php echo $row['userId']; ?></p></th>
                                    <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center"><?php echo htmlspecialchars($row['patient_Name']); ?></p></th>
                                    <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center"><?php echo htmlspecialchars($row['doctorSpecialization']); ?></p></th>
                                    <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center"><?php echo htmlspecialchars($row['doctorName']); ?></p></th>
                                    <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center"><?php echo $row['appointmentDate']; ?></p></th>
                                    <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center"><?php echo $row['postingDate']; ?></p></th>
                                    <th scope="col" class="text-center align-middle">
                                        <?php echo get_detailed_status_badge($row); ?>
                                        <?php echo appt_cancelled_by($row); ?>
                                    </th>
                                    <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center"><?php echo $row['employname']; ?></p></th>
                                    <th scope="col" class="text-center align-middle"><?php echo appt_payment_badge($row); ?></th>
                                    <th scope="col">
                                        <?php echo appt_action_buttons($row, '/includes/search-res.php?q=' . urlencode($search) . '&page=' . $page); ?>
                                    </th>
                                </tr>
                            <?php
                                }
                            } else {
                                echo '<tr><td colspan="10" class="text-center text-gray-500 py-4">No results found.</td></tr>';
                            }
                        }
                        ?>
                    </tbody>
                </table>

                <!-- Pagination UI -->
                <?php if (isset($totalPages) && $totalPages > 1): ?>
                <div class="w-full flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 mt-4">
                    <div class="flex flex-1 justify-between sm:hidden">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
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
                                    <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50"><i class="bi bi-chevron-left"></i></a>
                                <?php endif; ?>
                                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?= $p === $page ? 'bg-blue-600 text-white' : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50' ?>"><?= $p ?></a>
                                <?php endfor; ?>
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50"><i class="bi bi-chevron-right"></i></a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </main>
</div>

   <?php
?>

    <script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>
