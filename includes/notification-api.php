<?php
/**
 * Notification API — Helper functions for the notification system
 */

if (!function_exists('hms_create_notification')) {
    /**
     * Create a notification and mirror it to email if the recipient has one.
     */
    function hms_create_notification(mysqli $conn, array $data): bool
    {
        $stmt = $conn->prepare("
            INSERT INTO notifications (recipient_type, recipient_id, title, message, type, related_doctor_id, related_appointment_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $recipientType = $data['recipient_type'];
        $recipientId   = (int)$data['recipient_id'];
        $title         = $data['title'];
        $message       = $data['message'];
        $type          = $data['type'] ?? 'system';
        $doctorId      = $data['related_doctor_id'] ?? null;
        $appointmentId = $data['related_appointment_id'] ?? null;

        $stmt->bind_param("sisssii", $recipientType, $recipientId, $title, $message, $type, $doctorId, $appointmentId);
        $result = $stmt->execute();
        $stmt->close();

        // ── Mirror to email ───────────────────────────────────────────────────
        $mirrorEmail = $data['mirror_email'] ?? true;
        if ($result && $recipientId > 0 && $mirrorEmail) {
            // Include mailer if not already loaded
            if (!function_exists('hms_send_notification_email')) {
                $mailerPath = __DIR__ . '/mailer.php';
                if (file_exists($mailerPath)) {
                    require_once $mailerPath;
                }
            }

            if (function_exists('hms_send_notification_email')) {
                $email = null;
                $name  = null;

                try {
                    if ($recipientType === 'patient') {
                        $es = $conn->prepare("SELECT email, fullName FROM users WHERE uid = ? AND email IS NOT NULL AND email != '' LIMIT 1");
                        $es->bind_param("i", $recipientId);
                        $es->execute();
                        $er = $es->get_result()->fetch_assoc();
                        $es->close();
                        if ($er) { $email = $er['email']; $name = $er['fullName'] ?: 'Patient'; }

                    } elseif ($recipientType === 'doctor') {
                        $es = $conn->prepare("SELECT docEmail, doctorName FROM doctors WHERE id = ? AND docEmail IS NOT NULL AND docEmail != '' LIMIT 1");
                        $es->bind_param("i", $recipientId);
                        $es->execute();
                        $er = $es->get_result()->fetch_assoc();
                        $es->close();
                        if ($er) { $email = $er['docEmail']; $name = $er['doctorName'] ?: 'Doctor'; }

                    } elseif (in_array($recipientType, ['employee', 'admin'], true)) {
                        $es = $conn->prepare("SELECT email, username FROM employ WHERE id = ? AND email IS NOT NULL AND email != '' LIMIT 1");
                        $es->bind_param("i", $recipientId);
                        $es->execute();
                        $er = $es->get_result()->fetch_assoc();
                        $es->close();
                        if ($er) { $email = $er['email']; $name = $er['username'] ?: 'Staff'; }
                    }

                    if ($email) {
                        hms_send_notification_email($email, $name, $title, $message, $type);
                    }
                } catch (\Throwable $e) {
                    error_log("HMS notification email lookup error: " . $e->getMessage());
                }
            }
        }

        return $result;
    }
}

if (!function_exists('hms_get_unread_count')) {
    /**
     * Get unread notification count for a user
     */
    function hms_get_unread_count(mysqli $conn, string $recipientType, int $recipientId): int
    {
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE recipient_type = ? AND recipient_id = ? AND is_read = 0");
        $stmt->bind_param("si", $recipientType, $recipientId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($result['cnt'] ?? 0);
    }
}

if (!function_exists('hms_get_notifications')) {
    /**
     * Get notifications for a user
     */
    function hms_get_notifications(mysqli $conn, string $recipientType, int $recipientId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $conn->prepare("
            SELECT n.*, d.doctorName 
            FROM notifications n
            LEFT JOIN doctors d ON d.id = n.related_doctor_id
            WHERE n.recipient_type = ? AND n.recipient_id = ?
            ORDER BY n.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param("siii", $recipientType, $recipientId, $limit, $offset);
        $stmt->execute();
        $res = $stmt->get_result();
        $notifications = [];
        while ($row = $res->fetch_assoc()) {
            $notifications[] = $row;
        }
        $stmt->close();
        return $notifications;
    }
}

if (!function_exists('hms_mark_notification_read')) {
    /**
     * Mark a notification as read
     */
    function hms_mark_notification_read(mysqli $conn, int $notificationId, string $recipientType, int $recipientId): bool
    {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND recipient_type = ? AND recipient_id = ?");
        $stmt->bind_param("isi", $notificationId, $recipientType, $recipientId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
}

if (!function_exists('hms_mark_all_read')) {
    /**
     * Mark all notifications as read
     */
    function hms_mark_all_read(mysqli $conn, string $recipientType, int $recipientId): bool
    {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE recipient_type = ? AND recipient_id = ? AND is_read = 0");
        $stmt->bind_param("si", $recipientType, $recipientId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
}

if (!function_exists('hms_get_notification_recipient')) {
    /**
     * Get the notification recipient type and id based on session
     */
    function hms_get_notification_recipient(): array
    {
        $role = $_SESSION['role'] ?? '';
        switch ($role) {
            case 'Patient':
                return ['type' => 'patient', 'id' => (int)($_SESSION['uid'] ?? 0)];
            case 'Doctor':
                return ['type' => 'doctor', 'id' => (int)($_SESSION['id'] ?? 0)];
            case 'User':
                return ['type' => 'employee', 'id' => (int)($_SESSION['id'] ?? 0)];
            case 'Admin':
            case 'System Admin':
                return ['type' => 'admin', 'id' => (int)($_SESSION['id'] ?? 0)];
            default:
                return ['type' => 'employee', 'id' => 0];
        }
    }
}

if (!function_exists('hms_notify_affected_patients')) {
    /**
     * When a doctor changes their schedule, notify affected patients with future appointments
     */
    function hms_notify_affected_patients(mysqli $conn, int $doctorId, string $changeDescription): void
    {
        // Get doctor name
        $docStmt = $conn->prepare("SELECT doctorName FROM doctors WHERE id = ?");
        $docStmt->bind_param("i", $doctorId);
        $docStmt->execute();
        $docRow = $docStmt->get_result()->fetch_assoc();
        $docStmt->close();
        $doctorName = $docRow['doctorName'] ?? 'Doctor';

        // Find all future appointments for this doctor
        $stmt = $conn->prepare("
            SELECT DISTINCT a.userId, a.patient_email, a.appointmentDate, a.appointmentTime,
                   u.email as user_email, u.fullName
            FROM appointment a
            LEFT JOIN users u ON u.uid = a.userId
            WHERE a.doctorId = ? 
              AND a.appointmentDate >= CURDATE()
              AND a.userStatus = 1
              AND a.doctorStatus = 1
        ");
        $stmt->bind_param("i", $doctorId);
        $stmt->execute();
        $res = $stmt->get_result();

        $notifiedUsers = [];
        while ($row = $res->fetch_assoc()) {
            $uid = (int)$row['userId'];
            if ($uid > 0 && !in_array($uid, $notifiedUsers)) {
                // Create notification for registered patient
                hms_create_notification($conn, [
                    'recipient_type' => 'patient',
                    'recipient_id' => $uid,
                    'title' => 'Schedule Update — ' . $doctorName,
                    'message' => $doctorName . ' has updated their schedule. ' . $changeDescription . ' Your appointment on ' . $row['appointmentDate'] . ' at ' . $row['appointmentTime'] . ' may be affected. Please check availability.',
                    'type' => 'schedule_change',
                    'related_doctor_id' => $doctorId,
                ]);
                $notifiedUsers[] = $uid;
            }
        }
        $stmt->close();

        // Notify all reception employees (User role)
        $empStmt = $conn->prepare("SELECT id, username FROM employ WHERE role = 'User'");
        $empStmt->execute();
        $empRes = $empStmt->get_result();
        while ($emp = $empRes->fetch_assoc()) {
            hms_create_notification($conn, [
                'recipient_type' => 'employee',
                'recipient_id' => (int)$emp['id'],
                'title' => 'Doctor Schedule Changed — ' . $doctorName,
                'message' => $doctorName . ' has updated their schedule. ' . $changeDescription,
                'type' => 'schedule_change',
                'related_doctor_id' => $doctorId,
                'mirror_email' => false,
            ]);
        }
        $empStmt->close();

        // Notify admins
        $adminStmt = $conn->prepare("SELECT id, username FROM employ WHERE role IN ('Admin', 'System Admin')");
        $adminStmt->execute();
        $adminRes = $adminStmt->get_result();
        while ($adm = $adminRes->fetch_assoc()) {
            hms_create_notification($conn, [
                'recipient_type' => 'admin',
                'recipient_id' => (int)$adm['id'],
                'title' => 'Doctor Schedule Changed — ' . $doctorName,
                'message' => $doctorName . ' has updated their schedule. ' . $changeDescription,
                'type' => 'schedule_change',
                'related_doctor_id' => $doctorId,
                'mirror_email' => false,
            ]);
        }
        $adminStmt->close();
    }
}

if (!function_exists('hms_notify_reception_new_booking')) {
    /**
     * Notify reception when a patient books (especially unregistered ones)
     */
    function hms_notify_reception_new_booking(mysqli $conn, array $bookingData): void
    {
        // Per user request: staff should only be notified of cancellations and schedule changes.
        // Skip new booking notifications to save processing time.
        return;
        
        error_log("HMS: hms_notify_reception_new_booking called for Admin/Employee notification. Patient: " . ($bookingData['patient_name'] ?? 'Unknown'));
        $patientName = $bookingData['patient_name'] ?? 'Patient';
        $doctorName  = $bookingData['doctor_name'] ?? 'Doctor';
        $date        = $bookingData['date'] ?? '';
        $time        = $bookingData['time'] ?? '';
        $isRegistered = $bookingData['is_registered'] ?? false;

        $title = $isRegistered
            ? 'New Appointment — ' . $patientName
            : '⚠ New Appointment (Unregistered Patient) — ' . $patientName;

        $message = $patientName . ' booked an appointment with ' . $doctorName . ' on ' . $date . ' at ' . $time . '.';
        if (!$isRegistered) {
            $message .= ' This patient does not have an account on the system.';
        }

        // Notify all reception employees (User role)
        $empStmt = $conn->prepare("SELECT id FROM employ WHERE role = 'User'");
        $empStmt->execute();
        $empRes = $empStmt->get_result();
        while ($emp = $empRes->fetch_assoc()) {
            hms_create_notification($conn, [
                'recipient_type' => 'employee',
                'recipient_id'  => (int)$emp['id'],
                'title'         => $title,
                'message'       => $message,
                'type'          => 'appointment',
                'related_doctor_id' => $bookingData['doctor_id'] ?? null,
                'related_appointment_id' => $bookingData['appointment_id'] ?? null,
                'mirror_email' => false,
            ]);
        }
        $empStmt->close();

        // Notify Admins and System Admins
        $adminStmt = $conn->prepare("SELECT id FROM employ WHERE role IN ('Admin', 'System Admin')");
        $adminStmt->execute();
        $adminRes = $adminStmt->get_result();
        while ($admin = $adminRes->fetch_assoc()) {
            hms_create_notification($conn, [
                'recipient_type' => 'admin',
                'recipient_id'  => (int)$admin['id'],
                'title'         => $title,
                'message'       => $message,
                'type'          => 'appointment',
                'related_doctor_id' => $bookingData['doctor_id'] ?? null,
                'related_appointment_id' => $bookingData['appointment_id'] ?? null,
                'mirror_email' => false,
            ]);
        }
        $adminStmt->close();
    }
}
