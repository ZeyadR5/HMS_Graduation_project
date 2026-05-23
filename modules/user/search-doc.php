<?php
require_once __DIR__ . '/../../includes/auth.php';
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
    <title> Search Doctors</title>
    <link rel="stylesheet" href="../css/med-record.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="icon" href="../../assets/images/echol.png">

    <link rel="stylesheet" href="/assets/css/responsive.css">
</head>

<body>
    <div class="min-h-full">
        <?php $activePage = 'doctors'; require_once __DIR__ . '/../../includes/nav.php'; ?>

        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900"> Search Doctors</h1>
            </div>
        </header>
        <main class="">
            <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">

                <div class="flex flex-wrap w-full">
                    <form class="space-y-6 flex flex-wrap p-3" action="./search-doc.php" method="POST">
                        <div class="px-3 py-1.5">
                            <label for="Search" class="flex text-sm font-medium text-gray-900">Search</label>
                            <div class="mt-2">
                                <input id="Search" name="input" type="Search" required class="font-bold block w-26 rounded-md border-2 px-4 py-1 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                            </div>
                        </div>

                        <div class="px-3 py-1.5">
                            <button name="search" type="submit" class="flex w-20 justify-center rounded-md bg-blue-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Search</button>
                        </div>
                    </form>
                    <table class=" table table-striped table-hover table-bordered border-gray-400">
                        <thead>
                            <tr>
                                <th scope="col">
                                    <p class="text-lg leading- font-bold text-gray-900 text-center">Doctor ID</p>
                                </th>
                                <th scope="col">
                                    <p class="text-lg leading- font-bold text-gray-900 text-center">Doctor Name</p>
                                </th>
                                <th scope="col">
                                    <p class="text-lg leading- font-bold text-gray-900 text-center">Doctor statue</p>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="table-group-divider">

                            <?php
                            $search = $_POST['input'];
                            $ret = mysqli_query($connect, "SELECT * FROM doctors  WHERE `id` LIKE '%$search%' OR `doctorName` LIKE '%$search%' ORDER BY statue desc  ");
                            if ($ret->num_rows > 0) {
                                while ($row = $ret->fetch_assoc()) {
                            ?>
                                    <tr>
                                        <th scope="col">
                                            <p class="text-lg leading- font-bold text-gray-900 text-center"><?php echo $row['id']; ?></p>
                                        </th>
                                        <th scope="col">
                                            <p class="text-lg leading- font-bold text-gray-900 text-center"><?php echo $row['doctorName']; ?></p>
                                        </th>

                                        <th scope="col">
                                            <p class="text-lg leading- font-bold text-gray-900 text-center">
                                                <?php
                                                if (($row['statue'] == 1)) {
                                                ?>
                                                    <button class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-lg font-medium text-blue-700 ring-1 ring-inset ring-red-600/20">`ON`</button>
                                                <?php } else {
                                                ?>
                                                    <button class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-lg font-medium text-red-700 ring-1 ring-inset ring-red-600/20">`OFF`</button>
                                                <?php } ?>
                                            </p>
                                        </th>
                                    </tr>
                        </tbody>
                <?php }
                            } ?>
                    </table>
                </div>
        </main>

           <?php
?>

    <script src="/assets/js/responsive-nav.js" defer></script>
</body>

</html>
