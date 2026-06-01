<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

// Start session without triggering auth redirects
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$studentId = (int)($_POST['student_id'] ?? 0);
$date      = trim($_POST['date'] ?? date('Y-m-d'));
$timeIn    = trim($_POST['time_in'] ?? date('H:i'));
$status    = trim($_POST['status'] ?? 'present');
$period    = (int)($_POST['period'] ?? 1);

if ($studentId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid student']);
    exit;
}

$allowed = ['present', 'absent', 'late', 'excused'];
if (!in_array($status, $allowed)) $status = 'present';

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT id FROM student_attendance WHERE student_id = ? AND date = ? AND period = ?");
    $stmt->execute([$studentId, $date, $period]);
    $existing = $stmt->fetch();

    if ($existing) {
        $db->prepare("UPDATE student_attendance SET status = ?, time_in = ?, marked_by = 'manual', period = ? WHERE id = ?")
           ->execute([$status, $timeIn, $period, $existing['id']]);
        $msg = 'Attendance updated';
    } else {
        $db->prepare("INSERT INTO student_attendance (student_id, date, time_in, status, marked_by, period) VALUES (?,?,?,?,?,?)")
           ->execute([$studentId, $date, $timeIn, $status, 'manual', $period]);
        $msg = 'Attendance marked';
    }

    // Audit log — safely, won't break if session missing
    try {
        $userId = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
          $db->prepare("INSERT INTO audit_logs (user_id, action, module, description, ip_address) VALUES (?,?,?,?,?)")
              ->execute([$userId, 'MARK', 'student_attendance', "Marked student ID $studentId as $status on $date period $period via card scan", $ip]);
    } catch (Exception $e) { /* silently ignore audit failure */ }

    echo json_encode(['success' => true, 'message' => $msg, 'status' => $status]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
