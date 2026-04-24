<?php
// Professional logout functionality
session_start();

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Log the logout (optional)
if (isset($_SESSION['user_id'])) {
    error_log("User logout: ID " . $_SESSION['user_id'] . " at " . date('Y-m-d H:i:s'));
}

// Complete session destruction
$_SESSION = array();

// Remove session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Try header redirect first
header('Location: login.php?logout=success');
exit();
?>