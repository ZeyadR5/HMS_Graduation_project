<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/appointment-helpers.php';

ini_set("display_errors", 0);
$connect = hms_db_connect();
if ($connect->connect_error) {
    die("Connection failed: " . $connect->connect_error);
}

if (isset($_GET['cancel'], $_GET['id'])) {
    hms_require_csrf('./calender.php');

    require_once __DIR__ . '/../../includes/notification-api.php';
    $apid = (int)$_GET['id'];
    $uid = (int)($_SESSION['uid'] ?? 0);

    // Fetch appointment data before updating
    $stmt = $connect->prepare("SELECT doctorId, patient_Name, appointmentDate, appointmentTime FROM appointment WHERE apid = ? AND userId = ?");
    $stmt->bind_param("ii", $apid, $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();

    if ($row) {
        $patientName = $row['patient_Name'] ?: ($_SESSION['username'] ?? 'Patient');
        $doctorId = (int)$row['doctorId'];
        $date = $row['appointmentDate'];
        $time = $row['appointmentTime'];

        if (mysqli_query($connect, "UPDATE appointment SET userStatus='0', cancelledBy='Patient' WHERE apid = {$apid}")) {
            // Notify Doctor
            hms_create_notification($connect, [
                'recipient_type' => 'doctor',
                'recipient_id' => $doctorId,
                'title' => 'Appointment Cancelled — ' . $patientName,
                'message' => "Patient $patientName has cancelled their appointment on $date at $time via Calendar.",
                'type' => 'cancellation',
                'related_doctor_id' => $doctorId,
                'related_appointment_id' => $apid
            ]);

            // Notify Reception (Employee role) and Admins
            $staffStmt = $connect->prepare("SELECT id, role FROM employ WHERE role IN ('User', 'Admin', 'System Admin')");
            $staffStmt->execute();
            $staffRes = $staffStmt->get_result();
            while ($staff = $staffRes->fetch_assoc()) {
                $isRelReception = ($staff['role'] === 'User');
                hms_create_notification($connect, [
                    'recipient_type' => $isRelReception ? 'employee' : 'admin',
                    'recipient_id' => (int)$staff['id'],
                    'title' => 'Patient Cancellation — ' . $patientName,
                    'message' => "Patient $patientName cancelled an appointment with Doctor ID $doctorId on $date via Calendar.",
                    'type' => 'cancellation',
                    'related_doctor_id' => $doctorId,
                    'related_appointment_id' => $apid
                ]);
            }
            $staffStmt->close();
            
            echo "<script>alert('Appointment cancelled successfully.'); window.location.href='./calender.php';</script>";
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="icon" href="/assets/images/echol.png">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <link rel="stylesheet" href="./mobile-header-fix.css">
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
                <table class="table table-striped table-hover table-bordered border-gray-400">
                    <thead>
                        <tr>
                            <th scope="col"><p class="text-lg font-bold text-gray-900 text-center">Dr Name</p></th>
                            <th scope="col"><p class="text-lg font-bold text-gray-900 text-center">Reservations</p></th>
                            <th scope="col"><p class="text-lg font-bold text-gray-900 text-center">Reservations Date</p></th>
                            <th scope="col"><p class="text-lg font-bold text-gray-900 text-center">Date</p></th>
                            <th scope="col"><p class="text-lg font-bold text-gray-900 text-center">Paid</p></th>
                            <th scope="col"><p class="text-lg font-bold text-gray-900 text-center">Status</p></th>
                            <th scope="col"><p class="text-lg font-bold text-gray-900 text-center">Cancel</p></th>
                        </tr>
                    </thead>
                    <tbody class="table-group-divider">
                        <?php
                        $userId = (int)($_SESSION['uid'] ?? 0);
                        $sql = "SELECT doctors.doctorName AS docname, appointment.*
                                FROM appointment
                                JOIN doctors ON doctors.id = appointment.doctorId
                                WHERE appointmentDate = CURRENT_DATE()
                                  AND appointment.userId = {$userId}
                                ORDER BY apid DESC";
                        $allQuery = mysqli_query($connect, $sql);
                        if ($allQuery && mysqli_num_rows($allQuery) > 0):
                            while ($row = mysqli_fetch_assoc($allQuery)):
                        ?>
                            <tr>
                                <th scope="col"><p class="text-lg font-bold text-gray-900 text-center"><?php echo htmlspecialchars($row['docname']); ?></p></th>
                                <th scope="col"><p class="text-lg font-bold text-gray-900 text-center"><?php echo htmlspecialchars($row['doctorSpecialization']); ?></p></th>
                                <th scope="col"><p class="text-lg font-bold text-gray-900 text-center"><?php echo htmlspecialchars($row['appointmentDate']); ?></p></th>
                                <th scope="col"><p class="text-lg font-bold text-gray-900 text-center"><?php echo htmlspecialchars($row['postingDate'] ?: '-'); ?></p></th>
                                <th scope="col"><p class="text-lg font-bold text-gray-900 text-center"><?php echo htmlspecialchars((string)$row['consultancyFees']); ?></p></th>
                                <th scope="col" class="text-center align-middle">
                                    <?php echo appt_status_badge($row); ?>
                                    <?php echo appt_cancelled_by($row); ?>
                                </th>
                                <th scope="col">
                                    <p class="text-lg font-bold text-gray-900 text-center">
                                        <?php if ((int)$row['doctorStatus'] === 2): ?>
                                            <button class="text-lg inline-flex items-center rounded-md bg-green-50 px-2 py-1 font-medium text-red-700 ring-1 ring-inset ring-red-600/20">Cancel</button>
                                        <?php elseif ((int)$row['userStatus'] === 1 && (int)$row['doctorStatus'] === 1): ?>
                                            <a href="./calender.php?id=<?php echo (int)$row['apid']; ?>&cancel=update&<?= hms_csrf_query() ?>" onClick="return confirm('Are you sure you want to cancel this appointment?');" class="text-lg inline-flex items-center rounded-md bg-green-50 px-2 py-1 font-medium text-red-700 ring-1 ring-inset ring-red-600/20">Cancel</a>
                                        <?php elseif ((int)$row['userStatus'] === 0 || (int)$row['doctorStatus'] === 0): ?>
                                            <span class="text-lg inline-flex items-center rounded-md bg-green-50 px-2 py-1 font-medium text-red-700 ring-1 ring-inset ring-red-600/20">Cancelled</span>
                                        <?php endif; ?>
                                    </p>
                                </th>
                            </tr>
                        <?php
                            endwhile;
                        endif;
                        ?>
                    </tbody>
                </table>
            </div>
        </main>

        <script src="/assets/js/responsive-nav.js" defer></script>
    </div>
</body>
</html>
