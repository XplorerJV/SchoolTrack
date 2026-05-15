<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../email.php';

/**
 * Process Absent Students - Mark as absent & send notifications
 * Can be triggered by cron job: php process-absent.php
 * Or called via GET/POST request
 */

$db = getDB();
$cutoff = getSetting('cutoff_time') ?: '09:30:00';
$today  = date('Y-m-d');
$now    = date('H:i:s');

$response = ['success' => false, 'message' => '', 'processed' => 0];

if ($now < $cutoff) {
    $response['message'] = 'Current time is before cutoff time. No action taken.';
    echo json_encode($response);
    exit;
}

try {
    // Get students not marked today (absent)
    $stmt = $db->prepare("
        SELECT s.* FROM students s
        LEFT JOIN student_attendance sa ON sa.student_id = s.id AND sa.date = ?
        WHERE sa.id IS NULL AND s.is_active = 1 AND s.parent_email IS NOT NULL AND s.parent_email != ''
    ");
    $stmt->execute([$today]);
    $absentStudents = $stmt->fetchAll();
    
    foreach ($absentStudents as $student) {
        // Mark as absent
        $stmt = $db->prepare("INSERT INTO student_attendance (student_id, date, status, marked_by) VALUES (?,?,'absent','system')");
        $stmt->execute([$student['id'], $today]);
        
        // Send notification
        sendAbsentNotification($student);
        $response['processed']++;
        
        // Log the action
        auditLog(0, 'AUTO_ABSENT_MARK', 'attendance', "Student {$student['name']} marked absent automatically");
    }
    
    $response['success'] = true;
    $response['message'] = "Processed $response[processed] absent student(s)";
} catch (Exception $e) {
    $response['message'] = "Error: " . $e->getMessage();
}

echo json_encode($response);
?>
