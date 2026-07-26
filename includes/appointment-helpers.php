<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/secure-token.php';

/**
 * Shared appointment UI helpers.
 */

function appt_status_badge(array $row): string
{
    $us = (int)($row['userStatus'] ?? -1);
    $ds = (int)($row['doctorStatus'] ?? -1);

    if ($us === 1 && $ds === 1) {
        $bg = "bg-blue-50 text-blue-700 ring-blue-600/20";
        $label = "Active";
    } elseif ($us === 0) {
        $bg = "bg-red-50 text-red-700 ring-red-600/20";
        $label = "Cancelled";
    } elseif ($ds === 0) {
        $bg = "bg-orange-50 text-orange-700 ring-orange-600/20";
        $label = "Cancelled";
    } elseif ($us === 2 && $ds === 2) {
        $bg = "bg-green-50 text-green-700 ring-green-600/20";
        $label = "Completed";
    } else {
        $bg = "bg-gray-50 text-gray-600 ring-gray-400/20";
        $label = "Unknown";
    }

    return "<span class='inline-flex items-center rounded-md px-2 py-1 text-sm font-medium ring-1 ring-inset {$bg}'>{$label}</span>";
}

/**
 * Payment status badge — shows financial status of an appointment.
 */
function appt_payment_badge(array $row): string
{
    $fees = (int)($row['consultancyFees'] ?? 0);
    $paid = (int)($row['paid'] ?? 0);
    $depositStatus = $row['deposit_status'] ?? 'none';
    $depositAmount = (int)($row['deposit_amount'] ?? 0);
    $discount = (int)($row['discount'] ?? 0);

    // Calculate total credit (paid + discount)
    $totalCollected = $paid + $discount;
    if ($depositStatus === 'paid') {
        $totalCollected += $depositAmount;
    }

    if ($fees <= 0) {
        return "<span class='inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-500'>N/A</span>";
    }

    if ($totalCollected >= $fees) {
        // Fully paid
        return "<span class='inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-green-100 text-green-700 ring-1 ring-inset ring-green-600/20'><i class='bi bi-check-circle-fill me-1'></i>Paid</span>";
    }

    if ($depositStatus === 'paid' && $depositAmount > 0) {
        // Deposit paid, remaining at hospital
        $remaining = $fees - $depositAmount - $paid - $discount;
        return "<span class='inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-blue-100 text-blue-700 ring-1 ring-inset ring-blue-600/20' title='Deposit: {$depositAmount} | Discount: {$discount} | Remaining: {$remaining}'><i class='bi bi-cash me-1'></i>Deposit ✓</span>";
    }

    if ($depositStatus === 'pending') {
        return "<span class='inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-yellow-100 text-yellow-700 ring-1 ring-inset ring-yellow-600/20'><i class='bi bi-hourglass-split me-1'></i>Pending</span>";
    }

    if ($paid > 0 && $paid < $fees) {
        // Partial cash payment
        return "<span class='inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-amber-100 text-amber-700 ring-1 ring-inset ring-amber-600/20' title='Paid: {$paid} / {$fees}'><i class='bi bi-exclamation-circle me-1'></i>Partial</span>";
    }

    // Unpaid
    return "<span class='inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-red-100 text-red-700 ring-1 ring-inset ring-red-600/20'><i class='bi bi-x-circle me-1'></i>Unpaid</span>";
}

function appt_cancelled_by_text(array $row): string
{
    $us = (int)($row['userStatus'] ?? -1);
    $ds = (int)($row['doctorStatus'] ?? -1);
    $by = trim((string)($row['cancelledBy'] ?? ''));

    if (($us === 1 && $ds === 1) || ($us === 2 && $ds === 2)) {
        return '-';
    }

    if ($by !== '') {
        return $by;
    }

    if ($us === 0) {
        return 'Patient';
    }

    if ($ds === 0) {
        return 'Doctor';
    }

    return '-';
}

function appt_cancelled_by(array $row): string
{
    $byText = appt_cancelled_by_text($row);

    if ($byText === '-') {
        return '';
    }

    return "<span class='block text-xs text-gray-400 mt-1'>Cancelled by: <span class='font-medium text-red-500'>" . htmlspecialchars($byText) . "</span></span>";
}

