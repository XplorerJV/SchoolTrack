<?php
$pageTitle = 'Class Folders';
require_once __DIR__ . '/../auth.php';
requireRole('admin', '../index.php');
require_once __DIR__ . '/../header.php';
require_once __DIR__ . '/../periods.php';

$db    = getDB();
$today = date('Y-m-d');

// Selected date from GET (default today)
$selDate = isset($_GET['date']) && $_GET['date'] ? $_GET['date'] : $today;

$classes = [];
for ($i = 1; $i <= 10; $i++) {
    $classes[$i] = ['class'=>(string)$i,'total'=>0,'present'=>0,'absent'=>0,'late'=>0,'marked'=>0];
}
foreach ($db->query("SELECT class,COUNT(*) as c FROM students WHERE is_active=1 GROUP BY class")->fetchAll() as $r) {
    $c=(int)$r['class'];
    if ($c>=1&&$c<=10) $classes[$c]['total']=(int)$r['c'];
}
$stmt = $db->prepare("SELECT s.class,sa.status,COUNT(*) as cnt FROM students s JOIN student_attendance sa ON s.id=sa.student_id WHERE sa.date=? AND sa.period=1 GROUP BY s.class,sa.status");
$stmt->execute([$selDate]);
foreach ($stmt->fetchAll() as $r) {
    $c=(int)$r['class'];
    if ($c<1||$c>10) continue;
    $classes[$c][$r['status']]=(int)$r['cnt'];
    $classes[$c]['marked']+=(int)$r['cnt'];
}
$periodStats = [];
$stmt = $db->prepare("SELECT s.class,sa.period,COUNT(*) as cnt FROM students s JOIN student_attendance sa ON s.id=sa.student_id WHERE sa.date=? GROUP BY s.class,sa.period");
$stmt->execute([$selDate]);
foreach ($stmt->fetchAll() as $r) {
    $periodStats[$r['class']][$r['period']]=(int)$r['cnt'];
}
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="folder"></i> Class Folders</h1>
        <p id="headerDate"><?= date('l, d M Y', strtotime($selDate)) ?> — 9 Periods (08:00–17:00)</p>
    </div>
    <!-- Global date picker -->
    <div style="display:flex;align-items:center;gap:10px">
        <label style="font-size:13px;font-weight:600;color:#64748b">Date:</label>
        <input type="date" id="globalDate" value="<?= $selDate ?>" max="<?= $today ?>"
               style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;cursor:pointer"
               onchange="window.location.href='?date='+this.value">
    </div>
</div>

