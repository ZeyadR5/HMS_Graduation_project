<?php
require_once __DIR__ . '/../../includes/auth.php';

$conn = hms_db_connect();
if ($conn->connect_error) {
    die("Connection failed");
}
$conn->set_charset("utf8mb4");

$role = $_SESSION['role'] ?? '';
if ($role !== 'Doctor') {
    header("Location: /modules/dashboard.php");
    exit;
}

$doctorId = (int)($_SESSION['id'] ?? 0);
$doctorName = $_SESSION['username'] ?? 'Doctor';
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= $theme ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule — Echo HMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>
    <link rel="icon" href="/assets/images/l-gh.png">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --sch-primary: #0ea5e9;
            --sch-primary-dark: #0284c7;
            --sch-success: #10b981;
            --sch-danger: #ef4444;
            --sch-warning: #f59e0b;
            --sch-bg: #f1f5f9;
            --sch-card: #ffffff;
            --sch-border: #e2e8f0;
            --sch-text: #0f172a;
            --sch-text-muted: #64748b;
        }

        .sch-page { background: var(--sch-bg); min-height: 100vh; }

        .sch-card {
            background: var(--sch-card);
            border: 1px solid var(--sch-border);
            border-radius: 18px;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.06);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .sch-card-title {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--sch-text);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sch-card-title i { color: var(--sch-primary); }

        .sch-day-row {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 1rem;
            align-items: center;
            padding: 0.85rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .sch-day-row:last-child { border-bottom: none; }

        .sch-day-label {
            font-weight: 700;
            color: var(--sch-text);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sch-day-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
        }

        .sch-toggle {
            position: relative;
            width: 48px;
            height: 26px;
            flex-shrink: 0;
        }

        .sch-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .sch-toggle-slider {
            position: absolute;
            inset: 0;
            background: #cbd5e1;
            border-radius: 999px;
            cursor: pointer;
            transition: background 0.25s;
        }

        .sch-toggle-slider::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            top: 3px;
            left: 3px;
            transition: transform 0.25s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.15);
        }

        .sch-toggle input:checked + .sch-toggle-slider {
            background: var(--sch-success);
        }

        .sch-toggle input:checked + .sch-toggle-slider::after {
            transform: translateX(22px);
        }

        .sch-time-input {
            border: 2px solid var(--sch-border);
            border-radius: 10px;
            padding: 0.4rem 0.6rem;
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--sch-text);
            background: #f8fafc;
            width: 120px;
            transition: border-color 0.2s;
        }

        .sch-time-input:focus {
            outline: none;
            border-color: var(--sch-primary);
            background: white;
        }

        .sch-time-input:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .sch-slot-select {
            border: 2px solid var(--sch-border);
            border-radius: 10px;
            padding: 0.4rem 0.6rem;
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--sch-text);
            background: #f8fafc;
            width: 90px;
        }

        .sch-slot-select:disabled { opacity: 0.4; }

        .sch-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.6rem 1.2rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.88rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .sch-btn-primary {
            background: linear-gradient(135deg, var(--sch-primary), #14b8a6);
            color: white;
            box-shadow: 0 4px 14px rgba(14, 165, 233, 0.25);
        }

        .sch-btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(14, 165, 233, 0.35);
        }

        .sch-btn-danger {
            background: var(--sch-danger);
            color: white;
        }

        .sch-btn-outline {
            background: white;
            border: 2px solid var(--sch-border);
            color: var(--sch-text);
        }

        .sch-btn-outline:hover {
            border-color: var(--sch-primary);
            color: var(--sch-primary);
        }

        .sch-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.2rem 0.6rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .sch-badge-on { background: #dcfce7; color: #166534; }
        .sch-badge-off { background: #fef2f2; color: #991b1b; }
        .sch-badge-custom { background: #fef3c7; color: #92400e; }

        .sch-override-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.7rem 0;
            border-bottom: 1px solid #f1f5f9;
            flex-wrap: wrap;
        }

        .sch-toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            padding: 0.9rem 1.4rem;
            border-radius: 14px;
            color: white;
            font-weight: 700;
            font-size: 0.88rem;
            z-index: 9999;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.35s cubic-bezier(0.22, 1, 0.36, 1);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .sch-toast.show { transform: translateY(0); opacity: 1; }
        .sch-toast.success { background: var(--sch-success); }
        .sch-toast.error { background: var(--sch-danger); }

        .sch-appt-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.7rem 1rem;
            background: #f8fafc;
            border-radius: 12px;
            margin-bottom: 0.5rem;
            border: 1px solid #e2e8f0;
        }

        .sch-empty {
            text-align: center;
            color: var(--sch-text-muted);
            padding: 2rem;
            font-size: 0.9rem;
        }

        .sch-main-grid {
            display: grid; 
            grid-template-columns: 1fr 380px; 
            gap: 1.5rem;
        }

        /* Performance: prevent CLS by reserving space */
        #scheduleGrid {
            min-height: 320px;
            contain: layout style;
        }
        #overridesList {
            min-height: 60px;
        }
        #todayList {
            min-height: 80px;
        }
        /* content-visibility for below-fold sections */
        .sch-card:not(:first-child) {
            content-visibility: auto;
            contain-intrinsic-size: 0 200px;
        }

        .sch-override-form {
            display: flex; 
            gap: 0.75rem; 
            flex-wrap: wrap; 
            margin-bottom: 1rem; 
            align-items: flex-end;
        }

        .sch-ov-field {
            display: flex;
            flex-direction: column;
        }

        .sch-input-wrap {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            flex: 1;
        }

        @media (max-width: 991px) {
            .sch-main-grid { 
                grid-template-columns: 1fr; 
            }
        }

        @media (max-width: 640px) {
            .sch-day-row { grid-template-columns: 1fr; gap: 0.5rem; }
            .sch-day-controls { flex-wrap: wrap; gap: 0.5rem; }
            .sch-time-input, .sch-slot-select { width: 100%; min-width: 90px; }
            .sch-override-form { flex-direction: column; align-items: stretch; }
            .sch-ov-field { width: 100%; }
            #ovCustomFields { flex-direction: column; width: 100%; }
            .sch-btn-outline { width: 100%; justify-content: center; }
            .sch-override-row { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
            .sch-override-row .sch-btn { margin-left: 0 !important; width: 100%; justify-content: center; }
        }
    </style>
</head>

<body class="sch-page">
    <div class="min-h-full">
        <?php $activePage = 'my-schedule'; require_once __DIR__ . '/../../includes/nav.php'; ?>

        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">
                    <i class="bi bi-calendar2-week" style="color: var(--sch-primary)"></i> My Schedule
                </h1>
                <p class="mt-1 text-sm text-gray-500">Manage your weekly working hours, appointment duration, and days off.</p>
            </div>
        </header>

        <main>
            <div class="mx-auto max-w-7xl py-6 px-4 sm:px-6 lg:px-8">
                <div class="sch-main-grid">
                    <!-- LEFT: Weekly Schedule -->
                    <div>
                        <div class="sch-card">
                            <div class="sch-card-title">
                                <i class="bi bi-clock-history"></i> Weekly Schedule
                            </div>
                            <div id="scheduleGrid"></div>
                            <div style="margin-top: 1.2rem; display: flex; gap: 0.75rem; justify-content: flex-end;">
                                <button class="sch-btn sch-btn-primary" onclick="saveSchedule()">
                                    <i class="bi bi-check-circle"></i> Save Schedule
                                </button>
                            </div>
                        </div>

                        <!-- Day Overrides -->
                        <div class="sch-card">
                            <div class="sch-card-title">
                                <i class="bi bi-calendar-x"></i> Day Overrides (Vacations & Custom)
                            </div>
                            <div class="sch-override-form">
                                <div class="sch-ov-field">
                                    <label style="font-size: 0.78rem; font-weight: 600; color: var(--sch-text-muted);">Date</label>
                                    <input type="date" id="ovDate" class="sch-time-input" min="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="sch-ov-field">
                                    <label style="font-size: 0.78rem; font-weight: 600; color: var(--sch-text-muted);">Type</label>
                                    <select id="ovStatus" class="sch-slot-select" onchange="toggleOvCustom()">
                                        <option value="off">Day Off</option>
                                        <option value="custom">Custom Hours</option>
                                    </select>
                                </div>
                                <div id="ovCustomFields" style="display: none; gap: 0.5rem;">
                                    <div class="sch-ov-field">
                                        <label style="font-size: 0.78rem; font-weight: 600; color: var(--sch-text-muted);">From</label>
                                        <input type="time" id="ovStart" class="sch-time-input">
                                    </div>
                                    <div class="sch-ov-field">
                                        <label style="font-size: 0.78rem; font-weight: 600; color: var(--sch-text-muted);">To</label>
                                        <input type="time" id="ovEnd" class="sch-time-input">
                                    </div>
                                </div>
                                <div class="sch-ov-field" style="flex: 1; min-width: 150px;">
                                    <label style="font-size: 0.78rem; font-weight: 600; color: var(--sch-text-muted);">Reason</label>
                                    <input type="text" id="ovReason" class="sch-time-input" style="width: 100%;" placeholder="Optional reason">
                                </div>
                                <button class="sch-btn sch-btn-outline" onclick="addOverride()" style="align-self: flex-end;">
                                    <i class="bi bi-plus-circle"></i> Add
                                </button>
                            </div>
                            <div id="overridesList"></div>
                        </div>
                    </div>

                    <!-- RIGHT: Today's Appointments -->
                    <div>
                        <div class="sch-card" style="position: sticky; top: 100px;">
                            <div class="sch-card-title">
                                <i class="bi bi-calendar-check"></i> Today's Appointments
                            </div>
                            <div id="todayList">
                                <div class="sch-empty"><i class="bi bi-inbox"></i> Loading...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Toast -->
    <div id="schToast" class="sch-toast"></div>

    <script>
        const API_URL = '/modules/doctor/schedule-api.php';
        const HMS_CSRF_TOKEN = '<?= htmlspecialchars(hms_csrf_token(), ENT_QUOTES, 'UTF-8') ?>';
        const DAY_NAMES = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        const DAY_ICONS = ['bi-calendar', 'bi-calendar', 'bi-calendar', 'bi-calendar', 'bi-calendar', 'bi-calendar', 'bi-calendar'];
        let currentSchedule = {};
        let currentOverrides = [];

        // ========== TOAST ==========
        function showToast(message, type = 'success') {
            const toast = document.getElementById('schToast');
            toast.textContent = message;
            toast.className = 'sch-toast ' + type;
            setTimeout(() => toast.classList.add('show'), 10);
            setTimeout(() => toast.classList.remove('show'), 3000);
        }

        // ========== LOAD SCHEDULE ==========
        async function loadSchedule() {
            try {
                const form = new FormData();
                form.append('action', 'get_schedule');
                const resp = await fetch(API_URL, { method: 'POST', body: form });
                const data = await resp.json();
                if (data.success) {
                    currentSchedule = {};
                    data.schedules.forEach(s => {
                        currentSchedule[s.day_of_week] = s;
                    });
                    currentOverrides = data.overrides || [];
                    renderScheduleGrid();
                    renderOverrides();
                }
            } catch (e) {
                showToast('Failed to load schedule', 'error');
            }
        }

        // ========== RENDER SCHEDULE GRID ==========
        function renderScheduleGrid() {
            const container = document.getElementById('scheduleGrid');
            let html = '';
            for (let d = 0; d < 7; d++) {
                const sch = currentSchedule[d] || null;
                const isOn = sch && sch.status !== 'off';
                const startTime = sch ? sch.start_time.substring(0, 5) : '09:00';
                const endTime = sch ? sch.end_time.substring(0, 5) : '17:00';
                const slotDur = sch ? sch.slot_duration : 30;
                const disabledAttr = isOn ? '' : 'disabled';

                html += `
                <div class="sch-day-row">
                    <div class="sch-day-label">
                        <label class="sch-toggle">
                            <input type="checkbox" id="dayOn_${d}" ${isOn ? 'checked' : ''} onchange="toggleDayInputs(${d})">
                            <span class="sch-toggle-slider"></span>
                        </label>
                        ${DAY_NAMES[d]}
                    </div>
                    <div class="sch-day-controls" id="dayControls_${d}">
                        <div class="sch-input-wrap">
                            <span style="font-size:0.78rem;color:var(--sch-text-muted);font-weight:600;">From</span>
                            <input type="time" class="sch-time-input" id="dayStart_${d}" value="${startTime}" ${disabledAttr}>
                        </div>
                        <div class="sch-input-wrap">
                            <span style="font-size:0.78rem;color:var(--sch-text-muted);font-weight:600;">To</span>
                            <input type="time" class="sch-time-input" id="dayEnd_${d}" value="${endTime}" ${disabledAttr}>
                        </div>
                        <div class="sch-input-wrap">
                            <span style="font-size:0.78rem;color:var(--sch-text-muted);font-weight:600;">Slot</span>
                            <select class="sch-slot-select" id="daySlot_${d}" ${disabledAttr}>
                                <option value="15" ${slotDur == 15 ? 'selected' : ''}>15m</option>
                                <option value="20" ${slotDur == 20 ? 'selected' : ''}>20m</option>
                                <option value="30" ${slotDur == 30 ? 'selected' : ''}>30m</option>
                                <option value="45" ${slotDur == 45 ? 'selected' : ''}>45m</option>
                                <option value="60" ${slotDur == 60 ? 'selected' : ''}>60m</option>
                            </select>
                        </div>
                        <span class="sch-status-badge ${isOn ? 'sch-badge-on' : 'sch-badge-off'}" id="dayBadge_${d}">
                            ${isOn ? '● Available' : '● Off'}
                        </span>
                    </div>
                </div>`;
            }
            container.innerHTML = html;
        }

        function toggleDayInputs(d) {
            const isOn = document.getElementById('dayOn_' + d).checked;
            ['dayStart_', 'dayEnd_', 'daySlot_'].forEach(prefix => {
                document.getElementById(prefix + d).disabled = !isOn;
            });
            const badge = document.getElementById('dayBadge_' + d);
            badge.className = 'sch-status-badge ' + (isOn ? 'sch-badge-on' : 'sch-badge-off');
            badge.innerHTML = isOn ? '● Available' : '● Off';
        }

        // ========== SAVE SCHEDULE ==========
        async function saveSchedule() {
            const days = [];
            for (let d = 0; d < 7; d++) {
                const isOn = document.getElementById('dayOn_' + d).checked;
                days.push({
                    day_of_week: d,
                    start_time: document.getElementById('dayStart_' + d).value,
                    end_time: document.getElementById('dayEnd_' + d).value,
                    slot_duration: parseInt(document.getElementById('daySlot_' + d).value),
                    status: isOn ? 'available' : 'off'
                });
            }

            const form = new FormData();
            form.append('action', 'save_schedule');
            form.append('csrf_token', HMS_CSRF_TOKEN);
            form.append('days', JSON.stringify(days));

            try {
                const resp = await fetch(API_URL, { method: 'POST', body: form });
                const data = await resp.json();
                if (data.success) {
                    showToast('Schedule saved! ' + (data.changes > 0 ? data.changes + ' changes notified.' : ''), 'success');
                    loadSchedule();
                } else {
                    showToast(data.message || 'Failed to save', 'error');
                }
            } catch (e) {
                showToast('Network error', 'error');
            }
        }

        // ========== OVERRIDES ==========
        function toggleOvCustom() {
            const status = document.getElementById('ovStatus').value;
            const fields = document.getElementById('ovCustomFields');
            fields.style.display = status === 'custom' ? 'flex' : 'none';
        }

        // Init the override custom fields visibility
        document.addEventListener('DOMContentLoaded', () => {
            toggleOvCustom();
        });

        function renderOverrides() {
            const container = document.getElementById('overridesList');
            if (currentOverrides.length === 0) {
                container.innerHTML = '<div class="sch-empty"><i class="bi bi-calendar-check"></i> No upcoming overrides.</div>';
                return;
            }
            let html = '';
            currentOverrides.forEach(ov => {
                const badgeClass = ov.status === 'off' ? 'sch-badge-off' : 'sch-badge-custom';
                const badgeText = ov.status === 'off' ? 'Day Off' : 'Custom';
                const timeInfo = ov.status === 'custom' && ov.start_time && ov.end_time
                    ? ov.start_time.substring(0,5) + ' - ' + ov.end_time.substring(0,5)
                    : '';
                html += `
                <div class="sch-override-row">
                    <strong style="min-width:100px;">${ov.override_date}</strong>
                    <span class="sch-status-badge ${badgeClass}">${badgeText}</span>
                    ${timeInfo ? `<span style="font-size:0.82rem;color:var(--sch-text-muted);">${timeInfo}</span>` : ''}
                    ${ov.reason ? `<span style="font-size:0.82rem;color:var(--sch-text-muted);font-style:italic;">${ov.reason}</span>` : ''}
                    <button class="sch-btn sch-btn-danger" style="padding: 0.3rem 0.7rem; font-size: 0.78rem; margin-left: auto;" onclick="deleteOverride(${ov.id})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>`;
            });
            container.innerHTML = html;
        }

        async function addOverride() {
            const ovDate = document.getElementById('ovDate').value;
            const ovStatus = document.getElementById('ovStatus').value;
            const ovStart = document.getElementById('ovStart').value;
            const ovEnd = document.getElementById('ovEnd').value;
            const ovReason = document.getElementById('ovReason').value;

            if (!ovDate) { showToast('Please select a date', 'error'); return; }

            const form = new FormData();
            form.append('action', 'add_override');
            form.append('csrf_token', HMS_CSRF_TOKEN);
            form.append('override_date', ovDate);
            form.append('status', ovStatus);
            form.append('start_time', ovStart);
            form.append('end_time', ovEnd);
            form.append('reason', ovReason);

            try {
                const resp = await fetch(API_URL, { method: 'POST', body: form });
                const data = await resp.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    document.getElementById('ovDate').value = '';
                    document.getElementById('ovReason').value = '';
                    loadSchedule();
                } else {
                    showToast(data.message || 'Failed', 'error');
                }
            } catch (e) {
                showToast('Network error', 'error');
            }
        }

        async function deleteOverride(id) {
            if (!confirm('Remove this override?')) return;
            const form = new FormData();
            form.append('action', 'delete_override');
            form.append('csrf_token', HMS_CSRF_TOKEN);
            form.append('override_id', id);
            try {
                const resp = await fetch(API_URL, { method: 'POST', body: form });
                const data = await resp.json();
                if (data.success) {
                    showToast('Override removed', 'success');
                    loadSchedule();
                }
            } catch (e) {
                showToast('Network error', 'error');
            }
        }

        // ========== TODAY'S APPOINTMENTS ==========
        async function loadToday() {
            const form = new FormData();
            form.append('action', 'get_today_appointments');
            try {
                const resp = await fetch(API_URL, { method: 'POST', body: form });
                const data = await resp.json();
                const container = document.getElementById('todayList');
                if (data.success && data.appointments.length > 0) {
                    let html = '';
                    data.appointments.forEach(a => {
                        const statusIcon = (a.userStatus == 1 && a.doctorStatus == 1) ? '🟢' :
                                           (a.doctorStatus == 2) ? '✅' : '🔴';
                        html += `
                        <div class="sch-appt-item">
                            <div>
                                <div style="font-weight:700;font-size:0.9rem;">${statusIcon} ${a.patient_Name}</div>
                                <div style="font-size:0.78rem;color:var(--sch-text-muted);">
                                    ${a.appointmentTime ? a.appointmentTime.substring(0,5) : ''} — ${a.consultancyFees} EGP
                                </div>
                            </div>
                        </div>`;
                    });
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<div class="sch-empty"><i class="bi bi-inbox"></i> No appointments today.</div>';
                }
            } catch (e) {
                document.getElementById('todayList').innerHTML = '<div class="sch-empty">Failed to load</div>';
            }
        }

        // ========== INIT ==========
        // Use requestIdleCallback for non-critical today's appointments
        loadSchedule();
        if ('requestIdleCallback' in window) {
            requestIdleCallback(() => loadToday(), { timeout: 2000 });
        } else {
            setTimeout(loadToday, 500);
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <script src="/assets/js/responsive-nav.js" defer></script>
</body>
</html>
