<?php
/**
 * Live Queue Viewer — Mobile-friendly page for patients to check their queue position.
 * No login required. Accessible via QR code on the Queue Screen.
 */
define('HMS_SKIP_AUTO_CONNECT', true);
require_once __DIR__ . '/../../includes/config.php';
date_default_timezone_set('Africa/Cairo');

$connect = hms_db_connect();
if ($connect->connect_error) die("Connection failed");
$connect->set_charset("utf8mb4");

$sql = $connect->query(
    "SELECT appointment.apid, appointment.patient_Name, appointment.appointmentTime,
            appointment.doctorSpecialization, appointment.patient_status, appointment.priority,
            doctors.doctorName
     FROM appointment
     JOIN doctors ON doctors.id = appointment.doctorId
     WHERE appointmentDate = CURRENT_DATE()
       AND userStatus IN (1,2)
       AND patient_status IN ('waiting','in progress')
     ORDER BY (priority = 'urgent') DESC, appointmentTime ASC, postingDate ASC"
);

$queue = [];
$position = 0;
while ($row = $sql->fetch_assoc()) {
    $row['position'] = ++$position;
    $queue[] = $row;
}

$totalWaiting    = count(array_filter($queue, fn($q) => $q['patient_status'] === 'waiting'));
$totalInProgress = count(array_filter($queue, fn($q) => $q['patient_status'] === 'in progress'));

