<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/audit.php';

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['Admin', 'System Admin'], true)) {
    header("Location: /modules/dashboard.php");
    exit();
}

$connect = hms_db_connect();
if ($connect->connect_error) {
    die("Connection failed: " . $connect->connect_error);
}

hms_audit_ensure_table($connect);

$search = trim($_GET['q'] ?? '');
$query = "SELECT * FROM audit_logs";

if ($search !== '') {
    $safeSearch = mysqli_real_escape_string($connect, $search);
    $query .= " WHERE action_key LIKE '%{$safeSearch}%'
        OR description LIKE '%{$safeSearch}%'
        OR actor_name LIKE '%{$safeSearch}%'
        OR actor_login LIKE '%{$safeSearch}%'
        OR actor_role LIKE '%{$safeSearch}%'
        OR entity_type LIKE '%{$safeSearch}%'
        OR entity_id LIKE '%{$safeSearch}%'
        OR ip_address LIKE '%{$safeSearch}%'
        OR request_uri LIKE '%{$safeSearch}%'";
}

$orderColumn = hms_audit_has_column($connect, 'audit_logs', 'created_at') ? 'created_at' : 'id';
$query .= " ORDER BY {$orderColumn} DESC, id DESC LIMIT 250";
$logs = mysqli_query($connect, $query);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" href="/assets/images/echol.png">
    <link rel="stylesheet" href="/assets/css/responsive.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-slate-100 min-h-screen">
    <div class="min-h-full">
        <?php $activePage = 'audit-log'; require_once __DIR__ . '/nav.php'; ?>

        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">Audit Log</h1>
                <p class="mt-2 text-sm text-slate-600">All tracked actions inside the system.</p>
            </div>
        </header>

        <main>
            <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                <form method="GET" class="rounded-2xl bg-white p-4 shadow-sm border border-slate-200 mb-6">
                    <div class="flex flex-wrap gap-3 items-end">
                        <div class="flex-1 min-w-[240px]">
                            <label for="q" class="block text-sm font-semibold text-slate-700 mb-1">Search</label>
                            <input id="q" name="q" type="search" value="<?= htmlspecialchars($search) ?>" placeholder="Action, actor, IP, path..." class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Search</button>
                        </div>
                        <div>
                            <a href="/includes/audit-log.php" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                        </div>
                    </div>
                </form>

                <div class="rounded-2xl bg-white shadow-sm border border-slate-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Time</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Actor</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Entity</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Source</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Details</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <?php if (!$logs || mysqli_num_rows($logs) === 0): ?>
                                    <tr>
                                        <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">No audit events found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php while ($log = mysqli_fetch_assoc($logs)): ?>
                                        <tr class="align-top">
                                            <td class="px-4 py-4 text-sm text-slate-600 whitespace-nowrap"><?= htmlspecialchars($log['created_at']) ?></td>
                                            <td class="px-4 py-4 text-sm text-slate-700">
                                                <p class="font-semibold text-slate-900"><?= htmlspecialchars($log['action_key']) ?></p>
                                                <p class="mt-1"><?= htmlspecialchars($log['description']) ?></p>
                                            </td>
                                            <td class="px-4 py-4 text-sm text-slate-700">
                                                <p class="font-semibold text-slate-900"><?= htmlspecialchars($log['actor_name'] ?: 'Unknown') ?></p>
                                                <p><?= htmlspecialchars($log['actor_role'] ?: 'Guest') ?></p>
                                                <p><?= htmlspecialchars($log['actor_login'] ?: '-') ?></p>
                                            </td>
                                            <td class="px-4 py-4 text-sm text-slate-700">
                                                <p class="font-semibold text-slate-900"><?= htmlspecialchars($log['entity_type'] ?: '-') ?></p>
                                                <p><?= htmlspecialchars($log['entity_id'] ?: '-') ?></p>
                                            </td>
                                            <td class="px-4 py-4 text-sm text-slate-700">
                                                <p><?= htmlspecialchars($log['ip_address'] ?: '-') ?></p>
                                                <p class="break-all text-slate-500"><?= htmlspecialchars($log['request_uri'] ?: '-') ?></p>
                                            </td>
                                            <td class="px-4 py-4 text-sm text-slate-600 break-all"><?= htmlspecialchars($log['details_json'] ?: '-') ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>

