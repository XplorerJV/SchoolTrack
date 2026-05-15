<?php
$pageTitle = 'Daily Report';
require_once __DIR__ . '/../auth.php';
requireRole('admin', '../index.php');

$db = getDB();
$reportDate = $_GET['date'] ?? date('Y-m-d');
$selectedClass = $_GET['class'] ?? '';

// Get report date details
$dateObj = DateTime::createFromFormat('Y-m-d', $reportDate);
$formattedDate = $dateObj ? $dateObj->format('d M Y (l)') : date('d M Y');

// Get attendance details for the day (needed for both export and display)
$query = "
    SELECT 
        s.id, s.roll_number, s.name, s.class, s.section,
        sa.status, sa.time_in, sa.marked_by, sa.notes
    FROM students s
    LEFT JOIN student_attendance sa ON s.id = sa.student_id AND sa.date = ?
    WHERE s.is_active = 1
";
$params = [$reportDate];

if (!empty($selectedClass)) {
    $query .= " AND s.class = ?";
    $params[] = $selectedClass;
}

$query .= " ORDER BY s.class, s.roll_number";

$stmt = $db->prepare($query);
$stmt->execute($params);
$attendanceData = $stmt->fetchAll();

// Handle CSV export (must be before any output)
if (isset($_GET['export'])) {
    $data = [];
    foreach ($attendanceData as $row) {
        $data[] = [
            $row['roll_number'],
            $row['name'],
            $row['class'],
            ucfirst($row['status'] ?? 'absent'),
            $row['time_in'] ? formatTime($row['time_in']) : '-',
            $row['marked_by'] ? ucfirst($row['marked_by']) : '-',
            $row['notes'] ?? ''
        ];
    }
    exportCSV("daily-report-" . $reportDate . ".csv",
              ['Roll No', 'Name', 'Class', 'Status', 'Time In', 'Marked By', 'Notes'],
              $data);
}

require_once __DIR__ . '/../header.php';

// Get total stats for the day
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
    FROM student_attendance
    WHERE date = ?
");
$stmt->execute([$reportDate]);
$dayStats = $stmt->fetch();

// Get unique classes
$stmt = $db->prepare("SELECT DISTINCT class FROM students WHERE is_active = 1 ORDER BY class");
$stmt->execute();
$classes = $stmt->fetchAll();

// Get class-wise summary
$stmt = $db->prepare("
    SELECT 
        s.class,
        COUNT(*) as total,
        SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) as late
    FROM students s
    LEFT JOIN student_attendance sa ON s.id = sa.student_id AND sa.date = ?
    WHERE s.is_active = 1
    GROUP BY s.class
    ORDER BY s.class
");
$stmt->execute([$reportDate]);
$classStats = $stmt->fetchAll();
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="calendar"></i> Daily Attendance Report</h1>
        <p>Detailed attendance report for <?= htmlspecialchars($formattedDate) ?></p>
    </div>
</div>

