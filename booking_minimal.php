<?php
/**
 * Minimal booking.php to isolate the 500 error
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Booking Test</title></head><body>";
echo "<h1>Booking Page Test</h1>";

try {
    // Test 1: Session
    echo "<p>1. Starting session...</p>";
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    echo "<p>✅ Session started</p>";
    
    // Test 2: Config
    echo "<p>2. Loading config...</p>";
    require_once 'includes/config.php';
    echo "<p>✅ Config loaded</p>";
    
    // Test 3: Functions
    echo "<p>3. Loading functions...</p>";
    require_once 'includes/functions.php';
    echo "<p>✅ Functions loaded</p>";
    
    // Test 4: Room lookup
    echo "<p>4. Testing room lookup...</p>";
    $room_id = isset($_GET['room']) ? (int)$_GET['room'] : 26;
    $room = get_room_by_id($room_id);
    if ($room) {
        echo "<p>✅ Room found: " . htmlspecialchars($room['name']) . "</p>";
    } else {
        echo "<p>❌ Room not found</p>";
    }
    
    // Test 5: Translation
    echo "<p>5. Testing translations...</p>";
    $title = __('booking.title');
    echo "<p>✅ Translation works: " . htmlspecialchars($title) . "</p>";
    
    // Test 6: Basic form
    echo "<h2>Basic Booking Form</h2>";
    echo "<form method='GET' action='booking.php'>";
    echo "<label>Room ID: <input type='number' name='room' value='" . $room_id . "'></label><br>";
    echo "<button type='submit'>Test Booking Page</button>";
    echo "</form>";
    
    if ($room) {
        echo "<h3>Room Details:</h3>";
        echo "<ul>";
        echo "<li>Name: " . htmlspecialchars($room['name']) . "</li>";
        echo "<li>Price: ETB " . number_format($room['price'], 2) . "</li>";
        echo "<li>Capacity: " . $room['capacity'] . " customers</li>";
        echo "</ul>";
    }
    
    echo "<p><strong>If you see this message, the basic includes are working!</strong></p>";
    echo "<p><a href='booking.php?room=$room_id'>Try Full Booking Page</a></p>";
    
} catch (Exception $e) {
    echo "<p>❌ Exception: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
} catch (Error $e) {
    echo "<p>❌ Fatal Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
}

echo "</body></html>";
?>