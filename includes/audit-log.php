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

// ── Filters ──────────────────────────────────────────────────────────────────
$search    = trim($_GET['q']       ?? '');
$dateFrom  = trim($_GET['date_from'] ?? '');
$dateTo    = trim($_GET['date_to']   ?? '');
$userFilter= trim($_GET['user']      ?? '');

// ── Pagination ───────────────────────────────────────────────────────────────
$perPage = 25;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$orderColumn = hms_audit_has_column($connect, 'audit_logs', 'created_at') ? 'created_at' : 'id';

// Build WHERE
$conditions = [];
$params     = [];
$types      = '';

if ($search !== '') {
    $conditions[] = "(action_key LIKE ? OR description LIKE ? OR actor_name LIKE ? OR actor_login LIKE ? OR actor_role LIKE ? OR entity_type LIKE ? OR ip_address LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like,$like,$like,$like,$like,$like,$like]);
    $types .= 'sssssss';
}

if ($userFilter !== '') {
    $conditions[] = "(actor_name LIKE ? OR actor_login LIKE ?)";
    $likeU = "%{$userFilter}%";
    $params[] = $likeU; $params[] = $likeU;
    $types .= 'ss';
}

if ($dateFrom !== '') {
    $conditions[] = "DATE({$orderColumn}) >= ?";
    $params[] = $dateFrom;
    $types .= 's';
}

