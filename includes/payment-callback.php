<?php
/**
 * Payment Callback Handler (Webhook)
 * ===================================
 * Receives payment notifications from Paymob.
 * URL to register in Paymob: https://yourdomain.com/hms/includes/payment-callback.php
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/payment-config.php';
require_once __DIR__ . '/payment-gateway.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Get callback data
$data = $_REQUEST;
if (empty($data)) {
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody, true) ?? [];
}

if (empty($data)) {
    http_response_code(400);
    exit('No data received');
}

// Verify and process
$result = HmsPaymentGateway::verifyCallback($data);

if (!$result['success']) {
    http_response_code(400);
    error_log('HMS Payment Callback FAILED: ' . json_encode($result));
    exit('Verification failed');
}

$appointmentId = $result['appointment_id'];
$transactionId = $result['transaction_id'];
$channel = $result['channel'];

if ($appointmentId <= 0) {
    http_response_code(400);
    exit('Invalid appointment ID');
}

$conn = hms_db_connect();
$stmt = $conn->prepare("UPDATE appointment SET deposit_status = 'paid', deposit_transaction_id = ?, deposit_channel = ?, deposit_paid_at = NOW() WHERE apid = ? AND deposit_status = 'pending'");
$stmt->bind_param("ssi", $transactionId, $channel, $appointmentId);
$stmt->execute();
$stmt->close();
$conn->close();

http_response_code(200);
echo json_encode(['status' => 'ok']);
