<?php
require_once __DIR__ . '/bootstrap.php';

$pageTitle = $pageTitle ?? 'Doctors Status';
$showDeleteAction = $showDeleteAction ?? false;

$connect = hms_management_connect();

// PHP POST handler for adding a doctor
$add_errors = [];
$add_success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_doctor'])) {
    hms_require_csrf(hms_management_current_path());

    $name = trim($_POST['name'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['Password'] ?? '');
    $roleSelected = 'Doctor';

    if ($name === '' || $specialization === '' || $price === '' || $email === '' || $password === '') {
        $add_errors[] = "Please fill all fields / يرجى ملء جميع الحقول.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $add_errors[] = "Invalid email format / صيغة البريد الإلكتروني غير صحيحة.";
    } elseif (!is_numeric($price) || floatval($price) < 0) {
        $add_errors[] = "Price must be a valid number / يجب أن يكون السعر رقماً صحيحاً.";
    } else {
        $check_email_stmt = $connect->prepare("SELECT id FROM doctors WHERE docEmail = ? LIMIT 1");
        $check_email_stmt->bind_param("s", $email);
        $check_email_stmt->execute();
        $check_email = $check_email_stmt->get_result();

        if ($check_email->num_rows > 0) {
            $add_errors[] = "Email already exists / البريد الإلكتروني مسجل بالفعل.";
        }
        $check_email_stmt->close();
    }

    if (empty($add_errors)) {
        $insert_stmt = $connect->prepare("
            INSERT INTO doctors (`doctorName`, `specilization`, `docFees`, `docEmail`, `password`, `role`)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $insert_stmt->bind_param("ssisss", $name, $specialization, $price, $email, $password, $roleSelected);

        if ($insert_stmt->execute()) {
            $add_success = true;
        } else {
            $add_errors[] = "Something went wrong / حدث خطأ غير متوقع.";
        }
        $insert_stmt->close();
    }
}

// Fetch specializations for the dropdown list
$specs_options = [];
if (!empty($addDoctorPath)) {
    $specializations_result = mysqli_query($connect, "SELECT specilization FROM doctorspecilization");
    if ($specializations_result) {
        while ($row = mysqli_fetch_assoc($specializations_result)) {
            $specs_options[] = $row['specilization'];
        }
    }
}

if ($showDeleteAction && isset($_GET['cancel'])) {
    hms_require_csrf(hms_management_current_path());

    $doctorId = intval($_GET['id'] ?? 0);
    if ($doctorId > 0) {
        $doctorResult = mysqli_query($connect, "SELECT id, doctorName, specilization, docEmail FROM doctors WHERE id = {$doctorId}");
        $doctor = $doctorResult ? mysqli_fetch_assoc($doctorResult) : null;
        mysqli_query($connect, "DELETE FROM doctors WHERE id = {$doctorId}");

        if ($doctor) {
            hms_audit_log($connect, 'doctor.deleted', [
                'description' => 'A doctor account was deleted.',
                'entity_type' => 'doctor',
                'entity_id' => (string)$doctorId,
                'details' => [
                    'doctor_name' => $doctor['doctorName'] ?? null,
                    'specialization' => $doctor['specilization'] ?? null,
                    'email' => $doctor['docEmail'] ?? null,
                ],
            ]);
        }
    }

    header("Location: " . hms_management_current_path());
    exit();
}

if (isset($_GET['toggle_active']) && isset($_GET['id'])) {
    hms_require_csrf(hms_management_current_path());
    $doctorId = intval($_GET['id']);
    $statusResult = mysqli_query($connect, "SELECT is_active FROM doctors WHERE id = {$doctorId}");
    if ($statusResult && $row = mysqli_fetch_assoc($statusResult)) {
        $newStatus = (int)$row['is_active'] === 1 ? 0 : 1;
        mysqli_query($connect, "UPDATE doctors SET is_active = {$newStatus} WHERE id = {$doctorId}");
    }
    header("Location: " . hms_management_current_path());
    exit();
}

if (isset($_POST['reset_password']) && isset($_POST['id']) && !empty($_POST['new_password'])) {
    $doctorId = intval($_POST['id']);
    $newPassword = $_POST['new_password']; // Note: stored as plaintext per current logic
    $stmt = $connect->prepare("UPDATE doctors SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $newPassword, $doctorId);
    $stmt->execute();
    $stmt->close();
    header("Location: " . hms_management_current_path() . "?msg=password_reset");
    exit();
}

$countQuery = mysqli_query($connect, "SELECT COUNT(*) as total FROM doctors");
$totalRows = 0;
if ($countQuery) {
    $totalRows = (int)($countQuery->fetch_assoc()['total'] ?? 0);
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$totalPages = ceil($totalRows / $perPage);
$offset = ($page - 1) * $perPage;

$doctors = mysqli_query($connect, "SELECT DISTINCT * FROM doctors ORDER BY id DESC LIMIT $perPage OFFSET $offset");
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
</head>
<body>
    <div class="min-h-full">
        <?php $activePage = 'doctors'; require __DIR__ . '/../../../includes/nav.php'; ?>

        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 flex justify-between items-center">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900"><?= htmlspecialchars($pageTitle) ?></h1>
                <?php if (!empty($addDoctorPath)): ?>
                    <button type="button" onclick="document.getElementById('addDoctorModal').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow hover:bg-blue-700 transition-all focus:ring-2 focus:ring-blue-500">
                        <i class="bi bi-plus-circle"></i> Add Doctor
                    </button>
                <?php endif; ?>
            </div>
        </header>

        <main>
            <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
            <?php if ($add_success): ?>
                <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 flex items-center gap-2">
                    <i class="bi bi-check-circle-fill text-emerald-500"></i> New Doctor added successfully.
                </div>
            <?php endif; ?>
                <form class="space-y-6 flex flex-wrap p-3" action="<?= htmlspecialchars(hms_management_sibling_path('search-doc.php')) ?>" method="POST">
                    <div class="px-3 py-1.5">
                        <label for="Search" class="flex text-sm font-medium text-gray-900">Search</label>
                        <div class="mt-2">
                            <input id="Search" name="input" type="Search" required class="font-bold block w-26 rounded-md border-2 px-4 py-1 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                        </div>
                    </div>
                    <div class="px-3 py-1.5">
                        <button name="search" type="submit" class="flex w-20 justify-center rounded-md bg-blue-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-indigo-500">Search</button>
                    </div>
                </form>

                <table class="table table-striped table-hover table-bordered border-gray-400 w-full">
                    <thead>
                        <tr>
                            <th><p class="text-lg font-bold text-gray-900 text-center">Doctor ID</p></th>
                            <th><p class="text-lg font-bold text-gray-900 text-center">Doctor Name</p></th>
                            <th><p class="text-lg font-bold text-gray-900 text-center">Account Status</p></th>
                            <th><p class="text-lg font-bold text-gray-900 text-center">Log Status</p></th>
                            <th><p class="text-lg font-bold text-gray-900 text-center">Reset Password</p></th>
                            <?php if ($showDeleteAction): ?>
                                <th><p class="text-lg font-bold text-gray-900 text-center">Delete Doctor</p></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="table-group-divider">
                        <?php if ($doctors): ?>
                            <?php while ($row = mysqli_fetch_assoc($doctors)): ?>
                                <tr>
                                    <th><p class="text-lg font-bold text-gray-900 text-center"><?= htmlspecialchars((string)$row['id']) ?></p></th>
                                    <th><p class="text-lg font-bold text-gray-900 text-center"><?= htmlspecialchars($row['doctorName']) ?></p></th>
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
                                            <?php if ((int)$row['statue'] === 1): ?>
                                                <button class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-lg font-medium text-blue-700 ring-1 ring-inset ring-red-600/20">ON</button>
                                            <?php else: ?>
                                                <button class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-lg font-medium text-red-700 ring-1 ring-inset ring-red-600/20">OFF</button>
                                            <?php endif; ?>
                                        </p>
                                    </th>
                                    <th>
                                        <p class="text-lg font-bold text-gray-900 text-center">
                                            <button type="button" onclick="resetDoctorPassword(<?= (int)$row['id'] ?>, '<?= htmlspecialchars($row['doctorName'], ENT_QUOTES) ?>')" class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-lg font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20">Reset</button>
                                        </p>
                                    </th>
                                    <?php if ($showDeleteAction): ?>
                                        <th><p class="text-lg font-bold text-gray-900 text-center"><a href="<?= htmlspecialchars(hms_management_current_path()) ?>?id=<?= (int)$row['id'] ?>&cancel=1&<?= hms_csrf_query() ?>" onClick="return confirm('Are You Sure You Want To Delete ?');" class="text-lg inline-flex items-center rounded-md bg-green-50 px-2 py-1 font-medium text-red-700 ring-1 ring-inset ring-red-600/20">Delete</a></p></th>
                                    <?php endif; ?>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination UI -->
            <?php if (isset($totalPages) && $totalPages > 1): ?>
            <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 mt-4">
                <div class="flex flex-1 justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing <span class="font-medium"><?= $offset + 1 ?></span> to <span class="font-medium"><?= min($offset + $perPage, $totalRows) ?></span> of <span class="font-medium"><?= $totalRows ?></span> results
                        </p>
                    </div>
                    <div>
                        <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50"><i class="bi bi-chevron-left"></i></a>
                            <?php endif; ?>
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <a href="?page=<?= $p ?>" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?= $p === $page ? 'bg-blue-600 text-white' : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50' ?>"><?= $p ?></a>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50"><i class="bi bi-chevron-right"></i></a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- Hidden form for password reset -->
    <form id="resetPasswordForm" method="POST" action="<?= htmlspecialchars(hms_management_current_path()) ?>" style="display:none;">
        <input type="hidden" name="reset_password" value="1">
        <input type="hidden" name="id" id="resetPasswordId" value="">
        <input type="hidden" name="new_password" id="resetPasswordValue" value="">
    </form>

    <script>
    function resetDoctorPassword(id, doctorName) {
        var newPass = prompt("Enter new password for " + doctorName + ":");
        if (newPass !== null && newPass.trim() !== "") {
            document.getElementById('resetPasswordId').value = id;
            document.getElementById('resetPasswordValue').value = newPass.trim();
            document.getElementById('resetPasswordForm').submit();
        }
    }
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'password_reset'): ?>
        alert("Password has been successfully reset.");
        window.history.replaceState({}, document.title, window.location.pathname);
    <?php endif; ?>
    </script>
    <script src="/assets/js/responsive-nav.js" defer></script>

<?php if (!empty($addDoctorPath)): ?>
<!-- Add Doctor Modal -->
<div id="addDoctorModal" class="<?= empty($add_errors) ? 'hidden' : '' ?> fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-hidden flex flex-col scale-in-center">
        <div class="p-6 border-b flex justify-between items-center bg-blue-600">
            <h3 class="text-xl font-bold text-white flex items-center gap-2">
                <i class="bi bi-person-plus-fill"></i> Add New Doctor
            </h3>
            <button type="button" onclick="document.getElementById('addDoctorModal').classList.add('hidden')" class="text-blue-100 hover:text-white transition-colors">
                <i class="bi bi-x-lg text-xl"></i>
            </button>
        </div>
        <form method="POST" action="" class="flex-grow overflow-y-auto p-6 space-y-4">
            <?= hms_csrf_field() ?>
            <input type="hidden" name="action_add_doctor" value="1">

            <?php if (!empty($add_errors)): ?>
                <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                    <ul class="list-disc list-inside space-y-1">
                        <?php foreach ($add_errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div>
                <label for="add_specialization" class="mb-2 block text-sm font-semibold text-slate-700 font-bold">Specialization</label>
                <select
                    id="add_specialization" name="specialization" required
                    class="block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                >
                    <option value="">Select specialization</option>
                    <?php foreach ($specs_options as $specOption): ?>
                        <option value="<?= htmlspecialchars($specOption) ?>" <?= (($_POST['specialization'] ?? '') === $specOption) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($specOption) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="add_doc_name" class="mb-2 block text-sm font-semibold text-slate-700 font-bold">Doctor name</label>
                <input
                    id="add_doc_name" name="name" type="text"
                    value="<?= htmlspecialchars($_POST['name'] ?? 'DR / ', ENT_QUOTES, 'UTF-8') ?>"
                    required minlength="6"
                    class="block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                >
            </div>

            <div>
                <label for="add_price" class="mb-2 block text-sm font-semibold text-slate-700 font-bold">Appointment price</label>
                <input
                    id="add_price" name="price" type="text"
                    value="<?= htmlspecialchars($_POST['price'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    required inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                    class="block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                >
            </div>

            <div>
                <label for="add_doc_email" class="mb-2 block text-sm font-semibold text-slate-700 font-bold">Email</label>
                <input
                    id="add_doc_email" name="email" type="email"
                    value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    required
                    class="block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                >
            </div>

            <div>
                <label for="add_doc_Password" class="mb-2 block text-sm font-semibold text-slate-700 font-bold">Password</label>
                <input
                    id="add_doc_Password" name="Password" type="text"
                    value="<?= htmlspecialchars($_POST['Password'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    required minlength="4"
                    class="block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                >
            </div>

            <div class="pt-4 border-t bg-gray-50 -mx-6 -mb-6 px-6 py-4 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('addDoctorModal').classList.add('hidden')"
                    class="px-5 py-2.5 bg-slate-100 text-slate-700 rounded-xl font-bold hover:bg-slate-200 transition-all">
                    Cancel
                </button>
                <button type="submit"
                    class="px-6 py-2.5 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 transition-all shadow-md">
                    Add Doctor
                </button>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes scale-in-center {
    0% { transform: scale(0.9); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}
.scale-in-center { animation: scale-in-center 0.25s cubic-bezier(0.250, 0.460, 0.450, 0.940) both; }
</style>
<?php endif; ?>

</body>
</html>
