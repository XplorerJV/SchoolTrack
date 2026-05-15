<?php
$pageTitle = 'Principal Dashboard';
require_once __DIR__ . '/../auth.php';
requireRole('principal', '../index.php');
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

// Get class-wise attendance
$stmt = $db->prepare("
    SELECT 
        s.class,
        COUNT(DISTINCT s.id) as total_students,
        COUNT(DISTINCT CASE WHEN sa.date = ? AND sa.status = 'present' THEN s.id END) as present,
        COUNT(DISTINCT CASE WHEN sa.date = ? AND sa.status = 'absent' THEN s.id END) as absent
    FROM students s
    LEFT JOIN student_attendance sa ON s.id = sa.student_id
    WHERE s.is_active = 1
    GROUP BY s.class
    ORDER BY s.class
");
$stmt->execute([date('Y-m-d'), date('Y-m-d')]);
$classAttendance = $stmt->fetchAll();

// Get absent students list
$stmt = $db->prepare("
    SELECT s.roll_number, s.name, s.class, s.parent_email
    FROM students s
    WHERE s.is_active = 1 AND NOT EXISTS (
        SELECT 1 FROM student_attendance sa 
        WHERE sa.student_id = s.id AND sa.date = ? AND sa.status != 'absent'
    )
    ORDER BY s.class, s.roll_number
");
$stmt->execute([date('Y-m-d')]);
$absentStudents = $stmt->fetchAll();

// Get monthly trend (last 30 days)
$stmt = $db->prepare("
    SELECT 
        DATE(sa.date) as date,
        COUNT(*) as total,
        SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent
    FROM student_attendance sa
    WHERE sa.date >= DATE_SUB(?, INTERVAL 30 DAY)
    GROUP BY DATE(sa.date)
    ORDER BY sa.date DESC
");
$stmt->execute([date('Y-m-d')]);
$trendData = $stmt->fetchAll();
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="grid"></i> Principal Dashboard</h1>
        <p>School attendance overview and performance metrics</p>
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

    <!-- Class-wise Attendance & Absent Students -->
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
                                <th>%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classAttendance as $row): 
                                $percentage = $row['total_students'] > 0 ? round(($row['present'] / $row['total_students']) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['class']) ?></strong></td>
                                <td><?= $row['total_students'] ?></td>
                                <td><span class="badge" style="background:#d1fae5;color:#059669"><?= $row['present'] ?></span></td>
                                <td><span class="badge" style="background:#fee2e2;color:#dc2626"><?= $row['absent'] ?></span></td>
                                <td><span class="badge" style="background:<?= $percentage >= 75 ? '#d1fae5' : '#fef3c7' ?>;color:<?= $percentage >= 75 ? '#059669' : '#d97706' ?>"><?= $percentage ?>%</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Absent Students -->
        <div class="card">
            <div class="card-header">
                <h3><i data-feather="alert-circle"></i> Absent Students Today (<?= count($absentStudents) ?>)</h3>
            </div>
            <div class="card-body">
                <div class="table-wrapper">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Roll No</th>
                                <th>Name</th>
                                <th>Class</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($absentStudents)): ?>
                            <tr><td colspan="3" style="text-align:center;padding:20px;color:#6b7280">All students present today! 🎉</td></tr>
                            <?php else: ?>
                                <?php foreach (array_slice($absentStudents, 0, 10) as $student): ?>
                                <tr>
                                    <td><?= htmlspecialchars($student['roll_number']) ?></td>
                                    <td><?= htmlspecialchars($student['name']) ?></td>
                                    <td><?= htmlspecialchars($student['class']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
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
                <a href="reports.php" class="btn btn-primary">
                    <i data-feather="bar-chart-2"></i> View Reports
                </a>
                <a href="attendance.php" class="btn btn-primary">
                    <i data-feather="check-square"></i> View Attendance
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
