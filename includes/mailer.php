<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Sends an OTP email to the specified address.
 * 
 * @param string $toEmail The recipient's email address.
 * @param string $otpCode The 6-digit OTP code to send.
 * @param string $patientName Optional name of the patient.
 * @return bool True if email was sent successfully, false otherwise.
 */
function hms_send_otp_email(string $toEmail, string $otpCode, string $patientName = 'Patient'): bool
{
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';             // Set the SMTP server to send through
        $mail->SMTPAuth   = true;                         // Enable SMTP authentication
        $mail->Username   = 'it4gamma@gmail.com';        // SMTP username
        $mail->Password   = 'ebxs txsx brdk ttqn';        // SMTP password (app password)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
        $mail->Port       = 587;                          // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above

        // To test without real SMTP, you can use Mailtrap or uncomment the die() below.
        // For now, we will simulate success if credentials are not configured, so development doesn't break.
        if ($mail->Password === 'your_app_password') {
            error_log("HMS OTP (Mock Mode): Sent OTP $otpCode to $toEmail");
            return true;
        }

        // Recipients
        $mail->setFrom('noreply@hms-system.local', 'HMS Support');
        $mail->addAddress($toEmail, $patientName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your HMS Account Password Reset OTP';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; background-color: #f8fbff; padding: 20px;'>
                <div style='max-width: 500px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; border: 1px solid #dbeafe;'>
                    <h2 style='color: #0ea5e9; margin-top: 0;'>HMS Account Recovery</h2>
                    <p>Hello <b>" . htmlspecialchars($patientName) . "</b>,</p>
                    <p>We received a request to reset your password. Here is your One-Time Password (OTP):</p>
                    <div style='background-color: #f0fdf4; border: 1px dashed #22c55e; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 5px; color: #166534; margin: 20px 0;'>
                        {$otpCode}
                    </div>
                    <p>This code will expire in 15 minutes.</p>
                    <p>If you did not request this, please ignore this email.</p>
                    <br>
                    <p style='color: #64748b; font-size: 12px;'>Thank you,<br>HMS Support Team</p>
                </div>
            </div>
        ";
        $mail->AltBody = "Hello $patientName,\n\nYour HMS Account Password Reset OTP is: $otpCode\n\nThis code will expire in 15 minutes.\n\nThank you,\nHMS Support Team";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
