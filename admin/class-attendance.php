<?php
$pageTitle = 'Mark Class Attendance';
require_once __DIR__ . '/../auth.php';
requireRole('admin', '../index.php');
require_once __DIR__ . '/../header.php';

$db = getDB();
$user = getCurrentUser();
$selectedClass = $_GET['class'] ?? '';
$attendanceDate = $_GET['date'] ?? date('Y-m-d');
$selectedPeriod = (int)($_GET['period'] ?? 1);

// Default period times (can be adjusted in settings)
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
$error = $success = '';

if (empty($selectedClass)) {
    header('Location: classes.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attendances = $_POST['attendance'] ?? [];
    $selectedPeriod = (int)($_POST['period'] ?? $selectedPeriod);
    $markedCount = 0;
    $errors = [];

    foreach ($attendances as $studentId => $data) {
        $status = $data['status'] ?? '';
        $timeIn = $data['time_in'] ?? null;
        $notes = trim($data['notes'] ?? '');
        
        if (empty($status)) continue;

        try {
            $stmt = $db->prepare("SELECT id FROM student_attendance WHERE student_id = ? AND date = ? AND period = ?");
            $stmt->execute([$studentId, $attendanceDate, $selectedPeriod]);
            $existing = $stmt->fetch();

            if ($existing) {
                $stmt = $db->prepare("
                    UPDATE student_attendance 
                    SET status = ?, time_in = ?, notes = ?, marked_by = 'manual', marked_by_user_id = ?, period = ?
                    WHERE student_id = ? AND date = ? AND period = ?
                ");
                $stmt->execute([$status, $timeIn, $notes, $user['id'], $selectedPeriod, $studentId, $attendanceDate, $selectedPeriod]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO student_attendance (student_id, date, time_in, status, marked_by, marked_by_user_id, notes, period)
                    VALUES (?, ?, ?, ?, 'manual', ?, ?, ?)
                ");
                $stmt->execute([$studentId, $attendanceDate, $timeIn, $status, $user['id'], $notes, $selectedPeriod]);
            }
            $markedCount++;
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (count($errors) === 0 && $markedCount > 0) {
        $success = "Attendance marked for $markedCount students!";
        auditLog($_SESSION['user_id'], 'CREATE', 'attendance', "Marked attendance for class $selectedClass on $attendanceDate (period $selectedPeriod)");
    } elseif (count($errors) > 0) {
        $error = 'Some errors occurred: ' . implode(', ', $errors);
    }
}

// Get all students in the class
$stmt = $db->prepare("
    SELECT id, name, roll_number, section 
    FROM students 
    WHERE class = ? AND is_active = 1 
    ORDER BY roll_number
");
$stmt->execute([$selectedClass]);
$students = $stmt->fetchAll();

// Get existing attendance for this date
$existingAttendance = [];
// Get existing attendance for this date and selected period
$stmt = $db->prepare("
    SELECT student_id, status, time_in, notes 
    FROM student_attendance 
    WHERE date = ? AND period = ? AND student_id IN (SELECT id FROM students WHERE class = ?)
");
$stmt->execute([$attendanceDate, $selectedPeriod, $selectedClass]);
foreach ($stmt->fetchAll() as $record) {
    $existingAttendance[$record['student_id']] = $record;
}

// Get attendance summary for the date and period
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused
    FROM student_attendance 
    WHERE date = ? AND period = ? AND student_id IN (SELECT id FROM students WHERE class = ?)
");
$stmt->execute([$attendanceDate, $selectedPeriod, $selectedClass]);
$stats = $stmt->fetch();
?>

<div class="page-header">
    <div class="header-content">
                <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
            <div>
                <h1><i data-feather="check-square"></i> Mark Attendance - Class <?php echo htmlspecialchars($selectedClass); ?></h1>
                <p>Date: <?php echo htmlspecialchars($attendanceDate); ?> | Period: <?php echo htmlspecialchars($selectedPeriod . ' (' . $periodTimes[$selectedPeriod] . ')'); ?></p>
            </div>
            <a href="classes.php" class="btn btn-secondary" style="margin-top: 10px;">
                <i data-feather="arrow-left"></i> Back to Classes
            </a>
        </div>
    </div>
</div>

<div class="alerts-container">
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><i data-feather="check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><i data-feather="alert-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
</div>

<div class="container-fluid">
    <!-- Date and Stats Section -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card-small">
                <p class="text-muted">Total Students</p>
                <h3><?php echo count($students); ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-small success">
                <p class="text-muted">Present</p>
                <h3><?php echo $stats['present'] ?? 0; ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-small danger">
                <p class="text-muted">Absent</p>
                <h3><?php echo $stats['absent'] ?? 0; ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-small warning">
                <p class="text-muted">Late</p>
                <h3><?php echo $stats['late'] ?? 0; ?></h3>
            </div>
        </div>
    </div>

    <!-- Date Selection -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="get" class="form-inline" style="display: flex; gap: 10px; align-items: center;">
                        <input type="hidden" name="class" value="<?php echo htmlspecialchars($selectedClass); ?>">
                        <label>Select Date:</label>
                        <input type="date" name="date" value="<?php echo htmlspecialchars($attendanceDate); ?>" class="form-control">
                        <label>Period:</label>
                        <select name="period" class="form-control">
                            <?php foreach ($periodTimes as $p => $range): ?>
                                <option value="<?php echo $p; ?>" <?php echo ($p == $selectedPeriod) ? 'selected' : ''; ?>>Period <?php echo $p; ?> (<?php echo $range; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary"><i data-feather="search"></i> Load</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Form -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h2>Class <?php echo htmlspecialchars($selectedClass); ?> - Students Attendance</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($students)): ?>
                        <div class="alert alert-info">
                            <i data-feather="info"></i> No students found in this class.
                        </div>
                    <?php else: ?>
                        <form method="post" id="attendanceForm">
                            <input type="hidden" name="period" value="<?php echo htmlspecialchars($selectedPeriod); ?>">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Roll #</th>
                                            <th>Name</th>
                                            <th>Status</th>
                                            <th>Time In</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                            <?php $att = $existingAttendance[$student['id']] ?? null; ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($student['roll_number']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                                <td>
                                                    <select name="attendance[<?php echo $student['id']; ?>][status]" class="form-control form-control-sm">
                                                        <option value="">-- Select --</option>
                                                        <option value="present" <?php echo ($att && $att['status'] === 'present') ? 'selected' : ''; ?>>Present</option>
                                                        <option value="absent" <?php echo ($att && $att['status'] === 'absent') ? 'selected' : ''; ?>>Absent</option>
                                                        <option value="late" <?php echo ($att && $att['status'] === 'late') ? 'selected' : ''; ?>>Late</option>
                                                        <option value="excused" <?php echo ($att && $att['status'] === 'excused') ? 'selected' : ''; ?>>Excused</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="time" name="attendance[<?php echo $student['id']; ?>][time_in]" 
                                                           class="form-control form-control-sm" 
                                                           value="<?php echo ($att && $att['time_in']) ? $att['time_in'] : ''; ?>">
                                                </td>
                                                <td>
                                                    <input type="text" name="attendance[<?php echo $student['id']; ?>][notes]" 
                                                           class="form-control form-control-sm" 
                                                           placeholder="Notes"
                                                           value="<?php echo ($att && $att['notes']) ? htmlspecialchars($att['notes']) : ''; ?>">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="form-actions" style="margin-top: 20px; display: flex; gap: 10px;">
                                <button type="submit" class="btn btn-success" name="submit" value="save">
                                    <i data-feather="save"></i> Save Attendance
                                </button>
                                <a href="classes.php" class="btn btn-secondary">
                                    <i data-feather="x"></i> Cancel
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card-small {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.card-small p {
    margin: 0 0 10px 0;
    font-size: 12px;
}

.card-small h3 {
    margin: 0;
    font-size: 28px;
    font-weight: bold;
    color: #333;
}

.card-small.success h3 { color: #28a745; }
.card-small.danger h3 { color: #dc3545; }
.card-small.warning h3 { color: #ffc107; }

.form-inline { flex-wrap: wrap; }
.form-inline label { margin-right: 10px; }
.form-inline input, .form-inline button { margin-right: 10px; }
</style>

<?php require_once __DIR__ . '/../footer.php'; ?>