if ($dateTo !== '') {
    $conditions[] = "DATE({$orderColumn}) <= ?";
    $params[] = $dateTo;
    $types .= 's';
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Count total rows
$countSql = "SELECT COUNT(*) FROM audit_logs {$where}";
$countStmt = $connect->prepare($countSql);
if ($types && $params) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRows = (int)$countStmt->get_result()->fetch_row()[0];
$countStmt->close();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Fetch page data
$dataSql = "SELECT * FROM audit_logs {$where} ORDER BY {$orderColumn} DESC, id DESC LIMIT {$perPage} OFFSET {$offset}";
$dataStmt = $connect->prepare($dataSql);
if ($types && $params) {
    $dataStmt->bind_param($types, ...$params);
}
$dataStmt->execute();
$logs = $dataStmt->get_result();

// Helper: build query string preserving current filters
function buildUrl(array $overrides = []): string {
    $base = array_filter([
        'q'         => $_GET['q']         ?? '',
        'user'      => $_GET['user']      ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to'   => $_GET['date_to']   ?? '',
        'page'      => $_GET['page']      ?? '',
    ]);
    $merged = array_filter(array_merge($base, $overrides), fn($v) => $v !== '');
    return '/includes/audit-log.php' . ($merged ? '?' . http_build_query($merged) : '');
}

// Action badge colors
function actionBadge(string $key): string {
    $key = strtolower($key);
    if (str_contains($key, 'login'))  return 'bg-emerald-100 text-emerald-700';
    if (str_contains($key, 'logout')) return 'bg-slate-100 text-slate-600';
    if (str_contains($key, 'delete') || str_contains($key, 'denied')) return 'bg-red-100 text-red-700';
    if (str_contains($key, 'create') || str_contains($key, 'register')) return 'bg-blue-100 text-blue-700';
    if (str_contains($key, 'update') || str_contains($key, 'edit') || str_contains($key, 'recover')) return 'bg-amber-100 text-amber-700';
    if (str_contains($key, 'fail') || str_contains($key, 'error')) return 'bg-rose-100 text-rose-700';
    return 'bg-indigo-100 text-indigo-700';
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log</title>
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>
    <link rel="icon" href="/assets/images/echol.png">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen">
    <div class="min-h-full">
        <?php $activePage = 'audit-log'; require_once __DIR__ . '/nav.php'; ?>

        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-gray-900 flex items-center gap-2">
                        <i class="bi bi-shield-lock-fill text-indigo-600"></i> Audit Log
                    </h1>
                    <p class="mt-1 text-sm text-slate-500">All tracked actions inside the system &mdash; <span class="font-semibold text-slate-700"><?= number_format($totalRows) ?></span> total events</p>
                </div>
            </div>
        </header>

        <main>
            <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8 space-y-5">

                <!-- ── Filter Form ──────────────────────────────────────────── -->
                <form method="GET" class="rounded-2xl bg-white p-5 shadow-sm border border-slate-200">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">
                                <i class="bi bi-search mr-1"></i>Search
                            </label>
                            <input name="q" type="search" value="<?= htmlspecialchars($search) ?>"
                                placeholder="Action, IP, path..."
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800 focus:border-indigo-400 focus:outline-none focus:bg-white transition">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">
                                <i class="bi bi-person mr-1"></i>Username / Name
                            </label>
                            <input name="user" type="search" value="<?= htmlspecialchars($userFilter) ?>"
                                placeholder="Actor name or login..."
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800 focus:border-indigo-400 focus:outline-none focus:bg-white transition">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">
                                <i class="bi bi-calendar-event mr-1"></i>Date From
                            </label>
                            <input name="date_from" type="date" value="<?= htmlspecialchars($dateFrom) ?>"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800 focus:border-indigo-400 focus:outline-none focus:bg-white transition">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">
                                <i class="bi bi-calendar-check mr-1"></i>Date To
                            </label>
                            <input name="date_to" type="date" value="<?= htmlspecialchars($dateTo) ?>"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800 focus:border-indigo-400 focus:outline-none focus:bg-white transition">
                        </div>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <button type="submit"
                            class="rounded-xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition flex items-center gap-2">
                            <i class="bi bi-funnel-fill"></i> Apply Filters
                        </button>
                        <a href="/includes/audit-log.php"
                            class="rounded-xl border border-slate-300 px-5 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition flex items-center gap-2">
                            <i class="bi bi-x-circle"></i> Reset
                        </a>
                    </div>
                </form>

                <!-- ── Table ────────────────────────────────────────────────── -->
                <div class="rounded-2xl bg-white shadow-sm border border-slate-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Time</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Action</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Actor</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Entity</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Source</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Details</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <?php if ($logs->num_rows === 0): ?>
                                    <tr>
                                        <td colspan="6" class="px-4 py-14 text-center">
                                            <i class="bi bi-inbox text-4xl text-slate-300 block mb-2"></i>
                                            <p class="text-sm text-slate-500">No audit events found for the selected filters.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php while ($log = $logs->fetch_assoc()): ?>
                                        <tr class="align-top hover:bg-slate-50 transition-colors">
                                            <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap">
                                                <?php
                                                    $ts = strtotime($log['created_at'] ?? '');
                                                    echo $ts ? date('Y-m-d', $ts) . '<br><span class="text-slate-400">' . date('H:i:s', $ts) . '</span>' : htmlspecialchars($log['created_at'] ?? '-');
                                                ?>
                                            </td>
                                            <td class="px-4 py-3 text-sm max-w-[200px]">
                                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold <?= actionBadge($log['action_key'] ?? '') ?>">
                                                    <?= htmlspecialchars($log['action_key'] ?? '-') ?>
                                                </span>
                                                <p class="mt-1.5 text-slate-500 text-xs leading-relaxed"><?= htmlspecialchars($log['description'] ?? '') ?></p>
                                            </td>
                                            <td class="px-4 py-3 text-sm">
                                                <p class="font-semibold text-slate-900"><?= htmlspecialchars($log['actor_name'] ?: 'Unknown') ?></p>
                                                <p class="text-xs text-indigo-600 font-medium"><?= htmlspecialchars($log['actor_role'] ?: 'Guest') ?></p>
                                                <p class="text-xs text-slate-400"><?= htmlspecialchars($log['actor_login'] ?: '-') ?></p>
                                            </td>
                                            <td class="px-4 py-3 text-sm">
                                                <p class="font-semibold text-slate-800"><?= htmlspecialchars($log['entity_type'] ?: '-') ?></p>
                                                <p class="text-xs text-slate-400"><?= htmlspecialchars($log['entity_id'] ?: '-') ?></p>
                                            </td>
                                            <td class="px-4 py-3 text-xs text-slate-600 max-w-[160px]">
                                                <p class="font-mono"><?= htmlspecialchars($log['ip_address'] ?: '-') ?></p>
                                                <p class="break-all text-slate-400 mt-0.5"><?= htmlspecialchars($log['request_uri'] ?: '-') ?></p>
                                            </td>
                                            <td class="px-4 py-3 text-xs text-slate-500 break-all max-w-[180px]">
                                                <?= htmlspecialchars($log['details_json'] ?: '-') ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- ── Pagination ──────────────────────────────────────── -->
                    <?php if ($totalPages > 1): ?>
                    <div class="border-t border-slate-100 px-5 py-4 flex items-center justify-between flex-wrap gap-3 bg-slate-50">
                        <p class="text-sm text-slate-500">
                            Showing <span class="font-semibold text-slate-700"><?= number_format($offset + 1) ?></span>
                            – <span class="font-semibold text-slate-700"><?= number_format(min($offset + $perPage, $totalRows)) ?></span>
                            of <span class="font-semibold text-slate-700"><?= number_format($totalRows) ?></span> events
                        </p>
                        <nav class="flex items-center gap-1">
                            <?php if ($page > 1): ?>
                                <a href="<?= buildUrl(['page' => $page - 1]) ?>"
                                   class="px-3 py-1.5 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-white hover:border-indigo-300 transition flex items-center gap-1">
                                   <i class="bi bi-chevron-left text-xs"></i> Prev
                                </a>
                            <?php endif; ?>

                            <?php
                            $range = 2;
                            $start = max(1, $page - $range);
                            $end   = min($totalPages, $page + $range);
                            if ($start > 1): ?>
                                <a href="<?= buildUrl(['page' => 1]) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-white transition">1</a>
                                <?php if ($start > 2): ?><span class="px-1 text-slate-400">…</span><?php endif; ?>
                            <?php endif; ?>

                            <?php for ($p = $start; $p <= $end; $p++): ?>
                                <a href="<?= buildUrl(['page' => $p]) ?>"
                                   class="px-3 py-1.5 rounded-lg text-sm font-semibold transition border
                                          <?= $p === $page
                                              ? 'bg-indigo-600 border-indigo-600 text-white shadow'
                                              : 'border-slate-200 text-slate-600 hover:bg-white hover:border-indigo-300' ?>">
                                    <?= $p ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($end < $totalPages): ?>
                                <?php if ($end < $totalPages - 1): ?><span class="px-1 text-slate-400">…</span><?php endif; ?>
                                <a href="<?= buildUrl(['page' => $totalPages]) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-white transition"><?= $totalPages ?></a>
                            <?php endif; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="<?= buildUrl(['page' => $page + 1]) ?>"
                                   class="px-3 py-1.5 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-white hover:border-indigo-300 transition flex items-center gap-1">
                                   Next <i class="bi bi-chevron-right text-xs"></i>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>
    <script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>
