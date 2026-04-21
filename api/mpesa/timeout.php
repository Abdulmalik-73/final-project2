<?php
/**
 * M-Pesa Payment Timeout Endpoint
 * 
 * Receives timeout notification from Safaricom M-Pesa
 * 
 * POST /api/mpesa/timeout.php
 * 
 * This endpoint is called by M-Pesa servers when payment request times out
 */

header('Content-Type: application/json');

require_once '../../includes/config.php';
require_once '../../includes/MpesaAPI.php';

// Log all incoming requests for debugging
$raw_input = file_get_contents('php://input');
error_log('[M-Pesa Timeout] Received: ' . $raw_input);

try {
    // Parse timeout data
    $timeout_data = json_decode($raw_input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON payload');
    }
    
    // Extract timeout information
    $body = $timeout_data['Body'] ?? [];
    $stk_callback = $body['stkCallback'] ?? [];
    
    $merchant_request_id = $stk_callback['MerchantRequestID'] ?? null;
    $checkout_request_id = $stk_callback['CheckoutRequestID'] ?? null;
    
    // Update transaction status to timeout
    if ($checkout_request_id) {
        $stmt = $conn->prepare("
            UPDATE mpesa_transactions 
            SET status = 'timeout',
                result_code = '1032',
                result_desc = 'Request cancelled by user',
                callback_received = 1,
                callback_data = ?,
                completed_at = NOW()
            WHERE checkout_request_id = ?
        ");
        
        $callback_json = json_encode($timeout_data);
        $stmt->bind_param("ss", $callback_json, $checkout_request_id);
        $stmt->execute();
        
        // Log timeout callback
        $stmt = $conn->prepare("
            INSERT INTO mpesa_callback_logs (
                callback_type, merchant_request_id, checkout_request_id,
                result_code, result_desc, callback_data, ip_address
            ) VALUES ('timeout', ?, ?, '1032', 'Request cancelled by user', ?, ?)
        ");
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt->bind_param("ssss", $merchant_request_id, $checkout_request_id, $callback_json, $ip);
        $stmt->execute();
        
        error_log('[M-Pesa Timeout] Transaction marked as timeout: ' . $checkout_request_id);
    }
    
    // Return success response to M-Pesa
    http_response_code(200);
    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => 'Timeout notification received'
    ]);
    
} catch (Exception $e) {
    error_log('[M-Pesa Timeout Error] ' . $e->getMessage());
    
    // Return 200 to prevent M-Pesa from retrying
    http_response_code(200);
    echo json_encode([
        'ResultCode' => 1,
        'ResultDesc' => 'Error processing timeout'
    ]);
}
