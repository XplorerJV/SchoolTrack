<?php
require_once __DIR__ . '/config.php';

/**
 * Send email using SMTP settings from DB
 * Uses PHPMailer if available, falls back to mail()
 */
function sendEmail($to, $subject, $body, $studentId = null) {
    $host     = getSetting('smtp_host');
    $port     = getSetting('smtp_port') ?: 587;
    $user     = getSetting('smtp_username');
    $pass     = getSetting('smtp_password');
    $fromName = getSetting('smtp_from_name') ?: 'School Attendance';
    $enc      = getSetting('smtp_encryption') ?: 'tls';

    $success = false;
    $error   = '';

    // Log notification record
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO email_notifications (student_id, notification_type, recipient_email, subject, message, status) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$studentId, 'absent', $to, $subject, $body, 'pending']);
    $notifId = $db->lastInsertId();

    // Try SMTP via socket if credentials provided
    if (!empty($host) && !empty($user) && !empty($pass)) {
        try {
            $success = sendSMTPMail($host, $port, $user, $pass, $fromName, $to, $subject, $body, $enc);
        } catch (Exception $e) {
            $error = $e->getMessage();
            $success = false;
        }
    } else {
        // Fallback to PHP mail()
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: $fromName <noreply@school.local>\r\n";
        $success = mail($to, $subject, $body, $headers);
    }

    // Update notification status
    $status = $success ? 'sent' : 'failed';
    $stmt2 = $db->prepare("UPDATE email_notifications SET status = ?, sent_at = NOW() WHERE id = ?");
    $stmt2->execute([$status, $notifId]);

    return $success;
}

/**
 * Basic SMTP sender via fsockopen (no dependency)
 */
function sendSMTPMail($host, $port, $user, $pass, $fromName, $to, $subject, $body, $enc = 'tls') {
    $prefix = ($enc === 'ssl') ? 'ssl://' : '';
    $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 30);
    if (!$socket) throw new Exception("SMTP connect failed: $errstr ($errno)");

    $read = fgets($socket, 515);
    if (substr($read, 0, 3) != '220') throw new Exception("SMTP: $read");

    fputs($socket, "EHLO " . php_uname('n') . "\r\n");
    $ehlo = '';
    while ($line = fgets($socket, 515)) { $ehlo .= $line; if ($line[3] == ' ') break; }

    if ($enc === 'tls') {
        fputs($socket, "STARTTLS\r\n");
        fgets($socket, 515);
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        fputs($socket, "EHLO " . php_uname('n') . "\r\n");
        while ($line = fgets($socket, 515)) { if ($line[3] == ' ') break; }
    }

    fputs($socket, "AUTH LOGIN\r\n");
    fgets($socket, 515);
    fputs($socket, base64_encode($user) . "\r\n");
    fgets($socket, 515);
    fputs($socket, base64_encode($pass) . "\r\n");
    $authResp = fgets($socket, 515);
    if (substr($authResp, 0, 3) != '235') throw new Exception("SMTP AUTH failed: $authResp");

    fputs($socket, "MAIL FROM:<$user>\r\n");        fgets($socket, 515);
    fputs($socket, "RCPT TO:<$to>\r\n");             fgets($socket, 515);
    fputs($socket, "DATA\r\n");                       fgets($socket, 515);

    $msg  = "From: $fromName <$user>\r\n";
    $msg .= "To: $to\r\n";
    $msg .= "Subject: $subject\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $msg .= $body . "\r\n.\r\n";
    fputs($socket, $msg);
    $resp = fgets($socket, 515);
    fputs($socket, "QUIT\r\n");
    fclose($socket);
    return substr($resp, 0, 3) == '250';
}

/**
 * Send absent notification email
 */
function sendAbsentNotification($student) {
    $schoolName = getSetting('school_name') ?: 'Our School';
    $date = date('d M Y');
    $subject = "Absence Alert: {$student['name']} - $date";
    $body = "
    <html><body style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
    <div style='background:#1e3a5f;padding:20px;text-align:center;'>
        <h2 style='color:white;margin:0;'>$schoolName</h2>
        <p style='color:#90cdf4;margin:5px 0 0;'>Attendance Notification</p>
    </div>
    <div style='padding:30px;background:#f8fafc;'>
        <h3 style='color:#e53e3e;'>⚠️ Absence Alert</h3>
        <p>Dear Parent/Guardian,</p>
        <p>This is to inform you that your ward <strong>{$student['name']}</strong> 
        (Class {$student['class']}-{$student['section']}, Roll No: {$student['roll_number']}) 
        was <strong style='color:#e53e3e;'>absent</strong> today, <strong>$date</strong>.</p>
        <div style='background:white;border-left:4px solid #e53e3e;padding:15px;margin:20px 0;'>
            <p style='margin:0;'><strong>Student:</strong> {$student['name']}</p>
            <p style='margin:5px 0 0;'><strong>Class:</strong> {$student['class']}-{$student['section']}</p>
            <p style='margin:5px 0 0;'><strong>Date:</strong> $date</p>
            <p style='margin:5px 0 0;'><strong>Status:</strong> <span style='color:#e53e3e;'>Absent</span></p>
        </div>
        <p>If this is a mistake or you have already informed the school, please disregard this message.</p>
        <p>For queries, contact the school office.</p>
    </div>
    <div style='background:#1e3a5f;padding:15px;text-align:center;'>
        <p style='color:#90cdf4;margin:0;font-size:12px;'>$schoolName | Automated Attendance System</p>
    </div>
    </body></html>";

    return sendEmail($student['parent_email'], $subject, $body, $student['id']);
}

