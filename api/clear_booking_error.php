<?php
/**
 * Clear Booking Error Session
 * Clears the max booking error from session after minimum display time
 */

session_start();
header('Content-Type: application/json');

// Check if error exists and minimum time has passed
if (isset($_SESSION['max_booking_error_time'])) {
    $error_time = $_SESSION['max_booking_error_time'];
    $current_time = time();
    $time_elapsed = $current_time - $error_time;
    $min_display_time = 180; // 3 minutes
    
    if ($time_elapsed >= $min_display_time) {
        // Clear the error
        unset($_SESSION['max_booking_error']);
        unset($_SESSION['max_booking_error_time']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Error cleared'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Minimum display time not reached',
            'remaining_time' => $min_display_time - $time_elapsed
        ]);
    }
} else {
    // No error to clear
    unset($_SESSION['max_booking_error']);
    unset($_SESSION['max_booking_error_time']);
    
    echo json_encode([
        'success' => true,
        'message' => 'No error to clear'
    ]);
}
