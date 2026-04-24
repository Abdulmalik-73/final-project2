<?php
/**
 * Health check script to diagnose HTTP 500 errors
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Health Check - Harar Ras Hotel</h1>";

// Test 1: Basic PHP functionality
echo "<h2>✅ PHP Working</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Current time: " . date('Y-m-d H:i:s') . "<br>";

// Test 2: Database connection
echo "<h2>🔌 Database Connection</h2>";
try {
    require_once 'includes/config.php';
    if ($conn->ping()) {
        echo "✅ Database connection successful<br>";
        echo "Database: " . $conn->server_info . "<br>";
    } else {
        echo "❌ Database connection failed<br>";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// Test 3: Session functionality
echo "<h2>🔐 Session</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "✅ Session working<br>";
echo "Session ID: " . session_id() . "<br>";

// Test 4: File system
echo "<h2>📁 File System</h2>";
$upload_dir = __DIR__ . '/uploads/ids/';
if (is_dir($upload_dir)) {
    echo "✅ Uploads directory exists: $upload_dir<br>";
    if (is_writable($upload_dir)) {
        echo "✅ Uploads directory is writable<br>";
    } else {
        echo "❌ Uploads directory is not writable<br>";
    }
} else {
    echo "❌ Uploads directory does not exist<br>";
}

// Test 5: Environment variables
echo "<h2>🌍 Environment Variables</h2>";
$db_vars = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME', 'DB_PORT'];
foreach ($db_vars as $var) {
    $value = getenv($var) ?: 'NOT SET';
    echo "$var: " . (strpos($var, 'PASS') !== false ? '[REDACTED]' : $value) . "<br>";
}

// Test 6: Required files
echo "<h2>📄 Required Files</h2>";
$required_files = [
    'includes/config.php',
    'includes/functions.php',
    'dashboard/receptionist.php',
    'dashboard/verify-id.php',
    'view-id.php',
    'api/upload_id.php'
];

foreach ($required_files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "✅ $file exists<br>";
    } else {
        echo "❌ $file missing<br>";
    }
}

echo "<hr>";
echo "<p><em>Health check completed. If any tests failed, those are likely causing the HTTP 500 error.</em></p>";
?>