function appt_action_buttons(array $row, string $returnUrl): string
{
    $role = $_SESSION['role'] ?? '';
    $apid = (int)($row['apid'] ?? 0);
    $us = (int)($row['userStatus'] ?? -1);
    $ds = (int)($row['doctorStatus'] ?? -1);
    $isActive = ($us === 1 && $ds === 1);

    $base = '/includes/appointment-action.php';
    $payBase = '/includes/appointment-pay.php';
    $ret = urlencode($returnUrl);
    $csrf = hms_csrf_query();
    $html = '<div class="flex gap-1 justify-center flex-wrap">';

    if (!$isActive) {
        if ($us === 0 || $ds === 0) {
            $html .= "<span class='inline-flex items-center rounded-lg bg-gray-50 px-3 py-1.5 text-xs font-bold text-gray-400 border border-gray-200 cursor-not-allowed shadow-sm' title='No actions available'>
                <i class='bi bi-ban me-1.5'></i>Cancelled
            </span>";
        } elseif ($us === 2 && $ds === 2) {
            $html .= "<span class='inline-flex items-center rounded-lg bg-gray-50 px-3 py-1.5 text-xs font-bold text-gray-400 border border-gray-200 cursor-not-allowed shadow-sm' title='No actions available'>
                <i class='bi bi-check2-all me-1.5'></i>Completed
            </span>";
        } else {
            $html .= "<span class='inline-flex items-center rounded-lg bg-gray-50 px-3 py-1.5 text-xs font-bold text-gray-400 border border-gray-200 cursor-not-allowed shadow-sm'>
                <i class='bi bi-dash-circle me-1.5'></i>Closed
            </span>";
        }
        $html .= '</div>';
        return $html;
    }

    // Check if appointment needs payment (for User/Admin roles)
    $needsPayment = false;
    if ($isActive) {
        $fees = (int)($row['consultancyFees'] ?? 0);
        $paid = (int)($row['paid'] ?? 0);
        $discount = (int)($row['discount'] ?? 0);
        $depositPaid = (($row['deposit_status'] ?? 'none') === 'paid') ? (int)($row['deposit_amount'] ?? 0) : 0;
        $needsPayment = ($fees > 0 && ($paid + $depositPaid + $discount) < $fees);
    }

    $ref = urlencode(hms_encrypt_id($apid));

    if ($role === 'Patient') {
        if ($isActive) {
            $html .= "<a href='{$base}?action=edit&ref={$ref}&return={$ret}'
                class='inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20 hover:bg-blue-100'>
                <i class='bi bi-pencil me-1'></i>Edit</a>";

            $html .= "<a href='{$base}?action=cancel&ref={$ref}&return={$ret}&{$csrf}'
                class='inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20 hover:bg-red-100'
                onclick=\"return confirm('Cancel this appointment?');\">
                <i class='bi bi-x-circle me-1'></i>Cancel</a>";
        }
    } elseif ($role === 'Doctor') {
        if ($isActive) {
            $html .= "<a href='{$base}?action=cancel&ref={$ref}&return={$ret}&{$csrf}'
                class='inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20 hover:bg-red-100'
                onclick=\"return confirm('Cancel this appointment?');\">
                <i class='bi bi-x-circle me-1'></i>Cancel</a>";
        }
    } elseif (in_array($role, ['User', 'Admin', 'System Admin'], true)) {
        if ($isActive) {
            // Pay button (only if not fully paid)
            if ($needsPayment) {
                $html .= "<a href='{$payBase}?ref={$ref}&return={$ret}'
                    class='inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20 hover:bg-green-100'>
                    <i class='bi bi-cash-coin me-1'></i>Pay</a>";
            }

            $html .= "<a href='{$base}?action=edit&ref={$ref}&return={$ret}'
                class='inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20 hover:bg-blue-100'>
                <i class='bi bi-pencil me-1'></i>Edit</a>";

            $html .= "<a href='{$base}?action=cancel&ref={$ref}&return={$ret}'
                class='inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20 hover:bg-red-100'>
                <i class='bi bi-x-circle me-1'></i>Cancel</a>";
        }
    } else {
        $html .= "<span class='text-gray-400 text-xs'>-</span>";
    }

    $html .= '</div>';
    return $html;
}
/**
 * Detailed booking status badge
 */
function get_detailed_status_badge(array $row): string
{
    $us = (int)($row['userStatus'] ?? 1);
    $ds = (int)($row['doctorStatus'] ?? 1);
    $ps = $row['patient_status'] ?? 'waiting';
    $lateCount = (int)($row['late_reschedule_count'] ?? 0);
    
    // 1. Cancelled
    if ($us === 0 || $ds === 0) {
        return "<span class='inline-flex items-center rounded-md bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700 ring-1 ring-inset ring-red-600/20' title='Cancelled / ملغي'>Cancelled <span class='text-[10px] opacity-75 ms-1'>(ملغي)</span></span>";
    }
    
    // 2. Completed
    if ($ps === 'done' || ($us === 2 && $ds === 2)) {
        return "<span class='inline-flex items-center rounded-md bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700 ring-1 ring-inset ring-green-600/20' title='Completed / تم الكشف'>Completed <span class='text-[10px] opacity-75 ms-1'>(تم الكشف)</span></span>";
    }
    
    // Check if past time
    date_default_timezone_set('Africa/Cairo');
    $currentDate = date('Y-m-d');
    $currentTime = date('H:i:s');
    
    $apptDate = $row['appointmentDate'];
    $apptTime = $row['appointmentTime'] ?: '00:00:00';
    
    $isPast = false;
    if ($apptDate < $currentDate) {
        $isPast = true;
    } elseif ($apptDate === $currentDate && $apptTime < $currentTime) {
        $isPast = true;
    }
    
    // 3. No-Show
    if ($isPast && $ps === 'waiting') {
        return "<span class='inline-flex items-center rounded-md bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20' title='الميعاد عدا والمريض مجاش ومبلغش المستشفى انه هيلغي'>No-Show <span class='text-[10px] opacity-75 ms-1'>(لم يحضر)</span></span>";
    }
    
    // 4. Rescheduled
    if ($lateCount > 0) {
        return "<span class='inline-flex items-center rounded-md bg-purple-50 px-2.5 py-1 text-xs font-semibold text-purple-700 ring-1 ring-inset ring-purple-700/10' title='Rescheduled / تأجل'>Rescheduled <span class='text-[10px] opacity-75 ms-1'>(تأجل)</span></span>";
    }
    
    // 5. Pending
    return "<span class='inline-flex items-center rounded-md bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-inset ring-blue-700/10' title='Pending / لسه ميعاده مجاش'>Pending <span class='text-[10px] opacity-75 ms-1'>(قيد الانتظار)</span></span>";
}
?>
