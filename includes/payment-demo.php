<?php
/**
 * Demo Payment Simulator
 * ======================
 * Simulates a payment gateway page for testing.
 * This page is ONLY accessible in HMS_PAYMENT_DEMO_MODE.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/payment-config.php';

$fawryRef = htmlspecialchars($_GET['fawry_ref'] ?? '');
if (!HMS_PAYMENT_DEMO_MODE && empty($fawryRef)) {
    die('Demo mode is disabled.');
}

$apid = (int)($_GET['apid'] ?? 0);
$ref = htmlspecialchars($_GET['ref'] ?? '');
$amount = (int)($_GET['amount'] ?? 0);
$channel = htmlspecialchars($_GET['channel'] ?? 'fawry');

if ($apid === 0 || $amount === 0) {
    die('Invalid payment parameters.');
}

// Process simulated payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    hms_require_csrf('/includes/payment-demo.php');

    $conn = hms_db_connect();
    $action = $_POST['action'];

    if ($action === 'pay') {
        $txnId = 'DEMO_TXN_' . strtoupper(substr(md5(uniqid()), 0, 12));
        $stmt = $conn->prepare("UPDATE appointment SET deposit_status = 'paid', deposit_transaction_id = ?, deposit_channel = ?, deposit_paid_at = NOW() WHERE apid = ? AND deposit_status = 'pending'");
        $stmt->bind_param("ssi", $txnId, $channel, $apid);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo "<script>alert('✅ Payment successful! Transaction: {$txnId}'); window.location.href='/modules/patient/calender.php';</script>";
        } else {
            echo "<script>alert('Payment already processed or appointment not found.'); window.location.href='/modules/patient/calender.php';</script>";
        }
        $stmt->close();
        $conn->close();
        exit;
    } elseif ($action === 'cancel') {
        $stmt = $conn->prepare("UPDATE appointment SET deposit_status = 'none' WHERE apid = ? AND deposit_status = 'pending'");
        $stmt->bind_param("i", $apid);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        echo "<script>alert('Payment cancelled.'); window.location.href='/modules/patient/calender.php';</script>";
        exit;
    }
}

$channelLabels = ['fawry' => 'Fawry', 'ewallet' => 'E-Wallet', 'instapay' => 'InstaPay'];
$channelLabel = $channelLabels[$channel] ?? ucfirst($channel);
$channelIcons = ['fawry' => 'bi-upc-scan', 'ewallet' => 'bi-phone', 'instapay' => 'bi-bank'];
$channelIcon = $channelIcons[$channel] ?? 'bi-credit-card';

$isRealFawry = !empty($fawryRef);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment — Demo Mode</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>
    <link rel="icon" href="/assets/images/echol.png">
    <style>
        body { background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .payment-card { background: rgba(255,255,255,0.97); border-radius: 24px; box-shadow: 0 25px 60px rgba(0,0,0,0.3); max-width: 440px; width: 100%; overflow: hidden; }
        .payment-header { background: linear-gradient(135deg, #4f46e5, #7c3aed); padding: 2rem; text-align: center; color: white; }
        .payment-header .amount { font-size: 2.5rem; font-weight: 800; }
        .payment-header .currency { font-size: 1rem; opacity: 0.8; }
        .demo-badge { background: #fbbf24; color: #92400e; padding: 4px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; display: inline-block; margin-bottom: 0.5rem; }
        .payment-body { padding: 2rem; }
        .info-row { display: flex; justify-content: space-between; padding: 0.6rem 0; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #64748b; }
        .info-value { font-weight: 600; color: #0f172a; }
        .btn-pay { background: linear-gradient(135deg, #10b981, #059669); border: none; color: white; padding: 14px; border-radius: 14px; font-size: 1.1rem; font-weight: 700; width: 100%; cursor: pointer; transition: all 0.2s; }
        .btn-pay:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4); }
        .btn-cancel { background: transparent; border: 2px solid #e2e8f0; color: #64748b; padding: 12px; border-radius: 14px; font-size: 0.95rem; font-weight: 600; width: 100%; cursor: pointer; margin-top: 0.75rem; transition: all 0.2s; }
        .btn-cancel:hover { border-color: #ef4444; color: #ef4444; }
        .channel-badge { background: #eff6ff; color: #3b82f6; padding: 6px 16px; border-radius: 10px; font-size: 0.85rem; font-weight: 600; }
    </style>
</head>
<body>
    <div class="payment-card">
        <div class="payment-header">
            <?php if ($isRealFawry): ?>
                <div class="demo-badge" style="background: #10b981; color: white;">✅ Order Created</div>
            <?php else: ?>
                <div class="demo-badge">⚡ DEMO MODE</div>
            <?php endif; ?>
            <div style="margin-top: 0.5rem;">
                <i class="bi <?= $channelIcon ?>" style="font-size: 2rem; opacity: 0.9;"></i>
            </div>
            <div class="amount"><?= number_format($amount) ?></div>
            <div class="currency"><?= HMS_CURRENCY ?></div>
            <div style="margin-top: 0.5rem; font-size: 0.85rem; opacity: 0.85;">Booking Deposit (30%)</div>
        </div>

        <div class="payment-body">
            <?php if ($isRealFawry): ?>
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <p style="font-size: 0.9rem; color: #64748b; margin-bottom: 0.5rem;">Pay at any Fawry outlet using this reference:</p>
                    <div style="background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 16px; padding: 1.2rem; font-size: 1.8rem; font-weight: 800; color: #0f172a; font-family: monospace;">
                        <?= $fawryRef ?>
                    </div>
                </div>
                <div style="background: #eff6ff; border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; font-size: 0.85rem; color: #1e40af;">
                    <i class="bi bi-info-circle-fill me-1"></i>
                    Your booking is now <strong>Pending</strong>. It will be confirmed automatically once you pay at Fawry.
                </div>
                <a href="/modules/patient/calender.php" class="btn-pay" style="display: block; text-align: center; text-decoration: none;">
                    Done - Go to Calendar
                </a>
            <?php else: ?>
                <div class="info-row">
                    <span class="info-label">Appointment #</span>
                    <span class="info-value"><?= $apid ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment Method</span>
                    <span class="channel-badge"><i class="bi <?= $channelIcon ?> me-1"></i><?= $channelLabel ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Reference</span>
                    <span class="info-value" style="font-family: monospace; font-size: 0.8rem;"><?= $ref ?></span>
                </div>
                <div class="info-row" style="background: #fefce8; margin: 0.5rem -2rem; padding: 0.6rem 2rem; border-bottom: none;">
                    <span class="info-label" style="color: #a16207;"><i class="bi bi-info-circle me-1"></i>Demo Payment</span>
                    <span class="info-value" style="color: #a16207; font-size: 0.8rem;">Click below to simulate</span>
                </div>

                <form method="POST" style="margin-top: 1.5rem;">
                    <?= hms_csrf_field() ?>
                    <input type="hidden" name="action" value="pay">
                    <button type="submit" class="btn-pay">
                        <i class="bi bi-check-circle me-2"></i>Confirm Payment — <?= number_format($amount) ?> <?= HMS_CURRENCY ?>
                    </button>
                </form>
                <form method="POST">
                    <?= hms_csrf_field() ?>
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit" class="btn-cancel">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
