<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { echo json_encode(['error'=>'unauthorized']); exit; }

$db    = getDB();
$today = date('Y-m-d');

// Overall today stats (period 1 only — main attendance)
$row = $db->prepare("SELECT
    SUM(status='present') as present,
    SUM(status='absent')  as absent,
    SUM(status='late')    as late,
    SUM(status='excused') as excused,
    COUNT(*)              as marked
FROM student_attendance WHERE date=? AND period=1");
$row->execute([$today]);
$totals = $row->fetch();

$totalStudents = $db->query("SELECT COUNT(*) FROM students WHERE is_active=1")->fetchColumn();

// Class-wise breakdown ordered 1-10
$stmt = $db->prepare("SELECT s.class,
    COUNT(DISTINCT s.id) as total,
    SUM(CASE WHEN sa.status='present' AND sa.period=1 THEN 1 ELSE 0 END) as present,
    SUM(CASE WHEN sa.status='absent'  AND sa.period=1 THEN 1 ELSE 0 END) as absent,
    SUM(CASE WHEN sa.status='late'    AND sa.period=1 THEN 1 ELSE 0 END) as late
FROM students s
LEFT JOIN student_attendance sa ON s.id=sa.student_id AND sa.date=?
WHERE s.is_active=1
GROUP BY s.class ORDER BY CAST(s.class AS UNSIGNED)");
$stmt->execute([$today]);
$classes = $stmt->fetchAll();

// Recent 8 attendance entries
$stmt = $db->prepare("SELECT sa.status, sa.time_in, sa.period, s.name, s.class, s.roll_number
FROM student_attendance sa
JOIN students s ON s.id=sa.student_id
WHERE sa.date=? ORDER BY sa.created_at DESC LIMIT 8");
$stmt->execute([$today]);
$recent = $stmt->fetchAll();

// Last updated time
echo json_encode([
    'date'          => date('l, d M Y'),
    'time'          => date('h:i:s A'),
    'total_students'=> (int)$totalStudents,
    'present'       => (int)($totals['present'] ?? 0),
    'absent'        => (int)($totals['absent']  ?? 0),
    'late'          => (int)($totals['late']    ?? 0),
    'excused'       => (int)($totals['excused'] ?? 0),
    'marked'        => (int)($totals['marked']  ?? 0),
    'not_marked'    => (int)$totalStudents - (int)($totals['marked'] ?? 0),
    'classes'       => $classes,
    'recent'        => $recent,
]);
