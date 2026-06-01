<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/notification-api.php';

$role = $_SESSION['role'] ?? '';
$activePage = $activePage ?? '';
$displayName = trim((string) ($_SESSION['username'] ?? 'Team Member'));
$roleLabel = $role !== '' ? $role : 'Guest';
$logoutHref = '/includes/logout.php';
$logoHref = '/modules/dashboard.php';

if (!function_exists('hms_nav_item')) {
    function hms_nav_item(string $href, string $label, string $key): array
    {
        return [
            'type' => 'link',
            'href' => $href,
            'label' => $label,
            'key' => $key,
        ];
    }
}

if (!function_exists('hms_nav_dropdown')) {
    function hms_nav_dropdown(string $label, array $items, string $key): array
    {
        return [
            'type' => 'dropdown',
            'label' => $label,
            'items' => $items,
            'key' => $key,
        ];
    }
}

if (!function_exists('hms_nav_initials')) {
    function hms_nav_initials(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'HM';
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            if ($part === '') {
                continue;
            }

            if (function_exists('mb_substr')) {
                $initials .= mb_strtoupper(mb_substr($part, 0, 1));
            } else {
                $initials .= strtoupper(substr($part, 0, 1));
            }
        }

        return $initials !== '' ? $initials : 'HM';
    }
}

if (!function_exists('hms_render_nav_link')) {
    function hms_render_nav_link(array $item, string $activePage, bool $mobile = false): string
    {
        $type = $item['type'] ?? 'link';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        
        if ($type === 'dropdown') {
            $isActive = false;
            $itemsHtml = '';
            foreach ($item['items'] as $subItem) {
                // Check if sub item is active
                $isSubActive = ($activePage === $subItem['key']) || (strpos($requestUri, $subItem['href']) !== false && $subItem['href'] !== '/modules/dashboard.php');
                if ($isSubActive) {
                    $isActive = true;
                }
                
                $subClassName = $mobile ? 'hms-med-mobile-sublink' : 'hms-med-nav-sublink';
                if ($isSubActive) {
                    $subClassName .= ' is-active';
                }
                
                $itemsHtml .= sprintf(
                    "<a href='%s' class='%s'>%s</a>",
                    htmlspecialchars($subItem['href'], ENT_QUOTES, 'UTF-8'),
                    $subClassName,
                    htmlspecialchars($subItem['label'], ENT_QUOTES, 'UTF-8')
                );
            }
            
            if ($mobile) {
                $btnClass = 'hms-med-mobile-link hms-med-mobile-dropdown-btn';
                if ($isActive) $btnClass .= ' is-active';
                $dropdownId = 'mob-drop-' . $item['key'];
                // Expanded by default if active
                $expanded = $isActive ? 'true' : 'false';
                $contentClass = $isActive ? 'hms-med-mobile-dropdown-content is-open' : 'hms-med-mobile-dropdown-content';
                
                return sprintf(
                    "<div class='hms-med-mobile-dropdown'>
                        <button type='button' class='%s' aria-expanded='%s' aria-controls='%s'>
                            <span>%s</span>
                            <svg class='hms-chevron' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'></polyline></svg>
                        </button>
                        <div class='%s' id='%s'>%s</div>
                    </div>",
                    $btnClass,
                    $expanded,
                    $dropdownId,
                    htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'),
                    $contentClass,
                    $dropdownId,
                    $itemsHtml
                );
            } else {
                $btnClass = 'hms-med-nav-link hms-med-nav-dropdown-btn';
                if ($isActive) $btnClass .= ' is-active';
                return sprintf(
                    "<div class='hms-med-nav-dropdown'>
                        <button type='button' class='%s' aria-expanded='false'>
                            <span>%s</span>
                            <svg class='hms-chevron' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'></polyline></svg>
                        </button>
                        <div class='hms-med-nav-dropdown-content'>%s</div>
                    </div>",
                    $btnClass,
                    htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'),
                    $itemsHtml
                );
            }
        }

        $isActive = ($activePage === $item['key']) || (strpos($requestUri, $item['href']) !== false && $item['href'] !== '/modules/dashboard.php');
        $className = $mobile ? 'hms-med-mobile-link' : 'hms-med-nav-link';
        if ($isActive) {
            $className .= ' is-active';
        }

        $ariaCurrent = $isActive ? " aria-current='page'" : '';

        return sprintf(
            "<a href='%s' class='%s'%s>%s</a>",
            htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'),
            $className,
            $ariaCurrent,
            htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8')
        );
    }
}

