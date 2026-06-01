<?php
$pageTitle = 'Class Attendance Report';
require_once __DIR__ . '/../auth.php';
requireRole('principal', '../index.php');
require_once __DIR__ . '/../header.php';

$db = getDB();
$selectedClass = $_GET['class'] ?? '';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

if (empty($selectedClass)) {
    header('Location: classes.php');
    exit;
}

// Get class info
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_students,
        COUNT(DISTINCT section) as sections
    FROM students 
    WHERE class = ? AND is_active = 1
");
$stmt->execute([$selectedClass]);
$classInfo = $stmt->fetch();
// Get student performance for selected period
$stmt = $db->prepare("
    SELECT 
        s.id, 
        s.roll_number, 
        s.name, 
        s.section,
        COUNT(sa.id) as total_days,
        SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN sa.status = 'excused' THEN 1 ELSE 0 END) as excused
    FROM students s
    LEFT JOIN student_attendance sa ON s.id = sa.student_id AND sa.date BETWEEN ? AND ?
    WHERE s.class = ? AND s.is_active = 1
    GROUP BY s.id, s.roll_number, s.name, s.section
    ORDER BY s.roll_number
");
$stmt->execute([$startDate, $endDate, $selectedClass]);
$students = $stmt->fetchAll();

// Calculate class-wide statistics
$classStats = [
    'total_marked' => 0,
    'total_present' => 0,
    'total_absent' => 0,
    'total_late' => 0,
    'total_excused' => 0,
    'attendance_percentage' => 0
];

foreach ($students as $student) {
    $classStats['total_marked'] += $student['total_days'];
    $classStats['total_present'] += $student['present'];
    $classStats['total_absent'] += $student['absent'];
    $classStats['total_late'] += $student['late'];
    $classStats['total_excused'] += $student['excused'];
}

if ($classStats['total_marked'] > 0) {
    $classStats['attendance_percentage'] = round(($classStats['total_present'] / $classStats['total_marked']) * 100, 2);
}

