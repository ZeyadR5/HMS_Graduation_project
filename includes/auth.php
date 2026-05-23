<?php
date_default_timezone_set('Africa/Cairo');
if (!defined('HMS_SKIP_AUTO_CONNECT')) {
    define('HMS_SKIP_AUTO_CONNECT', true);
}
require_once __DIR__ . '/config.php';

if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
require_once __DIR__ . '/audit.php';

function hms_role_home(string $role): string
{
    return match ($role) {
        'System Admin', 'Admin', 'Doctor', 'Patient', 'User' => '/modules/dashboard.php',
        default => '/index.php',
    };
}

function hms_redirect_with_popup(string $message, string $target = '/index.php'): void
{
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $safeTarget = htmlspecialchars($target, ENT_QUOTES, 'UTF-8');

    echo "<script>alert('{$safeMessage}'); window.location.href='{$safeTarget}';</script>";
    exit();
}

function hms_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function hms_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(hms_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function hms_csrf_query(): string
{
    return 'csrf_token=' . urlencode(hms_csrf_token());
}

function hms_validate_csrf(?string $token = null): bool
{
    $token = $token ?? ($_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '');
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function hms_require_csrf(string $target = '/modules/dashboard.php'): void
{
    if (!hms_validate_csrf()) {
        hms_redirect_with_popup('Security check failed. Please try again.', $target);
    }
}

function hms_rate_limit(string $bucket, int $maxAttempts, int $windowSeconds): bool
{
    $now = time();
    $_SESSION['rate_limits'][$bucket] = array_values(array_filter(
        $_SESSION['rate_limits'][$bucket] ?? [],
        static fn($attemptTime) => is_int($attemptTime) && ($now - $attemptTime) < $windowSeconds
    ));

    if (count($_SESSION['rate_limits'][$bucket]) >= $maxAttempts) {
        return false;
    }

    $_SESSION['rate_limits'][$bucket][] = $now;
    return true;
}

function hms_is_authorized_path(string $role, string $path): bool
{
    if ($path === '/modules/dashboard.php') {
        return true;
    }

    // Shared management files are partials. They must be reached through
    // the role-specific admin/super-admin wrappers, not directly.
    if (str_starts_with($path, '/modules/shared/management/')) {
        return false;
    }

    // Shared module and includes pages are accessible to all logged-in users
    if (str_starts_with($path, '/modules/shared/') || str_starts_with($path, '/includes/')) {
        return true;
    }

    $rolePaths = [
        'System Admin' => ['/modules/super-admin/', '/modules/admin/'],
        'Admin' => ['/modules/admin/'],
        'Doctor' => ['/modules/doctor/'],
        'Patient' => ['/modules/patient/'],
        'User' => ['/modules/user/'],
    ];

    foreach ($rolePaths[$role] ?? [] as $prefix) {
        if (str_starts_with($path, $prefix)) {
            return true;
        }
    }

    return false;
}

if (empty($_SESSION['logged_in']) || empty($_SESSION['login']) || empty($_SESSION['role'])) {
    header("Location: /index.php");
    exit();
}

$currentPath = parse_url($_SERVER['PHP_SELF'] ?? '', PHP_URL_PATH) ?: '';
$auditConnect = hms_db_connect(false);
$auditEnabled = $auditConnect instanceof mysqli && !$auditConnect->connect_error;

if ($auditEnabled) {
    hms_audit_auto_log_request($auditConnect);
}

if (str_starts_with($currentPath, '/modules/') && !hms_is_authorized_path($_SESSION['role'], $currentPath)) {
    if ($auditEnabled) {
        hms_audit_log($auditConnect, 'access.denied', [
            'entity_type' => 'request',
            'entity_id' => basename($currentPath) ?: $currentPath,
            'description' => 'Unauthorized access attempt',
            'details' => [
                'path' => $currentPath,
                'role' => $_SESSION['role'] ?? null,
            ],
        ]);
        $auditConnect->close();
    }

    hms_redirect_with_popup(
        'Access denied. You are not allowed to open this page.',
        hms_role_home($_SESSION['role'])
    );
}

if ($auditEnabled) {
    $auditConnect->close();
}
