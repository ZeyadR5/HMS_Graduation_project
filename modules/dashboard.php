<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/secure-token.php';
require_once __DIR__ . '/../includes/appointment-helpers.php';

$connect = hms_db_connect();
if ($connect->connect_error) {
    die("Connection failed: " . $connect->connect_error);
}

$role = $_SESSION['role'] ?? '';
$username = $_SESSION['username'] ?? 'User';
$sessionId = (int)($_SESSION['id'] ?? 0);
$sessionUid = (int)($_SESSION['uid'] ?? 0);

// Shared dashboard data helpers.
function hms_e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function hms_scalar(mysqli $connect, string $query, $default = 0)
{
    $result = $connect->query($query);
    if (!$result) {
        return $default;
    }

    $row = $result->fetch_row();
    return $row[0] ?? $default;
}

function hms_row(mysqli $connect, string $query): array
{
    $result = $connect->query($query);
    if (!$result) {
        return [];
    }

    $row = $result->fetch_assoc();
    return is_array($row) ? $row : [];
}

function hms_rows(mysqli $connect, string $query): array
{
    $result = $connect->query($query);
    if (!$result) {
        return [];
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

function hms_table_exists(mysqli $connect, string $table): bool
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($table === '') {
        return false;
    }

    $result = $connect->query("SHOW TABLES LIKE '{$table}'");
    return (bool)($result && $result->num_rows > 0);
}

function hms_column_exists(mysqli $connect, string $table, string $column): bool
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($table === '' || $column === '') {
        return false;
    }

    $result = $connect->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return (bool)($result && $result->num_rows > 0);
}

function hms_mysql_date_expr(string $field): string
{
    return "STR_TO_DATE({$field}, '%Y-%m-%d')";
}

function hms_mysql_datetime_expr(string $dateField, string $timeField): string
{
    return "STR_TO_DATE(CONCAT({$dateField}, ' ', CASE
        WHEN LENGTH(TRIM(COALESCE({$timeField}, ''))) = 5 THEN CONCAT(TRIM({$timeField}), ':00')
        WHEN LENGTH(TRIM(COALESCE({$timeField}, ''))) = 8 THEN TRIM({$timeField})
        ELSE '00:00:00'
    END), '%Y-%m-%d %H:%i:%s')";
}

function hms_money($value): string
{
    return number_format((float)$value) . ' EGP';
}

function hms_number($value): string
{
    return number_format((float)$value);
}

function hms_short_date($value): string
{
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime((string)$value);
    return $timestamp ? date('d M Y', $timestamp) : (string)$value;
}

function hms_short_time($value): string
{
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime((string)$value);
    return $timestamp ? date('h:i A', $timestamp) : (string)$value;
}

function hms_datetime_label($date, $time): string
{
    if (!$date && !$time) {
        return '-';
    }

    $timestamp = strtotime((string)$date);
    $dateStr = $timestamp ? date('d M', $timestamp) : hms_short_date($date);

    return trim($dateStr . ' at ' . hms_short_time($time));
}

function hms_limit_text($value, int $length = 90): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '-';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length - 3) . '...' : $value;
    }

    return strlen($value) > $length ? substr($value, 0, $length - 3) . '...' : $value;
}

function hms_badge(string $label, string $tone = 'slate'): string
{
    $map = [
        'blue' => 'bg-blue-50 text-blue-700 ring-blue-600/20',
        'cyan' => 'bg-cyan-50 text-cyan-700 ring-cyan-600/20',
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'rose' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        'orange' => 'bg-orange-50 text-orange-700 ring-orange-600/20',
        'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
        'slate' => 'bg-slate-100 text-slate-700 ring-slate-500/20',
    ];

    $classes = $map[$tone] ?? $map['slate'];
    return "<span class='inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {$classes}'>" . hms_e($label) . "</span>";
}

function hms_online_badge($value): string
{
    return (int)$value === 1 ? hms_badge('Online', 'emerald') : hms_badge('Offline', 'slate');
}

function hms_payment_meta(array $row): array
{
    $fees = (float)($row['consultancyFees'] ?? 0);
    $paid = (float)($row['paid'] ?? 0);
    $isPaid = $paid > 0 || ($fees > 0 && $paid >= $fees);
    $label = $isPaid ? 'Paid' : 'Unpaid';
    $tone = $isPaid ? 'emerald' : 'amber';
    $amount = $paid > 0 ? $paid : $fees;

    return ['label' => $label, 'tone' => $tone, 'amount' => $amount];
}

function hms_report_meta(array $row): array
{
    $hasReport = (int)($row['has_report'] ?? 0) === 1;
    return ['label' => $hasReport ? 'Ready' : 'Pending', 'tone' => $hasReport ? 'emerald' : 'amber'];
}

function hms_appt_status_meta(array $row): array
{
    $userStatus = (int)($row['userStatus'] ?? -1);
    $doctorStatus = (int)($row['doctorStatus'] ?? -1);

    if ($userStatus === 0 || $doctorStatus === 0) {
        $label = 'Cancelled';
        $tone = 'rose';
    } elseif ($userStatus === 2 || $doctorStatus === 2) {
        $label = 'Completed';
        $tone = 'emerald';
    } elseif ($userStatus === 1 && $doctorStatus === 1) {
        $label = 'Active';
        $tone = 'blue';
    } elseif ($userStatus === 1 || $doctorStatus === 1) {
        $label = 'Pending';
        $tone = 'amber';
    } else {
        $label = 'Pending';
        $tone = 'slate';
    }

    $note = '';
    $cancelledBy = appt_cancelled_by_text($row);
    if ($label === 'Cancelled' && $cancelledBy !== '-') {
        $note = $cancelledBy;
    }

    return ['label' => $label, 'tone' => $tone, 'note' => $note];
}

function hms_status_stack(array $row): string
{
    $meta = hms_appt_status_meta($row);
    $html = hms_badge($meta['label'], $meta['tone']);
    if ($meta['note'] !== '') {
        $html .= "<div class='mt-1 text-xs text-slate-500'>By " . hms_e($meta['note']) . "</div>";
    }

    return $html;
}

function hms_payment_stack(array $row): string
{
    $meta = hms_payment_meta($row);
    return hms_badge($meta['label'], $meta['tone']) . "<div class='mt-1 text-xs text-slate-500'>" . hms_e(hms_money($meta['amount'])) . "</div>";
}

function hms_report_stack(array $row): string
{
    $meta = hms_report_meta($row);
    return hms_badge($meta['label'], $meta['tone']);
}

function hms_primary_row(array $cells): array
{
    return ['cells' => $cells];
}

function hms_panel_item(string $title, string $subtitle = '', string $meta = '', string $badge = '', string $action = ''): array
{
    return ['title' => $title, 'subtitle' => $subtitle, 'meta' => $meta, 'badge' => $badge, 'action' => $action];
}

function hms_action_link(string $href, string $label, string $tone = 'blue'): string
{
    $map = [
        'blue' => 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100',
        'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100',
        'amber' => 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100',
        'slate' => 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100',
        'indigo' => 'border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100',
    ];

    $classes = $map[$tone] ?? $map['blue'];
    return "<a href='" . hms_e($href) . "' class='inline-flex items-center rounded-xl border px-3 py-2 text-sm font-semibold transition {$classes}'>" . hms_e($label) . "</a>";
}

$apptDateExpr = hms_mysql_date_expr('a.appointmentDate');
$apptDateTimeExpr = hms_mysql_datetime_expr('a.appointmentDate', 'a.appointmentTime');
$medicalHistoryTreatmentField = hms_column_exists($connect, 'tblmedicalhistory', 'treatment') ? 'mh.treatment' : (hms_column_exists($connect, 'tblmedicalhistory', 'prescription') ? 'mh.prescription' : "''");
$medicalHistoryReportField = hms_column_exists($connect, 'tblmedicalhistory', 'Report') ? 'mh.Report' : (hms_column_exists($connect, 'tblmedicalhistory', 'description') ? 'mh.description' : "''");
$medicalHistoryDateField = hms_column_exists($connect, 'tblmedicalhistory', 'CreationDate') ? 'mh.CreationDate' : 'NULL';

$heroTitle = 'Operational Dashboard';
$heroText = 'Role-aware clinic visibility with live reservations, workflow signals, and quick actions.';
$heroHighlights = [];
$stats = [];
$quickLinks = [];
$primaryTitle = 'Operational Snapshot';
$primaryColumns = [];
$primaryRows = [];
$primaryEmpty = 'No records available for this dashboard yet.';
$sidePanels = [];
$pageTone = 'cyan';

