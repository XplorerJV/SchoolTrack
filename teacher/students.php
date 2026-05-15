<?php
$pageTitle = 'Students';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../header.php';

requireRole('teacher', '../index.php');

$db = getDB();
$filterClass = $_GET['class'] ?? '';

// Get unique classes
$stmt = $db->prepare("SELECT DISTINCT class FROM students WHERE is_active = 1 ORDER BY class");
$stmt->execute();
$classes = $stmt->fetchAll();

// Get students
$query = "SELECT * FROM students WHERE is_active = 1";
$params = [];

if (!empty($filterClass)) {
    $query .= " AND class = ?";
    $params[] = $filterClass;
}

$query .= " ORDER BY class, roll_number";

$stmt = $db->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get student details for modal
$viewStudent = null;
if (isset($_GET['view'])) {
    $id = (int)$_GET['view'];
    $stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$id]);
    $viewStudent = $stmt->fetch();
    
    // Get student's attendance percentage (last 30 days)
    if ($viewStudent) {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
            FROM student_attendance
            WHERE student_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$viewStudent['id']]);
        $attendance = $stmt->fetch();
        $viewStudent['attendance_percentage'] = $attendance['total'] > 0 ? round(($attendance['present'] / $attendance['total']) * 100, 1) : 0;
    }
}
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="users"></i> Students</h1>
        <p>View your assigned students and their details</p>
    </div>
</div>

<div class="page-content">
    <!-- Filters -->
    <div class="card mb-6">
        <div class="card-body">
            <form method="GET" class="form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Filter by Class</label>
                        <select name="class" onchange="this.form.submit()">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $cls): ?>
                            <option value="<?= htmlspecialchars($cls['class']) ?>" <?= $filterClass === $cls['class'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cls['class']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Students List -->
    <div class="card">
        <div class="card-header">
            <h3><i data-feather="list"></i> All Students (<?= count($students) ?>)</h3>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Roll No</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Section</th>
                            <th>Card UID</th>
                            <th>Parent Contact</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                        <tr><td colspan="7" style="text-align:center;padding:20px;color:#6b7280">No students found</td></tr>
                        <?php else: ?>
                            <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?= htmlspecialchars($student['roll_number']) ?></td>
                                <td><?= htmlspecialchars($student['name']) ?></td>
                                <td><?= htmlspecialchars($student['class']) ?></td>
                                <td><?= htmlspecialchars($student['section'] ?? '-') ?></td>
                                <td><code><?= htmlspecialchars($student['card_uid'] ?? '-') ?></code></td>
                                <td>
                                    <small><?= htmlspecialchars($student['parent_phone'] ?? '-') ?></small>
                                </td>
                                <td class="action-buttons">
                                    <button class="btn btn-sm btn-secondary" onclick="viewStudent(<?= $student['id'] ?>)">
                                        <i data-feather="eye"></i>
                                    </button>
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

<!-- View Student Modal -->
<div id="viewModal" style="<?= $viewStudent ? 'display:flex' : 'display:none' ?>;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:white;border-radius:12px;padding:30px;max-width:500px;width:90%;max-height:80vh;overflow-y:auto">
        <?php if ($viewStudent): ?>
            <h3><?= htmlspecialchars($viewStudent['name']) ?></h3>
            <div style="margin-top:20px;padding:20px;background:#f9fafb;border-radius:8px">
                <p style="margin:0 0 10px"><strong>Roll Number:</strong> <?= htmlspecialchars($viewStudent['roll_number']) ?></p>
                <p style="margin:0 0 10px"><strong>Class:</strong> <?= htmlspecialchars($viewStudent['class']) ?></p>
                <p style="margin:0 0 10px"><strong>Section:</strong> <?= htmlspecialchars($viewStudent['section'] ?? '-') ?></p>
                <p style="margin:0 0 10px"><strong>Card UID:</strong> <code><?= htmlspecialchars($viewStudent['card_uid'] ?? '-') ?></code></p>
                <p style="margin:0 0 10px"><strong>Attendance (30d):</strong> <span class="badge" style="background:#d1fae5;color:#059669"><?= $viewStudent['attendance_percentage'] ?>%</span></p>
                <p style="margin:0 0 10px"><strong>Parent Email:</strong> <?= htmlspecialchars($viewStudent['parent_email'] ?? '-') ?></p>
                <p style="margin:0"><strong>Parent Phone:</strong> <?= htmlspecialchars($viewStudent['parent_phone'] ?? '-') ?></p>
            </div>
        <?php endif; ?>
        <div style="margin-top:20px;display:flex;gap:10px">
            <button type="button" class="btn btn-secondary" onclick="closeModal()" style="flex:1">Close</button>
        </div>
    </div>
</div>

<script>
function viewStudent(id) {
    window.location.href = '?view=' + id;
}
function closeModal() {
    window.location.href = '?';
}
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
