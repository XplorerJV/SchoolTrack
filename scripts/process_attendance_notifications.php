<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../email.php';

// This script is intended to be run by cron once after the school day ends
// Example: php scripts/process_attendance_notifications.php

$results = ['absent'=>0,'bunk'=>0];

// Process absent students (uses existing function)
if (function_exists('processAbsentStudents')) {
    $count = processAbsentStudents();
    $results['absent'] = is_numeric($count) ? (int)$count : 0;
}

// Process bunk students
if (function_exists('processBunkStudents')) {
    $count = processBunkStudents();
    $results['bunk'] = is_numeric($count) ? (int)$count : 0;
}

echo "Processed notifications:\n";
echo "Absent sent: " . $results['absent'] . "\n";
echo "Bunk handled: " . $results['bunk'] . "\n";

?>
