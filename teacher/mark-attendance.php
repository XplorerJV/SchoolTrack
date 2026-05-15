<?php
$pageTitle = 'Mark Student Attendance';
require_once __DIR__ . '/../auth.php';
requireRole('teacher', '../index.php');
require_once __DIR__ . '/../header.php';

$db = getDB();
$user = getCurrentUser();
$attendanceDate = $_GET['date'] ?? date('Y-m-d');
$selectedClass = $_GET['class'] ?? '';
$error = $success = '';

// Get all classes (teacher can mark attendance for any class)
$stmt = $db->prepare("SELECT DISTINCT class FROM students WHERE is_active = 1 ORDER BY class");
$stmt->execute();
$classes = $stmt->fetchAll();

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attendances = $_POST['attendance'] ?? [];
    $markedCount = 0;
    $errors = [];

    foreach ($attendances as $studentId => $data) {
        $status = $data['status'] ?? 'present';
        $timeIn = $data['time_in'] ?? null;
        $notes = $data['notes'] ?? '';
        
        if (empty($status)) continue;

        try {
            // Check if attendance already exists
            $stmt = $db->prepare("SELECT id FROM student_attendance WHERE student_id = ? AND date = ?");
            $stmt->execute([$studentId, $attendanceDate]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Update existing record
                $stmt = $db->prepare("
                    UPDATE student_attendance 
                    SET status = ?, time_in = ?, notes = ?, marked_by = 'manual', marked_by_user_id = ?
                    WHERE student_id = ? AND date = ?
                ");
                $stmt->execute([$status, $timeIn, $notes, $user['id'], $studentId, $attendanceDate]);
                $action = 'UPDATED';
            } else {
                // Insert new record
                $stmt = $db->prepare("
                    INSERT INTO student_attendance (student_id, date, time_in, status, marked_by, marked_by_user_id, notes)
                    VALUES (?, ?, ?, ?, 'manual', ?, ?)
                ");
                $stmt->execute([$studentId, $attendanceDate, $timeIn, $status, $user['id'], $notes]);
                $action = 'CREATED';
            }

            // Log in audit
            $studentStmt = $db->prepare("SELECT name FROM students WHERE id = ?");
            $studentStmt->execute([$studentId]);
            $student = $studentStmt->fetch();
            
            auditLog($user['id'], 'UPDATE', 'student_attendance', "Teacher manually $action attendance for {$student['name']} on {$attendanceDate}");
            $markedCount++;
        } catch (Exception $e) {
            $errors[] = "Error marking attendance: " . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $error = implode('; ', $errors);
    } elseif ($markedCount > 0) {
        $success = "Attendance marked successfully for $markedCount student(s)!";
    }
}

// Get students to mark
$query = "SELECT * FROM students WHERE is_active = 1";
$params = [];

if (!empty($selectedClass)) {
    $query .= " AND class = ?";
    $params[] = $selectedClass;
}

$query .= " ORDER BY class, roll_number";

$stmt = $db->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get existing attendance for this date
$attendanceMap = [];
if (!empty($students)) {
    $studentIds = array_column($students, 'id');
    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
    $stmt = $db->prepare("
        SELECT * FROM student_attendance 
        WHERE student_id IN ($placeholders) AND date = ?
    ");
    $params = array_merge($studentIds, [$attendanceDate]);
    $stmt->execute($params);
    
    foreach ($stmt->fetchAll() as $record) {
        $attendanceMap[$record['student_id']] = $record;
    }
}
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="edit-2"></i> Mark Student Attendance</h1>
        <p>Manually mark and correct student attendance records</p>
    </div>
</div>

<div class="page-content">
    <?php if ($error): ?>
    <div class="alert alert-danger">
        <i data-feather="alert-circle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
    <div class="alert alert-success">
        <i data-feather="check-circle"></i> <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card mb-6">
        <div class="card-body">
            <form method="GET" class="form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Select Date</label>
                        <input type="date" name="date" value="<?= $attendanceDate ?>" class="form-input" onchange="this.form.submit()">
                    </div>
                    <div class="form-group">
                        <label>Filter by Class</label>
                        <select name="class" class="form-input" onchange="this.form.submit()">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $cls): ?>
                            <option value="<?= htmlspecialchars($cls['class']) ?>" <?= $selectedClass === $cls['class'] ? 'selected' : '' ?>>
                                Class <?= htmlspecialchars($cls['class']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Attendance Marking Form -->
    <form method="POST" action="?date=<?= urlencode($attendanceDate) ?>&class=<?= urlencode($selectedClass) ?>" class="form">
        <div class="card">
            <div class="card-header">
                <h3><i data-feather="edit-3"></i> Mark Attendance for <?= htmlspecialchars(date('d M Y', strtotime($attendanceDate))) ?></h3>
            </div>
            <div class="card-body">
                <?php if (empty($students)): ?>
                <div style="padding:40px;text-align:center;color:#6b7280;">
                    <p>No students found for the selected filters.</p>
                </div>
                <?php else: ?>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:60px">Roll No</th>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th style="width:120px">Status</th>
                                <th style="width:100px">Time In</th>
                                <th style="width:200px">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student):
                                $existing = $attendanceMap[$student['id']] ?? null;
                                $currentStatus = $existing['status'] ?? 'present';
                                $currentTime = $existing['time_in'] ?? '';
                                $currentNotes = $existing['notes'] ?? '';
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($student['roll_number']) ?></strong></td>
                                <td><?= htmlspecialchars($student['name']) ?></td>
                                <td><?= htmlspecialchars($student['class']) ?></td>
                                <td>
                                    <select name="attendance[<?= $student['id'] ?>][status]" class="form-input" style="width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:6px">
                                        <option value="">-</option>
                                        <option value="present" <?= $currentStatus === 'present' ? 'selected' : '' ?>>Present</option>
                                        <option value="absent" <?= $currentStatus === 'absent' ? 'selected' : '' ?>>Absent</option>
                                        <option value="late" <?= $currentStatus === 'late' ? 'selected' : '' ?>>Late</option>
                                        <option value="excused" <?= $currentStatus === 'excused' ? 'selected' : '' ?>>Excused</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="time" name="attendance[<?= $student['id'] ?>][time_in]" value="<?= htmlspecialchars($currentTime) ?>" class="form-input" style="width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:6px">
                                </td>
                                <td>
                                    <input type="text" name="attendance[<?= $student['id'] ?>][notes]" value="<?= htmlspecialchars($currentNotes) ?>" placeholder="Reason/Notes" class="form-input" style="width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:6px">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body" style="background:#f9fafb;border-top:1px solid #e5e7eb">
                <div style="display:flex;gap:10px">
                    <button type="submit" class="btn btn-success">
                        <i data-feather="save"></i> Save Attendance
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i data-feather="x"></i> Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>

    <!-- Help Text -->
    <div style="margin-top:20px;padding:16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;color:#1e40af">
        <p style="margin:0;font-size:13px;line-height:1.6">
            <strong><i data-feather="info" style="display:inline;width:16px;height:16px;margin-right:6px"></i>Note:</strong>
            Mark attendance for students in your class. All changes are logged in the audit trail for transparency. 
            You can correct previous records by selecting their date and updating the status.
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
