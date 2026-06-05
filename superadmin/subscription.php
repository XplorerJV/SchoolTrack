<?php
$pageTitle = 'Subscription';
require_once __DIR__ . '/../auth.php';
requireRole('superadmin', '../index.php');
require_once __DIR__ . '/../header.php';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        setSetting('subscription_plan',    trim($_POST['subscription_plan'] ?? 'Free'));
        setSetting('email_notifications',  isset($_POST['email_notifications']) ? '1' : '0');
        setSetting('sms_notifications',    isset($_POST['sms_notifications']) ? '1' : '0');
        $success = 'Subscription settings saved successfully!';
        auditLog($_SESSION['user_id'], 'UPDATE', 'subscription', 'Updated subscription / feature customization');
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$plan          = getSetting('subscription_plan') ?: 'Free';
$emailEnabled  = getSetting('email_notifications') === '1';
$smsEnabled    = getSetting('sms_notifications') === '1';
$plans = ['Free', 'Basic', 'Premium', 'Enterprise'];
?>

<style>
.switch { position:relative; display:inline-block; width:48px; height:26px; flex-shrink:0; }
.switch input { opacity:0; width:0; height:0; }
.slider { position:absolute; cursor:pointer; inset:0; background:#cbd5e1; transition:.3s; border-radius:26px; }
.slider:before { content:""; position:absolute; height:20px; width:20px; left:3px; bottom:3px; background:#fff; transition:.3s; border-radius:50%; }
.switch input:checked + .slider { background:#22c55e; }
.switch input:checked + .slider:before { transform:translateX(22px); }
.feature-row { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:16px 0; border-bottom:1px solid #eef2f7; }
.feature-row:last-child { border-bottom:none; }
.feature-meta { display:flex; align-items:center; gap:14px; }
.feature-ic { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
</style>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="credit-card"></i> Subscription</h1>
        <p>Manage the school's plan and customize which features are enabled</p>
    </div>
</div>

<div class="page-content">
    <?php if ($error): ?><div class="alert alert-danger"><i data-feather="alert-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><i data-feather="check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="POST" class="form">

        <!-- Plan -->
        <div class="card mb-6">
            <div class="card-header"><h3><i data-feather="award"></i> Plan</h3></div>
            <div class="card-body">
                <div class="form-group" style="max-width:320px;">
                    <label>Current Plan</label>
                    <select name="subscription_plan">
                        <?php foreach ($plans as $p): ?>
                        <option value="<?= htmlspecialchars($p) ?>" <?= $plan === $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Customize -->
        <div class="card mb-6">
            <div class="card-header"><h3><i data-feather="sliders"></i> Customize</h3></div>
            <div class="card-body">
                <p style="color:#64748b;font-size:13px;margin-bottom:8px;">Turn features on or off for this school's subscription.</p>

                <div class="feature-row">
                    <div class="feature-meta">
                        <div class="feature-ic" style="background:rgba(59,130,246,.1)"><i data-feather="mail" style="color:#3b82f6"></i></div>
                        <div>
                            <div style="font-weight:600;color:#0f172a;">Email Feature</div>
                            <div style="font-size:13px;color:#64748b;">Send attendance alerts and reports over email.</div>
                        </div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="email_notifications" <?= $emailEnabled ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="feature-row">
                    <div class="feature-meta">
                        <div class="feature-ic" style="background:rgba(34,197,94,.1)"><i data-feather="message-square" style="color:#22c55e"></i></div>
                        <div>
                            <div style="font-weight:600;color:#0f172a;">SMS Feature</div>
                            <div style="font-size:13px;color:#64748b;">Send attendance alerts to parents over SMS.</div>
                        </div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="sms_notifications" <?= $smsEnabled ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary"><i data-feather="save"></i> Save Changes</button>
    </form>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
