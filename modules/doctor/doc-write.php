<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/secure-token.php';
ini_set("display_errors", 0);

$connect = hms_db_connect();
if ($connect->connect_error) {
  die("Connection failed: " . $connect->connect_error);
}
$connect->set_charset('utf8mb4');

$docid = intval($_SESSION['id'] ?? 0);
$isEdit = (isset($_GET['edit']) && $_GET['edit'] == '1');

// Get appointment id safely using secure token
$id = null;
if (!empty($_GET['ref'])) {
    $id = hms_decrypt_id($_GET['ref']);
    if ($id === null) {
        die("Invalid or tampered link.");
    }
} elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
    // Redirect to secure URL
    $id = intval($_GET['id']);
    $params = [];
    if (isset($_GET['edit']) && $_GET['edit'] == '1') $params['edit'] = '1';
    $params['ref'] = hms_encrypt_id($id);
    header("Location: doc-write.php?" . http_build_query($params));
    exit();
} elseif (isset($_POST['apid']) && is_numeric($_POST['apid'])) {
    $id = intval($_POST['apid']);
}

$existingReport = null;

if ($id !== null) {
  $existingReportSql = mysqli_query($connect, "SELECT * FROM tblmedicalhistory WHERE apid = '$id' LIMIT 1");
  if ($existingReportSql && mysqli_num_rows($existingReportSql) > 0) {
    $existingReport = mysqli_fetch_assoc($existingReportSql);
  }
}

