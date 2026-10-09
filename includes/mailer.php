<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/env.php';

// ── Shared SMTP builder ───────────────────────────────────────────────────────
function hms_mailer_build(): ?PHPMailer
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = hms_env('HMS_SMTP_HOST', 'smtp.gmail.com');
        $mail->SMTPAuth   = true;
        $mail->Username   = hms_env('HMS_SMTP_USER', '');
        $mail->Password   = hms_env('HMS_SMTP_PASS', '');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 5; // 5 seconds connection timeout to prevent hanging on blocked SMTP ports
        $mail->setFrom(hms_env('HMS_SMTP_FROM', 'noreply@hms-pro.app'), 'Echo HMS');
        return $mail;
    } catch (Exception $e) {
        error_log("HMS Mailer build error: " . $e->getMessage());
        return null;
    }
}

// ── Shared HTML email wrapper ─────────────────────────────────────────────────
function hms_email_wrap(string $content, string $accentColor = '#6366f1'): string
{
    return "
    <div style='font-family:Arial,sans-serif;background:#f1f5f9;padding:32px 16px;'>
      <div style='max-width:520px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.07);'>
        <div style='background:{$accentColor};padding:24px 28px;text-align:center;'>
          <h1 style='color:#fff;margin:0;font-size:20px;letter-spacing:1px;'>&#127973; Echo HMS</h1>
        </div>
        <div style='padding:28px 32px;'>
          {$content}
        </div>
        <div style='background:#f8fafc;border-top:1px solid #e2e8f0;padding:16px 32px;text-align:center;'>
          <p style='color:#94a3b8;font-size:12px;margin:0;'>This is an automated message — please do not reply.<br>Echo Hospital Management System</p>
        </div>
      </div>
    </div>";
}

// ── OTP / Password Reset ──────────────────────────────────────────────────────
/**
 * Sends an OTP email for password reset.
 */
function hms_send_otp_email(string $toEmail, string $otpCode, string $patientName = 'Patient'): bool
{
    $mail = hms_mailer_build();
    if (!$mail) return false;
    try {
        $mail->addAddress($toEmail, $patientName);
        $mail->isHTML(true);
        $mail->Subject = 'Your HMS Account Password Reset OTP';
        $body = "
            <h2 style='color:#0ea5e9;margin-top:0;'>HMS Account Recovery</h2>
            <p>Hello <b>" . htmlspecialchars($patientName) . "</b>,</p>
            <p>We received a request to reset your password. Here is your One-Time Password (OTP):</p>
            <div style='background:#f0fdf4;border:1px dashed #22c55e;padding:15px;text-align:center;font-size:28px;font-weight:bold;letter-spacing:8px;color:#166534;margin:20px 0;border-radius:8px;'>
                {$otpCode}
            </div>
            <p>This code will expire in <strong>15 minutes</strong>.</p>
            <p style='color:#6b7280;font-size:13px;'>If you did not request this, please ignore this email.</p>
        ";
        $mail->Body    = hms_email_wrap($body, '#0ea5e9');
        $mail->AltBody = "Hello $patientName,\n\nYour HMS Password Reset OTP is: $otpCode\n\nExpires in 15 minutes.\n\nEcho HMS";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("HMS OTP email error: {$mail->ErrorInfo}");
        return false;
    }
}

// ── Appointment: Booked ───────────────────────────────────────────────────────
/**
 * Sends a booking confirmation email to the patient.
 */
