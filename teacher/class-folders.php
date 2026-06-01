<?php
$pageTitle = 'Class Folders';
require_once __DIR__ . '/../auth.php';
requireRole('teacher', '../index.php');
require_once __DIR__ . '/../header.php';
require_once __DIR__ . '/../periods.php';

$db    = getDB();
$today = date('Y-m-d');

$classes = [];
for ($i = 1; $i <= 10; $i++) {
    $classes[$i] = ['class'=>(string)$i,'total'=>0,'present'=>0,'absent'=>0,'late'=>0,'marked'=>0];
}
foreach ($db->query("SELECT class,COUNT(*) as c FROM students WHERE is_active=1 GROUP BY class")->fetchAll() as $r) {
    $c = (int)$r['class'];
    if ($c>=1&&$c<=10) $classes[$c]['total']=(int)$r['c'];
}
$stmt = $db->prepare("SELECT s.class,sa.status,COUNT(*) as cnt FROM students s JOIN student_attendance sa ON s.id=sa.student_id WHERE sa.date=? AND sa.period=1 GROUP BY s.class,sa.status");
$stmt->execute([$today]);
foreach ($stmt->fetchAll() as $r) {
    $c=(int)$r['class'];
    if ($c<1||$c>10) continue;
    $classes[$c][$r['status']]=(int)$r['cnt'];
    $classes[$c]['marked']+=(int)$r['cnt'];
}

// Per-class period completion stats
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
        <p>Today: <?= date('l, d M Y') ?> — 9 Periods (08:00–17:00)</p>
    </div>
</div>

<div class="page-content">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <?php
        $ts = array_sum(array_column($classes,'total'));
        $tp = array_sum(array_column($classes,'present'));
        $ta = array_sum(array_column($classes,'absent'));
        $tl = array_sum(array_column($classes,'late'));
        ?>
        <div class="stat-card"><div class="stat-icon" style="background:rgba(59,130,246,.1)"><i data-feather="users" style="color:#3b82f6"></i></div><div class="stat-content"><p class="stat-label">Total Students</p><p class="stat-value"><?= $ts ?></p></div></div>
        <div class="stat-card"><div class="stat-icon" style="background:rgba(16,185,129,.1)"><i data-feather="check-circle" style="color:#10b981"></i></div><div class="stat-content"><p class="stat-label">Present Today</p><p class="stat-value"><?= $tp ?></p></div></div>
        <div class="stat-card"><div class="stat-icon" style="background:rgba(239,68,68,.1)"><i data-feather="x-circle" style="color:#ef4444"></i></div><div class="stat-content"><p class="stat-label">Absent Today</p><p class="stat-value"><?= $ta ?></p></div></div>
        <div class="stat-card"><div class="stat-icon" style="background:rgba(245,158,11,.1)"><i data-feather="clock" style="color:#f59e0b"></i></div><div class="stat-content"><p class="stat-label">Late Today</p><p class="stat-value"><?= $tl ?></p></div></div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px">
        <?php foreach ($classes as $c):
            $pct   = $c['total']>0 ? round($c['present']/$c['total']*100) : 0;
            $color = $pct>=75?'#10b981':($pct>=50?'#f59e0b':($c['marked']===0?'#94a3b8':'#ef4444'));
            $ps    = $periodStats[$c['class']] ?? [];
        ?>
        <div style="background:#fff;border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,.06);overflow:hidden;border:1px solid #f1f5f9">
            <div style="height:5px;background:<?= $color ?>"></div>
            <div style="padding:18px">
                <!-- Header -->
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
                    <div>
                        <div style="font-size:20px;font-weight:700;color:#0f172a">Class <?= $c['class'] ?></div>
                        <div style="font-size:12px;color:#64748b"><?= $c['total'] ?> students</div>
                    </div>
                    <div style="text-align:right">
                        <div style="font-size:22px;font-weight:700;color:<?= $color ?>"><?= $pct ?>%</div>
                        <div style="font-size:10px;color:#94a3b8">P1 present</div>
                    </div>
                </div>

                <!-- Progress -->
                <div style="height:5px;background:#e2e8f0;border-radius:3px;margin-bottom:12px">
                    <div style="width:<?= $pct ?>%;height:100%;background:<?= $color ?>;border-radius:3px"></div>
                </div>

                <!-- Stats -->
                <div style="display:flex;gap:5px;margin-bottom:14px;flex-wrap:wrap">
                    <span class="badge badge-present"><?= $c['present'] ?> P</span>
                    <span class="badge badge-absent"><?= $c['absent'] ?> A</span>
                    <span class="badge badge-late"><?= $c['late'] ?> L</span>
                    <span class="badge" style="background:#f1f5f9;color:#475569"><?= $c['marked'] ?>/<?= $c['total'] ?></span>
                </div>

                <!-- 9 Period buttons with breaks -->
                <div style="font-size:11px;color:#94a3b8;font-weight:600;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px">Mark by Period</div>
                <div style="display:flex;gap:4px;flex-wrap:wrap;align-items:center;margin-bottom:12px">
                    <?php foreach (PERIOD_TIMES as $p=>$pt):
                        $cnt  = $ps[$p] ?? 0;
                        $done = $c['total']>0 && $cnt>=$c['total'];
                        $part = $cnt>0 && !$done;
                        $bc   = $done?'#10b981':($part?'#f59e0b':'#e2e8f0');
                        $bg   = $done?'#f0fdf4':($part?'#fffbeb':'#f8fafc');
                        $fc   = $done?'#065f46':($part?'#92400e':'#475569');
                    ?>
                    <?php if ($p===4): ?><div style="width:1px;height:28px;background:#e2e8f0;margin:0 2px"></div><?php endif; ?>
                    <?php if ($p===7): ?><div style="width:1px;height:28px;background:#e2e8f0;margin:0 2px"></div><?php endif; ?>
                    <a href="mark-attendance.php?class=<?= urlencode($c['class']) ?>&period=<?= $p ?>"
                       title="<?= $pt['label'] ?> (<?= $pt['time'] ?>)"
                       style="position:relative;display:inline-flex;flex-direction:column;align-items:center;padding:5px 8px;border-radius:6px;text-decoration:none;font-size:11px;font-weight:700;border:1.5px solid <?= $bc ?>;background:<?= $bg ?>;color:<?= $fc ?>;min-width:34px;text-align:center">
                        P<?= $p ?>
                        <?php if ($done): ?>
                        <span style="position:absolute;top:-5px;right:-5px;background:#10b981;color:#fff;border-radius:50%;width:13px;height:13px;font-size:8px;display:flex;align-items:center;justify-content:center">✓</span>
                        <?php elseif ($part): ?>
                        <span style="position:absolute;top:-5px;right:-5px;background:#f59e0b;color:#fff;border-radius:50%;width:13px;height:13px;font-size:8px;display:flex;align-items:center;justify-content:center"><?= $cnt ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>

                <!-- Break legend -->
                <div style="font-size:10px;color:#94a3b8;margin-bottom:12px">
                    ☕ Break 11:00–11:30 &nbsp;|&nbsp; 🍽️ Break 14:30–15:00
                </div>

                <!-- Open folder -->
                <a href="../admin/class-folder.php?class=<?= urlencode($c['class']) ?>" class="btn btn-sm btn-secondary" style="width:100%;justify-content:center">
                    <i data-feather="folder-open"></i> Open Class Folder
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <p style="margin-top:20px;font-size:12px;color:#94a3b8;text-align:center">
        🟢 Fully marked &nbsp;|&nbsp; 🟡 Partially marked &nbsp;|&nbsp; ⬜ Not marked
    </p>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
