<?php
$pageTitle = 'Attendance Reports';
require_once __DIR__ . '/../auth.php';
requireRole('principal', '../index.php');

$db        = getDB();
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date']   ?? date('Y-m-d');
$selClass  = $_GET['class']      ?? '';

$classes = $db->query("SELECT DISTINCT class FROM students WHERE is_active=1 ORDER BY CAST(class AS UNSIGNED)")->fetchAll();

// CSV export
if (isset($_GET['export'])) {
    $cw = $selClass ? " AND s.class=?" : "";
    $p  = $selClass ? [$startDate,$endDate,$selClass] : [$startDate,$endDate];
    $stmt = $db->prepare("SELECT s.roll_number,s.name,s.class,COUNT(DISTINCT sa.date) as days,SUM(sa.status='present') as present,SUM(sa.status='absent') as absent,SUM(sa.status='late') as late FROM students s LEFT JOIN student_attendance sa ON s.id=sa.student_id AND sa.date BETWEEN ? AND ? AND sa.period=1 WHERE s.is_active=1$cw GROUP BY s.id ORDER BY CAST(s.class AS UNSIGNED),s.roll_number");
    $stmt->execute($p);
    $rows = array_map(fn($r)=>[$r['roll_number'],$r['name'],'Class '.$r['class'],$r['days'],$r['present'],$r['absent'],$r['late'],$r['days']>0?round($r['present']/$r['days']*100,1).'%':'0%'], $stmt->fetchAll());
    exportCSV("attendance-report-$startDate-to-$endDate.csv",['Roll No','Name','Class','Days','Present','Absent','Late','%'],$rows);
}

require_once __DIR__ . '/../header.php';

// Class-wise summary ordered 1-10
$stmt = $db->prepare("SELECT s.class,COUNT(DISTINCT s.id) as students,SUM(CASE WHEN sa.status='present' AND sa.period=1 THEN 1 ELSE 0 END) as present,SUM(CASE WHEN sa.status='absent' AND sa.period=1 THEN 1 ELSE 0 END) as absent,SUM(CASE WHEN sa.status='late' AND sa.period=1 THEN 1 ELSE 0 END) as late FROM students s LEFT JOIN student_attendance sa ON s.id=sa.student_id AND sa.date BETWEEN ? AND ? WHERE s.is_active=1 GROUP BY s.class ORDER BY CAST(s.class AS UNSIGNED)");
$stmt->execute([$startDate,$endDate]);
$classSummary = $stmt->fetchAll();

// Per-student
$cw = $selClass ? " AND s.class=?" : "";
$p2 = $selClass ? [$startDate,$endDate,$selClass] : [$startDate,$endDate];
$stmt = $db->prepare("SELECT s.roll_number,s.name,s.class,COUNT(DISTINCT sa.date) as days,SUM(sa.status='present') as present,SUM(sa.status='absent') as absent,SUM(sa.status='late') as late FROM students s LEFT JOIN student_attendance sa ON s.id=sa.student_id AND sa.date BETWEEN ? AND ? AND sa.period=1 WHERE s.is_active=1$cw GROUP BY s.id ORDER BY CAST(s.class AS UNSIGNED),s.roll_number");
$stmt->execute($p2);
$students = $stmt->fetchAll();

// Frequent absentees
$stmt = $db->prepare("SELECT s.roll_number,s.name,s.class,COUNT(CASE WHEN sa.status='absent' THEN 1 END) as cnt FROM students s LEFT JOIN student_attendance sa ON s.id=sa.student_id AND sa.date BETWEEN ? AND ? WHERE s.is_active=1 GROUP BY s.id HAVING cnt>0 ORDER BY cnt DESC LIMIT 15");
$stmt->execute([$startDate,$endDate]);
$absentees = $stmt->fetchAll();
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="bar-chart-2"></i> Attendance Reports</h1>
        <p>School-wide attendance analytics</p>
    </div>
</div>