$links = [];

switch ($role) {
    case 'Admin':
        $links = [
            hms_nav_item('/modules/dashboard.php', 'Dashboard', 'dashboard'),
            hms_nav_item('/modules/admin/admin-Reservations.php', 'Reservations', 'reservations'),
            hms_nav_item('/includes/med-record.php', 'Medical Records', 'medical-record'),
            hms_nav_dropdown('Management', [
                hms_nav_item('/modules/admin/admin-user-log.php', 'Users', 'users'),
                hms_nav_item('/modules/admin/Add-user.php', 'Add User', 'add-user'),
                hms_nav_item('/modules/admin/doc.php', 'Doctors', 'doctors'),
                hms_nav_item('/modules/admin/Manage-specializations.php', 'Specializations', 'manage-specializations'),
                hms_nav_item('/modules/shared/doc-timetable.php', 'Timetable', 'time-table'),
                hms_nav_item('/includes/audit-log.php', 'Audit Log', 'audit-log'),
            ], 'management'),
            hms_nav_dropdown('Operations', [
                hms_nav_item('/modules/admin/admin-Payments.php', 'Payments', 'payments'),
                hms_nav_item('/modules/shared/queue-screen.php', 'Queue Board', 'queue-board'),
            ], 'operations'),
        ];
        break;

    case 'System Admin':
        $links = [
            hms_nav_item('/modules/dashboard.php', 'Dashboard', 'dashboard'),
            hms_nav_item('/modules/super-admin/super-Reservations.php', 'Reservations', 'reservations'),
            hms_nav_item('/includes/med-record.php', 'Medical Records', 'medical-record'),
            hms_nav_dropdown('Management', [
                hms_nav_item('/modules/super-admin/super-user-log.php', 'Users', 'users'),
                hms_nav_item('/modules/super-admin/Add-user.php', 'Add User', 'add-user'),
                hms_nav_item('/modules/super-admin/doc.php', 'Doctors', 'doctors'),
                hms_nav_item('/modules/super-admin/Add-Doctor.php', 'Add Doctor', 'add-doctor'),
                hms_nav_item('/modules/super-admin/Add-specilization.php', 'Specializations', 'manage-specializations'),
                hms_nav_item('/modules/shared/doc-timetable.php', 'Timetable', 'time-table'),
                hms_nav_item('/includes/audit-log.php', 'Audit Log', 'audit-log'),
            ], 'management'),
            hms_nav_dropdown('Operations', [
                hms_nav_item('/modules/super-admin/super-Payments.php', 'Payments', 'payments'),
                hms_nav_item('/modules/shared/queue-screen.php', 'Queue Board', 'queue-board'),
            ], 'operations'),
        ];
        break;

    case 'Doctor':
        $links = [
            hms_nav_item('/modules/dashboard.php', 'Dashboard', 'dashboard'),
            hms_nav_item('/modules/doctor/schedule.php', 'My Schedule', 'my-schedule'),
            hms_nav_item('/modules/doctor/doc-Reservations.php', 'Reservations', 'reservations'),
            hms_nav_item('/includes/med-record.php', 'Medical Records', 'medical-record'),
            hms_nav_item('/modules/doctor/doc-write.php', 'Write Report', 'write-report'),
        ];
        break;

    case 'User':
        $links = [
            hms_nav_item('/modules/dashboard.php', 'Dashboard', 'dashboard'),
            hms_nav_item('/modules/user/new_appoint.php', 'New Reservation', 'new-reservation'),
            hms_nav_item('/modules/user/Reservations.php', 'Reservations', 'reservations'),
            hms_nav_item('/includes/med-record.php', 'Medical Records', 'medical-record'),
            hms_nav_item('/modules/shared/doc-timetable.php', 'Doctors Time Table', 'time-table'),
        ];
        break;

    case 'Patient':
        $links = [
            hms_nav_item('/modules/dashboard.php', 'Dashboard', 'dashboard'),
            hms_nav_item('/modules/patient/New-reservation.php', 'New Reservation', 'new-reservation'),
            hms_nav_item('/modules/patient/calender.php', 'Calendar', 'calendar'),
            hms_nav_item('/includes/med-record.php', 'Medical Records', 'medical-record'),
            hms_nav_item('/modules/shared/doc-timetable.php', 'Doctors Time Table', 'time-table'),
            hms_nav_item('/modules/patient/chatbot.php', 'AI Assistant', 'ai-assistant'),
            hms_nav_item('/modules/shared/queue-public.php', 'Live Queue', 'live-queue'),
        ];
        break;
}

