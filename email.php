<?php
require_once __DIR__ . '/auth.php';

/**
 * Send email — External API first, SMTP fallback.
 */
function sendEmail($to, $subject, $body, $studentId = null, $notificationType = 'absent') {
    if (empty($to)) return false;

    if (getSetting('email_notifications') !== '1') return false;

    $apiUrl    = getSetting('email_api_url');
    $apiKey    = getSetting('email_api_key');
    $apiHeader = getSetting('email_api_header') ?: 'Authorization';
    $host      = getSetting('smtp_host');
    $port      = getSetting('smtp_port') ?: 587;
    $user      = getSetting('smtp_username');
    $pass      = getSetting('smtp_password');
    $fromName  = getSetting('smtp_from_name') ?: 'School Attendance';
    $enc       = getSetting('smtp_encryption') ?: 'tls';

    $db = getDB();
    $stmt = $db->prepare("INSERT INTO email_notifications (student_id, notification_type, recipient_email, subject, message, status) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$studentId, $notificationType, $to, $subject, $body, 'pending']);
    $notifId = $db->lastInsertId();

    $success = false;

    // 1. Try external email API first (as configured)
    if (!empty($apiUrl) && !empty($apiKey)) {
        try {
            $success = sendExternalEmail($apiUrl, $apiKey, $apiHeader, $to, $subject, $body);
        } catch (Exception $e) {
            $success = false;
        }
    }

    // 2. Fallback to SMTP
    if (!$success && !empty($host) && !empty($user) && !empty($pass)) {
        try {
            $success = sendSMTPMail($host, $port, $user, $pass, $fromName, $to, $subject, $body, $enc);
        } catch (Exception $e) {
            $success = false;
        }
    }

    $db->prepare("UPDATE email_notifications SET status = ?, sent_at = NOW() WHERE id = ?")
       ->execute([$success ? 'sent' : 'failed', $notifId]);

    auditLog($_SESSION['user_id'] ?? null, 'EMAIL_NOTIFICATION', 'email_notifications',
        ($success ? 'Sent' : 'Failed') . " email to {$to} (type: {$notificationType})");

    return $success;
}

function sendExternalEmail($url, $apiKey, $headerName, $to, $subject, $body) {
    $payload = json_encode(['to' => $to, 'subject' => $subject, 'body' => $body]);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', "$headerName: $apiKey"],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response   = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        if ($response === false || $statusCode < 200 || $statusCode >= 300) {
            throw new Exception('External API failed: ' . ($curlError ?: $response));
        }
        return true;
    }

    $context = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\n$headerName: $apiKey\r\n",
        'content' => $payload,
        'timeout' => 30,
    ]]);
    $result = @file_get_contents($url, false, $context);
    preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0] ?? '', $m);
    $code = (int)($m[1] ?? 0);
    if ($result === false || $code < 200 || $code >= 300) {
        throw new Exception('External API failed: HTTP ' . $code);
    }
    return true;
}

function sendSMTPMail($host, $port, $user, $pass, $fromName, $to, $subject, $body, $enc = 'tls') {
    $prefix = ($enc === 'ssl') ? 'ssl://' : '';
    $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 30);
    if (!$socket) throw new Exception("SMTP connect failed: $errstr ($errno)");

    $read = fgets($socket, 515);
    if (substr($read, 0, 3) != '220') throw new Exception("SMTP: $read");

    fputs($socket, "EHLO " . php_uname('n') . "\r\n");
    while ($line = fgets($socket, 515)) { if ($line[3] == ' ') break; }

    if ($enc === 'tls') {
        fputs($socket, "STARTTLS\r\n"); fgets($socket, 515);
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        fputs($socket, "EHLO " . php_uname('n') . "\r\n");
        while ($line = fgets($socket, 515)) { if ($line[3] == ' ') break; }
    }

    fputs($socket, "AUTH LOGIN\r\n");          fgets($socket, 515);
    fputs($socket, base64_encode($user) . "\r\n"); fgets($socket, 515);
    fputs($socket, base64_encode($pass) . "\r\n");
    $authResp = fgets($socket, 515);
    if (substr($authResp, 0, 3) != '235') throw new Exception("SMTP AUTH failed: $authResp");

    fputs($socket, "MAIL FROM:<$user>\r\n");  fgets($socket, 515);
    fputs($socket, "RCPT TO:<$to>\r\n");      fgets($socket, 515);
    fputs($socket, "DATA\r\n");               fgets($socket, 515);

    $msg  = "From: $fromName <$user>\r\nTo: $to\r\nSubject: $subject\r\n";
    $msg .= "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n";
    $msg .= $body . "\r\n.\r\n";
    fputs($socket, $msg);
    $resp = fgets($socket, 515);
    fputs($socket, "QUIT\r\n");
    fclose($socket);
    return substr($resp, 0, 3) == '250';
}

function sendAbsentNotification($student) {
    $schoolName = getSetting('school_name') ?: 'Our School';
    $date = date('d M Y');
    $section = !empty($student['section']) ? '-' . $student['section'] : '';
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
        <p>Your ward <strong>{$student['name']}</strong>
        (Class {$student['class']}{$section}, Roll No: {$student['roll_number']})
        was <strong style='color:#e53e3e;'>absent</strong> today, <strong>$date</strong>.</p>
        <div style='background:white;border-left:4px solid #e53e3e;padding:15px;margin:20px 0;'>
            <p style='margin:0;'><strong>Student:</strong> {$student['name']}</p>
            <p style='margin:5px 0 0;'><strong>Class:</strong> {$student['class']}{$section}</p>
            <p style='margin:5px 0 0;'><strong>Date:</strong> $date</p>
            <p style='margin:5px 0 0;'><strong>Status:</strong> <span style='color:#e53e3e;'>Absent</span></p>
        </div>
        <p>If this is a mistake, please contact the school office.</p>
    </div>
    <div style='background:#1e3a5f;padding:15px;text-align:center;'>
        <p style='color:#90cdf4;margin:0;font-size:12px;'>$schoolName | Automated Attendance System</p>
    </div>
    </body></html>";

    return sendEmail($student['parent_email'], $subject, $body, $student['id']);
}

function processAbsentStudents() {
    $db     = getDB();
    $cutoff = getSetting('cutoff_time') ?: '09:30:00';
    $today  = date('Y-m-d');
    $now    = date('H:i:s');

    if ($now < $cutoff) return 0;

    $stmt = $db->prepare("
        SELECT s.* FROM students s
        LEFT JOIN student_attendance sa ON sa.student_id = s.id AND sa.date = ?
        WHERE sa.id IS NULL AND s.is_active = 1 AND s.parent_email IS NOT NULL AND s.parent_email != ''
    ");
    $stmt->execute([$today]);
    $absentStudents = $stmt->fetchAll();

    foreach ($absentStudents as $student) {
        $db->prepare("INSERT INTO student_attendance (student_id, date, status, marked_by) VALUES (?,?,'absent','system')")
           ->execute([$student['id'], $today]);
        sendAbsentNotification($student);
    }
    return count($absentStudents);
}
