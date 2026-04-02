<?php
/**
 * M-Pesa Payment Callback Endpoint
 * 
 * Receives payment confirmation from Safaricom M-Pesa
 * 
 * POST /api/mpesa/callback.php
 * 
 * This endpoint is called by M-Pesa servers when payment is completed
 */

header('Content-Type: application/json');

require_once '../../includes/config.php';
require_once '../../includes/MpesaAPI.php';

// Log all incoming requests for debugging
$raw_input = file_get_contents('php://input');
error_log('[M-Pesa Callback] Received: ' . $raw_input);

try {
    // Parse callback data
    $callback_data = json_decode($raw_input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON payload');
    }
    
    // Initialize M-Pesa API
    $mpesa = new MpesaAPI($conn);
    
    // Process callback
    $result = $mpesa->processCallback($callback_data);
    
    if ($result['success']) {
        error_log('[M-Pesa Callback] Successfully processed. Result Code: ' . $result['result_code']);
        
        // Return success response to M-Pesa
        http_response_code(200);
        echo json_encode([
            'ResultCode' => 0,
            'ResultDesc' => 'Callback processed successfully'
        ]);
    } else {
        error_log('[M-Pesa Callback] Processing failed: ' . $result['error']);
        
        // Still return 200 to M-Pesa to prevent retries
        http_response_code(200);
        echo json_encode([
            'ResultCode' => 1,
            'ResultDesc' => 'Callback received but processing failed'
        ]);
    }
    
} catch (Exception $e) {
    error_log('[M-Pesa Callback Error] ' . $e->getMessage());
    
    // Return 200 to prevent M-Pesa from retrying
    http_response_code(200);
    echo json_encode([
        'ResultCode' => 1,
        'ResultDesc' => 'Error processing callback'
    ]);
}
