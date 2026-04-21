<?php
/**
 * M-Pesa Payment Initiation Endpoint
 * 
 * Initiates STK Push to customer's phone
 * 
 * POST /api/mpesa/initiate_payment.php
 * 
 * Request Body:
 * {
 *   "booking_id": 123,
 *   "phone_number": "0973409026",
 *   "amount": 8000
 * }
 */

header('Content-Type: application/json');
session_start();

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/MpesaAPI.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in.']);
    exit;
}

try {
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    
    $booking_id = (int)($input['booking_id'] ?? 0);
    $phone_number = trim($input['phone_number'] ?? '');
    $amount = (float)($input['amount'] ?? 0);
    
    // Validate input
    if (empty($booking_id)) {
        throw new Exception('Booking ID is required');
    }
    
    if (empty($phone_number)) {
        throw new Exception('Phone number is required');
    }
    
    if ($amount <= 0) {
        throw new Exception('Invalid amount');
    }
    
    // Verify booking exists and belongs to user
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("
        SELECT id, booking_reference, total_price, status, payment_status, booking_type
        FROM bookings 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Booking not found or access denied');
    }
    
    $booking = $result->fetch_assoc();
    
    // Check if booking is already paid
    if ($booking['payment_status'] === 'paid') {
        throw new Exception('This booking has already been paid');
    }
    
    // Check if booking is cancelled
    if ($booking['status'] === 'cancelled') {
        throw new Exception('Cannot pay for a cancelled booking');
    }
    
    // Verify amount matches booking total
    if (abs($amount - $booking['total_price']) > 0.01) {
        throw new Exception('Payment amount does not match booking total');
    }
    
    // Determine transaction description based on booking type
    $booking_type = $booking['booking_type'] ?? 'room';
    $transaction_desc = match($booking_type) {
        'room' => 'Room Booking Payment',
        'food_order' => 'Food Order Payment',
        'service' => 'Service Payment',
        default => 'Hotel Payment'
    };
    
    // Initialize M-Pesa API
    $mpesa = new MpesaAPI($conn);
    
    // Initiate STK Push
    $response = $mpesa->initiateSTKPush(
        $phone_number,
        $amount,
        $booking_id,
        $booking['booking_reference'],
        $transaction_desc
    );
    
    // Log activity
    log_user_activity(
        $user_id,
        'payment',
        "M-Pesa payment initiated for booking {$booking['booking_reference']}. Amount: ETB " . number_format($amount, 2),
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    );
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Payment request sent successfully',
        'data' => $response
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log('[M-Pesa Payment Initiation Error] ' . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
