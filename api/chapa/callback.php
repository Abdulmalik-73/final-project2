<?php
/**
 * Chapa Payment Callback Handler
 * Handles payment verification after customer completes payment
 */

session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/services/ChapaPaymentService.php';

// Get transaction reference from query parameter
$tx_ref = $_GET['tx_ref'] ?? '';

if (empty($tx_ref)) {
    header('Location: ../../payment-upload.php?error=invalid_transaction');
    exit;
}

// Find booking by transaction reference
$query = "SELECT * FROM bookings WHERE transaction_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $tx_ref);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header('Location: ../../my-bookings.php?error=booking_not_found');
    exit;
}

// Initialize Chapa service
$chapa = new ChapaPaymentService();

// Verify payment
$verification = $chapa->verifyPayment($tx_ref);

if ($verification['verified'] === true) {
    // Payment successful - update booking
    $update_query = "UPDATE bookings SET 
                     payment_status = 'paid',
                     verification_status = 'verified',
                     verified_at = NOW(),
                     screenshot_uploaded_at = NOW()
                     WHERE id = ?";
    
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("i", $booking['id']);
    $update_stmt->execute();
    
    // Update room status to booked if it's a room booking
    if ($booking['booking_type'] == 'room' && !empty($booking['room_id'])) {
        $room_query = "UPDATE rooms SET status = 'booked' WHERE id = ?";
        $room_stmt = $conn->prepare($room_query);
        $room_stmt->bind_param("i", $booking['room_id']);
        $room_stmt->execute();
    }
    
    // Log the payment
    log_booking_activity(
        $booking['id'], 
        $booking['user_id'], 
        'payment_verified', 
        'pending_verification', 
        'verified', 
        'Payment verified via Chapa - Amount: ' . $verification['amount'] . ' ETB',
        $booking['user_id']
    );
    
    error_log("Chapa payment verified successfully for booking #" . $booking['id']);
    
    // Redirect to success page
    header('Location: ../../payment-success.php?booking=' . $booking['id'] . '&tx_ref=' . $tx_ref);
    exit;
    
} elseif ($verification['verified'] === 'pending') {
    // Payment still pending
    header('Location: ../../payment-upload.php?booking=' . $booking['id'] . '&status=pending');
    exit;
    
} else {
    // Payment failed
    $update_query = "UPDATE bookings SET 
                     verification_status = 'rejected'
                     WHERE id = ?";
    
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("i", $booking['id']);
    $update_stmt->execute();
    
    error_log("Chapa payment failed for booking #" . $booking['id']);
    
    header('Location: ../../payment-upload.php?booking=' . $booking['id'] . '&error=payment_failed');
    exit;
}
