<?php
require_once __DIR__ . '/env.php';

if (!function_exists('hms_db_connect')) {
    function hms_db_connect(bool $dieOnError = true): ?mysqli
    {
        $host = hms_env('HMS_DB_HOST', '127.0.0.1');
        $username = hms_env('HMS_DB_USER', 'root');
        $password = hms_env('HMS_DB_PASS', '');
        $dbname = hms_env('HMS_DB_NAME', 'hms');
        $port = (int)hms_env('HMS_DB_PORT', '3306');
        $portsToTry = [$port];
        if ($port !== 3306) {
            $portsToTry[] = 3306;
        }
        $connection = null;
        $lastError = '';

        foreach ($portsToTry as $p) {
            try {
                $conn = @new mysqli($host, $username, $password, $dbname, $p);
                if (!$conn->connect_errno) {
                    $connection = $conn;
                    break;
                }
                $lastError = $conn->connect_error;
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        if (!$connection) {
            if ($dieOnError) {
                die("Connection failed: " . htmlspecialchars($lastError));
            }
            return null;
        }

        $connection->set_charset('utf8mb4');
        return $connection;
    }
}

$host = hms_env('HMS_DB_HOST', '127.0.0.1');
$username = hms_env('HMS_DB_USER', 'root');
$password = hms_env('HMS_DB_PASS', '');
$dbname = hms_env('HMS_DB_NAME', 'hms');
if (!defined('HMS_SKIP_AUTO_CONNECT') || HMS_SKIP_AUTO_CONNECT !== true) {
    $connect = hms_db_connect();
}

if (!function_exists('hms_get_doctor_avatar')) {
    /**
     * Detects doctor gender based on name keywords and returns the correct avatar image path.
     */
    function hms_get_doctor_avatar(string $doctorName): string
    {
        $name = mb_strtolower($doctorName);
        $femaleNames = [
            'nabila', 'hoda', 'amnaa', 'salma', 'sara', 'saraa', 'fatma', 'yasmine', 'mona', 
            'marwa', 'nour', 'reem', 'nada', 'alaa', 'aya', 'menna', 'mai', 'rania', 'heba', 
            'shaimaa', 'ola', 'ghada', 'shery', 'dina', 'yasmin', 'amal', 'fatimah', 'mariam', 
            'sally', 'doaa', 'eman', 'sherihan', 'asmaa', 'nourhan', 'rawan', 'marian', 'engy'
        ];
        foreach ($femaleNames as $fn) {
            if (strpos($name, $fn) !== false) {
                return '/assets/images/doctor-avatar-female.png';
            }
        }
        return '/assets/images/doctor-avatar-male.png';
    }
}

if (!function_exists('hms_get_specialization_icon')) {
    /**
     * Maps a doctor specialization to the corresponding custom vector icon path.
     */
    function hms_get_specialization_icon(string $spec): string
    {
        $spec = mb_strtolower(trim($spec));
        if (strpos($spec, 'cardio') !== false || strpos($spec, 'heart') !== false) {
            return '/assets/images/spec-cardiology.png';
        }
        if (strpos($spec, 'dent') !== false || strpos($spec, 'teeth') !== false) {
            return '/assets/images/spec-dentist.png';
        }
        if (strpos($spec, 'ortho') !== false || strpos($spec, 'bone') !== false) {
            return '/assets/images/spec-orthopedics.png';
        }
        if (strpos($spec, 'pediatric') !== false || strpos($spec, 'child') !== false || strpos($spec, 'kid') !== false) {
            return '/assets/images/spec-pediatric.png';
        }
        if (strpos($spec, 'derma') !== false || strpos($spec, 'skin') !== false) {
            return '/assets/images/spec-dermatology.png';
        }
        if (strpos($spec, 'gyn') !== false || strpos($spec, 'obstetric') !== false || strpos($spec, 'women') !== false || strpos($spec, 'preg') !== false) {
            return '/assets/images/spec-obstetrician.png';
        }
        return '/assets/images/spec-general.png';
    }
}
?>