if (isset($_POST['submit'])) {
  hms_require_csrf('./doc-Reservations.php');

  $Patient_ID  = intval($_POST['Patient_ID']);
  $apid        = intval($_POST['apid']);
  $docid       = intval($_SESSION['id']);
  $Report      = mysqli_real_escape_string($connect, $_POST['Report'] ?? '');
  $Treatment   = mysqli_real_escape_string($connect, $_POST['prescription'] ?? '');
  $Scan        = mysqli_real_escape_string($connect, $_POST['Scan'] ?? '');

  // ØªØ­Ù‚Ù‚ Ø¥Ù† Ø§Ù„Ø­Ø¬Ø² Ø¯Ù‡ ÙØ¹Ù„Ø§Ù‹ Ø¨ØªØ§Ø¹ Ø§Ù„Ø¯ÙƒØªÙˆØ± Ø¯Ù‡
  $check = mysqli_query($connect, "SELECT * FROM appointment WHERE apid = '$apid' AND doctorId = '$docid' AND userStatus != 0 AND doctorStatus != 0 LIMIT 1");
  if (!$check || mysqli_num_rows($check) === 0) {
      echo "<script>alert('Access Denied: This appointment does not belong to you or has been cancelled.');
      window.location.href = './doc-Reservations.php';</script>";
      exit();
  }

  $appointmentRow = mysqli_fetch_assoc($check);
  $redirectUid = intval($appointmentRow['userId'] ?? $Patient_ID);

  // Ù„Ùˆ Ø§Ù„ØªÙ‚Ø±ÙŠØ± Ù…ÙˆØ¬ÙˆØ¯ Ø¨Ø§Ù„ÙØ¹Ù„ -> ØªØ¹Ø¯ÙŠÙ„
  $existingCheck = mysqli_query($connect, "SELECT * FROM tblmedicalhistory WHERE apid = '$apid' LIMIT 1");

  if ($existingCheck && mysqli_num_rows($existingCheck) > 0) {
      $query = mysqli_query(
        $connect,
        "UPDATE tblmedicalhistory
         SET prescription = '$Treatment',
             Scan = '$Scan',
             description = '$Report'
         WHERE apid = '$apid'"
      );

      if ($query) {
        $connect->query("UPDATE appointment SET patient_status = 'done' WHERE apid = '$apid'");
        echo "<script>alert('Report Updated successfully');
        window.location.href = '/includes/patient-profile.php?ref=' + encodeURIComponent('<?= hms_encrypt_id((int)$redirectUid) ?>');
        </script>";
        exit();
      } else {
        echo "<script>alert('Error: " . addslashes($connect->error) . "');</script>";
      }
  } else {
      $connect->query("UPDATE appointment SET doctorStatus = 2, userStatus = 2, patient_status = 'done' WHERE apid = '$apid'");

      $query = mysqli_query(
        $connect,
        "INSERT INTO tblmedicalhistory (userId, apid, prescription, Scan, description)
         VALUES ('$Patient_ID', '$apid', '$Treatment', '$Scan', '$Report')"
      );

      if ($query) {
        echo "<script>alert('Report Saved successfully');
        window.location.href = '/includes/patient-profile.php?ref=' + encodeURIComponent('<?= hms_encrypt_id((int)$redirectUid) ?>');
        </script>";
        exit();
      } else {
        echo "<script>alert('Error: " . addslashes($connect->error) . "');</script>";
      }
  }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $isEdit ? 'Edit Report' : 'Write Report' ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="icon" href="../../assets/images/echol.png">
  <link rel="stylesheet" href="/assets/css/responsive.css">
  <link rel="stylesheet" href="/assets/css/generate-button.css">
</head>
<body>
  <div class="min-h-full">
    <?php $activePage = 'write-report'; require_once __DIR__ . '/../../includes/nav.php'; ?>

    <header class="bg-white shadow">
      <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900"><?= $isEdit ? 'Edit Report' : 'Write Report' ?></h1>
      </div>
    </header>

    <main>
      <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
        <div class="isolate bg-white px-6 py-12 lg:px-8">
          <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Patient Report</h2>
          </div>

          <?php
          if ($id !== null) {
            $sql = mysqli_query($connect, "SELECT * FROM appointment JOIN users ON users.uid = appointment.userId WHERE apid = $id AND appointment.doctorId = '$docid' AND userStatus != 0 AND doctorStatus != 0");

            if ($sql && mysqli_num_rows($sql) > 0) {
              $row = mysqli_fetch_assoc($sql);

              $docsql = mysqli_query($connect, "SELECT doctorName FROM doctors WHERE id = '$docid'");
              $docrow = mysqli_fetch_assoc($docsql);
          ?>

          <form method="post" action="./doc-write.php?ref=<?= urlencode(hms_encrypt_id($id)) ?><?= $isEdit ? '&edit=1' : '' ?>" class="mx-auto max-w-full mt-10">
            <?= hms_csrf_field() ?>

            <input type="hidden" name="apid" value="<?php echo $row['apid']; ?>">
            <input type="hidden" name="Patient_ID" value="<?php echo $row['userId']; ?>">

            <div class="grid grid-cols-1 lg:grid-cols-6 gap-x-8 gap-y-8">

              <!-- Left Side: Traditional Form -->
              <div class="lg:col-span-4 grid grid-cols-1 sm:grid-cols-4 gap-6">
                <div class="sm:col-span-2">
                  <label class="block text-sm font-semibold leading-6 text-gray-900">Patient Name</label>
                  <div class="mt-2.5">
                    <input value="<?php echo htmlspecialchars($row['patient_Name']); ?>" type="text" name="name"
                      class="block w-full rounded-md border-2 px-3.5 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 sm:text-sm bg-gray-50" readonly>
                  </div>
                </div>

                <div class="sm:col-span-2">
                  <label class="block text-sm font-semibold leading-6 text-gray-900">Date</label>
                  <div class="mt-2.5">
                    <input value="<?php echo $row['appointmentDate']; ?>" type="text" name="Date"
                      class="block w-full rounded-md border-2 px-3.5 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 sm:text-sm bg-gray-50" readonly>
                  </div>
                </div>

                <div class="sm:col-span-4">
                  <label class="block text-sm font-semibold leading-6 text-gray-900">Treatment / Prescription</label>
                  <div class="mt-2.5">
                    <textarea name="prescription" id="prescriptionField" rows="4"
                      class="block w-full rounded-md border-2 px-3.5 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 sm:text-sm transition-all focus:border-indigo-500"><?= htmlspecialchars($existingReport['prescription'] ?? '') ?></textarea>
                  </div>
                </div>

                <div class="sm:col-span-4">
                  <label class="block text-sm font-semibold leading-6 text-gray-900">Scans / Tests</label>
                  <div class="mt-2.5">
                    <textarea name="Scan" id="scanField" rows="2"
                      class="block w-full rounded-md border-2 px-3.5 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 sm:text-sm transition-all focus:border-indigo-500"><?= htmlspecialchars($existingReport['Scan'] ?? '') ?></textarea>
                  </div>
                </div>

                <div class="sm:col-span-4">
                  <label class="block text-sm font-semibold leading-6 text-gray-900">Clinical Report / Summary</label>
                  <div class="mt-2.5">
                    <textarea name="Report" id="reportField" rows="6"
                      class="block w-full rounded-md border-2 px-3.5 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 sm:text-sm transition-all focus:border-indigo-500"><?= htmlspecialchars($existingReport['description'] ?? '') ?></textarea>
                  </div>
                </div>
              </div>

              <!-- Right Side: AI Assistant -->
              <div class="lg:col-span-2 mt-8 lg:mt-0">
                <div class="rounded-2xl border-2 border-indigo-100 bg-indigo-50/50 p-6 sticky top-24">
                  <h3 class="flex items-center gap-2 text-lg font-bold text-indigo-900 mb-4">
                    <i class="bi bi-robot"></i> AI Documentation Assistant
                  </h3>
                  
                  <div class="mb-4">
                    <label class="block text-xs font-bold text-indigo-700 uppercase tracking-wider mb-2">Raw Visit Notes</label>
                    <textarea id="aiRawNotes" rows="8" 
                      placeholder="Type quick notes here... (e.g. Pt c/o chest pain, BP 140/90, lungs clear, start ASA)"
                      class="block w-full rounded-xl border-1 border-indigo-200 px-3.5 py-2 text-sm text-gray-900 shadow-inner focus:ring-2 focus:ring-indigo-500"></textarea>
                  </div>

                  <div class="flex flex-col gap-3">
                    <button type="button" id="generateAiNote" class="pb-ai-button">
                      <span>Generate structured Report</span>
                      <span class="pb-ai-sparkle">✦</span>
                    </button>

                    <button type="button" id="summarizeHistory"
                      class="flex items-center justify-center gap-2 w-full rounded-xl bg-emerald-600 py-3 text-sm font-bold text-white shadow-lg hover:bg-emerald-700 transition-all active:scale-95">
                      <i class="bi bi-journal-medical"></i> Summarize Patient History
                    </button>
                  </div>

                  <div id="aiStatus" class="mt-4 text-xs font-medium text-indigo-600 hidden">
                    <span class="flex items-center gap-2">
                      <div class="animate-spin rounded-full h-3 w-3 border-b-2 border-indigo-600"></div>
                      Processing with AI...
                    </span>
                  </div>
                </div>
              </div>

            </div>

            <div class="mt-10 flex justify-start">
              <button type="submit" name="submit"
                class="w-full sm:w-96 rounded-xl bg-gray-900 py-3 text-center text-sm font-bold text-white shadow-xl hover:bg-black transition-all">
                <?= $isEdit ? 'Update Report' : 'Save & Finalize Visit' ?>
              </button>
            </div>

          </form>

          <!-- History Modal -->
          <div id="historyModal" class="fixed inset-0 z-50 flex items-center justify-center hidden bg-black/50 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[80vh] overflow-hidden flex flex-col">
              <div class="p-6 border-b flex justify-between items-center bg-emerald-50">
                <h3 class="text-xl font-bold text-emerald-900">Patient History Clinical Summary</h3>
                <button type="button" onclick="document.getElementById('historyModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                  <i class="bi bi-x-lg"></i>
                </button>
              </div>
              <div class="p-8 overflow-y-auto" id="historySummaryContent">
                <!-- AI Content here -->
              </div>
              <div class="p-4 border-t bg-gray-50 flex justify-end">
                <button type="button" onclick="document.getElementById('historyModal').classList.add('hidden')" class="px-6 py-2 bg-gray-200 rounded-lg font-bold">Close</button>
              </div>
            </div>
          </div>

          <?php
            } else {
              echo '<p class="text-center text-red-500 mt-6">No appointment found with this ID, or you are not allowed to access it.</p>';
            }
          } else {
            echo '<p class="text-center text-gray-500 mt-6">Please select an appointment from the <a href="./doc-Reservations.php" class="text-indigo-600 underline">Reservations</a> page.</p>';
          }
          ?>

        </div>
      </div>
    </main>
  </div>

  <script src="/assets/js/responsive-nav.js" defer></script>
  <script>
  const generateBtn = document.getElementById('generateAiNote');
  const summarizeBtn = document.getElementById('summarizeHistory');
  const aiStatus = document.getElementById('aiStatus');
  const patientId = <?= $row['userId'] ?? 0 ?>;

  generateBtn.addEventListener('click', async () => {
    const notes = document.getElementById('aiRawNotes').value;
    if (!notes.trim()) { alert('Please enter some raw notes first.'); return; }

    aiStatus.classList.remove('hidden');
    generateBtn.disabled = true;

    try {
      const res = await fetch('./doctor-ai-api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'generate_visit_note', notes: notes })
      });
      const data = await res.json();
      
      if (data.error) throw new Error(data.error);

      // Populate fields
      document.getElementById('reportField').value = data.clinical_note + "\n\n--- VISIT SUMMARY ---\n" + data.visit_summary;
      document.getElementById('prescriptionField').value = data.suggested_prescription;
      document.getElementById('scanField').value = data.suggested_scans;

      // Add flair
      document.getElementById('reportField').classList.add('ring-2', 'ring-green-500');
      setTimeout(() => document.getElementById('reportField').classList.remove('ring-2', 'ring-green-500'), 2000);

    } catch (err) {
      alert('AI Generation Error: ' + err.message);
    } finally {
      aiStatus.classList.add('hidden');
      generateBtn.disabled = false;
    }
  });

  summarizeBtn.addEventListener('click', async () => {
    aiStatus.classList.remove('hidden');
    summarizeBtn.disabled = true;

    try {
      const res = await fetch('./doctor-ai-api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'summarize_history', patient_id: patientId })
      });
      const data = await res.json();
      
      if (data.error) throw new Error(data.error);

      document.getElementById('historySummaryContent').innerHTML = `<div class="prose prose-emerald max-w-none">${data.summary.replace(/\n/g, '<br>')}</div>`;
      document.getElementById('historyModal').classList.remove('hidden');

    } catch (err) {
      alert('AI Summary Error: ' + err.message);
    } finally {
      aiStatus.classList.add('hidden');
      summarizeBtn.disabled = false;
    }
  });
  </script>
</body>
</html>
