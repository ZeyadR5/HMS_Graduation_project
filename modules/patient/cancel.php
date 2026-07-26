<?php
/**
 * cancel.php — Patient appointment cancellation handler (optimized)
 *
 * Accepts:
 *   ?ref=<encrypted apid>  — secure appointment reference
 *   ?return=<url>          — where to redirect after cancellation (optional)
 *   CSRF token in query string
 *
 * Performance notes:
 *   - mirror_email => false for all staff notifications (no synchronous SMTP loop)
 *   - Pure PHP header() redirect — no blocking JS alert()
 *   - Single DB update + doctor notification only sends 1 email max
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/secure-token.php';
require_once __DIR__ . '/../../includes/notification-api.php';

$connect = hms_db_connect();
$uid     = (int)($_SESSION['uid'] ?? 0);

// ── Determine return URL (fallback to calender) ───────────────────────────────
$returnRaw  = $_GET['return'] ?? './calender.php?cancelled=1';
$returnUrl  = filter_var($returnRaw, FILTER_SANITIZE_URL);
// Only allow relative paths to prevent open redirect
if (!$returnUrl || str_starts_with($returnUrl, 'http')) {
    $returnUrl = './calender.php?cancelled=1';
}

// ── Validate CSRF ─────────────────────────────────────────────────────────────
hms_require_csrf($returnUrl);

// ── Decrypt appointment ID ────────────────────────────────────────────────────
$apid = 0;
if (!empty($_GET['ref'])) {
    $decrypted = hms_decrypt_id($_GET['ref']);
    if ($decrypted !== null) {
        $apid = (int)$decrypted;
    }
} elseif (!empty($_GET['id'])) {
    // Legacy plain ID fallback
    $apid = (int)$_GET['id'];
}

if ($apid <= 0 || $uid <= 0) {
    header("Location: $returnUrl");
    exit();
}

// ── Fetch appointment (must belong to this patient) ───────────────────────────
$stmt = $connect->prepare(
    "SELECT apid, userStatus, doctorId, patient_Name, appointmentDate, appointmentTime
     FROM appointment WHERE apid = ? AND userId = ? LIMIT 1"
);
$stmt->bind_param("ii", $apid, $uid);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    // Not found or doesn't belong to this patient — redirect silently
    header("Location: $returnUrl");
    exit();
}

if ((int)$row['userStatus'] === 0) {
    // Already cancelled — just redirect, no need to process again
    header("Location: $returnUrl");
    exit();
}

// ── Perform cancellation ──────────────────────────────────────────────────────
$upd = $connect->prepare(
    "UPDATE appointment SET userStatus = 0, cancelledBy = 'Patient' WHERE apid = ? AND userId = ?"
);
$upd->bind_param("ii", $apid, $uid);

if ($upd->execute() && $upd->affected_rows > 0) {
    $patientName = trim((string)($row['patient_Name'] ?? '')) ?: ($_SESSION['username'] ?? 'Patient');
    $doctorId    = (int)$row['doctorId'];
    $date        = $row['appointmentDate'];
    $time        = $row['appointmentTime'];

    // ── Notify Doctor (1 email is acceptable — fast) ──────────────────────────
    hms_create_notification($connect, [
        'recipient_type'         => 'doctor',
        'recipient_id'           => $doctorId,
        'title'                  => 'Appointment Cancelled — ' . $patientName,
        'message'                => "Patient {$patientName} cancelled their appointment on {$date} at {$time}.",
        'type'                   => 'cancellation',
        'related_doctor_id'      => $doctorId,
        'related_appointment_id' => $apid,
        // Doctor email is intentional — only 1 email, not a loop
    ]);

    // ── Notify Staff & Admins (DB notification only — NO email loop) ──────────
    // mirror_email => false prevents the synchronous SMTP loop that caused hanging
    $staffRes = $connect->query(
        "SELECT id FROM employ WHERE role IN ('User', 'Admin', 'System Admin')"
    );
    if ($staffRes) {
        while ($emp = $staffRes->fetch_assoc()) {
            hms_create_notification($connect, [
                'recipient_type'         => 'employee',
                'recipient_id'           => (int)$emp['id'],
                'title'                  => 'Patient Cancellation — ' . $patientName,
                'message'                => "Patient {$patientName} cancelled an appointment (Doctor ID {$doctorId}) on {$date}.",
                'type'                   => 'cancellation',
                'related_doctor_id'      => $doctorId,
                'related_appointment_id' => $apid,
                'mirror_email'           => false, // ← CRITICAL: no SMTP per staff member
            ]);
        }
    }
}
$upd->close();

// ── Fast redirect — no JS alert blocking ─────────────────────────────────────
header("Location: $returnUrl");
exit();
?>
