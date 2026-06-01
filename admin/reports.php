<?php
$pageTitle = 'Reports';
require_once __DIR__ . '/../auth.php';
requireRole(['admin','principal'], '../index.php');

$db            = getDB();
$reportType    = $_GET['report']     ?? 'daily';
$startDate     = $_GET['start_date'] ?? date('Y-m-01');
$endDate       = $_GET['end_date']   ?? date('Y-m-d');
$selectedClass = $_GET['class']      ?? '';

// Classes ordered 1-10
$classes = $db->query("SELECT DISTINCT class FROM students WHERE is_active=1 ORDER BY CAST(class AS UNSIGNED)")->fetchAll();

// CSV exports — before any output
if (isset($_GET['export'])) {
    $classWhere = $selectedClass ? " AND s.class=?" : "";
    $baseParams = $selectedClass ? [$startDate, $endDate, $selectedClass] : [$startDate, $endDate];

    switch ($_GET['export']) {
        case 'daily':
            $date = $_GET['export_date'] ?? $endDate;
            $stmt = $db->prepare("SELECT s.roll_number,s.name,s.class,sa.status,sa.time_in,sa.period FROM student_attendance sa JOIN students s ON s.id=sa.student_id WHERE sa.date=? ORDER BY CAST(s.class AS UNSIGNED),s.roll_number");
            $stmt->execute([$date]);
            $rows = array_map(fn($r)=>[$r['roll_number'],$r['name'],'Class '.$r['class'],'P'.$r['period'],ucfirst($r['status']),$r['time_in']??'-'], $stmt->fetchAll());
            exportCSV("daily-attendance-$date.csv",['Roll No','Name','Class','Period','Status','Time In'],$rows);
            break;

        case 'monthly':
            $stmt = $db->prepare("SELECT s.roll_number,s.name,s.class,COUNT(DISTINCT sa.date) as days,SUM(sa.status='present') as present,SUM(sa.status='absent') as absent,SUM(sa.status='late') as late FROM students s LEFT JOIN student_attendance sa ON s.id=sa.student_id AND sa.date BETWEEN ? AND ? AND sa.period=1 WHERE s.is_active=1$classWhere GROUP BY s.id ORDER BY CAST(s.class AS UNSIGNED),s.roll_number");
            $stmt->execute($baseParams);
            $rows = array_map(fn($r)=>[$r['roll_number'],$r['name'],'Class '.$r['class'],$r['days'],$r['present'],$r['absent'],$r['late'],$r['days']>0?round($r['present']/$r['days']*100,1).'%':'0%'], $stmt->fetchAll());
            exportCSV("monthly-attendance-$startDate-to-$endDate.csv",['Roll No','Name','Class','Days','Present','Absent','Late','%'],$rows);
            break;

        case 'classwise':
            $stmt = $db->query("SELECT s.class,COUNT(DISTINCT s.id) as students,COUNT(DISTINCT CASE WHEN sa.status='present' AND sa.period=1 THEN sa.id END) as present,COUNT(DISTINCT CASE WHEN sa.status='absent' AND sa.period=1 THEN sa.id END) as absent FROM students s LEFT JOIN student_attendance sa ON s.id=sa.student_id AND sa.date BETWEEN '$startDate' AND '$endDate' WHERE s.is_active=1 GROUP BY s.class ORDER BY CAST(s.class AS UNSIGNED)");
            $rows = array_map(fn($r)=>['Class '.$r['class'],$r['students'],$r['present'],$r['absent'],$r['present']+$r['absent']>0?round($r['present']/($r['present']+$r['absent'])*100,1).'%':'0%'], $stmt->fetchAll());
            exportCSV("classwise-report-$startDate-to-$endDate.csv",['Class','Students','Present','Absent','Attendance %'],$rows);
            break;

        case 'absentees':
            $stmt = $db->prepare("SELECT s.roll_number,s.name,s.class,COUNT(CASE WHEN sa.status='absent' THEN 1 END) as cnt FROM students s LEFT JOIN student_attendance sa ON s.id=sa.student_id AND sa.date BETWEEN ? AND ? WHERE s.is_active=1 GROUP BY s.id HAVING cnt>0 ORDER BY cnt DESC");
            $stmt->execute([$startDate,$endDate]);
            $rows = array_map(fn($r)=>[$r['roll_number'],$r['name'],'Class '.$r['class'],$r['cnt']], $stmt->fetchAll());
            exportCSV("frequent-absentees-$startDate-to-$endDate.csv",['Roll No','Name','Class','Absences'],$rows);
            break;
    }
}

