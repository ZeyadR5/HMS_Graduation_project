<?php
require_once __DIR__ . '/../../includes/auth.php';

$connect = hms_db_connect();
if ($connect->connect_error) {
    die("Connection failed: " . $connect->connect_error);
}

$role = $_SESSION['role'] ?? '';
// Only allow Patient, User, Admin, System Admin
if (!in_array($role, ['Patient', 'User', 'Admin', 'System Admin'])) {
    header("Location: /modules/dashboard.php");
    exit();
}

$searchQuery = $_POST['input'] ?? '';

// 1. Fetch Active Doctors
$sqlStr = "SELECT d.id, d.doctorName, d.specilization, d.docFees, d.statue 
           FROM doctors d 
           WHERE d.is_active = 1";

if (!empty($searchQuery)) {
    $safeSearch = $connect->real_escape_string($searchQuery);
    $sqlStr .= " AND (d.doctorName LIKE '%$safeSearch%' OR d.specilization LIKE '%$safeSearch%')";
}

$sqlStr .= " ORDER BY d.doctorName ASC";
$doctorsResult = $connect->query($sqlStr);
$doctors = [];
$doctorIds = [];

if ($doctorsResult && $doctorsResult->num_rows > 0) {
    while($row = $doctorsResult->fetch_assoc()) {
        $doctors[$row['id']] = $row;
        $doctorIds[] = $row['id'];
    }
}

// 2. Fetch Schedules
$schedules = [];
if (!empty($doctorIds)) {
    $idsList = implode(',', $doctorIds);
    $schResult = $connect->query("SELECT * FROM doctor_schedules WHERE doctor_id IN ($idsList)");
    if ($schResult) {
        while($sch = $schResult->fetch_assoc()) {
            $schedules[$sch['doctor_id']][$sch['day_of_week']] = $sch;
        }
    }
}

// 3. Fetch Overrides for today onwards
$overrides = [];
if (!empty($doctorIds)) {
    $idsList = implode(',', $doctorIds);
    $ovResult = $connect->query("SELECT * FROM doctor_day_overrides WHERE doctor_id IN ($idsList) AND override_date >= CURRENT_DATE()");
    if ($ovResult) {
        while($ov = $ovResult->fetch_assoc()) {
            $overrides[$ov['doctor_id']][$ov['override_date']] = $ov;
        }
    }
}

$phpToday = (int)date('w'); // 0=Sun, 1=Mon... 6=Sat
$dbToday = ($phpToday + 1) % 7; // 0=Sat, 1=Sun...
$todayDateStr = date('Y-m-d');

// DEBUG START
// error_log("DEBUG TIMETABLE: phpToday=$phpToday, dbToday=$dbToday, dateStr=$todayDateStr");
// error_log("DEBUG TIMETABLE SCHEDULES: " . print_r($schedules, true));
// DEBUG END

// 4. Filter to ONLY doctors available today
$availableDoctorsToday = [];
foreach ($doctors as $docId => $doc) {
    $isAvailableToday = false;
    $todayHours = '';
    $todayOverride = $overrides[$docId][$todayDateStr] ?? null;

    if ($todayOverride) {
        if ($todayOverride['status'] === 'custom') {
            $isAvailableToday = true;
            $todayHours = formatTime($todayOverride['start_time']) . ' - ' . formatTime($todayOverride['end_time']);
        }
    } else {
        $todaySch = $schedules[$docId][$dbToday] ?? null;
        if ($todaySch && $todaySch['status'] === 'available') {
            $isAvailableToday = true;
            $todayHours = formatTime($todaySch['start_time']) . ' - ' . formatTime($todaySch['end_time']);
        }
    }

    // If Patient, filter out doctors not available today
    if ($role === 'Patient' && !$isAvailableToday) {
        continue;
    }

    $doc['todayHours'] = $todayHours;
    $doc['isAvailableToday'] = $isAvailableToday;
    
    // For non-patients, we need to find the nearest available day if not available today
    $nearestDayStr = null;
    $nearestHours = null;
    
    if (!$isAvailableToday) {
        for ($i = 1; $i <= 14; $i++) {
            $checkDate = date('Y-m-d', strtotime("+$i days"));
            $checkPhpDay = (int)date('w', strtotime($checkDate));
            $checkDbDay = ($checkPhpDay + 1) % 7;

            $ov = $overrides[$docId][$checkDate] ?? null;
            if ($ov) {
                if ($ov['status'] === 'custom') {
                    $nearestDayStr = date('l, M j', strtotime($checkDate));
                    $nearestHours = formatTime($ov['start_time']) . ' - ' . formatTime($ov['end_time']);
                    break;
                }
            } else {
                $sch = $schedules[$docId][$checkDbDay] ?? null;
                if ($sch && $sch['status'] === 'available') {
                    $nearestDayStr = date('l, M j', strtotime($checkDate));
                    $nearestHours = formatTime($sch['start_time']) . ' - ' . formatTime($sch['end_time']);
                    break;
                }
            }
        }
    }
    
    $doc['nearestDayStr'] = $nearestDayStr;
    $doc['nearestHours'] = $nearestHours;
    $doc['hasAnySchedule'] = !empty($schedules[$docId]);

    $availableDoctorsToday[$docId] = $doc;
}

