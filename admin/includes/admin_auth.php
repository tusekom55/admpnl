<?php
// Admin authentication and security
require_once 'admin_functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
function requireAdmin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
    
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
        header('Location: ../index.php');
        exit();
    }
}

// Generate CSRF token for admin forms
function getAdminCSRFToken() {
    if (!isset($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf_token'];
}

// Verify CSRF token
function verifyAdminCSRFToken($token) {
    return isset($_SESSION['admin_csrf_token']) && hash_equals($_SESSION['admin_csrf_token'], $token);
}

// Log admin activity
function logAdminActivity($action, $details = '') {
    if (isset($_SESSION['user_id'])) {
        logActivity($_SESSION['user_id'], 'admin_' . $action, $details);
    }
}

// Check admin permissions for specific actions
function hasAdminPermission($action) {
    // For now, all admins have all permissions
    // This can be extended for role-based permissions
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}
?>