// Get daily breakdown for the period
$stmt = $db->prepare("
    SELECT 
        sa.date,
        COUNT(DISTINCT CASE WHEN sa.status = 'present' THEN sa.student_id END) as present,
        COUNT(DISTINCT CASE WHEN sa.status = 'absent' THEN sa.student_id END) as absent,
        COUNT(DISTINCT CASE WHEN sa.status = 'late' THEN sa.student_id END) as late,
        COUNT(DISTINCT CASE WHEN sa.status = 'excused' THEN sa.student_id END) as excused,
        COUNT(DISTINCT sa.student_id) as total_marked
    FROM student_attendance sa
    JOIN students s ON sa.student_id = s.id
    WHERE s.class = ? AND sa.date BETWEEN ? AND ?
    GROUP BY sa.date
    ORDER BY sa.date DESC
");
$stmt->execute([$selectedClass, $startDate, $endDate]);
$dailyBreakdown = $stmt->fetchAll();
?>

<div class="page-header">
    <div class="header-content">
        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
            <div>
                <h1><i data-feather="bar-chart-2"></i> Class <?php echo htmlspecialchars($selectedClass); ?> - Attendance Report</h1>
                <p>Period: <?php echo htmlspecialchars($startDate); ?> to <?php echo htmlspecialchars($endDate); ?></p>
            </div>
            <a href="classes.php" class="btn btn-secondary" style="margin-top: 10px;">
                <i data-feather="arrow-left"></i> Back to Classes
            </a>
        </div>
    </div>
</div>

<div class="page-content">
    <!-- Class Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-box">
                <p>Total Students</p>
                <h3><?php echo $classInfo['total_students']; ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box success">
                <p>Attendance Rate</p>
                <h3><?php echo $classStats['attendance_percentage']; ?>%</h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box warning">
                <p>Days Recorded</p>
                <h3><?php echo $classStats['total_marked'] > 0 ? round($classStats['total_marked'] / $classInfo['total_students']) : 0; ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box info">
                <p>Sections</p>
                <h3><?php echo $classInfo['sections']; ?></h3>
            </div>
        </div>
    </div>

    <!-- Date Filter -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="get" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                        <input type="hidden" name="class" value="<?php echo htmlspecialchars($selectedClass); ?>">
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <label><strong>From:</strong></label>
                            <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" class="form-control">
                        </div>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <label><strong>To:</strong></label>
                            <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" class="form-control">
                        </div>
                        <button type="submit" class="btn btn-primary"><i data-feather="search"></i> Filter</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>Period Summary</h3>
                </div>
                <div class="card-body">
                    <div class="summary-row">
                        <span>Total Attendance Records:</span>
                        <strong><?php echo $classStats['total_marked']; ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Present:</span>
                        <strong class="text-success"><?php echo $classStats['total_present']; ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Absent:</span>
                        <strong class="text-danger"><?php echo $classStats['total_absent']; ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Late:</span>
                        <strong class="text-warning"><?php echo $classStats['total_late']; ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Excused:</span>
                        <strong class="text-info"><?php echo $classStats['total_excused']; ?></strong>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>Recent Daily Records</h3>
                </div>
                <div class="card-body">
                    <?php if (count($dailyBreakdown) === 0): ?>
                        <p class="text-muted">No attendance data for selected period</p>
                    <?php else: ?>
                        <div class="daily-list">
                            <?php foreach (array_slice($dailyBreakdown, 0, 5) as $day): ?>
                                <div class="daily-item">
                                    <div class="date-badge"><?php echo date('d M', strtotime($day['date'])); ?></div>
                                    <div class="daily-stats">
                                        <span class="badge badge-success"><?php echo $day['present']; ?> P</span>
                                        <span class="badge badge-danger"><?php echo $day['absent']; ?> A</span>
                                        <span class="badge badge-warning"><?php echo $day['late']; ?> L</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Performance Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h2>Individual Student Performance</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($students)): ?>
                        <div class="alert alert-info">
                            <i data-feather="info"></i> No students found in this class.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th>Roll #</th>
                                        <th>Name</th>
                                        <th>Section</th>
                                        <th class="text-center">Days Marked</th>
                                        <th class="text-center">Present</th>
                                        <th class="text-center">Absent</th>
                                        <th class="text-center">Late</th>
                                        <th class="text-center">Attendance %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <?php 
                                        $percentage = $student['total_days'] > 0 ? 
                                            round(($student['present'] / $student['total_days']) * 100) : 0;
                                        $statusClass = $percentage >= 90 ? 'success' : ($percentage >= 75 ? 'warning' : 'danger');
                                        ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($student['roll_number']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($student['name']); ?></td>
                                            <td><?php echo htmlspecialchars($student['section'] ?? '-'); ?></td>
                                            <td class="text-center">
                                                <span class="badge badge-secondary"><?php echo $student['total_days']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-success"><?php echo $student['present']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-danger"><?php echo $student['absent']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-warning"><?php echo $student['late']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-<?php echo $statusClass; ?>"><?php echo $percentage; ?>%</span>
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
    </div>
</div>

<style>
.row { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
.col-12 { flex: 1 1 100%; }
.col-md-3 { flex: 1 1 calc(25% - 15px); min-width: 200px; }
.col-md-6 { flex: 1 1 calc(50% - 10px); min-width: 350px; }
.stat-box {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.stat-box p { margin: 0 0 10px 0; font-size: 12px; color: #999; }
.stat-box h3 { margin: 0; font-size: 28px; font-weight: bold; color: #333; }
.stat-box.success h3 { color: #28a745; }
.stat-box.warning h3 { color: #ffc107; }
.stat-box.info h3 { color: #17a2b8; }
.summary-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
.summary-row:last-child { border-bottom: none; }
.daily-list { display: flex; flex-direction: column; gap: 10px; }
.daily-item { display: flex; align-items: center; gap: 15px; padding: 10px; background: #f9f9f9; border-radius: 5px; }
.date-badge { background: #007bff; color: white; padding: 5px 10px; border-radius: 4px; font-weight: bold; min-width: 60px; text-align: center; }
.daily-stats { display: flex; gap: 8px; }
.text-success { color: #28a745; }
.text-danger { color: #dc3545; }
.text-warning { color: #ffc107; }
.text-info { color: #17a2b8; }
.text-muted { color: #6c757d; }
.text-center { text-align: center; }
.table { width: 100%; border-collapse: collapse; }
.table th { background: #f8f9fa; padding: 12px; border-bottom: 2px solid #dee2e6; font-weight: 600; }
.table td { padding: 12px; border-bottom: 1px solid #dee2e6; }
.table tr:hover { background: #f9f9f9; }
</style>

<?php require_once __DIR__ . '/../footer.php'; ?>
