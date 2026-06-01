<?php
$pageTitle = 'Mark Attendance';
require_once __DIR__ . '/../auth.php';
requireRole('teacher', '../index.php');
require_once __DIR__ . '/../header.php';

$db   = getDB();
$user = getCurrentUser();

$selectedClass  = $_GET['class'] ?? '';
if (empty($selectedClass)) { header('Location: classes.php'); exit; }

$attendanceDate = $_GET['date']   ?? date('Y-m-d');
$selectedPeriod = max(1, min(6, (int)($_GET['period'] ?? 1)));

$periodTimes = [
    1=>'08:00–09:00', 2=>'09:00–10:00', 3=>'10:00–11:00',
    4=>'11:30–12:30', 5=>'12:30–13:30', 6=>'13:30–14:30',
];

$error = $success = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postPeriod = max(1, min(6, (int)($_POST['period'] ?? $selectedPeriod)));
    $postDate   = $_POST['date'] ?? $attendanceDate;
    $attendances = $_POST['attendance'] ?? [];
    $count = 0;

    foreach ($attendances as $sid => $data) {
        $status = $data['status'] ?? '';
        if (empty($status)) continue;
        $timeIn = !empty($data['time_in']) ? $data['time_in'] : null;
        $notes  = $data['notes'] ?? '';

        // Check existing
        $chk = $db->prepare("SELECT id FROM student_attendance WHERE student_id=? AND date=? AND period=?");
        $chk->execute([$sid, $postDate, $postPeriod]);
        $existing = $chk->fetch();

        if ($existing) {
            $stmt = $db->prepare("UPDATE student_attendance SET status=?,time_in=?,notes=?,marked_by='manual',marked_by_user_id=? WHERE student_id=? AND date=? AND period=?");
            $stmt->execute([$status,$timeIn,$notes,$user['id'],$sid,$postDate,$postPeriod]);
        } else {
            $stmt = $db->prepare("INSERT INTO student_attendance (student_id,date,time_in,period,status,marked_by,marked_by_user_id,notes) VALUES (?,?,?,?,?,'manual',?,?)");
            $stmt->execute([$sid,$postDate,$timeIn,$postPeriod,$status,$user['id'],$notes]);
        }
        $count++;
    }

    auditLog($user['id'],'UPDATE','attendance',"Marked Period $postPeriod attendance for Class $selectedClass on $postDate ($count students)");
    $success = "Attendance saved for $count student(s) — Period $postPeriod";
    $selectedPeriod = $postPeriod;
    $attendanceDate = $postDate;
}

// Get students
$stmt = $db->prepare("SELECT * FROM students WHERE class=? AND is_active=1 ORDER BY roll_number");
$stmt->execute([$selectedClass]);
$students = $stmt->fetchAll();

// Get existing attendance map
$attendanceMap = [];
if (!empty($students)) {
    $ids = array_column($students,'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT * FROM student_attendance WHERE student_id IN ($ph) AND date=? AND period=?");
    $stmt->execute(array_merge($ids, [$attendanceDate, $selectedPeriod]));
    foreach ($stmt->fetchAll() as $r) $attendanceMap[$r['student_id']] = $r;
}

// Period completion stats
$periodStats = [];
for ($p=1;$p<=6;$p++) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM student_attendance sa JOIN students s ON sa.student_id=s.id WHERE s.class=? AND sa.date=? AND sa.period=?");
    $stmt->execute([$selectedClass, $attendanceDate, $p]);
    $periodStats[$p] = $stmt->fetchColumn();
}
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="check-square"></i> Mark Attendance — Class <?= htmlspecialchars($selectedClass) ?></h1>
        <p><?= date('l, d M Y', strtotime($attendanceDate)) ?> &nbsp;|&nbsp; Period <?= $selectedPeriod ?> (<?= $periodTimes[$selectedPeriod] ?>)</p>
    </div>
    <a href="classes.php" class="btn btn-secondary"><i data-feather="arrow-left"></i> Back to Classes</a>
</div>

