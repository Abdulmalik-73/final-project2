<?php
// Simple health check endpoint for Render
header('Content-Type: application/json');

$health = [
    'status' => 'ok',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
];

// Check database connection
try {
    require_once 'includes/config.php';
    if ($conn && $conn->ping()) {
        $health['database'] = 'connected';
    } else {
        $health['database'] = 'disconnected';
        $health['status'] = 'error';
    }
} catch (Exception $e) {
    $health['database'] = 'error: ' . $e->getMessage();
    $health['status'] = 'error';
}

echo json_encode($health, JSON_PRETTY_PRINT);
?>