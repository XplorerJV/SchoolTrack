<?php
$pageTitle = 'Class Folder';
require_once __DIR__ . '/../auth.php';
requireRole('admin', '../index.php');
require_once __DIR__ . '/../header.php';

$db = getDB();
$selectedClass = $_GET['class'] ?? '';
if ($selectedClass === '') { header('Location: class-folders.php'); exit; }

$attendanceDate = $_GET['date'] ?? date('Y-m-d');
$selectedPeriod = (int)($_GET['period'] ?? 1);

// Period times mapping
$periodTimes = [
    1 => '08:00-09:00',
    2 => '09:00-10:00',
    3 => '10:00-11:00',
    4 => '11:30-12:30',
    5 => '12:30-13:30',
    6 => '13:30-14:30',
    7 => '14:45-15:45',
    8 => '15:45-16:45',
    9 => '16:45-17:45',
];
if (!isset($periodTimes[$selectedPeriod])) $selectedPeriod = 1;

// Fetch students with full details
$stmt = $db->prepare("SELECT id, name, roll_number, section FROM students WHERE class=? AND is_active=1 ORDER BY roll_number");
$stmt->execute([$selectedClass]);
$students = $stmt->fetchAll();

// Fetch attendance for selected date and period
$attendanceMap = [];
if (!empty($students)) {
    $studentIds = array_column($students, 'id');
    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
    $stmt = $db->prepare("SELECT student_id, status, time_in, notes FROM student_attendance WHERE student_id IN ($placeholders) AND date = ? AND period = ?");
    $params = array_merge($studentIds, [$attendanceDate, $selectedPeriod]);
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $record) {
        $attendanceMap[$record['student_id']] = $record;
    }
}

// View single student profile
$viewStudent = null;
if (isset($_GET['view'])) {
    $stmt = $db->prepare("SELECT * FROM students WHERE id=? AND class=?");
    $stmt->execute([(int)$_GET['view'], $selectedClass]);
    $viewStudent = $stmt->fetch();
    if ($viewStudent) {
        // Attendance stats last 30 days period 1
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT date) as days,
                SUM(status='present') as present,
                SUM(status='absent')  as absent,
                SUM(status='late')    as late
            FROM student_attendance
            WHERE student_id=? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND period=1
        ");
        $stmt->execute([$viewStudent['id']]);
        $att = $stmt->fetch();
        $att['pct'] = $att['days'] > 0 ? round($att['present'] / $att['days'] * 100, 1) : 0;

        // Recent 10 records
        $stmt = $db->prepare("SELECT date, status, period, time_in FROM student_attendance WHERE student_id=? AND period=1 ORDER BY date DESC LIMIT 10");
        $stmt->execute([$viewStudent['id']]);
        $recent = $stmt->fetchAll();
    }
}
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="folder"></i> Class <?= htmlspecialchars($selectedClass) ?></h1>
        <p><?= count($students) ?> students | Date: <?= htmlspecialchars($attendanceDate) ?> | Period: <?= htmlspecialchars($selectedPeriod . ' (' . $periodTimes[$selectedPeriod] . ')') ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="class-folders.php" class="btn btn-secondary"><i data-feather="arrow-left"></i> Back</a>
        <a href="class-attendance.php?class=<?= urlencode($selectedClass) ?>&date=<?= urlencode($attendanceDate) ?>&period=<?= $selectedPeriod ?>" class="btn btn-info"><i data-feather="check-square"></i> Mark Now</a>
        <a href="class-performance.php?class=<?= urlencode($selectedClass) ?>" class="btn btn-secondary"><i data-feather="bar-chart-2"></i> Performance</a>
    </div>
</div>

