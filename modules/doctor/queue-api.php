<?php
define('HMS_SKIP_AUTO_CONNECT', true);
require_once __DIR__ . '/../../includes/auth.php'; // includes session_start and config.php
header('Content-Type: application/json; charset=utf-8');

// Additional security check for Doctor role (auth.php already checks if logged in)
if (($_SESSION['role'] ?? '') !== 'Doctor') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit();
}

$connect = hms_db_connect(false);
if (!$connect) {
    http_response_code(500);
    echo json_encode(['error' => 'DB Connection Failed'], JSON_UNESCAPED_UNICODE);
    exit();
}


$doctorId = (int)($_SESSION['id'] ?? 0);

$input = json_decode(file_get_contents('php://input'), true);
$apid = (int)($input['apid'] ?? 0);
$action = $input['action'] ?? ''; // 'update_status', 'update_priority'
$value = $input['value'] ?? '';

if (!$apid || !$action) {
    echo json_encode(['error' => 'Missing parameters'], JSON_UNESCAPED_UNICODE);
    exit();
}

if (!hms_validate_csrf($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Security check failed'], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($action === 'update_status') {
    // Valid statuses: waiting, in progress (done is only set when report is submitted)
    if (!in_array($value, ['waiting', 'in progress'])) {
        echo json_encode(['error' => 'Invalid status. Finish is only available after writing the report.'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Check current status to prevent reverting
    $checkStmt = $connect->prepare("SELECT patient_status FROM appointment WHERE apid = ? AND doctorId = ?");
    $checkStmt->bind_param("ii", $apid, $doctorId);
    $checkStmt->execute();
    $currentRow = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$currentRow) {
        echo json_encode(['error' => 'Appointment not found or access denied'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $currentStatus = $currentRow['patient_status'] ?? 'waiting';

    // Logic: 
    // 1. If Done, no more changes
    if ($currentStatus === 'done') {
        echo json_encode(['error' => 'هذا الكشف تم بالفعل ولا يمكن تغيير حالته.'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    // 2. If In Progress, cannot go back to Waiting
    if ($currentStatus === 'in progress' && $value === 'waiting') {
        echo json_encode(['error' => 'لا يمكن إعادة الحالة إلى الانتظار بعد بدء الكشف.'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $stmt = $connect->prepare("UPDATE appointment SET patient_status = ? WHERE apid = ? AND doctorId = ?");
    $stmt->bind_param("sii", $value, $apid, $doctorId);
    if ($stmt->execute()) {
        // Send notification to the patient if they have a registered account
        $apptStmt = $connect->prepare("SELECT a.userId, a.patient_Name, a.appointmentTime, a.doctorId, d.doctorName
                                        FROM appointment a
                                        JOIN doctors d ON d.id = a.doctorId
                                        WHERE a.apid = ? AND a.doctorId = ?");
        $apptStmt->bind_param("ii", $apid, $doctorId);
        $apptStmt->execute();
        $apptRow = $apptStmt->get_result()->fetch_assoc();
        $apptStmt->close();

        if ($apptRow && (int)$apptRow['userId'] > 0) {
            $patientUid = (int)$apptRow['userId'];
            // Check if patient has a real account (email + password)
            $accStmt = $connect->prepare("SELECT uid FROM users WHERE uid = ? AND email IS NOT NULL AND email != '' AND password IS NOT NULL AND password != ''");
            $accStmt->bind_param("i", $patientUid);
            $accStmt->execute();
            $hasAccount = $accStmt->get_result()->num_rows > 0;
            $accStmt->close();

            if ($hasAccount) {
                require_once __DIR__ . '/../../includes/notification-api.php';
                $doctorName = $apptRow['doctorName'] ?? 'Doctor';

                if ($value === 'in progress') {
                    hms_create_notification($connect, [
                        'recipient_type' => 'patient',
                        'recipient_id' => $patientUid,
                        'title' => 'دورك دلوقتي! 🏥',
                        'message' => 'اتفضل توجه لعيادة د. ' . $doctorName . ' — دورك جه.',
                        'type' => 'queue',
                        'related_doctor_id' => (int)$apptRow['doctorId'],
                        'related_appointment_id' => $apid,
                    ]);
                } elseif ($value === 'waiting') {
                    // Calculate queue position
                    $posStmt = $connect->prepare("SELECT COUNT(*) as pos FROM appointment 
                                                   WHERE doctorId = ? AND appointmentDate = CURRENT_DATE() 
                                                   AND userStatus IN (1,2) AND patient_status = 'waiting'
                                                   AND apid <= ?
                                                   ORDER BY (priority='urgent') DESC, appointmentTime ASC, postingDate ASC");
                    $posStmt->bind_param("ii", $apptRow['doctorId'], $apid);
                    $posStmt->execute();
                    $posRow = $posStmt->get_result()->fetch_assoc();
                    $posStmt->close();
                    $position = (int)($posRow['pos'] ?? 0);

                    hms_create_notification($connect, [
                        'recipient_type' => 'patient',
                        'recipient_id' => $patientUid,
                        'title' => 'أنت في قائمة الانتظار 📋',
                        'message' => 'أنت في الدور رقم ' . $position . ' عند د. ' . $doctorName . '. هنبلغك لما دورك يجي.',
                        'type' => 'queue',
                        'related_doctor_id' => (int)$apptRow['doctorId'],
                        'related_appointment_id' => $apid,
                    ]);
                }
            }
        }

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => $stmt->error]);
    }
} elseif ($action === 'update_priority') {
    // Valid priorities: normal, urgent
    if (!in_array($value, ['normal', 'urgent'])) {
        echo json_encode(['error' => 'Invalid priority'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Check current status to prevent priority change after starting/finishing
    $checkStmt = $connect->prepare("SELECT patient_status FROM appointment WHERE apid = ? AND doctorId = ?");
    $checkStmt->bind_param("ii", $apid, $doctorId);
    $checkStmt->execute();
    $currentRow = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$currentRow) {
        echo json_encode(['error' => 'Appointment not found or access denied'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $currentStatus = $currentRow['patient_status'] ?? 'waiting';

    if (in_array($currentStatus, ['in progress', 'done'])) {
        echo json_encode(['error' => 'لا يمكن تغيير درجة الأولوية بعد بدء الكشف أو انتهائه.'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $stmt = $connect->prepare("UPDATE appointment SET priority = ? WHERE apid = ? AND doctorId = ?");
    $stmt->bind_param("sii", $value, $apid, $doctorId);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => $stmt->error]);
    }
}
$connect->close();
