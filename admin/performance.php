<?php
$pageTitle = 'Performance Tracking';
require_once __DIR__ . '/../auth.php';
requireRole('admin', '../index.php');
require_once __DIR__ . '/../header.php';

$db = getDB();
$selectedClass = $_GET['class'] ?? '';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Get all classes
$stmt = $db->prepare("SELECT DISTINCT class FROM students WHERE is_active = 1 ORDER BY class");
$stmt->execute();
$classes = $stmt->fetchAll();

// Get class-wise performance
$query = "
    SELECT 
        s.class,
        COUNT(DISTINCT s.id) as total_students,
        COUNT(DISTINCT sa.id) as total_marked,
        SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as total_present,
        SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as total_absent,
        SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) as total_late
    FROM students s
    LEFT JOIN student_attendance sa ON s.id = sa.student_id AND sa.date BETWEEN ? AND ?
    WHERE s.is_active = 1
";
$params = [$startDate, $endDate];

if (!empty($selectedClass)) {
    $query .= " AND s.class = ?";
    $params[] = $selectedClass;
}

$query .= " GROUP BY s.class ORDER BY s.class";

$stmt = $db->prepare($query);
$stmt->execute($params);
$classPerformance = $stmt->fetchAll();

// Get student performance with percentage
$query = "
    SELECT 
        s.id, s.roll_number, s.name, s.class,
        COUNT(sa.id) as total_days,
        SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) as late
    FROM students s
    LEFT JOIN student_attendance sa ON s.id = sa.student_id AND sa.date BETWEEN ? AND ?
    WHERE s.is_active = 1
";
$params = [$startDate, $endDate];

if (!empty($selectedClass)) {
    $query .= " AND s.class = ?";
    $params[] = $selectedClass;
}

$query .= " GROUP BY s.id ORDER BY present DESC, s.class, s.roll_number";

$stmt = $db->prepare($query);
$stmt->execute($params);
$studentPerformance = $stmt->fetchAll();

// Get frequent absentees (with more details)
$query = "
    SELECT 
        s.id, s.roll_number, s.name, s.class, s.parent_email,
        COUNT(CASE WHEN sa.status = 'absent' THEN 1 END) as absent_count,
        COUNT(CASE WHEN sa.status = 'late' THEN 1 END) as late_count,
        COUNT(sa.id) as total_days
    FROM students s
    LEFT JOIN student_attendance sa ON s.id = sa.student_id AND sa.date BETWEEN ? AND ?
    WHERE s.is_active = 1
";
$params = [$startDate, $endDate];

if (!empty($selectedClass)) {
    $query .= " AND s.class = ?";
    $params[] = $selectedClass;
}

$query .= " GROUP BY s.id HAVING COUNT(CASE WHEN sa.status = 'absent' THEN 1 END) >= 2 ORDER BY COUNT(CASE WHEN sa.status = 'absent' THEN 1 END) DESC LIMIT 20";

$stmt = $db->prepare($query);
$stmt->execute($params);
$frequentAbsentees = $stmt->fetchAll();

// Get top performers
$topPerformersQuery = "
    SELECT 
        s.id, s.roll_number, s.name, s.class,
        COUNT(sa.id) as total_days,
        SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent
    FROM students s
    LEFT JOIN student_attendance sa ON s.id = sa.student_id AND sa.date BETWEEN ? AND ?
    WHERE s.is_active = 1
";
$topParams = [$startDate, $endDate];

if (!empty($selectedClass)) {
    $topPerformersQuery .= " AND s.class = ?";
    $topParams[] = $selectedClass;
}

$topPerformersQuery .= " GROUP BY s.id HAVING COUNT(sa.id) > 0 ORDER BY (SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) / COUNT(sa.id)) DESC LIMIT 10";

$stmt = $db->prepare($topPerformersQuery);
$stmt->execute($topParams);
$topPerformers = $stmt->fetchAll();
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="trending-up"></i> Performance Tracking</h1>
        <p>Student and class-wise attendance performance analysis</p>
    </div>
</div>

