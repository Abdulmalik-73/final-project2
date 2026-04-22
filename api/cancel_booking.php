<?php
/**
 * Booking Cancellation API
 * Handles customer-initiated booking cancellations with refund calculation
 */

// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/cancel_booking_errors.log');

// Start session first
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

try {
    require_once '../includes/config.php';
    require_once '../includes/functions.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Configuration error: ' . $e->getMessage()]);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login again.']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }
    
    $booking_reference = $input['booking_reference'] ?? '';
    $action = $input['action'] ?? 'calculate'; // 'calculate' or 'confirm'
    
    if (empty($booking_reference)) {
        throw new Exception('Booking reference is required');
    }
    
    // Check database connection
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection not available');
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Fetch booking details
    $stmt = $conn->prepare("
        SELECT b.*, r.name as room_name, u.first_name, u.last_name, u.email
        FROM bookings b
        LEFT JOIN rooms r ON b.room_id = r.id
        JOIN users u ON b.user_id = u.id
        WHERE b.booking_reference = ? AND b.user_id = ?
    ");
    $stmt->bind_param("si", $booking_reference, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Booking not found or access denied');
    }
    
    $booking = $result->fetch_assoc();
    
    // Check if booking can be cancelled
    if ($booking['status'] === 'cancelled') {
        throw new Exception('This booking has already been cancelled');
    }
    
    if ($booking['status'] === 'no_show') {
        throw new Exception('Cannot cancel a no-show booking');
    }
    
    if ($booking['status'] === 'checked_out') {
        throw new Exception('Cannot cancel a completed booking');
    }
    
    // Check if check-in date has passed
    $check_in_date = new DateTime($booking['check_in_date']);
    $current_date = new DateTime();
    $current_date->setTime(0, 0, 0);
    
    if ($current_date > $check_in_date) {
        throw new Exception('Cannot cancel booking after check-in date has passed');
    }
    
    // Calculate days before check-in
    $days_before_checkin = $check_in_date->diff($current_date)->days;
    
    // Determine refund percentage based on Harar Ras Hotel policy
    if ($days_before_checkin >= 7) {
        $refund_percentage = 95;
    } elseif ($days_before_checkin >= 3 && $days_before_checkin <= 6) {
        $refund_percentage = 75;
    } elseif ($days_before_checkin >= 1 && $days_before_checkin <= 2) {
        $refund_percentage = 50;
    } elseif ($days_before_checkin === 0) {
        $refund_percentage = 25;
    } else {
        $refund_percentage = 0;
    }
    
    // Calculate refund amounts
    $total_amount = (float)$booking['total_price'];
    $refund_amount = $total_amount * ($refund_percentage / 100);
    $processing_fee = $refund_amount * 0.05; // 5% processing fee
    $final_refund = $refund_amount - $processing_fee;
    $penalty_amount = $total_amount - $final_refund;
    
    // If action is 'calculate', return calculation details
    if ($action === 'calculate') {
        echo json_encode([
            'success' => true,
            'data' => [
                'booking_reference' => $booking['booking_reference'],
                'room_name' => $booking['room_name'] ?? 'N/A',
                'check_in_date' => $booking['check_in_date'],
                'total_amount' => number_format($total_amount, 2),
                'days_before_checkin' => $days_before_checkin,
                'refund_percentage' => $refund_percentage,
                'refund_amount' => number_format($refund_amount, 2),
                'processing_fee' => number_format($processing_fee, 2),
                'processing_fee_percentage' => 5,
                'final_refund' => number_format($final_refund, 2),
                'penalty_amount' => number_format($penalty_amount, 2)
            ]
        ]);
        exit;
    }
    
    // If action is 'confirm', process the cancellation
    if ($action === 'confirm') {
        $conn->begin_transaction();
        
        try {
            // Check if cancelled_at column exists
            $check_column = $conn->query("SHOW COLUMNS FROM bookings LIKE 'cancelled_at'");
            $has_cancelled_at = ($check_column && $check_column->num_rows > 0);
            
            // Update booking status - use dynamic SQL based on available columns
            if ($has_cancelled_at) {
                $update_stmt = $conn->prepare("
                    UPDATE bookings 
                    SET status = 'cancelled',
                        cancelled_at = NOW(),
                        cancelled_by = ?,
                        cancellation_reason = 'Customer initiated cancellation',
                        refund_amount = ?,
                        penalty_amount = ?,
                        payment_status = CASE 
                            WHEN ? > 0 THEN 'refund_pending'
                            ELSE payment_status
                        END
                    WHERE id = ?
                ");
                $update_stmt->bind_param("idddi", 
                    $user_id, 
                    $final_refund, 
                    $penalty_amount,
                    $final_refund,
                    $booking['id']
                );
            } else {
                // Fallback if columns don't exist yet
                $update_stmt = $conn->prepare("
                    UPDATE bookings 
                    SET status = 'cancelled',
                        payment_status = CASE 
                            WHEN ? > 0 THEN 'refund_pending'
                            ELSE payment_status
                        END
                    WHERE id = ?
                ");
                $update_stmt->bind_param("di", 
                    $final_refund,
                    $booking['id']
                );
            }
            
            if (!$update_stmt->execute()) {
                throw new Exception('Failed to update booking: ' . $update_stmt->error);
            }
            
            // Create refund record
            $refund_reference = 'REF' . date('Ymd') . str_pad($booking['id'], 6, '0', STR_PAD_LEFT);
            
            // Check if refunds table exists
            $check_table = $conn->query("SHOW TABLES LIKE 'refunds'");
            if ($check_table && $check_table->num_rows > 0) {
                $refund_stmt = $conn->prepare("
                    INSERT INTO refunds (
                        booking_id, booking_reference, customer_id, customer_name, customer_email,
                        original_amount, check_in_date, cancellation_date, days_before_checkin,
                        refund_percentage, refund_amount, processing_fee, processing_fee_percentage,
                        final_refund, refund_status, refund_reference, admin_notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, 'Pending', ?, 'Customer initiated cancellation')
                ");
                
                $customer_name = $booking['first_name'] . ' ' . $booking['last_name'];
                
                $refund_stmt->bind_param("isissdsiiddddds",
                    $booking['id'],
                    $booking['booking_reference'],
                    $user_id,
                    $customer_name,
                    $booking['email'],
                    $total_amount,
                    $booking['check_in_date'],
                    $days_before_checkin,
                    $refund_percentage,
                    $refund_amount,
                    $processing_fee,
                    5.00,
                    $final_refund,
                    $refund_reference
                );
                
                if (!$refund_stmt->execute()) {
                    throw new Exception('Failed to create refund record: ' . $refund_stmt->error);
                }
            } else {
                error_log("Warning: refunds table does not exist, skipping refund record creation");
            }
            
            // Log activity
            log_user_activity(
                $user_id,
                'booking_cancelled',
                "Booking {$booking['booking_reference']} cancelled. Refund: ETB " . number_format($final_refund, 2),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            );
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Cancellation request submitted successfully! Your refund of ETB ' . number_format($final_refund, 2) . ' is pending manager approval. You will receive your refund within 5-7 business days after approval.',
                'data' => [
                    'refund_reference' => $refund_reference,
                    'final_refund' => number_format($final_refund, 2),
                    'status' => 'Pending Manager Approval'
                ]
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
        exit;
    }
    
    throw new Exception('Invalid action');
    
} catch (mysqli_sql_exception $e) {
    error_log("SQL Error in cancel_booking.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'code' => 'DB_ERROR'
    ]);
} catch (Exception $e) {
    error_log("Error in cancel_booking.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