<div class="page-content">
    <!-- Date and Period Selection -->
    <div class="card" style="margin-bottom:20px">
        <div class="card-body">
            <form method="get" style="display:flex;gap:15px;align-items:center;flex-wrap:wrap">
                <input type="hidden" name="class" value="<?= htmlspecialchars($selectedClass) ?>">
                <div style="display:flex;gap:10px;align-items:center">
                    <label style="font-weight:600;margin:0">Select Date:</label>
                    <input type="date" name="date" value="<?= htmlspecialchars($attendanceDate) ?>" class="form-control" style="width:150px">
                </div>
                <div style="display:flex;gap:10px;align-items:center">
                    <label style="font-weight:600;margin:0">Period:</label>
                    <select name="period" class="form-control" style="width:200px">
                        <?php foreach ($periodTimes as $p => $range): ?>
                            <option value="<?= $p ?>" <?= ($p == $selectedPeriod) ? 'selected' : '' ?>>Period <?= $p ?> (<?= $range ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><i data-feather="search"></i> Load</button>
            </form>
        </div>
    </div>

    <!-- Quick Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));gap:12px;margin-bottom:20px">
        <?php
        $present = 0;
        $absent = 0;
        $late = 0;
        $unmarked = 0;
        foreach ($students as $s) {
            if (!isset($attendanceMap[$s['id']])) {
                $unmarked++;
            } else {
                $status = $attendanceMap[$s['id']]['status'];
                if ($status === 'present') $present++;
                elseif ($status === 'absent') $absent++;
                elseif ($status === 'late') $late++;
            }
        }
        ?>
        <div style="background:#ecfdf5;padding:12px;border-radius:6px;text-align:center">
            <div style="font-size:24px;font-weight:bold;color:#059669"><?= $present ?></div>
            <div style="font-size:12px;color:#047857">Present</div>
        </div>
        <div style="background:#fee2e2;padding:12px;border-radius:6px;text-align:center">
            <div style="font-size:24px;font-weight:bold;color:#dc2626"><?= $absent ?></div>
            <div style="font-size:12px;color:#b91c1c">Absent</div>
        </div>
        <div style="background:#fef3c7;padding:12px;border-radius:6px;text-align:center">
            <div style="font-size:24px;font-weight:bold;color:#d97706"><?= $late ?></div>
            <div style="font-size:12px;color:#b45309">Late</div>
        </div>
        <div style="background:#f3f4f6;padding:12px;border-radius:6px;text-align:center">
            <div style="font-size:24px;font-weight:bold;color:#6b7280"><?= $unmarked ?></div>
            <div style="font-size:12px;color:#4b5563">Unmarked</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i data-feather="users"></i> Students — Class <?= htmlspecialchars($selectedClass) ?></h3>
        </div>
        <div class="card-body" style="padding:0">
            <?php if (empty($students)): ?>
            <div style="padding:40px;text-align:center;color:#6b7280">No students found in this class.</div>
            <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Roll No</th>
                            <th>Name</th>
                            <th>Section</th>
                            <th style="text-align:center">Attendance</th>
                            <th style="text-align:center">Time In</th>
                            <th style="text-align:center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($students as $s):
                        $att = $attendanceMap[$s['id']] ?? null;
                        $status = $att ? $att['status'] : 'unmarked';
                        $statusColor = [
                            'present' => '#ecfdf5',
                            'absent' => '#fee2e2',
                            'late' => '#fef3c7',
                            'excused' => '#dbeafe',
                            'unmarked' => '#f3f4f6'
                        ];
                        $statusTextColor = [
                            'present' => '#059669',
                            'absent' => '#dc2626',
                            'late' => '#d97706',
                            'excused' => '#0369a1',
                            'unmarked' => '#6b7280'
                        ];
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($s['roll_number']) ?></strong></td>
                        <td><?= htmlspecialchars($s['name']) ?></td>
                        <td><span class="badge" style="background:#f1f5f9;color:#475569"><?= htmlspecialchars($s['section'] ?? '-') ?></span></td>
                        <td style="text-align:center">
                            <span style="display:inline-block;padding:6px 12px;background:<?= $statusColor[$status] ?>;color:<?= $statusTextColor[$status] ?>;border-radius:4px;font-size:13px;font-weight:600">
                                <?= ucfirst($status) ?>
                            </span>
                        </td>
                        <td style="text-align:center;font-size:13px"><?= $att && $att['time_in'] ? $att['time_in'] : '-' ?></td>
                        <td style="text-align:center">
                            <div style="display:flex;gap:6px;justify-content:center">
                                <a href="class-attendance.php?class=<?= urlencode($selectedClass) ?>&date=<?= urlencode($attendanceDate) ?>&period=<?= $selectedPeriod ?>" class="btn btn-sm btn-info" title="Mark Attendance"><i data-feather="edit-2"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Period selection bar for this class
