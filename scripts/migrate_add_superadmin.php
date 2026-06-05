<?php
require_once __DIR__ . '/../config.php';

$db = getDB();

// 1. Add 'superadmin' to the users.role enum
try {
    $row = $db->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
    if ($row) {
        $type = $row['Type'];
        if (strpos($type, "'superadmin'") === false) {
            preg_match("/enum\((.*)\)/i", $type, $m);
            if (isset($m[1])) {
                $newVals = "'superadmin', " . $m[1];
                $db->exec("ALTER TABLE users MODIFY COLUMN role ENUM($newVals) NOT NULL DEFAULT 'teacher'");
                echo "Migration: added 'superadmin' to users.role enum.\n";
            }
        } else {
            echo "Migration skipped: 'superadmin' already in users.role enum.\n";
        }
    }
} catch (Exception $e) {
    echo "Role enum migration error: " . $e->getMessage() . "\n";
}

// 2. Create a default super admin account (password: password)
try {
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute(['superadmin@school.com']);
    if (!$stmt->fetch()) {
        $hash = password_hash('password', PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO users (name,email,password,role,phone,subject,employee_id,is_active) VALUES (?,?,?,?,?,?,?,1)")
           ->execute(['Super Admin', 'superadmin@school.com', $hash, 'superadmin', '', '', 'SADM001']);
        echo "Migration: created superadmin@school.com (password: password).\n";
    } else {
        echo "Migration skipped: superadmin@school.com already exists.\n";
    }
} catch (Exception $e) {
    echo "Super admin user migration error: " . $e->getMessage() . "\n";
}

// 3. Seed new feature/subscription settings
try {
    foreach ([
        'sms_notifications' => '0',
        'subscription_plan' => 'Free',
    ] as $key => $default) {
        if (getSetting($key) === null) {
            setSetting($key, $default);
            echo "Migration: added setting '$key' = '$default'.\n";
        } else {
            echo "Migration skipped: setting '$key' already exists.\n";
        }
    }
} catch (Exception $e) {
    echo "Settings migration error: " . $e->getMessage() . "\n";
}

echo "Done.\n";
?>
