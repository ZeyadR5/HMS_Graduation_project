<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/secure-token.php';

require_once "../../includes/appointment-helpers.php";
if (!isset($_SESSION['uid'])) { header("location: /index.php"); exit(); }
$connect = hms_db_connect();
if ($connect->connect_error) die("Connection failed: " . $connect->connect_error);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Reservations</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="icon" href="/assets/images/echol.png">

    <link rel="stylesheet" href="/assets/css/responsive.css">
    <link rel="stylesheet" href="./mobile-header-fix.css">
</head>
<body>
<div class="min-h-full">

  <!-- NAV -->
  <?php $activePage = 'reservations'; require_once __DIR__ . '/../../includes/nav.php'; ?>

  <!-- HEADER -->
  <header class="bg-white shadow">
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
      <h1 class="text-3xl font-bold tracking-tight text-gray-900">My Reservations</h1>
    </div>
  </header>

  <!-- MAIN -->
  <main>
    <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
      <table class="w-full table table-striped table-hover table-bordered border-gray-400">
        <thead>
          <tr>
            <th><p class="text-lg font-bold text-gray-900 text-center">Doctor</p></th>
            <th><p class="text-lg font-bold text-gray-900 text-center">Specialization</p></th>
            <th><p class="text-lg font-bold text-gray-900 text-center">Date</p></th>
            <th><p class="text-lg font-bold text-gray-900 text-center">Time</p></th>
            <th><p class="text-lg font-bold text-gray-900 text-center">Fees</p></th>
            <th><p class="text-lg font-bold text-gray-900 text-center">Status</p></th>
            <th><p class="text-lg font-bold text-gray-900 text-center">Report</p></th>
            <th><p class="text-lg font-bold text-gray-900 text-center">Actions</p></th>
          </tr>
        </thead>
        <tbody class="table-group-divider">
          <?php
          $uid = $_SESSION['uid'];
          $sql = mysqli_query($connect,
            "SELECT appointment.*, doctors.doctorName
             FROM appointment
             JOIN doctors ON doctors.id = appointment.doctorId
             WHERE appointment.userId = '$uid'
             ORDER BY postingDate DESC"
          );
          while ($row = mysqli_fetch_assoc($sql)):
          ?>
          <tr>
            <td><p class="text-sm font-semibold text-gray-900 text-center"><?= htmlspecialchars($row['doctorName']) ?></p></td>
            <td><p class="text-sm text-gray-700 text-center"><?= htmlspecialchars($row['doctorSpecialization']) ?></p></td>
            <td><p class="text-sm text-gray-700 text-center"><?= htmlspecialchars($row['appointmentDate']) ?></p></td>
            <td><p class="text-sm text-gray-700 text-center"><?= htmlspecialchars($row['appointmentTime']) ?></p></td>
            <td><p class="text-sm text-gray-700 text-center"><?= htmlspecialchars($row['consultancyFees']) ?> EGP</p></td>
            <td>
              <p class="text-center">
                <?= appt_status_badge($row) ?>
                <?= appt_cancelled_by($row) ?>
              </p>
            </td>
            <td>
              <p class="text-center">
                <a href="./report.php?ref=<?= urlencode(hms_encrypt_id((int)$row['apid'])) ?>"
                   class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-sm font-medium text-green-700 ring-1 ring-inset ring-green-600/20 hover:bg-green-100">
                  <i class="bi bi-file-text me-1"></i>View
                </a>
              </p>
            </td>
            <td>
              <?= appt_action_buttons($row, '/modules/patient/Reservations.php') ?>
            </td>
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