<div class="page-content">
    <?php if ($error): ?><div class="alert alert-danger"><i data-feather="alert-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><i data-feather="check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <!-- Date + Period Selector -->
    <div class="card mb-6">
        <div class="card-body">
            <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
                <input type="hidden" name="class" value="<?= htmlspecialchars($selectedClass) ?>">
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="date" value="<?= $attendanceDate ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()">
                </div>
                <div class="form-group">
                    <label>Period</label>
                    <select name="period" onchange="this.form.submit()">
                        <?php foreach($periodTimes as $p=>$t): ?>
                        <option value="<?=$p?>" <?=$p==$selectedPeriod?'selected':''?>>Period <?=$p?> (<?=$t?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Period Quick Nav -->
    <div class="card mb-6">
        <div class="card-body" style="padding:16px">
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <span style="font-size:13px;color:#64748b;font-weight:600;margin-right:4px">Periods:</span>
                <?php foreach($periodTimes as $p=>$t): ?>
                <a href="?class=<?= urlencode($selectedClass) ?>&date=<?= $attendanceDate ?>&period=<?= $p ?>"
                   class="btn btn-sm <?= $p==$selectedPeriod?'btn-primary':'btn-secondary' ?>"
                   style="position:relative">
                    P<?= $p ?>
                    <?php if($periodStats[$p]>0): ?>
                    <span style="position:absolute;top:-6px;right:-6px;background:#10b981;color:#fff;border-radius:50%;width:16px;height:16px;font-size:9px;display:flex;align-items:center;justify-content:center;font-weight:700"><?= $periodStats[$p] ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
                <span style="margin-left:auto;font-size:12px;color:#94a3b8"><?= count($students) ?> students in class</span>
            </div>
        </div>
    </div>

    <!-- Attendance Form -->
    <form method="POST" action="?class=<?= urlencode($selectedClass) ?>&date=<?= $attendanceDate ?>&period=<?= $selectedPeriod ?>">
        <input type="hidden" name="period" value="<?= $selectedPeriod ?>">
        <input type="hidden" name="date" value="<?= $attendanceDate ?>">

        <div class="card">
            <div class="card-header">
                <h3><i data-feather="edit-3"></i> Period <?= $selectedPeriod ?> — <?= $periodTimes[$selectedPeriod] ?></h3>
                <div style="display:flex;gap:8px">
                    <button type="button" onclick="markAll('present')" class="btn btn-sm btn-success">All Present</button>
                    <button type="button" onclick="markAll('absent')"  class="btn btn-sm btn-danger">All Absent</button>
                </div>
            </div>
            <div class="card-body" style="padding:0">
                <?php if (empty($students)): ?>
                <div style="padding:40px;text-align:center;color:#6b7280">No students found in Class <?= htmlspecialchars($selectedClass) ?></div>
                <?php else: ?>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:50px">#</th>
                                <th style="width:90px">Roll No</th>
                                <th>Name</th>
                                <th style="width:70px">Gender</th>
                                <th style="width:80px">Section</th>
                                <th style="width:130px">Status</th>
                                <th style="width:110px">Time In</th>
                                <th style="width:160px">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($students as $i=>$s):
                            $att = $attendanceMap[$s['id']] ?? null;
                            $curStatus = $att['status'] ?? 'present';
                            $curTime   = $att['time_in'] ?? '';
                            $curNotes  = $att['notes'] ?? '';
                        ?>
                        <tr id="row-<?= $s['id'] ?>" class="att-row" data-status="<?= $curStatus ?>">
                            <td style="color:#94a3b8"><?= $i+1 ?></td>
                            <td><strong><?= htmlspecialchars($s['roll_number']) ?></strong></td>
                            <td>
                                <?= htmlspecialchars($s['name']) ?>
                                <?php if($s['date_of_birth']): ?>
                                <div style="font-size:11px;color:#94a3b8">DOB: <?= date('d M Y',strtotime($s['date_of_birth'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:13px;color:#64748b"><?= ucfirst($s['gender']??'-') ?></td>
                            <td><span class="badge" style="background:#f1f5f9;color:#475569"><?= htmlspecialchars($s['section']??'-') ?></span></td>
                            <td>
                                <select name="attendance[<?= $s['id'] ?>][status]"
                                        class="status-select form-input"
                                        style="padding:6px 8px;font-size:13px"
                                        onchange="updateRow(this,<?= $s['id'] ?>)">
                                    <option value="present" <?= $curStatus==='present'?'selected':'' ?>>✅ Present</option>
                                    <option value="absent"  <?= $curStatus==='absent' ?'selected':'' ?>>❌ Absent</option>
                                    <option value="late"    <?= $curStatus==='late'   ?'selected':'' ?>>⏰ Late</option>
                                    <option value="excused" <?= $curStatus==='excused'?'selected':'' ?>>📋 Excused</option>
                                </select>
                            </td>
                            <td>
                                <input type="time" name="attendance[<?= $s['id'] ?>][time_in]"
                                       value="<?= htmlspecialchars($curTime) ?>"
                                       class="form-input" style="padding:6px 8px;font-size:13px">
                            </td>
                            <td>
                                <input type="text" name="attendance[<?= $s['id'] ?>][notes]"
                                       value="<?= htmlspecialchars($curNotes) ?>"
                                       placeholder="Reason..."
                                       class="form-input" style="padding:6px 8px;font-size:13px">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($students)): ?>
            <div style="padding:16px 24px;background:#f8fafc;border-top:1px solid #e2e8f0;display:flex;gap:10px;align-items:center">
                <button type="submit" class="btn btn-success"><i data-feather="save"></i> Save Period <?= $selectedPeriod ?> Attendance</button>
                <a href="classes.php" class="btn btn-secondary">Cancel</a>
                <span id="liveCount" style="margin-left:auto;font-size:13px;color:#64748b"></span>
            </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>

<script>
function updateRow(sel, id) {
    const row = document.getElementById('row-' + id);
    row.dataset.status = sel.value;
    const colors = { present:'#f0fdf4', absent:'#fef2f2', late:'#fffbeb', excused:'#eff6ff' };
    row.style.background = colors[sel.value] || '';
    updateCount();
}

function markAll(status) {
    document.querySelectorAll('.status-select').forEach(sel => {
        sel.value = status;
        updateRow(sel, sel.name.match(/\[(\d+)\]/)[1]);
    });
}

function updateCount() {
    const counts = { present:0, absent:0, late:0, excused:0 };
    document.querySelectorAll('.att-row').forEach(r => counts[r.dataset.status] = (counts[r.dataset.status]||0)+1);
    document.getElementById('liveCount').textContent =
        `✅ ${counts.present} Present  ❌ ${counts.absent} Absent  ⏰ ${counts.late} Late  📋 ${counts.excused} Excused`;
}

// Init row colors and count
document.querySelectorAll('.att-row').forEach(row => {
    const colors = { present:'#f0fdf4', absent:'#fef2f2', late:'#fffbeb', excused:'#eff6ff' };
    row.style.background = colors[row.dataset.status] || '';
});
updateCount();
</script>
