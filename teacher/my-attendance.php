<?php
$pageTitle = 'My Attendance';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../header.php';

requireRole('teacher', '../index.php');

$db = getDB();
$user = getCurrentUser();
$error = $success = '';

// Handle attendance marking/editing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'mark') {
        $date = $_POST['date'] ?? date('Y-m-d');
        $status = $_POST['status'] ?? 'present';
        $timeIn = $_POST['time_in'] ?? date('H:i');
        $timeOut = $_POST['time_out'] ?? null;
        $notes = trim($_POST['notes'] ?? '');
        
        // Check if already marked
        $stmt = $db->prepare("SELECT id FROM teacher_attendance WHERE teacher_id = ? AND date = ?");
        $stmt->execute([$user['id'], $date]);
        
        if ($stmt->fetch()) {
            $error = 'You have already marked attendance for this date. Edit it instead.';
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO teacher_attendance (teacher_id, date, time_in, time_out, status, notes) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$user['id'], $date, $timeIn, $timeOut, $status, $notes]);
                $success = 'Attendance marked successfully!';
                auditLog($user['id'], 'CREATE', 'teacher_attendance', "Marked attendance for $date");
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'present';
        $timeIn = $_POST['time_in'] ?? '';
        $timeOut = $_POST['time_out'] ?? null;
        $notes = trim($_POST['notes'] ?? '');
        
        if ($id > 0) {
            try {
                $stmt = $db->prepare("UPDATE teacher_attendance SET status = ?, time_in = ?, time_out = ?, notes = ? WHERE id = ? AND teacher_id = ?");
                $stmt->execute([$status, $timeIn, $timeOut, $notes, $id, $user['id']]);
                $success = 'Attendance updated successfully!';
                auditLog($user['id'], 'UPDATE', 'teacher_attendance', "Updated attendance ID: $id");
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// Get attendance history
$stmt = $db->prepare("
    SELECT * FROM teacher_attendance
    WHERE teacher_id = ?
    ORDER BY date DESC
");
$stmt->execute([$user['id']]);
$attendanceRecords = $stmt->fetchAll();

// Get record for editing
$editRecord = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM teacher_attendance WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$id, $user['id']]);
    $editRecord = $stmt->fetch();
}
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="check-square"></i> My Attendance</h1>
        <p>Mark and manage your attendance records</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('markForm').scrollIntoView({behavior:'smooth'})">
        <i data-feather="plus"></i> Mark Attendance
    </button>
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

    <!-- Attendance Form -->
    <div class="card" id="markForm">
        <div class="card-header">
            <h3><?= $editRecord ? 'Edit Attendance' : 'Mark Attendance' ?></h3>
        </div>
        <div class="card-body">
            <form method="POST" class="form">
                <input type="hidden" name="action" value="<?= $editRecord ? 'edit' : 'mark' ?>">
                <?php if ($editRecord): ?>
                <input type="hidden" name="id" value="<?= $editRecord['id'] ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label>Date *</label>
                        <?php if ($editRecord): ?>
                            <input type="date" value="<?= $editRecord['date'] ?>" disabled>
                            <input type="hidden" name="date" value="<?= $editRecord['date'] ?>">
                        <?php else: ?>
                            <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Status *</label>
                        <select name="status" required>
                            <option value="present" <?= ($editRecord['status'] ?? 'present') === 'present' ? 'selected' : '' ?>>Present</option>
                            <option value="absent" <?= ($editRecord['status'] ?? '') === 'absent' ? 'selected' : '' ?>>Absent</option>
                            <option value="late" <?= ($editRecord['status'] ?? '') === 'late' ? 'selected' : '' ?>>Late</option>
                            <option value="half_day" <?= ($editRecord['status'] ?? '') === 'half_day' ? 'selected' : '' ?>>Half Day</option>
                            <option value="on_leave" <?= ($editRecord['status'] ?? '') === 'on_leave' ? 'selected' : '' ?>>On Leave</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Time In</label>
                        <input type="time" name="time_in" value="<?= $editRecord['time_in'] ?? date('H:i') ?>">
                    </div>
                    <div class="form-group">
                        <label>Time Out</label>
                        <input type="time" name="time_out" value="<?= $editRecord['time_out'] ?? '' ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex:1">
                        <label>Notes</label>
                        <textarea name="notes" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px"><?= htmlspecialchars($editRecord['notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i data-feather="save"></i> <?= $editRecord ? 'Update Attendance' : 'Mark Attendance' ?>
                    </button>
                    <?php if ($editRecord): ?>
                    <a href="my-attendance.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Attendance History -->
    <div class="card mt-8">
        <div class="card-header">
            <h3><i data-feather="list"></i> Attendance History</h3>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attendanceRecords)): ?>
                        <tr><td colspan="6" style="text-align:center;padding:20px;color:#6b7280">No records yet</td></tr>
                        <?php else: ?>
                            <?php foreach ($attendanceRecords as $record): ?>
                            <tr>
                                <td><?= formatDate($record['date']) ?></td>
                                <td><span class="badge badge-<?= $record['status'] ?>"><?= ucfirst(str_replace('_', ' ', $record['status'])) ?></span></td>
                                <td><?= $record['time_in'] ? formatTime($record['time_in']) : '-' ?></td>
                                <td><?= $record['time_out'] ? formatTime($record['time_out']) : '-' ?></td>
                                <td><?= htmlspecialchars($record['notes'] ?? '') ?></td>
                                <td class="action-buttons">
                                    <a href="?edit=<?= $record['id'] ?>" class="btn btn-sm btn-secondary">
                                        <i data-feather="edit"></i>
                                    </a>
                                </td>
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
