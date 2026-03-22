<?php
// Suppress deprecation warnings in production
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: login.php?redirect=payment-upload');
    exit();
}

$booking_id = isset($_GET['booking']) ? (int)$_GET['booking'] : 0;
$error = '';
$success = '';
$feedback_success = '';
$feedback_error = '';

// Debug: Log page access
error_log("Payment upload page accessed with booking ID: " . $booking_id . " by user: " . ($_SESSION['user_id'] ?? 'not logged in'));

if (!$booking_id) {
    error_log("No booking ID provided, redirecting to index");
    header('Location: index.php');
    exit();
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_feedback'])) {
    // Check if feedback already exists for this booking
    $check_feedback = "SELECT id FROM customer_feedback WHERE booking_id = ?";
    $check_stmt = $conn->prepare($check_feedback);
    $check_stmt->bind_param("i", $booking_id);
    $check_stmt->execute();
    $existing_feedback = $check_stmt->get_result();
    
    if ($existing_feedback->num_rows > 0) {
        $error = 'You have already submitted feedback for this booking.';
    } else {
        $overall_rating = isset($_POST['overall_rating']) ? (int)$_POST['overall_rating'] : 0;
        $service_quality = isset($_POST['service_quality']) ? (int)$_POST['service_quality'] : 0;
        $cleanliness = isset($_POST['cleanliness']) ? (int)$_POST['cleanliness'] : 0;
        $comments = sanitize_input($_POST['comments'] ?? '');
        $service_type = sanitize_input($_POST['service_type'] ?? '');
        $booking_type = sanitize_input($_POST['booking_type'] ?? 'room');
        
        // Validate ratings (must be between 1 and 5)
        if ($overall_rating < 1 || $overall_rating > 5) {
            $feedback_error = 'Overall rating must be between 1 and 5 stars';
        } elseif ($service_quality < 1 || $service_quality > 5) {
            $feedback_error = 'Service quality rating must be between 1 and 5 stars';
        } elseif ($cleanliness < 1 || $cleanliness > 5) {
            $feedback_error = 'Cleanliness rating must be between 1 and 5 stars';
        } else {
            // Insert feedback into database
            $feedback_query = "INSERT INTO customer_feedback (booking_id, customer_id, payment_id, overall_rating, service_quality, cleanliness, comments, booking_type, service_type, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $feedback_stmt = $conn->prepare($feedback_query);
            $feedback_stmt->bind_param("iiiiissss", $booking_id, $_SESSION['user_id'], $booking_id, $overall_rating, $service_quality, $cleanliness, $comments, $booking_type, $service_type);
            
            if ($feedback_stmt->execute()) {
                $feedback_success = 'Your response is submitted successfully! Thank you for your feedback.';
                
                // Log the feedback submission
                error_log("Feedback submitted - Booking ID: $booking_id, Overall: $overall_rating, Service: $service_quality, Cleanliness: $cleanliness");
            } else {
                $feedback_error = 'Failed to submit feedback: ' . $conn->error;
                error_log("Feedback submission failed: " . $conn->error);
            }
        }
    }
}

// Get booking details (including food orders)
$query = "SELECT b.*, 
          COALESCE(r.name, 'Food Order') as room_name, 
          COALESCE(r.room_number, 'N/A') as room_number, 
          CONCAT(u.first_name, ' ', u.last_name) as customer_name,
          u.email as email,
          fo.order_reference as food_order_ref,
          fo.table_reservation,
          fo.reservation_date,
          fo.reservation_time,
          fo.guests as food_guests
          FROM bookings b 
          LEFT JOIN rooms r ON b.room_id = r.id 
          JOIN users u ON b.user_id = u.id 
          LEFT JOIN food_orders fo ON b.id = fo.booking_id
          WHERE b.id = ? AND b.user_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header('Location: index.php');
    exit();
}

// Get food order items if this is a food order
$food_items = [];
if ($booking['booking_type'] == 'food_order') {
    $items_query = "SELECT * FROM food_order_items WHERE order_id = (SELECT id FROM food_orders WHERE booking_id = ?)";
    $items_stmt = $conn->prepare($items_query);
    $items_stmt->bind_param("i", $booking_id);
    $items_stmt->execute();
    $food_items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get service booking details if this is a service booking
$service_details = null;
if (in_array($booking['booking_type'], ['spa_service', 'laundry_service'])) {
    $service_query = "SELECT * FROM service_bookings WHERE booking_id = ?";
    $service_stmt = $conn->prepare($service_query);
    $service_stmt->bind_param("i", $booking_id);
    $service_stmt->execute();
    $service_details = $service_stmt->get_result()->fetch_assoc();
}

// Check if feedback already exists for this booking
$feedback_exists = false;
$check_feedback_query = "SELECT id FROM customer_feedback WHERE booking_id = ?";
$check_feedback_stmt = $conn->prepare($check_feedback_query);
$check_feedback_stmt->bind_param("i", $booking_id);
$check_feedback_stmt->execute();
$feedback_result = $check_feedback_stmt->get_result();
if ($feedback_result->num_rows > 0) {
    $feedback_exists = true;
}

// Check if booking is in correct status for payment upload
if (!in_array($booking['verification_status'], ['pending_payment', 'rejected'])) {
    $error = 'This booking is not eligible for payment upload.';
}

// Generate payment reference if not exists
if (empty($booking['payment_reference'])) {
    $payment_ref = 'HRH-' . str_pad($booking_id, 4, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(md5($booking_id . time()), 0, 6));
    $deadline = date('Y-m-d H:i:s', strtotime('+30 minutes'));
    
    $update_query = "UPDATE bookings SET payment_reference = ?, payment_deadline = ?, verification_status = 'pending_payment' WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ssi", $payment_ref, $deadline, $booking_id);
    $update_stmt->execute();
    
    $booking['payment_reference'] = $payment_ref;
    $booking['payment_deadline'] = $deadline;
    $booking['verification_status'] = 'pending_payment';
}

