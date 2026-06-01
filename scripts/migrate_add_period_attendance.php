<?php
// Migration: add period support to student_attendance
require_once __DIR__ . "/../config.php";

try {
    $db = getDB();

    // Drop old unique index if exists and add period columns
    $db->exec("ALTER TABLE student_attendance DROP INDEX unique_student_date;");
    $db->exec("ALTER TABLE student_attendance ADD COLUMN period TINYINT NULL AFTER date, ADD COLUMN period_start TIME NULL AFTER period, ADD COLUMN period_end TIME NULL AFTER period_start;");

    // Add new unique constraint per student/date/period
    $db->exec("ALTER TABLE student_attendance ADD UNIQUE KEY unique_student_date_period (student_id, date, period);");

    echo "Migration completed: period columns added.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