$desktopLinks = array_map(fn($item) => hms_render_nav_link($item, $activePage, false), $links);
$mobileLinks = array_map(fn($item) => hms_render_nav_link($item, $activePage, true), $links);
$userInitials = hms_nav_initials($displayName);

// Notification badge count
$_hmsNotifRecipient = hms_get_notification_recipient();
$_hmsNotifConn = hms_db_connect(false);
$_hmsUnreadCount = 0;
if ($_hmsNotifConn && !$_hmsNotifConn->connect_error) {
    $_hmsUnreadCount = hms_get_unread_count($_hmsNotifConn, $_hmsNotifRecipient['type'], $_hmsNotifRecipient['id']);
    $_hmsNotifConn->close();
}

if (!defined('HMS_NAV_ASSETS')) {
    define('HMS_NAV_ASSETS', true);
    ?>
    <style>
        .hms-med-nav-shell {
            position: sticky;
            top: 0;
            z-index: 50;
            padding-top: 8px;
            background: linear-gradient(180deg, rgba(241, 245, 249, 0.96), rgba(241, 245, 249, 0.72), rgba(241, 245, 249, 0));
            backdrop-filter: blur(8px);
            width: 100%;
            max-width: 100vw;
        }

        .hms-med-nav-card {
            border: 1px solid #dbeafe;
            border-radius: 22px;
            background:
                radial-gradient(circle at top right, rgba(14, 165, 233, 0.1), transparent 28%),
                linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
            overflow: visible;
            width: 100%;
        }

        .hms-med-top {
            display: none;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.7rem 1rem;
        }

        .hms-med-brand-wrap {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 0;
        }

        .hms-med-brand-mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border-radius: 15px;
            border: 1px solid #bfdbfe;
            background: linear-gradient(135deg, #eff6ff, #ffffff);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
            flex: 0 0 auto;
        }

        .hms-med-brand-mark img {
            width: 34px;
            height: 34px;
            object-fit: contain;
        }

        .hms-med-brand-title {
            display: inline-block;
            color: #0f172a;
            font-size: 1rem;
            font-weight: 800;
            letter-spacing: 0.01em;
            text-decoration: none;
        }

        .hms-med-brand-subtitle {
            margin-top: 0.08rem;
            color: #475569;
            font-size: 0.78rem;
            line-height: 1.3;
        }

        .hms-med-role {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            margin-top: 0.28rem;
            padding: 0.24rem 0.62rem;
            border-radius: 999px;
            background: #ecfeff;
            border: 1px solid #bae6fd;
            color: #0f766e;
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .hms-med-role::before {
            content: '';
            width: 0.55rem;
            height: 0.55rem;
            border-radius: 999px;
            background: #14b8a6;
            box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.12);
        }

        .hms-med-side {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            flex: 0 0 auto;
        }

        .hms-med-top-role {
            display: inline-flex;
        }

        .hms-med-desktop-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 0;
        }

        .hms-med-desktop-brand-copy {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 0;
        }

        .hms-med-desktop-title {
            color: #0f172a;
            font-size: 0.98rem;
            font-weight: 800;
            text-decoration: none;
            white-space: nowrap;
        }

        .hms-med-desktop-actions {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            flex: 0 0 auto;
            padding-top: 0.15rem;
            min-width: 200px;
            justify-content: flex-end;
        }

        .hms-med-user {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.4rem 0.6rem;
            border: 1px solid #dbeafe;
            background: #f8fbff;
            border-radius: 15px;
            cursor: pointer;
            transition: background 0.2s ease, border-color 0.2s ease;
            text-decoration: none;
        }
        .hms-med-user:hover {
            background: #eff6ff;
            border-color: #bfdbfe;
        }

        .hms-med-avatar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.85rem;
            height: 1.85rem;
            border-radius: 999px;
            background: linear-gradient(135deg, #0ea5e9, #14b8a6);
            color: #ffffff;
            font-size: 0.72rem;
            font-weight: 800;
            flex: 0 0 auto;
        }

        .hms-med-user-name {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #0f172a;
            font-size: 0.84rem;
            font-weight: 700;
        }

        .hms-med-user-role {
            color: #64748b;
            font-size: 0.72rem;
        }

        .hms-med-logout {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0.62rem 0.88rem;
            border-radius: 14px;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: #ffffff;
            font-size: 0.82rem;
            font-weight: 800;
            text-decoration: none;
            transition: background 0.18s ease, transform 0.18s ease;
        }

        .hms-med-logout:hover {
            background: linear-gradient(135deg, #3b82f6, #60a5fa);
            color: #ffffff;
            transform: translateY(-1px);
        }

        .hms-med-mobile-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            border: 1px solid #bfdbfe;
            background: #f8fbff;
            color: #0f172a;
        }

        .hms-med-links-row {
            border-top: 1px solid #e0f2fe;
            padding: 0.7rem 1rem 0.8rem;
        }

        .hms-med-links-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            width: 100%;
            transition: all 0.3s ease;
        }

        .hms-med-links-spacer {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            min-width: 120px;
        }

        .hms-med-links-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: center;
            flex: 1 1 auto;
            min-width: 0;
            padding: 0.25rem 0;
        }

        .hms-med-nav-link,
        .hms-med-mobile-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 0.5rem 0.7rem;
            border-radius: 13px;
            border: 1px solid #dbeafe;
            background: #ffffff;
            color: #2563eb;
            font-size: 0.78rem;
            font-weight: 700;
            text-decoration: none;
            transition: background 0.18s ease, border-color 0.18s ease, color 0.18s ease, transform 0.18s ease;
            white-space: nowrap;
        }

        .hms-med-nav-link:hover,
        .hms-med-mobile-link:hover {
            background: #dbeafe;
            border-color: #60a5fa;
            color: #1e3a8a;
            transform: translateY(-1px);
        }

        .hms-med-nav-link.is-active,
        .hms-med-mobile-link.is-active {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            border-color: transparent;
            color: #ffffff;
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.25);
        }

        .hms-med-mobile-menu {
            display: none;
            border-top: 1px solid #e0f2fe;
            padding: 0.9rem 1rem 1rem;
            background: #ffffff;
        }

        .hms-med-mobile-stack {
            display: grid;
            gap: 0.6rem;
        }

        .hms-med-mobile-link {
            width: 100%;
            justify-content: flex-start;
            text-align: left;
        }

        .hms-med-mobile-user {
            display: grid;
            gap: 0.65rem;
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid #e2e8f0;
        }

        .hms-med-mobile-user .hms-med-user {
            width: 100%;
        }

        .hms-med-mobile-user .hms-med-logout {
            width: 100%;
        }

        @media (max-width: 767px) {
            .hms-med-nav-shell {
                padding-top: 6px;
            }

            .hms-med-top {
                display: flex;
                width: 100%;
            }

            .hms-med-nav-card {
                border-radius: 18px;
                width: 100%;
            }

            .hms-med-top {
                padding: 0.8rem 0.9rem;
            }

            .hms-med-brand-mark {
                width: 44px;
                height: 44px;
                border-radius: 14px;
            }

            .hms-med-brand-mark img {
                width: 30px;
                height: 30px;
            }

            .hms-med-brand-title {
                font-size: 0.94rem;
            }

            .hms-med-brand-subtitle {
                display: none;
            }

            .hms-med-top-role {
                display: none;
            }

            .hms-med-desktop-brand {
                display: none;
            }

            .hms-med-desktop-actions {
                display: none;
            }

            .hms-med-mobile-toggle {
                display: inline-flex;
                flex-shrink: 0;
            }

            .hms-med-links-row {
                display: none;
            }

            .hms-med-mobile-menu.is-open {
                display: block;
            }
        }

        @media (min-width: 768px) and (max-width: 1200px) {
            .hms-med-links-bar {
                flex-direction: column;
                gap: 0.8rem;
                padding: 0.5rem 0;
            }
            .hms-med-links-spacer {
                width: 100%;
                justify-content: center;
                border-bottom: 1px solid #f1f5f9;
                padding-bottom: 0.5rem;
            }
            .hms-med-desktop-actions {
                width: 100%;
                justify-content: center;
                border-top: 1px solid #f1f5f9;
                padding-top: 0.5rem;
            }
            .hms-med-links-grid {
                width: 100%;
            }
            .hms-med-nav-link {
                padding: 0.4rem 0.6rem;
                font-size: 0.72rem;
            }
        }

        @media (min-width: 1201px) and (max-width: 1440px) {
            .hms-med-nav-link {
                padding: 0.45rem 0.65rem;
                font-size: 0.76rem;
            }
            .hms-med-links-spacer {
                min-width: 140px;
            }
            .hms-med-desktop-title {
                font-size: 1.4rem !important;
            }
            .hms-med-desktop-brand-copy img {
                width: 32px !important;
                height: 32px !important;
            }
        }

        /* Language Switcher */
        .hms-lang-switch {
            display: inline-flex;
            align-items: center;
            background: #f1f5f9;
            border-radius: 12px;
            padding: 0.2rem;
            border: 1px solid #e2e8f0;
        }
        .hms-lang-btn {
            padding: 0.35rem 0.6rem;
            font-size: 0.75rem;
            font-weight: 700;
            color: #64748b;
            background: transparent;
            border: none;
            border-radius: 9px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .hms-lang-btn.is-active {
            background: #ffffff;
            color: #0f172a;
            box-shadow: 0 2px 4px rgba(15, 23, 42, 0.05);
        }

        /* Notifications */
        .hms-notif-btn {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            border: 1px solid #dbeafe;
            background: #f8fbff;
            color: #64748b;
            text-decoration: none;
            transition: all 0.2s;
        }
        .hms-notif-btn:hover {
            color: #0f172a;
            border-color: #bfdbfe;
            background: #eff6ff;
        }
        .hms-notif-badge {
            position: absolute;
            top: -4px;
            inset-inline-end: -4px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 999px;
            background: linear-gradient(135deg, #ef4444, #f97316);
            color: white;
            font-size: 0.68rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.4);
        }

        /* Nav Dropdown Desktop */
        .hms-med-nav-dropdown {
            position: relative;
            display: inline-block;
        }
        .hms-med-nav-dropdown-btn {
            cursor: pointer;
            background: transparent;
        }
        .hms-med-nav-dropdown-btn .hms-chevron {
            transition: transform 0.2s ease;
        }
        .hms-med-nav-dropdown:hover .hms-chevron,
        .hms-med-nav-dropdown.is-open .hms-chevron {
            transform: rotate(180deg);
        }
        .hms-med-nav-dropdown-content {
            display: none;
            position: absolute;
            top: calc(100% + 0.5rem);
            inset-inline-start: 50%;
            transform: translateX(-50%);
            min-width: 220px;
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.1);
            padding: 0.5rem;
            z-index: 100;
        }
        .hms-med-nav-dropdown-content::before {
            content: '';
            position: absolute;
            top: -0.5rem;
            left: 0;
            width: 100%;
            height: 0.5rem;
        }
        html[dir="rtl"] .hms-med-nav-dropdown-content {
            transform: translateX(50%);
        }
        .hms-med-nav-dropdown:hover .hms-med-nav-dropdown-content,
        .hms-med-nav-dropdown.is-open .hms-med-nav-dropdown-content {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }
        .hms-med-nav-sublink {
            display: flex;
            align-items: center;
            padding: 0.6rem 0.8rem;
            border-radius: 10px;
            color: #475569;
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.15s ease, color 0.15s ease;
        }
        .hms-med-nav-sublink:hover, .hms-med-nav-sublink.is-active {
            background: #f1f5f9;
            color: #0f172a;
        }
        .hms-med-nav-sublink.is-active {
            background: #eff6ff;
            color: #2563eb;
        }

        /* Nav Dropdown Mobile */
        .hms-med-mobile-dropdown {
            display: flex;
            flex-direction: column;
            width: 100%;
        }
        .hms-med-mobile-dropdown-btn {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            cursor: pointer;
        }
        .hms-med-mobile-dropdown-btn .hms-chevron {
            transition: transform 0.2s ease;
        }
        .hms-med-mobile-dropdown-btn[aria-expanded="true"] .hms-chevron {
            transform: rotate(180deg);
        }
        .hms-med-mobile-dropdown-content {
            display: none;
            flex-direction: column;
            padding: 0.5rem 0.5rem 0 1.5rem;
            gap: 0.4rem;
        }
        html[dir="rtl"] .hms-med-mobile-dropdown-content {
            padding: 0.5rem 1.5rem 0 0.5rem;
        }
        .hms-med-mobile-dropdown-content.is-open {
            display: flex;
        }
        .hms-med-mobile-sublink {
            display: block;
            padding: 0.5rem 0.8rem;
            border-radius: 10px;
            color: #475569;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
        }
        .hms-med-mobile-sublink.is-active {
            color: #2563eb;
            background: #eff6ff;
        }

        /* Profile Dropdown */
        .hms-med-profile-dropdown {
            position: relative;
        }
        .hms-med-profile-dropdown-content {
            display: none;
            position: absolute;
            top: calc(100% + 0.5rem);
            inset-inline-end: 0;
            min-width: 200px;
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.1);
            padding: 0.5rem;
            z-index: 100;
        }
        .hms-med-profile-dropdown-content::before {
            content: '';
            position: absolute;
            top: -0.5rem;
            left: 0;
            width: 100%;
            height: 0.5rem;
        }
        .hms-med-profile-dropdown:hover .hms-med-profile-dropdown-content,
        .hms-med-profile-dropdown.is-open .hms-med-profile-dropdown-content {
            display: flex;
            flex-direction: column;
        }
        .hms-med-profile-dropdown-content a {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 0.8rem;
            border-radius: 10px;
            color: #475569;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
        }
        .hms-med-profile-dropdown-content a:hover {
            background: #f1f5f9;
            color: #0f172a;
        }
        .hms-med-profile-dropdown-content a.hms-logout-link {
            color: #ef4444;
        }
        .hms-med-profile-dropdown-content a.hms-logout-link:hover {
            background: #fef2f2;
        }
        .hms-dropdown-divider {
            height: 1px;
            background: #e2e8f0;
            margin: 0.3rem 0;
        }
    </style>
    <?php
}
?>