function hms_send_appointment_booked_email(
    string $toEmail,
    string $patientName,
    string $doctorName,
    string $date,
    string $time,
    int    $fees
): bool {
    $mail = hms_mailer_build();
    if (!$mail) return false;
    try {
        $mail->addAddress($toEmail, $patientName);
        $mail->isHTML(true);
        $mail->Subject = 'Appointment Confirmed — Echo HMS';
        $body = "
            <h2 style='color:#22c55e;margin-top:0;'>&#10003; Appointment Confirmed!</h2>
            <p>Hello <strong>" . htmlspecialchars($patientName) . "</strong>,</p>
            <p>Your appointment has been successfully booked. Here are the details:</p>
            <table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:14px;'>
              <tr style='background:#f0fdf4;'><td style='padding:10px 14px;font-weight:bold;color:#374151;width:40%;'>Doctor</td><td style='padding:10px 14px;color:#111827;'>" . htmlspecialchars($doctorName) . "</td></tr>
              <tr><td style='padding:10px 14px;font-weight:bold;color:#374151;'>Date</td><td style='padding:10px 14px;color:#111827;'>" . htmlspecialchars($date) . "</td></tr>
              <tr style='background:#f0fdf4;'><td style='padding:10px 14px;font-weight:bold;color:#374151;'>Time</td><td style='padding:10px 14px;color:#111827;'>" . htmlspecialchars($time) . "</td></tr>
              <tr><td style='padding:10px 14px;font-weight:bold;color:#374151;'>Consultation Fee</td><td style='padding:10px 14px;color:#111827;'>{$fees} EGP</td></tr>
            </table>
            <p style='color:#6b7280;font-size:13px;'>Please arrive 10 minutes before your appointment. To cancel or reschedule, do so at least 24 hours in advance.</p>
        ";
        $mail->Body    = hms_email_wrap($body, '#22c55e');
        $mail->AltBody = "Appointment Confirmed!\nDoctor: $doctorName\nDate: $date | Time: $time\nFee: {$fees} EGP";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("HMS booking email error: {$mail->ErrorInfo}");
        return false;
    }
}

// ── Appointment: Cancelled ────────────────────────────────────────────────────
/**
 * Sends a cancellation notification to a patient or doctor.
 */
function hms_send_appointment_cancelled_email(
    string $toEmail,
    string $recipientName,
    string $patientName,
    string $doctorName,
    string $date,
    string $time,
    string $cancelledBy,
    string $reason = ''
): bool {
    $mail = hms_mailer_build();
    if (!$mail) return false;
    try {
        $mail->addAddress($toEmail, $recipientName);
        $mail->isHTML(true);
        $mail->Subject = 'Appointment Cancelled — Echo HMS';
        $reasonHtml = $reason
            ? "<div style='background:#fff7ed;border-left:4px solid #f97316;padding:10px 14px;border-radius:4px;color:#9a3412;margin-top:12px;font-size:13px;'><strong>Reason:</strong> " . htmlspecialchars($reason) . "</div>"
            : '';
        $body = "
            <h2 style='color:#ef4444;margin-top:0;'>&#10007; Appointment Cancelled</h2>
            <p>Hello <strong>" . htmlspecialchars($recipientName) . "</strong>,</p>
            <p>The following appointment has been <strong>cancelled</strong> by <em>" . htmlspecialchars($cancelledBy) . "</em>:</p>
            <table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:14px;'>
              <tr style='background:#fef2f2;'><td style='padding:10px 14px;font-weight:bold;color:#374151;width:40%;'>Patient</td><td style='padding:10px 14px;color:#111827;'>" . htmlspecialchars($patientName) . "</td></tr>
              <tr><td style='padding:10px 14px;font-weight:bold;color:#374151;'>Doctor</td><td style='padding:10px 14px;color:#111827;'>" . htmlspecialchars($doctorName) . "</td></tr>
              <tr style='background:#fef2f2;'><td style='padding:10px 14px;font-weight:bold;color:#374151;'>Date</td><td style='padding:10px 14px;color:#111827;'>" . htmlspecialchars($date) . "</td></tr>
              <tr><td style='padding:10px 14px;font-weight:bold;color:#374151;'>Time</td><td style='padding:10px 14px;color:#111827;'>" . htmlspecialchars($time) . "</td></tr>
            </table>
            {$reasonHtml}
            <p style='color:#6b7280;font-size:13px;margin-top:16px;'>If you believe this is a mistake, please contact reception.</p>
        ";
        $mail->Body    = hms_email_wrap($body, '#ef4444');
        $mail->AltBody = "Appointment Cancelled\nPatient: $patientName | Doctor: $doctorName\nDate: $date | Time: $time\nCancelled by: $cancelledBy" . ($reason ? "\nReason: $reason" : '');
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("HMS cancellation email error: {$mail->ErrorInfo}");
        return false;
    }
}

// ── Appointment: Rescheduled ──────────────────────────────────────────────────
/**
 * Sends a rescheduling notification email to the patient.
 */
