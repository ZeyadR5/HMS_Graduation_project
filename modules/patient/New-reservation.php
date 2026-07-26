<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notification-api.php';
require_once __DIR__ . '/../../includes/payment-config.php';
require_once __DIR__ . '/../../includes/secure-token.php';
require_once __DIR__ . '/../../includes/mailer.php';

ini_set("display_errors", 0);


$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hms";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("ÙØ´Ù„ Ø§Ù„Ø§ØªØµØ§Ù„ Ø¨Ù‚Ø§Ø¹Ø¯Ø© Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª: " . $conn->connect_error);
}


if (isset($_POST['Save'])) {
    hms_require_csrf('/modules/patient/New-reservation.php');

    $specilization = $_POST['specialization'];
    $doctorid = (int)$_POST['Doctor'];
    $userid = (int)$_SESSION['uid'];
    $username = $_SESSION['username'];
    $fees = $_POST['price'];
    $appdate = $_POST['Date'];
    $time = $_POST['Time'];
    $userstatus = 1;
    $docstatus = 1;

    // Server-side: Check if doctor has set up their schedule
    $schCheck = $conn->prepare("SELECT COUNT(*) as cnt FROM doctor_schedules WHERE doctor_id = ?");
    $schCheck->bind_param("i", $doctorid);
    $schCheck->execute();
    $schCount = $schCheck->get_result()->fetch_assoc()['cnt'];
    $schCheck->close();

    if ((int)$schCount === 0) {
        echo "<script>alert('This doctor has not set up their schedule yet. Booking is not available.');</script>";
    } else {
        // Server-side validation for past time
        $appDateTime = strtotime($appdate . ' ' . $time);
        if ($appDateTime < time()) {
            echo "<script>alert('Error: You cannot book an appointment for a past time.'); window.history.back();</script>";
            exit;
        }

        $insertStmt = $conn->prepare("INSERT INTO appointment(doctorSpecialization,doctorId,userId,patient_Name,consultancyFees,appointmentDate,appointmentTime,userStatus,doctorStatus)
                      VALUES(?,?,?,?,?,?,?,?,?)");
        $insertStmt->bind_param("siissssii", $specilization, $doctorid, $userid, $username, $fees, $appdate, $time, $userstatus, $docstatus);
        $query = $insertStmt->execute();
        $insertStmt->close();
    if ($query) {
        $newApptId = mysqli_insert_id($conn);

        // Calculate and save deposit amount
        $depositAmount = hms_calculate_deposit((int)$fees);
        $depositStmt = $conn->prepare("UPDATE appointment SET deposit_amount = ? WHERE apid = ?");
        $depositStmt->bind_param("ii", $depositAmount, $newApptId);
        $depositStmt->execute();
        $depositStmt->close();

        // Notify reception
        $docNameRes = mysqli_query($conn, "SELECT doctorName FROM doctors WHERE id='" . intval($doctorid) . "'");
        $docNameRow = mysqli_fetch_assoc($docNameRes);
        hms_notify_reception_new_booking($conn, [
            'patient_name' => $username,
            'doctor_name' => $docNameRow['doctorName'] ?? 'Doctor',
            'date' => $appdate,
            'time' => $time,
            'is_registered' => true,
            'doctor_id' => intval($doctorid),
            'appointment_id' => $newApptId,
        ]);

        // Notify the doctor about the new booking
        hms_create_notification($conn, [
            'recipient_type' => 'doctor',
            'recipient_id' => intval($doctorid),
            'title' => 'New Appointment — ' . $username,
            'message' => 'Patient: ' . $username . ' | Date: ' . $appdate . ' | Time: ' . $time,
            'type' => 'appointment',
            'related_doctor_id' => intval($doctorid),
            'related_appointment_id' => $newApptId,
        ]);

        // If appointment is today, notify patient about their queue position
        if ($appdate === date('Y-m-d')) {
            $posRes = mysqli_query($conn, "SELECT COUNT(*) as pos FROM appointment 
                                           WHERE doctorId='" . intval($doctorid) . "' AND appointmentDate = CURRENT_DATE() 
                                           AND userStatus IN (1,2) AND patient_status IN ('waiting','in progress')
                                           AND apid <= $newApptId");
            $posRow = mysqli_fetch_assoc($posRes);
            $position = (int)($posRow['pos'] ?? 0);
            $docName = $docNameRow['doctorName'] ?? 'Doctor';

            hms_create_notification($conn, [
                'recipient_type' => 'patient',
                'recipient_id' => intval($userid),
                'title' => 'أنت في قائمة الانتظار 📋',
                'message' => 'تم تسجيلك في الدور رقم ' . $position . ' عند د. ' . $docName . ' — الموعد: ' . $time . '. هنبلغك لما دورك يجي.',
                'type' => 'queue',
                'related_doctor_id' => intval($doctorid),
                'related_appointment_id' => $newApptId,
                'mirror_email' => false,
            ]);
        }

        // ── Email: Booking Confirmation ──────────────────────────────────
        $emailStmt = $conn->prepare("SELECT email, fullName FROM users WHERE uid = ? AND email IS NOT NULL AND email != '' LIMIT 1");
        $emailStmt->bind_param("i", $userid);
        $emailStmt->execute();
        $emailRow = $emailStmt->get_result()->fetch_assoc();
        $emailStmt->close();
        if ($emailRow && !empty($emailRow['email'])) {
            hms_send_appointment_booked_email(
                $emailRow['email'],
                $emailRow['fullName'] ?: $username,
                $docNameRow['doctorName'] ?? 'Doctor',
                $appdate,
                $time,
                (int)$fees
            );
        }

        // Redirect to deposit payment page
        if (HMS_PAYMENT_ENABLED && $depositAmount > 0) {
            $token = urlencode(hms_encrypt_id((int)$newApptId));
            echo "<script>window.location.href='/modules/patient/pay-deposit.php?ref={$token}';</script>";
        } else {
            echo "<script>alert('Appointment done successfully'); window.location.href='/modules/patient/calender.php';</script>";
        }
    }
    } // end else (doctor has schedule)
}
?>



<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Reservation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>
<link rel="icon" href="/assets/images/echol.png">
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Flatpickr CSS only (no blocking JS) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    
    <script>
        var fp; // Flatpickr instance
        
        // Defer flatpickr init until DOM is ready - will be initialized after scripts load
        function initFlatpickr() {
            if (typeof flatpickr === 'undefined') {
                setTimeout(initFlatpickr, 100);
                return;
            }
            fp = flatpickr("#Date", {
                minDate: "today",
                dateFormat: "Y-m-d",
                disableMobile: "true",
                onChange: function(selectedDates, dateStr, instance) {
                    loadAvailableSlots();
                }
            });
        }
        document.addEventListener('DOMContentLoaded', initFlatpickr);

        function getdoctor(val) {
            $.ajax({
                type: "POST",
                url: "get_doctor.php",
                data: 'specilizationid=' + val,
                success: function(data) {
                    $("#doctor").html(data);
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
    </script>


    <script>
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
                                // Enable if day of week is in workingDays AND not in blockedDates
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
            var dateVal = $("#Date").val();
            
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
                    if (data.doctor_info) {
                        var info = data.doctor_info;
                        var slotText = info.slot_duration ? info.slot_duration + ' min' : 'Not set';
                        $("#docInfoName").text(info.name || '');
                        $("#docInfoFees").text(info.fees ? info.fees + ' EGP' : '');
                        $("#docInfoSlot").text(slotText);
                        $("#doctorInfoPanel").show();
                    }

                    if (!data.available && !data.no_schedule) {
                        $("#availabilityAlert").html('<i class="bi bi-exclamation-triangle-fill"></i> ' + (data.message || 'Doctor not available on this day')).show();
                        $("#timeSlot").html('<option value="">Not available</option>');
                        return;
                    }

                    $("#availabilityAlert").hide();

                    if (data.no_schedule) {
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
    <link rel="stylesheet" href="./mobile-header-fix.css">
</head>

<body>
    <div class="min-h-full">
        <?php $activePage = 'new-reservation'; require_once __DIR__ . '/../../includes/nav.php'; ?>
        <!-- <div class="absolute inset-x-0 -top-40 -z-10 transform-gpu overflow-hidden blur-3xl sm:-top-80" aria-hidden="true">
            <div class="relative left-[calc(50%-11rem)] aspect-[1155/678] w-[36.125rem] -translate-x-1/2 rotate-[30deg] bg-gradient-to-tr from-[#ff80b5] to-[#9089fc] opacity-30 sm:left-[calc(50%-30rem)] sm:w-[72.1875rem]" style="clip-path: polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)">
            </div>
        </div> -->
        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">New Reservation</h1>
            </div>
        </header>

        <main class="">
            <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">

                <form action="#" method="POST" class="mx-auto max-w-xl sm:mt-20">
                    <?= hms_csrf_field() ?>
                    <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">



                        <div class="sm:col-span-2">
                            <div class="mt-2.5">
                                <label for="specialization" class="block text-sm font-semibold leading-6 text-gray-900">specialization</label>
                                <div class="mt-2">
                                    <select id="specialization" name="specialization" onChange="getdoctor(this.value);" class="block w-full rounded-md border-2 px-3.5 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                        <option>Select specialization</option>
                                        <?php $ret = mysqli_query($conn, "select * from doctorspecilization");
                                        while ($row = mysqli_fetch_array($ret)) {
                                        ?>
                                            <option value="<?php echo htmlentities($row['specilization']); ?>">
                                                <?php echo htmlentities($row['specilization']); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="sm:col-span-2">
                            <div class="mt-2.5">
                                <label for="specialization" class="block text-sm font-semibold leading-6 text-gray-900">Doctor</label>
                                <div class="mt-2">
                                    <select id="doctor" name="Doctor" onChange="getfee(this.value);" class="block w-full rounded-md border-2 px-3.5 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                        <option>Select Doctor</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="sm:col-span-2">
                            <label for="price" class="block text-sm font-semibold leading-6 text-gray-900">price</label>
                            <div class="mt-2.5">
                                <select readonly name="price" id="price" class="block w-full rounded-md border-2 px-3.5 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                </select>
                            </div>
                        </div>

                        <!-- Doctor Info Panel -->
                        <div class="sm:col-span-2 doc-info-panel" id="doctorInfoPanel">
                            <div class="info-row">
                                <div class="info-item"><strong>Doctor:</strong> <span id="docInfoName"></span></div>
                                <div class="info-item"><strong>Fee:</strong> <span id="docInfoFees"></span></div>
                                <div class="info-item"><strong>Session:</strong> <span id="docInfoSlot"></span></div>
                            </div>
                        </div>

                        <div class="sm:col-span-2">
                            <div class="availability-alert" id="availabilityAlert"></div>
                        </div>

                        <div class="sm:col-span-2">
                            <label for="Date" class="block text-sm font-semibold leading-6 text-gray-900">Date</label>
                            <div class="mt-2.5">
                                <input type="Date" name="Date" id="Date" min="<?php echo date('Y-m-d'); ?>" onchange="loadAvailableSlots()" class="block w-full rounded-md border-2 px-3.5 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                            </div>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="Time" class="block text-sm font-semibold leading-6 text-gray-900">Available Time Slots</label>
                            <div class="mt-2.5">
                                <select name="Time" id="timeSlot" class="block w-full rounded-md border-2 px-3.5 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                    <option value="">Select doctor & date first</option>
                                </select>
                            </div>
                            <div id="manualTimeWrapper" style="display:none;margin-top:0.5rem;">
                                <input type="time" name="ManualTime" value="<?php echo date('H:i'); ?>" class="block w-full rounded-md border-2 px-3.5 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 sm:text-sm sm:leading-6">
                                <small style="color:#94a3b8;">Doctor has no schedule set — enter time manually</small>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Info Note -->
                    <div style="margin-top: 1.5rem; background: linear-gradient(135deg, #eff6ff, #faf5ff); border: 1px solid #c7d2fe; border-radius: 14px; padding: 1rem 1.2rem;">
                        <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                            <i class="bi bi-info-circle-fill" style="color: #6366f1; font-size: 1.3rem; flex-shrink: 0; margin-top: 2px;"></i>
                            <div>
                                <p style="font-weight: 700; color: #312e81; font-size: 0.9rem; margin: 0;">Booking Deposit (30%)</p>
                                <p style="color: #64748b; font-size: 0.8rem; margin: 4px 0 0 0;">After booking, you'll be asked to pay a 30% deposit to confirm your appointment. You can pay via <strong>Fawry</strong>, <strong>E-Wallet</strong>, or <strong>InstaPay</strong>. The remaining balance is paid at the hospital.</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-10">
                        <button type="submit" name="Save" class="block w-full rounded-md bg-indigo-600 px-3.5 py-2.5 text-center text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Book & Continue to Payment </button>
                    </div>
                </form>
            </div>
        </main>
        <!-- Scripts deferred to end of body for minimal TBT -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js" defer></script>
        <script src="https://cdn.jsdelivr.net/npm/flatpickr" defer></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
        <script src="/assets/js/responsive-nav.js" defer></script>
</body>

</html>
