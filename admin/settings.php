<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/../auth.php';
requireRole('admin', '../index.php');
require_once __DIR__ . '/../header.php';

$db = getDB();
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'school_name'       => trim($_POST['school_name'] ?? ''),
        'school_address'    => trim($_POST['school_address'] ?? ''),
        'school_start_time' => $_POST['school_start_time'] ?? '07:30:00',
        'late_time'         => $_POST['late_time'] ?? '08:15:00',
        'cutoff_time'       => $_POST['cutoff_time'] ?? '09:30:00',
        'academic_year'     => trim($_POST['academic_year'] ?? ''),
    ];
    try {
        foreach ($settings as $key => $value) setSetting($key, $value);
        if (!empty($_FILES['school_logo']['tmp_name'])) {
            $logoUrl = saveUploadedFile($_FILES['school_logo']);
            if ($logoUrl) setSetting('school_logo', $logoUrl);
            else throw new Exception('Invalid logo file.');
        }
        $success = 'Settings saved successfully!';
        auditLog($_SESSION['user_id'], 'UPDATE', 'settings', 'Updated system settings');
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$s = [];
foreach (['school_name','school_address','school_start_time','late_time','cutoff_time','academic_year','school_logo'] as $k)
    $s[$k] = getSetting($k);
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="settings"></i> Settings</h1>
        <p>Configure school and attendance settings</p>
    </div>
</div>

<div class="page-content">
    <?php if ($error): ?><div class="alert alert-danger"><i data-feather="alert-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><i data-feather="check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="form">

        <!-- General -->
        <div class="card mb-6">
            <div class="card-header"><h3><i data-feather="info"></i> General Settings</h3></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>School Name *</label>
                        <input type="text" name="school_name" value="<?= htmlspecialchars($s['school_name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Academic Year</label>
                        <input type="text" name="academic_year" value="<?= htmlspecialchars($s['academic_year'] ?? '') ?>" placeholder="e.g., 2025-2026">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex:2">
                        <label>School Address</label>
                        <input type="text" name="school_address" value="<?= htmlspecialchars($s['school_address'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>School Logo</label>
                        <input type="file" name="school_logo" accept="image/png,image/jpeg,image/svg+xml">
                        <?php if (!empty($s['school_logo'])): ?>
                        <div style="margin-top:10px;display:flex;gap:10px;align-items:center">
                            <img src="<?= htmlspecialchars(mediaUrl($s['school_logo'])) ?>" style="height:50px;border-radius:8px;border:1px solid #e2e8f0">
                            <span style="font-size:13px;color:#64748b">Current logo</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Times -->
        <div class="card mb-6">
            <div class="card-header"><h3><i data-feather="clock"></i> Attendance Times</h3></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>School Start Time</label>
                        <input type="time" name="school_start_time" value="<?= substr($s['school_start_time'] ?? '07:30:00', 0, 5) ?>">
                        <small style="color:#64748b;font-size:12px">Time when school opens</small>
                    </div>
                    <div class="form-group">
                        <label>Late Arrival Cutoff</label>
                        <input type="time" name="late_time" value="<?= substr($s['late_time'] ?? '08:15:00', 0, 5) ?>">
                        <small style="color:#64748b;font-size:12px">Marked late after this time</small>
                    </div>
                    <div class="form-group">
                        <label>Absent Cutoff Time</label>
                        <input type="time" name="cutoff_time" value="<?= substr($s['cutoff_time'] ?? '09:30:00', 0, 5) ?>">
                        <small style="color:#64748b;font-size:12px">Marked absent if not present by this time</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <button type="submit" class="btn btn-success"><i data-feather="save"></i> Save Settings</button>
            </div>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
