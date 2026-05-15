<?php
require_once __DIR__ . '/../config.php';

$db = getDB();
try {
    $row = $db->query("SHOW COLUMNS FROM student_attendance LIKE 'status'")->fetch();
    if ($row) {
        $type = $row['Type'];
        if (strpos($type, "'bunk'") === false) {
            // extract existing enum values
            preg_match("/enum\((.*)\)/i", $type, $m);
            if (isset($m[1])) {
                $vals = $m[1];
                $newVals = $vals . ", 'bunk'";
                $sql = "ALTER TABLE student_attendance MODIFY COLUMN status ENUM($newVals) DEFAULT 'present'";
                $db->exec($sql);
                echo "Migration completed: added 'bunk' to status enum.\n";
            }
        } else {
            echo "Migration skipped: 'bunk' already present in status enum.\n";
        }
    } else {
        echo "Migration failed: status column not found.\n";
    }
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}

?>