// Get payment method instructions
$payment_methods_query = "SELECT * FROM payment_method_instructions WHERE is_active = 1 ORDER BY display_order, method_name";
$payment_methods = $conn->query($payment_methods_query);

if (!$payment_methods) {
    error_log("Failed to fetch payment methods: " . $conn->error);
    $error = 'Unable to load payment methods. Please try again.';
    $payment_methods = [];
} else {
    $payment_methods = $payment_methods->fetch_all(MYSQLI_ASSOC);
    error_log("Loaded " . count($payment_methods) . " payment methods");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($error)) {
    $payment_method = sanitize_input($_POST['payment_method']);
    
    // Handle file upload
    if (isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['error'] == 0) {
        $upload_dir = 'uploads/payment_screenshots/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['payment_screenshot']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            $error = 'Invalid file type. Please upload JPG, PNG, or WebP images only.';
        } elseif ($_FILES['payment_screenshot']['size'] > 5 * 1024 * 1024) { // 5MB limit
            $error = 'File size too large. Maximum 5MB allowed.';
        } else {
            $filename = 'payment_' . $booking_id . '_' . time() . '.' . $file_extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['payment_screenshot']['tmp_name'], $filepath)) {
                // Update booking with screenshot info
                $update_query = "UPDATE bookings SET 
                                payment_method = ?, 
                                payment_screenshot = ?, 
                                screenshot_uploaded_at = NOW(), 
                                verification_status = 'pending_verification'
                                WHERE id = ?";
                
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("ssi", $payment_method, $filepath, $booking_id);
                
                if ($update_stmt->execute()) {
                    // Log the upload
                    $log_query = "INSERT INTO payment_verification_log 
                                 (booking_id, payment_reference, action_type, performed_by, screenshot_path, bank_method, ip_address, user_agent) 
                                 VALUES (?, ?, 'screenshot_uploaded', ?, ?, ?, ?, ?)";
                    
                    $log_stmt = $conn->prepare($log_query);
                    $ip_address = $_SERVER['REMOTE_ADDR'];
                    $user_agent = $_SERVER['HTTP_USER_AGENT'];
                    $log_stmt->bind_param("isissss", $booking_id, $booking['payment_reference'], $_SESSION['user_id'], $filepath, $payment_method, $ip_address, $user_agent);
                    $log_stmt->execute();
                    
                    // Add to verification queue
                    $queue_query = "INSERT INTO payment_verification_queue 
                                   (booking_id, payment_reference, customer_name, room_name, total_amount, payment_method, screenshot_path, uploaded_at, priority) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'normal')";
                    
                    $queue_stmt = $conn->prepare($queue_query);
                    $queue_stmt->bind_param("isssdss", $booking_id, $booking['payment_reference'], $booking['customer_name'], $booking['room_name'], $booking['total_price'], $payment_method, $filepath);
                    $queue_stmt->execute();
                    
                    $success = 'Payment screenshot uploaded successfully! Your payment is now being verified by our staff. You will be notified once verification is complete.';
                    
                    // Refresh booking data
                    $stmt->execute();
                    $booking = $stmt->get_result()->fetch_assoc();
                } else {
                    $error = 'Failed to update booking. Please try again.';
                    unlink($filepath); // Remove uploaded file
                }
            } else {
                $error = 'Failed to upload file. Please try again.';
            }
        }
    } else {
        $error = 'Please select a payment screenshot to upload.';
    }
}