<div class="page-content">
    <!-- Filters -->
    <div class="card mb-6">
        <div class="card-body">
            <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
                <div class="form-group">
                    <label>From</label>
                    <input type="date" name="start_date" value="<?= $startDate ?>" onchange="this.form.submit()">
                </div>
                <div class="form-group">
                    <label>To</label>
                    <input type="date" name="end_date" value="<?= $endDate ?>" onchange="this.form.submit()">
                </div>
                <div class="form-group" style="min-width:140px">
                    <label>Class</label>
                    <select name="class" onchange="this.form.submit()">
                        <option value="">All Classes</option>
                        <?php foreach($classes as $c): ?>
                        <option value="<?= $c['class'] ?>" <?= $selClass==$c['class']?'selected':'' ?>>Class <?= $c['class'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <a href="?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&class=<?= urlencode($selClass) ?>&export=1" class="btn btn-secondary"><i data-feather="download"></i> Export CSV</a>
            </form>
        </div>
    </div>

    <!-- Class-wise Summary (Class 1 to 10) -->
    <div class="card mb-6">
        <div class="card-header"><h3><i data-feather="layers"></i> Class-wise Summary (Class 1 – 10)</h3></div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table class="table">
                    <thead><tr><th>Class</th><th style="text-align:center">Students</th><th style="text-align:center">Present</th><th style="text-align:center">Absent</th><th style="text-align:center">Late</th><th style="text-align:center">Attendance %</th><th style="text-align:center">Action</th></tr></thead>
                    <tbody>
                    <?php foreach($classSummary as $r):
                        $total = $r['present']+$r['absent']+$r['late'];
                        $pct   = $total>0 ? round($r['present']/$total*100,1) : 0;
                        $color = $pct>=75?'#10b981':($pct>=50?'#f59e0b':'#ef4444');
                    ?>
                    <tr>
                        <td><strong style="font-size:15px">Class <?= htmlspecialchars($r['class']) ?></strong></td>
                        <td style="text-align:center"><span class="badge" style="background:#dbeafe;color:#1e40af"><?= $r['students'] ?></span></td>
                        <td style="text-align:center"><span class="badge badge-present"><?= $r['present'] ?></span></td>
                        <td style="text-align:center"><span class="badge badge-absent"><?= $r['absent'] ?></span></td>
                        <td style="text-align:center"><span class="badge badge-late"><?= $r['late'] ?></span></td>
                        <td style="text-align:center">
                            <div style="display:flex;align-items:center;gap:8px;justify-content:center">
                                <div style="width:70px;height:6px;background:#e2e8f0;border-radius:3px"><div style="width:<?= $pct ?>%;height:100%;background:<?= $color ?>;border-radius:3px"></div></div>
                                <strong style="color:<?= $color ?>"><?= $pct ?>%</strong>
                            </div>
                        </td>
                        <td style="text-align:center">
                            <a href="../admin/class-performance.php?class=<?= urlencode($r['class']) ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="btn btn-sm btn-primary"><i data-feather="bar-chart-2"></i> Details</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Student Details -->
    <div class="card mb-6">
        <div class="card-header">
            <h3><i data-feather="users"></i> Student Attendance <?= $selClass ? '— Class '.$selClass : '(All Classes)' ?></h3>
        </div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table class="table">
                    <thead><tr><th>Roll No</th><th>Name</th><th>Class</th><th style="text-align:center">Days</th><th style="text-align:center">Present</th><th style="text-align:center">Absent</th><th style="text-align:center">Late</th><th style="text-align:center">%</th></tr></thead>
                    <tbody>
                    <?php
                    $prevClass = null;
                    foreach($students as $s):
                        $pct = $s['days']>0 ? round($s['present']/$s['days']*100,1) : 0;
                        $bg  = $pct>=75?'#d1fae5':($pct>=50?'#fef3c7':'#fee2e2');
                        $fc  = $pct>=75?'#065f46':($pct>=50?'#92400e':'#7f1d1d');
                        if ($s['class'] !== $prevClass): $prevClass = $s['class'];
                    ?>
                    <tr style="background:#f8fafc"><td colspan="8" style="padding:8px 14px;font-weight:700;color:#1e40af;font-size:13px">CLASS <?= htmlspecialchars($s['class']) ?></td></tr>
                    <?php endif; ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($s['roll_number']) ?></strong></td>
                        <td><?= htmlspecialchars($s['name']) ?></td>
                        <td><span class="badge" style="background:#dbeafe;color:#1e40af"><?= $s['class'] ?></span></td>
                        <td style="text-align:center"><?= $s['days'] ?></td>
                        <td style="text-align:center"><span class="badge badge-present"><?= $s['present'] ?></span></td>
                        <td style="text-align:center"><span class="badge badge-absent"><?= $s['absent'] ?></span></td>
                        <td style="text-align:center"><span class="badge badge-late"><?= $s['late'] ?></span></td>
                        <td style="text-align:center"><span class="badge" style="background:<?= $bg ?>;color:<?= $fc ?>"><?= $pct ?>%</span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Frequent Absentees -->
    <?php if (!empty($absentees)): ?>
    <div class="card">
        <div class="card-header"><h3><i data-feather="alert-circle"></i> Frequent Absentees</h3></div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table class="table">
                    <thead><tr><th>Roll No</th><th>Name</th><th>Class</th><th style="text-align:center">Absences</th></tr></thead>
                    <tbody>
                    <?php foreach($absentees as $s): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($s['roll_number']) ?></strong></td>
                        <td><?= htmlspecialchars($s['name']) ?></td>
                        <td><span class="badge" style="background:#dbeafe;color:#1e40af">Class <?= htmlspecialchars($s['class']) ?></span></td>
                        <td style="text-align:center"><span class="badge badge-absent"><?= $s['cnt'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
