<?php
$pageTitle = 'Manage Students';
require_once __DIR__ . '/../auth.php';
requireRole('admin', '../index.php');
require_once __DIR__ . '/../header.php';

$db = getDB();
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name       = trim($_POST['name'] ?? '');
        $rollNo     = trim($_POST['roll_number'] ?? '');
        $class      = trim($_POST['class'] ?? '');
        $section    = trim($_POST['section'] ?? '');
        $gender     = trim($_POST['gender'] ?? 'male');
        $dob        = trim($_POST['date_of_birth'] ?? '');
        $address    = trim($_POST['address'] ?? '');
        $contact    = trim($_POST['contact'] ?? '');
        $cardUid    = trim($_POST['card_uid'] ?? '');
        $parentEmail= trim($_POST['parent_email'] ?? '');
        $parentPhone= trim($_POST['parent_phone'] ?? '');

        if (empty($name) || empty($rollNo) || empty($class)) {
            $error = 'Name, Roll Number and Class are required.';
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO students (name,roll_number,class,section,gender,date_of_birth,address,contact,card_uid,parent_email,parent_phone) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$name,$rollNo,$class,$section,$gender,$dob?:null,$address,$contact,$cardUid,$parentEmail,$parentPhone]);
                $success = "Student '$name' added successfully!";
                auditLog($_SESSION['user_id'], 'CREATE', 'students', "Added student: $name");
            } catch (Exception $e) {
                $error = strpos($e->getMessage(),'Duplicate') ? 'Roll number or Card UID already exists.' : $e->getMessage();
            }
        }
    } elseif ($action === 'edit') {
        $id         = (int)($_POST['id'] ?? 0);
        $name       = trim($_POST['name'] ?? '');
        $class      = trim($_POST['class'] ?? '');
        $section    = trim($_POST['section'] ?? '');
        $gender     = trim($_POST['gender'] ?? 'male');
        $dob        = trim($_POST['date_of_birth'] ?? '');
        $address    = trim($_POST['address'] ?? '');
        $contact    = trim($_POST['contact'] ?? '');
        $cardUid    = trim($_POST['card_uid'] ?? '');
        $parentEmail= trim($_POST['parent_email'] ?? '');
        $parentPhone= trim($_POST['parent_phone'] ?? '');
        $isActive   = (int)($_POST['is_active'] ?? 1);

        if (empty($name) || empty($class) || $id <= 0) {
            $error = 'Invalid data.';
        } else {
            try {
                $stmt = $db->prepare("UPDATE students SET name=?,class=?,section=?,gender=?,date_of_birth=?,address=?,contact=?,card_uid=?,parent_email=?,parent_phone=?,is_active=? WHERE id=?");
                $stmt->execute([$name,$class,$section,$gender,$dob?:null,$address,$contact,$cardUid,$parentEmail,$parentPhone,$isActive,$id]);
                $success = 'Student updated successfully!';
                auditLog($_SESSION['user_id'], 'UPDATE', 'students', "Updated student ID: $id");
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("UPDATE students SET is_active=0 WHERE id=?")->execute([$id]);
            $success = 'Student deactivated.';
            auditLog($_SESSION['user_id'], 'DELETE', 'students', "Deactivated student ID: $id");
        }
    }
}

// Filters
$filterClass = $_GET['class'] ?? '';
$search      = trim($_GET['search'] ?? '');

$query  = "SELECT * FROM students WHERE is_active=1";
$params = [];
if ($filterClass) { $query .= " AND class=?"; $params[] = $filterClass; }
if ($search)      { $query .= " AND (name LIKE ? OR roll_number LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$query .= " ORDER BY CAST(class AS UNSIGNED), roll_number";

$stmt = $db->prepare($query); $stmt->execute($params);
$students = $stmt->fetchAll();

$classes = $db->query("SELECT DISTINCT class FROM students WHERE is_active=1 ORDER BY CAST(class AS UNSIGNED)")->fetchAll();

$editStudent = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM students WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editStudent = $stmt->fetch();
}
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="users"></i> Manage Students</h1>
        <p>Total: <?= count($students) ?> students</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('studentForm').scrollIntoView({behavior:'smooth'})">
        <i data-feather="plus"></i> Add Student
    </button>
</div>

