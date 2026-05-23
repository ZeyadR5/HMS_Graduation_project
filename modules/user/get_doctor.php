<?php
require_once __DIR__ . '/../../includes/auth.php';

$conn = hms_db_connect(false);
if (!$conn) {
  die("Database connection failed");
}

if (!empty($_POST["specilizationid"])) {
  $spec = trim($_POST['specilizationid']);
  $stmt = $conn->prepare("SELECT doctorName, id, docFees FROM doctors WHERE specilization = ?");
  $stmt->bind_param("s", $spec);
  $stmt->execute();
  $sql = $stmt->get_result();

  // Determine today's day_of_week for the schedule system (0=Saturday...6=Friday)
  date_default_timezone_set('Africa/Cairo');
  $phpDow = (int)date('w'); // 0=Sunday, 6=Saturday
  $dayMap = [6 => 0, 0 => 1, 1 => 2, 2 => 3, 3 => 4, 4 => 5, 5 => 6];
  $todayDow = $dayMap[$phpDow];
  $todayDate = date('Y-m-d');
  ?>
  <option selected="selected">Select doctor</option>
  <?php
  while ($row = mysqli_fetch_array($sql)) {
    $docId = (int)$row['id'];

    // Check if doctor has any schedule at all
    $schAny = $conn->query("SELECT COUNT(*) as cnt FROM doctor_schedules WHERE doctor_id = $docId");
    $hasSchedule = ($schAny && ($schAny->fetch_assoc()['cnt'] ?? 0) > 0);

    // Check if doctor works today (weekly schedule)
    $schToday = $conn->query("SELECT id FROM doctor_schedules WHERE doctor_id = $docId AND day_of_week = $todayDow AND status = 'available' LIMIT 1");
    $worksToday = ($schToday && $schToday->num_rows > 0);

    // Check if today is blocked by an override
    $ovToday = $conn->query("SELECT status FROM doctor_day_overrides WHERE doctor_id = $docId AND override_date = '$todayDate' LIMIT 1");
    $override = ($ovToday && $ovToday->num_rows > 0) ? $ovToday->fetch_assoc() : null;

    $availableToday = $worksToday;
    if ($override) {
      if ($override['status'] === 'off') {
        $availableToday = false;
      } elseif ($override['status'] === 'custom') {
        $availableToday = true;
      }
    }

    // Build label
    $label = htmlentities($row['doctorName']);
    if (!$hasSchedule) {
      $label .= ' ⚠ No schedule set';
    } elseif (!$availableToday) {
      $label .= ' ⚠ Not available today';
    }
    ?>
    <option value="<?php echo htmlentities($row['id']); ?>" data-fees="<?php echo htmlentities($row['docFees']); ?>"><?php echo $label; ?></option>
  <?php
  }
}

if (!empty($_POST["doctor"])) {
  $doctorId = intval($_POST['doctor']);
  $stmt = $conn->prepare("SELECT docFees FROM doctors WHERE id = ?");
  $stmt->bind_param("i", $doctorId);
  $stmt->execute();
  $sql = $stmt->get_result();
  while ($row = mysqli_fetch_array($sql)) { ?>
    <option value="<?php echo htmlentities($row['docFees']); ?>"><?php echo htmlentities($row['docFees']); ?></option>
<?php
  }
}
