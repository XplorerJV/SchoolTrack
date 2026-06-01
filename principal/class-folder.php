<?php
$pageTitle = 'Class Folder';
require_once __DIR__ . '/../auth.php';
requireRole('principal', '../index.php');
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

?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="folder"></i> Class <?= htmlspecialchars($selectedClass) ?></h1>
        <p><?= count($students) ?> students | Date: <?= htmlspecialchars($attendanceDate) ?> | Period: <?= htmlspecialchars($selectedPeriod . ' (' . $periodTimes[$selectedPeriod] . ')') ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="class-folders.php" class="btn btn-secondary"><i data-feather="arrow-left"></i> Back</a>
        <a href="attendance.php?class=<?= urlencode($selectedClass) ?>&date=<?= urlencode($attendanceDate) ?>&period=<?= $selectedPeriod ?>" class="btn btn-info"><i data-feather="eye"></i> View Attendance</a>
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
                            <th style="text-align:center">Notes</th>
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
                        <td style="font-size:13px"><?= $att && $att['notes'] ? htmlspecialchars($att['notes']) : '-' ?></td>
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
