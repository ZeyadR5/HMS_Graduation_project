<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once "../../includes/appointment-helpers.php";
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
    <title>Search Medical-Record</title>
    <link rel="stylesheet" href="../css/med-record.css">
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
        <?php $activePage = 'medical-record'; require_once __DIR__ . '/../../includes/nav.php'; ?>
        <!-- <div class="absolute inset-x-0 -top-40 -z-10 transform-gpu overflow-hidden blur-3xl sm:-top-80" aria-hidden="true">
            <div class="relative left-[calc(50%-11rem)] aspect-[1155/678] w-[36.125rem] -translate-x-1/2 rotate-[30deg] bg-gradient-to-tr from-[#ff80b5] to-[#9089fc] opacity-30 sm:left-[calc(50%-30rem)] sm:w-[72.1875rem]" style="clip-path: polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)">
            </div>
        </div> -->
        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">Search Medical-Record</h1>
            </div>
        </header>
        <main class="">
            <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">

                <div class="flex flex-wrap w-full">

                    <form class="space-y-6 flex flex-wrap p-3" action="./search.php" method="POST">

                        <div class=" flex flex-wrap">
                            <label for="Search" class="flex text-sm font-medium text-gray-900">From</label>
                            <div class="px-4">
                                <input id="Search" name="from" type="date" required class=" font-bold block w-26 rounded-md border-2 px-4 py-1 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                            </div>

                            <label for="Search" class="flex text-sm font-medium text-gray-900">To</label>
                            <div class="px-4">
                                <input id="Search" name="to" type="date" required class=" font-bold block w-26 rounded-md border-2 px-4 py-1 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                            </div>

                            <button name="search" type="submit" class="flex w-20 justify-center rounded-md bg-blue-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">SEARCH</button>
                        </div>
                    </form>
                    <table class=" table table-striped table-hover table-bordered border-gray-400">
                        <thead>
                            <tr>
                                <th scope="col">
                                    <p class="text-lg leading- font-bold text-gray-900 text-center">Doctor</p>
                                </th>
                                <th scope="col">
                                    <p class="text-lg leading- font-bold text-gray-900 text-center">Reservations</p>
                                </th>
                                <th scope="col">
                                    <p class="text-lg leading- font-bold text-gray-900 text-center">Paid</p>
                                </th>
                                <th scope="col">
                                    <p class="text-lg leading- font-bold text-gray-900 text-center">Date</p>
                                </th>
                                <th scope="col">
                                    <p class="text-lg leading- font-bold text-gray-900 text-center">Statue</p>
                                </th>
                                <th scope="col">
                                    <p class="text-lg leading- font-bold text-gray-900 text-center">Report</p>
                                </th>
                            <th scope="col"><p class="text-lg font-bold text-gray-900 text-center">Actions</p></th>
                            </tr>
                        </thead>
                        <tbody class="table-group-divider">

                            <?php
                            $userid = $_SESSION['id'];
                            $from = $_POST['input'];
                            $to = $_POST['to'];
                            $ret = mysqli_query($connect, "SELECT doctors.doctorName as docname,appointment.* FROM `appointment` join doctors on doctors.id=appointment.doctorId WHERE  userId='$userid' AND `appointmentDate` BETWEEN '$from' AND '$to' ORDER BY postingDate DESC");
                            if ($ret->num_rows > 0) {
                                while ($row = $ret->fetch_assoc()) {
                            ?>
                                    <tr>
                                        <th scope="col">
                                            <p class="text-lg leading- font-bold text-gray-900 text-center"><?php echo $row['docname']; ?></p>
                                        </th>
                                        <th scope="col">
                                            <p class="text-lg leading- font-bold text-gray-900 text-center"><?php echo $row['doctorSpecialization']; ?></p>
                                        </th>
                                        <th scope="col">
                                            <p class="text-lg leading- font-bold text-gray-900 text-center"><?php echo $row['consultancyFees']; ?></p>
                                        </th>
                                        <th scope="col">
                                            <p class="text-lg leading- font-bold text-gray-900 text-center"><?php echo $row['appointmentDate']; ?></p>
                                        </th>
                                                                        <th scope="col">
                                    <p class="text-center">
                                        <?php echo appt_status_badge($row); ?>
                                        <?php echo appt_cancelled_by($row); ?>
                                    </p>
                                </th>
                                                                        <th scope="col">
                                    <?php echo appt_action_buttons($row, '/modules/patient/search.php'); ?>
                                </th>
                                <th scope="col">
                                    <?php echo appt_action_buttons($row, '/modules/patient/search.php'); ?>
                                </th>
                                    </tr>
                        </tbody>
                <?php }
                            } ?>
                    </table>
                </div>
        </main>

    <script src="/assets/js/responsive-nav.js" defer></script>
</body>

</html>
