<?php
$pageTitle = 'Mark Student Attendance';
require_once __DIR__ . '/../auth.php';
requireRole('teacher', '../index.php');
require_once __DIR__ . '/../header.php';

$db = getDB();
$user = getCurrentUser();
$attendanceDate = $_GET['date'] ?? date('Y-m-d');
$selectedClass = $_GET['class'] ?? '';
$error = $success = '';

// Get all classes (teacher can mark attendance for any class)
$stmt = $db->prepare("SELECT DISTINCT class FROM students WHERE is_active = 1 ORDER BY class");
$stmt->execute();
$classes = $stmt->fetchAll();

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attendances = $_POST['attendance'] ?? [];
    $markedCount = 0;
    $errors = [];

    foreach ($attendances as $studentId => $data) {
        $status = $data['status'] ?? 'present';
        $timeIn = $data['time_in'] ?? null;
        $notes = $data['notes'] ?? '';
        
        if (empty($status)) continue;

        try {
            // Check if attendance already exists
            $stmt = $db->prepare("SELECT id FROM student_attendance WHERE student_id = ? AND date = ?");
            $stmt->execute([$studentId, $attendanceDate]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Update existing record
                $stmt = $db->prepare("
                    UPDATE student_attendance 
                    SET status = ?, time_in = ?, notes = ?, marked_by = 'manual', marked_by_user_id = ?
                    WHERE student_id = ? AND date = ?
                ");
                $stmt->execute([$status, $timeIn, $notes, $user['id'], $studentId, $attendanceDate]);
                $action = 'UPDATED';
            } else {
                // Insert new record
                $stmt = $db->prepare("
                    INSERT INTO student_attendance (student_id, date, time_in, status, marked_by, marked_by_user_id, notes)
                    VALUES (?, ?, ?, ?, 'manual', ?, ?)
                ");
                $stmt->execute([$studentId, $attendanceDate, $timeIn, $status, $user['id'], $notes]);
                $action = 'CREATED';
            }

            // Log in audit
            $studentStmt = $db->prepare("SELECT name FROM students WHERE id = ?");
            $studentStmt->execute([$studentId]);
            $student = $studentStmt->fetch();
            
            auditLog($user['id'], 'UPDATE', 'student_attendance', "Teacher manually $action attendance for {$student['name']} on {$attendanceDate}");
            $markedCount++;
        } catch (Exception $e) {
            $errors[] = "Error marking attendance: " . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $error = implode('; ', $errors);
    } elseif ($markedCount > 0) {
        $success = "Attendance marked successfully for $markedCount student(s)!";
    }
}

// Get students to mark
$query = "SELECT * FROM students WHERE is_active = 1";
$params = [];

if (!empty($selectedClass)) {
    $query .= " AND class = ?";
    $params[] = $selectedClass;
}

$query .= " ORDER BY class, roll_number";

$stmt = $db->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get existing attendance for this date
$attendanceMap = [];
if (!empty($students)) {
    $studentIds = array_column($students, 'id');
    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
    $stmt = $db->prepare("
        SELECT * FROM student_attendance 
        WHERE student_id IN ($placeholders) AND date = ?
    ");
    $params = array_merge($studentIds, [$attendanceDate]);
    $stmt->execute($params);
    
    foreach ($stmt->fetchAll() as $record) {
        $attendanceMap[$record['student_id']] = $record;
    }
}
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="edit-2"></i> Mark Student Attendance</h1>
        <p>Manually mark and correct student attendance records</p>
    </div>
</div>

<div class="page-content">
    <?php if ($error): ?>
    <div class="alert alert-danger">
        <i data-feather="alert-circle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
    <div class="alert alert-success">
        <i data-feather="check-circle"></i> <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card mb-6">
        <div class="card-body">
            <form method="GET" class="form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Select Date</label>
                        <input type="date" name="date" value="<?= $attendanceDate ?>" class="form-input" onchange="this.form.submit()">
                    </div>
                    <div class="form-group">
                        <label>Filter by Class</label>
                        <select name="class" class="form-input" onchange="this.form.submit()">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $cls): ?>
                            <option value="<?= htmlspecialchars($cls['class']) ?>" <?= $selectedClass === $cls['class'] ? 'selected' : '' ?>>
                                Class <?= htmlspecialchars($cls['class']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Quick card-scan lookup -->
    <div class="card mb-6">
        <div class="card-header">
            <h3><i data-feather="cpu"></i> Quick Card Scan</h3>
        </div>
        <div class="card-body">
            <div style="display:flex;gap:10px;align-items:center">
                <div>
                    <label>Card UID</label>
                    <input id="cardUidInput" type="text" placeholder="Scan or enter card UID" class="form-input" style="padding:8px;width:220px">
                </div>
                <div>
                    <label>Select Date</label>
                    <input id="cardDate" type="date" value="<?= $attendanceDate ?>" class="form-input" style="padding:8px;width:160px">
                </div>
                <div>
                    <label>Status</label>
                    <select id="cardStatus" class="form-input" style="padding:8px;width:140px">
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="late">Late</option>
                        <option value="excused">Excused</option>
                    </select>
                </div>
                <div style="display:flex;flex-direction:column">
                    <label>&nbsp;</label>
                    <button id="markCardBtn" type="button" class="btn btn-primary">Mark Attendance</button>
                </div>
            </div>

            <div id="cardResult" style="margin-top:12px"></div>
            <div id="scanHistory" style="margin-top:12px;border-top:1px dashed #e5e7eb;padding-top:12px">
                <h4 style="margin:0 0 8px 0;font-size:14px;color:#111827">Recent Scans</h4>
                <ul id="scanList" style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:6px"></ul>
            </div>
        </div>
    </div>

    <!-- Attendance Marking Form -->
    <form method="POST" action="?date=<?= urlencode($attendanceDate) ?>&class=<?= urlencode($selectedClass) ?>" class="form">
        <div class="card">
            <div class="card-header">
                <h3><i data-feather="edit-3"></i> Mark Attendance for <?= htmlspecialchars(date('d M Y', strtotime($attendanceDate))) ?></h3>
            </div>
            <div class="card-body">
                <?php if (empty($students)): ?>
                <div style="padding:40px;text-align:center;color:#6b7280;">
                    <p>No students found for the selected filters.</p>
                </div>
                <?php else: ?>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:60px">Roll No</th>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th style="width:120px">Status</th>
                                <th style="width:100px">Time In</th>
                                <th style="width:200px">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student):
                                $existing = $attendanceMap[$student['id']] ?? null;
                                $currentStatus = $existing['status'] ?? 'present';
                                $currentTime = $existing['time_in'] ?? '';
                                $currentNotes = $existing['notes'] ?? '';
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($student['roll_number']) ?></strong></td>
                                <td><?= htmlspecialchars($student['name']) ?></td>
                                <td><?= htmlspecialchars($student['class']) ?></td>
                                <td>
                                    <select name="attendance[<?= $student['id'] ?>][status]" class="form-input" style="width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:6px">
                                        <option value="">-</option>
                                        <option value="present" <?= $currentStatus === 'present' ? 'selected' : '' ?>>Present</option>
                                        <option value="absent" <?= $currentStatus === 'absent' ? 'selected' : '' ?>>Absent</option>
                                        <option value="late" <?= $currentStatus === 'late' ? 'selected' : '' ?>>Late</option>
                                        <option value="excused" <?= $currentStatus === 'excused' ? 'selected' : '' ?>>Excused</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="time" name="attendance[<?= $student['id'] ?>][time_in]" value="<?= htmlspecialchars($currentTime) ?>" class="form-input" style="width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:6px">
                                </td>
                                <td>
                                    <input type="text" name="attendance[<?= $student['id'] ?>][notes]" value="<?= htmlspecialchars($currentNotes) ?>" placeholder="Reason/Notes" class="form-input" style="width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:6px">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body" style="background:#f9fafb;border-top:1px solid #e5e7eb">
                <div style="display:flex;gap:10px">
                    <button type="submit" class="btn btn-success">
                        <i data-feather="save"></i> Save Attendance
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i data-feather="x"></i> Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>

    <!-- Help Text -->
    <div style="margin-top:20px;padding:16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;color:#1e40af">
        <p style="margin:0;font-size:13px;line-height:1.6">
            <strong><i data-feather="info" style="display:inline;width:16px;height:16px;margin-right:6px"></i>Note:</strong>
            Mark attendance for students in your class. All changes are logged in the audit trail for transparency. 
            You can correct previous records by selecting their date and updating the status.
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const cardInput = document.getElementById('cardUidInput');
    const resultDiv = document.getElementById('cardResult');
    const markBtn = document.getElementById('markCardBtn');
    const dateInput = document.getElementById('cardDate');
    const statusSelect = document.getElementById('cardStatus');

    // Default date to today if empty
    if (!dateInput.value) {
        const today = new Date().toISOString().slice(0,10);
        dateInput.value = today;
    }

    markBtn.disabled = true;

    function showMessage(html, isError){
        resultDiv.innerHTML = '<div style="padding:10px;border-radius:6px;color:'+ (isError? '#b91c1c':'#075985') +';background:'+ (isError? '#fee2e2':'#eff6ff') +';">'+html+'</div>';
    }

    // Debounce lookup when typing/scanning
    let lookupTimer = null;
    cardInput.addEventListener('input', function(){
        clearTimeout(lookupTimer);
        lookupTimer = setTimeout(lookupCard, 300);
    });

    cardInput.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); lookupCard(); } });

    function lookupCard(){
        const uid = cardInput.value.trim();
        if (!uid) {
            resultDiv.innerHTML = '';
            markBtn.disabled = true;
            return;
        }
        showMessage('Looking up card...', false);
        const date = dateInput.value || new Date().toISOString().slice(0,10);

        fetch('../api/student_lookup.php?card_uid=' + encodeURIComponent(uid) + '&date=' + encodeURIComponent(date))
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    markBtn.disabled = true;
                    return showMessage(data.message || 'Student not found', true);
                }
                const s = data.student;
                const att = data.attendance;
                if (att) {
                    showMessage('<strong>'+escapeHtml(s.name)+'</strong> — Already marked on '+escapeHtml(data.date)+' at '+escapeHtml(att.time_in || 'N/A')+' as '+escapeHtml(att.status));
                    markBtn.disabled = true;
                    // add to history as existing mark
                    addToHistory({ name: s.name, roll: s.roll_number, class: s.class, date: data.date, time: att.time_in || '-', status: att.status });
                } else {
                    showMessage('<strong>'+escapeHtml(s.name)+'</strong> — Roll: '+escapeHtml(s.roll_number)+' — Class: '+escapeHtml(s.class)+(s.section?(' / '+escapeHtml(s.section):''));
                    markBtn.disabled = false;
                    // default suggested status
                    statusSelect.value = 'present';
                }
            })
            .catch(err => { markBtn.disabled = true; showMessage('Lookup failed', true); });
    }

    markBtn.addEventListener('click', function(){
        const uid = cardInput.value.trim();
        if (!uid) return showMessage('Enter card UID first', true);
        showMessage('Looking up student...', false);

        const date = dateInput.value || new Date().toISOString().slice(0,10);

        fetch('../api/student_lookup.php?card_uid=' + encodeURIComponent(uid) + '&date=' + encodeURIComponent(date))
            .then(r => r.json())
            .then(data => {
                if (!data.success) return showMessage(data.message || 'Student not found', true);
                const s = data.student;
                // Prepare form data
                const fd = new FormData();
                fd.append('student_id', s.id);
                fd.append('date', date);
                fd.append('time_in', new Date().toLocaleTimeString('en-GB',{hour12:false,hour:'2-digit',minute:'2-digit'}));
                fd.append('status', statusSelect.value);
                fd.append('marked_by', 'card');

                showMessage('Marking attendance...', false);

                fetch('../api/mark_attendance.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            showMessage('Saved: '+escapeHtml(s.name)+' ('+escapeHtml(res.message)+')', false);
                            markBtn.disabled = true;
                            // play beep and animate
                            playBeep();
                            showSuccessAnimation();
                            // push to history
                            const markedTime = new Date().toLocaleTimeString('en-GB',{hour12:false,hour:'2-digit',minute:'2-digit'});
                            addToHistory({ name: s.name, roll: s.roll_number, class: s.class, date: date, time: markedTime, status: statusSelect.value });
                            // update table row if present on page
                            updateTableForStudent(s.id, statusSelect.value, markedTime, 'Marked via card');
                        } else {
                            showMessage(res.message || 'Error saving', true);
                        }
                    })
                    .catch(err => showMessage('Save failed', true));
            })
            .catch(err => showMessage('Lookup failed', true));
    });

    function escapeHtml(str){
        return String(str).replace(/[&<>"'`]/g, function(s){ return '&#'+s.charCodeAt(0)+';'; });
    }

    // Update attendance table row for a student if present on the page
    function updateTableForStudent(studentId, status, timeIn, notes){
        try{
            const statusEl = document.querySelector('select[name="attendance['+studentId+'][status]"]');
            const timeEl = document.querySelector('input[name="attendance['+studentId+'][time_in]"]');
            const notesEl = document.querySelector('input[name="attendance['+studentId+'][notes]"]');
            if (statusEl) { statusEl.value = status; statusEl.dispatchEvent(new Event('change')); }
            if (timeEl) { timeEl.value = timeIn; }
            if (notesEl) { notesEl.value = notes; }

            // highlight row
            const row = statusEl ? statusEl.closest('tr') : (timeEl ? timeEl.closest('tr') : null);
            if (row) {
                const orig = row.style.background;
                row.style.transition = 'background .6s';
                row.style.background = '#ecfdf5';
                setTimeout(()=>{ row.style.background = orig || ''; }, 800);
            }
        }catch(e){ /* ignore */ }
    }

    // History handling (localStorage)
    const HISTORY_KEY = 'scan_history_v1';
    function loadHistory(){
        try { return JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]'); } catch(e){ return []; }
    }
    function saveHistory(arr){ localStorage.setItem(HISTORY_KEY, JSON.stringify(arr)); }
    function addToHistory(entry){
        const arr = loadHistory();
        arr.unshift(entry);
        while (arr.length > 5) arr.pop();
        saveHistory(arr);
        renderHistory();
    }
    function renderHistory(){
        const list = document.getElementById('scanList');
        list.innerHTML = '';
        const arr = loadHistory();
        if (!arr.length) { list.innerHTML = '<li style="color:#6b7280">No recent scans</li>'; return; }
        arr.forEach(it => {
            const li = document.createElement('li');
            li.style.padding = '8px'; li.style.borderRadius = '6px'; li.style.background = '#f8fafc'; li.style.display = 'flex'; li.style.justifyContent = 'space-between'; li.style.alignItems = 'center';
            li.innerHTML = '<div style="font-size:13px"><strong>'+escapeHtml(it.name)+'</strong> <span style="color:#6b7280">('+escapeHtml(it.roll)+')</span><div style="font-size:12px;color:#6b7280">'+escapeHtml(it.class)+' — '+escapeHtml(it.date)+' '+escapeHtml(it.time)+'</div></div><div style="font-weight:600;color:#065f46">'+escapeHtml(it.status)+'</div>';
            list.appendChild(li);
        });
    }

    // Beep using WebAudio
    function playBeep(){
        try{
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = 'sine'; o.frequency.value = 1100;
            g.gain.value = 0.02;
            o.connect(g); g.connect(ctx.destination);
            o.start();
            setTimeout(()=>{ o.stop(); ctx.close(); }, 120);
        }catch(e){/* ignore */}
    }

    // Success animation
    function showSuccessAnimation(){
        const el = document.createElement('div');
        el.style.position = 'fixed'; el.style.right = '20px'; el.style.top = '20px'; el.style.padding = '12px 16px'; el.style.background = '#ecfccb'; el.style.color = '#166534'; el.style.borderRadius = '8px'; el.style.boxShadow = '0 6px 18px rgba(16,24,40,0.08)'; el.style.fontWeight='700'; el.style.zIndex = 9999;
        el.innerHTML = '✓ Attendance saved';
        document.body.appendChild(el);
        el.animate([{ transform: 'translateY(-8px)', opacity: 0 }, { transform: 'translateY(0)', opacity: 1 }, { transform: 'translateY(-8px)', opacity: 0 }], { duration: 1400 });
        setTimeout(()=>{ document.body.removeChild(el); }, 1400);
    }

    // render existing history on load
    renderHistory();
});
</script>