function hms_send_appointment_rescheduled_email(
    string $toEmail,
    string $patientName,
    string $doctorName,
    string $oldDate,
    string $oldTime,
    string $newDate,
    string $newTime
): bool {
    $mail = hms_mailer_build();
    if (!$mail) return false;
    try {
        $mail->addAddress($toEmail, $patientName);
        $mail->isHTML(true);
        $mail->Subject = 'Appointment Rescheduled — Echo HMS';
        $body = "
            <h2 style='color:#6366f1;margin-top:0;'>&#128197; Appointment Rescheduled</h2>
            <p>Hello <strong>" . htmlspecialchars($patientName) . "</strong>,</p>
            <p>Your appointment with <strong>" . htmlspecialchars($doctorName) . "</strong> has been updated:</p>
            <table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:14px;'>
              <tr><td style='padding:0 8px 0 0;width:50%;'>
                <div style='background:#fef2f2;border-radius:10px;padding:14px;'>
                  <p style='margin:0 0 6px;font-size:11px;font-weight:bold;color:#ef4444;text-transform:uppercase;letter-spacing:1px;'>Previous</p>
                  <p style='margin:0;color:#374151;font-weight:600;'>" . htmlspecialchars($oldDate) . "</p>
                  <p style='margin:4px 0 0;color:#6b7280;font-size:13px;'>" . htmlspecialchars($oldTime) . "</p>
                </div>
              </td><td style='padding:0 0 0 8px;width:50%;'>
                <div style='background:#f0fdf4;border-radius:10px;padding:14px;'>
                  <p style='margin:0 0 6px;font-size:11px;font-weight:bold;color:#22c55e;text-transform:uppercase;letter-spacing:1px;'>New</p>
                  <p style='margin:0;color:#374151;font-weight:600;'>" . htmlspecialchars($newDate) . "</p>
                  <p style='margin:4px 0 0;color:#6b7280;font-size:13px;'>" . htmlspecialchars($newTime) . "</p>
                </div>
              </td></tr>
            </table>
            <p style='color:#6b7280;font-size:13px;'>If you did not expect this change, please contact reception immediately.</p>
        ";
        $mail->Body    = hms_email_wrap($body, '#6366f1');
        $mail->AltBody = "Appointment Rescheduled\nDoctor: $doctorName\nPrevious: $oldDate $oldTime\nNew: $newDate $newTime";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("HMS reschedule email error: {$mail->ErrorInfo}");
        return false;
    }
}

// ── Generic System Notification Email ─────────────────────────────────────────
/**
 * Sends a generic notification email mirroring an in-app notification.
 * Called automatically from hms_create_notification().
 */
function hms_send_notification_email(
    string $toEmail,
    string $recipientName,
    string $title,
    string $message,
    string $type = 'system'
): bool {
    $mail = hms_mailer_build();
    if (!$mail) return false;

    // Choose accent color by notification type
    $colorMap = [
        'appointment'    => '#6366f1',
        'cancellation'   => '#ef4444',
        'queue'          => '#0ea5e9',
        'schedule_change'=> '#f59e0b',
        'payment'        => '#22c55e',
        'system'         => '#64748b',
    ];
    $accent = $colorMap[$type] ?? '#6366f1';

    // Icon by type
    $iconMap = [
        'appointment'    => '&#128197;',
        'cancellation'   => '&#10007;',
        'queue'          => '&#128203;',
        'schedule_change'=> '&#9888;',
        'payment'        => '&#10003;',
        'system'         => '&#128276;',
    ];
    $icon = $iconMap[$type] ?? '&#128276;';

    try {
        $mail->addAddress($toEmail, $recipientName);
        $mail->isHTML(true);
        $mail->Subject = $title . ' — Echo HMS';
        $body = "
            <h2 style='color:{$accent};margin-top:0;'>{$icon} " . htmlspecialchars($title) . "</h2>
            <p>Hello <strong>" . htmlspecialchars($recipientName) . "</strong>,</p>
            <div style='background:#f8fafc;border-left:4px solid {$accent};border-radius:4px;padding:14px 18px;margin:16px 0;color:#374151;font-size:14px;line-height:1.6;'>
                " . nl2br(htmlspecialchars($message)) . "
            </div>
            <p style='color:#6b7280;font-size:12px;margin-top:20px;'>This email was sent because you have an active account on Echo HMS. You can also view your notifications in the system dashboard.</p>
        ";
        $mail->Body    = hms_email_wrap($body, $accent);
        $mail->AltBody = "$title\n\n$message\n\n-- Echo HMS";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("HMS notification email error: {$mail->ErrorInfo}");
        return false;
    }
}
