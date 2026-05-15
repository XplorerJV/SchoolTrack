<?php
$pageTitle = 'Reports';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../header.php';

requireRole('admin', '../index.php');

$db = getDB();
$reportType = $_GET['report'] ?? 'daily';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$selectedClass = $_GET['class'] ?? '';

// Handle CSV export
if (isset($_GET['export'])) {
    $export = $_GET['export'];
    
    if ($export === 'daily') {
        $date = $_GET['export_date'] ?? date('Y-m-d');
        $stmt = $db->prepare("
            SELECT s.roll_number, s.name, s.class, sa.status, sa.time_in, sa.marked_by
            FROM student_attendance sa
            JOIN students s ON s.id = sa.student_id
            WHERE sa.date = ?
            ORDER BY s.class, s.roll_number
        ");
        $stmt->execute([$date]);
        $rows = $stmt->fetchAll();
        
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                $row['roll_number'],
                $row['name'],
                $row['class'],
                ucfirst($row['status']),
                $row['time_in'] ? formatTime($row['time_in']) : '-',
                ucfirst($row['marked_by'])
            ];
        }
        
        exportCSV("daily-attendance-" . $date . ".csv", 
                  ['Roll No', 'Name', 'Class', 'Status', 'Time In', 'Marked By'], 
                  $data);
    } elseif ($export === 'monthly') {
        $stmt = $db->prepare("
            SELECT 
                s.id, s.roll_number, s.name, s.class,
                COUNT(sa.id) as total_days,
                SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) as late
            FROM students s
            LEFT JOIN student_attendance sa ON s.id = sa.student_id AND sa.date BETWEEN ? AND ?
            WHERE s.is_active = 1
            GROUP BY s.id
            ORDER BY s.class, s.roll_number
        ");
        $stmt->execute([$startDate, $endDate]);
        $rows = $stmt->fetchAll();
        
        $data = [];
        foreach ($rows as $row) {
            $percentage = $row['total_days'] > 0 ? round(($row['present'] / $row['total_days']) * 100, 2) : 0;
            $data[] = [
                $row['roll_number'],
                $row['name'],
                $row['class'],
                $row['total_days'],
                $row['present'],
                $row['absent'],
                $row['late'],
                $percentage . '%'
            ];
        }
        
        exportCSV("monthly-attendance-" . $startDate . "-to-" . $endDate . ".csv",
                  ['Roll No', 'Name', 'Class', 'Total Days', 'Present', 'Absent', 'Late', 'Percentage'],
                  $data);
    } elseif ($export === 'absentees') {
        $stmt = $db->prepare("
            SELECT DISTINCT s.roll_number, s.name, s.class, s.parent_email
            FROM students s
            JOIN student_attendance sa ON s.id = sa.student_id
            WHERE sa.date BETWEEN ? AND ? AND sa.status = 'absent'
            ORDER BY s.class, s.roll_number
        ");
        $stmt->execute([$startDate, $endDate]);
        $rows = $stmt->fetchAll();
        
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                $row['roll_number'],
                $row['name'],
                $row['class'],
                $row['parent_email']
            ];
        }
        
        exportCSV("absent-students-" . $startDate . "-to-" . $endDate . ".csv",
                  ['Roll No', 'Name', 'Class', 'Parent Email'],
                  $data);
    }
}

// Get daily attendance data
$stmt = $db->prepare("
    SELECT 
        sa.date,
        COUNT(*) as total,
        SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) as late
    FROM student_attendance sa
    WHERE sa.date BETWEEN ? AND ?
    GROUP BY sa.date
    ORDER BY sa.date DESC
    LIMIT 31
");
$stmt->execute([$startDate, $endDate]);
$dailyData = $stmt->fetchAll();

// Get monthly data
$stmt = $db->prepare("
    SELECT 
        s.id, s.roll_number, s.name, s.class,
        COUNT(sa.id) as total_days,
        SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) as late
    FROM students s
    LEFT JOIN student_attendance sa ON s.id = sa.student_id AND sa.date BETWEEN ? AND ?
    WHERE s.is_active = 1
    GROUP BY s.id
    ORDER BY s.class, s.roll_number
");
$stmt->execute([$startDate, $endDate]);
$monthlyData = $stmt->fetchAll();

