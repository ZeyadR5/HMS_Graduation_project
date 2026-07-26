<?php
define('HMS_SKIP_AUTO_CONNECT', true);
require_once __DIR__ . '/../../includes/auth.php'; // includes session_start and config.php
header('Content-Type: application/json; charset=utf-8');

// Receptionist security check (role must be 'User' or 'Admin' or 'System Admin')
if (!in_array($_SESSION['role'] ?? '', ['User', 'Admin', 'System Admin'])) {
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

$input = json_decode(file_get_contents('php://input'), true);
$apid = (int)($input['apid'] ?? 0);
$action = $input['action'] ?? ''; // 'update_priority'
$value = $input['value'] ?? '';

if (!$apid || $action !== 'update_priority') {
    echo json_encode(['error' => 'Missing or invalid parameters'], JSON_UNESCAPED_UNICODE);
    exit();
}

if (!hms_validate_csrf($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Security check failed'], JSON_UNESCAPED_UNICODE);
    exit();
}

// Valid priorities: normal, urgent
if (!in_array($value, ['normal', 'urgent'])) {
    echo json_encode(['error' => 'Invalid priority value'], JSON_UNESCAPED_UNICODE);
    exit();
}

// Check current status to prevent priority change after starting/finishing
$checkStmt = $connect->prepare("SELECT patient_status FROM appointment WHERE apid = ?");
$checkStmt->bind_param("i", $apid);
$checkStmt->execute();
$currentRow = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if (!$currentRow) {
    echo json_encode(['error' => 'Appointment not found'], JSON_UNESCAPED_UNICODE);
    exit();
}

$currentStatus = $currentRow['patient_status'] ?? 'waiting';

if (in_array($currentStatus, ['in progress', 'done'])) {
    echo json_encode(['error' => 'لا يمكن تغيير درجة الأولوية بعد بدء الكشف أو انتهائه.'], JSON_UNESCAPED_UNICODE);
    exit();
}

$stmt = $connect->prepare("UPDATE appointment SET priority = ? WHERE apid = ?");
$stmt->bind_param("si", $value, $apid);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => $stmt->error]);
}

$connect->close();
?>
