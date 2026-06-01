<?php
// includes/header.php
$user = getCurrentUser();
$role = $user['role'] ?? '';
$schoolName = getSetting('school_name') ?: APP_NAME;
$schoolLogo = getSetting('school_logo');
$schoolAddress = getSetting('school_address');
$navItems = [];
if ($role === 'admin') {
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

<div class="main-content">
    <div class="top-bar" style="margin-bottom:0">
        <div class="page-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>
        <div style="display:flex;align-items:center;gap:12px;">
            <span style="font-size:13px;color:#64748b;"><?= date('l, d M Y') ?></span>
        </div>
    </div>

    <div class="page-wrapper" style="padding:30px;background:#f1f5f9;">
        <!-- Page Header -->
        <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;gap:20px;flex-wrap:wrap;">

        </div>

        <!-- Page Content -->