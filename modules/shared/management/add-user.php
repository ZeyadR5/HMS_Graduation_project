<?php
require_once __DIR__ . '/bootstrap.php';

$pageTitle = $pageTitle ?? 'Add New User';
$roleOptions = $roleOptions ?? ['System Admin', 'Admin', 'User'];

if (isset($_POST['Add'])) {
    hms_require_csrf(hms_management_current_path());

    $name = trim($_POST['name'] ?? '');
    $role = trim($_POST['Role'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['Password'] ?? '');

    $connect = hms_management_connect();

    $checkStmt = $connect->prepare("SELECT id FROM employ WHERE email = ? LIMIT 1");
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $existing = $checkStmt->get_result();

    if ($existing->num_rows > 0) {
        $checkStmt->close();
        $connect->close();
        echo "<script>alert('Email already exists!');</script>";
    } elseif ($name === '' || $role === '' || $email === '' || $password === '') {
        $checkStmt->close();
        $connect->close();
        echo "<script>alert('Please fill all fields.');</script>";
    } elseif (!in_array($role, $roleOptions, true)) {
        $checkStmt->close();
        $connect->close();
        echo "<script>alert('Selected role is not allowed here.');</script>";
    } else {
        $checkStmt->close();
        $insertStmt = $connect->prepare("
            INSERT INTO employ (`username`, `role`, `email`, `password`)
            VALUES (?, ?, ?, ?)
        ");
        $insertStmt->bind_param("ssss", $name, $role, $email, $password);

        if ($insertStmt->execute()) {
            hms_audit_log($connect, 'employee.created', [
                'description' => 'A new employee account was created.',
                'entity_type' => 'employee',
                'entity_id' => (string)$connect->insert_id,
                'details' => [
                    'username' => $name,
                    'role' => $role,
                    'email' => $email,
                ],
            ]);
            echo "<script>alert('User Added Successfully');</script>";
        } else {
            echo "<script>alert('Something Wrong');</script>";
        }

        $insertStmt->close();
        $connect->close();
    }
}
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
        <?php $activePage = 'add-user'; require __DIR__ . '/../../../includes/nav.php'; ?>

        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-gray-700"><?= htmlspecialchars($pageTitle) ?></h1>
            </div>
        </header>

        <main>
            <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                <form action="<?= htmlspecialchars(hms_management_current_path()) ?>" method="POST" class="mx-auto max-w-xl sm:mt-20">
                    <?= hms_csrf_field() ?>
                    <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="name" class="block text-sm font-semibold leading-6 text-gray-900">User name</label>
                            <div class="mt-2.5">
                                <input type="text" name="name" id="name" class="block w-full rounded-md border-2 px-3.5 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                            </div>
                        </div>
                        <div class="sm:col-span-2">
                            <div class="mt-2.5">
                                <label for="Role" class="block text-sm font-semibold leading-6 text-gray-900">Role</label>
                                <div class="mt-2">
                                    <select id="Role" name="Role" class="block w-full rounded-md border-2 px-3.5 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                        <?php $defaultRole = end($roleOptions); reset($roleOptions); ?>
                                        <?php foreach ($roleOptions as $roleOption): ?>
                                            <option value="<?= htmlspecialchars($roleOption) ?>" <?= $roleOption === $defaultRole ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($roleOption) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="email" class="block text-sm font-semibold leading-6 text-gray-900">Email</label>
                            <div class="mt-2.5">
                                <input type="email" name="email" id="email" class="block w-full rounded-md border-2 px-3.5 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                            </div>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="Password" class="block text-sm font-semibold leading-6 text-gray-900">Password</label>
                            <div class="mt-2.5">
                                <input type="text" name="Password" id="Password" class="block w-full rounded-md border-2 px-3.5 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                            </div>
                        </div>
                    </div>
                    <div class="mt-10">
                        <button type="submit" name="Add" class="block w-full rounded-md bg-indigo-600 px-3.5 py-2.5 text-center text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                            Add User
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>
