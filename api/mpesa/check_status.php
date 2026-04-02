<?php
/**
 * M-Pesa Payment Status Check Endpoint
 * 
 * Check the status of an M-Pesa payment transaction
 * 
 * GET /api/mpesa/check_status.php?transaction_id=123
 * GET /api/mpesa/check_status.php?booking_id=456
 */

header('Content-Type: application/json');
session_start();

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    $transaction_id = isset($_GET['transaction_id']) ? (int)$_GET['transaction_id'] : null;
    $booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : null;
    
    if (!$transaction_id && !$booking_id) {
        throw new Exception('Either transaction_id or booking_id is required');
    }
    
    // Build query based on provided parameter
    if ($transaction_id) {
        $query = "
            SELECT 
                mt.id,
                mt.booking_id,
                mt.merchant_request_id,
                mt.checkout_request_id,
                mt.transaction_id as mpesa_receipt,
                mt.phone_number,
                mt.amount,
                mt.account_reference,
                mt.transaction_desc,
                mt.status,
                mt.result_code,
                mt.result_desc,
                mt.callback_received,
                mt.initiated_at,
                mt.completed_at,
                b.booking_reference,
                b.payment_status as booking_payment_status,
                b.status as booking_status
            FROM mpesa_transactions mt
            LEFT JOIN bookings b ON mt.booking_id = b.id
            WHERE mt.id = ? AND b.user_id = ?
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $transaction_id, $user_id);
    } else {
        $query = "
            SELECT 
                mt.id,
                mt.booking_id,
                mt.merchant_request_id,
                mt.checkout_request_id,
                mt.transaction_id as mpesa_receipt,
                mt.phone_number,
                mt.amount,
                mt.account_reference,
                mt.transaction_desc,
                mt.status,
                mt.result_code,
                mt.result_desc,
                mt.callback_received,
                mt.initiated_at,
                mt.completed_at,
                b.booking_reference,
                b.payment_status as booking_payment_status,
                b.status as booking_status
            FROM mpesa_transactions mt
            LEFT JOIN bookings b ON mt.booking_id = b.id
            WHERE mt.booking_id = ? AND b.user_id = ?
            ORDER BY mt.created_at DESC
            LIMIT 1
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $booking_id, $user_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Transaction not found or access denied');
    }
    
    $transaction = $result->fetch_assoc();
    
    // Determine user-friendly status message
    $status_message = match($transaction['status']) {
        'pending' => 'Payment request initiated. Waiting for customer action.',
        'processing' => 'Payment request sent to phone. Please enter M-Pesa PIN.',
        'completed' => 'Payment completed successfully.',
        'failed' => 'Payment failed. ' . ($transaction['result_desc'] ?? 'Please try again.'),
        'cancelled' => 'Payment was cancelled.',
        'timeout' => 'Payment request timed out. Please try again.',
        default => 'Unknown status'
    };
    
    // Return transaction details
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'transaction_id' => $transaction['id'],
            'booking_id' => $transaction['booking_id'],
            'booking_reference' => $transaction['booking_reference'],
            'mpesa_receipt' => $transaction['mpesa_receipt'],
            'phone_number' => $transaction['phone_number'],
            'amount' => (float)$transaction['amount'],
            'status' => $transaction['status'],
            'status_message' => $status_message,
            'result_code' => $transaction['result_code'],
            'result_desc' => $transaction['result_desc'],
            'callback_received' => (bool)$transaction['callback_received'],
            'booking_payment_status' => $transaction['booking_payment_status'],
            'booking_status' => $transaction['booking_status'],
            'initiated_at' => $transaction['initiated_at'],
            'completed_at' => $transaction['completed_at']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
