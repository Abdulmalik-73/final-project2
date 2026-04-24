<?php
// Simple, safe logout functionality
session_start();

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Log logout activity if functions are available
if (isset($_SESSION['user_id'])) {
    try {
        require_once 'includes/config.php';
        require_once 'includes/functions.php';
        log_user_activity($_SESSION['user_id'], 'logout', 'User logged out', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
    } catch (Exception $e) {
        // Log error but don't stop logout process
        error_log("Logout activity logging failed: " . $e->getMessage());
    }
}

// Destroy all session data
$_SESSION = array();

// Delete the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear any authentication cookies
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Redirect to login page with success message
header('Location: login.php?logout=success');
exit();
?>
