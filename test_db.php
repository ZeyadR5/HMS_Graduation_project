<?php
require_once 'includes/config.php';
$connect = hms_db_connect();

$sqlStr = "SELECT d.id FROM doctors d WHERE d.is_active = 1 AND d.statue = 1";
$doctorsResult = $connect->query($sqlStr);
$doctorIds = [];
while($row = $doctorsResult->fetch_assoc()) {
    $doctorIds[] = $row['id'];
}

$schedules = [];
if (!empty($doctorIds)) {
    $idsList = implode(',', $doctorIds);
    $schResult = $connect->query("SELECT * FROM doctor_schedules WHERE doctor_id IN ($idsList)");
    while($sch = $schResult->fetch_assoc()) {
        $schedules[$sch['doctor_id']][$sch['day_of_week']] = $sch;
    }
}

$phpToday = (int)date('w'); // 0=Sun, 1=Mon... 6=Sat
$dbToday = ($phpToday + 1) % 7; // 0=Sat, 1=Sun...
$todayDateStr = date('Y-m-d');

echo "phpToday = $phpToday \n";
echo "dbToday = $dbToday \n";
echo "dateStr = $todayDateStr \n";
echo "Schedules: \n";
print_r($schedules);
