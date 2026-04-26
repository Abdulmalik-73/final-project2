<?php
/**
 * Secure Logout - Completely destroys session with AGGRESSIVE cache prevention
 */

// MUST be first - before any output
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Must start session before we can destroy it
if (session_status() == PHP_SESSION_NONE) {
    // Use same session name as config.php
    session_name('HARAR_RAS_SESSION');
    session_start();
}

// AGGRESSIVE session destruction
// Step 1: Clear all session variables
$_SESSION = [];

// Step 2: Unset all session variables
session_unset();

// Step 3: Destroy the session file on server
session_destroy();

// Step 4: Clear session cookie - MULTIPLE WAYS
$params = session_get_cookie_params();

// Method 1: Clear with exact parameters
setcookie(
    session_name(),
    '',
    time() - 86400,
    $params['path']   ?: '/',
    $params['domain'] ?: '',
    $params['secure'],
    $params['httponly']
);

// Method 2: Clear with root path
setcookie(session_name(), '', time() - 86400, '/');

// Method 3: Clear with empty domain
setcookie(session_name(), '', time() - 86400, '/', '');

// Step 5: Clear all possible session-related cookies
$cookies_to_clear = [
    'HARAR_RAS_SESSION',
    'PHPSESSID',
    'remember_token',
    'auth_token',
    'user_session',
    'session_id'
];

foreach ($cookies_to_clear as $cookie_name) {
    setcookie($cookie_name, '', time() - 86400, '/');
    setcookie($cookie_name, '', time() - 86400, '/', '');
}

// Step 6: Clear browser cache
header("Clear-Site-Data: \"cache\", \"cookies\", \"storage\"");

// Redirect to login with success message
header('Location: login.php?logout=success&t=' . time());
exit();
?>