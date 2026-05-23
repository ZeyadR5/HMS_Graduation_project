<?php
/**
 * Auto-Reschedule Late Patients
 * 
 * Checks for patients who are 15+ minutes late for their appointment today.
 * If late and still in 'waiting' status, the appointment is automatically
 * rescheduled to the next available working day for the same doctor at the same time.
 * 
 * This script is designed to be included from pages that refresh regularly
 * (like queue-screen.php or doc-Reservations.php).
 */

if (!function_exists('hms_check_late_patients')) {
    function hms_check_late_patients(mysqli $conn): array
    {
        date_default_timezone_set('Africa/Cairo');
        $rescheduled = [];
        $now = time();
        $today = date('Y-m-d');
        $lateThresholdMinutes = 15;

        // Find appointments for today that are still 'waiting' and past their time + 15 min
        $stmt = $conn->prepare("
            SELECT a.apid, a.userId, a.doctorId, a.appointmentTime, a.patient_Name,
                   a.doctorSpecialization, a.consultancyFees,
                   d.doctorName
            FROM appointment a
            JOIN doctors d ON d.id = a.doctorId
            WHERE a.appointmentDate = ?
              AND a.patient_status = 'waiting'
              AND a.userStatus = 1
              AND a.doctorStatus = 1
              AND a.appointmentTime IS NOT NULL
              AND a.appointmentTime != ''
        ");
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();

        $lateAppointments = [];
        while ($row = $result->fetch_assoc()) {
            // Parse appointment time and check if 15+ minutes late
            $apptTimestamp = strtotime($today . ' ' . $row['appointmentTime']);
            if ($apptTimestamp && ($now - $apptTimestamp) >= ($lateThresholdMinutes * 60)) {
                $lateAppointments[] = $row;
            }
        }
        $stmt->close();

        // Day mapping: PHP dow (0=Sun..6=Sat) -> System dow (0=Sat..6=Fri)
        $dayMap = [6 => 0, 0 => 1, 1 => 2, 2 => 3, 3 => 4, 4 => 5, 5 => 6];

        foreach ($lateAppointments as $appt) {
            $doctorId = (int)$appt['doctorId'];
            $apid = (int)$appt['apid'];
            $originalTime = $appt['appointmentTime'];

            // Normalize time to HH:MM
            $timeNormalized = date('H:i', strtotime($originalTime));

            // Get doctor's working days
            $schStmt = $conn->prepare("SELECT day_of_week FROM doctor_schedules WHERE doctor_id = ? AND status = 'available'");
            $schStmt->bind_param("i", $doctorId);
            $schStmt->execute();
            $schRes = $schStmt->get_result();
            $workingDays = [];
            while ($schRow = $schRes->fetch_assoc()) {
                $workingDays[] = (int)$schRow['day_of_week'];
            }
            $schStmt->close();

            if (empty($workingDays)) continue; // Doctor has no schedule

            // Search up to 30 days ahead for the next available slot
            $newDate = null;
            for ($dayOffset = 1; $dayOffset <= 30; $dayOffset++) {
                $candidateDate = date('Y-m-d', strtotime("+{$dayOffset} days"));
                $candidatePhpDow = (int)date('w', strtotime($candidateDate));
                $candidateSystemDow = $dayMap[$candidatePhpDow];

                // Check if this day is a working day
                if (!in_array($candidateSystemDow, $workingDays)) continue;

                // Check for day override (off)
                $ovStmt = $conn->prepare("SELECT status FROM doctor_day_overrides WHERE doctor_id = ? AND override_date = ?");
                $ovStmt->bind_param("is", $doctorId, $candidateDate);
                $ovStmt->execute();
                $ovRow = $ovStmt->get_result()->fetch_assoc();
                $ovStmt->close();

                if ($ovRow && $ovRow['status'] === 'off') continue; // Day is blocked

                // Check if the same time slot is available (not already booked)
                $bookStmt = $conn->prepare("
                    SELECT COUNT(*) as cnt FROM appointment 
                    WHERE doctorId = ? AND appointmentDate = ? 
                    AND appointmentTime = ?
                    AND userStatus IN (1, 2) AND doctorStatus IN (1, 2)
                ");
                $bookStmt->bind_param("iss", $doctorId, $candidateDate, $timeNormalized);
                $bookStmt->execute();
                $bookRow = $bookStmt->get_result()->fetch_assoc();
                $bookStmt->close();

                if ((int)$bookRow['cnt'] === 0) {
                    // Slot is free! Use this date
                    $newDate = $candidateDate;
                    break;
                }
            }

            if (!$newDate) continue; // No available date found within 30 days

            // Reschedule: update appointment date
            $updateStmt = $conn->prepare("UPDATE appointment SET appointmentDate = ?, appointmentTime = ? WHERE apid = ?");
            $updateStmt->bind_param("ssi", $newDate, $timeNormalized, $apid);
            if ($updateStmt->execute()) {
                $rescheduled[] = [
                    'apid' => $apid,
                    'patient' => $appt['patient_Name'],
                    'doctor' => $appt['doctorName'],
                    'old_date' => $today,
                    'new_date' => $newDate,
                    'time' => $timeNormalized,
                ];

                // Notify patient if they have a registered account
                $patientUid = (int)$appt['userId'];
                if ($patientUid > 0) {
                    $accStmt = $conn->prepare("SELECT uid FROM users WHERE uid = ? AND email IS NOT NULL AND email != '' AND password IS NOT NULL AND password != ''");
                    $accStmt->bind_param("i", $patientUid);
                    $accStmt->execute();
                    $hasAccount = $accStmt->get_result()->num_rows > 0;
                    $accStmt->close();

                    if ($hasAccount) {
                        require_once __DIR__ . '/notification-api.php';
                        hms_create_notification($conn, [
                            'recipient_type' => 'patient',
                            'recipient_id' => $patientUid,
                            'title' => 'تم تأجيل موعدك ⏰',
                            'message' => 'بسبب التأخر عن الموعد (' . $originalTime . ') — تم تأجيل حجزك عند د. ' . $appt['doctorName'] . ' لتاريخ ' . $newDate . ' في نفس الوقت.',
                            'type' => 'reschedule',
                            'related_doctor_id' => $doctorId,
                            'related_appointment_id' => $apid,
                        ]);
                    }
                }

                // Notify the doctor
                require_once __DIR__ . '/notification-api.php';
                hms_create_notification($conn, [
                    'recipient_type' => 'doctor',
                    'recipient_id' => $doctorId,
                    'title' => 'تأجيل تلقائي — ' . $appt['patient_Name'],
                    'message' => 'المريض ' . $appt['patient_Name'] . ' تأخر أكتر من 15 دقيقة عن موعد ' . $originalTime . '. تم تأجيل الموعد تلقائياً لـ ' . $newDate . '.',
                    'type' => 'reschedule',
                    'related_doctor_id' => $doctorId,
                    'related_appointment_id' => $apid,
                ]);
            }
            $updateStmt->close();
        }

        return $rescheduled;
    }
}
