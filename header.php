<?php
// includes/header.php
$user = getCurrentUser();
$role = $user['role'] ?? '';
$schoolName = getSetting('school_name') ?: APP_NAME;
$schoolLogo = mediaUrl(getSetting('school_logo'));
$schoolAddress = getSetting('school_address');
$navItems = [];
if ($role === 'superadmin') {
    $navItems = [
        ['url'=>'dashboard.php','icon'=>'grid','label'=>'Dashboard'],
        ['url'=>'subscription.php','icon'=>'credit-card','label'=>'Subscription'],
    ];
} elseif ($role === 'admin') {
    $navItems = [
        ['url'=>'dashboard.php','icon'=>'grid','label'=>'Dashboard'],
        ['url'=>'class-folders.php','icon'=>'folder','label'=>'Class Folders'],
        ['url'=>'students.php','icon'=>'users','label'=>'Students'],
        ['url'=>'teachers.php','icon'=>'briefcase','label'=>'Teachers'],
        ['url'=>'principals.php','icon'=>'user-check','label'=>'Principals'],
        ['url'=>'attendance.php','icon'=>'check-square','label'=>'Attendance'],
        ['url'=>'daily-report.php','icon'=>'calendar','label'=>'Daily Report'],
        ['url'=>'performance.php','icon'=>'trending-up','label'=>'Performance'],
        ['url'=>'reports.php','icon'=>'bar-chart-2','label'=>'Reports'],
        ['url'=>'logs.php','icon'=>'file-text','label'=>'Logs'],
        ['url'=>'settings.php','icon'=>'settings','label'=>'Settings'],
    ];
} elseif ($role === 'principal') {
    $navItems = [
        ['url'=>'dashboard.php','icon'=>'grid','label'=>'Dashboard'],
        ['url'=>'classes.php','icon'=>'layers','label'=>'Classes'],
        ['url'=>'attendance.php','icon'=>'check-square','label'=>'Attendance'],
        ['url'=>'reports.php','icon'=>'bar-chart-2','label'=>'Reports'],
    ];
} elseif ($role === 'teacher') {
    $navItems = [
        ['url'=>'dashboard.php','icon'=>'grid','label'=>'Dashboard'],
        ['url'=>'classes.php','icon'=>'layers','label'=>'Classes'],
        ['url'=>'my-attendance.php','icon'=>'check-square','label'=>'My Attendance'],
        ['url'=>'students.php','icon'=>'users','label'=>'Students'],
    ];
}
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'SchoolTrack') ?> — SchoolTrack</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/feather-icons@4.29.0/dist/feather.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/base.css">
</head>
<body>
<div class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <?php if ($schoolLogo): ?>
            <img src="<?= htmlspecialchars($schoolLogo) ?>" alt="School Logo" class="school-logo-img">
        <?php endif; ?>
        <div class="logo-text">
            <h1><?= htmlspecialchars($schoolName) ?></h1>
            <div class="sidebar-role"><?= ucfirst($role) ?> Panel</div>
            <?php if ($schoolAddress): ?>
            <div class="sidebar-subtitle"><?= htmlspecialchars($schoolAddress) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <nav class="nav-section">
        <div class="nav-label">Navigation</div>
        <?php foreach($navItems as $item): ?>
        <a href="<?= $item['url'] ?>" class="nav-item <?= $currentPage === $item['url'] ? 'active' : '' ?>">
            <i data-feather="<?= $item['icon'] ?>"></i>
            <?= $item['label'] ?>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            <div class="user-details">
                <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                <div class="user-role"><?= ucfirst($role) ?></div>
            </div>
        </div>
        <a href="../logout.php" class="logout-btn"><i data-feather="log-out"></i> Sign out</a>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="main-content">
    <div class="top-bar" style="margin-bottom:0">
        <div style="display:flex;align-items:center;gap:12px;">
            <button id="menuToggle" onclick="toggleSidebar()" style="display:none;background:none;border:none;cursor:pointer;padding:4px;color:#475569;" aria-label="Menu">
                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <div class="page-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>
        </div>
        <div style="display:flex;align-items:center;gap:12px;">
            <?php if (in_array($role, ['admin','teacher','principal'])): ?>
            <a href="<?= APP_URL ?>/mail-tester.php" target="_blank" rel="noopener" class="email-btn"
               style="display:inline-flex;align-items:center;gap:6px;background:#38bdf8;color:#0f172a;font-weight:600;font-size:13px;padding:8px 14px;border-radius:8px;text-decoration:none;">
                <i data-feather="mail" style="width:16px;height:16px;"></i> Email
            </a>
            <?php endif; ?>
            <span style="font-size:13px;color:#64748b;"><?= date('l, d M Y') ?></span>
        </div>
    </div>

    <div class="page-wrapper" style="padding:30px;background:#f1f5f9;">
        <!-- Page Header -->
        <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;gap:20px;flex-wrap:wrap;">

        </div>

        <!-- Page Content -->