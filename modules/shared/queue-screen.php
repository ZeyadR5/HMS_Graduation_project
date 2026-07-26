<?php
define('HMS_SKIP_AUTO_CONNECT', true);
require_once __DIR__ . '/../../includes/config.php';
$connect = hms_db_connect();

// Auto-reschedule patients who are 15+ minutes late
require_once __DIR__ . '/../../includes/auto-reschedule.php';
hms_check_late_patients($connect);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Queue Board | Echo HMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="icon" href="/assets/images/echol.png">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #0f172a; color: #fff; overflow-y: auto; overflow-x: hidden; }
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.06); }
        .urgent-glow { animation: urgent-glow 1.5s infinite alternate; }
        @keyframes urgent-glow { from { box-shadow: 0 0 5px #ef4444; } to { box-shadow: 0 0 22px #ef4444; } }
        .active-row { background: linear-gradient(90deg, rgba(16,185,129,0.1), transparent); border-left: 4px solid #10b981; }
        .back-btn { transition: all 0.2s; }
        .back-btn:hover { background: rgba(99,102,241,0.15); border-color: rgba(99,102,241,0.4); }
    </style>
    <meta http-equiv="refresh" content="10">
</head>
<body class="p-4 sm:p-10">
    <div class="min-h-[calc(100vh-5rem)] flex flex-col gap-8">

        <!-- Header -->
        <header class="flex flex-col sm:flex-row justify-between items-center glass p-6 sm:p-8 rounded-3xl gap-4">
            <div class="flex items-center gap-4 sm:gap-6">
                <!-- Back Button -->
                <button onclick="if(window.opener || window.history.length <= 1){ window.close(); } else { window.history.back(); }"
                   class="back-btn inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-700 text-slate-300 text-sm font-bold hover:text-white transition-all flex-shrink-0 cursor-pointer bg-transparent"
                   title="Close / Go Back">
                    <i class="bi bi-arrow-left text-base"></i>
                    <span class="hidden sm:inline">Back</span>
                </button>
                <div class="bg-indigo-600 p-4 rounded-2xl shadow-lg flex-shrink-0">
                    <i class="bi bi-hospital text-4xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl sm:text-4xl font-black">Patient Queue Board</h1>
                    <p class="text-slate-400 font-bold mt-1 uppercase tracking-widest text-xs sm:text-sm">Echo Hospital Management System · Live Flow</p>
                </div>
            </div>
            <div class="text-center sm:text-right flex-shrink-0">
                <div id="clock" class="text-3xl sm:text-4xl font-black text-indigo-400">00:00:00</div>
                <div class="text-slate-400 font-bold"><?= date('Y-m-d') ?></div>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8 flex-1 min-h-0">

            <!-- Main: Active Queue -->
            <div class="lg:col-span-8 flex flex-col gap-6">
                <div class="glass flex-1 rounded-3xl overflow-hidden flex flex-col">
                    <div class="bg-indigo-600/20 p-4 sm:p-6 flex flex-col sm:flex-row justify-between items-center gap-2">
                        <h2 class="text-xl sm:text-2xl font-black flex items-center gap-3">
                            <i class="bi bi-person-badge-fill"></i> Today's Waiting Queue
                        </h2>
                        <span class="bg-indigo-600 px-4 py-1 rounded-full text-sm font-bold tracking-wide">
                            <i class="bi bi-broadcast me-1"></i> Live
                        </span>
                    </div>
                    <div class="overflow-x-auto flex-1">
                        <div class="p-4 sm:p-6 min-w-max">
                            <table class="w-full text-left">
                                <thead class="text-slate-500 font-black border-b border-slate-800 text-xs uppercase tracking-widest">
                                    <tr>
                                        <th class="pb-4 py-4 pl-4">Patient Name</th>
                                        <th class="pb-4 py-4">Clinic / Specialty</th>
                                        <th class="pb-4 py-4">Status</th>
                                        <th class="pb-4 py-4">Time</th>
                                    </tr>
                                </thead>
                                <tbody class="text-base">
                                    <?php
                                    $sql = mysqli_query($connect,
                                        "SELECT appointment.*, doctors.doctorName
                                         FROM appointment
                                         JOIN doctors ON doctors.id = appointment.doctorId
                                         WHERE appointmentDate = CURRENT_DATE()
                                           AND userStatus = 1
                                           AND patient_status != 'done'
                                         ORDER BY (priority = 'urgent') DESC, appointmentTime ASC, postingDate ASC
                                         LIMIT 8");
                                    while ($row = mysqli_fetch_array($sql)):
                                        $isUrgent   = ($row['priority'] === 'urgent');
                                        $isProgress = ($row['patient_status'] === 'in progress');
                                    ?>
                                    <tr class="border-b border-slate-800/50 transition-all <?= $isProgress ? 'active-row' : '' ?>">
                                        <td class="py-6 pl-4">
                                            <div class="flex items-center gap-3">
                                                <?php if ($isUrgent): ?>
                                                    <span class="w-2.5 h-2.5 bg-rose-600 rounded-full urgent-glow flex-shrink-0"></span>
                                                <?php else: ?>
                                                    <span class="w-2.5 h-2.5 bg-slate-700 rounded-full flex-shrink-0"></span>
                                                <?php endif; ?>
                                                <span class="font-bold <?= $isUrgent ? 'text-rose-400' : '' ?>">
                                                    <?= htmlspecialchars($row['patient_Name']) ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="py-6 font-bold text-slate-300">
                                            <?= htmlspecialchars($row['doctorSpecialization']) ?>
                                            <span class="text-xs text-slate-500 font-medium ml-1">Dr. <?= htmlspecialchars($row['doctorName']) ?></span>
                                        </td>
                                        <td class="py-6">
                                            <?php if ($isProgress): ?>
                                                <span class="text-emerald-400 font-black flex items-center gap-2">
                                                    <i class="bi bi-broadcast"></i> In Session
                                                </span>
                                            <?php elseif ($isUrgent): ?>
                                                <span class="text-rose-400 font-bold flex items-center gap-2">
                                                    <i class="bi bi-exclamation-triangle-fill"></i> Urgent
                                                </span>
                                            <?php else: ?>
                                                <span class="text-slate-500 font-bold">Waiting</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-6 font-black text-indigo-400"><?= htmlspecialchars($row['appointmentTime'] ?: '—') ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php if (mysqli_num_rows($sql) == 0): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-20 text-slate-600 font-black text-xl">
                                                <i class="bi bi-calendar-check text-4xl block mb-3 text-slate-700"></i>
                                                No active appointments at the moment
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-4 flex flex-col gap-6 sm:gap-8 text-center">
                <div class="glass p-8 rounded-3xl flex-1 flex flex-col justify-center items-center gap-6">
                    <div class="w-32 h-32 bg-indigo-600/10 rounded-full flex items-center justify-center border-4 border-indigo-600/30">
                        <i class="bi bi-megaphone-fill text-6xl text-indigo-500"></i>
                    </div>
                    <h3 class="text-2xl font-black">Attention Please</h3>
                    <p class="text-slate-400 text-base leading-relaxed">
                        When your name is highlighted in <span class="text-emerald-400 font-bold">green</span>,
                        please proceed to the consultation room immediately.
                        <br><br>
                        <span class="text-rose-400 font-bold">Urgent cases</span> are given priority.
                    </p>
                </div>

                <div class="glass p-8 rounded-3xl">
                    <h4 class="text-slate-400 font-bold uppercase text-xs tracking-widest mb-4">Scan to Follow Your Queue</h4>
                    <?php
                        $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
                        $queueUrl = $scheme . '://' . $host . '/modules/shared/queue-public.php';
                        $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&bgcolor=ffffff&color=0f172a&data=' . urlencode($queueUrl);
                    ?>
                    <div class="bg-white p-3 rounded-2xl w-36 h-36 mx-auto">
                        <img src="<?= $qrApiUrl ?>" alt="Live Queue QR Code" style="width:100%;height:100%;object-fit:contain;">
                    </div>
                    <p class="text-[11px] text-slate-400 mt-4 font-bold">Scan with your phone to track your position</p>
                    <p class="text-[9px] text-slate-600 mt-1 break-all" dir="ltr"><?= $queueUrl ?></p>
                </div>
            </div>
        </div>

        <footer class="glass px-4 sm:px-8 py-4 rounded-2xl text-center text-xs sm:text-sm font-bold text-slate-500 flex flex-col sm:flex-row justify-between items-center gap-2">
            <span>Echo HMS — Hospital Management System</span>
            <span class="flex items-center gap-2">
                Auto-refresh every 10 seconds <i class="bi bi-arrow-repeat" style="animation:spin 2s linear infinite"></i>
            </span>
        </footer>
    </div>

    <style>@keyframes spin { to { transform: rotate(360deg); } }</style>
    <script>
        function updateClock() {
            document.getElementById('clock').innerText =
                new Date().toLocaleTimeString('en-GB');
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>
</body>
</html>
