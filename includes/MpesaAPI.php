<?php
/**
 * Safaricom Ethiopia M-Pesa API Integration Class
 * 
 * Handles:
 * - Access token generation and caching
 * - STK Push (C2B Payment)
 * - Payment verification
 * - Callback processing
 * - Error handling and logging
 * 
 * @author Harar Ras Hotel Development Team
 * @version 1.0.0
 */

class MpesaAPI {
    private $conn;
    private $base_url;
    private $client_id;
    private $client_secret;
    private $business_shortcode;
    private $passkey;
    private $callback_url;
    private $timeout_url;
    private $environment;
    
    /**
     * Constructor - Initialize M-Pesa API configuration
     */
    public function __construct($db_connection) {
        $this->conn = $db_connection;
        
        // Load configuration from environment variables
        $this->environment = getenv('MPESA_ENV') ?: 'sandbox';
        $this->base_url = getenv('MPESA_BASE_URL') ?: 'https://apisandbox.safaricom.et';
        $this->client_id = getenv('MPESA_CLIENT_ID');
        $this->client_secret = getenv('MPESA_CLIENT_SECRET');
        $this->business_shortcode = getenv('MPESA_BUSINESS_SHORTCODE') ?: '6564';
        $this->passkey = getenv('MPESA_PASSKEY');
        $this->callback_url = getenv('MPESA_CALLBACK_URL');
        $this->timeout_url = getenv('MPESA_TIMEOUT_URL');
        
        // Validate required configuration
        if (empty($this->client_id) || empty($this->client_secret)) {
            throw new Exception('M-Pesa API credentials not configured. Please check .env file.');
        }
    }
    
    /**
     * Generate and cache access token
     * 
     * @return string Access token
     * @throws Exception if token generation fails
     */
    public function getAccessToken() {
        // Check if we have a valid cached token
        $stmt = $this->conn->prepare("CALL get_valid_mpesa_token(@token, @is_valid)");
        $stmt->execute();
        
        $result = $this->conn->query("SELECT @token as token, @is_valid as is_valid");
        $row = $result->fetch_assoc();
        
        if ($row['is_valid'] == 1 && !empty($row['token'])) {
            $this->logDebug('Using cached M-Pesa access token');
            return $row['token'];
        }
        
        // Generate new token
        $this->logDebug('Generating new M-Pesa access token');
        
        $url = $this->base_url . '/v1/token/generate?grant_type=client_credentials';
        $credentials = base64_encode($this->client_id . ':' . $this->client_secret);
        
        $start_time = microtime(true);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->environment === 'production');
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $execution_time = microtime(true) - $start_time;
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        // Log API call
        $this->logAPICall(
            'GET',
            $url,
            ['Authorization' => 'Basic [REDACTED]'],
            null,
            $http_code,
            $response,
            $execution_time,
            $curl_error
        );
        
        if ($curl_error) {
            throw new Exception('M-Pesa API connection error: ' . $curl_error);
        }
        
        $response_data = json_decode($response, true);
        
        if ($http_code !== 200 || !isset($response_data['access_token'])) {
            $error_msg = $response_data['error_description'] ?? 'Failed to generate access token';
            throw new Exception('M-Pesa token generation failed: ' . $error_msg);
        }
        
        // Store token in database
        $stmt = $this->conn->prepare("CALL store_mpesa_token(?, ?)");
        $stmt->bind_param("si", $response_data['access_token'], $response_data['expires_in']);
        $stmt->execute();
        
        $this->logDebug('New M-Pesa access token generated and cached');
        
