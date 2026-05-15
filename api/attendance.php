<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

/**
 * RFID Card Attendance API Endpoint
 * Hardware sends POST request with card_uid parameter
 * Returns JSON response with status
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$cardUid = trim($_POST['card_uid'] ?? '');

if (empty($cardUid)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Card UID is required']);
    exit;
}

try {
    // Get student by card UID
    $student = getStudentByCard($cardUid);
    
    if (!$student) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Student card not found']);
        exit;
    }
    
    // Record attendance
    $result = recordStudentAttendanceByCard($student['id']);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'student_name' => $student['name'],
            'class' => $student['class'],
            'status' => $result['status'],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
