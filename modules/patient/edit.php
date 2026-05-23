<?php
require_once __DIR__ . '/../../includes/auth.php';
if (!isset($_SESSION['uid'])) {
    header("location: /index.php"); exit();
}

$connect = hms_db_connect();
if ($connect->connect_error) die("Connection failed");

$apid = intval($_GET['id'] ?? 0);
$uid  = $_SESSION['uid'];

// تأكد إن الحجز بتاع المريض ده
$stmt = $connect->prepare("SELECT appointment.*, doctors.doctorName FROM appointment JOIN doctors ON doctors.id=appointment.doctorId WHERE apid=? AND userId=?");
$stmt->bind_param("ii", $apid, $uid);
$stmt->execute();
$res  = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "<script>alert('Appointment not found!'); window.location.href='./Medical-Record.php';</script>";
    exit();
}
$appt = $res->fetch_assoc();

// لو الحجز ملغي أو خلص، مش هينفع يتعدل
if ($appt['userStatus'] == 0 || $appt['doctorStatus'] == 0 || $appt['doctorStatus'] == 2) {
    echo "<script>alert('Cannot edit a cancelled or completed appointment.'); window.location.href='./Medical-Record.php';</script>";
    exit();
}

$success = $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newDate = $_POST['appointmentDate'] ?? '';
    $newTime = $_POST['appointmentTime'] ?? '';

    if (empty($newDate) || empty($newTime)) {
        $error = "Please fill all fields.";
    } elseif ($newDate < date('Y-m-d')) {
        $error = "Date cannot be in the past.";
    } else {
        $upd = $connect->prepare("UPDATE appointment SET appointmentDate=?, appointmentTime=? WHERE apid=? AND userId=?");
        $upd->bind_param("ssii", $newDate, $newTime, $apid, $uid);
        if ($upd->execute()) {
            echo "<script>alert('Appointment updated successfully.'); window.location.href='./Medical-Record.php';</script>";
            exit();
        } else {
            $error = "Update failed. Try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Appointment</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="icon" href="/assets/images/echol.png">

    <link rel="stylesheet" href="/assets/css/responsive.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

<div class="bg-white rounded-2xl shadow-lg p-8 w-full max-w-md">
  <div class="flex items-center gap-3 mb-6">
    <a href="./Medical-Record.php" class="text-gray-400 hover:text-gray-700"><i class="bi bi-arrow-left text-xl"></i></a>
    <h1 class="text-2xl font-bold text-gray-800">Edit Appointment</h1>
  </div>

  <!-- Info -->
  <div class="bg-blue-50 rounded-xl p-4 mb-6 border border-blue-100">
    <p class="text-sm text-gray-600"><span class="font-semibold text-gray-800">Doctor:</span> <?= htmlspecialchars($appt['doctorName']) ?></p>
    <p class="text-sm text-gray-600 mt-1"><span class="font-semibold text-gray-800">Specialization:</span> <?= htmlspecialchars($appt['doctorSpecialization']) ?></p>
    <p class="text-sm text-gray-600 mt-1"><span class="font-semibold text-gray-800">Fees:</span> <?= htmlspecialchars($appt['consultancyFees']) ?> EGP</p>
  </div>

  <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 mb-4 text-sm"><?= $error ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="mb-4">
      <label class="block text-sm font-semibold text-gray-700 mb-1">New Date</label>
      <input type="date" name="appointmentDate"
             value="<?= htmlspecialchars($appt['appointmentDate']) ?>"
             min="<?= date('Y-m-d') ?>"
             required
             class="w-full border-2 border-gray-200 rounded-lg px-4 py-2 text-gray-800 focus:border-blue-500 focus:outline-none">
    </div>
    <div class="mb-6">
      <label class="block text-sm font-semibold text-gray-700 mb-1">New Time</label>
      <input type="time" name="appointmentTime"
             value="<?= htmlspecialchars($appt['appointmentTime']) ?>"
             required
             class="w-full border-2 border-gray-200 rounded-lg px-4 py-2 text-gray-800 focus:border-blue-500 focus:outline-none">
    </div>
    <div class="flex gap-3">
      <button type="submit"
              class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-lg transition">
        <i class="bi bi-check-circle me-1"></i> Save Changes
      </button>
      <a href="./Medical-Record.php"
         class="flex-1 text-center bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 rounded-lg transition">
        Cancel
      </a>
    </div>
  </form>
</div>


    <script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>
