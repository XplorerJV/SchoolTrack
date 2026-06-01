<?php
$pageTitle = 'Class Folder';
require_once __DIR__ . '/../auth.php';
requireRole('admin', '../index.php');
require_once __DIR__ . '/../header.php';

$db = getDB();
$selectedClass = $_GET['class'] ?? '';
if ($selectedClass === '') { header('Location: class-folders.php'); exit; }

// Fetch students with full details
$stmt = $db->prepare("SELECT * FROM students WHERE class=? AND is_active=1 ORDER BY roll_number");
$stmt->execute([$selectedClass]);
$students = $stmt->fetchAll();

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
        <p><?= count($students) ?> students</p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="class-folders.php" class="btn btn-secondary"><i data-feather="arrow-left"></i> Back</a>
        <a href="../teacher/mark-attendance.php?class=<?= urlencode($selectedClass) ?>" class="btn btn-primary"><i data-feather="check-square"></i> Mark Attendance</a>
        <a href="class-performance.php?class=<?= urlencode($selectedClass) ?>" class="btn btn-secondary"><i data-feather="bar-chart-2"></i> Performance</a>
    </div>
</div>

<div class="page-content">
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
                            <th>Gender</th>
                            <th>DOB</th>
                            <th>Contact</th>
                            <th>Parent Phone</th>
                            <th style="text-align:center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($students as $s): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($s['roll_number']) ?></strong></td>
                        <td><?= htmlspecialchars($s['name']) ?></td>
                        <td><span class="badge" style="background:#f1f5f9;color:#475569"><?= htmlspecialchars($s['section'] ?? '-') ?></span></td>
                        <td style="font-size:13px"><?= ucfirst($s['gender'] ?? '-') ?></td>
                        <td style="font-size:13px"><?= $s['date_of_birth'] ? date('d M Y', strtotime($s['date_of_birth'])) : '-' ?></td>
                        <td style="font-size:13px"><?= htmlspecialchars($s['contact'] ?? '-') ?></td>
                        <td style="font-size:13px"><?= htmlspecialchars($s['parent_phone'] ?? '-') ?></td>
                        <td style="text-align:center">
                            <div class="action-buttons" style="justify-content:center">
                                <a href="?class=<?= urlencode($selectedClass) ?>&view=<?= $s['id'] ?>" class="btn btn-sm btn-primary">
                                    <i data-feather="user"></i> Profile
                                </a>
                                <a href="../teacher/mark-attendance.php?class=<?= urlencode($selectedClass) ?>" class="btn btn-sm btn-secondary">
                                    <i data-feather="check-square"></i> Mark
                                </a>
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
