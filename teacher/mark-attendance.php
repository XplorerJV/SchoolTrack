<?php
$pageTitle = 'Mark Attendance';
require_once __DIR__ . '/../auth.php';
requireRole(['teacher','admin','principal'], '../index.php');
require_once __DIR__ . '/../header.php';
require_once __DIR__ . '/../periods.php';

$db   = getDB();
$user = getCurrentUser();
$role = $user['role'];

$selectedClass  = $_GET['class'] ?? '';
if (empty($selectedClass)) {
    $redirect = $role === 'admin' ? 'classes.php' : ($role === 'principal' ? '../principal/classes.php' : 'classes.php');
    header("Location: $redirect"); exit;
}

$attendanceDate = $_GET['date']   ?? date('Y-m-d');
$selectedPeriod = max(1, min(9, (int)($_GET['period'] ?? 1)));
$periodTimes    = PERIOD_TIMES;

$error = $success = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postPeriod  = max(1, min(9, (int)($_POST['period'] ?? $selectedPeriod)));
    $postDate    = $_POST['date'] ?? $attendanceDate;
    $attendances = $_POST['attendance'] ?? [];
    $count = 0;

    foreach ($attendances as $sid => $data) {
        $status = $data['status'] ?? '';
        if (empty($status)) continue;
        $timeIn = !empty($data['time_in']) ? $data['time_in'] : null;
        $notes  = $data['notes'] ?? '';

        $chk = $db->prepare("SELECT id FROM student_attendance WHERE student_id=? AND date=? AND period=?");
        $chk->execute([$sid, $postDate, $postPeriod]);
        $existing = $chk->fetch();

        if ($existing) {
            $db->prepare("UPDATE student_attendance SET status=?,time_in=?,notes=?,marked_by='manual',marked_by_user_id=? WHERE student_id=? AND date=? AND period=?")
               ->execute([$status,$timeIn,$notes,$user['id'],$sid,$postDate,$postPeriod]);
        } else {
            $db->prepare("INSERT INTO student_attendance (student_id,date,time_in,period,status,marked_by,marked_by_user_id,notes) VALUES (?,?,?,?,?,'manual',?,?)")
               ->execute([$sid,$postDate,$timeIn,$postPeriod,$status,$user['id'],$notes]);
        }
        $count++;
    }

    auditLog($user['id'],'UPDATE','attendance',"Marked Period $postPeriod attendance for Class $selectedClass on $postDate ($count students)");
    $success       = "✅ Attendance saved for $count student(s) — Period $postPeriod ({$periodTimes[$postPeriod]['time']})";
    $selectedPeriod = $postPeriod;
    $attendanceDate = $postDate;
}

// Students
$stmt = $db->prepare("SELECT * FROM students WHERE class=? AND is_active=1 ORDER BY roll_number");
$stmt->execute([$selectedClass]);
$students = $stmt->fetchAll();

// Existing attendance map for selected period
$attendanceMap = [];
if (!empty($students)) {
    $ids = array_column($students,'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT * FROM student_attendance WHERE student_id IN ($ph) AND date=? AND period=?");
    $stmt->execute(array_merge($ids, [$attendanceDate, $selectedPeriod]));
    foreach ($stmt->fetchAll() as $r) $attendanceMap[$r['student_id']] = $r;
}

// Period completion stats (how many marked per period today)
$periodStats = [];
for ($p = 1; $p <= 9; $p++) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM student_attendance sa JOIN students s ON sa.student_id=s.id WHERE s.class=? AND sa.date=? AND sa.period=?");
    $stmt->execute([$selectedClass, $attendanceDate, $p]);
    $periodStats[$p] = (int)$stmt->fetchColumn();
}