<div class="page-content">
    <!-- Filters -->
    <div class="card mb-6">
        <div class="card-body">
            <form method="GET" class="form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" value="<?= $startDate ?>" class="form-input">
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" value="<?= $endDate ?>" class="form-input">
                    </div>
                    <div class="form-group">
                        <label>Filter by Class</label>
                        <select name="class" class="form-input">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $class): ?>
                            <option value="<?= htmlspecialchars($class['class']) ?>" <?= $selectedClass === $class['class'] ? 'selected' : '' ?>>
                                Class <?= htmlspecialchars($class['class']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex;gap:8px;align-items:flex-end;">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i data-feather="filter"></i> Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Class-wise Performance Summary -->
    <div class="card mb-6">
        <div class="card-header">
            <h3><i data-feather="layers"></i> Class-wise Performance</h3>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Total Students</th>
                            <th>Total Days Marked</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Late</th>
                            <th>Class Avg %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classPerformance as $perf):
                            $avgPercentage = $perf['total_marked'] > 0 ? round(($perf['total_present'] / $perf['total_marked']) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><strong>Class <?= htmlspecialchars($perf['class']) ?></strong></td>
                            <td><?= $perf['total_students'] ?></td>
                            <td><?= $perf['total_marked'] ?></td>
                            <td><span class="badge" style="background:#d1fae5;color:#059669"><?= $perf['total_present'] ?></span></td>
                            <td><span class="badge" style="background:#fee2e2;color:#dc2626"><?= $perf['total_absent'] ?></span></td>
                            <td><span class="badge" style="background:#fef3c7;color:#d97706"><?= $perf['total_late'] ?></span></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <div style="width:100px;height:8px;background:#e5e7eb;border-radius:4px;overflow:hidden;">
                                        <div style="width:<?= $avgPercentage ?>%;height:100%;background:<?= $avgPercentage >= 80 ? '#22c55e' : ($avgPercentage >= 60 ? '#f59e0b' : '#ef4444') ?>"></div>
                                    </div>
                                    <span style="font-weight:600;min-width:50px;"><?= $avgPercentage ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Top Performers -->
        <div class="card">
            <div class="card-header">
                <h3><i data-feather="award"></i> Top Performers</h3>
            </div>
            <div class="card-body">
                <div class="table-wrapper">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Class</th>
                                <th>Attendance %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topPerformers as $performer):
                                $percentage = $performer['total_days'] > 0 ? round(($performer['present'] / $performer['total_days']) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($performer['name']) ?></div>
                                    <div style="font-size:12px;color:#6b7280;"><?= htmlspecialchars($performer['roll_number']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($performer['class']) ?></td>
                                <td>
                                    <span class="badge" style="background:#d1fae5;color:#059669;font-weight:600;">
                                        <?= $percentage ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (empty($topPerformers)): ?>
                <div style="padding:20px;text-align:center;color:#6b7280;font-size:13px;">
                    No attendance data available
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Frequent Absentees Alert -->
        <div class="card">
            <div class="card-header">
                <h3><i data-feather="alert-circle"></i> Frequent Absentees</h3>
            </div>
            <div class="card-body">
                <div class="table-wrapper">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Absences</th>
                                <th>Avg %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($frequentAbsentees as $absentee):
                                $percentage = $absentee['total_days'] > 0 ? round((($absentee['total_days'] - $absentee['absent_count']) / $absentee['total_days']) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($absentee['name']) ?></div>
                                    <div style="font-size:12px;color:#6b7280;"><?= htmlspecialchars($absentee['class']) ?></div>
                                </td>
                                <td>
                                    <span class="badge" style="background:#fee2e2;color:#dc2626;font-weight:600;">
                                        <?= $absentee['absent_count'] ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge" style="background:#fee2e2;color:#dc2626;">
                                        <?= $percentage ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (empty($frequentAbsentees)): ?>
                <div style="padding:20px;text-align:center;color:#6b7280;font-size:13px;">
                    No students with frequent absences
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Full Student Performance Table -->
    <div class="card">
        <div class="card-header">
            <h3><i data-feather="list"></i> All Students Performance <?= !empty($selectedClass) ? '- Class ' . htmlspecialchars($selectedClass) : '' ?></h3>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Roll No</th>
                            <th>Student Name</th>
                            <th>Class</th>
                            <th>Total Days</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Late</th>
                            <th>Attendance %</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($studentPerformance as $student):
                            $percentage = $student['total_days'] > 0 ? round(($student['present'] / $student['total_days']) * 100, 1) : 0;
                            
                            if ($percentage >= 75) {
                                $status = 'Good';
                                $statusColor = ['bg' => '#d1fae5', 'text' => '#059669'];
                            } elseif ($percentage >= 50) {
                                $status = 'Average';
                                $statusColor = ['bg' => '#fef3c7', 'text' => '#d97706'];
                            } else {
                                $status = 'Poor';
                                $statusColor = ['bg' => '#fee2e2', 'text' => '#dc2626'];
                            }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($student['roll_number']) ?></td>
                            <td><strong><?= htmlspecialchars($student['name']) ?></strong></td>
                            <td><?= htmlspecialchars($student['class']) ?></td>
                            <td><?= $student['total_days'] ?></td>
                            <td><?= $student['present'] ?></td>
                            <td><?= $student['absent'] ?></td>
                            <td><?= $student['late'] ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <div style="width:60px;height:6px;background:#e5e7eb;border-radius:3px;overflow:hidden;">
                                        <div style="width:<?= $percentage ?>%;height:100%;background:<?= $percentage >= 75 ? '#22c55e' : ($percentage >= 50 ? '#f59e0b' : '#ef4444') ?>"></div>
                                    </div>
                                    <span style="font-weight:600;min-width:40px;"><?= $percentage ?>%</span>
                                </div>
                            </td>
                            <td>
                                <span class="badge" style="background:<?= $statusColor['bg'] ?>;color:<?= $statusColor['text'] ?>">
                                    <?= $status ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (empty($studentPerformance)): ?>
            <div style="padding:40px;text-align:center;color:#6b7280;">
                <p>No student data available for the selected period.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
