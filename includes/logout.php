<?php
session_start();
define('HMS_SKIP_AUTO_CONNECT', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/audit.php';

// لو مش عامل login، رجعه للصفحة الرئيسية مباشرة
if (!isset($_SESSION['login']) || $_SESSION['login'] === '') {
    header("Location: /index.php");
    exit;
    }

$connect = hms_db_connect(false);

if ($connect) {
    hms_audit_log($connect, 'auth.logout', [
        'entity_type' => 'auth',
        'entity_id' => strtolower(str_replace(' ', '-', $_SESSION['role'] ?? 'guest')),
        'description' => 'User logged out',
        'details' => [
            'role' => $_SESSION['role'] ?? null,
            'login' => $_SESSION['login'] ?? null,
        ],
    ]);
}

if (!empty($_SESSION['role']) && $_SESSION['role'] === 'Doctor' && !empty($_SESSION['id'])) {
    if (!$connect->connect_error) {
        $connect->query("UPDATE doctors SET statue = 2 WHERE id = " . intval($_SESSION['id']));
        $connect->close();
    }
}

if (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['Admin', 'System Admin', 'User']) && !empty($_SESSION['id'])) {
        if (!$connect->connect_error) {
        $connect->query("UPDATE employ SET employ_statue = 0 WHERE id = " . intval($_SESSION['id']));
        $connect->close();
    }
}

// تدمير السيشن بالكامل
session_unset();
session_destroy();

// حذف الكوكيز (زيادة أمان)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// إعادة التوجيه
header("Location: /index.php");
exit;
?>