<div class="page-content">
    <!-- Summary stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <?php
        $ts=array_sum(array_column($classes,'total'));
        $tp=array_sum(array_column($classes,'present'));
        $ta=array_sum(array_column($classes,'absent'));
        $tl=array_sum(array_column($classes,'late'));
        ?>
        <div class="stat-card"><div class="stat-icon" style="background:rgba(59,130,246,.1)"><i data-feather="users" style="color:#3b82f6"></i></div><div class="stat-content"><p class="stat-label">Total Students</p><p class="stat-value"><?= $ts ?></p></div></div>
        <div class="stat-card"><div class="stat-icon" style="background:rgba(16,185,129,.1)"><i data-feather="check-circle" style="color:#10b981"></i></div><div class="stat-content"><p class="stat-label">Present (P1)</p><p class="stat-value"><?= $tp ?></p></div></div>
        <div class="stat-card"><div class="stat-icon" style="background:rgba(239,68,68,.1)"><i data-feather="x-circle" style="color:#ef4444"></i></div><div class="stat-content"><p class="stat-label">Absent (P1)</p><p class="stat-value"><?= $ta ?></p></div></div>
        <div class="stat-card"><div class="stat-icon" style="background:rgba(245,158,11,.1)"><i data-feather="clock" style="color:#f59e0b"></i></div><div class="stat-content"><p class="stat-label">Late (P1)</p><p class="stat-value"><?= $tl ?></p></div></div>
    </div>

    <!-- Class Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px">
        <?php foreach ($classes as $c):
            $pct   = $c['total']>0 ? round($c['present']/$c['total']*100) : 0;
            $color = $pct>=75?'#10b981':($pct>=50?'#f59e0b':($c['marked']===0?'#94a3b8':'#ef4444'));
            $ps    = $periodStats[$c['class']] ?? [];
        ?>
        <div style="background:#fff;border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,.07);overflow:hidden;border:1px solid #f1f5f9">
            <div style="height:5px;background:<?= $color ?>"></div>
            <div style="padding:18px 20px">

                <!-- Class header -->
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
                    <div>
                        <div style="font-size:22px;font-weight:700;color:#0f172a">Class <?= $c['class'] ?></div>
                        <div style="font-size:12px;color:#64748b"><?= $c['total'] ?> students</div>
                    </div>
                    <div style="text-align:right">
                        <div style="font-size:22px;font-weight:700;color:<?= $color ?>"><?= $pct ?>%</div>
                        <div style="font-size:10px;color:#94a3b8">P1 present</div>
                    </div>
                </div>

                <!-- Progress bar -->
                <div style="height:5px;background:#e2e8f0;border-radius:3px;margin-bottom:12px">
                    <div style="width:<?= $pct ?>%;height:100%;background:<?= $color ?>;border-radius:3px"></div>
                </div>

                <!-- Stats badges -->
                <div style="display:flex;gap:5px;margin-bottom:16px;flex-wrap:wrap">
                    <span class="badge badge-present"><?= $c['present'] ?> Present</span>
                    <span class="badge badge-absent"><?= $c['absent'] ?> Absent</span>
                    <span class="badge badge-late"><?= $c['late'] ?> Late</span>
                    <span class="badge" style="background:#f1f5f9;color:#475569"><?= $c['marked'] ?>/<?= $c['total'] ?> Marked</span>
                </div>

                <!-- ── DATE + PERIOD SELECTOR TAB ── -->
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px;margin-bottom:14px">
                    <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.6px;margin-bottom:10px">
                        <i data-feather="calendar" style="width:12px;height:12px;display:inline;vertical-align:middle"></i>
                        Select Date &amp; Period
                    </div>

                    <!-- Date input -->
                    <div style="margin-bottom:10px">
                        <input type="date" id="date_<?= $c['class'] ?>" value="<?= $selDate ?>" max="<?= $today ?>"
                               style="width:100%;padding:7px 10px;border:1.5px solid #e2e8f0;border-radius:7px;font-size:13px;background:#fff">
                    </div>

                    <!-- Period buttons P1-P9 with breaks -->
                    <div style="display:flex;gap:4px;flex-wrap:wrap;align-items:center">
                        <?php foreach (PERIOD_TIMES as $p=>$pt):
                            $cnt  = $ps[$p] ?? 0;
                            $done = $c['total']>0 && $cnt>=$c['total'];
                            $part = $cnt>0 && !$done;
                            $bc   = $done?'#10b981':($part?'#f59e0b':'#cbd5e1');
                            $bg   = $done?'#f0fdf4':($part?'#fffbeb':'#fff');
                            $fc   = $done?'#065f46':($part?'#92400e':'#374151');
                        ?>
                        <?php if ($p===4): ?>
                        <div title="Break 11:00–11:30" style="padding:3px 5px;background:#fef3c7;border-radius:4px;font-size:9px;color:#92400e;font-weight:700;line-height:1.3;text-align:center">☕<br>BRK</div>
                        <?php endif; ?>
                        <?php if ($p===7): ?>
                        <div title="Break 14:30–15:00" style="padding:3px 5px;background:#fef3c7;border-radius:4px;font-size:9px;color:#92400e;font-weight:700;line-height:1.3;text-align:center">🍽️<br>BRK</div>
                        <?php endif; ?>
                        <button type="button"
                                onclick="goMark('<?= $c['class'] ?>',<?= $p ?>)"
                                title="<?= $pt['label'] ?> — <?= $pt['time'] ?>"
                                style="position:relative;padding:5px 8px;border-radius:6px;font-size:11px;font-weight:700;border:1.5px solid <?= $bc ?>;background:<?= $bg ?>;color:<?= $fc ?>;cursor:pointer;min-width:32px;line-height:1">
                            P<?= $p ?>
                            <?php if ($done): ?>
                            <span style="position:absolute;top:-5px;right:-5px;background:#10b981;color:#fff;border-radius:50%;width:13px;height:13px;font-size:8px;display:flex;align-items:center;justify-content:center">✓</span>
                            <?php elseif ($part): ?>
                            <span style="position:absolute;top:-5px;right:-5px;background:#f59e0b;color:#fff;border-radius:50%;width:13px;height:13px;font-size:8px;display:flex;align-items:center;justify-content:center"><?= $cnt ?></span>
                            <?php endif; ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <div style="font-size:10px;color:#94a3b8;margin-top:6px">🟢 Done &nbsp;🟡 Partial &nbsp;⬜ Not marked</div>
                </div>
                <!-- ── END DATE + PERIOD SELECTOR TAB ── -->

                <!-- Bottom actions -->
                <div style="display:flex;gap:8px">
                    <a href="class-folder.php?class=<?= urlencode($c['class']) ?>" class="btn btn-sm btn-primary" style="flex:1;justify-content:center">
                        <i data-feather="folder-open"></i> Open Folder
                    </a>
                    <a href="class-performance.php?class=<?= urlencode($c['class']) ?>" class="btn btn-sm btn-secondary" style="padding:6px 12px">
                        <i data-feather="bar-chart-2"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <p style="margin-top:24px;font-size:12px;color:#94a3b8;text-align:center">
        Showing data for <strong><?= date('d M Y', strtotime($selDate)) ?></strong> — updates automatically each day
    </p>
</div>

<script>
function goMark(cls, period) {
    const dateEl = document.getElementById('date_' + cls);
    const date   = dateEl ? dateEl.value : '<?= $selDate ?>';
    window.location.href = '../teacher/mark-attendance.php?class=' + encodeURIComponent(cls) + '&period=' + period + '&date=' + encodeURIComponent(date);
}
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
