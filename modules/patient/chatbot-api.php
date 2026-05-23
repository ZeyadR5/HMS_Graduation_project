<?php
session_start();

// ── Auth guard ───────────────────────────────────────────────────────────────
if (empty($_SESSION['login']) || ($_SESSION['role'] ?? '') !== 'Patient') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

$now = time();
$_SESSION['ai_rate_limit'] = array_values(array_filter(
    $_SESSION['ai_rate_limit'] ?? [],
    static fn($attemptTime) => is_int($attemptTime) && ($now - $attemptTime) < 60
));
if (count($_SESSION['ai_rate_limit']) >= 20) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many AI requests. Please try again shortly.'], JSON_UNESCAPED_UNICODE);
    exit();
}
$_SESSION['ai_rate_limit'][] = $now;

// ── Parse input ──────────────────────────────────────────────────────────────
$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true);

if (!$input || empty($input['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No message provided'], JSON_UNESCAPED_UNICODE);
    exit();
}

$userMessage = trim($input['message']);
$chatHistory = $input['history'] ?? [];

// ── Diagnostic question filter ───────────────────────────────────────────────
$diagnosticPatterns = [
    'عندي ألم', 'عندي وجع', 'أحس بألم', 'احس بألم', 'إيه التشخيص', 'ايه التشخيص',
    'إيه المرض', 'ايه المرض', 'هل عندي', 'هل أنا مريض', 'هل انا مريض', 'سبب الألم',
    'سبب الوجع', 'سبب الصداع', 'أعراض', 'اعراض', 'تشخيص', 'علاج', 'دواء', 'أدوية',
    'ادوية', 'محتاج عملية', 'عملية جراحية', 'هل احتاج', 'هل أحتاج', 'حالتي خطيرة',
    'نتيجة التحليل', 'تفسير التحليل', 'قراءة التحليل', 'وصفة طبية', 'روشتة',
    'diagnos', 'symptom', 'treatment plan', 'prescri', 'what disease', 'what illness',
    'do i have', 'am i sick', 'is it serious', 'what medicine', 'what drug',
    'need surgery', 'chest pain', 'heart attack', 'i feel pain', 'i have pain',
];

$lowerMessage = mb_strtolower($userMessage);
$isDiagnostic = false;
foreach ($diagnosticPatterns as $pattern) {
    if (mb_strpos($lowerMessage, mb_strtolower($pattern)) !== false) {
        $isDiagnostic = true;
        break;
    }
}

