<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/audit.php';

if (!function_exists('hms_management_connect')) {
    function hms_management_connect(): mysqli
    {
        $connect = hms_db_connect();
        if ($connect->connect_error) {
            die("Connection failed: " . $connect->connect_error);
        }

        return $connect;
    }
}

if (!function_exists('hms_management_current_path')) {
    function hms_management_current_path(): string
    {
        return parse_url($_SERVER['PHP_SELF'] ?? '', PHP_URL_PATH) ?: '';
    }
}

if (!function_exists('hms_management_current_dir')) {
    function hms_management_current_dir(): string
    {
        $dir = str_replace('\\', '/', dirname(hms_management_current_path()));
        $dir = rtrim($dir, '/.');

        return $dir === '' ? '/' : $dir;
    }
}

if (!function_exists('hms_management_sibling_path')) {
    function hms_management_sibling_path(string $fileName): string
    {
        $dir = hms_management_current_dir();
        return ($dir === '/' ? '' : $dir) . '/' . ltrim($fileName, '/');
    }
}
