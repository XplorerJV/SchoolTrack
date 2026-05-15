<?php
$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../auth.php';
requireRole('admin', '../index.php');
require_once __DIR__ . '/../header.php';

$db = getDB();

// Get statistics
$stmt = $db->prepare("SELECT COUNT(*) as count FROM students WHERE is_active = 1");
$stmt->execute();
$totalStudents = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'teacher' AND is_active = 1");
$stmt->execute();
$totalTeachers = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM student_attendance WHERE date = ? AND status = 'present'");
$stmt->execute([date('Y-m-d')]);
$presentToday = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM student_attendance WHERE date = ? AND status = 'absent'");
$stmt->execute([date('Y-m-d')]);
$absentToday = $stmt->fetch()['count'];

// Get recent attendance
$stmt = $db->prepare("
    SELECT sa.*, s.name, s.roll_number, s.class 
    FROM student_attendance sa
    JOIN students s ON s.id = sa.student_id
    WHERE sa.date = ?
    ORDER BY sa.created_at DESC
    LIMIT 10
");
$stmt->execute([date('Y-m-d')]);
$recentAttendance = $stmt->fetchAll();

// Get class-wise attendance
$stmt = $db->prepare("
    SELECT 
        s.class,
        COUNT(DISTINCT s.id) as total_students,
        COUNT(DISTINCT CASE WHEN sa.date = ? AND sa.status = 'present' THEN s.id END) as present,
        COUNT(DISTINCT CASE WHEN sa.date = ? AND sa.status = 'absent' THEN s.id END) as absent,
        COUNT(DISTINCT CASE WHEN sa.date = ? AND sa.status = 'late' THEN s.id END) as late
    FROM students s
    LEFT JOIN student_attendance sa ON s.id = sa.student_id
    WHERE s.is_active = 1
    GROUP BY s.class
    ORDER BY s.class
");
$stmt->execute([date('Y-m-d'), date('Y-m-d'), date('Y-m-d')]);
$classAttendance = $stmt->fetchAll();
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="grid"></i> Dashboard</h1>
        <p>Admin overview and real-time statistics</p>
    </div>
</div>

<div class="page-content">
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(59,130,246,.1)">
                <i data-feather="users" style="color:#3b82f6"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Total Students</p>
                <p class="stat-value"><?= $totalStudents ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,.1)">
                <i data-feather="briefcase" style="color:#10b981"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Total Teachers</p>
                <p class="stat-value"><?= $totalTeachers ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(34,197,94,.1)">
                <i data-feather="check-circle" style="color:#22c55e"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Present Today</p>
                <p class="stat-value"><?= $presentToday ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(239,68,68,.1)">
                <i data-feather="x-circle" style="color:#ef4444"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Absent Today</p>
                <p class="stat-value"><?= $absentToday ?></p>
            </div>
        </div>
    </div>

    <!-- Class Attendance -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="card">
            <div class="card-header">
                <h3><i data-feather="bar-chart-2"></i> Class-wise Attendance Today</h3>
            </div>
            <div class="card-body">
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th>Total</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Late</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classAttendance as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['class']) ?></strong></td>
                                <td><?= $row['total_students'] ?></td>
                                <td><span class="badge" style="background:#d1fae5;color:#059669"><?= $row['present'] ?></span></td>
                                <td><span class="badge" style="background:#fee2e2;color:#dc2626"><?= $row['absent'] ?></span></td>
                                <td><span class="badge" style="background:#fef3c7;color:#d97706"><?= $row['late'] ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Attendance -->
        <div class="card">
            <div class="card-header">
                <h3><i data-feather="activity"></i> Recent Attendance (Today)</h3>
            </div>
            <div class="card-body">
                <div class="table-wrapper">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Class</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentAttendance as $record): ?>
                            <tr>
                                <td><?= htmlspecialchars($record['name']) ?></td>
                                <td><?= htmlspecialchars($record['class']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $record['status'] ?>">
                                        <?= ucfirst($record['status']) ?>
                                    </span>
                                </td>
                                <td><?= formatTime($record['time_in']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h3><i data-feather="zap"></i> Quick Actions</h3>
        </div>
        <div class="card-body">
            <div class="flex flex-wrap gap-3">
                <a href="students.php" class="btn btn-primary">
                    <i data-feather="users"></i> Manage Students
                </a>
                <a href="teachers.php" class="btn btn-primary">
                    <i data-feather="briefcase"></i> Manage Teachers
                </a>
                <a href="attendance.php" class="btn btn-primary">
                    <i data-feather="check-square"></i> View Attendance
                </a>
                <a href="daily-report.php" class="btn btn-secondary">
                    <i data-feather="calendar"></i> Daily Report
                </a>
                <a href="performance.php" class="btn btn-secondary">
                    <i data-feather="trending-up"></i> Performance
                </a>
                <a href="reports.php" class="btn btn-primary">
                    <i data-feather="bar-chart-2"></i> Reports
                </a>
                <a href="logs.php" class="btn btn-secondary">
                    <i data-feather="file-text"></i> Audit Logs
                </a>
                <a href="settings.php" class="btn btn-secondary">
                    <i data-feather="settings"></i> Settings
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
