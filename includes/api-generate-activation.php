<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$role = $_SESSION['role'] ?? '';
// Only allow non-patients to generate activation codes
if (!in_array($role, ['Admin', 'System Admin', 'User'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$connect = hms_db_connect();
if ($connect->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$uid = isset($data['uid']) ? (int)$data['uid'] : 0;

if ($uid <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid patient ID']);
    exit();
}

// Check if patient exists
$stmt = $connect->prepare("SELECT uid, fullName, nat_id FROM users WHERE uid = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Patient not found']);
    exit();
}
$patient = $res->fetch_assoc();
$stmt->close();

// Generate 6-digit code
$activationCode = sprintf("%06d", mt_rand(100000, 999999));
// Expire in 24 hours
$activationExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

$updateStmt = $connect->prepare("UPDATE users SET activation_code = ?, activation_expiry = ? WHERE uid = ?");
$updateStmt->bind_param("ssi", $activationCode, $activationExpiry, $uid);

if ($updateStmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'code' => $activationCode,
        'expiry' => $activationExpiry,
        'message' => 'Activation code generated successfully.'
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save activation code']);
}
$updateStmt->close();
$connect->close();
