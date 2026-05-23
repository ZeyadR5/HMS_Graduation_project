<?php
define('HMS_SKIP_AUTO_CONNECT', true);
require_once __DIR__ . '/../../includes/config.php';
$connect = hms_db_connect();

// Auto-reschedule patients who are 15+ minutes late
require_once __DIR__ . '/../../includes/auto-reschedule.php';
hms_check_late_patients($connect);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>شاشة الانتظار | Echo HMS Queue</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', sans-serif; background-color: #0f172a; color: #fff; overflow-y: auto; overflow-x: hidden; }
        .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .urgent-glow { animation: urgent-glow 1.5s infinite alternate; }
        @keyframes urgent-glow { from { box-shadow: 0 0 5px #ef4444; } to { box-shadow: 0 0 20px #ef4444; } }
        .active-row { background: linear-gradient(90deg, rgba(16, 185, 129, 0.1), transparent); border-right: 4px solid #10b981; }
    </style>
    <meta http-equiv="refresh" content="10">
</head>
<body class="p-4 sm:p-10">
    <div class="min-h-[calc(100vh-5rem)] sm:min-h-[calc(100vh-5rem)] flex flex-col gap-8">
        <!-- Header -->
        <header class="flex flex-col sm:flex-row justify-between items-center glass p-6 sm:p-8 rounded-3xl gap-4">
            <div class="flex flex-col sm:flex-row items-center gap-4 sm:gap-6 text-center sm:text-right">
                <div class="bg-indigo-600 p-4 rounded-2xl shadow-lg">
                    <i class="bi bi-hospital text-4xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl sm:text-4xl font-black">شاشة تنظيم الدور</h1>
                    <p class="text-slate-400 font-bold mt-1 uppercase tracking-widest text-xs sm:text-sm">Echo Management System | Patient Flow Board</p>
                </div>
            </div>
            <div class="text-center sm:text-right">
                <div id="clock" class="text-3xl sm:text-4xl font-black text-indigo-400">00:00:00</div>
                <div class="text-slate-400 font-bold"><?= date('Y-m-d') ?></div>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8 flex-1 min-h-0">
            <!-- Main Content: Active Patients -->
            <div class="lg:col-span-8 flex flex-col gap-6">
                <div class="glass flex-1 rounded-3xl overflow-hidden flex flex-col">
                    <div class="bg-indigo-600/20 p-4 sm:p-6 flex flex-col sm:flex-row justify-between items-center gap-2">
                        <h2 class="text-xl sm:text-2xl font-black flex items-center gap-3">
                            <i class="bi bi-person-badge-fill"></i> قائمة الانتظار الحالية
                        </h2>
                        <span class="bg-indigo-600 px-4 py-1 rounded-full text-sm font-bold">مباشر</span>
                    </div>
                    <div class="overflow-x-auto flex-1">
                        <div class="p-4 sm:p-6 min-w-max">
                            <table class="w-full text-right">
                                <thead class="text-slate-500 font-black border-b border-slate-800">
                                <tr>
                                    <th class="pb-4 py-4 pr-4">اسم المريض</th>
                                    <th class="pb-4 py-4">العيادة / التخصص</th>
                                    <th class="pb-4 py-4">الحالة</th>
                                    <th class="pb-4 py-4 text-left">الوقت</th>
                                </tr>
                            </thead>
                            <tbody class="text-lg">
                                <?php
                                $sql = mysqli_query($connect, "SELECT appointment.*, doctors.doctorName 
                                                            FROM appointment 
                                                            JOIN doctors ON doctors.id = appointment.doctorId 
                                                            WHERE appointmentDate = CURRENT_DATE() AND userStatus=1 AND patient_status != 'done'
                                                            ORDER BY (priority = 'urgent') DESC, postingDate ASC LIMIT 8");
                                while ($row = mysqli_fetch_array($sql)) {
                                    $isUrgent = ($row['priority'] === 'urgent');
                                    $isProgress = ($row['patient_status'] === 'in progress');
                                ?>
                                <tr class="border-b border-slate-800/50 transition-all <?= $isProgress ? 'active-row' : '' ?>">
                                    <td class="py-6 pr-4">
                                        <div class="flex items-center gap-4">
                                            <?php if ($isUrgent): ?>
                                                <span class="w-3 h-3 bg-rose-600 rounded-full urgent-glow"></span>
                                            <?php endif; ?>
                                            <span class="font-bold <?= $isUrgent ? 'text-rose-400' : '' ?>">
                                                <?= htmlspecialchars($row['patient_Name']) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-6 font-bold text-slate-300"><?= $row['doctorSpecialization'] ?> <span class="text-xs text-slate-500">(د. <?= $row['doctorName'] ?>)</span></td>
                                    <td class="py-6">
                                        <?php if ($isProgress): ?>
                                            <span class="text-emerald-400 font-black flex items-center gap-2">
                                                <i class="bi bi-broadcast"></i> بالداخل الآن
                                            </span>
                                        <?php else: ?>
                                            <span class="text-slate-500 font-bold">في الانتظار</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-6 text-left font-black text-indigo-400"><?= $row['appointmentTime'] ?></td>
                                </tr>
                                <?php } ?>
                                <?php if (mysqli_num_rows($sql) == 0): ?>
                                    <tr><td colspan="4" class="text-center py-20 text-slate-600 font-black text-2xl">لا يوجد مواعيد نشطة حالياً</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar: Stats & Callout -->
            <div class="lg:col-span-4 flex flex-col gap-6 sm:gap-8 text-center">
                <div class="glass p-8 rounded-3xl flex-1 flex flex-col justify-center items-center gap-6">
                    <div class="w-32 h-32 bg-indigo-600/10 rounded-full flex items-center justify-center border-4 border-indigo-600/30">
                        <i class="bi bi-megaphone-fill text-6xl text-indigo-500"></i>
                    </div>
                    <h3 class="text-3xl font-black">يرجى الانتباه</h3>
                    <p class="text-slate-400 text-lg leading-relaxed">
                        عند ظهور اسمك باللون الأخضر، يرجى التوجه لغرفة الكشف فوراً. 
                        <br>الحالات الطارئة لها الأولوية في الكشف.
                    </p>
                </div>
                
                <div class="glass p-8 rounded-3xl">
                    <h4 class="text-slate-400 font-bold uppercase text-xs tracking-widest mb-4">كود الوصول السريع</h4>
                    <?php
                        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                        $queueUrl = $scheme . '://' . $host . '/modules/shared/queue-public.php';
                        $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&bgcolor=ffffff&color=0f172a&data=' . urlencode($queueUrl);
                    ?>
                    <div class="bg-white p-3 rounded-2xl w-36 h-36 mx-auto">
                        <img src="<?= $qrApiUrl ?>" alt="QR Code" style="width:100%;height:100%;object-fit:contain;">
                    </div>
                    <p class="text-[11px] text-slate-400 mt-4 font-bold">امسح الكود من موبايلك لمتابعة دورك</p>
                    <p class="text-[9px] text-slate-600 mt-1 break-all" dir="ltr"><?= $queueUrl ?></p>
                </div>
            </div>
        </div>

        <footer class="glass px-4 sm:px-8 py-4 rounded-2xl text-center text-xs sm:text-sm font-bold text-slate-500 flex flex-col sm:flex-row justify-between items-center gap-2">
            <span>نظام Echo HMS لإدارة المستشفيات الرقمية</span>
            <span class="flex items-center gap-2">تحديث القائمة تلقائياً كل 10 ثواني <i class="bi bi-arrow-repeat spin"></i></span>
        </footer>
    </div>

    <script>
        function updateClock() {
            const now = new Date();
            document.getElementById('clock').innerText = now.toLocaleTimeString('en-GB');
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>
</body>
</html>
