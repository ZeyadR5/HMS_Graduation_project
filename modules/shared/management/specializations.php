<?php
require_once __DIR__ . '/bootstrap.php';

$pageTitle = $pageTitle ?? 'Manage Specializations';
$showAddForm = $showAddForm ?? false;
$showDeleteAction = $showDeleteAction ?? false;

$connect = hms_management_connect();

// Handle Addition (Super Admin only)
if ($showAddForm && isset($_POST['Add'])) {
    hms_require_csrf(hms_management_current_path());

    $name = trim($_POST['name'] ?? '');
    
    if ($name !== '') {
        $stmt = $connect->prepare("INSERT INTO doctorspecilization (`specilization`) VALUES (?)");
        $stmt->bind_param("s", $name);

        if ($stmt->execute()) {
            hms_audit_log($connect, 'specialization.added', [
                'description' => 'A new specialization was added.',
                'entity_type' => 'specialization',
                'details' => [
                    'name' => $name,
                ],
            ]);
            echo "<script>alert('Specialization Added Successfully');</script>";
        } else {
            echo "<script>alert('Something Wrong');</script>";
        }
        $stmt->close();
    }
}

// Handle Deletion
if ($showDeleteAction && isset($_GET['delete'])) {
    hms_require_csrf(hms_management_current_path());

    $specId = intval($_GET['id'] ?? 0);
    if ($specId > 0) {
        $specResult = mysqli_query($connect, "SELECT id, specilization FROM doctorspecilization WHERE id = {$specId}");
        $spec = $specResult ? mysqli_fetch_assoc($specResult) : null;
        mysqli_query($connect, "DELETE FROM doctorspecilization WHERE id = {$specId}");

        if ($spec) {
            hms_audit_log($connect, 'specialization.deleted', [
                'description' => 'A specialization was deleted.',
                'entity_type' => 'specialization',
                'entity_id' => (string)$specId,
                'details' => [
                    'name' => $spec['specilization'] ?? null,
                ],
            ]);
        }
    }

    header("Location: " . hms_management_current_path());
    exit();
}

$specializations = mysqli_query($connect, "SELECT * FROM doctorspecilization ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>
<link rel="icon" href="/assets/images/echol.png">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <link rel="stylesheet" href="/assets/css/add-button.css">
</head>
<body>
    <div class="min-h-full">
        <?php $activePage = 'manage-specializations'; require __DIR__ . '/../../../includes/nav.php'; ?>

        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900"><?= htmlspecialchars($pageTitle) ?></h1>
            </div>
        </header>

        <main>
            <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                
                <?php if ($showAddForm): ?>
                <div class="mb-10 bg-gray-50 p-6 rounded-lg shadow-sm border border-gray-200">
                    <h2 class="text-xl font-semibold mb-4 text-gray-800">Add New Specialization</h2>
                    <form action="#" method="POST" class="max-w-xl">
                        <?= hms_csrf_field() ?>
                        <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="name" class="block text-sm font-semibold leading-6 text-gray-900">Specialization Name</label>
                                <div class="mt-2.5">
                                    <input type="text" name="name" id="name" required class="block w-full rounded-md border-2 px-3.5 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                </div>
                            </div>
                        </div>
                        <div class="mt-6">
                            <button type="submit" name="Add" class="hms-add-btn">
                                <span class="hms-add-btn__text">Add</span>
                                <span class="hms-add-btn__icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" viewBox="0 0 24 24" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" stroke="currentColor" height="24" fill="none"><line y2="19" y1="5" x2="12" x1="12"></line><line y2="12" y1="12" x2="19" x1="5"></line></svg></span>
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <table class="table table-striped table-hover mb-0 w-full">
                        <thead class="table-light">
                            <tr>
                                <th><p class="text-base font-bold text-gray-900 text-center">ID</p></th>
                                <th><p class="text-base font-bold text-gray-900 text-center">Specialization</p></th>
                                <th><p class="text-base font-bold text-gray-900 text-center">Creation Date</p></th>
                                <?php if ($showDeleteAction): ?>
                                    <th><p class="text-base font-bold text-gray-900 text-center">Action</p></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="table-group-divider">
                            <?php if ($specializations && mysqli_num_rows($specializations) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($specializations)): ?>
                                    <tr>
                                        <td class="align-middle"><p class="text-base font-medium text-gray-900 text-center"><?= htmlspecialchars((string)$row['id']) ?></p></td>
                                        <td class="align-middle"><p class="text-base font-medium text-gray-900 text-center"><?= htmlspecialchars($row['specilization']) ?></p></td>
                                        <td class="align-middle"><p class="text-base font-medium text-gray-500 text-center"><?= htmlspecialchars($row['creationDate'] ?? '-') ?></p></td>
                                        <?php if ($showDeleteAction): ?>
                                            <td class="align-middle text-center">
                                                <a href="<?= htmlspecialchars(hms_management_current_path()) ?>?id=<?= (int)$row['id'] ?>&delete=1&<?= hms_csrf_query() ?>" onClick="return confirm('Are you sure you want to delete this specialization?');" class="inline-flex items-center rounded-md bg-red-50 px-3 py-1.5 text-sm font-medium text-red-700 ring-1 ring-inset ring-red-600/20 hover:bg-red-100 transition-colors">
                                                    <i class="bi bi-trash mr-1"></i> Delete
                                                </a>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?= $showDeleteAction ? '4' : '3' ?>" class="text-center py-4 text-gray-500">No specializations found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>