if ($isDiagnostic) {
    echo json_encode([
        'reply' => "⚠️ عذراً، لا أستطيع تقديم استشارات طبية أو تشخيصية.\n\n🏥 يجب مراجعة الطبيب المختص أو التوجه لقسم الطوارئ.\n\n📞 في حالة الطوارئ اتصل على: 123",
        'type' => 'diagnostic_blocked'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// ── DB Connection ───────────────────────────────────────────────────────────
define('HMS_SKIP_AUTO_CONNECT', true);
require_once __DIR__ . '/../../includes/config.php';
$connect = hms_db_connect(false);
if (!$connect) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed'], JSON_UNESCAPED_UNICODE);
    exit();
}

$sessionUid = (int)($_SESSION['uid'] ?? 0);
$patientName = $_SESSION['username'] ?? 'المريض';

// ── Get context ─────────────────────────────────────────────────────────────

// 1. Upcoming patient appointments
$upcomingAppointments = [];
$stmt = $connect->prepare("
    SELECT a.appointmentDate, a.appointmentTime, a.userStatus, a.doctorStatus, a.consultancyFees, a.paid,
           COALESCE(d.doctorName, 'غير محدد') AS doctor_name,
           COALESCE(NULLIF(a.doctorSpecialization, ''), d.specilization, 'عام') AS specialization
    FROM appointment a
    LEFT JOIN doctors d ON d.id = a.doctorId
    WHERE a.userId = ? AND STR_TO_DATE(a.appointmentDate, '%Y-%m-%d') >= CURDATE() AND a.userStatus <> 0 AND a.doctorStatus <> 0
    ORDER BY STR_TO_DATE(a.appointmentDate, '%Y-%m-%d') ASC, a.appointmentTime ASC LIMIT 5
");
$stmt->bind_param("i", $sessionUid);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $upcomingAppointments[] = $row;
}

// 2. Doctors, Schedules, and Booked Slots
$doctors = [];
$doctorResult = $connect->query("SELECT id, doctorName, specilization, docFees FROM doctors WHERE doctorName IS NOT NULL");
while ($dRow = $doctorResult->fetch_assoc()) {
    $docId = (int)$dRow['id'];
    $dRow['schedules'] = [];
    $dRow['booked_slots'] = [];

    // Weekly schedules
    $sStmt = $connect->prepare("SELECT day_of_week, start_time, end_time, slot_duration FROM doctor_schedules WHERE doctor_id = ? AND status = 'available'");
    $sStmt->bind_param("i", $docId);
    $sStmt->execute();
    $sRes = $sStmt->get_result();
    while ($sRow = $sRes->fetch_assoc()) { $dRow['schedules'][] = $sRow; }

    // Booked slots for next 7 days
    $bStmt = $connect->prepare("SELECT appointmentDate, appointmentTime FROM appointment WHERE doctorId = ? AND STR_TO_DATE(appointmentDate, '%Y-%m-%d') >= CURDATE() AND userStatus = 1 AND doctorStatus = 1 ORDER BY appointmentDate, appointmentTime LIMIT 20");
    $bStmt->bind_param("i", $docId);
    $bStmt->execute();
    $bRes = $bStmt->get_result();
    while ($bRow = $bRes->fetch_assoc()) { $dRow['booked_slots'][] = $bRow; }

    $doctors[] = $dRow;
}

// 3. Latest Medical History
$latestHistory = null;
$stmtH = $connect->prepare("SELECT treatment, Report, CreationDate FROM tblmedicalhistory WHERE UserID = ? ORDER BY ID DESC LIMIT 1");
$stmtH->bind_param("i", $sessionUid);
$stmtH->execute();
if ($hRes = $stmtH->get_result()->fetch_assoc()) { $latestHistory = $hRes; }

$connect->close();

$apiKey = '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';
if (!$apiKey) {
    http_response_code(500);
    echo json_encode(['error' => 'AI service is not configured'], JSON_UNESCAPED_UNICODE);
    exit();
}

// ── Format Context ───────────────────────────────────────────────────────────
$currentDate = date('Y-m-d');
$currentTime = date('g:i A');
$currentDayAr = ['Saturday'=>'السبت','Sunday'=>'الأحد','Monday'=>'الاثنين','Tuesday'=>'الثلاثاء','Wednesday'=>'الأربعاء','Thursday'=>'الخميس','Friday'=>'الجمعة'][date('l')] ?? date('l');

$doctorsContext = "\n\nبيانات الأطباء والمواعيد المتاحة حالياً:\n";
$dayMapAr = ['السبت', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'];
foreach ($doctors as $doc) {
    $doctorsContext .= "- الطبيب: {$doc['doctorName']} | التخصص: {$doc['specilization']} | رسوم الكشف: {$doc['docFees']} جنيه\n";
    if (!empty($doc['schedules'])) {
        $doctorsContext .= "  المواعيد الثابتة:\n";
        foreach ($doc['schedules'] as $sch) {
            $doctorsContext .= "    • {$dayMapAr[$sch['day_of_week']]}: " . date("g:i A", strtotime($sch['start_time'])) . " - " . date("g:i A", strtotime($sch['end_time'])) . " (مدة الكشف: {$sch['slot_duration']} دقيقة)\n";
        }
    }
    if (!empty($doc['booked_slots'])) {
        $doctorsContext .= "  ❌ محجوز بالفعل (غير متاح):\n";
        foreach ($doc['booked_slots'] as $bs) {
            $doctorsContext .= "    • {$bs['appointmentDate']} الساعة " . date("g:i A", strtotime($bs['appointmentTime'])) . "\n";
        }
    }
}

// ── AI Prompt ───────────────────────────────────────────────────────────────
$systemPrompt = <<<PROMPT
أنت "Echo Assistant" — مساعد دعم المرضى الذكي في نظام Echo HMS.
اسم المريض: {$patientName} 
تاريخ اليوم: {$currentDate} ({$currentDayAr})
الوقت الحالي الآن: {$currentTime}

## دورك:
1. **المواعيد المتاحة**: إبلاغ المريض بالمواعيد "الفارغة" والمتاحة "مستقبلاً" فقط. 
2. **فلترة المواعيد**:
   - ممنوع منعاً باتاً اقتراح أي موعد "محجوز بالفعل" من قائمة (❌).
   - ممنوع منعاً باتاً اقتراح أي موعد "في الماضي" (أي وقت سبق الوقت الحالي إذا كان الموعد اليوم).
   - إذا كان الوقت الحالي (مثلاً 9:55 مساءً) ونهاية عمل الطبيب (10:00 مساءً)، أخبر المريض أنه لا توجد مواعيد متاحة لباقي اليوم.
3. **اقتراح بدائل**: إذا لم يتبقَ مواعيد لليوم، اقترح مواعيد في الأيام التالية بناءً على جدول الطبيب.
4. **قواعد إضافية**: إذا لم يقم الطبيب بتحديث مواعيده، وجه المريض للاستقبال. ممنوع تقديم أي تشخيص طبي.
PROMPT;

// ── Call AI ──────────────────────────────────────────────────────────────────
$messages = [['role' => 'system', 'content' => $systemPrompt . $doctorsContext]];
$historySlice = array_slice($chatHistory, -10);
foreach ($historySlice as $msg) {
    if (isset($msg['role'], $msg['content'])) {
        $messages[] = ['role' => ($msg['role'] === 'user' ? 'user' : 'assistant'), 'content' => $msg['content']];
    }
}
$messages[] = ['role' => 'user', 'content' => $userMessage];

$payload = json_encode(['model' => 'gpt-4o-mini', 'messages' => $messages, 'temperature' => 0.5], JSON_UNESCAPED_UNICODE);
$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
    CURLOPT_TIMEOUT => 30,
]);
$response = curl_exec($ch);
$openaiData = json_decode($response, true);
$reply = $openaiData['choices'][0]['message']['content'] ?? 'عذراً، حدث خطأ في التواصل مع النظام.';

echo json_encode(['reply' => $reply, 'type' => 'ai_response'], JSON_UNESCAPED_UNICODE);