$connect->close();
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Queue | Echo HMS</title>
    <meta http-equiv="refresh" content="10">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>
    <link rel="icon" href="/assets/images/echol.png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #fff;
            min-height: 100vh;
            padding: 16px;
        }

        /* ── Header ── */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 16px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.12);
            color: #94a3b8;
            font-size: 0.85rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
            flex-shrink: 0;
        }
        .back-btn:hover { background: rgba(99,102,241,0.15); border-color: rgba(99,102,241,0.4); color: #fff; }
        .header-center { text-align: center; flex: 1; }
        .header-center h1 { font-size: 1.45rem; font-weight: 900; }
        .header-center p { color: #94a3b8; font-size: 0.8rem; font-weight: 500; margin-top: 2px; }

        /* ── Stats ── */
        .stats-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 20px; }
        .stat-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 16px 10px;
            text-align: center;
        }
        .stat-card .num { font-size: 2rem; font-weight: 900; line-height: 1; }
        .stat-card .label { font-size: 0.65rem; font-weight: 700; color: #94a3b8; margin-top: 4px; text-transform: uppercase; letter-spacing: 1px; }
        .stat-waiting .num  { color: #fbbf24; }
        .stat-active  .num  { color: #34d399; }
        .stat-total   .num  { color: #818cf8; }

        /* ── Search ── */
        .search-box {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 13px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .search-box i { color: #94a3b8; font-size: 1.1rem; }
        .search-box input {
            background: transparent; border: none; outline: none;
            color: #fff; font-family: 'Outfit', sans-serif;
            font-size: 0.95rem; font-weight: 600; width: 100%;
        }
        .search-box input::placeholder { color: #475569; }

        /* ── Queue Cards ── */
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
        .queue-card.active   { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); }
        .queue-card.urgent   { border-color: rgba(239,68,68,0.4); }
        .queue-card.highlight { background: rgba(99,102,241,0.15); border: 2px solid rgba(99,102,241,0.5); transform: scale(1.02); }
        .queue-card.dimmed   { opacity: 0.3; }

        .position-badge {
            width: 44px; height: 44px; border-radius: 12px;
            background: rgba(255,255,255,0.08);
            display: flex; align-items: center; justify-content: center;
            font-weight: 900; font-size: 1.1rem; flex-shrink: 0;
        }
        .queue-card.active .position-badge { background: #10b981; color: #fff; }

        .queue-info { flex: 1; min-width: 0; }
        .queue-info .name { font-size: 1rem; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .queue-info .details { font-size: 0.72rem; color: #94a3b8; font-weight: 600; margin-top: 2px; }

        .queue-status { text-align: right; flex-shrink: 0; }
        .status-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 10px; border-radius: 999px; font-size: 0.68rem; font-weight: 800;
        }
        .status-active  { background: rgba(16,185,129,0.2);  color: #34d399; }
        .status-waiting { background: rgba(148,163,184,0.15); color: #94a3b8; }
        .status-urgent  { background: rgba(239,68,68,0.2);    color: #f87171; }
        .queue-time { font-size: 0.78rem; font-weight: 800; color: #818cf8; margin-top: 4px; }

        /* ── Pulse Dot ── */
        .pulse-dot { width: 8px; height: 8px; border-radius: 50%; background: #10b981; animation: pulse-dot 1.5s infinite; }
        @keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.5;transform:scale(0.7)} }

        /* ── Empty State ── */
        .empty-state { text-align: center; padding: 60px 20px; color: #475569; }
        .empty-state i { font-size: 3rem; margin-bottom: 12px; color: #334155; display: block; }
        .empty-state h3 { font-size: 1.1rem; font-weight: 800; color: #64748b; }
        .empty-state p  { font-size: 0.82rem; margin-top: 6px; }

        /* ── Footer ── */
        .footer-note { text-align: center; margin-top: 24px; padding: 16px; color: #475569; font-size: 0.72rem; font-weight: 600; }
        .footer-note i { animation: spin 2s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <a href="javascript:history.back()" class="back-btn" title="Go Back">
            <i class="bi bi-arrow-left"></i>
            <span>Back</span>
        </a>
        <div class="header-center">
            <h1><i class="bi bi-hospital"></i> Live Queue</h1>
            <p>Auto-refreshes every 10 seconds</p>
        </div>
        <!-- Spacer to balance the back button -->
        <div style="width:80px;flex-shrink:0;"></div>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card stat-waiting">
            <div class="num"><?= $totalWaiting ?></div>
            <div class="label">Waiting</div>
        </div>
        <div class="stat-card stat-active">
            <div class="num"><?= $totalInProgress ?></div>
            <div class="label">In Session</div>
        </div>
        <div class="stat-card stat-total">
            <div class="num"><?= count($queue) ?></div>
            <div class="label">Total</div>
        </div>
    </div>

    <!-- Search -->
    <div class="search-box">
        <i class="bi bi-search"></i>
        <input type="text" id="searchInput" placeholder="Search your name to find your position..." oninput="filterQueue()">
    </div>

    <!-- Queue List -->
    <div class="queue-list" id="queueList">
        <?php if (empty($queue)): ?>
            <div class="empty-state">
                <i class="bi bi-calendar-check"></i>
                <h3>No active appointments right now</h3>
                <p>The queue is empty — appointments may have ended or not started yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($queue as $q):
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
                        <?= htmlspecialchars($q['doctorSpecialization']) ?> — Dr. <?= htmlspecialchars($q['doctorName']) ?>
                    </div>
                </div>
                <div class="queue-status">
                    <?php if ($isActive): ?>
                        <span class="status-badge status-active"><span class="pulse-dot"></span> In Session</span>
                    <?php elseif ($isUrgent): ?>
                        <span class="status-badge status-urgent"><i class="bi bi-exclamation-triangle-fill"></i> Urgent</span>
                    <?php else: ?>
                        <span class="status-badge status-waiting"><i class="bi bi-clock"></i> Waiting</span>
                    <?php endif; ?>
                    <div class="queue-time"><?= htmlspecialchars($q['appointmentTime'] ?: '—') ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="footer-note">
        <i class="bi bi-arrow-repeat"></i> Auto-refresh every 10 seconds<br>
        Echo HMS &copy; <?= date('Y') ?>
    </div>

    <script>
    function filterQueue() {
        var search = document.getElementById('searchInput').value.trim().toLowerCase();
        var cards  = document.querySelectorAll('.queue-card');
        if (!search) {
            cards.forEach(c => { c.classList.remove('highlight','dimmed'); c.style.display=''; });
            return;
        }
        cards.forEach(function(card) {
            var name = (card.getAttribute('data-name') || '').toLowerCase();
            if (name.includes(search)) {
                card.classList.add('highlight');
                card.classList.remove('dimmed');
            } else {
                card.classList.add('dimmed');
                card.classList.remove('highlight');
            }
        });
    }
    </script>
</body>
</html>
