<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$studentId = (int)($_POST['student_id'] ?? 0);
$date      = trim($_POST['date'] ?? date('Y-m-d'));
$timeIn    = trim($_POST['time_in'] ?? date('H:i'));
$status    = trim($_POST['status'] ?? 'present');
$markedBy  = 'manual';

if ($studentId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid student']);
    exit;
}

$allowed = ['present', 'absent', 'late', 'excused'];
if (!in_array($status, $allowed)) $status = 'present';

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT id FROM student_attendance WHERE student_id = ? AND date = ?");
    $stmt->execute([$studentId, $date]);
    $existing = $stmt->fetch();

    if ($existing) {
        $db->prepare("UPDATE student_attendance SET status = ?, time_in = ?, marked_by = ? WHERE id = ?")
           ->execute([$status, $timeIn, $markedBy, $existing['id']]);
        $msg = 'Attendance updated';
    } else {
        $db->prepare("INSERT INTO student_attendance (student_id, date, time_in, status, marked_by) VALUES (?,?,?,?,?)")
           ->execute([$studentId, $date, $timeIn, $status, $markedBy]);
        $msg = 'Attendance marked';
    }

    // Audit log
    $userId = $_SESSION['user_id'] ?? null;
    auditLog($userId, 'MARK', 'student_attendance', "Marked student ID $studentId as $status on $date via card scan");

    echo json_encode(['success' => true, 'message' => $msg, 'status' => $status]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