<div class="page-content">
    <?php if ($error): ?><div class="alert alert-danger"><i data-feather="alert-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><i data-feather="check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <!-- Add/Edit Form -->
    <div class="card" id="studentForm">
        <div class="card-header"><h3><?= $editStudent ? 'Edit Student' : 'Add New Student' ?></h3></div>
        <div class="card-body">
            <form method="POST" class="form">
                <input type="hidden" name="action" value="<?= $editStudent ? 'edit' : 'add' ?>">
                <?php if ($editStudent): ?><input type="hidden" name="id" value="<?= $editStudent['id'] ?>"><?php endif; ?>

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
                        <select name="class" required>
                            <option value="">Select Class</option>
                            <?php for($c=1;$c<=10;$c++): ?>
                            <option value="<?=$c?>" <?= ($editStudent['class']??'')==$c?'selected':'' ?>>Class <?=$c?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Section</label>
                        <select name="section">
                            <option value="">Select</option>
                            <option value="A" <?= ($editStudent['section']??'')==='A'?'selected':'' ?>>A</option>
                            <option value="B" <?= ($editStudent['section']??'')==='B'?'selected':'' ?>>B</option>
                            <option value="C" <?= ($editStudent['section']??'')==='C'?'selected':'' ?>>C</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender">
                            <option value="male"   <?= ($editStudent['gender']??'')==='male'?'selected':'' ?>>Male</option>
                            <option value="female" <?= ($editStudent['gender']??'')==='female'?'selected':'' ?>>Female</option>
                            <option value="other"  <?= ($editStudent['gender']??'')==='other'?'selected':'' ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth" value="<?= htmlspecialchars($editStudent['date_of_birth'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="tel" name="contact" value="<?= htmlspecialchars($editStudent['contact'] ?? '') ?>" placeholder="Student contact">
                    </div>
                    <div class="form-group">
                        <label>RFID Card UID</label>
                        <input type="text" name="card_uid" value="<?= htmlspecialchars($editStudent['card_uid'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex:2">
                        <label>Address</label>
                        <input type="text" name="address" value="<?= htmlspecialchars($editStudent['address'] ?? '') ?>" placeholder="Full address">
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

                <?php if ($editStudent): ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="is_active">
                            <option value="1" <?= $editStudent['is_active']?'selected':'' ?>>Active</option>
                            <option value="0" <?= !$editStudent['is_active']?'selected':'' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success"><i data-feather="save"></i> <?= $editStudent ? 'Update' : 'Add Student' ?></button>
                    <?php if ($editStudent): ?><a href="students.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <div class="card">
        <div class="card-body">
            <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
                <div class="form-group" style="min-width:160px">
                    <label>Filter by Class</label>
                    <select name="class" onchange="this.form.submit()">
                        <option value="">All Classes</option>
                        <?php foreach($classes as $c): ?>
                        <option value="<?= $c['class'] ?>" <?= $filterClass==$c['class']?'selected':'' ?>>Class <?= $c['class'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="min-width:220px">
                    <label>Search</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Name or Roll No">
                </div>
                <?php if ($filterClass): ?><input type="hidden" name="class" value="<?= htmlspecialchars($filterClass) ?>"><?php endif; ?>
                <button type="submit" class="btn btn-primary"><i data-feather="search"></i> Search</button>
                <a href="students.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>
    </div>

    <!-- Students Table -->
    <div class="card">
        <div class="card-header">
            <h3><i data-feather="list"></i> Students (<?= count($students) ?>)</h3>
        </div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Roll No</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Section</th>
                            <th>Gender</th>
                            <th>DOB</th>
                            <th>Contact</th>
                            <th>Address</th>
                            <th>Parent Phone</th>
                            <th>Card UID</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                        <tr><td colspan="11" style="text-align:center;padding:30px;color:#6b7280">No students found</td></tr>
                        <?php else: ?>
                        <?php foreach ($students as $s): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['roll_number']) ?></strong></td>
                            <td><?= htmlspecialchars($s['name']) ?></td>
                            <td><span class="badge" style="background:#dbeafe;color:#1e40af">Class <?= htmlspecialchars($s['class']) ?></span></td>
                            <td><?= htmlspecialchars($s['section'] ?? '-') ?></td>
                            <td><?= ucfirst($s['gender'] ?? '-') ?></td>
                            <td><?= $s['date_of_birth'] ? date('d M Y', strtotime($s['date_of_birth'])) : '-' ?></td>
                            <td><?= htmlspecialchars($s['contact'] ?? '-') ?></td>
                            <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($s['address'] ?? '') ?>"><?= htmlspecialchars($s['address'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($s['parent_phone'] ?? '-') ?></td>
                            <td><code style="font-size:11px"><?= htmlspecialchars($s['card_uid'] ?? '-') ?></code></td>
                            <td class="action-buttons">
                                <a href="?edit=<?= $s['id'] ?>" class="btn btn-sm btn-secondary"><i data-feather="edit"></i></a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Deactivate this student?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i data-feather="trash-2"></i></button>
                                </form>
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
