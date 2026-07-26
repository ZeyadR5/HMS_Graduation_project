<?php
/**
 * ====================================================
 * Doctor Schedules Seed Script
 * ====================================================
 * يحط مواعيد افتراضية لكل الدكاترة اللي مالهومش مواعيد.
 * شغّله مرة واحدة من المتصفح:
 *   http://localhost/hms/seed_schedules.php
 *
 * ملاحظة: بعد ما تشغله امسحه أو حوّله لاسم ثاني.
 * ====================================================
 */

require_once __DIR__ . '/includes/config.php';

$conn = hms_db_connect();
$conn->set_charset('utf8mb4');

// ─── إعدادات الـ Seed ─────────────────────────────
// day_of_week: 0=السبت  1=الأحد  2=الاثنين  3=الثلاثاء
//              4=الأربعاء  5=الخميس  6=الجمعة

/*
 * قواعد المواعيد حسب التخصص:
 *   - الجراحة / الأورام    → 8:00 - 16:00 | 30 دقيقة
 *   - الباطنة / القلب      → 9:00 - 17:00 | 30 دقيقة
 *   - الأسنان              → 10:00 - 18:00 | 20 دقيقة
 *   - الجلدية / الأشعة     → 9:00 - 15:00 | 20 دقيقة
 *   - النساء والتوليد       → 8:00 - 14:00 | 30 دقيقة
 *   - البولية              → 9:00 - 14:00 | 30 دقيقة
 *   - النفسية              → 10:00 - 17:00 | 45 دقيقة
 *   - العظام / التجميل     → 9:00 - 16:00 | 30 دقيقة
 *   - الأطفال              → 9:00 - 15:00 | 20 دقيقة
 *   - افتراضي              → 9:00 - 17:00 | 30 دقيقة
 *
 * الجمعة (6) = يوم إجازة لكل الدكاترة.
 */

function get_schedule_by_spec(string $spec): array
{
    $s = strtolower(trim($spec));

    if (str_contains($s, 'surg') || str_contains($s, 'oncol')) {
        return ['start' => '08:00', 'end' => '16:00', 'slot' => 30];
    }
    if (str_contains($s, 'cardio') || str_contains($s, 'heart') || str_contains($s, 'internal')) {
        return ['start' => '09:00', 'end' => '17:00', 'slot' => 30];
    }
    if (str_contains($s, 'dent') || str_contains($s, 'teeth')) {
        return ['start' => '10:00', 'end' => '18:00', 'slot' => 20];
    }
    if (str_contains($s, 'derm') || str_contains($s, 'skin')) {
        return ['start' => '09:00', 'end' => '15:00', 'slot' => 20];
    }
    if (str_contains($s, 'radiol')) {
        return ['start' => '09:00', 'end' => '15:00', 'slot' => 20];
    }
    if (str_contains($s, 'gyn') || str_contains($s, 'obstet') || str_contains($s, 'women')) {
        return ['start' => '08:00', 'end' => '14:00', 'slot' => 30];
    }
    if (str_contains($s, 'urol')) {
        return ['start' => '09:00', 'end' => '14:00', 'slot' => 30];
    }
    if (str_contains($s, 'psych')) {
        return ['start' => '10:00', 'end' => '17:00', 'slot' => 45];
    }
    if (str_contains($s, 'ortho') || str_contains($s, 'plastic') || str_contains($s, 'bone')) {
        return ['start' => '09:00', 'end' => '16:00', 'slot' => 30];
    }
    if (str_contains($s, 'pediatr') || str_contains($s, 'child')) {
        return ['start' => '09:00', 'end' => '15:00', 'slot' => 20];
    }

    // افتراضي
    return ['start' => '09:00', 'end' => '17:00', 'slot' => 30];
}

// ─── جيب كل الدكاترة ──────────────────────────────
$doctors = [];
$res = $conn->query("SELECT id, doctorName, specilization FROM doctors WHERE role = 'Doctor' AND is_active = 1");
while ($row = $res->fetch_assoc()) {
    $doctors[] = $row;
}

// ─── أيام الشغل (0-5)، الجمعة (6) إجازة ──────────
$workDays = [0, 1, 2, 3, 4, 5]; // السبت → الخميس

$dayNames = [
    0 => 'السبت',
    1 => 'الأحد',
    2 => 'الاثنين',
    3 => 'الثلاثاء',
    4 => 'الأربعاء',
    5 => 'الخميس',
    6 => 'الجمعة (إجازة)',
];

$inserted  = 0;
$skipped   = 0;
$log       = [];

$stmt = $conn->prepare("
    INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration, status)
    VALUES (?, ?, ?, ?, ?, 'available')
    ON DUPLICATE KEY UPDATE
        start_time    = IF(VALUES(start_time) != start_time, VALUES(start_time), start_time),
        end_time      = IF(VALUES(end_time) != end_time, VALUES(end_time), end_time),
        slot_duration = IF(VALUES(slot_duration) != slot_duration, VALUES(slot_duration), slot_duration)
");

foreach ($doctors as $doc) {
    $docId   = (int)$doc['id'];
    $docName = $doc['doctorName'];
    $spec    = $doc['specilization'] ?? 'general';

    $sched = get_schedule_by_spec($spec);
    $start = $sched['start'];
    $end   = $sched['end'];
    $slot  = $sched['slot'];

    $docLog = ["<strong>{$docName}</strong> (ID:{$docId}) — {$spec}"];

    foreach ($workDays as $day) {
        $stmt->bind_param("iissi", $docId, $day, $start, $end, $slot);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $docLog[] = "  ✔ {$dayNames[$day]}: {$start} → {$end} ({$slot} دقيقة)";
                $inserted++;
            } else {
                $docLog[] = "  — {$dayNames[$day]}: لا تغيير";
                $skipped++;
            }
        } else {
            $docLog[] = "  ✖ {$dayNames[$day]}: خطأ — " . $stmt->error;
        }
    }

    $log[] = implode("<br>", $docLog);
}

$stmt->close();
$conn->close();

?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>Seed: Doctor Schedules</title>
<style>
  body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; padding: 2rem; }
  h1   { color: #38bdf8; margin-bottom: .5rem; }
  .badge { display:inline-block; padding:.25rem .75rem; border-radius:999px; font-size:.85rem; font-weight:700; margin:.25rem; }
  .green { background:#166534; color:#bbf7d0; }
  .blue  { background:#1e3a5f; color:#bfdbfe; }
  .card  { background:#1e293b; border:1px solid #334155; border-radius:.75rem; padding:1.25rem; margin:.75rem 0; }
  .card p { margin:.2rem 0; font-size:.92rem; }
  .note  { background:#422006; border:1px solid #92400e; border-radius:.5rem; padding:1rem; margin-top:1.5rem; color:#fde68a; }
</style>
</head>
<body>
<h1>🌱 Seed: Doctor Schedules</h1>
<p>
  <span class="badge green">✔ صفوف مضافة / محدّثة: <?= $inserted ?></span>
  <span class="badge blue">— بدون تغيير: <?= $skipped ?></span>
</p>
<hr style="border-color:#334155; margin:1rem 0">

<?php foreach ($log as $entry): ?>
  <div class="card"><p><?= $entry ?></p></div>
<?php endforeach; ?>

<div class="note">
  ⚠️ <strong>تنبيه:</strong> بعد التأكد من النتيجة، احذف ملف <code>seed_schedules.php</code> من السيرفر أو أعد تسميته.
</div>
</body>
</html>
