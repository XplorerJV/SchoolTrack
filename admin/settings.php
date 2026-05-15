<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/../auth.php';
requireRole('admin', '../index.php');
require_once __DIR__ . '/../header.php';

$db = getDB();
$error = $success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'school_name'        => $_POST['school_name'] ?? '',
        'school_address'     => $_POST['school_address'] ?? '',
        'cutoff_time'        => $_POST['cutoff_time'] ?? '09:30:00',
        'late_time'          => $_POST['late_time'] ?? '08:15:00',
        'school_start_time'  => $_POST['school_start_time'] ?? '07:30:00',
        'academic_year'      => $_POST['academic_year'] ?? '',
        'smtp_host'          => $_POST['smtp_host'] ?? '',
        'smtp_port'          => $_POST['smtp_port'] ?? '587',
        'smtp_username'      => $_POST['smtp_username'] ?? '',
        'smtp_from_name'     => $_POST['smtp_from_name'] ?? 'School Attendance',
        'smtp_encryption'    => $_POST['smtp_encryption'] ?? 'tls',
        'email_api_url'      => $_POST['email_api_url'] ?? '',
        'email_api_key'      => $_POST['email_api_key'] ?? '',
        'email_api_header'   => $_POST['email_api_header'] ?? 'Authorization',
        'email_notifications'=> $_POST['email_notifications'] ?? '0',
    ];
    // Only update password if a new one was entered
    if (!empty($_POST['smtp_password'])) {
        $settings['smtp_password'] = $_POST['smtp_password'];
    }
    
    try {
        foreach ($settings as $key => $value) {
            setSetting($key, $value);
        }

        if (!empty($_FILES['school_logo']['tmp_name'])) {
            $logoUrl = saveUploadedFile($_FILES['school_logo']);
            if ($logoUrl) {
                setSetting('school_logo', $logoUrl);
            } else {
                throw new Exception('Invalid school logo upload.');
            }
        }

        $success = 'Settings saved successfully!';
        auditLog($_SESSION['user_id'], 'UPDATE', 'settings', 'Updated system settings');
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get current settings
$settingKeys = ['school_name', 'school_address', 'cutoff_time', 'late_time', 'school_start_time', 'academic_year', 
                'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_from_name', 
                'smtp_encryption', 'email_api_url', 'email_api_key', 'email_api_header', 'email_notifications'];

$currentSettings = [];
foreach ($settingKeys as $key) {
    $currentSettings[$key] = getSetting($key);
}
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="settings"></i> Settings</h1>
        <p>Configure system settings and email notifications</p>
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

    <form method="POST" enctype="multipart/form-data" class="form">
        <!-- General Settings -->
        <div class="card mb-6">
            <div class="card-header">
                <h3><i data-feather="info"></i> General Settings</h3>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>School Name *</label>
                        <input type="text" name="school_name" value="<?= htmlspecialchars($currentSettings['school_name'] ?? '') ?>" required class="form-input">
                    </div>
                    <div class="form-group">
                        <label>Academic Year</label>
                        <input type="text" name="academic_year" value="<?= htmlspecialchars($currentSettings['academic_year'] ?? '') ?>" placeholder="e.g., 2025-2026" class="form-input">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>School Address</label>
                        <textarea name="school_address" class="form-input" rows="3"><?= htmlspecialchars($currentSettings['school_address'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>School Logo</label>
                        <input type="file" name="school_logo" accept="image/png,image/jpeg,image/svg+xml" class="form-input">
                        <?php if (!empty($currentSettings['school_logo'])): ?>
                        <div style="margin-top:12px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                            <img src="<?= htmlspecialchars($currentSettings['school_logo']) ?>" alt="Current school logo" style="max-height:60px;border-radius:12px;border:1px solid #e2e8f0;">
                            <span style="color:#475569;font-size:13px;">Current logo</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

        <!-- Attendance Settings -->
        <div class="card mb-6">
            <div class="card-header">
                <h3><i data-feather="clock"></i> Attendance Times</h3>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>School Start Time</label>
                        <input type="time" name="school_start_time" value="<?= $currentSettings['school_start_time'] ?? '07:30' ?>">
                        <small>Time when school opens</small>
                    </div>
                    <div class="form-group">
                        <label>Late Time Cutoff</label>
                        <input type="time" name="late_time" value="<?= $currentSettings['late_time'] ?? '08:15' ?>">
                        <small>Students marked as late after this time</small>
                    </div>
                    <div class="form-group">
                        <label>Absence Cutoff Time</label>
                        <input type="time" name="cutoff_time" value="<?= $currentSettings['cutoff_time'] ?? '09:30' ?>">
                        <small>Students marked absent if not marked by this time</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Email/SMTP Settings -->
        <div class="card mb-6">
            <div class="card-header">
                <h3><i data-feather="mail"></i> Email Notifications</h3>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Enable Email Notifications</label>
                        <select name="email_notifications">
                            <option value="1" <?= $currentSettings['email_notifications'] ? 'selected' : '' ?>>Yes</option>
                            <option value="0" <?= !$currentSettings['email_notifications'] ? 'selected' : '' ?>>No</option>
                        </select>
                    </div>
                </div>

                <hr style="margin:20px 0;border:none;border-top:1px solid #e5e7eb">

                <div class="form-row">
                    <div class="form-group">
                        <label>SMTP Host</label>
                        <input type="text" name="smtp_host" value="<?= htmlspecialchars($currentSettings['smtp_host'] ?? '') ?>" placeholder="e.g., smtp.gmail.com">
                    </div>
                    <div class="form-group">
                        <label>SMTP Port</label>
                        <input type="number" name="smtp_port" value="<?= htmlspecialchars($currentSettings['smtp_port'] ?? '587') ?>">
                    </div>
                    <div class="form-group">
                        <label>Encryption Type</label>
                        <select name="smtp_encryption">
                            <option value="tls" <?= ($currentSettings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= ($currentSettings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            <option value="none" <?= ($currentSettings['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>SMTP Username/Email</label>
                        <input type="text" name="smtp_username" value="<?= htmlspecialchars($currentSettings['smtp_username'] ?? '') ?>" placeholder="e.g., school@gmail.com">
                    </div>
                    <div class="form-group">
                        <label>SMTP Password</label>
                        <input type="password" name="smtp_password" placeholder="Leave empty to keep current password">
                    </div>
                    <div class="form-group">
                        <label>From Name</label>
                        <input type="text" name="smtp_from_name" value="<?= htmlspecialchars($currentSettings['smtp_from_name'] ?? 'School Attendance') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>External Email API URL</label>
                        <input type="text" name="email_api_url" value="<?= htmlspecialchars($currentSettings['email_api_url'] ?? '') ?>" placeholder="https://api.example.com/send_email">
                    </div>
                    <div class="form-group">
                        <label>External Email API Key</label>
                        <input type="text" name="email_api_key" value="<?= htmlspecialchars($currentSettings['email_api_key'] ?? '') ?>" placeholder="MY_SECRET_KEY_123">
                    </div>
                    <div class="form-group">
                        <label>API Authorization Header</label>
                        <input type="text" name="email_api_header" value="<?= htmlspecialchars($currentSettings['email_api_header'] ?? 'Authorization') ?>" placeholder="Authorization">
                    </div>
                </div>

                <div style="background:#f3f4f6;padding:15px;border-radius:8px;margin-top:15px">
                    <p style="margin:0;font-size:13px;color:#6b7280">
                        <strong>Example for Gmail SMTP:</strong><br>
                        Host: smtp.gmail.com<br>
                        Port: 587<br>
                        Encryption: TLS<br>
                        Username: your-email@gmail.com<br>
                        Password: app-specific-password
                    </p>
                    <p style="margin:10px 0 0;font-size:13px;color:#6b7280">
                        <strong>Example for external API:</strong><br>
                        URL: https://email.indiegrampublications.com/send_email.php<br>
                        Header: Authorization<br>
                        Key: MY_SECRET_KEY_123
                    </p>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="card">
            <div class="card-body">
                <button type="submit" class="btn btn-success">
                    <i data-feather="save"></i> Save Settings
                </button>
            </div>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
