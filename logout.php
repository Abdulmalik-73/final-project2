<?php
/**
 * Secure Logout - Completely destroys session
 */

// Must start session before we can destroy it
if (session_status() == PHP_SESSION_NONE) {
    // Use same session name as config.php
    session_name('HARAR_RAS_SESSION');
    session_start();
}

// Properly destroy session in correct order
session_unset();
session_destroy();

// Overwrite the session cookie with an expired one
// Use the exact same parameters as config.php set them
$params = session_get_cookie_params();
setcookie(
    session_name(),
    '',
    time() - 86400,   // 1 day in the past
    $params['path']   ?: '/',
    $params['domain'] ?: '',
    $params['secure'],
    $params['httponly']
);

// Also clear with root path to be safe
setcookie(session_name(), '', time() - 86400, '/');

// Clear all session variables
$_SESSION = [];

// Prevent any cached page from being served
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

// Redirect to login
header('Location: login.php?logout=success');
exit();
?>