require_once __DIR__ . '/../header.php';

// ---- Data queries ----

// Daily summary
$stmt = $db->prepare("SELECT sa.date,COUNT(DISTINCT sa.id) as total,SUM(sa.status='present') as present,SUM(sa.status='absent') as absent,SUM(sa.status='late') as late FROM student_attendance sa WHERE sa.date BETWEEN ? AND ? AND sa.period=1 GROUP BY sa.date ORDER BY sa.date DESC LIMIT 31");
$stmt->execute([$startDate,$endDate]);
$dailyData = $stmt->fetchAll();

// Class-wise summary (ordered 1-10)
$stmt = $db->prepare("SELECT s.class,COUNT(DISTINCT s.id) as students,SUM(CASE WHEN sa.status='present' AND sa.period=1 THEN 1 ELSE 0 END) as present,SUM(CASE WHEN sa.status='absent' AND sa.period=1 THEN 1 ELSE 0 END) as absent,SUM(CASE WHEN sa.status='late' AND sa.period=1 THEN 1 ELSE 0 END) as late FROM students s LEFT JOIN student_attendance sa ON s.id=sa.student_id AND sa.date BETWEEN ? AND ? WHERE s.is_active=1 GROUP BY s.class ORDER BY CAST(s.class AS UNSIGNED)");
$stmt->execute([$startDate,$endDate]);
$classwiseData = $stmt->fetchAll();

// Monthly per-student
$classWhere = $selectedClass ? " AND s.class=?" : "";
$params2    = $selectedClass ? [$startDate,$endDate,$selectedClass] : [$startDate,$endDate];
$stmt = $db->prepare("SELECT s.roll_number,s.name,s.class,COUNT(DISTINCT sa.date) as days,SUM(sa.status='present') as present,SUM(sa.status='absent') as absent,SUM(sa.status='late') as late FROM students s LEFT JOIN student_attendance sa ON s.id=sa.student_id AND sa.date BETWEEN ? AND ? AND sa.period=1 WHERE s.is_active=1$classWhere GROUP BY s.id ORDER BY CAST(s.class AS UNSIGNED),s.roll_number");
$stmt->execute($params2);
$monthlyData = $stmt->fetchAll();

// Frequent absentees
$stmt = $db->prepare("SELECT s.roll_number,s.name,s.class,COUNT(CASE WHEN sa.status='absent' THEN 1 END) as cnt FROM students s LEFT JOIN student_attendance sa ON s.id=sa.student_id AND sa.date BETWEEN ? AND ? WHERE s.is_active=1 GROUP BY s.id HAVING cnt>0 ORDER BY cnt DESC LIMIT 20");
$stmt->execute([$startDate,$endDate]);
$absentees = $stmt->fetchAll();

// Teacher report
$stmt = $db->prepare("SELECT u.name,u.employee_id,COUNT(ta.id) as days,SUM(ta.status='present') as present,SUM(ta.status='absent') as absent,SUM(ta.status='late') as late FROM users u LEFT JOIN teacher_attendance ta ON u.id=ta.teacher_id AND ta.date BETWEEN ? AND ? WHERE u.role='teacher' AND u.is_active=1 GROUP BY u.id ORDER BY u.name");
$stmt->execute([$startDate,$endDate]);
$teacherData = $stmt->fetchAll();
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="bar-chart-2"></i> Reports</h1>
        <p>Attendance analytics and exports</p>
    </div>
</div>