require_once __DIR__ . '/../periods.php';
$periodTimes = PERIOD_TIMES;
$today = date('Y-m-d');
$pStats = [];
for ($p=1;$p<=9;$p++) {
    $st = $db->prepare("SELECT COUNT(*) FROM student_attendance sa JOIN students s ON sa.student_id=s.id WHERE s.class=? AND sa.date=? AND sa.period=?");
    $st->execute([$selectedClass,$today,$p]);
    $pStats[$p] = (int)$st->fetchColumn();
}
$totalStudents = count($students);
?>

<!-- Period Selection Bar -->
<div class="card mb-6">
    <div class="card-header">
        <h3><i data-feather="clock"></i> Mark Attendance by Period — Today (<?= date('d M Y') ?>)</h3>
    </div>
    <div class="card-body" style="padding:16px">
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <?php foreach($periodTimes as $p=>$pt):
                $marked  = $pStats[$p];
                $done    = $totalStudents > 0 && $marked >= $totalStudents;
                $partial = $marked > 0 && !$done;
            ?>
            <?php if ($p === 4): ?>
            <div style="padding:8px 10px;background:#fef3c7;border-radius:8px;font-size:11px;color:#92400e;font-weight:600;text-align:center;line-height:1.4">
                ☕ Break<br>11:00–11:30
            </div>
            <?php endif; ?>
            <?php if ($p === 7): ?>
            <div style="padding:8px 10px;background:#fef3c7;border-radius:8px;font-size:11px;color:#92400e;font-weight:600;text-align:center;line-height:1.4">
                🍽️ Break<br>14:30–15:00
            </div>
            <?php endif; ?>
            <a href="../teacher/mark-attendance.php?class=<?= urlencode($selectedClass) ?>&period=<?= $p ?>"
               style="position:relative;display:inline-flex;flex-direction:column;align-items:center;padding:8px 14px;border-radius:8px;text-decoration:none;font-size:12px;font-weight:600;min-width:72px;text-align:center;border:2px solid <?= $done?'#10b981':($partial?'#f59e0b':'#e2e8f0') ?>;background:<?= $done?'#f0fdf4':($partial?'#fffbeb':'#f8fafc') ?>;color:<?= $done?'#065f46':($partial?'#92400e':'#475569') ?>;transition:all .15s"
               onmouseover="this.style.borderColor='#1e40af';this.style.color='#1e40af'"
               onmouseout="this.style.borderColor='<?= $done?'#10b981':($partial?'#f59e0b':'#e2e8f0') ?>'";this.style.color='<?= $done?'#065f46':($partial?'#92400e':'#475569') ?>'">
                P<?= $p ?>
                <span style="font-size:10px;font-weight:400;margin-top:2px;color:#94a3b8"><?= $pt['time'] ?></span>
                <?php if ($done): ?>
                <span style="position:absolute;top:-6px;right:-6px;background:#10b981;color:#fff;border-radius:50%;width:16px;height:16px;font-size:9px;display:flex;align-items:center;justify-content:center">✓</span>
                <?php elseif ($partial): ?>
                <span style="position:absolute;top:-6px;right:-6px;background:#f59e0b;color:#fff;border-radius:50%;width:16px;height:16px;font-size:9px;display:flex;align-items:center;justify-content:center"><?= $marked ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <div style="margin-top:10px;font-size:12px;color:#94a3b8">
            🟢 Fully marked &nbsp; 🟡 Partially marked &nbsp; ⬜ Not marked
        </div>
    </div>
</div>

