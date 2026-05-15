<?php
$pageTitle = 'Manage Principals';
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
        $photoUrl = null;
        if (!empty($_FILES['photo']['tmp_name'])) {
            $photoUrl = saveUploadedFile($_FILES['photo']);
            if (!$photoUrl) {
                $error = 'Invalid principal photo upload.';
            }
        }

        if (empty($error) && (empty($name) || empty($email) || empty($password) || empty($empId))) {
            $error = 'Please fill in all required fields.';
        }

        if (empty($error)) {
            try {
                $hashedPwd = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (name, email, password, role, employee_id, phone, photo) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$name, $email, $hashedPwd, 'principal', $empId, $phone, $photoUrl]);
                $success = 'Principal added successfully!';
                auditLog($_SESSION['user_id'], 'CREATE', 'principals', "Added principal: $name");
            } catch (Exception $e) {
                $error = 'Error: ' . (strpos($e->getMessage(), 'Duplicate') ? 'Email or Employee ID already exists.' : $e->getMessage());
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $isActive = (int)($_POST['is_active'] ?? 1);
        $photoUrl = null;
        if (!empty($_FILES['photo']['tmp_name'])) {
            $photoUrl = saveUploadedFile($_FILES['photo']);
            if (!$photoUrl) {
                $error = 'Invalid principal photo upload.';
            }
        }

        if (empty($error) && (empty($name) || $id <= 0)) {
            $error = 'Invalid data.';
        }

        if (empty($error)) {
            try {
                $updateQuery = "UPDATE users SET name=?, phone=?, is_active=?";
                $params = [$name, $phone, $isActive];
                if ($photoUrl) {
                    $updateQuery .= ", photo=?";
                    $params[] = $photoUrl;
                }
                $updateQuery .= " WHERE id=?";
                $params[] = $id;
                $stmt = $db->prepare($updateQuery);
                $stmt->execute($params);
                $success = 'Principal updated successfully!';
                auditLog($_SESSION['user_id'], 'UPDATE', 'principals', "Updated principal ID: $id");
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND role = 'principal'");
                $stmt->execute([$id]);
                $success = 'Principal deactivated successfully!';
                auditLog($_SESSION['user_id'], 'DELETE', 'principals', "Deactivated principal ID: $id");
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// Get principals
$stmt = $db->prepare("SELECT * FROM users WHERE role = 'principal' AND is_active = 1 ORDER BY name");
$stmt->execute();
$principals = $stmt->fetchAll();

$editPrincipal = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'principal'");
    $stmt->execute([$id]);
    $editPrincipal = $stmt->fetch();
}
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="user-check"></i> Manage Principals</h1>
        <p>Add, edit, and manage principal accounts</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('addPrincipalForm').scrollIntoView({behavior:'smooth'})">
        <i data-feather="plus"></i> Add Principal
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

    <div class="card" id="addPrincipalForm">
        <div class="card-header">
            <h3><?= $editPrincipal ? 'Edit Principal' : 'Add New Principal' ?></h3>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" class="form">
                <input type="hidden" name="action" value="<?= $editPrincipal ? 'edit' : 'add' ?>">
                <?php if ($editPrincipal): ?>
                <input type="hidden" name="id" value="<?= $editPrincipal['id'] ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($editPrincipal['name'] ?? '') ?>" required>
                    </div>
                    <?php if (!$editPrincipal): ?>
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
                        <input type="tel" name="phone" value="<?= htmlspecialchars($editPrincipal['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Photo</label>
                        <input type="file" name="photo" accept="image/png,image/jpeg,image/svg+xml" class="form-input">
                        <?php if (!empty($editPrincipal['photo'])): ?>
                        <div style="margin-top:12px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                            <img src="<?= htmlspecialchars($editPrincipal['photo']) ?>" alt="Principal photo" style="max-height:60px;border-radius:9999px;border:1px solid #e2e8f0;">
                            <span style="color:#475569;font-size:13px;">Current photo</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($editPrincipal): ?>
                    <div class="form-group">
                        <label>Active</label>
                        <select name="is_active">
                            <option value="1" <?= $editPrincipal['is_active'] ? 'selected' : '' ?>>Yes</option>
                            <option value="0" <?= !$editPrincipal['is_active'] ? 'selected' : '' ?>>No</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i data-feather="save"></i> <?= $editPrincipal ? 'Update Principal' : 'Add Principal' ?>
                    </button>
                    <?php if ($editPrincipal): ?>
                    <a href="principals.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-8">
        <div class="card-header">
            <h3><i data-feather="list"></i> All Principals (<?= count($principals) ?>)</h3>
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
                            <th>Phone</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($principals as $principal): ?>
                        <tr>
                            <td>
                                <?php if (!empty($principal['photo'])): ?>
                                <img src="<?= htmlspecialchars($principal['photo']) ?>" alt="<?= htmlspecialchars($principal['name']) ?>" style="width:42px;height:42px;border-radius:9999px;object-fit:cover;border:1px solid #e2e8f0;">
                                <?php else: ?>
                                <div style="width:42px;height:42px;border-radius:9999px;background:#e2e8f0;display:inline-flex;align-items:center;justify-content:center;color:#64748b;font-size:12px;">N/A</div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($principal['name']) ?></td>
                            <td><?= htmlspecialchars($principal['email']) ?></td>
                            <td><?= htmlspecialchars($principal['employee_id']) ?></td>
                            <td><?= htmlspecialchars($principal['phone'] ?? '-') ?></td>
                            <td class="action-buttons">
                                <a href="?edit=<?= $principal['id'] ?>" class="btn btn-sm btn-secondary">
                                    <i data-feather="edit"></i>
                                </a>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $principal['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" data-confirm="Deactivate this principal?">
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
