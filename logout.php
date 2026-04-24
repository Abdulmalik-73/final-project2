<?php
// Professional logout functionality using auth system
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Store user info before destroying session (for logging)
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'Unknown';

// Log logout activity if functions are available
if ($user_id) {
    try {
        require_once 'includes/config.php';
        require_once 'includes/functions.php';
        log_user_activity($user_id, 'logout', 'User logged out', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
        error_log("Logout activity logged for user: $user_name (ID: $user_id)");
    } catch (Exception $e) {
        // Log error but don't stop logout process
        error_log("Logout activity logging failed: " . $e->getMessage());
    }
}

// Use the secure logout function from auth.php
require_once 'includes/auth.php';
secure_logout('login.php');
?>
