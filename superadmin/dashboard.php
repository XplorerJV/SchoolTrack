<?php
$pageTitle = 'Super Admin Dashboard';
require_once __DIR__ . '/../auth.php';
requireRole('superadmin', '../index.php');
require_once __DIR__ . '/../header.php';

$db = getDB();

// School profile — these come from the same `settings` table the admin edits in
// admin/settings.php, so any change made there shows up here automatically.
$schoolName    = getSetting('school_name') ?: '—';
$schoolAddress = getSetting('school_address') ?: '—';
$logo          = mediaUrl(getSetting('school_logo'));
$academicYear  = getSetting('academic_year') ?: '—';

$emailEnabled  = getSetting('email_notifications') === '1';
$smsEnabled    = getSetting('sms_notifications') === '1';
$plan          = getSetting('subscription_plan') ?: 'Free';

$totalStudents = $db->query("SELECT COUNT(*) FROM students WHERE is_active=1")->fetchColumn();
$totalTeachers = $db->query("SELECT COUNT(*) FROM users WHERE role='teacher' AND is_active=1")->fetchColumn();
$totalAdmins   = $db->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="grid"></i> Super Admin Dashboard</h1>
        <p><?= date('l, d M Y') ?></p>
    </div>
</div>

<div class="page-content">

    <!-- School profile (synced live from Admin -> Settings) -->
    <div class="card mb-6">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <h3><i data-feather="home"></i> School Profile</h3>
            <span class="badge badge-info">Synced from Admin Settings</span>
        </div>
        <div class="card-body">
            <div style="display:flex;gap:24px;align-items:center;flex-wrap:wrap;">
                <div style="flex-shrink:0;">
                    <?php if ($logo): ?>
                        <img src="<?= htmlspecialchars($logo) ?>" alt="School Logo"
                             style="height:90px;width:90px;object-fit:cover;border-radius:14px;border:1px solid #e2e8f0;background:#f8fafc;">
                    <?php else: ?>
                        <div style="height:90px;width:90px;border-radius:14px;border:1px dashed #cbd5e1;display:flex;align-items:center;justify-content:center;color:#94a3b8;">
                            <i data-feather="image"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div style="flex:1;min-width:240px;">
                    <table style="width:100%;border-collapse:collapse;font-size:14px;">
                        <tr>
                            <td style="padding:8px 0;color:#64748b;width:140px;">School Name</td>
                            <td style="padding:8px 0;color:#0f172a;font-weight:600;"><?= htmlspecialchars($schoolName) ?></td>
                        </tr>
                        <tr>
                            <td style="padding:8px 0;color:#64748b;vertical-align:top;">Address</td>
                            <td style="padding:8px 0;color:#0f172a;white-space:pre-line;"><?= htmlspecialchars($schoolAddress) ?></td>
                        </tr>
                        <tr>
                            <td style="padding:8px 0;color:#64748b;">Academic Year</td>
                            <td style="padding:8px 0;color:#0f172a;"><?= htmlspecialchars($academicYear) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(59,130,246,.1)"><i data-feather="users" style="color:#3b82f6"></i></div>
            <div class="stat-content"><p class="stat-label">Total Students</p><p class="stat-value"><?= $totalStudents ?></p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(139,92,246,.1)"><i data-feather="briefcase" style="color:#8b5cf6"></i></div>
            <div class="stat-content"><p class="stat-label">Total Teachers</p><p class="stat-value"><?= $totalTeachers ?></p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(6,182,212,.1)"><i data-feather="shield" style="color:#06b6d4"></i></div>
            <div class="stat-content"><p class="stat-label">Admins</p><p class="stat-value"><?= $totalAdmins ?></p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,.1)"><i data-feather="award" style="color:#f59e0b"></i></div>
            <div class="stat-content"><p class="stat-label">Subscription Plan</p><p class="stat-value" style="font-size:18px"><?= htmlspecialchars($plan) ?></p></div>
        </div>
    </div>

    <!-- Feature status -->
    <div class="card">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <h3><i data-feather="sliders"></i> Enabled Features</h3>
            <a href="subscription.php" class="btn btn-primary btn-sm"><i data-feather="settings"></i> Customize</a>
        </div>
        <div class="card-body">
            <div style="display:flex;gap:16px;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;border:1px solid #e2e8f0;border-radius:10px;">
                    <i data-feather="mail"></i> Email Notifications
                    <span class="badge <?= $emailEnabled ? 'badge-success' : 'badge-danger' ?>"><?= $emailEnabled ? 'On' : 'Off' ?></span>
                </div>
                <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;border:1px solid #e2e8f0;border-radius:10px;">
                    <i data-feather="message-square"></i> SMS Notifications
                    <span class="badge <?= $smsEnabled ? 'badge-success' : 'badge-danger' ?>"><?= $smsEnabled ? 'On' : 'Off' ?></span>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
