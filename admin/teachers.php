<?php
$pageTitle = 'Manage Teachers';
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
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $empId = trim($_POST['employee_id'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $photoUrl = null;
        if (!empty($_FILES['photo']['tmp_name'])) {
            $photoUrl = saveUploadedFile($_FILES['photo']);
            if (!$photoUrl) {
                $error = 'Invalid teacher photo upload.';
            }
        }
        
        if (empty($error) && (empty($name) || empty($email) || empty($password) || empty($empId))) {
            $error = 'Please fill in all required fields.';
        }
        
        if (empty($error)) {
            try {
                $hashedPwd = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (name, email, password, role, employee_id, phone, subject, photo) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute([$name, $email, $hashedPwd, 'teacher', $empId, $phone, $subject, $photoUrl]);
                $success = 'Teacher added successfully!';
                auditLog($_SESSION['user_id'], 'CREATE', 'teachers', "Added teacher: $name");
            } catch (Exception $e) {
                $error = 'Error: ' . (strpos($e->getMessage(), 'Duplicate') ? 'Email or Employee ID already exists.' : $e->getMessage());
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $isActive = (int)($_POST['is_active'] ?? 1);
        $photoUrl = null;
        if (!empty($_FILES['photo']['tmp_name'])) {
            $photoUrl = saveUploadedFile($_FILES['photo']);
            if (!$photoUrl) {
                $error = 'Invalid teacher photo upload.';
            }
        }

        if (empty($error) && (empty($name) || $id <= 0)) {
            $error = 'Invalid data.';
        }

        if (empty($error)) {
            try {
                $updateQuery = "UPDATE users SET name=?, phone=?, subject=?, is_active=?";
                $params = [$name, $phone, $subject, $isActive];
                if ($photoUrl) {
                    $updateQuery .= ", photo=?";
                    $params[] = $photoUrl;
                }
                $updateQuery .= " WHERE id=?";
                $params[] = $id;
                $stmt = $db->prepare($updateQuery);
                $stmt->execute($params);
                $success = 'Teacher updated successfully!';
                auditLog($_SESSION['user_id'], 'UPDATE', 'teachers', "Updated teacher ID: $id");
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND role = 'teacher'");
                $stmt->execute([$id]);
                $success = 'Teacher deactivated successfully!';
                auditLog($_SESSION['user_id'], 'DELETE', 'teachers', "Deactivated teacher ID: $id");
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// Get all teachers
$stmt = $db->prepare("SELECT * FROM users WHERE role = 'teacher' AND is_active = 1 ORDER BY name");
$stmt->execute();
$teachers = $stmt->fetchAll();

// Get teacher for editing
$editTeacher = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'teacher'");
    $stmt->execute([$id]);
    $editTeacher = $stmt->fetch();
}
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="briefcase"></i> Manage Teachers</h1>
        <p>Add, edit, and manage teacher accounts</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('addTeacherForm').scrollIntoView({behavior:'smooth'})">
        <i data-feather="plus"></i> Add Teacher
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
    <div class="card" id="addTeacherForm">
        <div class="card-header">
            <h3><?= $editTeacher ? 'Edit Teacher' : 'Add New Teacher' ?></h3>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" class="form">
                <input type="hidden" name="action" value="<?= $editTeacher ? 'edit' : 'add' ?>">
                <?php if ($editTeacher): ?>
                <input type="hidden" name="id" value="<?= $editTeacher['id'] ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($editTeacher['name'] ?? '') ?>" required>
                    </div>
                    <?php if (!$editTeacher): ?>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Password *</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label>Employee ID *</label>
                        <input type="text" name="employee_id" required>
                    </div>
                    <?php endif; ?>
                </div>

                    <div class="form-row">
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($editTeacher['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Subject</label>
                        <input type="text" name="subject" value="<?= htmlspecialchars($editTeacher['subject'] ?? '') ?>" placeholder="e.g., Mathematics">
                    </div>
                    <div class="form-group">
                        <label>Photo</label>
                        <input type="file" name="photo" accept="image/png,image/jpeg,image/svg+xml" class="form-input">
                        <?php if (!empty($editTeacher['photo'])): ?>
                        <div style="margin-top:12px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                            <img src="<?= htmlspecialchars($editTeacher['photo']) ?>" alt="Teacher photo" style="max-height:60px;border-radius:9999px;border:1px solid #e2e8f0;">
                            <span style="color:#475569;font-size:13px;">Current photo</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($editTeacher): ?>
                    <div class="form-group">
                        <label>Active</label>
                        <select name="is_active">
                            <option value="1" <?= $editTeacher['is_active'] ? 'selected' : '' ?>>Yes</option>
                            <option value="0" <?= !$editTeacher['is_active'] ? 'selected' : '' ?>>No</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i data-feather="save"></i> <?= $editTeacher ? 'Update Teacher' : 'Add Teacher' ?>
                    </button>
                    <?php if ($editTeacher): ?>
                    <a href="teachers.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Teachers List -->
    <div class="card mt-8">
        <div class="card-header">
            <h3><i data-feather="list"></i> All Teachers (<?= count($teachers) ?>)</h3>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Employee ID</th>
                            <th>Subject</th>
                            <th>Phone</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teachers as $teacher): ?>
                        <tr>
                            <td>
                                <?php if (!empty($teacher['photo'])): ?>
                                <img src="<?= htmlspecialchars($teacher['photo']) ?>" alt="<?= htmlspecialchars($teacher['name']) ?>" style="width:42px;height:42px;border-radius:9999px;object-fit:cover;border:1px solid #e2e8f0;">
                                <?php else: ?>
                                <div style="width:42px;height:42px;border-radius:9999px;background:#e2e8f0;display:inline-flex;align-items:center;justify-content:center;color:#64748b;font-size:12px;">N/A</div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($teacher['name']) ?></td>
                            <td><?= htmlspecialchars($teacher['email']) ?></td>
                            <td><?= htmlspecialchars($teacher['employee_id']) ?></td>
                            <td><?= htmlspecialchars($teacher['subject'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($teacher['phone'] ?? '-') ?></td>
                            <td class="action-buttons">
                                <a href="?edit=<?= $teacher['id'] ?>" class="btn btn-sm btn-secondary">
                                    <i data-feather="edit"></i>
                                </a>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $teacher['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" data-confirm="Deactivate this teacher?">
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
