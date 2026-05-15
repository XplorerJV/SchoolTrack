<?php
// Database Configuration
// School Attendance Management System

define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Change to your MySQL username
define('DB_PASS', '');            // Change to your MySQL password
define('DB_NAME', 'school_attendance');
define('DB_PORT', '3306');

// App Config
define('APP_NAME', 'SchoolTrack');
define('APP_URL', 'http://localhost/school'); // Change to your URL
define('APP_VERSION', '1.0.0');

define('UPLOADS_DIR', __DIR__ . '/uploads');
define('UPLOADS_URL', APP_URL . '/uploads');

// Session
define('SESSION_TIMEOUT', 3600); // 1 hour

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Create PDO connection
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// Get setting value
function getSetting($key) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : null;
    } catch (Exception $e) {
        return null;
    }
}

// Update setting value
function setSetting($key, $value) {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value = ?");
        return $stmt->execute([$key, $value, $value]);
    } catch (Exception $e) {
        return false;
    }
}

function saveUploadedFile($file) {
    if (empty($file) || empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $allowedTypes = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/svg+xml' => 'svg'
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowedTypes[$mime])) {
        return null;
    }
    if (!file_exists(UPLOADS_DIR)) {
        mkdir(UPLOADS_DIR, 0755, true);
    }
    $ext = $allowedTypes[$mime];
    $filename = 'upload_' . uniqid() . '.' . $ext;
    $destination = UPLOADS_DIR . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return null;
    }
    return UPLOADS_URL . '/' . $filename;
}

// Format date
function formatDate($date) {
    return date('d M Y', strtotime($date));
}

// Format time
function formatTime($time) {
    return date('h:i A', strtotime($time));
}

// Calculate attendance percentage
function getAttendancePercentage($presentDays, $totalDays) {
    if ($totalDays == 0) return 0;
    return round(($presentDays / $totalDays) * 100, 2);
}

// Export CSV
function exportCSV($filename, $headers, $rows) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// Check if student is late
function isLate($timeIn) {
    $lateTime = getSetting('late_time') ?: '08:15:00';
    return $timeIn > $lateTime;
}

// Get student by card UID
function getStudentByCard($cardUid) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM students WHERE card_uid = ? AND is_active = 1");
    $stmt->execute([$cardUid]);
    return $stmt->fetch();
}

// Record student attendance by card
function recordStudentAttendanceByCard($studentId) {
    $db = getDB();
    $today = date('Y-m-d');
    $now = date('H:i:s');
    
    // Check if already marked today
    $stmt = $db->prepare("SELECT id FROM student_attendance WHERE student_id = ? AND date = ?");
    $stmt->execute([$studentId, $today]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Already marked today'];
    }
    
    // Determine status
    $lateTime = getSetting('late_time') ?: '08:15:00';
    $status = ($now > $lateTime) ? 'late' : 'present';
    
    // Insert attendance
    $stmt = $db->prepare("INSERT INTO student_attendance (student_id, date, time_in, status, marked_by) VALUES (?,?,?,?,'card')");
    if ($stmt->execute([$studentId, $today, $now, $status])) {
        return ['success' => true, 'message' => 'Attendance marked', 'status' => $status];
    }
    return ['success' => false, 'message' => 'Error marking attendance'];
}
?>