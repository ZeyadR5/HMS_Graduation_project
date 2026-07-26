<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notification-api.php';
ini_set("display_errors", 0);

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "hms";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_POST['Save'])) {
    hms_require_csrf('/modules/user/new_appoint.php');

    $name           = trim($_POST['name'] ?? '');
    $phnumber       = trim($_POST['phnumber'] ?? '');
    $natid          = trim($_POST['natid'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $DoctorID       = intval($_POST['Doctor'] ?? 0);
    $price          = trim($_POST['price'] ?? '');
    $Payed          = trim($_POST['Payed'] ?? '');
    $method         = trim($_POST['method'] ?? '');
    $Date           = trim($_POST['Date'] ?? '');
    $Time           = trim($_POST['Time'] ?? '');
    $about          = trim($_POST['about'] ?? '');

    // Server-side validation
    $errors = [];
    if ($name === '') {
        $errors[] = "Patient Name is required / يجب إدخال اسم المريض.";
    } elseif (strlen($name) < 3) {
        $errors[] = "Patient Name must be at least 3 characters / يجب أن يكون اسم المريض 3 أحرف على الأقل.";
    }
    if ($phnumber !== '' && !preg_match('/^[0-9]{11}$/', $phnumber)) {
        $errors[] = "Phone number must be exactly 11 digits / يجب أن يكون رقم الهاتف 11 رقماً.";
    }
    if ($natid === '') {
        $errors[] = "National ID is required / يجب إدخال الرقم القومي.";
    } elseif (!preg_match('/^[0-9]{14}$/', $natid) && !str_starts_with($natid, 'temp_')) {
        $errors[] = "National ID must be exactly 14 digits / يجب أن يكون الرقم القومي 14 رقماً.";
    }

    if (!empty($errors)) {
        $errStr = implode("\\n", $errors);
        echo "<script>alert('{$errStr}'); window.history.back();</script>";
        exit;
    }

    $docstatus      = 1;
    $employid       = intval($_SESSION['id'] ?? 0);
    $employname     = $_SESSION['username'] ?? '';
    $userstatus     = 1;
    $employstat     = 1;

    $userid = null;

    if ($natid !== '') {
        $stmt = $conn->prepare("SELECT uid, fullName, PatientContno, nat_id FROM users WHERE nat_id = ? LIMIT 1");
        $stmt->bind_param("s", $natid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $patient = $res->fetch_assoc();
            $userid = (int)$patient['uid'];

            if ($name === '') {
                $name = $patient['fullName'] ?? '';
            }
            if ($phnumber === '') {
                $phnumber = $patient['PatientContno'] ?? '';
            }
        }
        $stmt->close();
    }

    if (!$userid && $phnumber !== '') {
        $stmt = $conn->prepare("SELECT uid, fullName, PatientContno FROM users WHERE PatientContno = ? LIMIT 1");
        $stmt->bind_param("s", $phnumber);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $patient = $res->fetch_assoc();
            $userid = (int)$patient['uid'];

            if ($name === '') {
                $name = $patient['fullName'] ?? '';
            }

            if ($natid === '') {
                $natid = $patient['nat_id'] ?? '';
            }
        }
        $stmt->close();
    }

    if (!$userid) {
        if ($natid === '') {
            $natid = 'temp_' . time() . rand(100, 999);
        }

        $stmt = $conn->prepare("INSERT INTO users (fullName, PatientContno) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $phnumber);
        $stmt->execute();
        $userid = $conn->insert_id;
        $stmt->close();
    } else {
        $stmt = $conn->prepare("
            UPDATE users
            SET 
                fullName = CASE WHEN (fullName IS NULL OR fullName = '') AND ? <> '' THEN ? ELSE fullName END,
                PatientContno = CASE WHEN (PatientContno IS NULL OR PatientContno = '') AND ? <> '' THEN ? ELSE PatientContno END
            WHERE uid = ?
        ");
        $stmt->bind_param("ssssi", $name, $name, $phnumber, $phnumber, $userid);
        $stmt->execute();
        $stmt->close();
    }

    // Server-side: Check if doctor has set up their schedule
    $schCheck = $conn->prepare("SELECT COUNT(*) as cnt FROM doctor_schedules WHERE doctor_id = ?");
    $schCheck->bind_param("i", $DoctorID);
    $schCheck->execute();
    $schCount = $schCheck->get_result()->fetch_assoc()['cnt'];
    $schCheck->close();

    if ((int)$schCount === 0) {
        echo "<script>alert('This doctor has not set up their schedule yet. Booking is not available.');</script>";
    } else {
        // Server-side validation for past time
        $appDateTime = strtotime($Date . ' ' . $Time);
        if ($appDateTime < time()) {
            echo "<script>alert('Error: You cannot book an appointment for a past time.'); window.history.back();</script>";
            exit;
        }

        $stmt = $conn->prepare("
        INSERT INTO appointment
        (userId, patient_Name, patient_Num, doctorSpecialization, doctorId, consultancyFees, appointmentDate, appointmentTime, userStatus, doctorStatus, employStatues, employId, employname, paid, method, commnet)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "isssisssiiiissss",
        $userid,
        $name,
        $phnumber,
        $specialization,
        $DoctorID,
        $price,
        $Date,
        $Time,
        $userstatus,
        $docstatus,
        $employstat,
        $employid,
        $employname,
        $Payed,
        $method,
        $about
    );

    if ($stmt->execute()) {
        $newApptId = $conn->insert_id;

        // Save patient email if provided
        $patientEmail = trim($_POST['patient_email'] ?? '');
        if ($patientEmail !== '' && $newApptId > 0) {
            $emailStmt = $conn->prepare("UPDATE appointment SET patient_email = ? WHERE apid = ?");
            $emailStmt->bind_param("si", $patientEmail, $newApptId);
            $emailStmt->execute();
            $emailStmt->close();
        }

        // Notify reception if patient is unregistered
        $isRegistered = ($userid && $userid > 0);
        $docNameStmt = $conn->prepare("SELECT doctorName FROM doctors WHERE id = ?");
        $docNameStmt->bind_param("i", $DoctorID);
        $docNameStmt->execute();
        $docNameRow = $docNameStmt->get_result()->fetch_assoc();
        $docNameStmt->close();

        // Check if patient has a real account (email + password)
        $hasAccount = false;
        if ($userid) {
            $accStmt = $conn->prepare("SELECT email, password FROM users WHERE uid = ? AND email IS NOT NULL AND email != '' AND password IS NOT NULL AND password != ''");
            $accStmt->bind_param("i", $userid);
            $accStmt->execute();
            $hasAccount = $accStmt->get_result()->num_rows > 0;
            $accStmt->close();
        }

        if (!$hasAccount) {
            hms_notify_reception_new_booking($conn, [
                'patient_name' => $name,
                'doctor_name' => $docNameRow['doctorName'] ?? 'Doctor',
                'date' => $Date,
                'time' => $Time,
                'is_registered' => false,
                'doctor_id' => $DoctorID,
                'appointment_id' => $newApptId,
            ]);
        }

        // Notify the doctor about the new booking
        hms_create_notification($conn, [
            'recipient_type' => 'doctor',
            'recipient_id' => $DoctorID,
            'title' => 'New Appointment — ' . $name,
            'message' => 'Patient: ' . $name . ' | Date: ' . $Date . ' | Time: ' . $Time,
            'type' => 'appointment',
            'related_doctor_id' => $DoctorID,
            'related_appointment_id' => $newApptId,
        ]);

        // If appointment is today and patient has account, notify about queue position
        if ($Date === date('Y-m-d') && $hasAccount && $userid > 0) {
            $posStmt = $conn->prepare("SELECT COUNT(*) as pos FROM appointment 
                                        WHERE doctorId = ? AND appointmentDate = CURRENT_DATE() 
                                        AND userStatus IN (1,2) AND patient_status IN ('waiting','in progress')
                                        AND apid <= ?");
            $posStmt->bind_param("ii", $DoctorID, $newApptId);
            $posStmt->execute();
            $posRow = $posStmt->get_result()->fetch_assoc();
            $posStmt->close();
            $position = (int)($posRow['pos'] ?? 0);
            $docName = $docNameRow['doctorName'] ?? 'Doctor';

            hms_create_notification($conn, [
                'recipient_type' => 'patient',
                'recipient_id' => (int)$userid,
                'title' => 'أنت في قائمة الانتظار 📋',
                'message' => 'تم تسجيلك في الدور رقم ' . $position . ' عند د. ' . $docName . ' — الموعد: ' . $Time . '. هنبلغك لما دورك يجي.',
                'type' => 'queue',
                'related_doctor_id' => $DoctorID,
                'related_appointment_id' => $newApptId,
            ]);
        }

        echo "<script>alert('Appointment Done'); window.location.href = './Reservations.php';</script>";
        exit;
    } else {
        echo "<script>alert('Error: " . addslashes($stmt->error) . "');</script>";
    }
    $stmt->close();
    } // end else (doctor has schedule)
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Reservations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>
<link rel="icon" href="../../assets/images/l-gh.png">
    
    <!-- Flatpickr CSS & JS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.8.0/jquery.min.js"></script>
    <script>
        var fp; // Flatpickr instance
        
        document.addEventListener('DOMContentLoaded', function() {
            fp = flatpickr("input[name='Date']", {
                minDate: "today",
                dateFormat: "Y-m-d",
                disableMobile: "true",
                onChange: function(selectedDates, dateStr, instance) {
                    loadAvailableSlots();
                }
            });
        });

        function getdoctor(val) {
            $.ajax({
                type: "POST",
                url: "get_doctor.php",
                data: 'specilizationid=' + val,
                success: function(data) {
                    $("#doctor").html(data);
                    // Reset dependent fields
                    $("#price").html('');
                    $("#timeSlot").html('<option value="">Select doctor first</option>');
                    $("#doctorInfoPanel").hide();
                    $("#availabilityAlert").hide();
                    
                    // Reset datepicker
                    if (fp) {
                        fp.clear();
                        fp.set('enable', [function() { return true; }]);
                    }
                }
            });
        }

        function getfee(val) {
            $.ajax({
                type: "POST",
                url: "get_doctor.php",
                data: 'doctor=' + val,
                success: function(data) {
                    $("#price").html(data);
                    updateDoctorAvailabilityMeta(val);
                }
            });
        }

        function updateDoctorAvailabilityMeta(doctorId) {
            if (!doctorId || doctorId === 'Select Doctor') return;

            $.ajax({
                type: "GET",
                url: "/includes/get_available_slots.php",
                data: { doctor_id: doctorId, mode: 'meta' },
                dataType: "json",
                success: function(data) {
                    if (data.success && fp) {
                        fp.clear(); // Clear previously selected date
                        
                        // Rule 1: Enable only specific days of the week
                        // Rule 2: Disable specific blocked dates
                        fp.set("enable", [
                            function(date) {
                                var day = date.getDay();
                                var dateStr = date.toISOString().split('T')[0];
                                
                                var isWorkingDay = data.working_days.includes(day);
                                var isBlocked = data.blocked_dates.includes(dateStr);
                                
                                return isWorkingDay && !isBlocked;
                            }
                        ]);
                        
                        // Show working days hint
                        var dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                        var workingDaysStr = data.working_days.map(d => dayNames[d]).join(', ');
                        if (workingDaysStr) {
                            $("#availabilityAlert").html('<i class="bi bi-calendar-event"></i> Doctor working days: ' + workingDaysStr).show();
                        }
                    }
                }
            });
        }

        function loadAvailableSlots() {
            var doctorId = $("#doctor").val();
            var dateVal = $("input[name='Date']").val();
            
            if (!doctorId || doctorId === 'Select Doctor' || !dateVal) {
                $("#timeSlot").html('<option value="">Select doctor & date</option>');
                $("#doctorInfoPanel").hide();
                $("#availabilityAlert").hide();
                return;
            }

            $.ajax({
                type: "GET",
                url: "/includes/get_available_slots.php",
                data: { doctor_id: doctorId, date: dateVal },
                dataType: "json",
                success: function(data) {
                    // Show doctor info
                    if (data.doctor_info) {
                        var info = data.doctor_info;
                        var slotText = info.slot_duration ? info.slot_duration + ' min' : 'Not set';
                        $("#docInfoName").text(info.name || '');
                        $("#docInfoFees").text(info.fees ? info.fees + ' EGP' : '');
                        $("#docInfoSlot").text(slotText);
                        $("#doctorInfoPanel").show();
                    }

                    if (!data.available && !data.no_schedule) {
                        // Day not available
                        $("#availabilityAlert").html('<i class="bi bi-exclamation-triangle-fill"></i> ' + (data.message || 'Doctor not available on this day')).show();
                        $("#timeSlot").html('<option value="">Not available</option>');
                        return;
                    }

                    $("#availabilityAlert").hide();

                    if (data.no_schedule) {
                        // Doctor has no schedule — block booking
                        $("#availabilityAlert").html('<i class="bi bi-exclamation-triangle-fill"></i> ' + (data.message || 'This doctor has not set up their schedule yet. Booking is not available.')).show();
                        $("#timeSlot").html('<option value="">Schedule not set</option>');
                        $("#manualTimeWrapper").hide();
                        return;
                    }

                    $("#manualTimeWrapper").hide();

                    // Client-side: double-check for past slots if date is today
                    var now = new Date();
                    var selectedDate = dateVal; // YYYY-MM-DD
                    var todayStr = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0') + '-' + String(now.getDate()).padStart(2,'0');
                    var isToday = (selectedDate === todayStr);
                    var currentMinutes = now.getHours() * 60 + now.getMinutes();

                    // Build slot options
                    var html = '<option value="">Select Time Slot</option>';
                    var hasAvailable = false;
                    data.slots.forEach(function(slot) {
                        var slotAvailable = slot.available;

                        // Extra client-side past check for today
                        if (isToday && slotAvailable) {
                            var parts = slot.time.split(':');
                            var slotMinutes = parseInt(parts[0]) * 60 + parseInt(parts[1]);
                            if (slotMinutes <= currentMinutes) {
                                slotAvailable = false;
                                slot.past = true;
                            }
                        }

                        if (slotAvailable) {
                            html += '<option value="' + slot.time + '">' + slot.display + '</option>';
                            hasAvailable = true;
                        } else {
                            var reason = slot.booked ? ' (Booked)' : ' (Past)';
                            html += '<option value="" disabled style="color:#999;">' + slot.display + reason + '</option>';
                        }
                    });

                    if (!hasAvailable) {
                        html = '<option value="">No available slots</option>';
                        $("#availabilityAlert").html('<i class="bi bi-exclamation-triangle-fill"></i> All time slots are booked for this day').show();
                    }

                    $("#timeSlot").html(html);
                },
                error: function() {
                    $("#timeSlot").html('<option value="">Error loading slots</option>');
                }
            });
        }
    </script>
    <style>
        .doc-info-panel {
            display: none;
            background: linear-gradient(135deg, #eff6ff, #f0fdf4);
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 0.8rem 1.2rem;
            margin-bottom: 1rem;
        }
        .doc-info-panel .info-row { display: flex; gap: 1.5rem; flex-wrap: wrap; }
        .doc-info-panel .info-item { font-size: 0.85rem; color: #334155; }
        .doc-info-panel .info-item strong { color: #0f172a; }
        .availability-alert {
            display: none;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            border-radius: 10px;
            padding: 0.7rem 1rem;
            font-size: 0.88rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
    </style>

    <link rel="stylesheet" href="/assets/css/responsive.css">
</head>

<body>
    <div class="min-h-full">
        <?php $activePage = 'new-appointment'; require_once __DIR__ . '/../../includes/nav.php'; ?>

        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">New Appointment</h1>
            </div>
        </header>

        <main>
            <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                <form method="post">
                    <?= hms_csrf_field() ?>
                    <div class="space-y-12">
                        <div class="border-b border-gray-900/10 pb-12">
                            <div class="mt-10 grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">

                                <div class="sm:col-span-6">
                                    <label class="block text-sm font-medium leading-6 text-gray-900">Patient Name</label>
                                    <div class="mt-2">
                                        <input type="text" name="name" autocomplete="given-name" required minlength="3"
                                            class="font-bold block w-full rounded-md py-2 px-3 text-gray-900 shadow-sm ring-1 ring-inset border-2 ring-gray-500 focus:ring-2 focus:ring-indigo-600 sm:text-sm">
                                    </div>
                                </div>

                                <div class="sm:col-span-3">
                                    <label class="block text-sm font-medium leading-6 text-gray-900">Phone Number</label>
                                    <div class="mt-2">
                                        <input type="text" name="phnumber" required
                                            pattern="[0-9]{11}" maxlength="11" minlength="11" title="Phone number must be exactly 11 digits / يجب أن يكون رقم الهاتف مكوناً من 11 رقماً" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                            class="font-bold block w-full rounded-md border-2 py-2 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-500 focus:ring-2 focus:ring-indigo-600 sm:text-sm">
                                    </div>
                                </div>

                                <div class="sm:col-span-3">
                                    <label class="block text-sm font-medium leading-6 text-gray-900">National ID</label>
                                    <div class="mt-2">
                                        <input type="text" name="natid" placeholder="رقم الهوية" required
                                            pattern="[0-9]{14}" maxlength="14" minlength="14" title="National ID must be exactly 14 digits / يجب أن يكون الرقم القومي مكوناً من 14 رقماً" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                            class="font-bold block w-full rounded-md border-2 py-2 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-500 focus:ring-2 focus:ring-indigo-600 sm:text-sm">
                                    </div>
                                </div>

                                <div class="sm:col-span-3">
                                    <label class="block text-sm font-semibold leading-6 text-gray-900">Specialization</label>
                                    <div class="mt-2">
                                        <select name="specialization" onChange="getdoctor(this.value);"
                                            class="block w-full rounded-md border-2 px-3.5 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600 sm:text-sm">
                                            <option>Select Specialization</option>
                                            <?php
                                            $ret = mysqli_query($conn, "SELECT * FROM doctorspecilization");
                                            while ($row = mysqli_fetch_array($ret)) { ?>
                                                <option value="<?php echo htmlentities($row['specilization']); ?>">
                                                    <?php echo htmlentities($row['specilization']); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="sm:col-span-3">
                                    <label class="block text-sm font-semibold leading-6 text-gray-900">Doctor</label>
                                    <div class="mt-2">
                                        <select id="doctor" name="Doctor" onChange="getfee(this.value);"
                                            class="block w-full rounded-md border-2 px-3.5 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600 sm:text-sm">
                                            <option>Select Doctor</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="sm:col-span-3">
                                    <label class="block text-sm font-medium leading-6 text-gray-900">Price</label>
                                    <div class="mt-2">
                                        <select name="price" id="price"
                                            class="font-bold block w-full rounded-md border-2 py-2 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-500 focus:ring-2 focus:ring-indigo-600 sm:text-sm">
                                        </select>
                                    </div>
                                </div>

                                <div class="sm:col-span-3">
                                    <label class="block text-sm font-medium leading-6 text-gray-900">Paid</label>
                                    <div class="mt-2">
                                        <input type="text" name="Payed"
                                            class="font-bold block w-full rounded-md border-2 py-2 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-500 focus:ring-2 focus:ring-indigo-600 sm:text-sm">
                                    </div>
                                </div>

                                <div class="sm:col-span-6">
                                    <label class="block text-sm font-medium leading-6 text-gray-900">Patient Email <span class="text-gray-400 font-normal">(Optional — for appointment confirmation)</span></label>
                                    <div class="mt-2">
                                        <input type="email" name="patient_email" placeholder="patient@example.com"
                                            class="font-bold block w-full rounded-md border-2 py-2 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-500 focus:ring-2 focus:ring-indigo-600 sm:text-sm">
                                    </div>
                                </div>

                            </div>

                            <!-- Payment Method hidden — not needed at reception desk -->
                            <input type="hidden" name="method" value="Cash">
                        </div>

                        <!-- Doctor Info Panel -->
                        <div class="sm:col-span-4 doc-info-panel" id="doctorInfoPanel">
                            <div class="info-row">
                                <div class="info-item"><strong>Doctor:</strong> <span id="docInfoName"></span></div>
                                <div class="info-item"><strong>Consultation Fee:</strong> <span id="docInfoFees"></span></div>
                                <div class="info-item"><strong>Session Duration:</strong> <span id="docInfoSlot"></span></div>
                            </div>
                        </div>

                        <div class="sm:col-span-4">
                            <div class="availability-alert" id="availabilityAlert"></div>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium leading-6 text-gray-900">Date</label>
                            <div class="mt-2">
                                <input type="date" value="<?php echo date('Y-m-d'); ?>" name="Date" min="<?php echo date('Y-m-d'); ?>" onchange="loadAvailableSlots()"
                                    class="font-bold block w-full rounded-md border-2 py-2 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-500 focus:ring-2 focus:ring-indigo-600 sm:text-sm">
                            </div>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium leading-6 text-gray-900">Available Time Slots</label>
                            <div class="mt-2">
                                <select name="Time" id="timeSlot"
                                    class="font-bold block w-full rounded-md border-2 py-2 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-500 focus:ring-2 focus:ring-indigo-600 sm:text-sm">
                                    <option value="">Select doctor & date first</option>
                                </select>
                            </div>
                            <div id="manualTimeWrapper" style="display:none;margin-top:0.5rem;">
                                <input type="time" name="ManualTime" value="<?php echo date('H:i'); ?>"
                                    class="font-bold block w-full rounded-md border-2 py-2 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-500 focus:ring-2 focus:ring-indigo-600 sm:text-sm">
                                <small class="text-gray-400">Doctor has no schedule set — enter time manually</small>
                            </div>
                        </div>

                        <div class="col-span-full">
                            <label class="block text-sm font-medium leading-6 text-gray-900">Comment</label>
                            <div class="mt-2">
                                <textarea placeholder="Add A Comment" name="about" rows="3"
                                    class="font-bold block w-full rounded-md border-2 py-2 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-500 focus:ring-2 focus:ring-indigo-600 sm:text-sm"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-x-6 m-6">
                        <button type="button" class="text-sm font-semibold leading-6 text-gray-900">Cancel</button>
                        <button type="submit" name="Save"
                            class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>


    <script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>
