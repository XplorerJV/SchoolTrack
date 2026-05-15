<?php
$pageTitle = 'Manage Attendance';
require_once __DIR__ . '/../auth.php';
requireRole('admin', '../index.php');
require_once __DIR__ . '/../header.php';

$db = getDB();
$error = $success = '';
$filterDate = $_GET['date'] ?? date('Y-m-d');
$filterClass = $_GET['class'] ?? '';
$filterStatus = $_GET['status'] ?? '';

// Handle form submission for attendance edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? 'present';
    $notes = trim($_POST['notes'] ?? '');
    
    if ($id > 0) {
        try {
            $stmt = $db->prepare("UPDATE student_attendance SET status = ?, notes = ? WHERE id = ?");
            $stmt->execute([$status, $notes, $id]);
            $success = 'Attendance updated successfully!';
            auditLog($_SESSION['user_id'], 'UPDATE', 'attendance', "Updated attendance ID: $id to $status");
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get unique classes
$stmt = $db->prepare("SELECT DISTINCT class FROM students WHERE is_active = 1 ORDER BY class");
$stmt->execute();
$classes = $stmt->fetchAll();

// Get attendance records
$query = "
    SELECT sa.*, s.name, s.roll_number, s.class, s.section
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
        <h1><i data-feather="check-square"></i> Manage Attendance</h1>
        <p>View and edit attendance records</p>
    </div>
</div>

<div class="page-content">
    <?php if ($error): ?>
    <div class="alert alert-danger"><i data-feather="alert-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success"><i data-feather="check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

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
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
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
                            <td><?= htmlspecialchars($record['notes'] ?? '') ?></td>
                            <td>
                                <button class="btn btn-sm btn-secondary" onclick="editAttendance(<?= $record['id'] ?>, '<?= $record['status'] ?>', '<?= htmlspecialchars(addslashes($record['notes'] ?? '')) ?>')">
                                    <i data-feather="edit"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Attendance Modal -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:white;border-radius:12px;padding:30px;max-width:500px;width:90%">
        <h3>Edit Attendance</h3>
        <form method="POST" style="margin-top:20px">
            <input type="hidden" name="id" id="recordId">
            <div class="form-group" style="margin-bottom:15px">
                <label>Status</label>
                <select name="status" id="statusSelect">
                    <option value="present">Present</option>
                    <option value="absent">Absent</option>
                    <option value="late">Late</option>
                    <option value="excused">Excused</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:15px">
                <label>Notes</label>
                <textarea name="notes" id="notesInput" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px"></textarea>
            </div>
            <div style="display:flex;gap:10px">
                <button type="submit" class="btn btn-success">Save</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function editAttendance(id, status, notes) {
    document.getElementById('recordId').value = id;
    document.getElementById('statusSelect').value = status;
    document.getElementById('notesInput').value = notes;
    document.getElementById('editModal').style.display = 'flex';
}
function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