// Check if payment deadline has passed
$deadline_passed = false;
if ($booking['payment_deadline'] && strtotime($booking['payment_deadline']) < time()) {
    $deadline_passed = true;
    if ($booking['verification_status'] == 'pending_payment') {
        // Auto-expire the booking
        $expire_query = "UPDATE bookings SET verification_status = 'expired' WHERE id = ?";
        $expire_stmt = $conn->prepare($expire_query);
        $expire_stmt->bind_param("i", $booking_id);
        $expire_stmt->execute();
        $booking['verification_status'] = 'expired';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Upload - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .payment-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: #f8f9fa;
        }
        
        .payment-method-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .payment-method-card:hover {
            border-color: #007bff;
            background-color: #f0f8ff;
        }
        
        .payment-method-card.selected {
            border-color: #007bff;
            background-color: #e3f2fd;
        }
        
        .payment-instructions {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .verification-tips {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 5px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .deadline-warning {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .star-rating-container {
            display: flex;
            gap: 8px;
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .star {
            cursor: pointer;
            color: #ddd;
            transition: all 0.2s ease;
            user-select: none;
        }
        
        .star:hover {
            color: #ffc107;
            transform: scale(1.15);
        }
        
        .star.active {
            color: #ffc107;
        }
        
        .star-rating-container.rated {
            animation: pulse 0.3s ease;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .rating-text {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
            min-height: 20px;
        }
        
        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            background: #fafafa;
            transition: all 0.3s;
        }
        
        .upload-area:hover {
            border-color: #007bff;
            background: #f0f8ff;
        }
        
        .upload-area.dragover {
            border-color: #007bff;
            background: #e3f2fd;
        }
        
        .status-badge {
            font-size: 0.9em;
            padding: 5px 10px;
        }
        
        .countdown {
            font-weight: bold;
            color: #dc3545;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Page Header -->
    <section class="py-4 bg-light">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-auto">
                    <a href="my-bookings.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to My Bookings
                    </a>
                </div>
                <div class="col text-center">
                    <h2 class="mb-0">Payment Upload</h2>
                    <p class="text-muted mb-0">Upload your payment screenshot for verification</p>
                </div>
                <div class="col-auto">
                    <!-- Spacer for centering -->
                </div>
            </div>
        </div>
    </section>
    
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card shadow">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">
                                <i class="fas fa-credit-card"></i> Payment Upload
                            </h4>
                        </div>
                        <div class="card-body">
                            <!-- Booking Details -->
                            <div class="payment-card">
                                <h5><i class="fas fa-info-circle text-primary"></i> 
                                    <?php 
                                    if ($booking['booking_type'] == 'food_order') {
                                        echo 'Food Order Details';
                                    } elseif ($booking['booking_type'] == 'spa_service') {
                                        echo 'Spa & Wellness Service Details';
                                    } elseif ($booking['booking_type'] == 'laundry_service') {
                                        echo 'Laundry Service Details';
                                    } else {
                                        echo 'Booking Details';
                                    }
                                    ?>
                                </h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Reference:</strong> <?php echo $booking['booking_reference']; ?></p>
                                        <?php if ($booking['booking_type'] == 'food_order'): ?>
                                            <p><strong>Food Type:</strong> 
                                                <?php 
                                                if (!empty($food_items)) {
                                                    $food_names = array_map(function($item) {
                                                        return $item['item_name'];
                                                    }, $food_items);
                                                    echo htmlspecialchars(implode(', ', $food_names));
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </p>
                                            <p><strong>Guests:</strong> <?php echo $booking['food_guests']; ?></p>
                                            <?php if ($booking['table_reservation']): ?>
                                                <p><strong>Table Reserved:</strong> Yes</p>
                                                <p><strong>Date:</strong> <?php echo $booking['reservation_date'] ? format_date($booking['reservation_date']) : 'Not specified'; ?></p>
                                                <p><strong>Time:</strong> <?php echo $booking['reservation_time'] ?: 'Not specified'; ?></p>
                                            <?php else: ?>
                                                <p><strong>Table Reserved:</strong> No (Takeaway)</p>
                                            <?php endif; ?>
                                        <?php elseif ($booking['booking_type'] == 'spa_service'): ?>
                                            <p><strong>Recreation Area:</strong> Spa & Wellness</p>
                                            <p><strong>Check-in:</strong> N/A</p>
                                            <p><strong>Check-out:</strong> N/A</p>
                                            <p><strong>Guests:</strong> 1</p>
                                        <?php elseif ($booking['booking_type'] == 'laundry_service'): ?>
                                            <p><strong>Our Service:</strong> Laundry Service</p>
                                            <p><strong>Check-in:</strong> N/A</p>
                                            <p><strong>Check-out:</strong> N/A</p>
                                            <p><strong>Guests:</strong> 1</p>
                                        <?php else: ?>
                                            <p><strong>Room:</strong> <?php echo $booking['room_name']; ?> (<?php echo $booking['room_number']; ?>)</p>
                                            <p><strong>Check-in:</strong> <?php echo format_date($booking['check_in_date']); ?></p>
                                            <p><strong>Check-out:</strong> <?php echo format_date($booking['check_out_date']); ?></p>
                                            <p><strong>Guests:</strong> <?php echo $booking['customers']; ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Total Amount:</strong> <span class="h5 text-success"><?php echo format_currency($booking['total_price']); ?></span></p>
                                        <p><strong>Payment Reference:</strong> <code><?php echo $booking['payment_reference']; ?></code></p>
                                        <p><strong>Status:</strong> 
                                            <?php
                                            $status_class = 'secondary';
                                            $status_text = ucfirst(str_replace('_', ' ', $booking['verification_status']));
                                            
                                            switch($booking['verification_status']) {
                                                case 'pending_payment':
                                                    $status_class = 'warning';
                                                    break;
                                                case 'pending_verification':
                                                    $status_class = 'info';
                                                    break;
                                                case 'verified':
                                                    $status_class = 'success';
                                                    break;
                                                case 'rejected':
                                                    $status_class = 'danger';
                                                    break;
                                                case 'expired':
                                                    $status_class = 'dark';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $status_class; ?> status-badge"><?php echo $status_text; ?></span>
                                        </p>
                                        <?php if ($booking['booking_type'] == 'food_order' && !empty($food_items)): ?>
                                            <div class="mt-3">
                                                <strong>Ordered Items:</strong>
                                                <ul class="small mt-2">
                                                    <?php foreach ($food_items as $item): ?>
                                                        <li><?php echo $item['item_name']; ?> × <?php echo $item['quantity']; ?> - <?php echo format_currency($item['total_price']); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php elseif ($booking['booking_type'] == 'spa_service' && $service_details): ?>
                                            <div class="mt-3">
                                                <strong>Spa Service Details:</strong>
                                                <ul class="small mt-2">
                                                    <li><strong>Service:</strong> <?php echo htmlspecialchars($service_details['service_name']); ?></li>
                                                    <li><strong>Date:</strong> <?php echo date('M j, Y', strtotime($service_details['service_date'])); ?></li>
                                                    <li><strong>Time:</strong> <?php echo date('h:i A', strtotime($service_details['service_time'])); ?></li>
                                                    <?php if ($service_details['special_requests']): ?>
                                                    <li><strong>Special Requests:</strong> <?php echo htmlspecialchars($service_details['special_requests']); ?></li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        <?php elseif ($booking['booking_type'] == 'laundry_service' && $service_details): ?>
                                            <div class="mt-3">
                                                <strong>Laundry Service Details:</strong>
                                                <ul class="small mt-2">
                                                    <li><strong>Service:</strong> <?php echo htmlspecialchars($service_details['service_name']); ?></li>
                                                    <li><strong>Quantity:</strong> <?php echo $service_details['quantity']; ?> <?php echo $service_details['quantity'] > 1 ? 'items' : 'item'; ?></li>
                                                    <li><strong>Pickup Date:</strong> <?php echo date('M j, Y', strtotime($service_details['service_date'])); ?></li>
                                                    <li><strong>Pickup Time:</strong> <?php echo date('h:i A', strtotime($service_details['service_time'])); ?></li>
                                                    <?php if ($service_details['special_requests']): ?>
                                                    <li><strong>Special Instructions:</strong> <?php echo htmlspecialchars($service_details['special_requests']); ?></li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payment Deadline Warning -->
                            <?php if ($booking['payment_deadline'] && !$deadline_passed && $booking['verification_status'] == 'pending_payment'): ?>
                            <div class="deadline-warning">
                                <h6><i class="fas fa-clock text-danger"></i> Payment Deadline</h6>
                                <p class="mb-2">You must upload your payment screenshot before: <strong><?php echo date('F j, Y g:i A', strtotime($booking['payment_deadline'])); ?></strong></p>
                                <p class="mb-0">Time remaining: <span class="countdown" id="countdown"></span></p>
                            </div>
                            <?php elseif ($deadline_passed): ?>
                            <div class="alert alert-danger">
                                <h6><i class="fas fa-exclamation-triangle"></i> Payment Deadline Expired</h6>
                                <p class="mb-0">The payment deadline for this booking has passed. Please contact our support team for assistance.</p>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Status Messages -->
                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success" style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                                    <i class="fas fa-check-circle"></i> <strong>Payment screenshot uploaded successfully! Please wait for verification.</strong>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Payment Status Display -->
                            <?php if ($booking['verification_status'] == 'pending_verification'): ?>
                            <div style="background: #e7f3ff; border: 2px solid #0c5460; border-radius: 10px; padding: 30px; margin: 20px 0;">
                                <h4 style="color: #0c5460; margin-bottom: 15px; font-weight: bold;">
                                    <i class="fas fa-clock"></i> Payment Verification Pending
                                </h4>
                                <p style="color: #0c5460; font-size: 16px; margin-bottom: 20px;">
                                    <strong>We will send the payment verification message after receptionist approves your payment</strong>
                                </p>
                                
                                <div style="background: white; padding: 20px; border-radius: 8px; margin: 15px 0;">
                                    <?php if ($booking['booking_type'] == 'food_order'): ?>
                                        <p style="margin: 8px 0;"><strong>Booking Reference:</strong> <?php echo $booking['booking_reference']; ?></p>
                                        <p style="margin: 8px 0;"><strong>Food Type:</strong> 
                                            <?php 
                                            if (!empty($food_items)) {
                                                $food_names = array_map(function($item) {
                                                    return $item['item_name'];
                                                }, $food_items);
                                                echo htmlspecialchars(implode(', ', $food_names));
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </p>
                                        <p style="margin: 8px 0;"><strong>Total Paid:</strong> <?php echo format_currency($booking['total_price']); ?></p>
                                    <?php elseif ($booking['booking_type'] == 'spa_service'): ?>
                                        <p style="margin: 8px 0;"><strong>Booking Reference:</strong> <?php echo $booking['booking_reference']; ?></p>
                                        <p style="margin: 8px 0;"><strong>Recreation Area:</strong> Spa & Wellness</p>
                                        <p style="margin: 8px 0;"><strong>Total Paid:</strong> <?php echo format_currency($booking['total_price']); ?></p>
                                    <?php elseif ($booking['booking_type'] == 'laundry_service'): ?>
                                        <p style="margin: 8px 0;"><strong>Booking Reference:</strong> <?php echo $booking['booking_reference']; ?></p>
                                        <p style="margin: 8px 0;"><strong>Our Service:</strong> Laundry Service</p>
                                        <p style="margin: 8px 0;"><strong>Total Paid:</strong> <?php echo format_currency($booking['total_price']); ?></p>
                                    <?php else: ?>
                                        <p style="margin: 8px 0;"><strong>Booking Reference:</strong> <?php echo $booking['booking_reference']; ?></p>
                                        <p style="margin: 8px 0;"><strong>Room:</strong> <?php echo $booking['room_name']; ?> - Room <?php echo $booking['room_number']; ?></p>
                                        <p style="margin: 8px 0;"><strong>Total Paid:</strong> <?php echo format_currency($booking['total_price']); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <p style="color: #0c5460; font-size: 14px; margin-top: 15px;">
                                    Once approved, you will receive a confirmation email at <strong><?php echo $booking['email']; ?></strong>
                                </p>
                                
                                <?php if ($booking['payment_screenshot']): ?>
                                <p class="mt-3">
                                    <a href="<?php echo $booking['payment_screenshot']; ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-eye"></i> View Uploaded Screenshot
                                    </a>
                                </p>
                                <?php endif; ?>
                                
                                <!-- Customer Feedback Form -->
                                <?php if (!$feedback_exists): ?>
                                <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin: 20px 0;">
                                    <h5 style="margin-bottom: 15px;"><i class="fas fa-star text-warning"></i> Share Your Feedback</h5>
                                    <p style="color: #666; margin-bottom: 15px;">Help us improve by sharing your experience with our hotel</p>
                                    
                                    <?php if ($feedback_success): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="fas fa-check-circle"></i> <strong><?php echo $feedback_success; ?></strong>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($feedback_error): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="fas fa-exclamation-circle"></i> <strong><?php echo $feedback_error; ?></strong>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!$feedback_success): ?>
                                    <form method="POST" action="">
                                        <input type="hidden" name="submit_feedback" value="1">
                                        <input type="hidden" name="booking_ref" value="<?php echo htmlspecialchars($booking['booking_reference']); ?>">
                                        <input type="hidden" name="payment_id" value="<?php echo htmlspecialchars($booking['payment_reference']); ?>">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                                        <input type="hidden" name="service_type" value="<?php echo $service_details ? htmlspecialchars($service_details['service_name']) : ''; ?>">
                                        <input type="hidden" name="booking_type" value="<?php echo $booking['booking_type']; ?>">
                                        
                                        <!-- Overall Rating -->
                                        <div style="margin-bottom: 20px;">
                                            <label style="display: block; margin-bottom: 10px; font-weight: 500; font-size: 15px;">Overall Rating: <span style="color: red;">*</span></label>
                                            <div class="star-rating-container" data-name="overall_rating" style="display: flex; gap: 8px; font-size: 36px; margin-bottom: 10px;">
                                                <span class="star" data-value="1" style="cursor: pointer; color: #ddd; transition: all 0.2s ease; user-select: none;">★</span>
                                                <span class="star" data-value="2" style="cursor: pointer; color: #ddd; transition: all 0.2s ease; user-select: none;">★</span>
                                                <span class="star" data-value="3" style="cursor: pointer; color: #ddd; transition: all 0.2s ease; user-select: none;">★</span>
                                                <span class="star" data-value="4" style="cursor: pointer; color: #ddd; transition: all 0.2s ease; user-select: none;">★</span>
                                                <span class="star" data-value="5" style="cursor: pointer; color: #ddd; transition: all 0.2s ease; user-select: none;">★</span>
                                            </div>
                                            <div class="rating-text" id="overall_rating_text" style="font-size: 14px; color: #666; margin-top: 5px; min-height: 20px;">Click to rate your overall experience</div>
                                            <input type="hidden" name="overall_rating" id="overall_rating" value="0" required>
                                        </div>
                                        
                                        <!-- Service Quality Rating -->
                                        <div style="margin-bottom: 20px;">
                                            <label style="display: block; margin-bottom: 10px; font-weight: 500; font-size: 15px;">Service Quality: <span style="color: red;">*</span></label>
                                            <div class="star-rating-container" data-name="service_quality" style="display: flex; gap: 8px; font-size: 36px; margin-bottom: 10px;">
                                                <span class="star" data-value="1" style="cursor: pointer; color: #ddd; transition: all 0.2s ease; user-select: none;">★</span>
                                                <span class="star" data-value="2" style="cursor: pointer; color: #ddd; transition: all 0.2s ease; user-select: none;">★</span>
                                                <span class="star" data-value="3" style="cursor: pointer; color: #ddd; transition: all 0.2s ease; user-select: none;">★</span>
                                                <span class="star" data-value="4" style="cursor: pointer; color: #ddd; transition: all 0.2s ease; user-select: none;">★</span>
                                                <span class="star" data-value="5" style="cursor: pointer; color: #ddd; transition: all 0.2s ease; user-select: none;">★</span>
                                            </div>
                                            <div class="rating-text" id="service_quality_text" style="font-size: 14px; color: #666; margin-top: 5px; min-height: 20px;">Rate the service quality</div>
                                            <input type="hidden" name="service_quality" id="service_quality" value="0" required>
                                        </div>
                                        
                                        <!-- Cleanliness Rating -->
                                        <div style="margin-bottom: 20px;">
                                            <label style="display: block; margin-bottom: 10px; font-weight: 500; font-size: 15px;">Cleanliness: <span style="color: red;">*</span></label>
                                            <div class="star-rating-container" data-name="cleanliness" style="display: flex; gap: 8px; font-size: 36px; margin-bottom: 10px;">
                                                <span class="star" data-value="1" style="cursor: pointer; color: #ddd; transition: all 0.2s ease; user-select: none;">★</span>
                                                <span class="star" data-value="2" style="cursor: pointer; color: #ddd; transition: all 0.2s ease; user-select: none;">★</span>
                                                <span class="star" data-value="3" style="cursor: pointer; color: #ddd; transition: all 0.2s ease; user-select: none;">★</span>
                                                <span class="star" data-value="4" style="cursor: pointer; color: #ddd; transition: all 0.2s ease; user-select: none;">★</span>
                                                <span class="star" data-value="5" style="cursor: pointer; color: #ddd; transition: all 0.2s ease; user-select: none;">★</span>
                                            </div>
                                            <div class="rating-text" id="cleanliness_text" style="font-size: 14px; color: #666; margin-top: 5px; min-height: 20px;">Rate the cleanliness</div>
                                            <input type="hidden" name="cleanliness" id="cleanliness" value="0" required>
                                        </div>
                                        
                                        <!-- Comments -->
                                        <div style="margin-bottom: 15px;">
                                            <label style="display: block; margin-bottom: 8px; font-weight: 500;">Additional Comments (Optional):</label>
                                            <textarea name="comments" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-family: Arial, sans-serif;" rows="3" placeholder="Share your thoughts..."></textarea>
                                        </div>
                                        
                                        <div style="display: flex; gap: 10px;">
                                            <button type="submit" class="btn btn-primary" style="flex: 1;">
                                                <i class="fas fa-paper-plane"></i> Submit Feedback
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="skipFeedback()" style="flex: 1;">
                                                <i class="fas fa-forward"></i> Skip for Now
                                            </button>
                                        </div>
                                    </form>
                                    <?php else: ?>
                                    <div class="text-center mt-4">
                                        <a href="index.php" class="btn btn-primary">
                                            <i class="fas fa-home"></i> Return to Home
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; padding: 20px; margin: 20px 0;">
                                    <h5 style="margin-bottom: 10px; color: #155724;"><i class="fas fa-check-circle"></i> Feedback Already Submitted</h5>
                                    <p style="color: #155724; margin-bottom: 0;">Thank you! You have already submitted your feedback for this booking.</p>
                                </div>
                                <?php endif; ?>
                                
                                <div class="text-center mt-4">
                                    <a href="index.php" class="btn btn-dark">
                                        <i class="fas fa-arrow-left"></i> BACK TO DASHBOARD
                                    </a>
                                </div>
                            </div>
                            <?php elseif ($booking['verification_status'] == 'verified'): ?>
                            <div class="alert alert-success">
                                <h6><i class="fas fa-check-circle"></i> Payment Verified</h6>
                                <p class="mb-2">Your payment has been successfully verified and your booking is confirmed!</p>
                                <p class="mb-0"><strong>Verified:</strong> <?php echo date('F j, Y g:i A', strtotime($booking['verified_at'])); ?></p>
                                <div class="text-center mt-3">
                                    <a href="customer-feedback.php?booking_ref=<?php echo urlencode($booking['booking_reference']); ?>&payment_id=<?php echo urlencode($booking['payment_reference']); ?>" class="btn btn-primary">
                                        <i class="fas fa-star me-2"></i> Share Your Feedback
                                    </a>
                                    <a href="booking-confirmation.php?booking_ref=<?php echo urlencode($booking['booking_reference']); ?>" class="btn btn-outline-primary ms-2">
                                        <i class="fas fa-forward me-2"></i> Skip to Confirmation
                                    </a>
                                </div>
                            </div>
                            <?php elseif ($booking['verification_status'] == 'rejected'): ?>
                            <div class="alert alert-danger">
                                <h6><i class="fas fa-times-circle"></i> Payment Rejected</h6>
                                <p class="mb-2">Your payment screenshot was rejected. Please upload a new screenshot with the correct information.</p>
                                <?php if ($booking['rejection_reason']): ?>
                                <p class="mb-0"><strong>Reason:</strong> <?php echo htmlspecialchars($booking['rejection_reason']); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Payment Upload Form -->
                            <?php if (in_array($booking['verification_status'], ['pending_payment', 'rejected']) && !$deadline_passed): ?>
                            <form method="POST" enctype="multipart/form-data" id="paymentForm">
                                <!-- Payment Method Selection -->
                                <div class="mb-4">
                                    <h5><i class="fas fa-university text-primary"></i> Select Payment Method</h5>
                                    <div class="row">
                                        <?php foreach ($payment_methods as $method): ?>
                                        <div class="col-md-6 col-lg-4">
                                            <div class="payment-method-card" data-method="<?php echo $method['method_code']; ?>">
                                                <input type="radio" name="payment_method" value="<?php echo $method['method_code']; ?>" id="method_<?php echo $method['method_code']; ?>" class="d-none">
                                                <label for="method_<?php echo $method['method_code']; ?>" class="w-100 mb-0" style="cursor: pointer;">
                                                    <h6 class="mb-1"><?php echo $method['method_name']; ?></h6>
                                                    <small class="text-muted"><?php echo $method['bank_name']; ?></small>
                                                    <?php if ($method['mobile_number']): ?>
                                                    <br><small class="text-primary"><?php echo $method['mobile_number']; ?></small>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- Payment Instructions -->
                                <div id="paymentInstructions" style="display: none;">
                                    <h5><i class="fas fa-list-ol text-primary"></i> Payment Instructions</h5>
                                    <div id="instructionsContent"></div>
                                </div>
                                
                                <!-- Screenshot Upload -->
                                <div class="mb-4">
                                    <h5><i class="fas fa-camera text-primary"></i> Upload Payment Screenshot</h5>
                                    <div class="upload-area" id="uploadArea">
                                        <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                        <h6>Click to select or drag and drop your screenshot</h6>
                                        <p class="text-muted mb-0">Supported formats: JPG, PNG, WebP (Max 5MB)</p>
                                        <input type="file" name="payment_screenshot" id="paymentScreenshot" accept="image/jpeg,image/jpg,image/png,image/webp" class="d-none" required>
                                    </div>
                                    <div id="filePreview" class="mt-3" style="display: none;">
                                        <img id="previewImage" src="" alt="Preview" class="img-thumbnail" style="max-width: 300px;">
                                        <p id="fileName" class="mt-2 mb-2"></p>
                                        <button type="button" class="btn btn-danger btn-sm" id="cancelBtn" onclick="cancelScreenshot()">
                                            <i class="fas fa-times"></i> Cancel / Remove Screenshot
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Submit Button -->
                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn" disabled>
                                        <i class="fas fa-arrow-right"></i> Proceed to Payment
                                    </button>
                                </div>
                            </form>
                            <?php endif; ?>
                            
                            <!-- Help Section -->
                            <div class="mt-5">
                                <h5><i class="fas fa-question-circle text-primary"></i> Need Help?</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Payment Issues</h6>
                                        <p>If you're having trouble with payment, please contact our support team:</p>
                                        <p><i class="fas fa-phone"></i> +251-911-123-456<br>
                                           <i class="fas fa-envelope"></i> support@hararrashotel.com</p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Screenshot Requirements</h6>
                                        <ul class="small">
                                            <li>Must show the exact amount (<?php echo format_currency($booking['total_price']); ?>)</li>
                                            <li>Must include payment reference: <code><?php echo $booking['payment_reference']; ?></code></li>
                                            <li>Must show successful transaction confirmation</li>
                                            <li>Image must be clear and readable</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Payment method instructions data
        const paymentMethods = <?php echo json_encode($payment_methods); ?>;
        
        // Payment method selection
        document.querySelectorAll('.payment-method-card').forEach(card => {
            card.addEventListener('click', function() {
                // Remove selected class from all cards
                document.querySelectorAll('.payment-method-card').forEach(c => c.classList.remove('selected'));
                
                // Add selected class to clicked card
                this.classList.add('selected');
                
                // Check the radio button
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                
                // Show instructions
                showPaymentInstructions(radio.value);
                
                // Enable submit button if file is also selected
                checkFormValidity();
            });
        });
        
        function showPaymentInstructions(methodCode) {
            const method = paymentMethods.find(m => m.method_code === methodCode);
            if (method) {
                const instructionsDiv = document.getElementById('paymentInstructions');
                const contentDiv = document.getElementById('instructionsContent');
                
                const amount = '<?php echo $booking['total_price']; ?>';
                const reference = '<?php echo $booking['payment_reference']; ?>';
                
                let instructions = method.payment_instructions
                    .replace(/{AMOUNT}/g, amount)
                    .replace(/{REFERENCE}/g, reference);
                
                contentDiv.innerHTML = `
                    <div class="payment-instructions">
                        <h6>${method.method_name} - ${method.bank_name}</h6>
                        <p><strong>Account:</strong> ${method.account_number}</p>
                        <p><strong>Account Holder:</strong> ${method.account_holder_name}</p>
                        <div class="instructions-steps">
                            ${instructions.split('\n').map(step => step.trim() ? `<p class="mb-1">${step}</p>` : '').join('')}
                        </div>
                    </div>
                    <div class="verification-tips">
                        <h6><i class="fas fa-lightbulb"></i> Verification Tips</h6>
                        <p class="mb-0">${method.verification_tips}</p>
                    </div>
                `;
                
                instructionsDiv.style.display = 'block';
            }
        }
        
        // File upload handling
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('paymentScreenshot');
        const filePreview = document.getElementById('filePreview');
        const previewImage = document.getElementById('previewImage');
        const fileName = document.getElementById('fileName');
        
        uploadArea.addEventListener('click', () => fileInput.click());
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect(files[0]);
            }
        });
        
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });
        
        function handleFileSelect(file) {
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Invalid file type. Please select a JPG, PNG, or WebP image only.');
                fileInput.value = '';
                return;
            }
            
            // Validate file size (5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size too large. Maximum 5MB allowed.');
                fileInput.value = '';
                return;
            }
            
            // Show preview
            const reader = new FileReader();
            reader.onload = (e) => {
                previewImage.src = e.target.result;
                fileName.textContent = `Selected: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
                filePreview.style.display = 'block';
                uploadArea.style.display = 'none';
                
                checkFormValidity();
            };
            reader.readAsDataURL(file);
        }
        
        function cancelScreenshot() {
            fileInput.value = '';
            previewImage.src = '';
            fileName.textContent = '';
            filePreview.style.display = 'none';
            uploadArea.style.display = 'block';
            checkFormValidity();
        }
        
        function checkFormValidity() {
            const methodSelected = document.querySelector('input[name="payment_method"]:checked');
            const fileSelected = fileInput.files.length > 0;
            const submitBtn = document.getElementById('submitBtn');
            
            submitBtn.disabled = !(methodSelected && fileSelected);
        }
        
        // Countdown timer
        <?php if ($booking['payment_deadline'] && !$deadline_passed && $booking['verification_status'] == 'pending_payment'): ?>
        const deadline = new Date('<?php echo date('c', strtotime($booking['payment_deadline'])); ?>').getTime();
        
        function updateCountdown() {
            const now = new Date().getTime();
            const distance = deadline - now;
            
            if (distance > 0) {
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                document.getElementById('countdown').innerHTML = 
                    `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            } else {
                document.getElementById('countdown').innerHTML = 'EXPIRED';
                location.reload(); // Reload to show expired status
            }
        }
        
        updateCountdown();
        setInterval(updateCountdown, 1000);
        <?php endif; ?>
        
        // Star rating system - Direct implementation
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize all star rating containers
            const containers = document.querySelectorAll('.star-rating-container');
            
            containers.forEach(container => {
                const stars = container.querySelectorAll('.star');
                const inputName = container.getAttribute('data-name');
                const hiddenInput = document.querySelector(`input[name="${inputName}"]`);
                const textElement = document.getElementById(`${inputName}_text`);
                let currentRating = 0;
                
                const ratingTexts = {
                    1: 'Poor',
                    2: 'Fair',
                    3: 'Good',
                    4: 'Very Good',
                    5: 'Excellent'
                };
                
                // Add click handlers
                stars.forEach((star, index) => {
                    star.addEventListener('click', function(e) {
                        e.preventDefault();
                        const rating = index + 1;
                        currentRating = rating;
                        hiddenInput.value = rating;
                        
                        // Update star colors
                        stars.forEach((s, i) => {
                            if (i < rating) {
                                s.style.color = '#ffc107';
                                s.classList.add('active');
                            } else {
                                s.style.color = '#ddd';
                                s.classList.remove('active');
                            }
                        });
                        
                        // Update text
                        if (textElement) {
                            textElement.textContent = ratingTexts[rating];
                            textElement.style.color = '#28a745';
                            textElement.style.fontWeight = '600';
                        }
                    });
                    
                    // Hover effect
                    star.addEventListener('mouseenter', function() {
                        const rating = index + 1;
                        stars.forEach((s, i) => {
                            if (i < rating) {
                                s.style.color = '#ffc107';
                            } else {
                                s.style.color = '#ddd';
                            }
                        });
                    });
                });
                
                // Reset to current rating on mouse leave
                container.addEventListener('mouseleave', function() {
                    stars.forEach((s, i) => {
                        if (i < currentRating) {
                            s.style.color = '#ffc107';
                        } else {
                            s.style.color = '#ddd';
                        }
                    });
                });
            });
        });
        
        // Form validation before submit
        document.querySelector('form[method="POST"]')?.addEventListener('submit', function(e) {
            const overallRating = parseInt(document.querySelector('input[name="overall_rating"]').value);
            const serviceQuality = parseInt(document.querySelector('input[name="service_quality"]').value);
            const cleanliness = parseInt(document.querySelector('input[name="cleanliness"]').value);
            
            if (!overallRating || overallRating < 1 || overallRating > 5) {
                e.preventDefault();
                alert('Please rate your overall experience (1-5 stars)');
                return false;
            }
            
            if (!serviceQuality || serviceQuality < 1 || serviceQuality > 5) {
                e.preventDefault();
                alert('Please rate the service quality (1-5 stars)');
                return false;
            }
            
            if (!cleanliness || cleanliness < 1 || cleanliness > 5) {
                e.preventDefault();
                alert('Please rate the cleanliness (1-5 stars)');
                return false;
            }
            
            return true;
        });
        
        // Skip feedback function
        function skipFeedback() {
            window.location.href = 'index.php';
        }
    </script>
</body>
</html>