// Role-aware dashboard assembly.
switch ($role) {
    case 'System Admin':
        // System Admin dashboard.
        $pageTone = 'cyan';
        $heroTitle = 'System Command Dashboard';

        $summary = hms_row($connect, "
            SELECT
                SUM(CASE WHEN {$apptDateExpr} = CURDATE() THEN 1 ELSE 0 END) AS today_reservations,
                SUM(CASE WHEN {$apptDateExpr} = CURDATE() AND a.userStatus = 1 AND a.doctorStatus = 1 THEN 1 ELSE 0 END) AS active_queue,
                SUM(CASE WHEN {$apptDateExpr} = CURDATE() AND (a.userStatus = 2 OR a.doctorStatus = 2) AND a.userStatus <> 0 AND a.doctorStatus <> 0 THEN 1 ELSE 0 END) AS completed_today,
                SUM(CASE WHEN {$apptDateExpr} = CURDATE() AND (a.userStatus = 0 OR a.doctorStatus = 0) THEN 1 ELSE 0 END) AS cancelled_today,
                SUM(CASE WHEN {$apptDateExpr} = CURDATE() AND COALESCE(a.paid, 0) = 0 AND a.userStatus <> 0 AND a.doctorStatus <> 0 THEN 1 ELSE 0 END) AS unpaid_today,
                SUM(CASE WHEN {$apptDateExpr} = CURDATE() AND a.userStatus <> 0 AND a.doctorStatus <> 0 THEN COALESCE(NULLIF(a.paid, 0), a.consultancyFees, 0) ELSE 0 END) AS payments_today
            FROM appointment a
        ");

        $reportsAddedToday = (int)hms_scalar($connect, "SELECT COUNT(*) FROM tblmedicalhistory WHERE CreationDate = CURDATE()", 0);
        if ($reportsAddedToday === 0) {
            $reportsAddedToday = (int)hms_scalar($connect, "
                SELECT COUNT(*)
                FROM tblmedicalhistory mh
                INNER JOIN appointment a ON a.apid = mh.apid
                WHERE {$apptDateExpr} = CURDATE()
            ", 0);
        }

        $doctorsOnline = (int)hms_scalar($connect, "SELECT COUNT(*) FROM doctors WHERE COALESCE(statue, 0) = 1", 0);
        $frontDeskOnline = (int)hms_scalar($connect, "SELECT COUNT(*) FROM employ WHERE role = 'User' AND COALESCE(employ_statue, 0) = 1", 0);
        $pendingReportsToday = (int)hms_scalar($connect, "
            SELECT COUNT(*)
            FROM appointment a
            WHERE {$apptDateExpr} = CURDATE()
              AND (a.userStatus = 2 OR a.doctorStatus = 2)
              AND a.userStatus <> 0
              AND a.doctorStatus <> 0
              AND NOT EXISTS (SELECT 1 FROM tblmedicalhistory mh WHERE mh.apid = a.apid)
        ", 0);
        $offlineCoverage = (int)hms_scalar($connect, "
            SELECT COUNT(*)
            FROM appointment a
            INNER JOIN doctors d ON d.id = a.doctorId
            WHERE {$apptDateExpr} = CURDATE()
              AND a.userStatus = 1
              AND a.doctorStatus = 1
              AND COALESCE(d.statue, 0) = 0
        ", 0);

        $heroText = 'Full system visibility across reservations, queue pressure, staffing presence, payments, and follow-up items that need action today.';
        $heroHighlights = [
            ['label' => 'Immediate Focus', 'value' => hms_number($summary['active_queue'] ?? 0) . ' patients are still active in today\'s queue.'],
            ['label' => 'Alerts', 'value' => ($pendingReportsToday + (int)($summary['unpaid_today'] ?? 0) + $offlineCoverage) > 0 ? 'Collections, report follow-up, or coverage need review.' : 'No critical gaps detected right now.'],
        ];

        $stats = [
            ['label' => 'Today Reservations', 'value' => hms_number($summary['today_reservations'] ?? 0), 'tone' => 'cyan'],
            ['label' => 'Active Queue', 'value' => hms_number($summary['active_queue'] ?? 0), 'tone' => 'blue'],
            ['label' => 'Completed Today', 'value' => hms_number($summary['completed_today'] ?? 0), 'tone' => 'emerald'],
            ['label' => 'Cancelled Today', 'value' => hms_number($summary['cancelled_today'] ?? 0), 'tone' => 'rose'],
            ['label' => 'Doctors Online', 'value' => hms_number($doctorsOnline), 'tone' => 'teal'],
            ['label' => 'Front Desk Online', 'value' => hms_number($frontDeskOnline), 'tone' => 'indigo'],
            ['label' => 'Payments Today', 'value' => hms_money($summary['payments_today'] ?? 0), 'tone' => 'amber'],
            ['label' => 'Reports Added Today', 'value' => hms_number($reportsAddedToday), 'tone' => 'emerald'],
        ];

        $quickLinks = [
            ['label' => 'Reservations', 'href' => '/modules/super-admin/super-Reservations.php'],
            ['label' => 'Payments', 'href' => '/modules/super-admin/super-Payments.php'],
            ['label' => 'Users', 'href' => '/modules/super-admin/super-user-log.php'],
            ['label' => 'Add Doctor', 'href' => '/modules/super-admin/Add-Doctor.php'],
            ['label' => 'Add User', 'href' => '/modules/super-admin/Add-user.php'],
            ['label' => 'Add Specialization', 'href' => '/modules/super-admin/Add-specilization.php'],
            ['label' => 'Audit Log', 'href' => '/includes/audit-log.php'],
        ];

        $primaryTitle = 'Today Reservations';
        $primaryColumns = ['Patient', 'Doctor', 'Specialization', 'Date & Time', 'Status', 'Payment', 'Created By'];
        $systemRows = hms_rows($connect, "
            SELECT
                a.apid,
                COALESCE(NULLIF(a.patient_Name, ''), u.fullName, CONCAT('Patient #', a.userId)) AS patient_name,
                COALESCE(d.doctorName, 'Unassigned Doctor') AS doctor_name,
                COALESCE(NULLIF(a.doctorSpecialization, ''), d.specilization, '-') AS specialization,
                a.appointmentDate,
                a.appointmentTime,
                a.userStatus,
                a.doctorStatus,
                a.cancelledBy,
                a.consultancyFees,
                a.paid,
                COALESCE(NULLIF(a.employname, ''), e.username, 'Patient') AS created_by
            FROM appointment a
            LEFT JOIN users u ON u.uid = a.userId
            LEFT JOIN doctors d ON d.id = a.doctorId
            LEFT JOIN employ e ON e.id = a.employId
            WHERE {$apptDateExpr} = CURDATE()
            ORDER BY {$apptDateTimeExpr} ASC, a.postingDate ASC
            LIMIT 10
        ");
        foreach ($systemRows as $row) {
            $primaryRows[] = hms_primary_row([
                "<div class='font-semibold text-slate-900'>" . hms_e($row['patient_name']) . "</div>",
                "<div class='font-medium text-slate-800'>" . hms_e($row['doctor_name']) . "</div>",
                "<div class='text-slate-600'>" . hms_e($row['specialization']) . "</div>",
                "<div class='font-medium text-slate-800'>" . hms_e(hms_datetime_label($row['appointmentDate'], $row['appointmentTime'])) . "</div>",
                hms_status_stack($row),
                hms_payment_stack($row),
                "<div class='text-sm text-slate-700'>" . hms_e($row['created_by']) . "</div>",
            ]);
        }
        $primaryEmpty = 'No reservations are scheduled for today yet.';

        $coverageRows = hms_rows($connect, "
            SELECT
                d.id,
                d.doctorName,
                COALESCE(NULLIF(d.specilization, ''), 'General') AS specilization,
                COALESCE(d.statue, 0) AS statue,
                COUNT(*) AS today_count,
                SUM(CASE WHEN a.userStatus = 1 AND a.doctorStatus = 1 THEN 1 ELSE 0 END) AS live_queue
            FROM doctors d
            INNER JOIN appointment a ON a.doctorId = d.id
            WHERE {$apptDateExpr} = CURDATE()
            GROUP BY d.id, d.doctorName, d.specilization, d.statue
            ORDER BY COALESCE(d.statue, 0) DESC, today_count DESC, d.doctorName ASC
            LIMIT 6
        ");
        $coveragePanel = [];
        foreach ($coverageRows as $row) {
            $coveragePanel[] = hms_panel_item(
                $row['doctorName'] ?: 'Unnamed Doctor',
                $row['specilization'] ?: 'General',
                'Today: ' . hms_number($row['today_count']) . ' reservations | Live queue: ' . hms_number($row['live_queue']),
                hms_online_badge($row['statue'])
            );
        }

        $criticalAlertRows = [];
        if ((int)($summary['unpaid_today'] ?? 0) > 0) {
            $criticalAlertRows[] = hms_panel_item('Unpaid reservations', hms_number($summary['unpaid_today']) . ' reservations still have no collected payment.', 'Review the payments page to close the day-end collection gap.', hms_badge('Follow Up', 'amber'), hms_action_link('/modules/super-admin/super-Payments.php', 'Open Payments', 'amber'));
        }
        if ($pendingReportsToday > 0) {
            $criticalAlertRows[] = hms_panel_item('Pending reports', hms_number($pendingReportsToday) . ' completed visits do not yet have linked medical reports.', 'Doctors or admins should confirm the report workflow for today.', hms_badge('Reports', 'blue'), hms_action_link('/includes/med-record.php', 'Open Medical Records', 'blue'));
        }
        if ($offlineCoverage > 0) {
            $criticalAlertRows[] = hms_panel_item('Coverage risk', hms_number($offlineCoverage) . ' active reservations are linked to doctors marked offline.', 'Check the doctor list and front desk coordination before queue delays grow.', hms_badge('Coverage', 'rose'), hms_action_link('/modules/super-admin/doc.php', 'View Doctors', 'slate'));
        }
        if ((int)($summary['cancelled_today'] ?? 0) > 0) {
            $criticalAlertRows[] = hms_panel_item('Cancelled today', hms_number($summary['cancelled_today']) . ' reservations were cancelled today.', 'Use this as a rebooking and service recovery check.', hms_badge('Cancelled', 'rose'), hms_action_link('/modules/super-admin/super-Reservations.php', 'Review Reservations', 'slate'));
        }

        $activityPanel = [];
        if (hms_table_exists($connect, 'audit_logs')) {
            $activityRows = hms_rows($connect, "
                SELECT description, action_key, actor_name, created_at
                FROM audit_logs
                WHERE DATE(created_at) = CURDATE()
                ORDER BY created_at DESC
                LIMIT 6
            ");
            foreach ($activityRows as $row) {
                $activityPanel[] = hms_panel_item(
                    $row['description'] ?: 'System activity',
                    ($row['actor_name'] ?: 'System') . ' | ' . str_replace('.', ' / ', (string)$row['action_key']),
                    hms_datetime_label(substr((string)$row['created_at'], 0, 10), substr((string)$row['created_at'], 11, 8)),
                    hms_badge('Audit', 'indigo')
                );
            }
        }
        if ($activityPanel === []) {
            $fallbackActivity = hms_rows($connect, "
                SELECT
                    COALESCE(NULLIF(a.patient_Name, ''), u.fullName, 'Patient') AS patient_name,
                    COALESCE(d.doctorName, 'Doctor') AS doctor_name,
                    a.postingDate
                FROM appointment a
                LEFT JOIN users u ON u.uid = a.userId
                LEFT JOIN doctors d ON d.id = a.doctorId
                WHERE DATE(a.postingDate) = CURDATE()
                ORDER BY a.postingDate DESC
                LIMIT 6
            ");
            foreach ($fallbackActivity as $row) {
                $activityPanel[] = hms_panel_item('Reservation created', $row['patient_name'] . ' with ' . $row['doctor_name'], hms_datetime_label(substr((string)$row['postingDate'], 0, 10), substr((string)$row['postingDate'], 11, 8)), hms_badge('Recent', 'slate'));
            }
        }

        $sidePanels = [
            ['title' => 'Doctor Coverage', 'empty' => 'No doctor coverage data is available.', 'rows' => $coveragePanel],
            ['title' => 'Critical Alerts', 'empty' => 'No critical alerts need action right now.', 'rows' => $criticalAlertRows],
            ['title' => 'Recent System Activity', 'empty' => 'No recent activity was found.', 'rows' => $activityPanel],
        ];
        break;

    case 'Admin':
        // Admin dashboard.
        $pageTone = 'blue';
        $heroTitle = 'Clinic Operations Dashboard';

        $summary = hms_row($connect, "
            SELECT
                SUM(CASE WHEN {$apptDateExpr} = CURDATE() THEN 1 ELSE 0 END) AS today_reservations,
                SUM(CASE WHEN {$apptDateExpr} = CURDATE() AND a.userStatus = 1 AND a.doctorStatus = 1 THEN 1 ELSE 0 END) AS active_queue,
                SUM(CASE WHEN {$apptDateExpr} = CURDATE() AND (a.userStatus = 2 OR a.doctorStatus = 2) AND a.userStatus <> 0 AND a.doctorStatus <> 0 THEN 1 ELSE 0 END) AS completed_today,
                SUM(CASE WHEN {$apptDateExpr} = CURDATE() AND COALESCE(a.paid, 0) = 0 AND a.userStatus <> 0 AND a.doctorStatus <> 0 THEN 1 ELSE 0 END) AS unpaid_today,
                SUM(CASE WHEN {$apptDateExpr} = CURDATE() AND a.userStatus <> 0 AND a.doctorStatus <> 0 THEN COALESCE(NULLIF(a.paid, 0), a.consultancyFees, 0) ELSE 0 END) AS payments_today
            FROM appointment a
        ");
        $doctorsOnline = (int)hms_scalar($connect, "SELECT COUNT(*) FROM doctors WHERE COALESCE(statue, 0) = 1", 0);
        $patientsWithReports = (int)hms_scalar($connect, "
            SELECT COUNT(DISTINCT a.userId)
            FROM appointment a
            INNER JOIN tblmedicalhistory mh ON mh.apid = a.apid
            WHERE {$apptDateExpr} = CURDATE()
              AND a.userId IS NOT NULL
              AND a.userId <> 0
        ", 0);
        $activePatients = (int)hms_scalar($connect, "SELECT COUNT(*) FROM users WHERE DATE(regDate) = CURDATE()", 0);
        $pendingReports = (int)hms_scalar($connect, "
            SELECT COUNT(*)
            FROM appointment a
            WHERE {$apptDateExpr} = CURDATE()
              AND a.userStatus <> 0
              AND a.doctorStatus <> 0
              AND NOT EXISTS (SELECT 1 FROM tblmedicalhistory mh WHERE mh.apid = a.apid)
        ", 0);
        $lateToday = (int)hms_scalar($connect, "
            SELECT COUNT(*)
            FROM appointment a
            WHERE {$apptDateExpr} = CURDATE()
              AND a.userStatus = 1
              AND a.doctorStatus = 1
              AND {$apptDateTimeExpr} < NOW()
        ", 0);

        $heroText = 'Daily clinic flow for reservations, collections, report progress, and delays that need the admin team today.';
        $heroHighlights = [
            ['label' => 'Operations Focus', 'value' => hms_number($summary['active_queue'] ?? 0) . ' patients are still active in today\'s clinic queue.'],
            ['label' => 'Follow Up', 'value' => (($pendingReports + $lateToday + (int)($summary['unpaid_today'] ?? 0)) > 0) ? 'Unpaid cases, late visits, or reports still need attention.' : 'No urgent operational blockers are visible right now.'],
        ];

        $stats = [
            ['label' => 'Today Reservations', 'value' => hms_number($summary['today_reservations'] ?? 0), 'tone' => 'cyan'],
            ['label' => 'Active Queue', 'value' => hms_number($summary['active_queue'] ?? 0), 'tone' => 'blue'],
            ['label' => 'Completed Today', 'value' => hms_number($summary['completed_today'] ?? 0), 'tone' => 'emerald'],
            ['label' => 'Unpaid Reservations', 'value' => hms_number($summary['unpaid_today'] ?? 0), 'tone' => 'amber'],
            ['label' => 'Payments Today', 'value' => hms_money($summary['payments_today'] ?? 0), 'tone' => 'amber'],
            ['label' => 'Doctors Online', 'value' => hms_number($doctorsOnline), 'tone' => 'teal'],
            ['label' => 'Patients With Reports Today', 'value' => hms_number($patientsWithReports), 'tone' => 'indigo'],
            ['label' => 'New Patients Today', 'value' => hms_number($activePatients), 'tone' => 'cyan'],
        ];

        $quickLinks = [
            ['label' => 'Reservations', 'href' => '/modules/admin/admin-Reservations.php'],
            ['label' => 'Payments', 'href' => '/modules/admin/admin-Payments.php'],
            ['label' => 'Users', 'href' => '/modules/admin/admin-user-log.php'],
            ['label' => 'Medical Records', 'href' => '/includes/med-record.php'],
            ['label' => 'Audit Log', 'href' => '/includes/audit-log.php'],
        ];

        $primaryRowsSource = hms_rows($connect, "
            SELECT
                a.apid,
                COALESCE(NULLIF(a.patient_Name, ''), u.fullName, CONCAT('Patient #', a.userId)) AS patient_name,
                COALESCE(d.doctorName, 'Unassigned Doctor') AS doctor_name,
                a.appointmentDate,
                a.appointmentTime,
                a.userStatus,
                a.doctorStatus,
                a.cancelledBy,
                a.consultancyFees,
                a.paid,
                EXISTS(SELECT 1 FROM tblmedicalhistory mh WHERE mh.apid = a.apid) AS has_report
            FROM appointment a
            LEFT JOIN users u ON u.uid = a.userId
            LEFT JOIN doctors d ON d.id = a.doctorId
            WHERE {$apptDateExpr} = CURDATE()
            ORDER BY {$apptDateTimeExpr} ASC, a.postingDate ASC
            LIMIT 10
        ");
        $primaryTitle = 'Today Reservations';
        $primaryColumns = ['Patient', 'Doctor', 'Time', 'Status', 'Payment Status', 'Report Status'];
        foreach ($primaryRowsSource as $row) {
            $primaryRows[] = hms_primary_row([
                "<div class='font-semibold text-slate-900'>" . hms_e($row['patient_name']) . "</div>",
                "<div class='font-medium text-slate-800'>" . hms_e($row['doctor_name']) . "</div>",
                "<div class='text-slate-700'>" . hms_e(hms_datetime_label($row['appointmentDate'], $row['appointmentTime'])) . "</div>",
                hms_status_stack($row),
                hms_payment_stack($row),
                hms_report_stack($row),
            ]);
        }
        $primaryEmpty = 'No reservations were found for the current admin view.';

        $unpaidRows = hms_rows($connect, "
            SELECT
                COALESCE(NULLIF(a.patient_Name, ''), u.fullName, CONCAT('Patient #', a.userId)) AS patient_name,
                COALESCE(d.doctorName, 'Unassigned Doctor') AS doctor_name,
                a.appointmentDate,
                a.appointmentTime,
                a.consultancyFees,
                a.paid
            FROM appointment a
            LEFT JOIN users u ON u.uid = a.userId
            LEFT JOIN doctors d ON d.id = a.doctorId
            WHERE {$apptDateExpr} = CURDATE()
              AND COALESCE(a.paid, 0) = 0
              AND a.userStatus <> 0
              AND a.doctorStatus <> 0
            ORDER BY {$apptDateExpr} ASC, {$apptDateTimeExpr} ASC
            LIMIT 6
        ");
        $unpaidPanel = [];
        foreach ($unpaidRows as $row) {
            $unpaidPanel[] = hms_panel_item($row['patient_name'] ?: 'Patient', $row['doctor_name'] ?: 'Doctor', 'Fee: ' . hms_money($row['consultancyFees'] ?? 0) . ' | ' . hms_datetime_label($row['appointmentDate'], $row['appointmentTime']), hms_badge('Unpaid', 'amber'), hms_action_link('/modules/admin/admin-Payments.php', 'Collect Payment', 'amber'));
        }

        $pendingReportRows = hms_rows($connect, "
            SELECT
                a.apid,
                a.userId,
                COALESCE(NULLIF(a.patient_Name, ''), u.fullName, CONCAT('Patient #', a.userId)) AS patient_name,
                COALESCE(d.doctorName, 'Unassigned Doctor') AS doctor_name,
                a.appointmentDate
            FROM appointment a
            LEFT JOIN users u ON u.uid = a.userId
            LEFT JOIN doctors d ON d.id = a.doctorId
            WHERE {$apptDateExpr} = CURDATE()
              AND a.userStatus <> 0
              AND a.doctorStatus <> 0
              AND NOT EXISTS (SELECT 1 FROM tblmedicalhistory mh WHERE mh.apid = a.apid)
            ORDER BY {$apptDateExpr} DESC, a.postingDate DESC
            LIMIT 6
        ");
        $pendingReportPanel = [];
        foreach ($pendingReportRows as $row) {
            $pendingReportPanel[] = hms_panel_item($row['patient_name'] ?: 'Patient', $row['doctor_name'] ?: 'Doctor', 'Visit date: ' . hms_short_date($row['appointmentDate']), hms_badge('Pending Report', 'blue'), hms_action_link('/includes/patient-profile.php?ref=' . urlencode(hms_encrypt_id((int)$row['userId'])), 'Open Record', 'blue'));
        }

        $lateCancelledRows = hms_rows($connect, "
            SELECT
                COALESCE(NULLIF(a.patient_Name, ''), u.fullName, CONCAT('Patient #', a.userId)) AS patient_name,
                COALESCE(d.doctorName, 'Unassigned Doctor') AS doctor_name,
                a.appointmentDate,
                a.appointmentTime,
                a.userStatus,
                a.doctorStatus,
                a.cancelledBy,
                CASE WHEN (a.userStatus = 0 OR a.doctorStatus = 0) THEN 'Cancelled' ELSE 'Late' END AS alert_type
            FROM appointment a
            LEFT JOIN users u ON u.uid = a.userId
            LEFT JOIN doctors d ON d.id = a.doctorId
            WHERE ({$apptDateExpr} = CURDATE() AND a.userStatus = 1 AND a.doctorStatus = 1 AND {$apptDateTimeExpr} < NOW())
               OR ({$apptDateExpr} = CURDATE() AND (a.userStatus = 0 OR a.doctorStatus = 0))
            ORDER BY {$apptDateTimeExpr} ASC, a.postingDate DESC
            LIMIT 6
        ");
        $coverageRows = hms_rows($connect, "
            SELECT
                d.id,
                d.doctorName,
                COALESCE(NULLIF(d.specilization, ''), 'General') AS specilization,
                COALESCE(d.statue, 0) AS statue,
                COUNT(*) AS today_count,
                SUM(CASE WHEN a.userStatus = 1 AND a.doctorStatus = 1 THEN 1 ELSE 0 END) AS live_queue
            FROM doctors d
            INNER JOIN appointment a ON a.doctorId = d.id
            WHERE {$apptDateExpr} = CURDATE()
            GROUP BY d.id, d.doctorName, d.specilization, d.statue
            ORDER BY COALESCE(d.statue, 0) DESC, today_count DESC, d.doctorName ASC
            LIMIT 6
        ");
        $coveragePanel = [];
        foreach ($coverageRows as $row) {
            $coveragePanel[] = hms_panel_item(
                $row['doctorName'] ?: 'Unnamed Doctor',
                $row['specilization'] ?: 'General',
                'Today: ' . hms_number($row['today_count']) . ' reservations | Live queue: ' . hms_number($row['live_queue']),
                hms_online_badge($row['statue'])
            );
        }

        $latePanel = [];
        foreach ($lateCancelledRows as $row) {
            $latePanel[] = hms_panel_item($row['patient_name'] ?: 'Patient', $row['doctor_name'] ?: 'Doctor', $row['alert_type'] . ' | ' . hms_datetime_label($row['appointmentDate'], $row['appointmentTime']), hms_badge($row['alert_type'], $row['alert_type'] === 'Late' ? 'amber' : 'rose'), $row['alert_type'] === 'Cancelled' ? '' : hms_action_link('/modules/admin/admin-Reservations.php', 'Review Queue', 'slate'));
        }

        $sidePanels = [
            ['title' => 'Doctor Coverage Today', 'empty' => 'No doctors have reservations today.', 'rows' => $coveragePanel],
            ['title' => 'Unpaid Cases', 'empty' => 'No unpaid reservations need collection right now.', 'rows' => $unpaidPanel],
            ['title' => 'Pending Reports', 'empty' => 'All tracked visits currently have report coverage for today.', 'rows' => $pendingReportPanel],
            ['title' => 'Late / Cancelled Appointments', 'empty' => 'No late or cancelled appointments require attention today.', 'rows' => $latePanel],
        ];
        break;

    case 'Doctor':
        // Doctor dashboard.
        $pageTone = 'emerald';
        $heroTitle = 'Doctor Workboard';

        $summary = hms_row($connect, "
            SELECT
                SUM(CASE WHEN {$apptDateExpr} = CURDATE() THEN 1 ELSE 0 END) AS today_appointments,
                SUM(CASE WHEN {$apptDateExpr} = CURDATE() AND a.userStatus = 1 AND a.doctorStatus = 1 THEN 1 ELSE 0 END) AS active_queue,
                SUM(CASE WHEN {$apptDateExpr} = CURDATE() AND (a.userStatus = 2 OR a.doctorStatus = 2) AND a.userStatus <> 0 AND a.doctorStatus <> 0 THEN 1 ELSE 0 END) AS completed_today,
                SUM(CASE WHEN {$apptDateExpr} = CURDATE() AND a.userStatus <> 0 AND a.doctorStatus <> 0 AND NOT EXISTS (SELECT 1 FROM tblmedicalhistory mh WHERE mh.apid = a.apid) THEN 1 ELSE 0 END) AS pending_reports,
                SUM(CASE WHEN {$apptDateExpr} = CURDATE() AND (a.userStatus = 0 OR a.doctorStatus = 0) THEN 1 ELSE 0 END) AS cancelled_cases,
                COUNT(DISTINCT CASE WHEN {$apptDateExpr} = CURDATE() AND (a.userStatus = 2 OR a.doctorStatus = 2) AND a.userStatus <> 0 AND a.doctorStatus <> 0 THEN a.userId END) AS patients_seen_today
            FROM appointment a
            WHERE a.doctorId = {$sessionId}
        ");

        $heroText = 'Action-first view for today\'s patient queue, unfinished reports, and the next consultations that need your attention.';
        $heroHighlights = [
            ['label' => 'Immediate Focus', 'value' => hms_number($summary['active_queue'] ?? 0) . ' patients are still active in your queue today.'],
            ['label' => 'Documentation', 'value' => (int)($summary['pending_reports'] ?? 0) > 0 ? 'Report work is pending on recent visits.' : 'Your report queue is currently clear.'],
        ];

        $stats = [
            ['label' => 'Today Appointments', 'value' => hms_number($summary['today_appointments'] ?? 0), 'tone' => 'cyan'],
            ['label' => 'Active Queue', 'value' => hms_number($summary['active_queue'] ?? 0), 'tone' => 'blue'],
            ['label' => 'Completed Today', 'value' => hms_number($summary['completed_today'] ?? 0), 'tone' => 'emerald'],
            ['label' => 'Pending Reports', 'value' => hms_number($summary['pending_reports'] ?? 0), 'tone' => 'amber'],
            ['label' => 'Cancelled Cases', 'value' => hms_number($summary['cancelled_cases'] ?? 0), 'tone' => 'rose'],
            ['label' => 'Patients Seen Today', 'value' => hms_number($summary['patients_seen_today'] ?? 0), 'tone' => 'indigo'],
        ];

        $quickLinks = [
            ['label' => 'Today Reservations', 'href' => '/modules/doctor/doc-Reservations.php'],
            ['label' => 'Write Report', 'href' => '/modules/doctor/doc-write.php'],
            ['label' => 'Medical Records', 'href' => '/includes/med-record.php'],
        ];

        $primaryTitle = 'Today Patient Queue';
        $primaryColumns = ['Patient', 'Date', 'Time', 'Status', 'Action'];
        $doctorRows = hms_rows($connect, "
            SELECT
                a.apid,
                a.userId,
                COALESCE(NULLIF(a.patient_Name, ''), u.fullName, CONCAT('Patient #', a.userId)) AS patient_name,
                a.appointmentDate,
                a.appointmentTime,
                a.userStatus,
                a.doctorStatus,
                a.cancelledBy,
                EXISTS(SELECT 1 FROM tblmedicalhistory mh WHERE mh.apid = a.apid) AS has_report
            FROM appointment a
            LEFT JOIN users u ON u.uid = a.userId
            WHERE a.doctorId = {$sessionId}
              AND {$apptDateExpr} = CURDATE()
            ORDER BY {$apptDateTimeExpr} ASC, a.postingDate ASC
            LIMIT 10
        ");
        foreach ($doctorRows as $row) {
            $actionHtml = '';
            if ((int)($row['has_report'] ?? 0) === 1) {
                $actionHtml .= hms_action_link('/includes/patient-profile.php?ref=' . urlencode(hms_encrypt_id((int)$row['userId'])), 'View Medical Record', 'blue');
            } else {
                // Only show "Write Report" if not cancelled
                if ((int)($row['userStatus'] ?? 1) !== 0 && (int)($row['doctorStatus'] ?? 1) !== 0) {
                    $actionHtml .= hms_action_link('/modules/doctor/doc-write.php?ref=' . urlencode(hms_encrypt_id((int)$row['apid'])), 'Write Report', 'emerald');
                    $actionHtml .= " <span class='inline-block w-2'></span>";
                }
                $actionHtml .= hms_action_link('/includes/patient-profile.php?ref=' . urlencode(hms_encrypt_id((int)$row['userId'])), 'View Medical Record', 'slate');
            }

            $primaryRows[] = hms_primary_row([
                "<div class='font-semibold text-slate-900'>" . hms_e($row['patient_name']) . "</div>",
                "<div class='text-slate-700'>" . hms_e(hms_short_date($row['appointmentDate'])) . "</div>",
                "<div class='text-slate-700'>" . hms_e(hms_short_time($row['appointmentTime'])) . "</div>",
                hms_status_stack($row),
                "<div class='min-w-[12rem]'>" . $actionHtml . "</div>",
            ]);
        }
        $primaryEmpty = 'You do not have patient appointments scheduled for today.';

        $reportRows = hms_rows($connect, "
            SELECT
                a.apid,
                a.userId,
                COALESCE(NULLIF(a.patient_Name, ''), u.fullName, CONCAT('Patient #', a.userId)) AS patient_name,
                a.appointmentDate,
                a.appointmentTime
            FROM appointment a
            LEFT JOIN users u ON u.uid = a.userId
            WHERE a.doctorId = {$sessionId}
              AND {$apptDateExpr} = CURDATE()
              AND a.userStatus <> 0
              AND a.doctorStatus <> 0
              AND NOT EXISTS (SELECT 1 FROM tblmedicalhistory mh WHERE mh.apid = a.apid)
            ORDER BY {$apptDateExpr} DESC, {$apptDateTimeExpr} DESC
            LIMIT 6
        ");
        $reportPanel = [];
        foreach ($reportRows as $row) {
            $reportPanel[] = hms_panel_item($row['patient_name'] ?: 'Patient', 'Appointment ' . hms_datetime_label($row['appointmentDate'], $row['appointmentTime']), 'This visit still needs a medical report.', hms_badge('Pending Report', 'amber'), hms_action_link('/modules/doctor/doc-write.php?ref=' . urlencode(hms_encrypt_id((int)$row['apid'])), 'Write Report', 'emerald'));
        }

        $nextRows = hms_rows($connect, "
            SELECT
                a.apid,
                a.userId,
                COALESCE(NULLIF(a.patient_Name, ''), u.fullName, CONCAT('Patient #', a.userId)) AS patient_name,
                a.appointmentDate,
                a.appointmentTime
            FROM appointment a
            LEFT JOIN users u ON u.uid = a.userId
            WHERE a.doctorId = {$sessionId}
              AND {$apptDateExpr} = CURDATE()
              AND a.userStatus = 1
              AND a.doctorStatus = 1
              AND {$apptDateTimeExpr} >= NOW()
            ORDER BY {$apptDateTimeExpr} ASC
            LIMIT 6
        ");
        $nextPanel = [];
        foreach ($nextRows as $row) {
            $nextPanel[] = hms_panel_item($row['patient_name'] ?: 'Patient', 'Scheduled for ' . hms_short_time($row['appointmentTime']), 'Keep the record ready before consultation.', hms_badge('Next Patient', 'blue'), hms_action_link('/includes/patient-profile.php?ref=' . urlencode(hms_encrypt_id((int)$row['userId'])), 'View Record', 'blue'));
        }

        $historyRows = hms_rows($connect, "
            SELECT
                a.userId,
                COALESCE(NULLIF(a.patient_Name, ''), u.fullName, CONCAT('Patient #', a.userId)) AS patient_name,
                MAX({$apptDateExpr}) AS last_visit,
                COUNT(*) AS visits_count
            FROM appointment a
            LEFT JOIN users u ON u.uid = a.userId
            WHERE a.doctorId = {$sessionId}
              AND {$apptDateExpr} = CURDATE()
              AND (a.userStatus = 2 OR a.doctorStatus = 2)
              AND a.userStatus <> 0
              AND a.doctorStatus <> 0
            GROUP BY a.userId, patient_name
            ORDER BY last_visit DESC
            LIMIT 6
        ");
        $historyPanel = [];
        foreach ($historyRows as $row) {
            $historyPanel[] = hms_panel_item($row['patient_name'] ?: 'Patient', 'Last visit: ' . hms_short_date($row['last_visit']), 'Completed visits: ' . hms_number($row['visits_count']), hms_badge('History', 'indigo'), hms_action_link('/includes/patient-profile.php?ref=' . urlencode(hms_encrypt_id((int)$row['userId'])), 'Open Record', 'slate'));
        }

        $sidePanels = [
            ['title' => 'Reports To Finish', 'empty' => 'There are no unfinished reports in your queue.', 'rows' => $reportPanel],
            ['title' => 'Next Patients', 'empty' => 'No more upcoming active patients are scheduled today.', 'rows' => $nextPanel],
            ['title' => 'Recent Patient History', 'empty' => 'Completed visit history will appear here.', 'rows' => $historyPanel],
        ];
        break;

    case 'User':
        // Front desk dashboard.
        $pageTone = 'amber';
        $heroTitle = 'Front Desk Dashboard';

        $summary = hms_row($connect, "
            SELECT
                SUM(CASE WHEN DATE(a.postingDate) = CURDATE() THEN 1 ELSE 0 END) AS created_today,
                SUM(CASE WHEN DATE(a.postingDate) = CURDATE() THEN 1 ELSE 0 END) AS total_created,
                SUM(CASE WHEN {$apptDateExpr} = CURDATE() AND a.userStatus = 1 AND a.doctorStatus = 1 THEN 1 ELSE 0 END) AS active_reservations,
                SUM(CASE WHEN {$apptDateExpr} = CURDATE() AND a.userStatus <> 0 AND a.doctorStatus <> 0 THEN COALESCE(NULLIF(a.paid, 0), a.consultancyFees, 0) ELSE 0 END) AS collected_payments,
                SUM(CASE WHEN {$apptDateExpr} = CURDATE() AND COALESCE(a.paid, 0) = 0 AND a.userStatus <> 0 AND a.doctorStatus <> 0 THEN 1 ELSE 0 END) AS unpaid_cases,
                SUM(CASE WHEN {$apptDateExpr} = CURDATE() AND {$apptDateTimeExpr} >= NOW() AND a.userStatus = 1 AND a.doctorStatus = 1 THEN 1 ELSE 0 END) AS upcoming_today
            FROM appointment a
            WHERE a.employId = {$sessionId}
        ");

        $heroText = 'Front desk control board for bookings, doctor availability, payment collection, and the next arrivals created by your desk.';
        $heroHighlights = [
            ['label' => 'Reception Focus', 'value' => hms_number($summary['upcoming_today'] ?? 0) . ' upcoming reservations are still ahead today.'],
            ['label' => 'Collections', 'value' => (int)($summary['unpaid_cases'] ?? 0) > 0 ? 'Some reservations still need payment follow-up.' : 'No unpaid reservation is pending in your queue.'],
        ];

        $stats = [
            ['label' => 'Created Today', 'value' => hms_number($summary['created_today'] ?? 0), 'tone' => 'cyan'],
            ['label' => 'Total Created Today', 'value' => hms_number($summary['total_created'] ?? 0), 'tone' => 'blue'],
            ['label' => 'Active Today', 'value' => hms_number($summary['active_reservations'] ?? 0), 'tone' => 'emerald'],
            ['label' => 'Collected Today', 'value' => hms_money($summary['collected_payments'] ?? 0), 'tone' => 'amber'],
            ['label' => 'Unpaid Today', 'value' => hms_number($summary['unpaid_cases'] ?? 0), 'tone' => 'rose'],
            ['label' => 'Upcoming Today', 'value' => hms_number($summary['upcoming_today'] ?? 0), 'tone' => 'indigo'],
        ];

        $quickLinks = [
            ['label' => 'New Appointment', 'href' => '/modules/user/new_appoint.php'],
            ['label' => 'Reservations', 'href' => '/modules/user/Reservations.php'],
            ['label' => 'Doctors', 'href' => '/modules/user/doc.php'],
            ['label' => 'Medical Records', 'href' => '/includes/med-record.php'],
        ];

        $primaryTitle = 'Recent Front Desk Activity';
        $primaryColumns = ['Patient', 'Doctor', 'Date & Time', 'Status', 'Payment', 'Payment Method'];
        $frontDeskRows = hms_rows($connect, "
            SELECT
                a.apid,
                COALESCE(NULLIF(a.patient_Name, ''), u.fullName, CONCAT('Patient #', a.userId)) AS patient_name,
                COALESCE(d.doctorName, 'Unassigned Doctor') AS doctor_name,
                a.appointmentDate,
                a.appointmentTime,
                a.userStatus,
                a.doctorStatus,
                a.cancelledBy,
                a.consultancyFees,
                a.paid,
                COALESCE(NULLIF(a.method, ''), 'Not recorded') AS method
            FROM appointment a
            LEFT JOIN users u ON u.uid = a.userId
            LEFT JOIN doctors d ON d.id = a.doctorId
            WHERE a.employId = {$sessionId}
              AND {$apptDateExpr} = CURDATE()
            ORDER BY a.postingDate DESC
            LIMIT 10
        ");
        foreach ($frontDeskRows as $row) {
            $primaryRows[] = hms_primary_row([
                "<div class='font-semibold text-slate-900'>" . hms_e($row['patient_name']) . "</div>",
                "<div class='font-medium text-slate-800'>" . hms_e($row['doctor_name']) . "</div>",
                "<div class='text-slate-700'>" . hms_e(hms_datetime_label($row['appointmentDate'], $row['appointmentTime'])) . "</div>",
                hms_status_stack($row),
                hms_payment_stack($row),
                "<div class='text-sm text-slate-700'>" . hms_e($row['method']) . "</div>",
            ]);
        }
        $primaryEmpty = 'No booking activity has been created from this front desk account yet.';

        $doctorAvailabilityRows = hms_rows($connect, "
            SELECT
                d.doctorName,
                COALESCE(NULLIF(d.specilization, ''), 'General') AS specilization,
                COALESCE(d.statue, 0) AS statue,
                COUNT(*) AS today_count
            FROM doctors d
            INNER JOIN appointment a ON a.doctorId = d.id
            WHERE {$apptDateExpr} = CURDATE()
            GROUP BY d.id, d.doctorName, d.specilization, d.statue
            ORDER BY COALESCE(d.statue, 0) DESC, today_count DESC, d.doctorName ASC
            LIMIT 6
        ");
        $doctorAvailabilityPanel = [];
        foreach ($doctorAvailabilityRows as $row) {
            $doctorAvailabilityPanel[] = hms_panel_item($row['doctorName'] ?: 'Unnamed Doctor', $row['specilization'] ?: 'General', 'Today: ' . hms_number($row['today_count']) . ' reservations scheduled.', hms_online_badge($row['statue']), hms_action_link('/modules/user/doc.php', 'Open Doctors', 'slate'));
        }

        $nextHourRows = hms_rows($connect, "
            SELECT
                COALESCE(NULLIF(a.patient_Name, ''), u.fullName, CONCAT('Patient #', a.userId)) AS patient_name,
                COALESCE(d.doctorName, 'Unassigned Doctor') AS doctor_name,
                a.appointmentDate,
                a.appointmentTime
            FROM appointment a
            LEFT JOIN users u ON u.uid = a.userId
            LEFT JOIN doctors d ON d.id = a.doctorId
            WHERE a.employId = {$sessionId}
              AND a.userStatus = 1
              AND a.doctorStatus = 1
              AND {$apptDateTimeExpr} BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 1 HOUR)
            ORDER BY {$apptDateTimeExpr} ASC
            LIMIT 6
        ");
        $nextHourPanel = [];
        foreach ($nextHourRows as $row) {
            $nextHourPanel[] = hms_panel_item($row['patient_name'] ?: 'Patient', $row['doctor_name'] ?: 'Doctor', hms_datetime_label($row['appointmentDate'], $row['appointmentTime']), hms_badge('Next Hour', 'blue'), hms_action_link('/modules/user/Reservations.php', 'Open Reservations', 'blue'));
        }

        $pendingPaymentRows = hms_rows($connect, "
            SELECT
                COALESCE(NULLIF(a.patient_Name, ''), u.fullName, CONCAT('Patient #', a.userId)) AS patient_name,
                COALESCE(d.doctorName, 'Unassigned Doctor') AS doctor_name,
                a.consultancyFees,
                a.appointmentDate,
                a.appointmentTime
            FROM appointment a
            LEFT JOIN users u ON u.uid = a.userId
            LEFT JOIN doctors d ON d.id = a.doctorId
            WHERE a.employId = {$sessionId}
              AND {$apptDateExpr} = CURDATE()
              AND COALESCE(a.paid, 0) = 0
              AND a.userStatus <> 0
              AND a.doctorStatus <> 0
            ORDER BY {$apptDateExpr} ASC, {$apptDateTimeExpr} ASC
            LIMIT 6
        ");
        $pendingPaymentPanel = [];
        foreach ($pendingPaymentRows as $row) {
            $pendingPaymentPanel[] = hms_panel_item($row['patient_name'] ?: 'Patient', $row['doctor_name'] ?: 'Doctor', 'Fee due: ' . hms_money($row['consultancyFees'] ?? 0) . ' | ' . hms_datetime_label($row['appointmentDate'], $row['appointmentTime']), hms_badge('Unpaid', 'amber'), hms_action_link('/modules/user/Reservations.php', 'Follow Up', 'amber'));
        }

        $recentBookingRows = hms_rows($connect, "
            SELECT
                COALESCE(NULLIF(a.patient_Name, ''), u.fullName, CONCAT('Patient #', a.userId)) AS patient_name,
                COALESCE(d.doctorName, 'Unassigned Doctor') AS doctor_name,
                a.postingDate,
                COALESCE(NULLIF(a.method, ''), 'Not recorded') AS method
            FROM appointment a
            LEFT JOIN users u ON u.uid = a.userId
            LEFT JOIN doctors d ON d.id = a.doctorId
            WHERE a.employId = {$sessionId}
              AND DATE(a.postingDate) = CURDATE()
            ORDER BY a.postingDate DESC
            LIMIT 6
        ");
        $recentBookingPanel = [];
        foreach ($recentBookingRows as $row) {
            $recentBookingPanel[] = hms_panel_item('Reservation created', $row['patient_name'] . ' with ' . $row['doctor_name'], 'Method: ' . $row['method'] . ' | ' . hms_datetime_label(substr((string)$row['postingDate'], 0, 10), substr((string)$row['postingDate'], 11, 8)), hms_badge('Recent', 'indigo'));
        }

        $sidePanels = [
            ['title' => 'Doctors Working Today', 'empty' => 'No doctors have reservations scheduled for today.', 'rows' => $doctorAvailabilityPanel],
            ['title' => 'Upcoming Appointments In Next Hour', 'empty' => 'No reservations from your desk start in the next hour.', 'rows' => $nextHourPanel],
            ['title' => 'Pending Payments', 'empty' => 'No pending payments are waiting in your queue.', 'rows' => $pendingPaymentPanel],
            ['title' => 'Recent Booking Actions', 'empty' => 'Recent front desk actions will appear here.', 'rows' => $recentBookingPanel],
        ];
        break;

    case 'Patient':
        // Patient dashboard.
        $pageTone = 'indigo';
        $heroTitle = 'Patient Care Dashboard';

        $summary = hms_row($connect, "
            SELECT
                SUM(CASE WHEN {$apptDateExpr} = CURDATE() THEN 1 ELSE 0 END) AS reservations_count,
                SUM(CASE WHEN {$apptDateExpr} = CURDATE() AND a.userStatus <> 0 AND a.doctorStatus <> 0 THEN 1 ELSE 0 END) AS upcoming_count,
                SUM(CASE WHEN {$apptDateExpr} = CURDATE() AND (a.userStatus = 2 OR a.doctorStatus = 2) AND a.userStatus <> 0 AND a.doctorStatus <> 0 THEN 1 ELSE 0 END) AS completed_visits,
                COUNT(DISTINCT CASE WHEN {$apptDateExpr} = CURDATE() AND a.doctorId IS NOT NULL AND a.doctorId <> 0 THEN a.doctorId END) AS doctors_seen,
                MAX(CASE WHEN {$apptDateExpr} = CURDATE() AND (a.userStatus = 2 OR a.doctorStatus = 2) AND a.userStatus <> 0 AND a.doctorStatus <> 0 THEN {$apptDateExpr} END) AS last_visit,
                SUM(CASE WHEN {$apptDateExpr} = CURDATE() AND a.userStatus <> 0 AND a.doctorStatus <> 0 THEN GREATEST(COALESCE(a.consultancyFees, 0) - COALESCE(a.paid, 0), 0) ELSE 0 END) AS outstanding_payments
            FROM appointment a
            WHERE a.userId = {$sessionUid}
        ");
        $medicalReports = (int)hms_scalar($connect, "
            SELECT COUNT(*)
            FROM tblmedicalhistory mh
            INNER JOIN appointment a ON a.apid = mh.apid
            WHERE mh.UserID = {$sessionUid}
              AND {$apptDateExpr} = CURDATE()
        ", 0);
        $nextAppointment = hms_row($connect, "
            SELECT
                COALESCE(d.doctorName, 'Doctor') AS doctor_name,
                COALESCE(NULLIF(a.doctorSpecialization, ''), d.specilization, '-') AS specialization,
                a.appointmentDate,
                a.appointmentTime
            FROM appointment a
            LEFT JOIN doctors d ON d.id = a.doctorId
            WHERE a.userId = {$sessionUid}
              AND {$apptDateExpr} = CURDATE()
              AND a.userStatus <> 0
              AND a.doctorStatus <> 0
            ORDER BY {$apptDateTimeExpr} ASC
            LIMIT 1
        ");
        $latestReport = hms_row($connect, "
            SELECT
                mh.apid,
                {$medicalHistoryReportField} AS report_text,
                {$medicalHistoryTreatmentField} AS treatment_text,
                {$medicalHistoryDateField} AS report_date,
                COALESCE(d.doctorName, 'Doctor') AS doctor_name,
                COALESCE(NULLIF(a.doctorSpecialization, ''), d.specilization, '-') AS specialization
            FROM tblmedicalhistory mh
            LEFT JOIN appointment a ON a.apid = mh.apid
            LEFT JOIN doctors d ON d.id = a.doctorId
            WHERE mh.UserID = {$sessionUid}
              AND {$apptDateExpr} = CURDATE()
            ORDER BY COALESCE({$medicalHistoryDateField}, {$apptDateExpr}) DESC, mh.ID DESC
            LIMIT 1
        ");

        $heroText = 'A clear patient view of your next appointments, completed visits, medical reports, reminders, and any payments still outstanding.';
        $heroHighlights = [
            ['label' => 'Next Visit', 'value' => $nextAppointment ? hms_datetime_label($nextAppointment['appointmentDate'], $nextAppointment['appointmentTime']) . ' with ' . ($nextAppointment['doctor_name'] ?? 'Doctor') : 'No upcoming appointment is scheduled right now.'],
            ['label' => 'Patient Focus', 'value' => ((float)($summary['outstanding_payments'] ?? 0) > 0) ? 'You still have outstanding payment to close.' : 'No outstanding payment is currently due.'],
        ];

        $nextAppointmentValue = $nextAppointment ? hms_short_date($nextAppointment['appointmentDate']) : 'No visit today';
        $nextAppointmentNote = $nextAppointment ? ($nextAppointment['doctor_name'] . ' at ' . hms_short_time($nextAppointment['appointmentTime'])) : 'No appointment scheduled for today.';
        $lastVisitValue = !empty($summary['last_visit']) ? hms_short_date($summary['last_visit']) : 'No visit today';

        $stats = [
            ['label' => 'Today Appointment', 'value' => $nextAppointmentValue, 'subtext' => $nextAppointmentNote, 'tone' => 'cyan'],
            // ['label' => 'My Reservations Today', 'value' => hms_number($summary['reservations_count'] ?? 0), 'tone' => 'blue'],
            ['label' => 'Completed Today', 'value' => hms_number($summary['completed_visits'] ?? 0), 'tone' => 'emerald'],
            ['label' => 'Reports Today', 'value' => hms_number($medicalReports), 'tone' => 'teal'],
            ['label' => 'Doctors Seen Today', 'value' => hms_number($summary['doctors_seen'] ?? 0), 'tone' => 'indigo'],
            ['label' => 'Last Visit Today', 'value' => $lastVisitValue, 'tone' => 'amber'],
            ['label' => 'Outstanding Today', 'value' => hms_money($summary['outstanding_payments'] ?? 0), 'tone' => 'rose'],
        ];

        $quickLinks = [
            ['label' => 'New Reservation', 'href' => '/modules/patient/New-reservation.php'],
            // ['label' => 'My Reservations', 'href' => '/modules/patient/Reservations.php'],
            ['label' => 'Calendar', 'href' => '/modules/patient/calender.php'],
            ['label' => 'Medical Record', 'href' => '/includes/patient-profile.php?ref=' . urlencode(hms_encrypt_id($sessionUid))],
            ['label' => 'Doctors Time Table', 'href' => '/modules/patient/doc.php'],
            ['label' => 'Profile', 'href' => '/modules/patient/Profile.php'],
        ];

        $primaryTitle = 'My Appointments Today';
        $primaryColumns = ['Doctor', 'Specialization', 'Date', 'Time', 'Status'];
        $patientRows = hms_rows($connect, "
            SELECT
                a.apid,
                COALESCE(d.doctorName, 'Doctor') AS doctor_name,
                COALESCE(NULLIF(a.doctorSpecialization, ''), d.specilization, '-') AS specialization,
                a.appointmentDate,
                a.appointmentTime,
                a.userStatus,
                a.doctorStatus,
                a.cancelledBy
            FROM appointment a
            LEFT JOIN doctors d ON d.id = a.doctorId
            WHERE a.userId = {$sessionUid}
              AND {$apptDateExpr} = CURDATE()
              AND a.userStatus <> 0
              AND a.doctorStatus <> 0
            ORDER BY {$apptDateTimeExpr} ASC
            LIMIT 10
        ");
        foreach ($patientRows as $row) {
            $primaryRows[] = hms_primary_row([
                "<div class='font-semibold text-slate-900'>" . hms_e($row['doctor_name']) . "</div>",
                "<div class='text-slate-700'>" . hms_e($row['specialization']) . "</div>",
                "<div class='text-slate-700'>" . hms_e(hms_short_date($row['appointmentDate'])) . "</div>",
                "<div class='text-slate-700'>" . hms_e(hms_short_time($row['appointmentTime'])) . "</div>",
                hms_status_stack($row),
            ]);
        }
        $primaryEmpty = 'You do not have any upcoming appointments right now.';

        $recentDoctorRows = hms_rows($connect, "
            SELECT
                d.id,
                d.doctorName,
                COALESCE(NULLIF(d.specilization, ''), 'General') AS specilization,
                COALESCE(d.statue, 0) AS statue,
                MAX({$apptDateExpr}) AS last_visit,
                COUNT(*) AS visits_count
            FROM appointment a
            INNER JOIN doctors d ON d.id = a.doctorId
            WHERE a.userId = {$sessionUid}
              AND {$apptDateExpr} = CURDATE()
              AND a.doctorId IS NOT NULL
              AND a.doctorId <> 0
            GROUP BY d.id, d.doctorName, d.specilization, d.statue
            ORDER BY last_visit DESC
            LIMIT 6
        ");
        $recentDoctorPanel = [];
        foreach ($recentDoctorRows as $row) {
            $recentDoctorPanel[] = hms_panel_item($row['doctorName'] ?: 'Doctor', $row['specilization'] ?: 'General', 'Visits: ' . hms_number($row['visits_count']) . ' | Last visit: ' . hms_short_date($row['last_visit']), hms_online_badge($row['statue']));
        }

        $latestReportPanel = [];
        if ($latestReport !== []) {
            $latestReportPanel[] = hms_panel_item($latestReport['doctor_name'] ?: 'Doctor', $latestReport['specialization'] ?: 'Medical Report', 'Treatment: ' . hms_limit_text($latestReport['treatment_text'] ?? '-', 80), hms_badge('Latest Report', 'emerald'), hms_action_link('/modules/patient/report.php?ref=' . urlencode(hms_encrypt_id((int)($latestReport['apid'] ?? 0))), 'View Report', 'emerald'));
            $latestReportPanel[] = hms_panel_item('Report Summary', hms_limit_text($latestReport['report_text'] ?? '-', 110), !empty($latestReport['report_date']) ? 'Recorded on ' . hms_short_date($latestReport['report_date']) : 'Available in your medical record.', '');
        }

        $cancelledCount = (int)hms_scalar($connect, "
            SELECT COUNT(*)
            FROM appointment
            WHERE userId = {$sessionUid}
              AND STR_TO_DATE(appointmentDate, '%Y-%m-%d') = CURDATE()
              AND (userStatus = 0 OR doctorStatus = 0)
        ", 0);
        $notificationsPanel = [];
        if ($nextAppointment !== []) {
            $notificationsPanel[] = hms_panel_item('Upcoming reminder', 'Your next visit is with ' . ($nextAppointment['doctor_name'] ?? 'Doctor'), hms_datetime_label($nextAppointment['appointmentDate'], $nextAppointment['appointmentTime']), hms_badge('Reminder', 'blue'));
        }
        if ((float)($summary['outstanding_payments'] ?? 0) > 0) {
            $notificationsPanel[] = hms_panel_item('Outstanding payment', 'You still have unpaid balance linked to your reservations.', 'Amount due: ' . hms_money($summary['outstanding_payments'] ?? 0), hms_badge('Payment', 'amber'));
        }
        if ($cancelledCount > 0) {
            $notificationsPanel[] = hms_panel_item('Cancelled reservations', hms_number($cancelledCount) . ' reservation(s) were cancelled.', 'Review your reservations list if you need to rebook.', hms_badge('Cancelled', 'rose'), hms_action_link('/modules/patient/Reservations.php', 'Open Reservations', 'slate'));
        }

        $reminderRows = hms_rows($connect, "
            SELECT
                COALESCE(d.doctorName, 'Doctor') AS doctor_name,
                COALESCE(NULLIF(a.doctorSpecialization, ''), d.specilization, '-') AS specialization,
                a.appointmentDate,
                a.appointmentTime
            FROM appointment a
            LEFT JOIN doctors d ON d.id = a.doctorId
            WHERE a.userId = {$sessionUid}
              AND {$apptDateExpr} = CURDATE()
              AND a.userStatus <> 0
              AND a.doctorStatus <> 0
            ORDER BY {$apptDateTimeExpr} ASC
            LIMIT 4
        ");
        $reminderPanel = [];
        foreach ($reminderRows as $row) {
            $reminderPanel[] = hms_panel_item($row['doctor_name'] ?: 'Doctor', $row['specialization'] ?: 'Specialization', hms_datetime_label($row['appointmentDate'], $row['appointmentTime']), hms_badge('Reminder', 'indigo'), hms_action_link('/modules/patient/calender.php', 'Open Calendar', 'indigo'));
        }

        $sidePanels = [
            ['title' => 'Recent Doctors', 'empty' => 'Doctors you visit will appear here.', 'rows' => $recentDoctorPanel],
            ['title' => 'Latest Report', 'empty' => 'No medical report has been added to your profile yet.', 'rows' => $latestReportPanel],
            ['title' => 'Notifications', 'empty' => 'No new patient notifications are waiting right now.', 'rows' => $notificationsPanel],
            ['title' => 'Appointment Reminders', 'empty' => 'Your next appointment reminders will appear here.', 'rows' => $reminderPanel],
        ];
        break;

    default:
        break;
}

$toneMap = [
    'cyan' => 'from-cyan-500/12 to-sky-500/5 border-cyan-200',
    'blue' => 'from-blue-500/12 to-indigo-500/5 border-blue-200',
    'emerald' => 'from-emerald-500/12 to-teal-500/5 border-emerald-200',
    'teal' => 'from-teal-500/12 to-cyan-500/5 border-teal-200',
    'indigo' => 'from-indigo-500/12 to-blue-500/5 border-indigo-200',
    'amber' => 'from-amber-500/12 to-orange-500/5 border-amber-200',
    'rose' => 'from-rose-500/12 to-pink-500/5 border-rose-200',
];
$heroToneMap = [
    'cyan' => 'from-cyan-600 via-sky-600 to-blue-700',
    'blue' => 'from-blue-700 via-indigo-700 to-slate-800',
    'emerald' => 'from-emerald-700 via-teal-700 to-cyan-800',
    'amber' => 'from-amber-600 via-orange-600 to-rose-700',
    'indigo' => 'from-indigo-700 via-blue-700 to-cyan-700',
];
$heroGradient = $heroToneMap[$pageTone] ?? $heroToneMap['cyan'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <link rel="icon" href="/assets/images/echol.png">
    <style>
        body:not(nav) p, body:not(nav) span, body:not(nav) div:not(.no-text), 
        td, th, .stat-card, .article-card, .panel-card {
            color: #1a1a2e !important;
            font-weight: 500 !important;
        }
        
        h1, h2, h3, h4, h5, .font-bold, .font-semibold, th {
            color: #0f172a !important;
            font-weight: 700 !important;
        }
        
        .stats-grid .stat-card,
        .primary-table,
        .insight-panel,
        .kpi-card {
            --text-xs: clamp(0.65rem, 2vw, 0.7rem);
            --text-sm: clamp(0.75rem, 2.5vw, 0.875rem);
            --text-base: clamp(0.875rem, 3vw, 1rem);
            --text-lg: clamp(1rem, 4vw, 1.125rem);
            --text-xl: clamp(1.125rem, 5vw, 1.25rem);
            --text-2xl: clamp(1.25rem, 6vw, 1.5rem);
            --text-3xl: clamp(1.5rem, 7vw, 1.875rem);
            --text-4xl: clamp(1.875rem, 8vw, 2.25rem);
        }
        
        .stats-grid .stat-card .text-xs,
        .stats-grid .stat-card [class*="text-xs"] { font-size: var(--text-xs) !important; }
        .stats-grid .stat-card .text-sm,
        .stats-grid .stat-card [class*="text-sm"] { font-size: var(--text-sm) !important; }
        .stats-grid .stat-card .text-base { font-size: var(--text-base) !important; }
        .stats-grid .stat-card .text-lg { font-size: var(--text-lg) !important; }
        .stats-grid .stat-card .text-xl { font-size: var(--text-xl) !important; }
        .stats-grid .stat-card .text-2xl { font-size: var(--text-2xl) !important; }
        .stats-grid .stat-card .text-3xl { font-size: var(--text-3xl) !important; }
        
        @media (max-width: 768px) {
            .overflow-x-auto {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            table {
                min-width: 500px;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <div class="min-h-full">
        <?php $activePage = 'dashboard'; require_once __DIR__ . '/../includes/nav.php'; ?>

        <!-- Hero section -->
        <header class="relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br <?= hms_e($heroGradient) ?>"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.25),transparent_32%)]"></div>
            <div class="relative mx-auto max-w-7xl px-4 py-4 sm:px-6 sm:py-8 lg:px-8">
                <section class="rounded-2xl border border-white/15 bg-white/10 px-4 py-5 text-white shadow-2xl shadow-slate-900/10 backdrop-blur sm:rounded-[28px] sm:px-6 sm:py-6">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.3em] text-cyan-100 sm:text-xs"><?= hms_e($role) ?></p>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight sm:mt-3 sm:text-4xl"><?= hms_e($heroTitle) ?></h1>
                    <p class="mt-2 max-w-3xl text-xs leading-6 text-sky-50/90 sm:mt-3 sm:text-base"><?= hms_e($heroText) ?></p>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <?php foreach ($quickLinks as $link): ?>
                            <a href="<?= hms_e($link['href']) ?>" class="inline-flex items-center rounded-xl border border-white/20 bg-white/95 px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm transition hover:-translate-y-0.5 hover:bg-white">
                                <?= hms_e($link['label']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        </header>

        <main>
            <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 sm:py-6 lg:px-8">
                <!-- KPI section -->
                <section class="mb-8 grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-4">
                    <?php foreach ($stats as $stat): ?>
                        <?php $tone = $toneMap[$stat['tone'] ?? 'cyan'] ?? $toneMap['cyan']; ?>
                        <article class="rounded-2xl border bg-gradient-to-br <?= hms_e($tone) ?> bg-white p-4 shadow-sm sm:rounded-[26px] sm:p-5">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-500 sm:text-xs"><?= hms_e($stat['label']) ?></p>
                            <p class="mt-2 text-2xl font-bold text-slate-900 sm:mt-3 sm:text-3xl"><?= hms_e((string)$stat['value']) ?></p>
                            <?php if (!empty($stat['subtext'])): ?>
                                <p class="mt-1 text-xs leading-5 text-slate-600 sm:mt-2 sm:text-sm"><?= hms_e($stat['subtext']) ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </section>

                <!-- Main dashboard content -->
                <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.65fr_1fr]">
                    <!-- Primary operational table -->
                    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm sm:rounded-[30px]">
                        <div class="border-b border-slate-100 px-4 py-4 sm:px-6 sm:py-5">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-400 sm:text-xs">Primary Table</p>
                            <h2 class="mt-1 text-lg font-bold text-slate-900 sm:mt-2 sm:text-xl"><?= hms_e($primaryTitle) ?></h2>
                        </div>

                        <div class="p-6">
                            <?php if ($primaryRows === []): ?>
                                <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center">
                                    <p class="text-lg font-semibold text-slate-700">Nothing to show yet</p>
                                    <p class="mt-2 text-sm text-slate-500"><?= hms_e($primaryEmpty) ?></p>
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-slate-200">
                                        <thead class="bg-slate-50">
                                            <tr>
                                                <?php foreach ($primaryColumns as $column): ?>
                                                    <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500"><?= hms_e($column) ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 bg-white">
                                            <?php foreach ($primaryRows as $row): ?>
                                                <tr class="align-top">
                                                    <?php foreach ($row['cells'] as $cell): ?>
                                                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-700"><?= $cell ?></td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>

                    <!-- Secondary insight panels -->
                    <aside class="space-y-6">
                        <?php foreach ($sidePanels as $panel): ?>
                            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm sm:rounded-[30px]">
                                <div class="border-b border-slate-100 px-4 py-4 sm:px-6 sm:py-5">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-400 sm:text-xs">Insight Panel</p>
                                    <h3 class="mt-1 text-base font-bold text-slate-900 sm:mt-2 sm:text-lg"><?= hms_e($panel['title']) ?></h3>
                                </div>

                                <div class="p-4 sm:p-6">
                                    <?php if (($panel['rows'] ?? []) === []): ?>
                                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center sm:rounded-3xl">
                                            <p class="text-sm font-medium text-slate-600"><?= hms_e($panel['empty']) ?></p>
                                        </div>
                                    <?php else: ?>
                                        <div class="space-y-4">
                                            <?php foreach ($panel['rows'] as $item): ?>
                                                <article class="rounded-2xl border border-slate-100 bg-slate-50/85 p-4 sm:rounded-3xl">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div class="min-w-0">
                                                            <p class="font-semibold text-slate-900"><?= hms_e($item['title']) ?></p>
                                                            <?php if (($item['subtitle'] ?? '') !== ''): ?>
                                                                <p class="mt-1 text-sm text-slate-600"><?= hms_e($item['subtitle']) ?></p>
                                                            <?php endif; ?>
                                                            <?php if (($item['meta'] ?? '') !== ''): ?>
                                                                <p class="mt-2 text-xs leading-5 text-slate-500"><?= hms_e($item['meta']) ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if (($item['badge'] ?? '') !== ''): ?>
                                                            <div class="shrink-0"><?= $item['badge'] ?></div>
                                                        <?php endif; ?>
                                                    </div>

                                                    <?php if (($item['action'] ?? '') !== ''): ?>
                                                        <div class="mt-4"><?= $item['action'] ?></div>
                                                    <?php endif; ?>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </aside>
                </section>
            </div>
        </main>
    </div>

    <script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>