<div class="page-content">
    <!-- Date & Class Selector -->
    <div class="card mb-6">
        <div class="card-body">
            <form method="GET" class="form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Select Date</label>
                        <input type="date" name="date" value="<?= $reportDate ?>" onchange="this.form.submit()" class="form-input">
                    </div>
                    <div class="form-group">
                        <label>Filter by Class</label>
                        <select name="class" onchange="this.form.submit()" class="form-input">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $class): ?>
                            <option value="<?= htmlspecialchars($class['class']) ?>" <?= $selectedClass === $class['class'] ? 'selected' : '' ?>>
                                Class <?= htmlspecialchars($class['class']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex;gap:8px;align-items:flex-end;">
                        <a href="?date=<?= $reportDate ?>&export=1<?= !empty($selectedClass) ? '&class=' . urlencode($selectedClass) : '' ?>" class="btn btn-sm btn-secondary">
                            <i data-feather="download"></i> Export
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Overall Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(59,130,246,.1)">
                <i data-feather="users" style="color:#3b82f6"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Total Students</p>
                <p class="stat-value"><?= $dayStats['total'] ?></p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(34,197,94,.1)">
                <i data-feather="check-circle" style="color:#22c55e"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Present</p>
                <p class="stat-value"><?= $dayStats['present'] ?></p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(239,68,68,.1)">
                <i data-feather="x-circle" style="color:#ef4444"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Absent</p>
                <p class="stat-value"><?= $dayStats['absent'] ?></p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,.1)">
                <i data-feather="clock" style="color:#f59e0b"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Late</p>
                <p class="stat-value"><?= $dayStats['late'] ?></p>
            </div>
        </div>
    </div>

    <!-- Class-wise Summary -->
    <?php if (empty($selectedClass)): ?>
    <div class="card mb-6">
        <div class="card-header">
            <h3><i data-feather="layers"></i> Class-wise Summary</h3>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Total</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Late</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classStats as $stat): 
                            $percentage = $stat['total'] > 0 ? round(($stat['present'] / $stat['total']) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><strong>Class <?= htmlspecialchars($stat['class']) ?></strong></td>
                            <td><?= $stat['total'] ?></td>
                            <td><span class="badge" style="background:#d1fae5;color:#059669"><?= $stat['present'] ?></span></td>
                            <td><span class="badge" style="background:#fee2e2;color:#dc2626"><?= $stat['absent'] ?></span></td>
                            <td><span class="badge" style="background:#fef3c7;color:#d97706"><?= $stat['late'] ?></span></td>
                            <td>
                                <span class="badge" style="background:<?= $percentage >= 80 ? '#d1fae5' : ($percentage >= 60 ? '#fef3c7' : '#fee2e2') ?>;color:<?= $percentage >= 80 ? '#059669' : ($percentage >= 60 ? '#d97706' : '#dc2626') ?>">
                                    <?= $percentage ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Detailed Attendance -->
    <div class="card">
        <div class="card-header">
            <h3><i data-feather="list"></i> Detailed Attendance <?= !empty($selectedClass) ? '- Class ' . htmlspecialchars($selectedClass) : '' ?></h3>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Roll No</th>
                            <th>Student Name</th>
                            <th>Class</th>
                            <th>Status</th>
                            <th>Time In</th>
                            <th>Marked By</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendanceData as $row): 
                            $status = $row['status'] ?? 'absent';
                            $statusColor = [
                                'present' => ['bg' => '#d1fae5', 'text' => '#059669', 'icon' => '✓'],
                                'absent' => ['bg' => '#fee2e2', 'text' => '#dc2626', 'icon' => '✗'],
                                'late' => ['bg' => '#fef3c7', 'text' => '#d97706', 'icon' => '⏱'],
                                'excused' => ['bg' => '#dbeafe', 'text' => '#0369a1', 'icon' => '!']
                            ];
                            $colors = $statusColor[$status] ?? ['bg' => '#f3f4f6', 'text' => '#6b7280', 'icon' => '-'];
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['roll_number']) ?></strong></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['class']) ?><?= $row['section'] ? '-' . htmlspecialchars($row['section']) : '' ?></td>
                            <td>
                                <span class="badge" style="background:<?= $colors['bg'] ?>;color:<?= $colors['text'] ?>">
                                    <?= $colors['icon'] ?> <?= ucfirst($status) ?>
                                </span>
                            </td>
                            <td><?= $row['time_in'] ? formatTime($row['time_in']) : '-' ?></td>
                            <td><?= $row['marked_by'] ? ucfirst($row['marked_by']) : '-' ?></td>
                            <td><?= $row['notes'] ? htmlspecialchars($row['notes']) : '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (empty($attendanceData)): ?>
            <div style="padding:40px;text-align:center;color:#6b7280;">
                <p>No attendance records found for the selected date and filters.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