/**
 * Mark absent and notify - run as cron or triggered
 */
function processAbsentStudents() {
    $db = getDB();
    $cutoff = getSetting('cutoff_time') ?: '09:30:00';
    $today  = date('Y-m-d');
    $now    = date('H:i:s');

    if ($now < $cutoff) return; // Too early

    // Get students not marked today
    $stmt = $db->prepare("
        SELECT s.* FROM students s
        LEFT JOIN student_attendance sa ON sa.student_id = s.id AND sa.date = ?
        WHERE sa.id IS NULL AND s.is_active = 1 AND s.parent_email IS NOT NULL AND s.parent_email != ''
    ");
    $stmt->execute([$today]);
    
    foreach ($stmt->fetchAll() as $student) {
        // Mark as absent
        $markStmt = $db->prepare("INSERT INTO student_attendance (student_id, date, status, marked_by) VALUES (?,?,'absent','system')");
        $markStmt->execute([$student['id'], $today]);
        
        // Send notification
        sendAbsentNotification($student);
    }
    return count($stmt->fetchAll());
}

/**
 * Send bunk notification (student marked but missing at day end)
 */
function sendBunkNotification($student) {
    $schoolName = getSetting('school_name') ?: 'Our School';
    $date = date('d M Y');
    $subject = "Bunk Warning: {$student['name']} - $date";
    $body = "
    <html><body style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
    <div style='background:#1e3a5f;padding:20px;text-align:center;'>
        <h2 style='color:white;margin:0;'>$schoolName</h2>
        <p style='color:#90cdf4;margin:5px 0 0;'>Attendance Notification</p>
    </div>
    <div style='padding:30px;background:#f8fafc;'>
        <h3 style='color:#e53e3e;'>⚠️ Bunk Warning</h3>
        <p>Dear Parent/Guardian,</p>
        <p>Our records show that your ward <strong>{$student['name']}</strong>
        (Class {$student['class']}-{$student['section']}, Roll No: {$student['roll_number']})
        was marked present earlier today but was <strong style='color:#e53e3e;'>not present</strong> at the end of the school day (<strong>$date</strong>).</p>
        <div style='background:white;border-left:4px solid #e53e3e;padding:15px;margin:20px 0;'>
            <p style='margin:0;'><strong>Student:</strong> {$student['name']}</p>
            <p style='margin:5px 0 0;'><strong>Class:</strong> {$student['class']}-{$student['section']}</p>
            <p style='margin:5px 0 0;'><strong>Date:</strong> $date</p>
            <p style='margin:5px 0 0;'><strong>Action:</strong> The attendance record for today will be removed. Please contact the school for clarification.</p>
        </div>
        <p>If this is a mistake or you have information, please contact the school office immediately.</p>
    </div>
    <div style='background:#1e3a5f;padding:15px;text-align:center;'>
        <p style='color:#90cdf4;margin:0;font-size:12px;'>$schoolName | Automated Attendance System</p>
    </div>
    </body></html>";

    // Send email
    $emailSent = sendEmail($student['parent_email'] ?? '', $subject, $body, $student['id'] ?? null);

    // Send SMS if parent phone available and Twilio configured
    $smsSent = false;
    if (!empty($student['parent_phone'])) {
        $smsSent = sendSMS($student['parent_phone'], "Bunk Warning: {$student['name']} on $date. Contact school for details.");
    }
    return $emailSent || $smsSent;
}

/**
 * Detect bunked students after school end time and delete their attendance
 */
function processBunkStudents() {
    $db = getDB();
    $endTime = getSetting('school_end_time') ?: '15:30:00';
    $today = date('Y-m-d');
    $now = date('H:i:s');

    if ($now < $endTime) return 0; // wait until day end

    $stmt = $db->prepare("SELECT sa.id, sa.student_id, s.name, s.roll_number, s.class, s.section, s.parent_email, s.parent_phone FROM student_attendance sa JOIN students s ON s.id = sa.student_id WHERE sa.date = ? AND sa.status = 'present' AND (sa.time_out IS NULL OR sa.time_out = '')");
    $stmt->execute([$today]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $r) {
        // send bunk notification
        sendBunkNotification($r);
        // mark attendance as 'bunk' instead of deleting
        try {
            $up = $db->prepare("UPDATE student_attendance SET status = 'bunk', notes = CONCAT(IFNULL(notes, ''), ' [auto-marked bunk]') WHERE id = ?");
            $up->execute([$r['id']]);
            try { auditLog($_SESSION['user_id'] ?? null, 'UPDATE', 'attendance', 'Auto-marked bunk attendance ID: ' . $r['id']); } catch (Exception $e) {}
        } catch (Exception $e) {
            // ignore update failures
        }
    }
    return count($rows);
}

/**
 * Send SMS using Twilio when configured
 */
function sendSMS($toPhone, $message) {
    $sid = getSetting('twilio_sid');
    $token = getSetting('twilio_token');
    $from = getSetting('twilio_from');
    if (empty($sid) || empty($token) || empty($from) || empty($toPhone)) return false;

    $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
    $data = http_build_query([
        'From' => $from,
        'To' => $toPhone,
        'Body' => $message
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_USERPWD, $sid . ':' . $token);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code >= 200 && $code < 300);
}

?>