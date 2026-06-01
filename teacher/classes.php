<?php
$pageTitle = 'Classes';
require_once __DIR__ . '/../auth.php';
requireRole('teacher', '../index.php');
require_once __DIR__ . '/../header.php';
require_once __DIR__ . '/../periods.php';

$db = getDB();
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
$stmt->execute([$today, $today, $today]);
$classes = $stmt->fetchAll();

// Monthly attendance % per class
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
$stmt->execute([$monthStart, $today]);
$monthlyMap = [];
foreach($stmt->fetchAll() as $r) $monthlyMap[$r['class']] = $r;

$totalStudents = array_sum(array_column($classes,'total_students'));
$totalPresent  = array_sum(array_column($classes,'present_today'));
$totalAbsent   = array_sum(array_column($classes,'absent_today'));
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="layers"></i> Classes</h1>
        <p>Select a class to mark period-wise attendance</p>
    </div>
</div>

<div class="page-content">
    <!-- Summary Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(59,130,246,.1)"><i data-feather="layers" style="color:#3b82f6"></i></div>
            <div class="stat-content"><p class="stat-label">Total Classes</p><p class="stat-value"><?= count($classes) ?></p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(139,92,246,.1)"><i data-feather="users" style="color:#8b5cf6"></i></div>
            <div class="stat-content"><p class="stat-label">Total Students</p><p class="stat-value"><?= $totalStudents ?></p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,.1)"><i data-feather="check-circle" style="color:#10b981"></i></div>
            <div class="stat-content"><p class="stat-label">Present Today</p><p class="stat-value"><?= $totalPresent ?></p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(239,68,68,.1)"><i data-feather="x-circle" style="color:#ef4444"></i></div>
            <div class="stat-content"><p class="stat-label">Absent Today</p><p class="stat-value"><?= $totalAbsent ?></p></div>
        </div>
    </div>

    <!-- Classes Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <?php foreach($classes as $cls):
            $m = $monthlyMap[$cls['class']] ?? ['total'=>0,'present'=>0];
            $monthPct = $m['total']>0 ? round(($m['present']/$m['total'])*100) : 0;
            $markedPct = $cls['total_students']>0 ? round(($cls['marked_today']/$cls['total_students'])*100) : 0;
            $color = $monthPct>=75 ? '#10b981' : ($monthPct>=50 ? '#f59e0b' : '#ef4444');
        ?>
        <div class="card" style="margin-bottom:0">
            <div class="card-body">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px">
                    <div>
                        <h2 style="margin:0;font-size:24px;font-weight:700;color:#0f172a">Class <?= htmlspecialchars($cls['class']) ?></h2>
                        <p style="margin:4px 0 0;color:#64748b;font-size:13px"><?= $cls['total_students'] ?> students</p>
                    </div>
                    <div style="text-align:right">
                        <div style="font-size:22px;font-weight:700;color:<?= $color ?>"><?= $monthPct ?>%</div>
                        <div style="font-size:11px;color:#94a3b8">This month</div>
                    </div>
                </div>

                <!-- Progress bar -->
                <div style="height:6px;background:#e2e8f0;border-radius:3px;margin-bottom:16px">
                    <div style="width:<?= $monthPct ?>%;height:100%;background:<?= $color ?>;border-radius:3px;transition:width .3s"></div>
                </div>

                <!-- Today stats -->
                <div style="display:flex;gap:8px;margin-bottom:16px">
                    <span class="badge badge-present"><?= $cls['present_today'] ?> Present</span>
                    <span class="badge badge-absent"><?= $cls['absent_today'] ?> Absent</span>
                    <span class="badge" style="background:#f1f5f9;color:#475569"><?= $cls['marked_today'] ?>/<?= $cls['total_students'] ?> Marked</span>
                </div>

                <!-- Action buttons -->
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <?php foreach(PERIOD_TIMES as $p=>$pt): ?>
                    <?php if($p===4): ?><div style="width:3px"></div><?php endif; ?>
                    <?php if($p===7): ?><div style="width:3px"></div><?php endif; ?>
                    <a href="mark-attendance.php?class=<?= urlencode($cls['class']) ?>&period=<?= $p ?>"
                       class="btn btn-sm <?= $p==1?'btn-primary':'btn-secondary' ?>"
                       style="font-size:12px;padding:6px 10px" title="<?= $pt['time'] ?>">
                        P<?= $p ?>
                    </a>
                    <?php endforeach; ?>
                    <a href="../admin/class-performance.php?class=<?= urlencode($cls['class']) ?>" class="btn btn-sm btn-secondary" style="font-size:12px;padding:6px 10px;margin-left:auto">
                        <i data-feather="bar-chart-2" style="width:14px;height:14px"></i> Performance
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