<nav class="hms-med-nav-shell" data-med-nav>
    <div class="w-full px-3 sm:px-5 lg:px-8">
        <div class="hms-med-nav-card">
            <div class="hms-med-top">
                <div class="hms-med-brand-wrap">
                    <a href="<?= htmlspecialchars($logoHref, ENT_QUOTES, 'UTF-8') ?>" class="hms-med-brand-mark">
                        <img src="/assets/images/echol.png" alt="Echo HMS">
                    </a>

                    <div class="min-w-0">
                        <a href="<?= htmlspecialchars($logoHref, ENT_QUOTES, 'UTF-8') ?>"
                            class="hms-med-brand-title">Echo HMS</a>
                        <div class="hms-med-brand-subtitle">Clean medical workflow for appointments, records, and team
                            coordination.</div>
                    </div>
                </div>

                <div class="hms-med-side">
                    <button type="button" class="hms-med-mobile-toggle" data-med-nav-toggle aria-expanded="false"
                        aria-controls="hms-med-mobile-menu">
                        <span class="sr-only">Open navigation</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="hms-med-links-row">
                <div class="hms-med-links-bar">
                    <div class="hms-med-links-spacer">
                        <div class="hms-med-desktop-brand">
                            <div class="hms-med-desktop-brand-copy">
                                <img src="/assets/images/echol.png" 
                                     alt="Echo HMS Logo" 
                                     style="width: 40px; height: 40px; object-fit: contain;">
                                <a href="<?= htmlspecialchars($logoHref, ENT_QUOTES, 'UTF-8') ?>"
                                    class="hms-med-desktop-title">Echo HMS</a>
                            </div>
                        </div>
                    </div>

                    <div class="hms-med-links-grid">
                        <?= implode('', $desktopLinks) ?>
                    </div>

                    <div class="hms-med-desktop-actions">


                        <!-- Notification -->
                        <a href="/includes/notifications.php" class="hms-notif-btn" title="Notifications">
                            <i class="bi <?= $_hmsUnreadCount > 0 ? 'bi-bell-fill' : 'bi-bell' ?>" style="font-size:1.1rem;"></i>
                            <?php if ($_hmsUnreadCount > 0): ?>
                            <span class="hms-notif-badge"><?= $_hmsUnreadCount > 99 ? '99+' : $_hmsUnreadCount ?></span>
                            <?php endif; ?>
                        </a>

                        <!-- Profile Dropdown -->
                        <?php $profileUrl = ($role === 'Patient') ? '/modules/patient/Profile.php' : '/modules/shared/profile.php'; ?>
                        <div class="hms-med-profile-dropdown" data-profile-dropdown>
                            <button type="button" class="hms-med-user" aria-expanded="false">
                                <span class="hms-med-avatar"><?= htmlspecialchars($userInitials, ENT_QUOTES, 'UTF-8') ?></span>
                                <div style="text-align: start;">
                                    <div class="hms-med-user-name"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="hms-med-user-role"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-inline-start: 4px;"><polyline points="6 9 12 15 18 9"></polyline></svg>
                            </button>
                            <div class="hms-med-profile-dropdown-content">
                                <a href="<?= htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') ?>">Profile</a>
                                <a href="#">Settings</a>
                                <div class="hms-dropdown-divider"></div>
                                <a href="<?= htmlspecialchars($logoutHref, ENT_QUOTES, 'UTF-8') ?>" class="hms-logout-link">Log Out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="hms-med-mobile-menu" class="hms-med-mobile-menu" data-med-nav-menu>
                <div class="hms-med-mobile-stack">
                    <?= implode('', $mobileLinks) ?>
                </div>

                <div class="hms-med-mobile-user">

                    <a href="<?= htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') ?>" class="hms-med-user" style="width: 100%;">
                        <span class="hms-med-avatar"><?= htmlspecialchars($userInitials, ENT_QUOTES, 'UTF-8') ?></span>
                        <div style="text-align: start;">
                            <div class="hms-med-user-name"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="hms-med-user-role"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </a>

                    <a href="<?= htmlspecialchars($logoutHref, ENT_QUOTES, 'UTF-8') ?>" class="hms-med-logout" style="width: 100%;">Log Out</a>
                </div>
            </div>
        </div>
    </div>
