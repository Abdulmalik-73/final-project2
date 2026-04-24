<?php
/**
 * Secure Logout Implementation
 * Properly destroys session and prevents back-button access
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Prevent caching to avoid back-button access
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

// Log the logout attempt (optional)
if (isset($_SESSION['user_id'])) {
    error_log("User logout: ID " . $_SESSION['user_id'] . " at " . date('Y-m-d H:i:s'));
}

// Step 1: Unset all session variables
$_SESSION = array();

// Step 2: Delete the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Step 3: Destroy the session
session_destroy();

// Step 4: Regenerate session ID to prevent session fixation
session_start();
session_regenerate_id(true);
session_destroy();

// Step 5: Clear any additional authentication cookies
$cookies_to_clear = ['remember_token', 'auth_token', 'user_session'];
foreach ($cookies_to_clear as $cookie) {
    if (isset($_COOKIE[$cookie])) {
        setcookie($cookie, '', time() - 3600, '/');
        unset($_COOKIE[$cookie]);
    }
}

// Step 6: Redirect to login page
header('Location: login.php?logout=success');
exit();
?>