// Back link based on role
$backLink = $role === 'admin' ? 'classes.php' : ($role === 'principal' ? '../principal/classes.php' : 'classes.php');
$folderLink = $role === 'admin' ? 'class-folder.php?class='.urlencode($selectedClass) : ($role === 'principal' ? '../principal/class-folders.php' : 'class-folders.php');
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="check-square"></i> Mark Attendance — Class <?= htmlspecialchars($selectedClass) ?></h1>
        <p><?= date('l, d M Y', strtotime($attendanceDate)) ?> &nbsp;|&nbsp;
           <strong style="color:#1e40af"><?= $periodTimes[$selectedPeriod]['label'] ?></strong>
           &nbsp;(<?= $periodTimes[$selectedPeriod]['time'] ?>)
        </p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="<?= $folderLink ?>" class="btn btn-secondary"><i data-feather="folder"></i> Class Folder</a>
        <a href="<?= $backLink ?>" class="btn btn-secondary"><i data-feather="arrow-left"></i> All Classes</a>
    </div>
</div>

<div class="page-content">
    <?php if ($error): ?><div class="alert alert-danger"><i data-feather="alert-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

    <!-- Date Selector -->
    <div class="card mb-6">
        <div class="card-body" style="padding:16px">
            <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
                <input type="hidden" name="class" value="<?= htmlspecialchars($selectedClass) ?>">
                <div class="form-group">
                    <label style="font-size:12px;font-weight:600;color:#64748b">Date</label>
                    <input type="date" name="date" value="<?= $attendanceDate ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()" style="padding:8px 12px">
                </div>
                <div class="form-group">
                    <label style="font-size:12px;font-weight:600;color:#64748b">Period</label>
                    <select name="period" onchange="this.form.submit()" style="padding:8px 12px">
                        <?php foreach($periodTimes as $p=>$pt): ?>
                        <option value="<?=$p?>" <?=$p==$selectedPeriod?'selected':''?>><?= $pt['label'] ?> (<?= $pt['time'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Period Quick Nav with schedule -->
    <div class="card mb-6">
        <div class="card-body" style="padding:16px">
            <div style="font-size:12px;font-weight:600;color:#64748b;margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px">
                Select Period — <?= date('d M Y', strtotime($attendanceDate)) ?>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                <?php foreach($periodTimes as $p=>$pt):
                    $marked  = $periodStats[$p];
                    $total   = count($students);
                    $done    = $total > 0 && $marked >= $total;
                    $partial = $marked > 0 && !$done;
                ?>
                <?php if ($p === 4): ?>
                <div style="display:flex;align-items:center;gap:4px;padding:6px 10px;background:#fef3c7;border-radius:6px;font-size:11px;color:#92400e;font-weight:600">
                    ☕ Break<br><span style="font-size:10px">11:00–11:30</span>
                </div>
                <?php endif; ?>
                <?php if ($p === 7): ?>
                <div style="display:flex;align-items:center;gap:4px;padding:6px 10px;background:#fef3c7;border-radius:6px;font-size:11px;color:#92400e;font-weight:600">
                    🍽️ Break<br><span style="font-size:10px">14:30–15:00</span>
                </div>
                <?php endif; ?>
                <a href="?class=<?= urlencode($selectedClass) ?>&date=<?= $attendanceDate ?>&period=<?= $p ?>"
                   style="position:relative;display:inline-flex;flex-direction:column;align-items:center;padding:8px 12px;border-radius:8px;text-decoration:none;font-size:12px;font-weight:600;min-width:70px;text-align:center;border:2px solid <?= $p==$selectedPeriod?'#1e40af':($done?'#10b981':($partial?'#f59e0b':'#e2e8f0')) ?>;background:<?= $p==$selectedPeriod?'#1e40af':($done?'#f0fdf4':($partial?'#fffbeb':'#f8fafc')) ?>;color:<?= $p==$selectedPeriod?'#fff':($done?'#065f46':($partial?'#92400e':'#475569')) ?>">
                    P<?= $p ?>
                    <span style="font-size:10px;font-weight:400;margin-top:2px"><?= $pt['time'] ?></span>
                    <?php if ($done): ?>
                    <span style="position:absolute;top:-5px;right:-5px;background:#10b981;color:#fff;border-radius:50%;width:16px;height:16px;font-size:9px;display:flex;align-items:center;justify-content:center">✓</span>
                    <?php elseif ($partial): ?>
                    <span style="position:absolute;top:-5px;right:-5px;background:#f59e0b;color:#fff;border-radius:50%;width:16px;height:16px;font-size:9px;display:flex;align-items:center;justify-content:center"><?= $marked ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
                <span style="margin-left:auto;font-size:12px;color:#94a3b8"><?= count($students) ?> students</span>
            </div>
        </div>
    </div>

    <!-- Attendance Form -->
    <form method="POST" action="?class=<?= urlencode($selectedClass) ?>&date=<?= $attendanceDate ?>&period=<?= $selectedPeriod ?>">
        <input type="hidden" name="period" value="<?= $selectedPeriod ?>">
        <input type="hidden" name="date"   value="<?= $attendanceDate ?>">

        <div class="card">
            <div class="card-header">
                <div>
                    <h3 style="margin:0"><i data-feather="edit-3"></i> <?= $periodTimes[$selectedPeriod]['label'] ?> &nbsp;·&nbsp; <?= $periodTimes[$selectedPeriod]['time'] ?></h3>
                    <p style="margin:4px 0 0;font-size:12px;color:#64748b">Class <?= htmlspecialchars($selectedClass) ?> &nbsp;·&nbsp; <?= date('d M Y', strtotime($attendanceDate)) ?></p>
                </div>
                <div style="display:flex;gap:8px">
                    <button type="button" onclick="markAll('present')" class="btn btn-sm btn-success"><i data-feather="check"></i> All Present</button>
                    <button type="button" onclick="markAll('absent')"  class="btn btn-sm btn-danger"><i data-feather="x"></i> All Absent</button>
                </div>
            </div>

            <div class="card-body" style="padding:0">
                <?php if (empty($students)): ?>
                <div style="padding:40px;text-align:center;color:#6b7280">No students in Class <?= htmlspecialchars($selectedClass) ?></div>
                <?php else: ?>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:40px">#</th>
                                <th style="width:90px">Roll No</th>
                                <th>Name</th>
                                <th style="width:70px">Section</th>
                                <th style="width:140px">Status</th>
                                <th style="width:110px">Time In</th>
                                <th style="width:160px">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($students as $i=>$s):
                            $att       = $attendanceMap[$s['id']] ?? null;
                            $curStatus = $att['status'] ?? 'present';
                            $curTime   = $att['time_in'] ?? $periodTimes[$selectedPeriod]['start'];
                            $curNotes  = $att['notes']   ?? '';
                        ?>
                        <tr id="row-<?= $s['id'] ?>" class="att-row" data-status="<?= $curStatus ?>">
                            <td style="color:#94a3b8;font-size:12px"><?= $i+1 ?></td>
                            <td><strong><?= htmlspecialchars($s['roll_number']) ?></strong></td>
                            <td>
                                <div style="font-weight:500"><?= htmlspecialchars($s['name']) ?></div>
                                <div style="font-size:11px;color:#94a3b8"><?= ucfirst($s['gender']??'') ?><?= $s['date_of_birth'] ? ' · '.date('d M Y',strtotime($s['date_of_birth'])) : '' ?></div>
                            </td>
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
            <div style="padding:16px 24px;background:#f8fafc;border-top:1px solid #e2e8f0;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <button type="submit" class="btn btn-success"><i data-feather="save"></i> Save <?= $periodTimes[$selectedPeriod]['label'] ?> Attendance</button>
                <a href="<?= $backLink ?>" class="btn btn-secondary">Cancel</a>
                <span id="liveCount" style="margin-left:auto;font-size:13px;color:#64748b;font-weight:500"></span>
            </div>
            <?php endif; ?>
        </div>
    </form>
</div>

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
    const c = { present:0, absent:0, late:0, excused:0 };
    document.querySelectorAll('.att-row').forEach(r => c[r.dataset.status] = (c[r.dataset.status]||0)+1);
    document.getElementById('liveCount').textContent =
        `✅ ${c.present} Present  ❌ ${c.absent} Absent  ⏰ ${c.late} Late  📋 ${c.excused} Excused`;
}
document.querySelectorAll('.att-row').forEach(row => {
    const colors = { present:'#f0fdf4', absent:'#fef2f2', late:'#fffbeb', excused:'#eff6ff' };
    row.style.background = colors[row.dataset.status] || '';
});
updateCount();
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
