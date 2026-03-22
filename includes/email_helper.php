<?php
/**
 * Email Helper Functions
 * Easy-to-use functions for sending emails throughout the application
 */

require_once __DIR__ . '/services/EmailService.php';

/**
 * Send room booking confirmation email
 * Call this after payment is confirmed
 */
function sendRoomBookingConfirmation($bookingId, $conn) {
    try {
        $emailService = new EmailService($conn);
        $result = $emailService->sendRoomBookingEmail($bookingId);
        
        if ($result['success']) {
            error_log("Room booking email sent successfully for booking ID: $bookingId");
        } else {
            error_log("Room booking email failed for booking ID: $bookingId - " . $result['message']);
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Room booking email error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Send food order confirmation email
 * Call this after food order payment is confirmed
 */
function sendFoodOrderConfirmation($orderId, $conn) {
    try {
        $emailService = new EmailService($conn);
        $result = $emailService->sendFoodOrderEmail($orderId);
        
        if ($result['success']) {
            error_log("Food order email sent successfully for order ID: $orderId");
        } else {
            error_log("Food order email failed for order ID: $orderId - " . $result['message']);
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Food order email error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Send spa service confirmation email
 * Call this after spa service payment is confirmed
 */
function sendSpaServiceConfirmation($serviceId, $conn) {
    try {
        $emailService = new EmailService($conn);
        $result = $emailService->sendServiceEmail($serviceId, 'spa');
        
        if ($result['success']) {
            error_log("Spa service email sent successfully for service ID: $serviceId");
        } else {
            error_log("Spa service email failed for service ID: $serviceId - " . $result['message']);
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Spa service email error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Send laundry service confirmation email
 * Call this after laundry service payment is confirmed
 */
function sendLaundryServiceConfirmation($serviceId, $conn) {
    try {
        $emailService = new EmailService($conn);
        $result = $emailService->sendServiceEmail($serviceId, 'laundry');
        
        if ($result['success']) {
            error_log("Laundry service email sent successfully for service ID: $serviceId");
        } else {
            error_log("Laundry service email failed for service ID: $serviceId - " . $result['message']);
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Laundry service email error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
