<?php
require_once __DIR__ . '/config.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) return false;
    if ((time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_destroy();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

// Require login
function requireLogin($redirect = '../index.php') {
    if (!isLoggedIn()) {
        header('Location: ' . $redirect);
        exit;
    }
}

// Require role
function requireRole($roles, $redirect = '../index.php') {
    requireLogin($redirect);
    if (!in_array($_SESSION['role'], (array)$roles)) {
        header('Location: ' . $redirect . '?error=unauthorized');
        exit;
    }
}

// Get current user
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id'   => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email'=> $_SESSION['user_email'],
        'role' => $_SESSION['role'],
    ];
}

// Login user
function loginUser($email, $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role']       = $user['role'];
        $_SESSION['last_activity'] = time();
        auditLog($user['id'], 'LOGIN', 'auth', 'User logged in');
        return $user;
    }
    return false;
}

// Logout
function logoutUser() {
    if (isLoggedIn()) {
        auditLog($_SESSION['user_id'], 'LOGOUT', 'auth', 'User logged out');
    }
    session_destroy();
}

// Audit log
function auditLog($userId, $action, $module, $description, $oldVal = null, $newVal = null) {
    try {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, module, description, old_value, new_value, ip_address) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$userId, $action, $module, $description, $oldVal, $newVal, $ip]);
    } catch (Exception $e) {
        // Silently fail logging
    }
}

// Get redirect by role
function getRoleDashboard($role) {
    switch ($role) {
        case 'superadmin': return 'superadmin/dashboard.php';
        case 'admin':     return 'admin/dashboard.php';
        case 'principal': return 'principal/dashboard.php';
        case 'teacher':   return 'teacher/dashboard.php';
        default:          return 'index.php';
    }
}
?>