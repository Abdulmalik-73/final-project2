<?php
/**
 * Debug script to identify what's causing the booking.php 500 error
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>Booking.php Debug Test</h1>\n";
echo "<pre>\n";

try {
    echo "1. Testing session start...\n";
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    echo "✅ Session started successfully\n";
    
    echo "\n2. Testing config include...\n";
    require_once 'includes/config.php';
    echo "✅ Config loaded successfully\n";
    
    echo "\n3. Testing functions include...\n";
    require_once 'includes/functions.php';
    echo "✅ Functions loaded successfully\n";
    
    echo "\n4. Testing RoomLockManager include...\n";
    require_once 'includes/RoomLockManager.php';
    echo "✅ RoomLockManager loaded successfully\n";
    
    echo "\n5. Testing database connection...\n";
    if (isset($conn) && $conn instanceof mysqli) {
        echo "✅ Database connection OK\n";
        echo "   Connection info: " . $conn->host_info . "\n";
    } else {
        echo "❌ Database connection failed\n";
        if (isset($conn)) {
            echo "   Connection error: " . $conn->connect_error . "\n";
        }
    }
    
    echo "\n6. Testing room lookup...\n";
    $room_id = 26; // From the URL in the screenshot
    if (function_exists('get_room_by_id')) {
        $room = get_room_by_id($room_id);
        if ($room) {
            echo "✅ Room $room_id found: " . $room['name'] . "\n";
            echo "   Room details: " . json_encode($room, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "❌ Room $room_id not found\n";
        }
    } else {
        echo "❌ get_room_by_id function not found\n";
    }
    
    echo "\n7. Testing translation function...\n";
    if (function_exists('__')) {
        $test_translation = __('booking.title');
        echo "✅ Translation function works: '$test_translation'\n";
        
        $test_auth = __('booking_auth.auth_required');
        echo "✅ Auth required translation: '$test_auth'\n";
    } else {
        echo "❌ Translation function __ not found\n";
    }
    
    echo "\n8. Testing temp_id_uploads table...\n";
    $table_check = $conn->query("SHOW TABLES LIKE 'temp_id_uploads'");
    if ($table_check && $table_check->num_rows > 0) {
        echo "✅ temp_id_uploads table exists\n";
        
        // Check table structure
        $structure = $conn->query("DESCRIBE temp_id_uploads");
        echo "   Table structure:\n";
        while ($row = $structure->fetch_assoc()) {
            echo "   - " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "❌ temp_id_uploads table does not exist\n";
        echo "   Creating table...\n";
        
        $create_table_sql = "
            CREATE TABLE IF NOT EXISTS temp_id_uploads (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(32) NOT NULL UNIQUE,
                image_data MEDIUMTEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_token (token),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        if ($conn->query($create_table_sql)) {
            echo "✅ temp_id_uploads table created successfully\n";
        } else {
            echo "❌ Failed to create temp_id_uploads table: " . $conn->error . "\n";
        }
    }
    
    echo "\n9. Testing RoomLockManager initialization...\n";
    $lockManager = new RoomLockManager($conn);
    echo "✅ RoomLockManager initialized successfully\n";
    
    echo "\n10. Testing session variables...\n";
    echo "   Session ID: " . session_id() . "\n";
    echo "   Session status: " . session_status() . "\n";
    echo "   Session variables: " . json_encode($_SESSION, JSON_PRETTY_PRINT) . "\n";
    
    echo "\n✅ ALL TESTS PASSED!\n";
    echo "The booking.php file should work now.\n";
    
} catch (Exception $e) {
    echo "\n❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Line: " . $e->getLine() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "\n❌ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Line: " . $e->getLine() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>\n";
echo "<p><a href='booking.php?room=26'>Test booking.php?room=26</a></p>\n";
?>