        return $response_data['access_token'];
    }
    
    /**
     * Initiate STK Push (C2B Payment)
     * 
     * @param string $phone_number Customer phone number
     * @param float $amount Amount to charge
     * @param int $booking_id Booking ID reference
     * @param string $account_reference Account reference (booking reference)
     * @param string $transaction_desc Transaction description
     * @return array Response data with transaction details
     * @throws Exception if STK push fails
     */
    public function initiateSTKPush($phone_number, $amount, $booking_id, $account_reference, $transaction_desc = 'Hotel Payment') {
        // Format phone number to Ethiopian format (251XXXXXXXXX)
        $formatted_phone = $this->formatPhoneNumber($phone_number);
        
        // Validate amount
        if ($amount <= 0) {
            throw new Exception('Invalid amount. Amount must be greater than 0.');
        }
        
        // Get access token
        $access_token = $this->getAccessToken();
        
        // Generate password and timestamp
        $timestamp = date('YmdHis');
        $password = base64_encode($this->business_shortcode . $this->passkey . $timestamp);
        
        // Prepare request payload
        $payload = [
            'BusinessShortCode' => $this->business_shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => round($amount, 2),
            'PartyA' => $formatted_phone,
            'PartyB' => $this->business_shortcode,
            'PhoneNumber' => $formatted_phone,
            'CallBackURL' => $this->callback_url,
            'AccountReference' => $account_reference,
            'TransactionDesc' => $transaction_desc
        ];
        
        $url = $this->base_url . '/mpesa/stkpush/v3/processrequest';
        
        $start_time = microtime(true);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->environment === 'production');
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $execution_time = microtime(true) - $start_time;
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        $response_data = json_decode($response, true);
        
        // Store transaction in database
        $transaction_id = $this->storeTransaction(
            $booking_id,
            $response_data['MerchantRequestID'] ?? null,
            $response_data['CheckoutRequestID'] ?? null,
            $formatted_phone,
            $amount,
            $account_reference,
            $transaction_desc,
            'C2B',
            $http_code === 200 ? 'processing' : 'failed',
            $response_data['ResponseCode'] ?? null,
            $response_data['ResponseDescription'] ?? null,
            $payload,
            $response_data,
            $curl_error
        );
        
        // Log API call
        $this->logAPICall(
            'POST',
            $url,
            ['Authorization' => 'Bearer [REDACTED]'],
            $payload,
            $http_code,
            $response,
            $execution_time,
            $curl_error,
            $transaction_id
        );
        
        if ($curl_error) {
            throw new Exception('M-Pesa API connection error: ' . $curl_error);
        }
        
        if ($http_code !== 200 || !isset($response_data['CheckoutRequestID'])) {
            $error_msg = $response_data['errorMessage'] ?? $response_data['ResponseDescription'] ?? 'STK Push failed';
            throw new Exception('M-Pesa STK Push failed: ' . $error_msg);
        }
        
        return [
            'success' => true,
            'transaction_id' => $transaction_id,
            'merchant_request_id' => $response_data['MerchantRequestID'],
            'checkout_request_id' => $response_data['CheckoutRequestID'],
            'response_code' => $response_data['ResponseCode'],
            'response_description' => $response_data['ResponseDescription'],
            'customer_message' => $response_data['CustomerMessage'] ?? 'Payment request sent to your phone. Please enter your M-Pesa PIN to complete payment.'
        ];
    }
    
    /**
     * Process M-Pesa callback
     * 
     * @param array $callback_data Callback payload from M-Pesa
     * @return array Processing result
     */
    public function processCallback($callback_data) {
        try {
            // Extract callback data
            $body = $callback_data['Body'] ?? [];
            $stk_callback = $body['stkCallback'] ?? [];
            
            $merchant_request_id = $stk_callback['MerchantRequestID'] ?? null;
            $checkout_request_id = $stk_callback['CheckoutRequestID'] ?? null;
            $result_code = $stk_callback['ResultCode'] ?? null;
            $result_desc = $stk_callback['ResultDesc'] ?? null;
            
            // Determine callback type and status
            $callback_type = ($result_code == '0') ? 'success' : 'error';
            $status = ($result_code == '0') ? 'completed' : 'failed';
            
            // Extract transaction ID from callback metadata
            $transaction_id = null;
            if (isset($stk_callback['CallbackMetadata']['Item'])) {
                foreach ($stk_callback['CallbackMetadata']['Item'] as $item) {
                    if ($item['Name'] === 'MpesaReceiptNumber') {
                        $transaction_id = $item['Value'];
                        break;
                    }
                }
            }
            
            // Log callback
            $callback_log_id = $this->logCallback(
                $checkout_request_id,
                $callback_type,
                $merchant_request_id,
                $result_code,
                $result_desc,
                $callback_data
            );
            
            // Update transaction status
            $stmt = $this->conn->prepare("CALL update_mpesa_transaction_status(?, ?, ?, ?, ?, ?)");
            $callback_json = json_encode($callback_data);
            $stmt->bind_param("ssssss", 
                $checkout_request_id,
                $transaction_id,
                $result_code,
                $result_desc,
                $status,
                $callback_json
            );
            $stmt->execute();
            
            // Mark callback as processed
            $this->conn->query("UPDATE mpesa_callback_logs SET processed = 1, processed_at = NOW() WHERE id = $callback_log_id");
            
            return [
                'success' => true,
                'result_code' => $result_code,
                'result_desc' => $result_desc,
                'transaction_id' => $transaction_id
            ];
            
        } catch (Exception $e) {
            // Log error
            if (isset($callback_log_id)) {
                $error_msg = $this->conn->real_escape_string($e->getMessage());
                $this->conn->query("UPDATE mpesa_callback_logs SET processing_error = '$error_msg' WHERE id = $callback_log_id");
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Format phone number to Ethiopian format (251XXXXXXXXX)
     * 
     * @param string $phone Phone number in various formats
     * @return string Formatted phone number
     */
    private function formatPhoneNumber($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Handle different formats
        if (strlen($phone) === 9) {
            // 9 digits: add 251 prefix
            return '251' . $phone;
        } elseif (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
            // 10 digits starting with 0: replace 0 with 251
            return '251' . substr($phone, 1);
        } elseif (strlen($phone) === 12 && substr($phone, 0, 3) === '251') {
            // Already in correct format
            return $phone;
        } elseif (strlen($phone) === 13 && substr($phone, 0, 4) === '+251') {
            // Starts with +251: remove +
            return substr($phone, 1);
        }
        
        // If format is unclear, throw exception
        throw new Exception('Invalid phone number format. Expected Ethiopian phone number.');
    }
    
    /**
     * Store transaction in database
     */
    private function storeTransaction($booking_id, $merchant_request_id, $checkout_request_id, $phone, $amount, $account_ref, $desc, $type, $status, $result_code, $result_desc, $request, $response, $error) {
        $stmt = $this->conn->prepare("
            INSERT INTO mpesa_transactions (
                booking_id, merchant_request_id, checkout_request_id, phone_number, 
                amount, account_reference, transaction_desc, transaction_type, 
                status, result_code, result_desc, api_request, api_response, error_message
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $request_json = json_encode($request);
        $response_json = json_encode($response);
        
        $stmt->bind_param("isssdsssssssss",
            $booking_id, $merchant_request_id, $checkout_request_id, $phone,
            $amount, $account_ref, $desc, $type,
            $status, $result_code, $result_desc, $request_json, $response_json, $error
        );
        
        $stmt->execute();
        return $this->conn->insert_id;
    }
    
    /**
     * Log API call
     */
    private function logAPICall($method, $endpoint, $req_headers, $req_body, $res_code, $res_body, $exec_time, $error, $transaction_id = null) {
        $stmt = $this->conn->prepare("
            INSERT INTO mpesa_api_logs (
                transaction_id, endpoint, request_method, request_headers, request_body,
                response_code, response_body, execution_time, error_message, ip_address, user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $req_headers_json = json_encode($req_headers);
        $req_body_json = json_encode($req_body);
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt->bind_param("issssisssss",
            $transaction_id, $endpoint, $method, $req_headers_json, $req_body_json,
            $res_code, $res_body, $exec_time, $error, $ip, $user_agent
        );
        
        $stmt->execute();
    }
    
    /**
     * Log callback
     */
    private function logCallback($checkout_request_id, $type, $merchant_request_id, $result_code, $result_desc, $callback_data) {
        // Get transaction ID
        $transaction_id = null;
        if ($checkout_request_id) {
            $result = $this->conn->query("SELECT id FROM mpesa_transactions WHERE checkout_request_id = '$checkout_request_id'");
            if ($row = $result->fetch_assoc()) {
                $transaction_id = $row['id'];
            }
        }
        
        $stmt = $this->conn->prepare("
            INSERT INTO mpesa_callback_logs (
                transaction_id, callback_type, merchant_request_id, checkout_request_id,
                result_code, result_desc, callback_data, ip_address
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $callback_json = json_encode($callback_data);
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        
        $stmt->bind_param("isssssss",
            $transaction_id, $type, $merchant_request_id, $checkout_request_id,
            $result_code, $result_desc, $callback_json, $ip
        );
        
        $stmt->execute();
        return $this->conn->insert_id;
    }
    
    /**
     * Log debug message
     */
    private function logDebug($message) {
        if ($this->environment === 'sandbox' || getenv('APP_ENV') === 'development') {
            error_log('[M-Pesa API] ' . $message);
        }
    }
}
