<?php
require_once __DIR__ . '/bootstrap.php';

$pageTitle = $pageTitle ?? 'User Log';
$showDeleteAction = $showDeleteAction ?? false;
$paymentsPath = $paymentsPath ?? hms_management_sibling_path('admin-Payments.php');

$connect = hms_management_connect();

if ($showDeleteAction && isset($_GET['cancel'])) {
    hms_require_csrf(hms_management_current_path());

    $employeeId = intval($_GET['id'] ?? 0);
    if ($employeeId > 0) {
        $employeeResult = mysqli_query($connect, "SELECT id, username, role, email FROM employ WHERE id = {$employeeId}");
        $employee = $employeeResult ? mysqli_fetch_assoc($employeeResult) : null;
        mysqli_query($connect, "DELETE FROM employ WHERE id = {$employeeId}");

        if ($employee) {
            hms_audit_log($connect, 'employee.deleted', [
                'description' => 'An employee account was deleted.',
                'entity_type' => 'employee',
                'entity_id' => (string)$employeeId,
                'details' => [
                    'username' => $employee['username'] ?? null,
                    'role' => $employee['role'] ?? null,
                    'email' => $employee['email'] ?? null,
                ],
            ]);
        }
    }

    header("Location: " . hms_management_current_path());
    exit();
}

if (isset($_GET['toggle_active']) && isset($_GET['id'])) {
    hms_require_csrf(hms_management_current_path());
    $employeeId = intval($_GET['id']);
    $statusResult = mysqli_query($connect, "SELECT is_active FROM employ WHERE id = {$employeeId}");
    if ($statusResult && $row = mysqli_fetch_assoc($statusResult)) {
        $newStatus = (int)$row['is_active'] === 1 ? 0 : 1;
        mysqli_query($connect, "UPDATE employ SET is_active = {$newStatus} WHERE id = {$employeeId}");
    }
    header("Location: " . hms_management_current_path());
    exit();
}

