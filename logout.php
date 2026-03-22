<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Log logout activity before destroying session (non-blocking)
if (isset($_SESSION['user_id'])) {
    try {
        log_user_activity($_SESSION['user_id'], 'logout', 'User logged out', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
    } catch (Exception $e) {
        // Log error but don't stop logout process
        error_log("Logout activity logging failed: " . $e->getMessage());
    }
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Set a success message for the next page
session_start();
$_SESSION['logout_message'] = 'You have been successfully logged out.';

// Redirect to home page
header('Location: index.php');
exit();
?>
