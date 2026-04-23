<?php
// Temporary debug endpoint - REMOVE AFTER FIXING
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.cookie_lifetime', 86400);
    ini_set('session.cookie_samesite', 'Lax');
    $session_path = sys_get_temp_dir() . '/php_sessions';
    if (!is_dir($session_path)) @mkdir($session_path, 0777, true);
    ini_set('session.save_path', $session_path);
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    ini_set('session.cookie_secure', $is_https ? 1 : 0);
    session_start();
}
header('Content-Type: application/json');
echo json_encode([
    'session_id'       => session_id(),
    'session_status'   => session_status(),
    'session_save_path'=> ini_get('session.save_path'),
    'sys_temp_dir'     => sys_get_temp_dir(),
    'session_data'     => $_SESSION,
    'cookie_header'    => $_SERVER['HTTP_COOKIE'] ?? 'none',
    'php_session_name' => session_name(),
]);
