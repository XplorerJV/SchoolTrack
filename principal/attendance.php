<?php
$pageTitle = 'View Attendance';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../header.php';

requireRole('principal', '../index.php');

$db = getDB();
$filterDate = $_GET['date'] ?? date('Y-m-d');
$filterClass = $_GET['class'] ?? '';
$filterStatus = $_GET['status'] ?? '';

// Get unique classes
$stmt = $db->prepare("SELECT DISTINCT class FROM students WHERE is_active = 1 ORDER BY class");
$stmt->execute();
$classes = $stmt->fetchAll();

// Get attendance records
$query = "
    SELECT sa.*, s.name, s.roll_number, s.class, s.section, s.photo
    FROM student_attendance sa
    JOIN students s ON s.id = sa.student_id
    WHERE sa.date = ?
";
$params = [$filterDate];

if (!empty($filterClass)) {
    $query .= " AND s.class = ?";
    $params[] = $filterClass;
}

if (!empty($filterStatus)) {
    $query .= " AND sa.status = ?";
    $params[] = $filterStatus;
}

$query .= " ORDER BY s.class, s.roll_number";

$stmt = $db->prepare($query);
$stmt->execute($params);
$attendance = $stmt->fetchAll();

// Get attendance stats
$stmt = $db->prepare("
    SELECT 
        status,
        COUNT(*) as count
    FROM student_attendance
    WHERE date = ?
    GROUP BY status
");
$stmt->execute([$filterDate]);
$stats = $stmt->fetchAll();
$statsMap = [];
foreach ($stats as $stat) {
    $statsMap[$stat['status']] = $stat['count'];
}
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="check-square"></i> View Attendance</h1>
        <p>School-wide attendance records for the selected date</p>
    </div>
</div>

<div class="page-content">
    <!-- Filters -->
    <div class="card mb-6">
        <div class="card-body">
            <form method="GET" class="form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="date" value="<?= $filterDate ?>" onchange="this.form.submit()">
                    </div>
                    <div class="form-group">
                        <label>Class</label>
                        <select name="class" onchange="this.form.submit()">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $cls): ?>
                            <option value="<?= htmlspecialchars($cls['class']) ?>" <?= $filterClass === $cls['class'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cls['class']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="present" <?= $filterStatus === 'present' ? 'selected' : '' ?>>Present</option>
                            <option value="absent" <?= $filterStatus === 'absent' ? 'selected' : '' ?>>Absent</option>
                            <option value="late" <?= $filterStatus === 'late' ? 'selected' : '' ?>>Late</option>
                            <option value="excused" <?= $filterStatus === 'excused' ? 'selected' : '' ?>>Excused</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(34,197,94,.1)">
                <i data-feather="check-circle" style="color:#22c55e"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Present</p>
                <p class="stat-value"><?= $statsMap['present'] ?? 0 ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(239,68,68,.1)">
                <i data-feather="x-circle" style="color:#ef4444"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Absent</p>
                <p class="stat-value"><?= $statsMap['absent'] ?? 0 ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,.1)">
                <i data-feather="alert-circle" style="color:#f59e0b"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Late</p>
                <p class="stat-value"><?= $statsMap['late'] ?? 0 ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(59,130,246,.1)">
                <i data-feather="info" style="color:#3b82f6"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Excused</p>
                <p class="stat-value"><?= $statsMap['excused'] ?? 0 ?></p>
            </div>
        </div>
    </div>

    <!-- Attendance Table -->
    <div class="card">
        <div class="card-header">
            <h3><i data-feather="table"></i> Attendance for <?= formatDate($filterDate) ?> (<?= count($attendance) ?> records)</h3>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Roll No</th>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Status</th>
                            <th>Time In</th>
                            <th>Marked By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attendance)): ?>
                        <tr><td colspan="7" style="text-align:center;padding:20px;color:#6b7280">No records found</td></tr>
                        <?php else: ?>
                            <?php foreach ($attendance as $record): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($record['photo'])): ?>
                                    <img src="<?= htmlspecialchars($record['photo']) ?>" alt="<?= htmlspecialchars($record['name']) ?>" style="width:42px;height:42px;border-radius:9999px;object-fit:cover;border:1px solid #e2e8f0;">
                                    <?php else: ?>
                                    <div style="width:42px;height:42px;border-radius:9999px;background:#e2e8f0;display:inline-flex;align-items:center;justify-content:center;color:#64748b;font-size:12px;">N/A</div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($record['roll_number']) ?></td>
                                <td><?= htmlspecialchars($record['name']) ?></td>
                                <td><?= htmlspecialchars($record['class']) ?></td>
                                <td><span class="badge badge-<?= $record['status'] ?>"><?= ucfirst($record['status']) ?></span></td>
                                <td><?= $record['time_in'] ? formatTime($record['time_in']) : '-' ?></td>
                                <td><?= ucfirst($record['marked_by']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
