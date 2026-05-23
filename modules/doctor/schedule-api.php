<?php
/**
 * Doctor Schedule API — Save/Update/Delete schedule data
 * Called via AJAX from schedule.php
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notification-api.php';

header('Content-Type: application/json; charset=utf-8');

$conn = hms_db_connect();
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}
$conn->set_charset("utf8mb4");

$role = $_SESSION['role'] ?? '';
if ($role !== 'Doctor') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$doctorId = (int)($_SESSION['id'] ?? 0);
if ($doctorId < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid doctor session']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$mutatingActions = ['save_schedule', 'add_override', 'delete_override'];
if (in_array($action, $mutatingActions, true) && !hms_validate_csrf($_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Security check failed']);
    exit;
}

// ============================================================
// ACTION: get_schedule — Load current weekly schedule
// ============================================================
if ($action === 'get_schedule') {
    $stmt = $conn->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = ? ORDER BY day_of_week");
    $stmt->bind_param("i", $doctorId);
    $stmt->execute();
    $res = $stmt->get_result();
    $schedules = [];
    while ($row = $res->fetch_assoc()) {
        $schedules[] = $row;
    }
    $stmt->close();

    // Load overrides for next 60 days
    $ovStmt = $conn->prepare("SELECT * FROM doctor_day_overrides WHERE doctor_id = ? AND override_date >= CURDATE() ORDER BY override_date");
    $ovStmt->bind_param("i", $doctorId);
    $ovStmt->execute();
    $ovRes = $ovStmt->get_result();
    $overrides = [];
    while ($row = $ovRes->fetch_assoc()) {
        $overrides[] = $row;
    }
    $ovStmt->close();

    echo json_encode(['success' => true, 'schedules' => $schedules, 'overrides' => $overrides]);
    exit;
}

// ============================================================
// ACTION: save_schedule — Save/Update weekly schedule
// ============================================================
if ($action === 'save_schedule') {
    $days = json_decode($_POST['days'] ?? '[]', true);
    if (!is_array($days)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }

    $changes = [];

    foreach ($days as $day) {
        $dayOfWeek    = (int)($day['day_of_week'] ?? -1);
        $startTime    = trim($day['start_time'] ?? '');
        $endTime      = trim($day['end_time'] ?? '');
        $slotDuration = (int)($day['slot_duration'] ?? 30);
        $status       = $day['status'] ?? 'off';

        if ($dayOfWeek < 0 || $dayOfWeek > 6) continue;

        $dayNames = ['Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'];

        if ($status === 'off') {
            // Check if existed before (to track change)
            $chkStmt = $conn->prepare("SELECT status FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ?");
            $chkStmt->bind_param("ii", $doctorId, $dayOfWeek);
            $chkStmt->execute();
            $chkRes = $chkStmt->get_result();
            if ($chkRes->num_rows > 0) {
                $oldRow = $chkRes->fetch_assoc();
                if ($oldRow['status'] !== 'off') {
                    $changes[] = $dayNames[$dayOfWeek] . ' set to OFF';
                }
            }
            $chkStmt->close();

            // Delete or set to off
            $stmt = $conn->prepare("DELETE FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ?");
            $stmt->bind_param("ii", $doctorId, $dayOfWeek);
            $stmt->execute();
            $stmt->close();
        } else {
            if ($startTime === '' || $endTime === '') continue;

            // Check for changes
            $chkStmt = $conn->prepare("SELECT start_time, end_time, slot_duration, status FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ?");
            $chkStmt->bind_param("ii", $doctorId, $dayOfWeek);
            $chkStmt->execute();
            $chkRes = $chkStmt->get_result();
            if ($chkRes->num_rows > 0) {
                $old = $chkRes->fetch_assoc();
                if ($old['start_time'] !== $startTime || $old['end_time'] !== $endTime || (int)$old['slot_duration'] !== $slotDuration) {
                    $changes[] = $dayNames[$dayOfWeek] . ': ' . $startTime . '-' . $endTime . ' (' . $slotDuration . ' min)';
                }
            } else {
                $changes[] = $dayNames[$dayOfWeek] . ' added: ' . $startTime . '-' . $endTime . ' (' . $slotDuration . ' min)';
            }
            $chkStmt->close();

            $stmt = $conn->prepare("
                INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration, status)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE start_time = VALUES(start_time), end_time = VALUES(end_time), 
                                        slot_duration = VALUES(slot_duration), status = VALUES(status)
            ");
            $stmt->bind_param("iissis", $doctorId, $dayOfWeek, $startTime, $endTime, $slotDuration, $status);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Send notifications if changes were made
    if (!empty($changes)) {
        $changeDesc = implode('; ', $changes);
        hms_notify_affected_patients($conn, $doctorId, $changeDesc);
    }

    echo json_encode(['success' => true, 'message' => 'Schedule saved successfully', 'changes' => count($changes)]);
    exit;
}

// ============================================================
// ACTION: add_override — Add a day override (vacation/custom)
// ============================================================
if ($action === 'add_override') {
    $overrideDate = trim($_POST['override_date'] ?? '');
    $status       = $_POST['status'] ?? 'off';
    $startTime    = trim($_POST['start_time'] ?? '');
    $endTime      = trim($_POST['end_time'] ?? '');
    $slotDuration = !empty($_POST['slot_duration']) ? (int)$_POST['slot_duration'] : null;
    $reason       = trim($_POST['reason'] ?? '');

    if ($overrideDate === '' || strtotime($overrideDate) === false) {
        echo json_encode(['success' => false, 'message' => 'Invalid date']);
        exit;
    }

    if ($overrideDate < date('Y-m-d')) {
        echo json_encode(['success' => false, 'message' => 'Cannot add override for past dates']);
        exit;
    }

    $startVal = ($status === 'custom' && $startTime !== '') ? $startTime : null;
    $endVal   = ($status === 'custom' && $endTime !== '') ? $endTime : null;
    $slotVal  = ($status === 'custom' && $slotDuration) ? $slotDuration : null;

    $stmt = $conn->prepare("
        INSERT INTO doctor_day_overrides (doctor_id, override_date, status, start_time, end_time, slot_duration, reason)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE status = VALUES(status), start_time = VALUES(start_time), 
                                end_time = VALUES(end_time), slot_duration = VALUES(slot_duration), reason = VALUES(reason)
    ");
    $stmt->bind_param("issssss", $doctorId, $overrideDate, $status, $startVal, $endVal, $slotVal, $reason);
    $stmt->execute();
    $stmt->close();

    // Notify affected patients
    $statusLabel = $status === 'off' ? 'Day Off' : 'Custom Hours';
    hms_notify_affected_patients($conn, $doctorId, $overrideDate . ' — ' . $statusLabel . ($reason ? ': ' . $reason : ''));

    echo json_encode(['success' => true, 'message' => 'Override saved for ' . $overrideDate]);
    exit;
}

// ============================================================
// ACTION: delete_override — Remove a day override
// ============================================================
if ($action === 'delete_override') {
    $overrideId = (int)($_POST['override_id'] ?? 0);
    if ($overrideId < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid override ID']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM doctor_day_overrides WHERE id = ? AND doctor_id = ?");
    $stmt->bind_param("ii", $overrideId, $doctorId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Override removed']);
    exit;
}

// ============================================================
// ACTION: get_today_appointments — Doctor's today appointments
// ============================================================
if ($action === 'get_today_appointments') {
    $stmt = $conn->prepare("
        SELECT a.apid, a.patient_Name, a.appointmentDate, a.appointmentTime, 
               a.consultancyFees, a.userStatus, a.doctorStatus
        FROM appointment a
        WHERE a.doctorId = ? AND a.appointmentDate = CURDATE() AND a.userStatus IN (1,2) AND a.doctorStatus IN (1,2)
        ORDER BY a.appointmentTime ASC
    ");
    $stmt->bind_param("i", $doctorId);
    $stmt->execute();
    $res = $stmt->get_result();
    $appointments = [];
    while ($row = $res->fetch_assoc()) {
        $appointments[] = $row;
    }
    $stmt->close();

    echo json_encode(['success' => true, 'appointments' => $appointments]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
$conn->close();
