<?php
$pageTitle = 'Audit Logs';
require_once __DIR__ . '/../auth.php';
requireRole('admin', '../index.php');
require_once __DIR__ . '/../header.php';

$db = getDB();
$filterAction = $_GET['action'] ?? '';
$filterModule = $_GET['module'] ?? '';
$filterDate = $_GET['date'] ?? date('Y-m-d');

// Get unique actions
$stmt = $db->prepare("SELECT DISTINCT action FROM audit_logs ORDER BY action");
$stmt->execute();
$actions = $stmt->fetchAll();

// Get unique modules
$stmt = $db->prepare("SELECT DISTINCT module FROM audit_logs ORDER BY module");
$stmt->execute();
$modules = $stmt->fetchAll();

// Get audit logs
$query = "
    SELECT al.*, u.name as user_name
    FROM audit_logs al
    LEFT JOIN users u ON u.id = al.user_id
    WHERE DATE(al.created_at) = ?
";
$params = [$filterDate];

if (!empty($filterAction)) {
    $query .= " AND al.action = ?";
    $params[] = $filterAction;
}

if (!empty($filterModule)) {
    $query .= " AND al.module = ?";
    $params[] = $filterModule;
}

$query .= " ORDER BY al.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="file-text"></i> Audit Logs</h1>
        <p>Track all system activities and changes</p>
    </div>
</div>

<div class="page-content">
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
                        <label>Action</label>
                        <select name="action" onchange="this.form.submit()">
                            <option value="">All Actions</option>
                            <?php foreach ($actions as $act): ?>
                            <option value="<?= htmlspecialchars($act['action']) ?>" <?= $filterAction === $act['action'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($act['action']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Module</label>
                        <select name="module" onchange="this.form.submit()">
                            <option value="">All Modules</option>
                            <?php foreach ($modules as $mod): ?>
                            <option value="<?= htmlspecialchars($mod['module']) ?>" <?= $filterModule === $mod['module'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($mod['module']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="card">
        <div class="card-header">
            <h3><i data-feather="table"></i> Logs (<?= count($logs) ?> records)</h3>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Module</th>
                            <th>Description</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><small><?= date('H:i:s', strtotime($log['created_at'])) ?></small></td>
                            <td><?= htmlspecialchars($log['user_name'] ?? 'System') ?></td>
                            <td><span class="badge" style="background:#e0e7ff;color:#3730a3"><?= htmlspecialchars($log['action']) ?></span></td>
                            <td><?= htmlspecialchars($log['module'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($log['description'] ?? '') ?></td>
                            <td><small><?= htmlspecialchars($log['ip_address'] ?? '-') ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
