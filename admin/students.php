<?php
$pageTitle = 'Manage Students';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../header.php';

requireRole('admin', '../index.php');

$db = getDB();
$error = $success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $rollNo = trim($_POST['roll_number'] ?? '');
        $class = trim($_POST['class'] ?? '');
        $section = trim($_POST['section'] ?? '');
        $cardUid = trim($_POST['card_uid'] ?? '');
        $parentEmail = trim($_POST['parent_email'] ?? '');
        $parentPhone = trim($_POST['parent_phone'] ?? '');
        $photoUrl = null;
        if (!empty($_FILES['photo']['tmp_name'])) {
            $photoUrl = saveUploadedFile($_FILES['photo']);
            if (!$photoUrl) {
                $error = 'Invalid student photo upload.';
            }
        }
        
        if (empty($error) && (empty($name) || empty($rollNo) || empty($class))) {
            $error = 'Please fill in all required fields.';
        }
        
        if (empty($error)) {
            try {
                $stmt = $db->prepare("INSERT INTO students (name, roll_number, class, section, card_uid, parent_email, parent_phone, photo) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute([$name, $rollNo, $class, $section, $cardUid, $parentEmail, $parentPhone, $photoUrl]);
                $success = 'Student added successfully!';
                auditLog($_SESSION['user_id'], 'CREATE', 'students', "Added student: $name");
            } catch (Exception $e) {
                $error = 'Error: ' . (strpos($e->getMessage(), 'Duplicate') ? 'Roll number already exists.' : $e->getMessage());
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $class = trim($_POST['class'] ?? '');
        $section = trim($_POST['section'] ?? '');
        $cardUid = trim($_POST['card_uid'] ?? '');
        $parentEmail = trim($_POST['parent_email'] ?? '');
        $parentPhone = trim($_POST['parent_phone'] ?? '');
        $isActive = (int)($_POST['is_active'] ?? 1);
        $photoUrl = null;
        if (!empty($_FILES['photo']['tmp_name'])) {
            $photoUrl = saveUploadedFile($_FILES['photo']);
            if (!$photoUrl) {
                $error = 'Invalid student photo upload.';
            }
        }
        
        if (empty($error) && (empty($name) || empty($class) || $id <= 0)) {
            $error = 'Invalid data.';
        }
        
        if (empty($error)) {
            try {
                $updateQuery = "UPDATE students SET name=?, class=?, section=?, card_uid=?, parent_email=?, parent_phone=?, is_active=?";
                $params = [$name, $class, $section, $cardUid, $parentEmail, $parentPhone, $isActive];
                if ($photoUrl) {
                    $updateQuery .= ", photo=?";
                    $params[] = $photoUrl;
                }
                $updateQuery .= " WHERE id=?";
                $params[] = $id;
                $stmt = $db->prepare($updateQuery);
                $stmt->execute($params);
                $success = 'Student updated successfully!';
                auditLog($_SESSION['user_id'], 'UPDATE', 'students', "Updated student ID: $id");
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $db->prepare("UPDATE students SET is_active = 0 WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'Student deactivated successfully!';
                auditLog($_SESSION['user_id'], 'DELETE', 'students', "Deactivated student ID: $id");
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// Get all students
$stmt = $db->prepare("SELECT * FROM students WHERE is_active = 1 ORDER BY class, roll_number");
$stmt->execute();
$students = $stmt->fetchAll();

// Get student for editing
$editStudent = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$id]);
    $editStudent = $stmt->fetch();
}
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="users"></i> Manage Students</h1>
        <p>Add, edit, and manage student records</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('addStudentForm').scrollIntoView({behavior:'smooth'})">
        <i data-feather="plus"></i> Add Student
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

    <!-- Form -->
    <div class="card" id="addStudentForm">
        <div class="card-header">
            <h3><?= $editStudent ? 'Edit Student' : 'Add New Student' ?></h3>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" class="form">
                <input type="hidden" name="action" value="<?= $editStudent ? 'edit' : 'add' ?>">
                <?php if ($editStudent): ?>
                <input type="hidden" name="id" value="<?= $editStudent['id'] ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label>Student Name *</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($editStudent['name'] ?? '') ?>" required>
                    </div>
                    <?php if (!$editStudent): ?>
                    <div class="form-group">
                        <label>Roll Number *</label>
                        <input type="text" name="roll_number" required>
                    </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label>Class *</label>
                        <input type="text" name="class" value="<?= htmlspecialchars($editStudent['class'] ?? '') ?>" placeholder="e.g., 10" required>
                    </div>
                    <div class="form-group">
                        <label>Section</label>
                        <input type="text" name="section" value="<?= htmlspecialchars($editStudent['section'] ?? '') ?>" placeholder="e.g., A">
                    </div>
                </div>

                    <div class="form-row">
                    <div class="form-group">
                        <label>RFID Card UID</label>
                        <input type="text" name="card_uid" value="<?= htmlspecialchars($editStudent['card_uid'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Parent Email</label>
                        <input type="email" name="parent_email" value="<?= htmlspecialchars($editStudent['parent_email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Parent Phone</label>
                        <input type="tel" name="parent_phone" value="<?= htmlspecialchars($editStudent['parent_phone'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Student Photo</label>
                        <input type="file" name="photo" accept="image/png,image/jpeg,image/svg+xml" class="form-input">
                        <?php if (!empty($editStudent['photo'])): ?>
                        <div style="margin-top:12px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                            <img src="<?= htmlspecialchars($editStudent['photo']) ?>" alt="Student photo" style="max-height:60px;border-radius:9999px;border:1px solid #e2e8f0;">
                            <span style="color:#475569;font-size:13px;">Current photo</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($editStudent): ?>
                    <div class="form-group">
                        <label>Active</label>
                        <select name="is_active">
                            <option value="1" <?= $editStudent['is_active'] ? 'selected' : '' ?>>Yes</option>
                            <option value="0" <?= !$editStudent['is_active'] ? 'selected' : '' ?>>No</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i data-feather="save"></i> <?= $editStudent ? 'Update Student' : 'Add Student' ?>
                    </button>
                    <?php if ($editStudent): ?>
                    <a href="students.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Students List -->
    <div class="card mt-8">
        <div class="card-header">
            <h3><i data-feather="list"></i> All Students (<?= count($students) ?>)</h3>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Roll No</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Section</th>
                            <th>Card UID</th>
                            <th>Parent Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <tr>
                            <td>
                                <?php if (!empty($student['photo'])): ?>
                                <img src="<?= htmlspecialchars($student['photo']) ?>" alt="<?= htmlspecialchars($student['name']) ?>" style="width:42px;height:42px;border-radius:9999px;object-fit:cover;border:1px solid #e2e8f0;">
                                <?php else: ?>
                                <div style="width:42px;height:42px;border-radius:9999px;background:#e2e8f0;display:inline-flex;align-items:center;justify-content:center;color:#64748b;font-size:12px;">N/A</div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($student['roll_number']) ?></td>
                            <td><?= htmlspecialchars($student['name']) ?></td>
                            <td><?= htmlspecialchars($student['class']) ?></td>
                            <td><?= htmlspecialchars($student['section'] ?? '-') ?></td>
                            <td><code><?= htmlspecialchars($student['card_uid'] ?? '-') ?></code></td>
                            <td><?= htmlspecialchars($student['parent_email'] ?? '-') ?></td>
                            <td class="action-buttons">
                                <a href="?edit=<?= $student['id'] ?>" class="btn btn-sm btn-secondary">
                                    <i data-feather="edit"></i>
                                </a>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $student['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" data-confirm="Deactivate this student?">
                                        <i data-feather="trash-2"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
