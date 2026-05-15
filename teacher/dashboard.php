<?php
$pageTitle = 'Teacher Dashboard';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../header.php';

requireRole('teacher', '../index.php');

$db = getDB();
$user = getCurrentUser();

// Get teacher's today attendance
$stmt = $db->prepare("SELECT * FROM teacher_attendance WHERE teacher_id = ? AND date = ?");
$stmt->execute([$user['id'], date('Y-m-d')]);
$todayAttendance = $stmt->fetch();

// Get recent attendance history
$stmt = $db->prepare("
    SELECT * FROM teacher_attendance 
    WHERE teacher_id = ?
    ORDER BY date DESC
    LIMIT 10
");
$stmt->execute([$user['id']]);
$recentAttendance = $stmt->fetchAll();

// Get statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
    FROM teacher_attendance
    WHERE teacher_id = ? AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())
");
$stmt->execute([$user['id']]);
$stats = $stmt->fetch();

// Get assigned students
$stmt = $db->prepare("
    SELECT s.* FROM students s
    WHERE s.is_active = 1
    ORDER BY s.class, s.roll_number
    LIMIT 10
");
$stmt->execute();
$assignedStudents = $stmt->fetchAll();

$attendance_percentage = $stats['total'] > 0 ? round(($stats['present'] / $stats['total']) * 100, 1) : 0;
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="grid"></i> Teacher Dashboard</h1>
        <p>Welcome <?= htmlspecialchars($user['name']) ?>, manage your attendance and view students</p>
    </div>
</div>

<div class="page-content">
    <!-- Today's Attendance -->
    <div class="card mb-6">
        <div class="card-header">
            <h3><i data-feather="check-circle"></i> Today's Attendance</h3>
        </div>
        <div class="card-body">
            <?php if ($todayAttendance): ?>
                <div style="padding:20px;background:#f0fdf4;border-radius:8px;border-left:4px solid #22c55e">
                    <p style="margin:0;color:#1b5e20"><strong>Status:</strong> <?= ucfirst($todayAttendance['status']) ?></p>
                    <p style="margin:5px 0 0;color:#1b5e20"><strong>Time In:</strong> <?= formatTime($todayAttendance['time_in']) ?></p>
                    <?php if ($todayAttendance['time_out']): ?>
                    <p style="margin:5px 0 0;color:#1b5e20"><strong>Time Out:</strong> <?= formatTime($todayAttendance['time_out']) ?></p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="padding:20px;background:#fef3c7;border-radius:8px;border-left:4px solid #f59e0b">
                    <p style="margin:0;color:#92400e">You haven't marked your attendance yet today.</p>
                    <a href="my-attendance.php" class="btn btn-sm btn-warning" style="margin-top:10px">
                        <i data-feather="plus"></i> Mark Attendance
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Monthly Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(59,130,246,.1)">
                <i data-feather="calendar" style="color:#3b82f6"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Total Days (This Month)</p>
                <p class="stat-value"><?= $stats['total'] ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(34,197,94,.1)">
                <i data-feather="check-circle" style="color:#22c55e"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Present</p>
                <p class="stat-value"><?= $stats['present'] ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(239,68,68,.1)">
                <i data-feather="x-circle" style="color:#ef4444"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Absent</p>
                <p class="stat-value"><?= $stats['absent'] ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,.1)">
                <i data-feather="percent" style="color:#f59e0b"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Attendance %</p>
                <p class="stat-value"><?= $attendance_percentage ?>%</p>
            </div>
        </div>
    </div>

    <!-- Recent Attendance & Students -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="card">
            <div class="card-header">
                <h3><i data-feather="history"></i> Recent Attendance</h3>
            </div>
            <div class="card-body">
                <div class="table-wrapper">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Time In</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentAttendance as $record): ?>
                            <tr>
                                <td><?= formatDate($record['date']) ?></td>
                                <td><span class="badge badge-<?= $record['status'] ?>"><?= ucfirst($record['status']) ?></span></td>
                                <td><?= $record['time_in'] ? formatTime($record['time_in']) : '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:15px;text-align:center">
                    <a href="my-attendance.php" class="btn btn-sm btn-secondary">View Full History</a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i data-feather="users"></i> Students</h3>
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
                            <?php foreach ($assignedStudents as $student): ?>
                            <tr>
                                <td><?= htmlspecialchars($student['roll_number']) ?></td>
                                <td><?= htmlspecialchars($student['name']) ?></td>
                                <td><?= htmlspecialchars($student['class']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:15px;text-align:center">
                    <a href="students.php" class="btn btn-sm btn-secondary">View All Students</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
