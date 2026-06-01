<?php
$pageTitle = 'Class Folders';
require_once __DIR__ . '/../auth.php';
requireRole('admin', '../index.php');
require_once __DIR__ . '/../header.php';
require_once __DIR__ . '/../periods.php';

$db    = getDB();
$today = date('Y-m-d');

// Build class array 1-10
$classes = [];
for ($i = 1; $i <= 10; $i++) {
    $classes[$i] = ['class'=>(string)$i, 'total'=>0, 'present'=>0, 'absent'=>0, 'late'=>0, 'marked'=>0];
}

// Total students per class
foreach ($db->query("SELECT class, COUNT(*) as c FROM students WHERE is_active=1 GROUP BY class")->fetchAll() as $r) {
    $c = (int)$r['class'];
    if ($c >= 1 && $c <= 10) $classes[$c]['total'] = (int)$r['c'];
}

// Today's attendance counts (period 1) — always uses current date
$stmt = $db->prepare("SELECT s.class, sa.status, COUNT(*) as cnt FROM students s JOIN student_attendance sa ON s.id=sa.student_id WHERE sa.date=? AND sa.period=1 GROUP BY s.class, sa.status");
$stmt->execute([$today]);
foreach ($stmt->fetchAll() as $r) {
    $c = (int)$r['class'];
    if ($c < 1 || $c > 10) continue;
    $classes[$c][$r['status']] = (int)$r['cnt'];
    $classes[$c]['marked'] += (int)$r['cnt'];
}

// Per-class per-period stats
$periodStats = [];
$stmt = $db->prepare("SELECT s.class,sa.period,COUNT(*) as cnt FROM students s JOIN student_attendance sa ON s.id=sa.student_id WHERE sa.date=? GROUP BY s.class,sa.period");
$stmt->execute([$today]);
foreach ($stmt->fetchAll() as $r) {
    $periodStats[$r['class']][$r['period']] = (int)$r['cnt'];
}
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="folder"></i> Class Folders</h1>
        <p>Today: <?= date('l, d M Y') ?> — Data refreshes automatically each day</p>
    </div>
</div>

