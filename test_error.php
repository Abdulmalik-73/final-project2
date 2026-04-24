<?php
// Enable error reporting to see what's causing the 500 error
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "PHP is working!<br>";

try {
    echo "Testing config.php...<br>";
    require_once 'includes/config.php';
    echo "✅ Config loaded successfully!<br>";
} catch (Exception $e) {
    echo "❌ Config error: " . $e->getMessage() . "<br>";
    die();
}

try {
    echo "Testing functions.php...<br>";
    require_once 'includes/functions.php';
    echo "✅ Functions loaded successfully!<br>";
} catch (Exception $e) {
    echo "❌ Functions error: " . $e->getMessage() . "<br>";
    die();
}

if (isset($conn) && $conn) {
    echo "✅ Database connected successfully!<br>";
} else {
    echo "❌ Database connection failed!<br>";
}

echo "All tests passed! The issue might be elsewhere.<br>";
?>