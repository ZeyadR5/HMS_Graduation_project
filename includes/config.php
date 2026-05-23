<?php
if (!function_exists('hms_env')) {
    function hms_env(string $key, ?string $fallback = null): ?string
    {
        $value = getenv($key);
        return ($value === false || $value === '') ? $fallback : $value;
    }
}

if (!function_exists('hms_db_connect')) {
    function hms_db_connect(bool $dieOnError = true): ?mysqli
    {
        $host = hms_env('HMS_DB_HOST', 'localhost');
        $username = hms_env('HMS_DB_USER', 'root');
        $password = hms_env('HMS_DB_PASS', '');
        $dbname = hms_env('HMS_DB_NAME', 'hms');

        $connection = @new mysqli($host, $username, $password, $dbname);
        if ($connection->connect_error) {
            if ($dieOnError) {
                die("Connection failed");
            }
            return null;
        }

        $connection->set_charset('utf8mb4');
        return $connection;
    }
}

$host = hms_env('HMS_DB_HOST', 'localhost');
$username = hms_env('HMS_DB_USER', 'root');
$password = hms_env('HMS_DB_PASS', '');
$dbname = hms_env('HMS_DB_NAME', 'hms');
if (!defined('HMS_SKIP_AUTO_CONNECT') || HMS_SKIP_AUTO_CONNECT !== true) {
    $connect = hms_db_connect();
}
?>
