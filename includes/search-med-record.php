<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/secure-token.php';
/**
 * search-med-record.php — صفحة سيرش Medical Record موحدة لكل الأدوار
 * 
 * كل search.php في admin/super-admin/doctor/user بتعمل redirect هنا
 */
require_once __DIR__ . "/appointment-helpers.php";
ini_set("display_errors", 0);
$connect = hms_db_connect();
if ($connect->connect_error) die("Connection failed: " . $connect->connect_error);

$role = $_SESSION['role'] ?? '';
$activePage = 'medical-record';

if ($role === 'Patient') {
    $uid = (int)($_SESSION['uid'] ?? 0);
    header("Location: /includes/patient-profile.php?ref=" . urlencode(hms_encrypt_id($uid)));
    exit();
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Medical Record</title>
    <link rel="stylesheet" href="../css/med_record.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="icon" href="../../assets/images/echol.png">
    <link rel="stylesheet" href="/assets/css/responsive.css">
</head>
<body>
<div class="min-h-full">

    <?php require_once __DIR__ . "/nav.php"; ?>

    <header class="bg-white shadow">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">Search Medical Record</h1>
        </div>
    </header>

    <main>
        <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
            <div class="flex flex-wrap w-full">

                <form class="space-y-6 flex flex-wrap p-3" action="../../includes/search-med-record.php" method="POST">
                    <div class="px-3 py-1.5">
                        <label for="Search" class="flex text-sm font-medium text-gray-900">Search</label>
                        <div class="mt-2">
                            <input id="Search" name="input" type="Search" required
                                value="<?php echo htmlspecialchars($_POST['input'] ?? ''); ?>"
                                class="block font-bold w-26 rounded-md border-2 px-4 py-1 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                        </div>
                    </div>
                    <div class="px-3 py-1.5">
                        <button name="search" type="submit" class="flex w-20 justify-center rounded-md bg-blue-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-indigo-500">Search</button>
                    </div>
                </form>

                <table class="table table-striped table-hover table-bordered border-gray-400">
                    <thead>
                        <tr>
                            <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center">Patient ID</p></th>
                            <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center">Patient Name</p></th>
                            <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center">Reservations</p></th>
                            <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center">Date</p></th>
                            <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center">Sign Date</p></th>
                            <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center">Report</p></th>
                        </tr>
                    </thead>
                    <tbody class="table-group-divider">
                        <?php
                        $search = trim($_POST['input'] ?? '');
                        $whereExtraSql = '';
                        if ($role === 'Doctor') {
                            $docid = (int)($_SESSION['id'] ?? 0);
                            $whereExtraSql = " AND appointment.doctorId = {$docid}";
                        }

                        $stmt = $connect->prepare("
                            SELECT appointment.*, 
                                   users.nat_id, 
                                   users.PatientContno as userPhone,
                                   users.fullName as userFullName
                            FROM appointment
                            LEFT JOIN users ON users.uid = appointment.userId
                            WHERE (
                               appointment.patient_Name LIKE ?
                               OR appointment.patient_Num LIKE ?
                               OR appointment.userId LIKE ?
                               OR appointment.apid LIKE ?
                               OR users.nat_id LIKE ?
                               OR users.PatientContno LIKE ?
                               OR users.fullName LIKE ?
                            )
                            {$whereExtraSql}
                             ORDER BY appointment.postingDate DESC
                        ");

                        $s = "%$search%";
                        $stmt->bind_param("sssssss", $s, $s, $s, $s, $s, $s, $s);
                        $stmt->execute();
                        $ret = $stmt->get_result();

                        if ($ret && $ret->num_rows > 0) {
                            while ($row = $ret->fetch_assoc()) {
                        ?>
                            <tr>
                                <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center"><?php echo $row['userId']; ?></p></th>
                                <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center"><?php echo htmlspecialchars($row['patient_Name']); ?></p></th>
                                <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center"><?php echo htmlspecialchars($row['doctorSpecialization']); ?></p></th>
                                <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center"><?php echo $row['appointmentDate']; ?></p></th>
                                <th scope="col"><p class="text-lg leading- font-bold text-gray-900 text-center"><?php echo $row['postingDate']; ?></p></th>
                                <th scope="col">
                                    <p class="text-lg leading- font-bold text-gray-900 text-center">
                                        <?php
                                        $roleDir = match($_SESSION['role'] ?? '') {
                                            'System Admin' => 'super-admin',
                                            'Admin' => 'admin',
                                            'Doctor' => 'doctor',
                                            'User' => 'user',
                                            default => 'patient'
                                        };
                                        ?>
                                        <a href="/modules/<?= $roleDir ?>/report.php?ref=<?= urlencode(hms_encrypt_id((int)$row['apid'])) ?>"
                                           class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-lg font-medium text-green-700 ring-1 ring-inset ring-green-600/20">View</a>
                                    </p>
                                </th>
                            </tr>
                        <?php
                            }
                        } else {
                            echo '<tr><td colspan="6" class="text-center text-gray-500 py-4">No results found.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>

            </div>
        </div>
    </main>
</div>


    <script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>
