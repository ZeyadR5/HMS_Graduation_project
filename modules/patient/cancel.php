<?php
require_once __DIR__ . '/../../includes/auth.php';

$connect = hms_db_connect();

require_once __DIR__ . '/../../includes/notification-api.php';

$apid = intval($_GET['id'] ?? 0);
$uid  = $_SESSION['uid'];

hms_require_csrf('./Medical-Record.php');

// تأكد إن الحجز ده بتاع المريض ده فعلاً
$stmt = $connect->prepare("SELECT apid, userStatus, doctorId, patient_Name, appointmentDate, appointmentTime FROM appointment WHERE apid=? AND userId=?");
$stmt->bind_param("ii", $apid, $uid);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "<script>alert('Appointment not found!'); window.location.href='./Medical-Record.php';</script>";
    exit();
}

$row = $res->fetch_assoc();

if ($row['userStatus'] == 0) {
    echo "<script>alert('Already cancelled!'); window.location.href='./Medical-Record.php';</script>";
    exit();
}

// إلغاء الحجز: userStatus = 0
$update = $connect->prepare("UPDATE appointment SET userStatus=0, cancelledBy='Patient' WHERE apid=? AND userId=?");
$update->bind_param("ii", $apid, $uid);
if ($update->execute()) {
    // Create notification for the doctor
    $patientName = $row['patient_Name'] ?: $_SESSION['username'];
    $doctorId = (int)$row['doctorId'];
    
    $notifDoctor = hms_create_notification($connect, [
        'recipient_type' => 'doctor',
        'recipient_id' => $doctorId,
        'title' => 'Appointment Cancelled — ' . $patientName,
        'message' => "Patient $patientName has cancelled their appointment scheduled for " . $row['appointmentDate'] . " at " . $row['appointmentTime'] . ".",
        'type' => 'cancellation',
        'related_doctor_id' => $doctorId,
        'related_appointment_id' => $apid
    ]);

    if (!$notifDoctor) {
        error_log("HMS: Doctor notification failed for APID $apid. Error: " . $connect->error);
    }

    // Notify staff (Receptionists and Admins)
    $staffStmt = $connect->prepare("SELECT id, role FROM employ WHERE role IN ('User', 'Admin', 'System Admin')");
    $staffStmt->execute();
    $staffRes = $staffStmt->get_result();
    while ($emp = $staffRes->fetch_assoc()) {
        $notifEmp = hms_create_notification($connect, [
            'recipient_type' => 'employee',
            'recipient_id' => (int)$emp['id'],
            'title' => 'Patient Cancellation — ' . $patientName,
            'message' => "Patient $patientName has cancelled their appointment with Doctor ID $doctorId on " . $row['appointmentDate'] . ".",
            'type' => 'cancellation',
            'related_doctor_id' => $doctorId,
            'related_appointment_id' => $apid
        ]);
        
        if (!$notifEmp) {
            error_log("HMS: Employee notification failed for UID " . $emp['id'] . ". Error: " . $connect->error);
        }
    }
} else {
    error_log("HMS: Update failed for APID $apid. Error: " . $connect->error);
}

echo "<script>alert('Appointment cancelled successfully.'); window.location.href='./Medical-Record.php';</script>";
exit();
?>
