<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/appointment-helpers.php';

ini_set("display_errors", 0);
$connect = hms_db_connect();
if ($connect->connect_error) {
    die("Connection failed: " . $connect->connect_error);
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations</title>
    <link rel="stylesheet" href="../css/Reservations.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="icon" href="../../assets/images/l-gh.png">
    <link rel="stylesheet" href="/assets/css/responsive.css">
</head>
<body>
    <div class="min-h-full">
        <?php $activePage = 'reservations'; require_once __DIR__ . '/../../includes/nav.php'; ?>

        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">Reservations</h1>
            </div>
        </header>

        <main>
            <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                <form class="space-y-6 flex flex-wrap p-3" action="./search-res.php" method="POST">
                    <div class="px-3 py-1.5">
                        <label for="Search" class="flex text-sm font-medium text-gray-900">Search</label>
                        <div class="mt-2">
                            <input id="Search" name="input" type="Search" required class="block font-bold w-26 rounded-md border-2 px-4 py-1 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                        </div>
                    </div>
                    <div class="px-3 py-1.5">
                        <button name="search" type="submit" class="flex w-20 justify-center rounded-md bg-blue-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-indigo-500">Search</button>
                    </div>
                </form>

                <table class="table table-striped table-hover table-bordered border-gray-400">
                    <thead>
                        <tr>
                            <th scope="col"><p class="text-lg font-bold text-gray-900 text-center">Patient ID</p></th>
                            <th scope="col"><p class="text-lg font-bold text-gray-900 text-center">Patient Name</p></th>
                            <th scope="col"><p class="text-lg font-bold text-gray-900 text-center">Reservation</p></th>
                            <th scope="col"><p class="text-lg font-bold text-gray-900 text-center">Doctor</p></th>
                            <th scope="col"><p class="text-lg font-bold text-gray-900 text-center">Appointment Date</p></th>
                            <th scope="col"><p class="text-lg font-bold text-gray-900 text-center">Status</p></th>
                            <th scope="col"><p class="text-lg font-bold text-gray-900 text-center">Payment</p></th>
                            <th scope="col"><p class="text-lg font-bold text-gray-900 text-center">Actions</p></th>
                        </tr>
                    </thead>
                    <tbody class="table-group-divider">
                        <?php
                        $sql = mysqli_query($connect, "SELECT appointment.*, doctors.doctorName FROM appointment
                            JOIN doctors ON doctors.id = appointment.doctorId
                            WHERE appointmentDate = CURRENT_DATE()
                            ORDER BY apid DESC");

                        while ($row = mysqli_fetch_assoc($sql)):
                        ?>
                            <tr>
                                <td><p class="text-base font-bold text-gray-900 text-center"><?php echo htmlspecialchars((string)$row['apid']); ?></p></td>
                                <td><p class="text-base font-bold text-gray-900 text-center"><?php echo htmlspecialchars($row['patient_Name']); ?></p></td>
                                <td><p class="text-base font-bold text-gray-900 text-center"><?php echo htmlspecialchars($row['doctorSpecialization']); ?></p></td>
                                <td><p class="text-base font-bold text-gray-900 text-center"><?php echo htmlspecialchars($row['doctorName']); ?></p></td>
                                <td><p class="text-base font-bold text-gray-900 text-center"><?php echo htmlspecialchars($row['appointmentDate']); ?></p></td>
                                <td class="text-center align-middle"><?php echo appt_status_badge($row); ?></td>
                                <td class="text-center align-middle"><?php echo appt_payment_badge($row); ?></td>
                                <td class="text-center align-middle"><?php echo appt_action_buttons($row, '/modules/user/Reservations.php'); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>

