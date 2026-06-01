<?php
$pageTitle = 'Classes - Monitor Attendance';
require_once __DIR__ . '/../auth.php';
requireRole('principal', '../index.php');
require_once __DIR__ . '/../header.php';

$db    = getDB();
$today = date('Y-m-d');

$stmt = $db->prepare("
    SELECT
        s.class,
        COUNT(DISTINCT s.id) as total_students,
        COUNT(DISTINCT CASE WHEN sa.date=? AND sa.status='present' AND sa.period=1 THEN s.id END) as present_today,
        COUNT(DISTINCT CASE WHEN sa.date=? AND sa.status='absent'  AND sa.period=1 THEN s.id END) as absent_today,
        COUNT(DISTINCT CASE WHEN sa.date=? AND sa.period=1 THEN s.id END) as marked_today
    FROM students s
    LEFT JOIN student_attendance sa ON s.id=sa.student_id
    WHERE s.is_active=1
    GROUP BY s.class
    ORDER BY CAST(s.class AS UNSIGNED)
");
$stmt->execute([$today,$today,$today]);
$classes = $stmt->fetchAll();

$monthStart = date('Y-m-01');
$stmt = $db->prepare("
    SELECT s.class,
        COUNT(sa.id) as total,
        SUM(CASE WHEN sa.status='present' THEN 1 ELSE 0 END) as present
    FROM students s
    LEFT JOIN student_attendance sa ON s.id=sa.student_id AND sa.date BETWEEN ? AND ? AND sa.period=1
    WHERE s.is_active=1
    GROUP BY s.class
");
$stmt->execute([$monthStart,$today]);
$monthlyMap = [];
foreach($stmt->fetchAll() as $r) $monthlyMap[$r['class']] = $r;
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="layers"></i> Classes — Monitor Attendance</h1>
        <p>School-wide class attendance overview</p>
    </div>
</div>

<div class="page-content">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(59,130,246,.1)"><i data-feather="layers" style="color:#3b82f6"></i></div>
            <div class="stat-content"><p class="stat-label">Classes</p><p class="stat-value"><?= count($classes) ?></p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(139,92,246,.1)"><i data-feather="users" style="color:#8b5cf6"></i></div>
            <div class="stat-content"><p class="stat-label">Total Students</p><p class="stat-value"><?= array_sum(array_column($classes,'total_students')) ?></p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,.1)"><i data-feather="check-circle" style="color:#10b981"></i></div>
            <div class="stat-content"><p class="stat-label">Present Today</p><p class="stat-value"><?= array_sum(array_column($classes,'present_today')) ?></p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(239,68,68,.1)"><i data-feather="x-circle" style="color:#ef4444"></i></div>
            <div class="stat-content"><p class="stat-label">Absent Today</p><p class="stat-value"><?= array_sum(array_column($classes,'absent_today')) ?></p></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3><i data-feather="layers"></i> All Classes</h3></div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th style="text-align:center">Students</th>
                            <th style="text-align:center">Present Today</th>
                            <th style="text-align:center">Absent Today</th>
                            <th style="text-align:center">Monthly %</th>
                            <th style="text-align:center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($classes as $cls):
                        $m = $monthlyMap[$cls['class']] ?? ['total'=>0,'present'=>0];
                        $pct = $m['total']>0 ? round(($m['present']/$m['total'])*100) : 0;
                        $color = $pct>=75?'#10b981':($pct>=50?'#f59e0b':'#ef4444');
                    ?>
                    <tr>
                        <td><strong style="font-size:15px">Class <?= htmlspecialchars($cls['class']) ?></strong></td>
                        <td style="text-align:center"><span class="badge" style="background:#dbeafe;color:#1e40af"><?= $cls['total_students'] ?></span></td>
                        <td style="text-align:center"><span class="badge badge-present"><?= $cls['present_today'] ?></span></td>
                        <td style="text-align:center"><span class="badge badge-absent"><?= $cls['absent_today'] ?></span></td>
                        <td style="text-align:center">
                            <div style="display:flex;align-items:center;gap:8px;justify-content:center">
                                <div style="width:60px;height:6px;background:#e2e8f0;border-radius:3px">
                                    <div style="width:<?= $pct ?>%;height:100%;background:<?= $color ?>;border-radius:3px"></div>
                                </div>
                                <strong style="color:<?= $color ?>"><?= $pct ?>%</strong>
                            </div>
                        </td>
                        <td style="text-align:center">
                            <div class="action-buttons" style="justify-content:center">
                                <a href="attendance.php?class=<?= urlencode($cls['class']) ?>" class="btn btn-sm btn-secondary">
                                    <i data-feather="eye"></i> View
                                </a>
                                <a href="../admin/class-performance.php?class=<?= urlencode($cls['class']) ?>" class="btn btn-sm btn-primary">
                                    <i data-feather="bar-chart-2"></i> Performance
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
