<?php
/**
 * Get Available Slots API
 * Input: doctor_id, date
 * Output: JSON { available, slots[], doctor_info{} }
 * 
 * Used by both employee (new_appoint.php) and patient (New-reservation.php) booking forms
 */
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Africa/Cairo');

$conn = hms_db_connect(false);
if (!$conn) {
    echo json_encode(['available' => false, 'message' => 'DB error']);
    exit;
}
$conn->set_charset("utf8mb4");

$doctorId = (int)($_GET['doctor_id'] ?? $_POST['doctor_id'] ?? 0);
$date     = trim($_GET['date'] ?? $_POST['date'] ?? '');
$mode     = trim($_GET['mode'] ?? $_POST['mode'] ?? '');

if ($doctorId < 1) {
    echo json_encode(['available' => false, 'message' => 'Missing doctor_id', 'slots' => []]);
    exit;
}

// Special mode to get doctor metadata (working days and overrides)
if ($mode === 'meta') {
    // Get weekly working days
    $schStmt = $conn->prepare("SELECT day_of_week FROM doctor_schedules WHERE doctor_id = ? AND status = 'available'");
    $schStmt->bind_param("i", $doctorId);
    $schStmt->execute();
    $schRes = $schStmt->get_result();
    $workingDays = [];
    while ($row = $schRes->fetch_assoc()) {
        // Map DB (0=Sat...6=Fri) to JS (0=Sun...6=Sat)
        $workingDays[] = (intval($row['day_of_week']) + 6) % 7;
    }
    $schStmt->close();

    // Get specific "Off" dates (overrides)
    $ovStmt = $conn->prepare("SELECT override_date FROM doctor_day_overrides WHERE doctor_id = ? AND status = 'off'");
    $ovStmt->bind_param("i", $doctorId);
    $ovStmt->execute();
    $ovRes = $ovStmt->get_result();
    $blockedDates = [];
    while ($row = $ovRes->fetch_assoc()) {
        $blockedDates[] = $row['override_date'];
    }
    $ovStmt->close();

    echo json_encode([
        'success' => true,
        'working_days' => $workingDays,
        'blocked_dates' => $blockedDates
    ]);
    exit;
}

if ($date === '') {
    echo json_encode(['available' => false, 'message' => 'Missing date', 'slots' => []]);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false) {
    echo json_encode(['available' => false, 'message' => 'Invalid date format', 'slots' => []]);
    exit;
}

// Get doctor info
$docStmt = $conn->prepare("SELECT id, doctorName, docFees, specilization FROM doctors WHERE id = ?");
$docStmt->bind_param("i", $doctorId);
$docStmt->execute();
$docRow = $docStmt->get_result()->fetch_assoc();
$docStmt->close();

if (!$docRow) {
    echo json_encode(['available' => false, 'message' => 'Doctor not found', 'slots' => []]);
    exit;
}

// Map date to day_of_week (0=Saturday ... 6=Friday)
$phpDow = (int)date('w', strtotime($date)); // 0=Sunday, 6=Saturday
// Convert to our system: 0=Saturday, 1=Sunday, ..., 6=Friday
$dayMap = [6 => 0, 0 => 1, 1 => 2, 2 => 3, 3 => 4, 4 => 5, 5 => 6];
$dayOfWeek = $dayMap[$phpDow];

// Check for day override first
$ovStmt = $conn->prepare("SELECT * FROM doctor_day_overrides WHERE doctor_id = ? AND override_date = ?");
$ovStmt->bind_param("is", $doctorId, $date);
$ovStmt->execute();
$override = $ovStmt->get_result()->fetch_assoc();
$ovStmt->close();

$startTime = null;
$endTime = null;
$slotDuration = 30;

if ($override) {
    if ($override['status'] === 'off') {
        echo json_encode([
            'available' => false,
            'message' => 'Doctor is not available on this day' . ($override['reason'] ? ' (' . $override['reason'] . ')' : ''),
            'slots' => [],
            'doctor_info' => [
                'name' => $docRow['doctorName'],
                'fees' => $docRow['docFees'],
                'specialization' => $docRow['specilization'],
                'slot_duration' => null,
            ]
        ]);
        exit;
    } elseif ($override['status'] === 'custom') {
        $startTime = $override['start_time'];
        $endTime = $override['end_time'];
        $slotDuration = $override['slot_duration'] ?? 30;
    }
} 

