<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/notification-api.php';

$conn = hms_db_connect();
$conn->set_charset("utf8mb4");

$recipient = hms_get_notification_recipient();
$recipientType = $recipient['type'];
$recipientId   = $recipient['id'];

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    hms_require_csrf('/includes/notifications.php');

    $postAction = $_POST['notif_action'] ?? '';
    
    if ($postAction === 'mark_read') {
        $nid = (int)($_POST['notif_id'] ?? 0);
        if ($nid > 0) {
            hms_mark_notification_read($conn, $nid, $recipientType, $recipientId);
        }
    } elseif ($postAction === 'mark_all_read') {
        hms_mark_all_read($conn, $recipientType, $recipientId);
    }
    
    // Redirect to avoid resubmission
    header("Location: /includes/notifications.php");
    exit;
}

// Fetch notifications
$filter = $_GET['filter'] ?? 'all';
$notifications = hms_get_notifications($conn, $recipientType, $recipientId, 100, 0);
$unreadCount = hms_get_unread_count($conn, $recipientType, $recipientId);

// Filter
if ($filter !== 'all') {
    $notifications = array_filter($notifications, function($n) use ($filter) {
        if ($filter === 'unread') return (int)$n['is_read'] === 0;
        return $n['type'] === $filter;
    });
    $notifications = array_values($notifications);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — Echo HMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" href="/assets/images/l-gh.png">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <style>
        .notif-page { background: #f1f5f9; min-height: 100vh; }

        .notif-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        .notif-header {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .notif-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notif-title i { color: #0ea5e9; }

        .notif-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            height: 24px;
            padding: 0 7px;
            border-radius: 999px;
            background: linear-gradient(135deg, #ef4444, #f97316);
            color: white;
            font-size: 0.75rem;
            font-weight: 800;
        }

        .notif-filters {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
        }

        .notif-filter-btn {
            padding: 0.35rem 0.75rem;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            background: white;
            color: #64748b;
            font-size: 0.78rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .notif-filter-btn:hover { border-color: #93c5fd; color: #0ea5e9; }

        .notif-filter-btn.active {
            background: linear-gradient(135deg, #0ea5e9, #14b8a6);
            color: white;
            border-color: transparent;
        }

        .notif-actions { display: flex; gap: 0.5rem; }

        .notif-mark-all {
            padding: 0.4rem 0.9rem;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #475569;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .notif-mark-all:hover { background: #e0f2fe; color: #0ea5e9; }

        .notif-list { padding: 0; }

        .notif-item {
            display: flex;
            gap: 1rem;
            padding: 1.1rem 1.5rem;
            border-bottom: 1px solid #f8fafc;
            transition: background 0.2s;
            cursor: default;
        }

        .notif-item:hover { background: #fafbfd; }

        .notif-item.unread {
            background: #f0f9ff;
            border-left: 4px solid #0ea5e9;
        }

        .notif-item.unread:hover { background: #e0f2fe; }

        .notif-icon-wrap {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.1rem;
        }

        .notif-icon-schedule { background: #dbeafe; color: #2563eb; }
        .notif-icon-appointment { background: #dcfce7; color: #16a34a; }
        .notif-icon-cancellation { background: #fef2f2; color: #dc2626; }
        .notif-icon-system { background: #f3e8ff; color: #7c3aed; }

        .notif-content { flex: 1; min-width: 0; }

        .notif-content-title {
            font-weight: 700;
            font-size: 0.9rem;
            color: #0f172a;
            margin-bottom: 0.2rem;
        }

        .notif-content-msg {
            font-size: 0.82rem;
            color: #475569;
            line-height: 1.45;
        }

        .notif-time {
            font-size: 0.72rem;
            color: #94a3b8;
            margin-top: 0.3rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .notif-read-btn {
            align-self: center;
            padding: 0.3rem 0.6rem;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: white;
            color: #94a3b8;
            font-size: 0.72rem;
            cursor: pointer;
            transition: all 0.2s;
            flex-shrink: 0;
        }

        .notif-read-btn:hover { background: #0ea5e9; color: white; border-color: transparent; }

        .notif-empty {
            text-align: center;
            padding: 3rem 1rem;
            color: #94a3b8;
        }

        .notif-empty i { font-size: 2.5rem; display: block; margin-bottom: 0.5rem; }
    </style>
</head>
<body class="notif-page">
    <div class="min-h-full">
        <?php $activePage = 'notifications'; require_once __DIR__ . '/nav.php'; ?>

        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">
                    <i class="bi bi-bell" style="color: #0ea5e9;"></i> Notifications
                </h1>
            </div>
        </header>

        <main>
            <div class="mx-auto max-w-4xl py-6 px-4 sm:px-6 lg:px-8">
                <div class="notif-card">
                    <div class="notif-header">
                        <div style="display:flex;align-items:center;gap:1rem;">
                            <div class="notif-title">
                                <i class="bi bi-bell-fill"></i>
                                All Notifications
                                <?php if ($unreadCount > 0): ?>
                                    <span class="notif-badge"><?= $unreadCount ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="notif-filters">
                                <a href="?filter=all" class="notif-filter-btn <?= $filter === 'all' ? 'active' : '' ?>">All</a>
                                <a href="?filter=unread" class="notif-filter-btn <?= $filter === 'unread' ? 'active' : '' ?>">Unread</a>
                                <a href="?filter=schedule_change" class="notif-filter-btn <?= $filter === 'schedule_change' ? 'active' : '' ?>">Schedule</a>
                                <a href="?filter=appointment" class="notif-filter-btn <?= $filter === 'appointment' ? 'active' : '' ?>">Appointments</a>
                                <a href="?filter=cancellation" class="notif-filter-btn <?= $filter === 'cancellation' ? 'active' : '' ?>">Cancellations</a>
                            </div>
                        </div>

                        <?php if ($unreadCount > 0): ?>
                        <form method="POST" style="margin:0;">
                            <?= hms_csrf_field() ?>
                            <input type="hidden" name="notif_action" value="mark_all_read">
                            <button type="submit" class="notif-mark-all">
                                <i class="bi bi-check-all"></i> Mark all as read
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>

                    <div class="notif-list">
                        <?php if (empty($notifications)): ?>
                            <div class="notif-empty">
                                <i class="bi bi-bell-slash"></i>
                                <div style="font-weight:700;">No notifications</div>
                                <div style="font-size:0.85rem;">You're all caught up!</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notif): 
                                $isUnread = (int)$notif['is_read'] === 0;
                                $iconClass = 'notif-icon-system';
                                $iconName = 'bi-info-circle';
                                switch ($notif['type']) {
                                    case 'schedule_change':
                                        $iconClass = 'notif-icon-schedule';
                                        $iconName = 'bi-calendar-event';
                                        break;
                                    case 'appointment':
                                        $iconClass = 'notif-icon-appointment';
                                        $iconName = 'bi-calendar-check';
                                        break;
                                    case 'cancellation':
                                        $iconClass = 'notif-icon-cancellation';
                                        $iconName = 'bi-calendar-x';
                                        break;
                                }
                                $timeAgo = '';
                                $ts = strtotime($notif['created_at']);
                                $diff = time() - $ts;
                                if ($diff < 60) $timeAgo = 'Just now';
                                elseif ($diff < 3600) $timeAgo = floor($diff/60) . 'm ago';
                                elseif ($diff < 86400) $timeAgo = floor($diff/3600) . 'h ago';
                                elseif ($diff < 604800) $timeAgo = floor($diff/86400) . 'd ago';
                                else $timeAgo = date('M j, Y', $ts);
                            ?>
                            <div class="notif-item <?= $isUnread ? 'unread' : '' ?>">
                                <div class="notif-icon-wrap <?= $iconClass ?>">
                                    <i class="bi <?= $iconName ?>"></i>
                                </div>
                                <div class="notif-content">
                                    <div class="notif-content-title"><?= htmlspecialchars($notif['title']) ?></div>
                                    <div class="notif-content-msg"><?= htmlspecialchars($notif['message']) ?></div>
                                    <div class="notif-time">
                                        <i class="bi bi-clock"></i> <?= $timeAgo ?>
                                        <?php if ($notif['doctorName']): ?>
                                            &middot; <i class="bi bi-person"></i> <?= htmlspecialchars($notif['doctorName']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($isUnread): ?>
                                <form method="POST" style="margin:0;">
                                    <?= hms_csrf_field() ?>
                                    <input type="hidden" name="notif_action" value="mark_read">
                                    <input type="hidden" name="notif_id" value="<?= (int)$notif['id'] ?>">
                                    <button type="submit" class="notif-read-btn" title="Mark as read">
                                        <i class="bi bi-check"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>