<div class="page-content">
    <!-- Summary bar -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <?php
        $totalStudents = array_sum(array_column($classes, 'total'));
        $totalPresent  = array_sum(array_column($classes, 'present'));
        $totalAbsent   = array_sum(array_column($classes, 'absent'));
        $totalLate     = array_sum(array_column($classes, 'late'));
        ?>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(59,130,246,.1)"><i data-feather="users" style="color:#3b82f6"></i></div>
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
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,.1)"><i data-feather="clock" style="color:#f59e0b"></i></div>
            <div class="stat-content"><p class="stat-label">Late Today</p><p class="stat-value"><?= $totalLate ?></p></div>
        </div>
    </div>

    <!-- Class Folder Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px">
        <?php foreach ($classes as $c):
            $pct   = $c['total'] > 0 ? round($c['present'] / $c['total'] * 100) : 0;
            $color = $pct >= 75 ? '#10b981' : ($pct >= 50 ? '#f59e0b' : ($c['marked'] === 0 ? '#94a3b8' : '#ef4444'));
            $markedPct = $c['total'] > 0 ? round($c['marked'] / $c['total'] * 100) : 0;
        ?>
        <div style="background:#fff;border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,.06);overflow:hidden;border:1px solid #f1f5f9;transition:box-shadow .2s" onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,.1)'" onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,.06)'">

            <!-- Folder top strip -->
            <div style="height:6px;background:<?= $color ?>"></div>

            <div style="padding:20px">
                <!-- Class title + attendance % -->
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px">
                    <div>
                        <div style="font-size:22px;font-weight:700;color:#0f172a">Class <?= $c['class'] ?></div>
                        <div style="font-size:13px;color:#64748b;margin-top:2px"><?= $c['total'] ?> students</div>
                    </div>
                    <div style="text-align:right">
                        <div style="font-size:24px;font-weight:700;color:<?= $color ?>"><?= $pct ?>%</div>
                        <div style="font-size:11px;color:#94a3b8">present</div>
                    </div>
                </div>

                <!-- Progress bar -->
                <div style="height:6px;background:#e2e8f0;border-radius:3px;margin-bottom:14px">
                    <div style="width:<?= $pct ?>%;height:100%;background:<?= $color ?>;border-radius:3px"></div>
                </div>

                <!-- Stats row -->
                <div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap">
                    <span class="badge badge-present"><?= $c['present'] ?> Present</span>
                    <span class="badge badge-absent"><?= $c['absent'] ?> Absent</span>
                    <span class="badge badge-late"><?= $c['late'] ?> Late</span>
                    <span class="badge" style="background:#f1f5f9;color:#475569"><?= $c['marked'] ?>/<?= $c['total'] ?> Marked</span>
                </div>

                <?php if ($c['marked'] === 0): ?>
                <div style="font-size:12px;color:#f59e0b;margin-bottom:12px;display:flex;align-items:center;gap:4px">
                    <i data-feather="alert-circle" style="width:14px;height:14px"></i> Attendance not marked today
                </div>
                <?php endif; ?>

                <!-- 9 Period buttons with breaks -->
                <div style="margin-bottom:10px">
                    <div style="font-size:11px;color:#94a3b8;font-weight:600;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px">Mark by Period</div>
                    <div style="display:flex;gap:4px;flex-wrap:wrap;align-items:center">
                        <?php foreach(PERIOD_TIMES as $p=>$pt):
                            $cnt  = $periodStats[$c['class']][$p] ?? 0;
                            $done = $c['total']>0 && $cnt>=$c['total'];
                            $part = $cnt>0 && !$done;
                            $bc   = $done?'#10b981':($part?'#f59e0b':'#e2e8f0');
                            $bg   = $done?'#f0fdf4':($part?'#fffbeb':'#f8fafc');
                            $fc   = $done?'#065f46':($part?'#92400e':'#475569');
                        ?>
                        <?php if($p===4||$p===7): ?><div style="width:1px;height:24px;background:#e2e8f0;margin:0 1px"></div><?php endif; ?>
                        <a href="../teacher/mark-attendance.php?class=<?= urlencode($c['class']) ?>&period=<?= $p ?>"
                           title="<?= $pt['time'] ?>" style="position:relative;display:inline-flex;align-items:center;justify-content:center;padding:4px 7px;border-radius:5px;text-decoration:none;font-size:11px;font-weight:700;border:1.5px solid <?= $bc ?>;background:<?= $bg ?>;color:<?= $fc ?>;min-width:30px">
                            P<?= $p ?>
                            <?php if($done): ?><span style="position:absolute;top:-5px;right:-5px;background:#10b981;color:#fff;border-radius:50%;width:12px;height:12px;font-size:8px;display:flex;align-items:center;justify-content:center">✓</span><?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <div style="font-size:10px;color:#94a3b8;margin-top:5px">☕ 11:00–11:30 &nbsp;|&nbsp; 🍽️ 14:30–15:00</div>
                </div>
                <!-- Action buttons -->
                <div style="display:flex;gap:8px">
                    <a href="class-folder.php?class=<?= urlencode($c['class']) ?>" class="btn btn-sm btn-primary" style="flex:1;justify-content:center">
                        <i data-feather="folder-open"></i> Open
                    </a>
                    <a href="class-performance.php?class=<?= urlencode($c['class']) ?>" class="btn btn-sm btn-secondary" style="padding:6px 10px">
                        <i data-feather="bar-chart-2"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <p style="margin-top:20px;font-size:12px;color:#94a3b8;text-align:center">
        <i data-feather="info" style="width:13px;height:13px;display:inline"></i>
        Data shown is for today (<?= date('d M Y') ?>). Automatically updates each day at page load.
    </p>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
