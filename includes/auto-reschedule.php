<?php
/**
 * Auto-Reschedule Late Patients
 * 
 * Checks for patients who are 15+ minutes late for their appointment today.
 * - If late for the first time: Rescheduled to the next available slot today (at least 1 hour later) without conflicts.
 * - If no slot is available today, or if they are late a second time for a postponed slot: Rescheduled to the next available day.
 * 
 * This script is designed to be included from pages that refresh regularly
 * (like queue-screen.php or doc-Reservations.php).
 */

if (!function_exists('hms_get_doctor_schedule_for_date')) {
    function hms_get_doctor_schedule_for_date(mysqli $conn, int $doctorId, string $date, array $dayMap): ?array
    {
        // 1. Check for override
        $ovStmt = $conn->prepare("SELECT status, start_time, end_time, slot_duration FROM doctor_day_overrides WHERE doctor_id = ? AND override_date = ?");
        $ovStmt->bind_param("is", $doctorId, $date);
        $ovStmt->execute();
        $override = $ovStmt->get_result()->fetch_assoc();
        $ovStmt->close();

        if ($override) {
            if ($override['status'] === 'off') {
                return null; // Day is off
            }
            return [
                'start_time' => $override['start_time'],
                'end_time' => $override['end_time'],
                'slot_duration' => $override['slot_duration'] ?? 30
            ];
        }

        // 2. Check weekly schedule
        $phpDow = (int)date('w', strtotime($date));
        $systemDow = $dayMap[$phpDow];
        $schStmt = $conn->prepare("SELECT start_time, end_time, slot_duration FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ? AND status = 'available'");
        $schStmt->bind_param("ii", $doctorId, $systemDow);
        $schStmt->execute();
        $schRow = $schStmt->get_result()->fetch_assoc();
        $schStmt->close();

        if ($schRow) {
            return [
                'start_time' => $schRow['start_time'],
                'end_time' => $schRow['end_time'],
                'slot_duration' => (int)$schRow['slot_duration']
            ];
        }

        return null;
    }
}

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
                   a.doctorSpecialization, a.consultancyFees, a.late_reschedule_count,
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
            $lateCount = (int)$appt['late_reschedule_count'];
            $patientName = $appt['patient_Name'];
            $doctorName = $appt['doctorName'];

            $newDate = null;
            $newTime = null;
            $rescheduledToNextDay = false;

            if ($lateCount == 0) {
                // First time late: reschedule to the next hour today
                $todaySchedule = hms_get_doctor_schedule_for_date($conn, $doctorId, $today, $dayMap);
                if ($todaySchedule !== null) {
                    $endTimeTs = strtotime($today . ' ' . $todaySchedule['end_time']);
                    $slotDuration = (int)$todaySchedule['slot_duration'];
                    $startSearchTs = strtotime($today . ' ' . $originalTime) + 3600; // 1 hour later
                    $slotSec = $slotDuration * 60;

                    $currentTs = $startSearchTs;
                    while ($currentTs + $slotSec <= $endTimeTs) {
                        if ($currentTs <= $now) {
                            // Skip slots that have already passed in real time
                            $currentTs += $slotSec;
                            continue;
                        }
                        $candidateTimeStr = date('H:i:s', $currentTs);

                        // Check if slot is free today
                        $bookStmt = $conn->prepare("
                            SELECT COUNT(*) as cnt FROM appointment 
                            WHERE doctorId = ? AND appointmentDate = ? 
                            AND appointmentTime = ?
                            AND userStatus IN (1, 2) AND doctorStatus IN (1, 2)
                        ");
                        $bookStmt->bind_param("iss", $doctorId, $today, $candidateTimeStr);
                        $bookStmt->execute();
                        $bookRow = $bookStmt->get_result()->fetch_assoc();
                        $bookStmt->close();

                        if ((int)$bookRow['cnt'] === 0) {
                            $newDate = $today;
                            $newTime = date('H:i:s', $currentTs);
                            break;
                        }
                        $currentTs += $slotSec;
                    }
                }

                // If no available slot is found today, fallback to Case 2 (reschedule to next day)
                if ($newDate === null) {
                    $rescheduledToNextDay = true;
                }
            } else {
                // Second time late: reschedule to the next day
                $rescheduledToNextDay = true;
            }

            if ($rescheduledToNextDay) {
                // Search up to 30 days ahead for the next available slot
                for ($dayOffset = 1; $dayOffset <= 30; $dayOffset++) {
                    $candidateDate = date('Y-m-d', strtotime("+{$dayOffset} days"));
                    $candSchedule = hms_get_doctor_schedule_for_date($conn, $doctorId, $candidateDate, $dayMap);
                    if ($candSchedule === null) continue;

                    // Try to keep the original time if it fits the doctor's shift on that day
                    $candStartTs = strtotime($candidateDate . ' ' . $candSchedule['start_time']);
                    $candEndTs = strtotime($candidateDate . ' ' . $candSchedule['end_time']);
                    $originalTimeTs = strtotime($candidateDate . ' ' . $originalTime);
                    $targetTimeStr = date('H:i:s', strtotime($originalTime));

                    if ($originalTimeTs >= $candStartTs && $originalTimeTs + ($candSchedule['slot_duration'] * 60) <= $candEndTs) {
                        // Check if free
                        $bookStmt = $conn->prepare("
                            SELECT COUNT(*) as cnt FROM appointment 
                            WHERE doctorId = ? AND appointmentDate = ? 
                            AND appointmentTime = ?
                            AND userStatus IN (1, 2) AND doctorStatus IN (1, 2)
                        ");
                        $bookStmt->bind_param("iss", $doctorId, $candidateDate, $targetTimeStr);
                        $bookStmt->execute();
                        $bookRow = $bookStmt->get_result()->fetch_assoc();
                        $bookStmt->close();

                        if ((int)$bookRow['cnt'] === 0) {
                            $newDate = $candidateDate;
                            $newTime = $targetTimeStr;
                            break;
                        }
                    }

                    // Otherwise, find the first available slot on that candidate day
                    $currentTs = $candStartTs;
                    $slotSec = $candSchedule['slot_duration'] * 60;
                    $foundSlot = false;
                    while ($currentTs + $slotSec <= $candEndTs) {
                        $candidateTimeStr = date('H:i:s', $currentTs);

                        $bookStmt = $conn->prepare("
                            SELECT COUNT(*) as cnt FROM appointment 
                            WHERE doctorId = ? AND appointmentDate = ? 
                            AND appointmentTime = ?
                            AND userStatus IN (1, 2) AND doctorStatus IN (1, 2)
                        ");
                        $bookStmt->bind_param("iss", $doctorId, $candidateDate, $candidateTimeStr);
                        $bookStmt->execute();
                        $bookRow = $bookStmt->get_result()->fetch_assoc();
                        $bookStmt->close();

                        if ((int)$bookRow['cnt'] === 0) {
                            $newDate = $candidateDate;
                            $newTime = $candidateTimeStr;
                            $foundSlot = true;
                            break;
                        }
                        $currentTs += $slotSec;
                    }
                    if ($foundSlot) {
                        break;
                    }
                }
            }

            if ($newDate === null || $newTime === null) {
                continue; // Could not reschedule
            }

            // Update appointment
            $nextLateCount = $rescheduledToNextDay ? 0 : ($lateCount + 1);
            $updateStmt = $conn->prepare("UPDATE appointment SET appointmentDate = ?, appointmentTime = ?, late_reschedule_count = ? WHERE apid = ?");
            $updateStmt->bind_param("ssii", $newDate, $newTime, $nextLateCount, $apid);
            if ($updateStmt->execute()) {
                $rescheduled[] = [
                    'apid' => $apid,
                    'patient' => $patientName,
                    'doctor' => $doctorName,
                    'old_date' => $today,
                    'new_date' => $newDate,
                    'time' => $newTime,
                    'rescheduled_to_next_day' => $rescheduledToNextDay
                ];

                // Notifications
                $patientUid = (int)$appt['userId'];
                if ($patientUid > 0) {
                    $accStmt = $conn->prepare("SELECT uid FROM users WHERE uid = ? AND email IS NOT NULL AND email != '' AND password IS NOT NULL AND password != ''");
                    $accStmt->bind_param("i", $patientUid);
                    $accStmt->execute();
                    $hasAccount = $accStmt->get_result()->num_rows > 0;
                    $accStmt->close();

                    if ($hasAccount) {
                        require_once __DIR__ . '/notification-api.php';
                        if ($rescheduledToNextDay) {
                            $title = 'تأجيل موعدك للغد ⏰';
                            $message = 'تنبيه: بسبب التأخر للمرة الثانية عن موعدك المؤجل (' . date('h:i A', strtotime($originalTime)) . ')، تم تأجيل حجزك عند د. ' . $doctorName . ' إلى يوم ' . $newDate . ' الساعة (' . date('h:i A', strtotime($newTime)) . ').';
                        } else {
                            $title = 'تم تأجيل موعدك ساعة ⏰';
                            $message = 'تنبيه: بسبب التأخر عن موعدك الأصلي (' . date('h:i A', strtotime($originalTime)) . ')، تم تأجيل حجزك عند د. ' . $doctorName . ' لليوم إلى الساعة (' . date('h:i A', strtotime($newTime)) . ') لضمان عدم إلغاء حجزك.';
                        }

                        hms_create_notification($conn, [
                            'recipient_type' => 'patient',
                            'recipient_id' => $patientUid,
                            'title' => $title,
                            'message' => $message,
                            'type' => 'reschedule',
                            'related_doctor_id' => $doctorId,
                            'related_appointment_id' => $apid,
                        ]);
                    }
                }

                // Notify Doctor
                require_once __DIR__ . '/notification-api.php';
                if ($rescheduledToNextDay) {
                    $docMessage = 'المريض ' . $patientName . ' تأخر للمرة الثانية عن موعده المؤجل. تم ترحيل حجزه تلقائياً إلى الغد/اليوم التالي ' . $newDate . ' الساعة (' . date('h:i A', strtotime($newTime)) . ').';
                } else {
                    $docMessage = 'المريض ' . $patientName . ' تأخر عن موعده الأصلي. تم تأجيل حجزه تلقائياً لليوم إلى الساعة (' . date('h:i A', strtotime($newTime)) . ').';
                }

                hms_create_notification($conn, [
                    'recipient_type' => 'doctor',
                    'recipient_id' => $doctorId,
                    'title' => 'تأجيل حجز — ' . $patientName,
                    'message' => $docMessage,
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