</nav>

<?php if (!defined('HMS_NAV_SCRIPT')):
    define('HMS_NAV_SCRIPT', true); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-med-nav]').forEach(function (nav) {
                const toggle = nav.querySelector('[data-med-nav-toggle]');
                const menu = nav.querySelector('[data-med-nav-menu]');
                if (!toggle || !menu || toggle.dataset.bound === '1') {
                    return;
                }

                toggle.dataset.bound = '1';
                toggle.addEventListener('click', function () {
                    const isOpen = menu.classList.contains('is-open');
                    menu.classList.toggle('is-open', !isOpen);
                    toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
                });
            });

            // Desktop Dropdowns
            document.querySelectorAll('.hms-med-nav-dropdown, .hms-med-profile-dropdown').forEach(dropdown => {
                const btn = dropdown.querySelector('.hms-med-nav-dropdown-btn, .hms-med-user');
                if (btn) {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        // Close others
                        document.querySelectorAll('.hms-med-nav-dropdown.is-open, .hms-med-profile-dropdown.is-open').forEach(el => {
                            if (el !== dropdown) el.classList.remove('is-open');
                        });
                        dropdown.classList.toggle('is-open');
                    });
                }
                
                dropdown.addEventListener('mouseenter', () => {
                    document.querySelectorAll('.hms-med-nav-dropdown.is-open, .hms-med-profile-dropdown.is-open').forEach(el => {
                        if (el !== dropdown) el.classList.remove('is-open');
                    });
                });

                dropdown.addEventListener('mouseleave', () => {
                    dropdown.classList.remove('is-open');
                });
            });

            // Mobile Accordions
            document.querySelectorAll('.hms-med-mobile-dropdown').forEach(dropdown => {
                const btn = dropdown.querySelector('.hms-med-mobile-dropdown-btn');
                const content = dropdown.querySelector('.hms-med-mobile-dropdown-content');
                btn.addEventListener('click', () => {
                    const isOpen = content.classList.contains('is-open');
                    content.classList.toggle('is-open', !isOpen);
                    btn.setAttribute('aria-expanded', !isOpen);
                });
            });

            // Close dropdowns when clicking outside
            document.addEventListener('click', () => {
                document.querySelectorAll('.hms-med-nav-dropdown.is-open, .hms-med-profile-dropdown.is-open').forEach(el => {
                    el.classList.remove('is-open');
                });
            });

            // Global logout handler to clear chat history
            document.querySelectorAll('.hms-med-logout, .hms-logout-link').forEach(btn => {
                btn.addEventListener('click', function() {
                    sessionStorage.removeItem('hms_chat_history');
                });
            });

            // Mobile App Notification Polling
            if (window.FlutterNotificationChannel) {
                let lastNotifiedIds = [];
                setInterval(() => {
                    fetch('/api-poll-notifications.php')
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success' && data.notifications) {
                                data.notifications.forEach(notif => {
                                    if (!lastNotifiedIds.includes(notif.id)) {
                                        lastNotifiedIds.push(notif.id);
                                        if (lastNotifiedIds.length > 20) {
                                            lastNotifiedIds.shift();
                                        }
                                        
                                        window.FlutterNotificationChannel.postMessage(JSON.stringify({
                                            title: notif.title,
                                            body: notif.message,
                                            id: notif.id
                                        }));
                                    }
                                });
                            }
                        })
                        .catch(err => console.error('Error polling notifications:', err));
                }, 10000); // 10 seconds
            }
        });
    </script>

    <!-- Global Page Loader -->
    <link rel="stylesheet" href="/assets/css/loader.css">
    <div id="global-loader" class="hidden">
      <div class="loadingspinner">
        <div id="square1"></div>
        <div id="square2"></div>
        <div id="square3"></div>
        <div id="square4"></div>
        <div id="square5"></div>
      </div>
    </div>
    
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        const loader = document.getElementById('global-loader');
        
        // Add Global Footer
        const footerHTML = `
            <footer style="margin-top: auto; padding: 1.5rem; text-align: center; background-color: #f8fafc; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 0.9rem; font-family: 'Tajawal', sans-serif; width: 100%; z-index: 10;">
                <p style="margin: 0; display: flex; align-items: center; justify-content: center; gap: 6px;">
                    جميع الحقوق محفوظة &copy; ${new Date().getFullYear()} 
                    <a href="/echo-team.php" style="color: #4f46e5; font-weight: 800; text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='#4338ca'" onmouseout="this.style.color='#4f46e5'">
                        فريق ايكو تيم (Echo Team)
                    </a>
                    <i class="bi bi-heart-fill text-rose-500 text-sm"></i>
                </p>
            </footer>
        `;
        
        const wrapper = document.querySelector('.min-h-full') || document.body;
        if (wrapper !== document.body) {
            wrapper.style.display = 'flex';
            wrapper.style.flexDirection = 'column';
            wrapper.style.minHeight = '100vh';
        }
        wrapper.insertAdjacentHTML('beforeend', footerHTML);
        
        // Intercept link clicks
        document.querySelectorAll('a').forEach(anchor => {
          anchor.addEventListener('click', function(e) {
            if (this.target === '_blank' || 
                this.hasAttribute('download') || 
                this.href.startsWith('javascript:') || 
                this.href.includes('#') ||
                this.getAttribute('onclick')) return;
            
            if (this.href && this.href !== window.location.href) {
                loader.classList.remove('hidden');
            }
          });
        });

        // Intercept form submissions
        document.querySelectorAll('form').forEach(form => {
          form.addEventListener('submit', function(e) {
            if (this.target === '_blank') return;
            // if form is valid (if there's HTML5 validation)
            if (this.checkValidity()) {
                loader.classList.remove('hidden');
            }
          });
        });

        // Hide loader if page is shown from back/forward cache
        window.addEventListener('pageshow', function(e) {
          loader.classList.add('hidden');
        });
      });
    </script>
<?php endif; ?>