<div class="page-content">
    <!-- Filters -->
    <div class="card mb-6">
        <div class="card-body">
            <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
                <div class="form-group" style="min-width:180px">
                    <label>Report Type</label>
                    <select name="report" onchange="this.form.submit()">
                        <option value="daily"     <?= $reportType==='daily'    ?'selected':'' ?>>Daily Summary</option>
                        <option value="classwise" <?= $reportType==='classwise'?'selected':'' ?>>Class-wise Summary</option>
                        <option value="monthly"   <?= $reportType==='monthly'  ?'selected':'' ?>>Monthly (Per Student)</option>
                        <option value="teacher"   <?= $reportType==='teacher'  ?'selected':'' ?>>Teacher Attendance</option>
                        <option value="absentees" <?= $reportType==='absentees'?'selected':'' ?>>Frequent Absentees</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>From</label>
                    <input type="date" name="start_date" value="<?= $startDate ?>" onchange="this.form.submit()">
                </div>
                <div class="form-group">
                    <label>To</label>
                    <input type="date" name="end_date" value="<?= $endDate ?>" onchange="this.form.submit()">
                </div>
                <?php if (in_array($reportType,['monthly','classwise'])): ?>
                <div class="form-group" style="min-width:140px">
                    <label>Class</label>
                    <select name="class" onchange="this.form.submit()">
                        <option value="">All Classes</option>
                        <?php foreach($classes as $c): ?>
                        <option value="<?= $c['class'] ?>" <?= $selectedClass==$c['class']?'selected':'' ?>>Class <?= $c['class'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary"><i data-feather="filter"></i> Apply</button>
            </form>
        </div>
    </div>

    <?php if ($reportType === 'daily'): ?>
    <!-- Daily Summary -->
    <div class="card">
        <div class="card-header">
            <h3><i data-feather="calendar"></i> Daily Attendance Summary</h3>
            <a href="?report=daily&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&export=daily&export_date=<?= $endDate ?>" class="btn btn-sm btn-secondary"><i data-feather="download"></i> Export CSV</a>
        </div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table class="table">
                    <thead><tr><th>Date</th><th style="text-align:center">Total</th><th style="text-align:center">Present</th><th style="text-align:center">Absent</th><th style="text-align:center">Late</th><th style="text-align:center">Attendance %</th></tr></thead>
                    <tbody>
                    <?php foreach($dailyData as $r):
                        $pct = $r['total']>0 ? round($r['present']/$r['total']*100,1) : 0;
                    ?>
                    <tr>
                        <td><strong><?= date('D, d M Y',strtotime($r['date'])) ?></strong></td>
                        <td style="text-align:center"><?= $r['total'] ?></td>
                        <td style="text-align:center"><span class="badge badge-present"><?= $r['present'] ?></span></td>
                        <td style="text-align:center"><span class="badge badge-absent"><?= $r['absent'] ?></span></td>
                        <td style="text-align:center"><span class="badge badge-late"><?= $r['late'] ?></span></td>
                        <td style="text-align:center">
                            <div style="display:flex;align-items:center;gap:8px;justify-content:center">
                                <div style="width:60px;height:6px;background:#e2e8f0;border-radius:3px"><div style="width:<?= $pct ?>%;height:100%;background:<?= $pct>=75?'#10b981':'#ef4444' ?>;border-radius:3px"></div></div>
                                <strong style="color:<?= $pct>=75?'#059669':'#dc2626' ?>"><?= $pct ?>%</strong>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($dailyData)): ?><tr><td colspan="6" style="text-align:center;padding:30px;color:#6b7280">No data for selected period</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php elseif ($reportType === 'classwise'): ?>
    <!-- Class-wise Summary (Class 1 to 10) -->
    <div class="card">
        <div class="card-header">
            <h3><i data-feather="layers"></i> Class-wise Attendance Summary</h3>
            <a href="?report=classwise&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&export=classwise" class="btn btn-sm btn-secondary"><i data-feather="download"></i> Export CSV</a>
        </div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table class="table">
                    <thead><tr><th>Class</th><th style="text-align:center">Students</th><th style="text-align:center">Present</th><th style="text-align:center">Absent</th><th style="text-align:center">Late</th><th style="text-align:center">Attendance %</th><th style="text-align:center">Action</th></tr></thead>
                    <tbody>
                    <?php foreach($classwiseData as $r):
                        $total = $r['present'] + $r['absent'] + $r['late'];
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
                            <a href="class-performance.php?class=<?= urlencode($r['class']) ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="btn btn-sm btn-primary"><i data-feather="bar-chart-2"></i> Details</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($classwiseData)): ?><tr><td colspan="7" style="text-align:center;padding:30px;color:#6b7280">No data for selected period</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php elseif ($reportType === 'monthly'): ?>
    <!-- Monthly Per-Student -->
    <div class="card">
        <div class="card-header">
            <h3><i data-feather="users"></i> Monthly Student Attendance <?= $selectedClass ? '— Class '.$selectedClass : '(All Classes)' ?></h3>
            <a href="?report=monthly&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&class=<?= urlencode($selectedClass) ?>&export=monthly" class="btn btn-sm btn-secondary"><i data-feather="download"></i> Export CSV</a>
        </div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table class="table">
                    <thead><tr><th>Roll No</th><th>Name</th><th>Class</th><th style="text-align:center">Days</th><th style="text-align:center">Present</th><th style="text-align:center">Absent</th><th style="text-align:center">Late</th><th style="text-align:center">Attendance %</th></tr></thead>
                    <tbody>
                    <?php
                    $prevClass = null;
                    foreach($monthlyData as $r):
                        $pct = $r['days']>0 ? round($r['present']/$r['days']*100,1) : 0;
                        $bg  = $pct>=75?'#d1fae5':($pct>=50?'#fef3c7':'#fee2e2');
                        $fc  = $pct>=75?'#065f46':($pct>=50?'#92400e':'#7f1d1d');
                        if ($r['class'] !== $prevClass):
                            $prevClass = $r['class'];
                    ?>
                    <tr style="background:#f8fafc"><td colspan="8" style="padding:8px 14px;font-weight:700;color:#1e40af;font-size:13px;letter-spacing:.5px">CLASS <?= htmlspecialchars($r['class']) ?></td></tr>
                    <?php endif; ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r['roll_number']) ?></strong></td>
                        <td><?= htmlspecialchars($r['name']) ?></td>
                        <td><span class="badge" style="background:#dbeafe;color:#1e40af"><?= $r['class'] ?></span></td>
                        <td style="text-align:center"><?= $r['days'] ?></td>
                        <td style="text-align:center"><span class="badge badge-present"><?= $r['present'] ?></span></td>
                        <td style="text-align:center"><span class="badge badge-absent"><?= $r['absent'] ?></span></td>
                        <td style="text-align:center"><span class="badge badge-late"><?= $r['late'] ?></span></td>
                        <td style="text-align:center"><span class="badge" style="background:<?= $bg ?>;color:<?= $fc ?>"><?= $pct ?>%</span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($monthlyData)): ?><tr><td colspan="8" style="text-align:center;padding:30px;color:#6b7280">No data for selected period</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php elseif ($reportType === 'teacher'): ?>
    <!-- Teacher Attendance -->
    <div class="card">
        <div class="card-header">
            <h3><i data-feather="briefcase"></i> Teacher Attendance Report</h3>
        </div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table class="table">
                    <thead><tr><th>Name</th><th>Employee ID</th><th style="text-align:center">Days</th><th style="text-align:center">Present</th><th style="text-align:center">Absent</th><th style="text-align:center">Late</th><th style="text-align:center">Attendance %</th></tr></thead>
                    <tbody>
                    <?php foreach($teacherData as $r):
                        $pct = $r['days']>0 ? round($r['present']/$r['days']*100,1) : 0;
                        $bg  = $pct>=75?'#d1fae5':($pct>=50?'#fef3c7':'#fee2e2');
                        $fc  = $pct>=75?'#065f46':($pct>=50?'#92400e':'#7f1d1d');
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                        <td><?= htmlspecialchars($r['employee_id'] ?? '-') ?></td>
                        <td style="text-align:center"><?= $r['days'] ?></td>
                        <td style="text-align:center"><span class="badge badge-present"><?= $r['present'] ?></span></td>
                        <td style="text-align:center"><span class="badge badge-absent"><?= $r['absent'] ?></span></td>
                        <td style="text-align:center"><span class="badge badge-late"><?= $r['late'] ?></span></td>
                        <td style="text-align:center"><span class="badge" style="background:<?= $bg ?>;color:<?= $fc ?>"><?= $pct ?>%</span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($teacherData)): ?><tr><td colspan="7" style="text-align:center;padding:30px;color:#6b7280">No teacher data found</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php elseif ($reportType === 'absentees'): ?>
    <!-- Frequent Absentees -->
    <div class="card">
        <div class="card-header">
            <h3><i data-feather="alert-circle"></i> Frequent Absentees</h3>
            <a href="?report=absentees&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&export=absentees" class="btn btn-sm btn-secondary"><i data-feather="download"></i> Export CSV</a>
        </div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table class="table">
                    <thead><tr><th>Roll No</th><th>Name</th><th>Class</th><th style="text-align:center">Total Absences</th></tr></thead>
                    <tbody>
                    <?php foreach($absentees as $r): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r['roll_number']) ?></strong></td>
                        <td><?= htmlspecialchars($r['name']) ?></td>
                        <td><span class="badge" style="background:#dbeafe;color:#1e40af">Class <?= htmlspecialchars($r['class']) ?></span></td>
                        <td style="text-align:center"><span class="badge badge-absent"><?= $r['cnt'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($absentees)): ?><tr><td colspan="4" style="text-align:center;padding:30px;color:#6b7280">No absentees found for selected period</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