// If no override with custom hours, check weekly schedule
if ($startTime === null) {
    $schStmt = $conn->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ? AND status = 'available'");
    $schStmt->bind_param("ii", $doctorId, $dayOfWeek);
    $schStmt->execute();
    $schRow = $schStmt->get_result()->fetch_assoc();
    $schStmt->close();

    if (!$schRow) {
        // No schedule for this day — check if doctor has ANY schedule
        $anyStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM doctor_schedules WHERE doctor_id = ?");
        $anyStmt->bind_param("i", $doctorId);
        $anyStmt->execute();
        $anyRow = $anyStmt->get_result()->fetch_assoc();
        $anyStmt->close();

        if ((int)$anyRow['cnt'] === 0) {
            // Doctor hasn't set up schedule yet — block booking
            echo json_encode([
                'available' => false,
                'no_schedule' => true,
                'message' => 'This doctor has not set up their schedule yet. Booking is not available until the doctor configures their working hours.',
                'slots' => [],
                'doctor_info' => [
                    'name' => $docRow['doctorName'],
                    'fees' => $docRow['docFees'],
                    'specialization' => $docRow['specilization'],
                    'slot_duration' => null,
                ]
            ]);
            exit;
        }

        // Doctor has schedule but not on this day
        $dayNames = ['Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'];
        echo json_encode([
            'available' => false,
            'message' => 'Doctor is not available on ' . $dayNames[$dayOfWeek] . 's',
            'slots' => [],
            'doctor_info' => [
                'name' => $docRow['doctorName'],
                'fees' => $docRow['docFees'],
                'specialization' => $docRow['specilization'],
                'slot_duration' => null,
            ]
        ]);
        exit;
    }

    $startTime = $schRow['start_time'];
    $endTime = $schRow['end_time'];
    $slotDuration = (int)$schRow['slot_duration'];
}

// Generate time slots
$slots = [];
$startTs = strtotime($date . ' ' . $startTime);
$endTs = strtotime($date . ' ' . $endTime);
$slotSec = $slotDuration * 60;

// Get already booked times for this doctor on this date
$bookedStmt = $conn->prepare("
    SELECT appointmentTime FROM appointment 
    WHERE doctorId = ? AND appointmentDate = ? AND userStatus IN (1, 2) AND doctorStatus IN (1, 2)
");
$bookedStmt->bind_param("is", $doctorId, $date);
$bookedStmt->execute();
$bookedRes = $bookedStmt->get_result();
$bookedTimes = [];
while ($bRow = $bookedRes->fetch_assoc()) {
    // Normalize time to H:i format
    $bt = $bRow['appointmentTime'];
    if ($bt) {
        $bookedTimes[] = date('H:i', strtotime($bt));
    }
}
$bookedStmt->close();

$current = $startTs;
$nowTimestamp = time();
$isToday = ($date === date('Y-m-d'));

while ($current + $slotSec <= $endTs) {
    $timeStr = date('H:i', $current);
    $isBooked = in_array($timeStr, $bookedTimes);
    
    // If the date is today, mark past time slots as unavailable
    $isPast = false;
    if ($isToday && $current < $nowTimestamp) {
        $isPast = true;
    }

    $slots[] = [
        'time' => $timeStr,
        'display' => date('h:i A', $current),
        'booked' => $isBooked,
        'past' => $isPast,
        'available' => !$isBooked && !$isPast,
    ];
    $current += $slotSec;
}

echo json_encode([
    'available' => true,
    'no_schedule' => false,
    'slots' => $slots,
    'is_today' => $isToday,
    'server_time' => date('H:i'),
    'doctor_info' => [
        'name' => $docRow['doctorName'],
        'fees' => $docRow['docFees'],
        'specialization' => $docRow['specilization'],
        'slot_duration' => $slotDuration,
    ]
]);

$conn->close();
