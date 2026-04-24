<?php
// Simple test to check if PHP is working
echo "PHP is working!<br>";
echo "PHP Version: " . phpversion() . "<br>";

// Test if we can include config
try {
    require_once 'includes/config.php';
    echo "Config loaded successfully!<br>";
} catch (Exception $e) {
    echo "Config error: " . $e->getMessage() . "<br>";
}

// Test if we can include functions
try {
    require_once 'includes/functions.php';
    echo "Functions loaded successfully!<br>";
} catch (Exception $e) {
    echo "Functions error: " . $e->getMessage() . "<br>";
}

// Test database connection
if (isset($conn) && $conn) {
    echo "Database connected successfully!<br>";
} else {
    echo "Database connection failed!<br>";
}
?>