<?php if ($viewStudent): ?>
<!-- Student Profile Modal -->
<div style="position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1000;display:flex;align-items:center;justify-content:center;padding:20px">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:580px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3)">

        <!-- Header -->
        <div style="padding:24px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;background:linear-gradient(135deg,#1e40af,#3b82f6);border-radius:16px 16px 0 0">
            <div>
                <h3 style="margin:0;color:#fff;font-size:20px"><?= htmlspecialchars($viewStudent['name']) ?></h3>
                <p style="margin:4px 0 0;color:rgba(255,255,255,.7);font-size:13px">Roll: <?= htmlspecialchars($viewStudent['roll_number']) ?> &nbsp;|&nbsp; Class <?= htmlspecialchars($viewStudent['class']) ?> - <?= htmlspecialchars($viewStudent['section'] ?? '') ?></p>
            </div>
            <a href="?class=<?= urlencode($selectedClass) ?>" style="color:rgba(255,255,255,.7);text-decoration:none;font-size:24px;line-height:1">&times;</a>
        </div>

        <div style="padding:24px">
            <!-- Details Grid -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px">
                <?php
                $details = [
                    'Gender'       => ucfirst($viewStudent['gender'] ?? '-'),
                    'Date of Birth'=> $viewStudent['date_of_birth'] ? date('d M Y', strtotime($viewStudent['date_of_birth'])) : '-',
                    'Contact'      => $viewStudent['contact'] ?? '-',
                    'Card UID'     => $viewStudent['card_uid'] ?? '-',
                    'Parent Phone' => $viewStudent['parent_phone'] ?? '-',
                    'Parent Email' => $viewStudent['parent_email'] ?? '-',
                ];
                foreach ($details as $label => $val): ?>
                <div style="background:#f8fafc;padding:12px;border-radius:8px">
                    <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px"><?= $label ?></div>
                    <div style="font-size:14px;font-weight:600;color:#1e293b"><?= htmlspecialchars($val) ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($viewStudent['address']): ?>
            <div style="background:#f8fafc;padding:12px;border-radius:8px;margin-bottom:20px">
                <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px">Address</div>
                <div style="font-size:14px;color:#1e293b"><?= htmlspecialchars($viewStudent['address']) ?></div>
            </div>
            <?php endif; ?>

            <!-- Attendance Stats -->
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:16px;margin-bottom:20px">
                <div style="font-size:13px;font-weight:600;color:#065f46;margin-bottom:12px">Attendance — Last 30 Days (Period 1)</div>
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <?php foreach ([['Days',$att['days'],'#3b82f6'],['Present',$att['present'],'#10b981'],['Absent',$att['absent'],'#ef4444'],['Late',$att['late'],'#f59e0b']] as $si): ?>
                    <div style="text-align:center;flex:1;min-width:55px;background:#fff;padding:10px;border-radius:8px">
                        <div style="font-size:22px;font-weight:700;color:<?= $si[2] ?>"><?= $si[1] ?></div>
                        <div style="font-size:11px;color:#64748b"><?= $si[0] ?></div>
                    </div>
                    <?php endforeach; ?>
                    <div style="text-align:center;flex:1;min-width:55px;background:#fff;padding:10px;border-radius:8px">
                        <div style="font-size:22px;font-weight:700;color:<?= $att['pct']>=75?'#059669':'#dc2626' ?>"><?= $att['pct'] ?>%</div>
                        <div style="font-size:11px;color:#64748b">Rate</div>
                    </div>
                </div>
                <!-- Progress bar -->
                <div style="margin-top:12px;height:8px;background:#dcfce7;border-radius:4px">
                    <div style="width:<?= $att['pct'] ?>%;height:100%;background:<?= $att['pct']>=75?'#10b981':'#ef4444' ?>;border-radius:4px;transition:width .3s"></div>
                </div>
            </div>

            <!-- Recent Records -->
            <?php if (!empty($recent)): ?>
            <div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:8px">Recent Attendance</div>
            <div style="display:flex;flex-direction:column;gap:5px">
                <?php foreach ($recent as $r):
                    $colors = ['present'=>['#d1fae5','#065f46'],'absent'=>['#fee2e2','#7f1d1d'],'late'=>['#fef3c7','#92400e'],'excused'=>['#dbeafe','#1e40af']];
                    $c = $colors[$r['status']] ?? ['#f1f5f9','#475569'];
                ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#f8fafc;border-radius:6px">
                    <span style="font-size:13px;color:#374151"><?= date('D, d M Y', strtotime($r['date'])) ?></span>
                    <div style="display:flex;gap:8px;align-items:center">
                        <?php if ($r['time_in']): ?><span style="font-size:12px;color:#94a3b8"><?= date('h:i A', strtotime($r['time_in'])) ?></span><?php endif; ?>
                        <span style="font-size:11px;font-weight:600;padding:3px 10px;border-radius:12px;background:<?= $c[0] ?>;color:<?= $c[1] ?>"><?= ucfirst($r['status']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div style="padding:16px 24px;border-top:1px solid #f1f5f9;display:flex;gap:8px">
            <a href="students.php?edit=<?= $viewStudent['id'] ?>" class="btn btn-secondary"><i data-feather="edit"></i> Edit Student</a>
            <a href="?class=<?= urlencode($selectedClass) ?>" class="btn btn-secondary" style="margin-left:auto">Close</a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../footer.php'; ?>
