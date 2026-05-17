<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$cardUid = trim($_GET['card_uid'] ?? '');
$date    = trim($_GET['date'] ?? date('Y-m-d'));

if (empty($cardUid)) {
    echo json_encode(['success' => false, 'message' => 'Card UID required']);
    exit;
}

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT id, name, roll_number, class, section FROM students WHERE card_uid = ? AND is_active = 1");
    $stmt->execute([$cardUid]);
    $student = $stmt->fetch();

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Card not found. Check card UID.']);
        exit;
    }

    $stmt = $db->prepare("SELECT status, time_in FROM student_attendance WHERE student_id = ? AND date = ?");
    $stmt->execute([$student['id'], $date]);
    $attendance = $stmt->fetch();

    echo json_encode([
        'success'    => true,
        'student'    => $student,
        'attendance' => $attendance ?: null,
        'date'       => $date,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