$doctors = $availableDoctorsToday;

function formatTime($timeStr) {
    return date('h:i A', strtotime($timeStr));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctors Time Table</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>
    <link rel="icon" href="/assets/images/echol.png">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <style>
        .doc-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #f1f5f9;
            transition: transform 0.2s, box-shadow 0.2s;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .doc-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.1);
        }
        .doc-header {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .doc-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            box-shadow: 0 4px 10px rgba(14,165,233,0.3);
            flex-shrink: 0;
        }
        .doc-info h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 800;
            color: #0f172a;
        }
        .doc-spec {
            font-size: 0.85rem;
            color: #0ea5e9;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 0.2rem;
        }
        .doc-body {
            padding: 1.5rem;
            flex-grow: 1;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px dashed #e2e8f0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .info-value {
            font-weight: 700;
            color: #0f172a;
            font-size: 0.9rem;
            text-align: right;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.25rem 0.7rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
        }
        .status-on { background: #dcfce7; color: #166534; }
        .status-off { background: #f1f5f9; color: #64748b; }
        .status-nearest { background: #fef3c7; color: #92400e; }
        
        .doc-footer {
            padding: 1.2rem 1.5rem;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            text-align: center;
        }
        .btn-book {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            background: linear-gradient(135deg, #0ea5e9, #14b8a6);
            color: white;
            font-weight: 700;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(14,165,233,0.25);
        }
        .btn-book:hover {
            transform: translateY(-2px);
            color: white;
            box-shadow: 0 6px 16px rgba(14,165,233,0.35);
        }
        
        .search-bar {
            background: white;
            border-radius: 16px;
            padding: 1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
        }
        .search-input {
            flex-grow: 1;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-weight: 600;
            color: #0f172a;
            transition: all 0.2s;
        }
        .search-input:focus {
            outline: none;
            border-color: #0ea5e9;
        }
        .btn-search {
            background: #0f172a;
            color: white;
            border-radius: 12px;
            padding: 0 1.5rem;
            font-weight: 700;
            border: none;
            transition: all 0.2s;
        }
        .btn-search:hover {
            background: #1e293b;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-full">
        <?php $activePage = 'time-table'; require_once __DIR__ . '/../../includes/nav.php'; ?>

        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900 flex items-center gap-3">
                    <i class="bi bi-calendar-heart text-blue-500"></i> Doctors Time Table
                </h1>
                <p class="mt-2 text-sm text-gray-500">Find available doctors, view their schedules, and book your next appointment.</p>
            </div>
        </header>

        <main>
            <div class="mx-auto max-w-7xl py-8 px-4 sm:px-6 lg:px-8">
                
                <!-- Search -->
                <form class="search-bar flex-wrap md:flex-nowrap" method="POST" action="">
                    <input type="text" name="input" value="<?= htmlspecialchars($searchQuery) ?>" class="search-input w-full md:w-auto" placeholder="Search by doctor name or specialization...">
                    <button type="submit" class="btn-search py-3 md:py-0 w-full md:w-auto">
                        <i class="bi bi-search mr-2"></i> Search
                    </button>
                    <?php if (!empty($searchQuery)): ?>
                        <a href="?" class="btn btn-light d-flex align-items-center rounded-3 fw-bold">Clear</a>
                    <?php endif; ?>
                </form>

                <?php if (empty($doctors)): ?>
                    <div class="text-center py-12 bg-white rounded-2xl shadow-sm border border-gray-100">
                        <i class="bi bi-search text-gray-300" style="font-size: 3rem;"></i>
                        <h3 class="mt-4 text-lg font-bold text-gray-900">No doctors found</h3>
                        <p class="text-gray-500">Try adjusting your search criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach($doctors as $docId => $doc): 
                            // Get initials
                            $nameParts = explode(' ', $doc['doctorName']);
                            $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
                        ?>
                            <div class="doc-card">
                                <div class="doc-header">
                                    <div class="rounded-full overflow-hidden border-2 border-white ring-2 ring-indigo-100 shadow-md animate-fade-in" style="width:60px; height:60px; flex-shrink: 0;">
                                        <img src="<?= hms_get_doctor_avatar($doc['doctorName']) ?>" alt="Dr. <?= htmlspecialchars($doc['doctorName']) ?>" class="w-full h-full object-cover">
                                    </div>
                                    <div class="doc-info">
                                        <h3><?= htmlspecialchars($doc['doctorName']) ?></h3>
                                        <div class="doc-spec"><?= htmlspecialchars($doc['specilization']) ?></div>
                                    </div>
                                </div>
                                <div class="doc-body">
                                    <div class="info-row">
                                        <div class="info-label"><i class="bi bi-cash-stack"></i> Consultation Fees</div>
                                        <div class="info-value text-green-600"><?= htmlspecialchars($doc['docFees']) ?> EGP</div>
                                    </div>
                                    
                                    <div class="info-row <?= ($doc['isAvailableToday']) ? 'border-none pb-0' : '' ?>">
                                        <div class="info-label"><i class="bi bi-clock-history"></i> Today's Status</div>
                                        <div class="info-value">
                                            <?php if (!$doc['hasAnySchedule']): ?>
                                                <span class="status-badge" style="background: #e2e8f0; color: #475569;">● Not Scheduled Yet</span>
                                            <?php elseif ($doc['isAvailableToday']): ?>
                                                <span class="status-badge status-on mb-1">● Available</span>
                                                <div class="text-xs text-gray-500 font-bold"><?= $doc['todayHours'] ?></div>
                                            <?php else: ?>
                                                <span class="status-badge status-off mb-1">● Day Off</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if ($role !== 'Patient' && $doc['hasAnySchedule']): ?>
                                    <div class="mt-4 pt-3 border-t border-dashed border-gray-200">
                                        <div class="text-sm font-bold text-gray-700 mb-2"><i class="bi bi-calendar-week"></i> Weekly Schedule:</div>
                                        <div class="space-y-1">
                                            <?php 
                                            $dayNames = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                                            for($d=0; $d<7; $d++) {
                                                if (isset($schedules[$docId][$d]) && $schedules[$docId][$d]['status'] === 'available') {
                                                    $sch = $schedules[$docId][$d];
                                                    echo '<div class="flex justify-between text-xs text-gray-600 bg-gray-50 p-1.5 rounded">';
                                                    echo '<span class="font-semibold">' . $dayNames[$d] . '</span>';
                                                    echo '<span>' . formatTime($sch['start_time']) . ' - ' . formatTime($sch['end_time']) . '</span>';
                                                    echo '</div>';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($role === 'Patient' || $role === 'User'): ?>
                                    <div class="doc-footer">
                                        <?php 
                                            // Patient goes to New-reservation, User goes to new_appoint
                                            $bookLink = ($role === 'Patient') ? '/modules/patient/New-reservation.php' : '/modules/user/new_appoint.php';
                                            // We could append ?doctor_id=... if the booking page supports it, but for now we just link to it.
                                        ?>
                                        <a href="<?= $bookLink ?>" class="btn-book">
                                            <i class="bi bi-calendar2-check"></i> Book Appointment
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>
    <script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>
