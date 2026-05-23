<?php
require_once __DIR__ . '/../../includes/auth.php';

$connect = hms_db_connect();
if ($connect->connect_error) die("Connection failed");

$apid    = intval($_GET['id']   ?? 0);
$return  = basename($_GET['from'] ?? 'admin-Reservations.php');

hms_require_csrf('./' . $return);

// تحقق إن الحجز موجود
$res = $connect->query("SELECT apid, userStatus FROM appointment WHERE apid=$apid");
if ($res->num_rows === 0) {
    echo "<script>alert('Appointment not found!'); window.location.href='./$return';</script>"; exit();
}
$row = $res->fetch_assoc();
if ($row['userStatus'] == 0) {
    echo "<script>alert('Already cancelled!'); window.location.href='./$return';</script>"; exit();
}

// إلغاء من جانب الأدمن — نستخدم userStatus=0
$stmt = $connect->prepare("SELECT doctorId, userId, patient_Name, appointmentDate, appointmentTime FROM appointment WHERE apid=?");
$stmt->bind_param("i", $apid);
$stmt->execute();
$apptData = $stmt->get_result()->fetch_assoc();

if ($connect->query("UPDATE appointment SET userStatus=0, cancelledBy='Admin' WHERE apid=$apid")) {
    require_once __DIR__ . '/../../includes/notification-api.php';
    
    $patientName = $apptData['patient_Name'];
    $doctorId = (int)$apptData['doctorId'];
    $date = $apptData['appointmentDate'];
    $time = $apptData['appointmentTime'];

    // 1. Notify Doctor
    hms_create_notification($connect, [
        'recipient_type' => 'doctor',
        'recipient_id' => $doctorId,
        'title' => 'Appointment Cancelled by Admin — ' . $patientName,
        'message' => "Admin has cancelled the appointment for $patientName on $date at $time.",
        'type' => 'cancellation',
        'related_doctor_id' => $doctorId,
        'related_appointment_id' => $apid
    ]);

    // 2. Notify Patient
    if (!empty($apptData['userId'])) {
        hms_create_notification($connect, [
            'recipient_type' => 'patient',
            'recipient_id' => (int)$apptData['userId'],
            'title' => 'Appointment Cancelled by Hospital — ' . $date,
            'message' => "Your appointment on $date at $time has been cancelled by the hospital administration.",
            'type' => 'cancellation',
            'related_doctor_id' => $doctorId,
            'related_appointment_id' => $apid
        ]);
    }

    // 3. Notify Reception (Employee role) and other Admins
    $staffStmt = $connect->prepare("SELECT id, role FROM employ WHERE role IN ('User', 'Admin', 'System Admin')");
    $staffStmt->execute();
    $staffRes = $staffStmt->get_result();
    while ($staff = $staffRes->fetch_assoc()) {
        $isRelReception = ($staff['role'] === 'User');
        hms_create_notification($connect, [
            'recipient_type' => $isRelReception ? 'employee' : 'admin',
            'recipient_id' => (int)$staff['id'],
            'title' => 'Admin Cancellation — ' . $patientName,
            'message' => "An appointment for $patientName on $date has been cancelled by Admin.",
            'type' => 'cancellation',
            'related_doctor_id' => $doctorId,
            'related_appointment_id' => $apid
        ]);
    }
    $staffStmt->close();
}

echo "<script>alert('Appointment cancelled successfully.'); window.location.href='./$return';</script>";
exit();
?>
