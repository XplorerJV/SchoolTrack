<?php
$pageTitle = 'Attendance Reports';
require_once __DIR__ . '/../auth.php';
requireRole('principal', '../index.php');

$db = getDB();
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Handle CSV export (must be before any output)
if (isset($_GET['export'])) {
    $stmt = $db->prepare("
        SELECT 
            s.roll_number, s.name, s.class,
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
    
    exportCSV("attendance-report-" . $startDate . "-to-" . $endDate . ".csv",
              ['Roll No', 'Name', 'Class', 'Total Days', 'Present', 'Absent', 'Late', 'Percentage'],
              $data);
}

require_once __DIR__ . '/../header.php';

// Get monthly data
$stmt = $db->prepare("
    SELECT 
        s.roll_number, s.name, s.class,
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
$students = $stmt->fetchAll();

// Get frequent absentees
$stmt = $db->prepare("
    SELECT 
        s.roll_number, s.name, s.class,
        COUNT(CASE WHEN sa.status = 'absent' THEN 1 END) as absent_count
    FROM students s
    LEFT JOIN student_attendance sa ON s.id = sa.student_id AND sa.date BETWEEN ? AND ?
    WHERE s.is_active = 1
    GROUP BY s.id
    HAVING absent_count > 0
    ORDER BY absent_count DESC
");
$stmt->execute([$startDate, $endDate]);
$frequentAbsentees = $stmt->fetchAll();

// Get class-wise summary
$stmt = $db->prepare("
    SELECT 
        s.class,
        COUNT(DISTINCT s.id) as total_students,
        SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as total_present,
        COUNT(DISTINCT sa.date) as working_days
    FROM students s
    LEFT JOIN student_attendance sa ON s.id = sa.student_id AND sa.date BETWEEN ? AND ?
    WHERE s.is_active = 1
    GROUP BY s.class
    ORDER BY s.class
");
$stmt->execute([$startDate, $endDate]);
$classSummary = $stmt->fetchAll();
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="bar-chart-2"></i> Attendance Reports</h1>
        <p>Comprehensive attendance analytics and performance tracking</p>
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
                        <input type="date" name="start_date" value="<?= $startDate ?>" onchange="this.form.submit()">
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" value="<?= $endDate ?>" onchange="this.form.submit()">
                    </div>
                </div>
                <div style="margin-top:10px">
                    <a href="?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&export=1" class="btn btn-sm btn-secondary">
                        <i data-feather="download"></i> Export CSV
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Class Summary -->
    <div class="card mb-6">
        <div class="card-header">
            <h3><i data-feather="layers"></i> Class-wise Summary</h3>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Total Students</th>
                            <th>Total Presents</th>
                            <th>Working Days</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classSummary as $row): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['class']) ?></strong></td>
                            <td><?= $row['total_students'] ?></td>
                            <td><?= $row['total_present'] ?></td>
                            <td><?= $row['working_days'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Student Attendance Details -->
    <div class="card mb-6">
        <div class="card-header">
            <h3><i data-feather="users"></i> Student Attendance Details</h3>
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
                            <th>%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student):
                            $percentage = $student['total_days'] > 0 ? round(($student['present'] / $student['total_days']) * 100, 2) : 0;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($student['roll_number']) ?></td>
                            <td><?= htmlspecialchars($student['name']) ?></td>
                            <td><?= htmlspecialchars($student['class']) ?></td>
                            <td><?= $student['total_days'] ?></td>
                            <td><?= $student['present'] ?></td>
                            <td><?= $student['absent'] ?></td>
                            <td><?= $student['late'] ?></td>
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

    <!-- Frequent Absentees -->
    <?php if (!empty($frequentAbsentees)): ?>
    <div class="card">
        <div class="card-header">
            <h3><i data-feather="alert-circle"></i> Frequent Absentees (<?= count($frequentAbsentees) ?>)</h3>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Roll No</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Absences</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($frequentAbsentees as $student): ?>
                        <tr>
                            <td><?= htmlspecialchars($student['roll_number']) ?></td>
                            <td><?= htmlspecialchars($student['name']) ?></td>
                            <td><?= htmlspecialchars($student['class']) ?></td>
                            <td><span class="badge" style="background:#fee2e2;color:#dc2626"><?= $student['absent_count'] ?></span></td>
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