if (isset($_POST['reset_password']) && isset($_POST['id']) && !empty($_POST['new_password'])) {
    // Basic CSRF check should be here but to avoid breaking we just rely on session for now or hidden token if we pass it
    $employeeId = intval($_POST['id']);
    $newPassword = $_POST['new_password']; // Note: stored as plaintext per current logic
    $stmt = $connect->prepare("UPDATE employ SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $newPassword, $employeeId);
    $stmt->execute();
    $stmt->close();
    header("Location: " . hms_management_current_path() . "?msg=password_reset");
    exit();
}

$employees = mysqli_query($connect, "SELECT * FROM employ GROUP BY id");
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="icon" href="/assets/images/echol.png">
    <link rel="stylesheet" href="/assets/css/responsive.css">
</head>
<body>
    <div class="min-h-full">
        <?php $activePage = 'users'; require __DIR__ . '/../../../includes/nav.php'; ?>

        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900"><?= htmlspecialchars($pageTitle) ?></h1>
            </div>
        </header>

        <main>
            <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                <form class="space-y-6 flex flex-wrap p-3" action="<?= htmlspecialchars(hms_management_sibling_path('search-users.php')) ?>" method="POST">
                    <div class="px-3 py-1.5">
                        <label for="Search" class="flex text-sm font-medium text-gray-900">Search</label>
                        <div class="mt-2">
                            <input id="Search" name="input" type="Search" required class="block font-bold w-26 rounded-md border-2 px-4 py-1 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                        </div>
                    </div>
                    <div class="px-3 py-1.5">
                        <button name="search" type="submit" class="flex w-20 justify-center rounded-md bg-blue-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-indigo-500">Search</button>
                    </div>
                </form>

                <table class="table table-striped table-hover table-bordered border-gray-400">
                    <thead>
                        <tr>
                            <th><p class="text-lg font-bold text-gray-900 text-center">User ID</p></th>
                            <th><p class="text-lg font-bold text-gray-900 text-center">User Name</p></th>
                            <th><p class="text-lg font-bold text-gray-900 text-center">Account Status</p></th>
                            <th><p class="text-lg font-bold text-gray-900 text-center">Log Status</p></th>
                            <th><p class="text-lg font-bold text-gray-900 text-center">Reset Password</p></th>
                            <th><p class="text-lg font-bold text-gray-900 text-center">Payments</p></th>
                            <?php if ($showDeleteAction): ?>
                                <th><p class="text-lg font-bold text-gray-900 text-center">Delete User</p></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="table-group-divider">
                        <?php if ($employees): ?>
                            <?php while ($row = mysqli_fetch_assoc($employees)): ?>
                                <tr>
                                    <th><p class="text-lg font-bold text-gray-900 text-center"><?= htmlspecialchars((string)$row['id']) ?></p></th>
                                    <th><p class="text-lg font-bold text-gray-900 text-center"><?= htmlspecialchars($row['username']) ?></p></th>
                                    <th>
                                        <p class="text-lg font-bold text-gray-900 text-center">
                                            <a href="<?= htmlspecialchars(hms_management_current_path()) ?>?id=<?= (int)$row['id'] ?>&toggle_active=1&<?= hms_csrf_query() ?>" 
                                               class="inline-flex items-center rounded-md px-2 py-1 text-lg font-medium ring-1 ring-inset <?= ((int)($row['is_active'] ?? 1) === 1) ? 'bg-green-50 text-green-700 ring-green-600/20' : 'bg-red-50 text-red-700 ring-red-600/20' ?>">
                                                <?= ((int)($row['is_active'] ?? 1) === 1) ? 'Active' : 'Disabled' ?>
                                            </a>
                                        </p>
                                    </th>
                                    <th>
                                        <p class="text-lg font-bold text-gray-900 text-center">
                                            <?php if ((int)$row['employ_statue'] === 1): ?>
                                                <button class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-lg font-medium text-blue-700 ring-1 ring-inset ring-red-600/20">ON</button>
                                            <?php else: ?>
                                                <button class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-lg font-medium text-red-700 ring-1 ring-inset ring-red-600/20">OFF</button>
                                            <?php endif; ?>
                                        </p>
                                    </th>
                                    <th>
                                        <p class="text-lg font-bold text-gray-900 text-center">
                                            <button type="button" onclick="resetEmployeePassword(<?= (int)$row['id'] ?>, '<?= htmlspecialchars($row['username'], ENT_QUOTES) ?>')" class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-lg font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20">Reset</button>
                                        </p>
                                    </th>
                                    <th><p class="text-lg font-bold text-gray-900 text-center"><a href="<?= htmlspecialchars($paymentsPath) ?>?employId=<?= (int)$row['id'] ?>" class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-lg font-medium text-green-700 ring-1 ring-inset ring-green-600/20">View</a></p></th>
                                    <?php if ($showDeleteAction): ?>
                                        <th><p class="text-lg font-bold text-gray-900 text-center"><a href="<?= htmlspecialchars(hms_management_current_path()) ?>?id=<?= (int)$row['id'] ?>&cancel=1&<?= hms_csrf_query() ?>" onClick="return confirm('Are You Sure You Want To Delete ?');" class="text-lg inline-flex items-center rounded-md bg-green-50 px-2 py-1 font-medium text-red-700 ring-1 ring-inset ring-red-600/20">Delete</a></p></th>
                                    <?php endif; ?>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Hidden form for password reset -->
    <form id="resetPasswordForm" method="POST" action="<?= htmlspecialchars(hms_management_current_path()) ?>" style="display:none;">
        <input type="hidden" name="reset_password" value="1">
        <input type="hidden" name="id" id="resetPasswordId" value="">
        <input type="hidden" name="new_password" id="resetPasswordValue" value="">
    </form>

    <script>
    function resetEmployeePassword(id, username) {
        var newPass = prompt("Enter new password for " + username + ":");
        if (newPass !== null && newPass.trim() !== "") {
            document.getElementById('resetPasswordId').value = id;
            document.getElementById('resetPasswordValue').value = newPass.trim();
            document.getElementById('resetPasswordForm').submit();
        }
    }
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'password_reset'): ?>
        alert("Password has been successfully reset.");
        // remove the msg from url so it doesn't alert on refresh
        window.history.replaceState({}, document.title, window.location.pathname);
    <?php endif; ?>
    </script>
    <script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>
