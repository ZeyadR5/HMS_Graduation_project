<?php
/**
 * Public Queue Viewer — Mobile-friendly page for patients to check their queue position.
 * No login required. Accessible via QR code on the Queue Screen.
 */
define('HMS_SKIP_AUTO_CONNECT', true);
require_once __DIR__ . '/../../includes/config.php';
date_default_timezone_set('Africa/Cairo');

$connect = hms_db_connect();
if ($connect->connect_error) {
    die("Connection failed");
}
$connect->set_charset("utf8mb4");

// Get all active appointments for today (waiting + in progress)
$sql = $connect->query("SELECT appointment.apid, appointment.patient_Name, appointment.appointmentTime,
                                appointment.doctorSpecialization, appointment.patient_status, appointment.priority,
                                doctors.doctorName
                         FROM appointment
                         JOIN doctors ON doctors.id = appointment.doctorId
                         WHERE appointmentDate = CURRENT_DATE() AND userStatus IN (1,2) AND patient_status IN ('waiting', 'in progress')
                         ORDER BY (priority = 'urgent') DESC, postingDate ASC");

$queue = [];
$position = 0;
while ($row = $sql->fetch_assoc()) {
    $position++;
    $row['position'] = $position;
    $queue[] = $row;
}

// Count stats
$totalWaiting = 0;
$totalInProgress = 0;
foreach ($queue as $q) {
    if ($q['patient_status'] === 'waiting') $totalWaiting++;
    if ($q['patient_status'] === 'in progress') $totalInProgress++;
}

$connect->close();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>متابعة الدور | Echo HMS</title>
    <meta http-equiv="refresh" content="10">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" href="/assets/images/echol.png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #fff;
            min-height: 100vh;
            padding: 16px;
        }

        .header {
            text-align: center;
            padding: 20px 16px;
            margin-bottom: 16px;
        }
        .header h1 {
            font-size: 1.6rem;
            font-weight: 900;
            margin-bottom: 4px;
        }
        .header p {
            color: #94a3b8;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .stats-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 16px 10px;
            text-align: center;
        }
        .stat-card .num {
            font-size: 2rem;
            font-weight: 900;
            line-height: 1;
        }
        .stat-card .label {
            font-size: 0.7rem;
            font-weight: 700;
            color: #94a3b8;
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .stat-waiting .num { color: #fbbf24; }
        .stat-active .num { color: #34d399; }
        .stat-total .num { color: #818cf8; }

        .search-box {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .search-box i { color: #94a3b8; font-size: 1.2rem; }
        .search-box input {
            background: transparent;
            border: none;
            outline: none;
            color: #fff;
            font-family: 'Tajawal', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            width: 100%;
        }
        .search-box input::placeholder { color: #64748b; }

        .queue-list { display: flex; flex-direction: column; gap: 10px; }

        .queue-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.3s;
        }
        .queue-card.active {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .queue-card.urgent {
            border-color: rgba(239, 68, 68, 0.4);
        }
        .queue-card.highlight {
            background: rgba(99, 102, 241, 0.15);
            border: 2px solid rgba(99, 102, 241, 0.5);
            transform: scale(1.02);
        }
        .queue-card.dimmed {
            opacity: 0.35;
        }

        .position-badge {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .queue-card.active .position-badge {
            background: #10b981;
            color: #fff;
        }

        .queue-info { flex: 1; min-width: 0; }
        .queue-info .name {
            font-size: 1.05rem;
            font-weight: 800;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .queue-info .details {
            font-size: 0.75rem;
            color: #94a3b8;
            font-weight: 600;
            margin-top: 2px;
        }

        .queue-status {
            text-align: left;
            flex-shrink: 0;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 800;
        }
        .status-active {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
        }
        .status-waiting {
            background: rgba(148, 163, 184, 0.15);
            color: #94a3b8;
        }
        .status-urgent {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }
        .queue-time {
            font-size: 0.8rem;
            font-weight: 800;
            color: #818cf8;
            margin-top: 3px;
        }

        .footer-note {
            text-align: center;
            margin-top: 24px;
            padding: 16px;
            color: #475569;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .footer-note i { animation: spin 2s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #475569;
        }
        .empty-state i { font-size: 3rem; margin-bottom: 12px; color: #334155; }
        .empty-state h3 { font-size: 1.2rem; font-weight: 800; color: #64748b; }
        .empty-state p { font-size: 0.85rem; margin-top: 6px; }

        .pulse-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #10b981;
            animation: pulse-dot 1.5s infinite;
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.7); }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="bi bi-hospital"></i> متابعة دور الانتظار</h1>
        <p>الصفحة بتتحدث تلقائياً كل 10 ثواني</p>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card stat-waiting">
            <div class="num"><?= $totalWaiting ?></div>
            <div class="label">في الانتظار</div>
        </div>
        <div class="stat-card stat-active">
            <div class="num"><?= $totalInProgress ?></div>
            <div class="label">بالداخل الآن</div>
        </div>
        <div class="stat-card stat-total">
            <div class="num"><?= count($queue) ?></div>
            <div class="label">إجمالي</div>
        </div>
    </div>

    <!-- Search -->
    <div class="search-box">
        <i class="bi bi-search"></i>
        <input type="text" id="searchInput" placeholder="ابحث عن اسمك لمعرفة دورك..." oninput="filterQueue()">
    </div>

    <!-- Queue List -->
    <div class="queue-list" id="queueList">
        <?php if (empty($queue)): ?>
            <div class="empty-state">
                <i class="bi bi-emoji-smile"></i>
                <h3>لا يوجد مواعيد نشطة حالياً</h3>
                <p>القائمة فارغة — ممكن تكون المواعيد انتهت أو لسه مبدأتش</p>
            </div>
        <?php else: ?>
            <?php foreach ($queue as $q): ?>
                <?php
                    $isActive = ($q['patient_status'] === 'in progress');
                    $isUrgent = ($q['priority'] === 'urgent');
                    $cardClass = '';
                    if ($isActive) $cardClass .= ' active';
                    if ($isUrgent) $cardClass .= ' urgent';
                ?>
                <div class="queue-card<?= $cardClass ?>" data-name="<?= htmlspecialchars($q['patient_Name']) ?>">
                    <div class="position-badge"><?= $q['position'] ?></div>
                    <div class="queue-info">
                        <div class="name">
                            <?php if ($isUrgent): ?><span style="color:#f87171">⚠</span> <?php endif; ?>
                            <?= htmlspecialchars($q['patient_Name']) ?>
                        </div>
                        <div class="details">
                            <?= htmlspecialchars($q['doctorSpecialization']) ?> — د. <?= htmlspecialchars($q['doctorName']) ?>
                        </div>
                    </div>
                    <div class="queue-status">
                        <?php if ($isActive): ?>
                            <span class="status-badge status-active"><span class="pulse-dot"></span> بالداخل</span>
                        <?php else: ?>
                            <span class="status-badge status-waiting"><i class="bi bi-clock"></i> انتظار</span>
                        <?php endif; ?>
                        <div class="queue-time"><?= $q['appointmentTime'] ?: '—' ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="footer-note">
        <i class="bi bi-arrow-repeat"></i> تحديث تلقائي كل 10 ثواني<br>
        Echo HMS &copy; <?= date('Y') ?>
    </div>

    <script>
    function filterQueue() {
        var search = document.getElementById('searchInput').value.trim().toLowerCase();
        var cards = document.querySelectorAll('.queue-card');
        
        if (!search) {
            // Reset all cards
            cards.forEach(function(card) {
                card.classList.remove('highlight', 'dimmed');
                card.style.display = '';
            });
            return;
        }

        cards.forEach(function(card) {
            var name = (card.getAttribute('data-name') || '').toLowerCase();
            if (name.includes(search)) {
                card.classList.add('highlight');
                card.classList.remove('dimmed');
                card.style.display = '';
            } else {
                card.classList.remove('highlight');
                card.classList.add('dimmed');
                card.style.display = '';
            }
        });
    }
    </script>
</body>
</html>