// Get frequent absentees
$stmt = $db->prepare("
    SELECT 
        s.id, s.roll_number, s.name, s.class,
        COUNT(CASE WHEN sa.status = 'absent' THEN 1 END) as absent_count
    FROM students s
    LEFT JOIN student_attendance sa ON s.id = sa.student_id AND sa.date BETWEEN ? AND ?
    WHERE s.is_active = 1
    GROUP BY s.id
    HAVING absent_count > 0
    ORDER BY absent_count DESC
    LIMIT 20
");
$stmt->execute([$startDate, $endDate]);
$frequentAbsentees = $stmt->fetchAll();

// Get unique classes
$stmt = $db->prepare("SELECT DISTINCT class FROM students WHERE is_active = 1 ORDER BY class");
$stmt->execute();
$classes = $stmt->fetchAll();
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="bar-chart-2"></i> Reports</h1>
        <p>Attendance analytics and export reports</p>
    </div>
</div>

<div class="page-content">
    <!-- Filters -->
    <div class="card mb-6">
        <div class="card-body">
            <form method="GET" class="form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Report Type</label>
                        <select name="report" onchange="this.form.submit()">
                            <option value="daily" <?= $reportType === 'daily' ? 'selected' : '' ?>>Daily Report</option>
                            <option value="monthly" <?= $reportType === 'monthly' ? 'selected' : '' ?>>Monthly Report</option>
                            <option value="absentees" <?= $reportType === 'absentees' ? 'selected' : '' ?>>Frequent Absentees</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" value="<?= $startDate ?>" onchange="this.form.submit()">
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" value="<?= $endDate ?>" onchange="this.form.submit()">
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($reportType === 'daily'): ?>
    <!-- Daily Report -->
    <div class="card mb-6">
        <div class="card-header">
            <h3><i data-feather="calendar"></i> Daily Attendance Summary</h3>
            <a href="?report=daily&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&export=daily&export_date=<?= $endDate ?>" class="btn btn-sm btn-secondary">
                <i data-feather="download"></i> Export CSV
            </a>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Late</th>
                            <th>Present %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dailyData as $row): ?>
                        <tr>
                            <td><strong><?= formatDate($row['date']) ?></strong></td>
                            <td><?= $row['total'] ?></td>
                            <td><span class="badge" style="background:#d1fae5;color:#059669"><?= $row['present'] ?></span></td>
                            <td><span class="badge" style="background:#fee2e2;color:#dc2626"><?= $row['absent'] ?></span></td>
                            <td><span class="badge" style="background:#fef3c7;color:#d97706"><?= $row['late'] ?></span></td>
                            <td><?= $row['total'] > 0 ? round(($row['present'] / $row['total']) * 100, 1) : 0 ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php elseif ($reportType === 'monthly'): ?>
    <!-- Monthly Report -->
    <div class="card mb-6">
        <div class="card-header">
            <h3><i data-feather="bar-chart-2"></i> Monthly Attendance Report</h3>
            <a href="?report=monthly&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&export=monthly" class="btn btn-sm btn-secondary">
                <i data-feather="download"></i> Export CSV
            </a>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Roll No</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Total Days</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Late</th>
                            <th>Attendance %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthlyData as $row): 
                            $percentage = $row['total_days'] > 0 ? round(($row['present'] / $row['total_days']) * 100, 2) : 0;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row['roll_number']) ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['class']) ?></td>
                            <td><?= $row['total_days'] ?></td>
                            <td><?= $row['present'] ?></td>
                            <td><?= $row['absent'] ?></td>
                            <td><?= $row['late'] ?></td>
                            <td>
                                <span class="badge" style="background:<?= $percentage >= 75 ? '#d1fae5' : ($percentage >= 50 ? '#fef3c7' : '#fee2e2') ?>;color:<?= $percentage >= 75 ? '#059669' : ($percentage >= 50 ? '#d97706' : '#dc2626') ?>">
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

    <?php elseif ($reportType === 'absentees'): ?>
    <!-- Frequent Absentees Report -->
    <div class="card">
        <div class="card-header">
            <h3><i data-feather="alert-circle"></i> Frequent Absentees</h3>
            <a href="?report=absentees&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&export=absentees" class="btn btn-sm btn-secondary">
                <i data-feather="download"></i> Export CSV
            </a>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Roll No</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Total Absences</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($frequentAbsentees as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['roll_number']) ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['class']) ?></td>
                            <td><span class="badge" style="background:#fee2e2;color:#dc2626"><?= $row['absent_count'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
