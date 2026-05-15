<?php
require_once __DIR__ . '/../config.php';

$db = getDB();
try {
    // MySQL doesn't support IF NOT EXISTS for ALTER COLUMN in older versions; use safe check
    $row = $db->query("SHOW COLUMNS FROM student_attendance LIKE 'time_out'")->fetch();
    if (!$row) {
        $db->exec("ALTER TABLE student_attendance ADD COLUMN time_out TIME NULL COMMENT 'Optional logout/checkout time'");
        echo "Migration completed: time_out column added.\n";
    } else {
        echo "Migration skipped: time_out column already exists.\n";
    }
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}

?>
