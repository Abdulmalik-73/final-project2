<?php
/**
 * Debug script to check session status and variables
 */

// Start session with same configuration as main app
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.cookie_lifetime', 86400);
    ini_set('session.cookie_samesite', 'Lax');

    $session_path = sys_get_temp_dir() . '/php_sessions';
    if (!is_dir($session_path)) {
        @mkdir($session_path, 0777, true);
    }
    ini_set('session.save_path', $session_path);

    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    ini_set('session.cookie_secure', $is_https ? 1 : 0);
    
    session_name('HARAR_RAS_SESSION');
    session_start();
}

header('Content-Type: application/json');

$debug_info = [
    'session_status' => session_status(),
    'session_id' => session_id(),
    'session_name' => session_name(),
    'session_save_path' => session_save_path(),
    'session_cookie_params' => session_get_cookie_params(),
    'session_variables' => $_SESSION,
    'is_logged_in' => isset($_SESSION['user_id']) && !empty($_SESSION['user_id']),
    'user_id' => $_SESSION['user_id'] ?? 'not set',
    'user_role' => $_SESSION['user_role'] ?? 'not set',
    'user_name' => $_SESSION['user_name'] ?? 'not set',
    'server_info' => [
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'not set',
        'HTTPS' => $_SERVER['HTTPS'] ?? 'not set',
        'HTTP_X_FORWARDED_PROTO' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'not set',
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'not set',
        'USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? 'not set'
    ],
    'cookies' => $_COOKIE,
    'timestamp' => date('Y-m-d H:i:s')
];

echo json_encode($debug_info, JSON_PRETTY_PRINT);
?>