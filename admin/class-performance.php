<?php
$pageTitle = 'Class Performance';
require_once __DIR__ . '/../auth.php';
requireRole(['admin','principal'], '../index.php');
require_once __DIR__ . '/../header.php';

$db = getDB();
$selectedClass = $_GET['class'] ?? '';
$startDate     = $_GET['start_date'] ?? date('Y-m-01');
$endDate       = $_GET['end_date']   ?? date('Y-m-d');

if (empty($selectedClass)) {
    header('Location: classes.php'); exit;
}

// Class summary
$stmt = $db->prepare("SELECT COUNT(*) as total, COUNT(DISTINCT section) as sections FROM students WHERE class=? AND is_active=1");
$stmt->execute([$selectedClass]);
$classInfo = $stmt->fetch();

// Per-student performance
$stmt = $db->prepare("
    SELECT s.id, s.roll_number, s.name, s.section, s.gender,
        COUNT(DISTINCT sa.date) as total_days,
        SUM(CASE WHEN sa.status='present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN sa.status='absent'  THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN sa.status='late'    THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN sa.status='excused' THEN 1 ELSE 0 END) as excused
    FROM students s
    LEFT JOIN student_attendance sa ON s.id=sa.student_id AND sa.date BETWEEN ? AND ? AND sa.period=1
    WHERE s.class=? AND s.is_active=1
    GROUP BY s.id ORDER BY s.roll_number
");
$stmt->execute([$startDate, $endDate, $selectedClass]);
$students = $stmt->fetchAll();

// Class totals
$totalPresent = array_sum(array_column($students,'present'));
$totalAbsent  = array_sum(array_column($students,'absent'));
$totalLate    = array_sum(array_column($students,'late'));
$totalDays    = array_sum(array_column($students,'total_days'));
$classAvg     = $totalDays > 0 ? round(($totalPresent/$totalDays)*100,1) : 0;

// Daily breakdown
$stmt = $db->prepare("
    SELECT sa.date,
        SUM(CASE WHEN sa.status='present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN sa.status='absent'  THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN sa.status='late'    THEN 1 ELSE 0 END) as late
    FROM student_attendance sa
    JOIN students s ON sa.student_id=s.id
    WHERE s.class=? AND sa.date BETWEEN ? AND ? AND sa.period=1
    GROUP BY sa.date ORDER BY sa.date DESC LIMIT 10
");
$stmt->execute([$selectedClass, $startDate, $endDate]);
$dailyData = $stmt->fetchAll();

$classes = $db->query("SELECT DISTINCT class FROM students WHERE is_active=1 ORDER BY CAST(class AS UNSIGNED)")->fetchAll();
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="bar-chart-2"></i> Class <?= htmlspecialchars($selectedClass) ?> — Performance</h1>
        <p><?= date('d M Y', strtotime($startDate)) ?> to <?= date('d M Y', strtotime($endDate)) ?></p>
    </div>
    <a href="classes.php" class="btn btn-secondary"><i data-feather="arrow-left"></i> Back to Classes</a>
</div>

<div class="page-content">

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(59,130,246,.1)"><i data-feather="users" style="color:#3b82f6"></i></div>
            <div class="stat-content"><p class="stat-label">Total Students</p><p class="stat-value"><?= $classInfo['total'] ?></p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,.1)"><i data-feather="trending-up" style="color:#10b981"></i></div>
            <div class="stat-content"><p class="stat-label">Avg Attendance</p><p class="stat-value"><?= $classAvg ?>%</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(239,68,68,.1)"><i data-feather="x-circle" style="color:#ef4444"></i></div>
            <div class="stat-content"><p class="stat-label">Total Absences</p><p class="stat-value"><?= $totalAbsent ?></p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,.1)"><i data-feather="clock" style="color:#f59e0b"></i></div>
            <div class="stat-content"><p class="stat-label">Total Late</p><p class="stat-value"><?= $totalLate ?></p></div>
        </div>
    </div>

    <!-- Date Filter + Class Switcher -->
    <div class="card mb-6">
        <div class="card-body">
            <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
                <div class="form-group" style="min-width:140px">
                    <label>Class</label>
                    <select name="class">
                        <?php foreach($classes as $c): ?>
                        <option value="<?= $c['class'] ?>" <?= $c['class']==$selectedClass?'selected':'' ?>>Class <?= $c['class'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>From Date</label>
                    <input type="date" name="start_date" value="<?= $startDate ?>">
                </div>
                <div class="form-group">
                    <label>To Date</label>
                    <input type="date" name="end_date" value="<?= $endDate ?>">
                </div>
                <button type="submit" class="btn btn-primary"><i data-feather="filter"></i> Apply</button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Period Summary -->
        <div class="card">
            <div class="card-header"><h3><i data-feather="pie-chart"></i> Period Summary</h3></div>
            <div class="card-body">
                <?php
                $rows = [
                    ['label'=>'Total Records','val'=>$totalDays,'color'=>'#3b82f6'],
                    ['label'=>'Present','val'=>$totalPresent,'color'=>'#10b981'],
                    ['label'=>'Absent','val'=>$totalAbsent,'color'=>'#ef4444'],
                    ['label'=>'Late','val'=>$totalLate,'color'=>'#f59e0b'],
                ];
                foreach($rows as $r): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #f1f5f9">
                    <span style="color:#64748b;font-size:14px"><?= $r['label'] ?></span>
                    <strong style="color:<?= $r['color'] ?>;font-size:16px"><?= $r['val'] ?></strong>
                </div>
                <?php endforeach; ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0">
                    <span style="color:#64748b;font-size:14px">Class Average</span>
                    <strong style="color:<?= $classAvg>=75?'#10b981':'#ef4444' ?>;font-size:18px"><?= $classAvg ?>%</strong>
                </div>
            </div>
        </div>

        <!-- Daily Breakdown -->
        <div class="card">
            <div class="card-header"><h3><i data-feather="calendar"></i> Recent Daily Records</h3></div>
            <div class="card-body" style="padding:0">
                <?php if (empty($dailyData)): ?>
                <div style="padding:30px;text-align:center;color:#6b7280">No attendance data for selected period</div>
                <?php else: ?>
                <div class="table-wrapper">
                    <table class="table">
                        <thead><tr><th>Date</th><th>Present</th><th>Absent</th><th>Late</th></tr></thead>
                        <tbody>
                        <?php foreach($dailyData as $d): ?>
                        <tr>
                            <td><strong><?= date('d M Y', strtotime($d['date'])) ?></strong></td>
                            <td><span class="badge badge-present"><?= $d['present'] ?></span></td>
                            <td><span class="badge badge-absent"><?= $d['absent'] ?></span></td>
                            <td><span class="badge badge-late"><?= $d['late'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Student Performance Table -->
    <div class="card">
        <div class="card-header">
            <h3><i data-feather="users"></i> Individual Student Performance — Class <?= htmlspecialchars($selectedClass) ?></h3>
            <a href="?class=<?= urlencode($selectedClass) ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&export=csv" class="btn btn-sm btn-secondary"><i data-feather="download"></i> Export CSV</a>
        </div>
        <div class="card-body" style="padding:0">
            <?php
            // CSV export
            if (isset($_GET['export']) && $_GET['export']==='csv') {
                $headers = ['Roll No','Name','Section','Gender','Days Marked','Present','Absent','Late','Attendance %'];
                $rows = [];
                foreach($students as $s) {
                    $pct = $s['total_days']>0 ? round(($s['present']/$s['total_days'])*100,1) : 0;
                    $rows[] = [$s['roll_number'],$s['name'],$s['section']??'',$s['gender']??'',$s['total_days'],$s['present'],$s['absent'],$s['late'],$pct.'%'];
                }
                exportCSV("class{$selectedClass}_performance_{$startDate}.csv", $headers, $rows);
            }
            ?>
            <?php if (empty($students)): ?>
            <div style="padding:30px;text-align:center;color:#6b7280">No students found in this class.</div>
            <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Roll No</th>
                            <th>Name</th>
                            <th>Section</th>
                            <th>Gender</th>
                            <th style="text-align:center">Days</th>
                            <th style="text-align:center">Present</th>
                            <th style="text-align:center">Absent</th>
                            <th style="text-align:center">Late</th>
                            <th style="text-align:center">Attendance %</th>
                            <th style="text-align:center">Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($students as $s):
                        $pct = $s['total_days']>0 ? round(($s['present']/$s['total_days'])*100,1) : 0;
                        if ($pct >= 90)      { $grade='Excellent'; $gc='#065f46'; $gb='#d1fae5'; }
                        elseif ($pct >= 75)  { $grade='Good';      $gc='#92400e'; $gb='#fef3c7'; }
                        elseif ($pct >= 60)  { $grade='Average';   $gc='#1e40af'; $gb='#dbeafe'; }
                        else                 { $grade='Poor';      $gc='#7f1d1d'; $gb='#fee2e2'; }
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($s['roll_number']) ?></strong></td>
                        <td><?= htmlspecialchars($s['name']) ?></td>
                        <td><?= htmlspecialchars($s['section'] ?? '-') ?></td>
                        <td><?= ucfirst($s['gender'] ?? '-') ?></td>
                        <td style="text-align:center"><?= $s['total_days'] ?></td>
                        <td style="text-align:center"><span class="badge badge-present"><?= $s['present'] ?></span></td>
                        <td style="text-align:center"><span class="badge badge-absent"><?= $s['absent'] ?></span></td>
                        <td style="text-align:center"><span class="badge badge-late"><?= $s['late'] ?></span></td>
                        <td style="text-align:center">
                            <div style="display:flex;align-items:center;gap:8px;justify-content:center">
                                <div style="width:80px;height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden">
                                    <div style="width:<?= $pct ?>%;height:100%;background:<?= $pct>=75?'#10b981':'#ef4444' ?>;border-radius:4px"></div>
                                </div>
                                <strong style="color:<?= $pct>=75?'#059669':'#dc2626' ?>"><?= $pct ?>%</strong>
                            </div>
                        </td>
                        <td style="text-align:center"><span class="badge" style="background:<?= $gb ?>;color:<?= $gc ?>"><?= $grade ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
