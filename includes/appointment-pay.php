<?php
/**
 * Employee Payment Settlement Page
 * =================================
 * Allows employees (User/Admin) to collect remaining payment from patients.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/payment-config.php';
require_once __DIR__ . '/secure-token.php';

ini_set("display_errors", 0);

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['User', 'Admin', 'System Admin'], true)) {
    echo "<script>alert('Access denied.'); window.history.back();</script>";
    exit;
}

$conn = hms_db_connect();
$ref = $_GET['ref'] ?? '';
$apid = hms_decrypt_id($ref) ?: 0;
$returnUrl = $_GET['return'] ?? '/modules/user/Reservations.php';

if ($apid === 0) {
    echo "<script>alert('Invalid appointment.'); window.history.back();</script>";
    exit;
}

// Fetch appointment
$stmt = $conn->prepare("SELECT a.*, d.doctorName, d.docFees FROM appointment a JOIN doctors d ON d.id = a.doctorId WHERE a.apid = ?");
$stmt->bind_param("i", $apid);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$appointment) {
    echo "<script>alert('Appointment not found.'); window.history.back();</script>";
    exit;
}

$fees = (int)$appointment['consultancyFees'];
$depositPaid = (int)($appointment['deposit_amount'] ?? 0);
$depositStatus = $appointment['deposit_status'] ?? 'none';
$previouslyPaid = (int)($appointment['paid'] ?? 0);
$previousDiscount = (int)($appointment['discount'] ?? 0);
$remaining = hms_calculate_remaining($fees, ($depositStatus === 'paid' ? $depositPaid : 0), $previouslyPaid, $previousDiscount);

// Process payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settle'])) {
    hms_require_csrf('/includes/appointment-pay.php');

    $payAmount = (int)($_POST['pay_amount'] ?? 0);
    $discount = (int)($_POST['discount'] ?? 0);
    $discountReason = trim($_POST['discount_reason'] ?? '');
    $method = trim($_POST['method'] ?? 'Cash');
    $employId = (int)($_SESSION['id'] ?? 0);
    $employName = $_SESSION['username'] ?? '';

    if ($payAmount < 0 || $discount < 0) {
        echo "<script>alert('Please enter valid non-negative amounts.');</script>";
    } elseif ($payAmount === 0 && $discount === 0) {
        echo "<script>alert('Please enter either a payment amount or a discount.');</script>";
    } elseif (($payAmount + $discount) > $remaining) {
        echo "<script>alert('Total (Payment + Discount) cannot exceed the remaining balance.');</script>";
    } else {
        $totalPaid = $previouslyPaid + $payAmount;
        $totalDiscount = $previousDiscount + $discount;

        $stmt = $conn->prepare("UPDATE appointment SET paid = ?, discount = ?, discount_reason = ?, method = ?, employId = ?, employname = ?, employStatues = 1 WHERE apid = ?");
        $stmt->bind_param("iisssii", $totalPaid, $totalDiscount, $discountReason, $method, $employId, $employName, $apid);
        $stmt->execute();
        $stmt->close();

        // If deposit was pending (instapay manual), confirm it
        if ($depositStatus === 'pending') {
            $stmt2 = $conn->prepare("UPDATE appointment SET deposit_status = 'paid', deposit_paid_at = NOW() WHERE apid = ?");
            $stmt2->bind_param("i", $apid);
            $stmt2->execute();
            $stmt2->close();
        }

        $conn->close();
        echo "<script>alert('Payment recorded successfully! Amount: {$payAmount} " . HMS_CURRENCY . "'); window.location.href='" . htmlspecialchars($returnUrl) . "';</script>";
        exit;
    }
}

$conn->close();

$depositBadgeClass = match ($depositStatus) {
    'paid' => 'bg-green-100 text-green-700',
    'pending' => 'bg-yellow-100 text-yellow-700',
    'failed' => 'bg-red-100 text-red-700',
    default => 'bg-gray-100 text-gray-500',
};
$depositBadgeText = match ($depositStatus) {
    'paid' => '✅ Deposit Paid',
    'pending' => '⏳ Pending Verification',
    'failed' => '❌ Failed',
    default => 'No Deposit',
};
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settle Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" href="/assets/images/echol.png">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <style>
        .settle-page { max-width: 580px; margin: 0 auto; padding: 2rem 1rem; }
        .patient-card {
            background: white; border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden; margin-bottom: 1.5rem;
        }
        .patient-header {
            background: linear-gradient(135deg, #1e293b, #334155);
            padding: 1.5rem; color: white;
        }
        .patient-header h2 { font-size: 1.2rem; font-weight: 700; margin: 0; }
        .patient-header .apid { font-size: 0.8rem; opacity: 0.6; }
        .patient-body { padding: 1.5rem; }
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
        .detail-item .label { font-size: 0.78rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }
        .detail-item .value { font-size: 0.95rem; font-weight: 700; color: #0f172a; margin-top: 2px; }

        .financial-breakdown {
            background: #f8fafc; border-radius: 14px;
            padding: 1.2rem; margin-bottom: 1.5rem;
        }
        .fin-row { display: flex; justify-content: space-between; padding: 0.5rem 0; font-size: 0.9rem; }
        .fin-row.total { border-top: 2px solid #e2e8f0; padding-top: 0.8rem; margin-top: 0.3rem; font-weight: 800; font-size: 1.1rem; }
        .fin-row .fin-label { color: #64748b; }
        .fin-row .fin-value { font-weight: 600; color: #0f172a; }
        .fin-row .fin-value.green { color: #059669; }
        .fin-row .fin-value.red { color: #dc2626; }

        .settle-form { background: white; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 1.5rem; }
        .settle-form h3 { font-size: 1.05rem; font-weight: 700; margin-bottom: 1rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { font-size: 0.85rem; font-weight: 600; color: #334155; display: block; margin-bottom: 0.3rem; }
        .form-input {
            width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0;
            border-radius: 12px; font-size: 1rem; font-weight: 600;
            transition: border-color 0.2s;
        }
        .form-input:focus { outline: none; border-color: #6366f1; }
        .btn-settle {
            width: 100%; background: linear-gradient(135deg, #10b981, #059669);
            color: white; border: none; padding: 14px; border-radius: 14px;
            font-size: 1.05rem; font-weight: 700; cursor: pointer;
            transition: all 0.2s;
        }
        .btn-settle:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(16,185,129,0.4); }
        .btn-back {
            display: inline-block; margin-top: 1rem; color: #64748b;
            text-decoration: none; font-size: 0.88rem;
        }
        .btn-back:hover { color: #334155; }

        .quick-amount { display: flex; gap: 0.5rem; margin-top: 0.5rem; flex-wrap: wrap; }
        .quick-btn {
            background: #eff6ff; color: #3b82f6; border: 1px solid #bfdbfe;
            padding: 6px 14px; border-radius: 8px; font-size: 0.82rem;
            font-weight: 600; cursor: pointer; transition: all 0.15s;
        }
        .quick-btn:hover { background: #3b82f6; color: white; }
    </style>
</head>
<body>
    <div class="min-h-full">
        <?php $activePage = 'reservations'; require_once __DIR__ . '/nav.php'; ?>

        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">
                    <i class="bi bi-cash-coin me-2 text-green-600"></i>Settle Payment
                </h1>
            </div>
        </header>

        <main>
            <div class="settle-page">
                <!-- Patient Info -->
                <div class="patient-card">
                    <div class="patient-header">
                        <span class="apid">#<?= $apid ?></span>
                        <h2><i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($appointment['patient_Name']) ?></h2>
                    </div>
                    <div class="patient-body">
                        <div class="detail-grid">
                            <div class="detail-item">
                                <div class="label">Doctor</div>
                                <div class="value"><?= htmlspecialchars($appointment['doctorName']) ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="label">Specialization</div>
                                <div class="value"><?= htmlspecialchars($appointment['doctorSpecialization']) ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="label">Date</div>
                                <div class="value"><?= htmlspecialchars($appointment['appointmentDate']) ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="label">Deposit Status</div>
                                <div class="value">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium <?= $depositBadgeClass ?>">
                                        <?= $depositBadgeText ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Financial Breakdown -->
                <div class="financial-breakdown">
                    <div class="fin-row">
                        <span class="fin-label">Consultation Fee</span>
                        <span class="fin-value"><?= number_format($fees) ?> <?= HMS_CURRENCY ?></span>
                    </div>
                    <?php if ($depositStatus === 'paid' && $depositPaid > 0): ?>
                    <div class="fin-row">
                        <span class="fin-label">
                            <i class="bi bi-check-circle text-green-500 me-1"></i>Online Deposit (30%)
                            <?php if ($appointment['deposit_channel']): ?>
                                <span class="text-xs text-gray-400">(<?= hms_payment_channel_label($appointment['deposit_channel']) ?>)</span>
                            <?php endif; ?>
                        </span>
                        <span class="fin-value green">-<?= number_format($depositPaid) ?> <?= HMS_CURRENCY ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($previouslyPaid > 0): ?>
                    <div class="fin-row">
                        <span class="fin-label"><i class="bi bi-check-circle text-green-500 me-1"></i>Previously Paid</span>
                        <span class="fin-value green">-<?= number_format($previouslyPaid) ?> <?= HMS_CURRENCY ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($previousDiscount > 0): ?>
                    <div class="fin-row">
                        <span class="fin-label"><i class="bi bi-tag-fill text-blue-500 me-1"></i>Previous Discount</span>
                        <span class="fin-value text-blue-600">-<?= number_format($previousDiscount) ?> <?= HMS_CURRENCY ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="fin-row total">
                        <span class="fin-label">Remaining Balance</span>
                        <span id="displayRemaining" class="fin-value <?= $remaining > 0 ? 'red' : 'green' ?>"><?= number_format($remaining) ?> <?= HMS_CURRENCY ?></span>
                    </div>
                </div>

                <!-- Settlement Form -->
                <?php if ($remaining > 0): ?>
                <div class="settle-form">
                    <h3><i class="bi bi-receipt me-2"></i>Record Payment</h3>
                    <form method="POST">
                        <?= hms_csrf_field() ?>
                        <input type="hidden" name="settle" value="1">

                        <div class="form-group">
                            <label for="payAmount">Amount to Collect</label>
                            <input type="number" name="pay_amount" id="payAmount" class="form-input"
                                   value="<?= $remaining ?>" min="1" max="<?= $remaining ?>" required>
                            <div class="quick-amount">
                                <span class="quick-btn" onclick="document.getElementById('payAmount').value='<?= $remaining ?>'">Full: <?= number_format($remaining) ?></span>
                                <?php if ($remaining > 100): ?>
                                <span class="quick-btn" onclick="document.getElementById('payAmount').value='<?= intval($remaining/2) ?>'">Half: <?= number_format(intval($remaining/2)) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="method">Payment Method</label>
                            <select name="method" id="method" class="form-input">
                                <option value="Cash">Cash</option>
                                <option value="Visa">Visa</option>
                                <option value="VF Cash">Vodafone Cash</option>
                                <option value="E-Wallet">E-Wallet</option>
                                <option value="InstaPay">InstaPay</option>
                            </select>
                        </div>

                        <div class="border-t border-dashed border-gray-200 pt-4 mt-4">
                            <div class="flex items-center gap-2 mb-2">
                                <i class="bi bi-tag text-blue-500"></i>
                                <span class="text-sm font-bold text-gray-700">Add Discount (Optional)</span>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="form-group mb-0">
                                    <label for="discount" class="text-xs">Discount Amount</label>
                                    <input type="number" name="discount" id="discount" class="form-input text-blue-600 border-blue-100 bg-blue-50/30"
                                           value="0" min="0" max="<?= $remaining ?>">
                                </div>
                                <div class="form-group mb-0">
                                    <label for="discount_reason" class="text-xs">Reason</label>
                                    <input type="text" name="discount_reason" id="discount_reason" class="form-input text-sm font-medium"
                                           placeholder="e.g. Employee relative">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn-settle">
                            <i class="bi bi-check-circle me-2"></i>Record Payment
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <div style="text-align: center; padding: 2rem; background: #f0fdf4; border-radius: 16px; border: 1px solid #bbf7d0;">
                    <i class="bi bi-check-circle-fill text-green-500" style="font-size: 3rem;"></i>
                    <p style="font-weight: 700; font-size: 1.1rem; margin-top: 0.5rem; color: #059669;">Fully Paid</p>
                    <p style="color: #64748b; font-size: 0.88rem;">This appointment has been fully settled.</p>
                </div>
                <?php endif; ?>

                <div style="text-align: center; margin-top: 1rem;">
                    <a href="<?= htmlspecialchars($returnUrl) ?>" class="btn-back">
                        <i class="bi bi-arrow-left me-1"></i>Back to Reservations
                    </a>
                </div>
            </div>
        </main>
    </div>
    <script>
        const initialRemaining = <?= $remaining ?>;
        const payInput = document.getElementById('payAmount');
        const discountInput = document.getElementById('discount');
        const remainingDisplay = document.getElementById('displayRemaining');
        const currency = '<?= HMS_CURRENCY ?>';

        function updateRemaining() {
            const pay = parseInt(payInput.value) || 0;
            const discount = parseInt(discountInput.value) || 0;
            const currentRemaining = initialRemaining - pay - discount;
            
            remainingDisplay.textContent = currentRemaining.toLocaleString() + ' ' + currency;
            
            if (currentRemaining <= 0) {
                remainingDisplay.classList.remove('red');
                remainingDisplay.classList.add('green');
            } else {
                remainingDisplay.classList.remove('green');
                remainingDisplay.classList.add('red');
            }

            // Prevent going over
            if (currentRemaining < 0) {
                if (event && event.target === discountInput) {
                    discountInput.value = initialRemaining - pay;
                } else if (event && event.target === payInput) {
                    payInput.value = initialRemaining - discount;
                }
                updateRemaining();
            }
        }

        if (payInput) payInput.addEventListener('input', updateRemaining);
        if (discountInput) discountInput.addEventListener('input', updateRemaining);
    </script>
    <script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>
