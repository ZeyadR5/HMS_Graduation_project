<?php
/**
 * Patient Deposit Payment Page
 * ============================
 * After booking, patients are redirected here to pay the 30% deposit.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/payment-config.php';
require_once __DIR__ . '/../../includes/payment-gateway.php';
require_once __DIR__ . '/../../includes/secure-token.php';

ini_set("display_errors", 0);

$conn = hms_db_connect();

// Use encrypted ID only
if (isset($_GET['ref'])) {
    $ref = $_GET['ref'];
} elseif (isset($_GET['apid']) && is_numeric($_GET['apid'])) {
    $ref = hms_encrypt_id(intval($_GET['apid']));
    header("Location: pay-deposit.php?ref=" . urlencode($ref));
    exit();
} else {
    $ref = '';
}
$apid = hms_decrypt_id($ref) ?: 0;

if ($apid === 0) {
    echo "<script>alert('Invalid appointment.'); window.location.href='/modules/patient/New-reservation.php';</script>";
    exit;
}

// Fetch appointment details
$stmt = $conn->prepare("SELECT a.*, d.doctorName, d.docFees 
    FROM appointment a 
    JOIN doctors d ON d.id = a.doctorId 
    WHERE a.apid = ? AND a.userId = ?");
$uid = (int)$_SESSION['uid'];
$stmt->bind_param("ii", $apid, $uid);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$appointment) {
    echo "<script>alert('Appointment not found.'); window.location.href='/modules/patient/New-reservation.php';</script>";
    exit;
}

$fees = (int)$appointment['consultancyFees'];
$depositAmount = hms_calculate_deposit($fees);
$depositStatus = $appointment['deposit_status'] ?? 'none';

// Already paid
if ($depositStatus === 'paid') {
    echo "<script>alert('Deposit already paid!'); window.location.href='/modules/patient/calender.php';</script>";
    exit;
}

// Process channel selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_channel'])) {
    hms_require_csrf('/modules/patient/pay-deposit.php');

    $channel = $_POST['pay_channel'];
    if (!in_array($channel, ['fawry', 'ewallet', 'instapay'])) {
        echo "<script>alert('Invalid payment channel.');</script>";
    } else {
        // Mark as pending
        $stmt = $conn->prepare("UPDATE appointment SET deposit_amount = ?, deposit_status = 'pending', deposit_channel = ? WHERE apid = ?");
        $stmt->bind_param("isi", $depositAmount, $channel, $apid);
        $stmt->execute();
        $stmt->close();

        // Create payment
        $payResult = HmsPaymentGateway::createPayment([
            'appointment_id' => $apid,
            'amount' => $depositAmount,
            'channel' => $channel,
            'patient_name' => $appointment['patient_Name'],
            'patient_email' => $appointment['patient_email'] ?? '',
            'patient_phone' => (string)($appointment['patient_Num'] ?? ''),
        ]);

        if ($payResult['success'] && !empty($payResult['redirect_url'])) {
            header('Location: ' . $payResult['redirect_url']);
            exit;
        } else {
            // Revert pending status
            $stmt = $conn->prepare("UPDATE appointment SET deposit_status = 'none' WHERE apid = ?");
            $stmt->bind_param("i", $apid);
            $stmt->execute();
            $stmt->close();
            echo "<script>alert('Payment initiation failed. Please try again.');</script>";
        }
    }
}

// Mark deposit for InstaPay manual confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['instapay_confirm'])) {
    hms_require_csrf('/modules/patient/pay-deposit.php');
    $txnRef = trim($_POST['instapay_ref'] ?? '');
    if ($txnRef === '') {
        echo "<script>alert('Please enter your InstaPay transfer reference.');</script>";
    } else {
        $stmt = $conn->prepare("UPDATE appointment SET deposit_amount = ?, deposit_status = 'pending', deposit_channel = 'instapay', deposit_transaction_id = ? WHERE apid = ?");
        $stmt->bind_param("isi", $depositAmount, $txnRef, $apid);
        $stmt->execute();
        $stmt->close();
        echo "<script>alert('Thank you! Your payment is pending verification. The hospital will confirm shortly.'); window.location.href='/modules/patient/calender.php';</script>";
        exit;
    }
}

// Skip deposit (pay at hospital)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['skip_deposit'])) {
    hms_require_csrf('/modules/patient/pay-deposit.php');
    echo "<script>alert('You can pay at the hospital reception. Your booking is saved.'); window.location.href='/modules/patient/calender.php';</script>";
    exit;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Booking — Pay Deposit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>
    <link rel="icon" href="/assets/images/echol.png">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <style>
        .deposit-page { max-width: 640px; margin: 0 auto; padding: 2rem 1rem; }
        .booking-summary {
            background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%);
            border: 1px solid #bfdbfe;
            border-radius: 20px;
            padding: 1.8rem;
            margin-bottom: 1.5rem;
        }
        .booking-summary h2 { font-size: 1.3rem; font-weight: 700; color: #0f172a; margin-bottom: 1rem; }
        .summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; }
        .summary-item { font-size: 0.88rem; }
        .summary-item .label { color: #64748b; font-weight: 500; }
        .summary-item .value { color: #0f172a; font-weight: 700; margin-top: 2px; }

        .deposit-box {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            border-radius: 20px;
            padding: 1.5rem;
            text-align: center;
            color: white;
            margin-bottom: 1.5rem;
        }
        .deposit-box .deposit-label { font-size: 0.85rem; opacity: 0.85; }
        .deposit-box .deposit-amount { font-size: 2.2rem; font-weight: 800; margin: 0.3rem 0; }
        .deposit-box .deposit-note { font-size: 0.78rem; opacity: 0.7; }

        .payment-methods { margin-bottom: 1.5rem; }
        .payment-methods h3 { font-size: 1rem; font-weight: 700; color: #0f172a; margin-bottom: 1rem; }

        .channel-card {
            display: flex; align-items: center; gap: 1rem;
            background: white; border: 2px solid #e2e8f0;
            border-radius: 16px; padding: 1rem 1.2rem;
            margin-bottom: 0.75rem; cursor: pointer;
            transition: all 0.25s ease;
        }
        .channel-card:hover { border-color: #6366f1; transform: translateY(-2px); box-shadow: 0 8px 25px rgba(99,102,241,0.15); }
        .channel-icon {
            width: 52px; height: 52px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; flex-shrink: 0;
        }
        .channel-icon.fawry { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #b45309; }
        .channel-icon.ewallet { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #16a34a; }
        .channel-icon.instapay { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #2563eb; }

        .channel-info { flex: 1; }
        .channel-name { font-weight: 700; font-size: 0.95rem; color: #0f172a; }
        .channel-desc { font-size: 0.78rem; color: #64748b; margin-top: 2px; }
        .channel-arrow { color: #94a3b8; font-size: 1.2rem; }

        .skip-section {
            text-align: center; padding-top: 1rem;
            border-top: 1px solid #f1f5f9;
        }
        .skip-btn {
            background: transparent; border: 2px solid #e2e8f0;
            color: #64748b; padding: 10px 28px; border-radius: 12px;
            font-size: 0.88rem; font-weight: 600; cursor: pointer;
            transition: all 0.2s;
        }
        .skip-btn:hover { border-color: #94a3b8; color: #475569; }

        /* InstaPay Modal */
        .instapay-modal { display: none; }
        .instapay-modal.active { display: block; }
        .instapay-modal-bg { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; align-items: center; justify-content: center; }
        .instapay-modal-content {
            background: white; border-radius: 20px; padding: 2rem;
            max-width: 420px; width: 90%; max-height: 90vh; overflow-y: auto;
        }
        .ipa-address {
            background: #eff6ff; border: 2px dashed #93c5fd;
            border-radius: 12px; padding: 1rem; text-align: center;
            font-family: monospace; font-size: 1.1rem; font-weight: 700;
            color: #1e40af; margin: 1rem 0; cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="min-h-full">
        <?php $activePage = 'new-reservation'; require_once __DIR__ . '/../../includes/nav.php'; ?>

        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">
                    <i class="bi bi-shield-check me-2 text-indigo-600"></i>Confirm Your Booking
                </h1>
            </div>
        </header>

        <main>
            <div class="deposit-page">
                <!-- Booking Summary -->
                <div class="booking-summary">
                    <h2><i class="bi bi-calendar-check me-2"></i>Booking Summary</h2>
                    <div class="summary-grid">
                        <div class="summary-item">
                            <div class="label">Doctor</div>
                            <div class="value"><?= htmlspecialchars($appointment['doctorName'] ?? '') ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="label">Specialization</div>
                            <div class="value"><?= htmlspecialchars($appointment['doctorSpecialization'] ?? '') ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="label">Date</div>
                            <div class="value"><?= htmlspecialchars($appointment['appointmentDate'] ?? '') ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="label">Time</div>
                            <div class="value"><?= htmlspecialchars($appointment['appointmentTime'] ?? '') ?></div>
                        </div>
                        <div class="summary-item" style="grid-column: span 2;">
                            <div class="label">Total Consultation Fee</div>
                            <div class="value" style="font-size: 1.2rem; color: #059669;"><?= number_format($fees) ?> <?= HMS_CURRENCY ?></div>
                        </div>
                    </div>
                </div>

                <!-- Deposit Amount -->
                <div class="deposit-box">
                    <div class="deposit-label">Booking Deposit (30%)</div>
                    <div class="deposit-amount"><?= number_format($depositAmount) ?> <?= HMS_CURRENCY ?></div>
                    <div class="deposit-note">Pay now to confirm your appointment. Remaining <?= number_format($fees - $depositAmount) ?> <?= HMS_CURRENCY ?> at the hospital.</div>
                </div>

                <!-- Payment Methods -->
                <div class="payment-methods">
                    <h3><i class="bi bi-credit-card me-2"></i>Choose Payment Method</h3>

                    <?php if (hms_is_channel_enabled('fawry')): ?>
                    <form method="POST" style="display: inline;">
                        <?= hms_csrf_field() ?>
                        <input type="hidden" name="pay_channel" value="fawry">
                        <button type="submit" class="channel-card" style="width:100; border: none; text-align: left;">
                            <div class="channel-icon fawry"><i class="bi bi-upc-scan"></i></div>
                            <div class="channel-info">
                                <div class="channel-name">Fawry</div>
                                <div class="channel-desc">Pay at any Fawry outlet or via Fawry app</div>
                            </div>
                            <i class="bi bi-chevron-right channel-arrow"></i>
                        </button>
                    </form>
                    <?php endif; ?>

                    <?php if (hms_is_channel_enabled('ewallet')): ?>
                    <form method="POST" style="display: inline;">
                        <?= hms_csrf_field() ?>
                        <input type="hidden" name="pay_channel" value="ewallet">
                        <button type="submit" class="channel-card" style="width:100; border: none; text-align: left;">
                            <div class="channel-icon ewallet"><i class="bi bi-phone"></i></div>
                            <div class="channel-info">
                                <div class="channel-name">E-Wallet</div>
                                <div class="channel-desc">Vodafone Cash, Orange Money, Etisalat Cash, WE Pay</div>
                            </div>
                            <i class="bi bi-chevron-right channel-arrow"></i>
                        </button>
                    </form>
                    <?php endif; ?>

                    <?php if (hms_is_channel_enabled('instapay')): ?>
                    <div class="channel-card" onclick="document.getElementById('instapayModal').classList.add('active')">
                        <div class="channel-icon instapay"><i class="bi bi-bank"></i></div>
                        <div class="channel-info">
                            <div class="channel-name">InstaPay</div>
                            <div class="channel-desc">Transfer from your bank app instantly</div>
                        </div>
                        <i class="bi bi-chevron-right channel-arrow"></i>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Skip Deposit -->
                <div class="skip-section">
                    <p style="font-size: 0.82rem; color: #94a3b8; margin-bottom: 0.75rem;">
                        <i class="bi bi-info-circle me-1"></i>Prefer to pay at the hospital?
                    </p>
                    <form method="POST" style="display: inline;">
                        <?= hms_csrf_field() ?>
                        <input type="hidden" name="skip_deposit" value="1">
                        <button type="submit" class="skip-btn">
                            <i class="bi bi-arrow-right me-1"></i>Skip — Pay at Reception
                        </button>
                    </form>
                </div>
            </div>
        </main>

        <!-- InstaPay Modal -->
        <div class="instapay-modal" id="instapayModal">
            <div class="instapay-modal-bg" onclick="if(event.target===this)this.parentElement.classList.remove('active')">
                <div class="instapay-modal-content">
                    <h3 style="font-weight: 700; margin-bottom: 1rem;"><i class="bi bi-bank me-2 text-blue-600"></i>InstaPay Transfer</h3>
                    <p style="font-size: 0.88rem; color: #64748b; margin-bottom: 1rem;">
                        Transfer <strong><?= number_format($depositAmount) ?> <?= HMS_CURRENCY ?></strong> to the following InstaPay address:
                    </p>
                    <div class="ipa-address" onclick="navigator.clipboard.writeText('<?= htmlspecialchars(INSTAPAY_IPA_ADDRESS ?: 'hospital@instapay') ?>'); this.innerHTML='<i class=\'bi bi-check-circle me-1\'></i>Copied!';">
                        <i class="bi bi-clipboard me-1"></i>
                        <?= htmlspecialchars(INSTAPAY_IPA_ADDRESS ?: 'hospital@instapay') ?>
                    </div>
                    <p style="font-size: 0.82rem; color: #94a3b8; text-align: center;">Click to copy address</p>

                    <div style="background: #fefce8; border-radius: 12px; padding: 0.8rem; margin: 1rem 0; font-size: 0.82rem; color: #a16207;">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        After transferring, enter your transfer reference below. Your booking will be confirmed once verified.
                    </div>

                    <form method="POST">
                        <?= hms_csrf_field() ?>
                        <input type="hidden" name="instapay_confirm" value="1">
                        <div style="margin-bottom: 1rem;">
                            <label style="font-size: 0.85rem; font-weight: 600; color: #334155; display: block; margin-bottom: 0.3rem;">Transfer Reference / Transaction ID</label>
                            <input type="text" name="instapay_ref" required placeholder="e.g. IPA2024XXXXXXX"
                                   style="width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem;">
                        </div>
                        <button type="submit" style="width: 100%; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; border: none; padding: 12px; border-radius: 12px; font-weight: 700; font-size: 0.95rem; cursor: pointer;">
                            <i class="bi bi-check2-circle me-1"></i>I've Transferred — Confirm
                        </button>
                    </form>

                    <button onclick="this.closest('.instapay-modal').classList.remove('active')"
                            style="width: 100%; background: transparent; border: 1px solid #e2e8f0; padding: 10px; border-radius: 12px; margin-top: 0.5rem; color: #64748b; cursor: pointer;">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    <script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>
