<?php
/**
 * Health check endpoint for Render.com
 * Must respond quickly with HTTP 200 — no heavy DB operations
 */
header('Content-Type: application/json');
http_response_code(200);

$health = [
    'status'      => 'ok',
    'timestamp'   => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'server'      => $_SERVER['SERVER_SOFTWARE'] ?? 'Apache',
];

// Quick DB ping — load config but don't run auto-fix queries
try {
    // Load env only (no auto-fix, no session)
    $env_file = __DIR__ . '/.env';
    if (file_exists($env_file)) {
        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\"'");
            if (!defined($key)) define($key, $val);
        }
    }
    // Also read from system env (Render sets these)
    foreach (['DB_HOST','DB_PORT','DB_USER','DB_PASS','DB_NAME'] as $k) {
        $v = getenv($k);
        if ($v !== false && !defined($k)) define($k, $v);
    }

    if (defined('DB_HOST') && defined('DB_USER') && defined('DB_NAME')) {
        mysqli_report(MYSQLI_REPORT_OFF);
        $db = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)(DB_PORT ?? 3306));
        if (!$db->connect_error) {
            $health['database'] = 'connected';
            $db->close();
        } else {
            $health['database'] = 'unavailable';
            // Don't fail health check for DB — app still serves pages
        }
    } else {
        $health['database'] = 'config_missing';
    }
} catch (Exception $e) {
    $health['database'] = 'error';
}

echo json_encode($health, JSON_PRETTY_PRINT);
