<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/notification-api.php';

$recipient = hms_get_notification_recipient();
if ($recipient['id'] == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$conn = hms_db_connect(false);
if (!$conn || $conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'DB error']);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, title, message, created_at 
    FROM notifications 
    WHERE recipient_type = ? AND recipient_id = ? AND is_read = 0 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->bind_param("si", $recipient['type'], $recipient['id']);
$stmt->execute();
$res = $stmt->get_result();
$unread = [];
while ($row = $res->fetch_assoc()) {
    $unread[] = $row;
}
$stmt->close();
$conn->close();

echo json_encode([
    'status' => 'success',
    'notifications' => $unread
]);
