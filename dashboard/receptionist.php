<?php
// Suppress PHP warnings and notices for production
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_role('receptionist');

$message = '';
$error = '';

// Handle AJAX requests for check-in/check-out actions
if ($_POST && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'checkin':
            $booking_ref = sanitize_input($_POST['booking_ref']);
            $query = "UPDATE bookings SET status = 'checked_in' WHERE booking_reference = '$booking_ref' AND status = 'confirmed'";
            if ($conn->query($query)) {
                // Log the check-in
                $booking_query = "SELECT id, user_id FROM bookings WHERE booking_reference = '$booking_ref'";
                $booking_result = $conn->query($booking_query);
                if ($booking_result && $booking = $booking_result->fetch_assoc()) {
                    log_booking_activity($booking['id'], $booking['user_id'], 'checked_in', 'confirmed', 'checked_in', 'Guest checked in by receptionist', $_SESSION['user_id']);
                }
                echo json_encode(['success' => true, 'message' => 'Guest checked in successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to check in guest']);
            }
            exit();
            
        case 'checkout':
            $booking_ref = sanitize_input($_POST['booking_ref']);
            $query = "UPDATE bookings SET status = 'checked_out' WHERE booking_reference = '$booking_ref' AND status = 'checked_in'";
            if ($conn->query($query)) {
                // Update room status to available
                $room_query = "UPDATE rooms r 
                              JOIN bookings b ON r.id = b.room_id 
                              SET r.status = 'active' 
                              WHERE b.booking_reference = '$booking_ref'";
                $conn->query($room_query);
                
                // Log the check-out
                $booking_query = "SELECT id, user_id FROM bookings WHERE booking_reference = '$booking_ref'";
                $booking_result = $conn->query($booking_query);
                if ($booking_result && $booking = $booking_result->fetch_assoc()) {
                    log_booking_activity($booking['id'], $booking['user_id'], 'checked_out', 'checked_in', 'checked_out', 'Guest checked out by receptionist', $_SESSION['user_id']);
                }
                echo json_encode(['success' => true, 'message' => 'Guest checked out successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to check out guest']);
            }
            exit();
            
        case 'confirm_booking':
            $booking_ref = sanitize_input($_POST['booking_ref']);
            $query = "UPDATE bookings SET status = 'confirmed' WHERE booking_reference = '$booking_ref' AND status = 'pending'";
            if ($conn->query($query)) {
                // Log the confirmation
                $booking_query = "SELECT id, user_id FROM bookings WHERE booking_reference = '$booking_ref'";
                $booking_result = $conn->query($booking_query);
                if ($booking_result && $booking = $booking_result->fetch_assoc()) {
                    log_booking_activity($booking['id'], $booking['user_id'], 'confirmed', 'pending', 'confirmed', 'Booking confirmed by receptionist', $_SESSION['user_id']);
                }
                echo json_encode(['success' => true, 'message' => 'Booking confirmed successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to confirm booking']);
            }
            exit();
            
        case 'cancel_booking':
            $booking_ref = sanitize_input($_POST['booking_ref']);
            $query = "UPDATE bookings SET status = 'cancelled' WHERE booking_reference = '$booking_ref' AND status = 'pending'";
            if ($conn->query($query)) {
                // Log the cancellation
                $booking_query = "SELECT id, user_id FROM bookings WHERE booking_reference = '$booking_ref'";
                $booking_result = $conn->query($booking_query);
                if ($booking_result && $booking = $booking_result->fetch_assoc()) {
                    log_booking_activity($booking['id'], $booking['user_id'], 'cancelled', 'pending', 'cancelled', 'Booking cancelled by receptionist', $_SESSION['user_id']);
                }
                echo json_encode(['success' => true, 'message' => 'Booking cancelled successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to cancel booking']);
            }
            exit();
    }
}

// Get today's check-ins and check-outs
$today = date('Y-m-d');

// Today's Check-ins (confirmed bookings scheduled for today OR already checked in today) - ALL TYPES
$checkins = $conn->query("
    SELECT b.*, 
           COALESCE(r.name, '') as room_name, 
           COALESCE(r.room_number, '') as room_number, 
           COALESCE(r.price, 0) as price,
           CONCAT(u.first_name, ' ', u.last_name) as guest_name,
           u.email, u.phone,
           DATEDIFF(b.check_out_date, b.check_in_date) as nights,
           b.payment_status, b.payment_method, b.total_price,
           COALESCE(b.customers, 1) as num_guests,
           b.verification_status, b.status as booking_status,
           fo.order_reference as food_order_ref,
           fo.reservation_date as food_date,
           fo.reservation_time as food_time,
           sb.service_name,
           sb.service_category,
           sb.service_date,
           sb.service_time
    FROM bookings b 
    LEFT JOIN rooms r ON b.room_id = r.id 
    JOIN users u ON b.user_id = u.id 
    LEFT JOIN food_orders fo ON b.id = fo.booking_id
    LEFT JOIN service_bookings sb ON b.id = sb.booking_id
    WHERE (
        (DATE(b.check_in_date) = '$today' AND b.status IN ('confirmed', 'checked_in'))
        OR 
        (DATE(b.actual_checkin_time) = '$today' AND b.status = 'checked_in')
        OR
        (DATE(fo.reservation_date) = '$today' AND b.status = 'confirmed')
        OR
        (DATE(sb.service_date) = '$today' AND b.status = 'confirmed')
    )
    ORDER BY b.created_at DESC
");

// Debug: Get all bookings for today regardless of status
$debug_query = $conn->query("
    SELECT b.id, b.booking_reference, b.check_in_date, b.status, b.verification_status, b.payment_status, b.booking_type,
           CONCAT(u.first_name, ' ', u.last_name) as guest_name
    FROM bookings b 
    JOIN users u ON b.user_id = u.id 
    WHERE (DATE(b.check_in_date) = '$today' OR DATE(b.created_at) = '$today')
    ORDER BY b.created_at DESC
");

$debug_bookings = [];
if ($debug_query) {
    while ($row = $debug_query->fetch_assoc()) {
        $debug_bookings[] = $row;
    }
}

// Today's Check-outs (checked-in guests scheduled to leave today)
$checkouts = $conn->query("
    SELECT b.*, 
           COALESCE(r.name, 'Food Order') as room_name, 
           COALESCE(r.room_number, 'N/A') as room_number,
           b.customer_name as guest_name, b.customer_email as email, b.customer_phone as phone,
           DATEDIFF(b.check_out_date, b.check_in_date) as nights,
           b.payment_status, b.total_price, b.incidental_deposit,
           b.actual_checkin_time
    FROM bookings b 
    LEFT JOIN rooms r ON b.room_id = r.id 
    WHERE b.check_out_date = '$today' AND b.status = 'checked_in'
    ORDER BY b.actual_checkin_time DESC
");

// Get pending bookings (awaiting confirmation) - all types
$pending_bookings = $conn->query("
    SELECT b.*, 
           COALESCE(r.name, '') as room_name, 
           COALESCE(r.room_number, '') as room_number, 
           COALESCE(r.price, 0) as price,
           CONCAT(u.first_name, ' ', u.last_name) as guest_name,
           u.email, u.phone,
           DATEDIFF(b.check_out_date, b.check_in_date) as nights,
           b.payment_status,
           fo.order_reference as food_order_ref,
           sb.service_name,
           sb.service_category
    FROM bookings b 
    LEFT JOIN rooms r ON b.room_id = r.id 
    JOIN users u ON b.user_id = u.id 
    LEFT JOIN food_orders fo ON b.id = fo.booking_id
    LEFT JOIN service_bookings sb ON b.id = sb.booking_id
    WHERE b.status = 'pending'
    ORDER BY b.created_at DESC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receptionist Dashboard - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Service Type Badge Styling */
        .service-type-badge {
            display: inline-block;
            max-width: 200px;
            white-space: normal !important;
            line-height: 1.3;
            padding: 0.5rem 0.75rem;
            text-align: center;
        }
        
        .service-type-badge strong {
            display: block;
            font-size: 0.85rem;
            margin-bottom: 0.2rem;
        }
        
        .service-type-badge small {
            display: block;
            font-size: 0.75rem;
            line-height: 1.2;
            overflow: hidden;
            text-overflow: ellipsis;
            max-height: 2.4em;
        }
        
        /* Make table cells more compact */
        .table td {
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-hotel text-gold"></i> Harar Ras Hotel - Reception
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../index.php">
                    <i class="fas fa-home"></i> Back to Website
                </a>
                <span class="navbar-text me-3">
                    <i class="fas fa-user-tie"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?> (Receptionist)
                </span>
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="fas fa-concierge-bell"></i> Reception Menu</h6>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="receptionist.php" class="list-group-item list-group-item-action active">
                            <i class="fas fa-tachometer-alt"></i> Dashboard Overview
                        </a>
                        <a href="customer-checkin.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-user-plus"></i> Customer Check-In
                        </a>
                        <a href="receptionist-checkout.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-minus-circle"></i> Process Check-out
                        </a>
                        <a href="verify-payments.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-check-circle"></i> Verify Payments
                        </a>
                        <a href="receptionist-pending.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-calendar-check"></i> Pending Bookings
                        </a>
                        <a href="receptionist-rooms.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-bed"></i> Manage Rooms
                        </a>
                        <a href="receptionist-services.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-utensils"></i> Manage Foods & Services
                        </a>
                        <a href="../generate_bill.php" class="list-group-item list-group-item-action" target="_blank">
                            <i class="fas fa-file-invoice-dollar"></i> Generate Bill
                        </a>
                        <a href="../index.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-home"></i> Hotel Website
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <!-- Today's Check-ins -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-sign-in-alt"></i> Today's Check-ins (<?php echo date('F j, Y'); ?>)
                            <span class="badge bg-light text-success float-end"><?php echo $checkins->num_rows; ?> Guest(s)</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($checkins->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Booking Ref</th>
                                        <th>Guest Name</th>
                                        <th>Contact</th>
                                        <th>Service Type</th>
                                        <th>Nights</th>
                                        <th>Total Amount</th>
                                        <th>Payment Status</th>
                                        <th>Check-in Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($booking = $checkins->fetch_assoc()): 
                                        // Determine service type display
                                        $booking_type = $booking['booking_type'];
                                        $service_display = '';
                                        $service_badge_class = 'bg-secondary';
                                        
                                        if ($booking_type == 'room') {
                                            $room_name = htmlspecialchars($booking['room_name']);
                                            $room_number = htmlspecialchars($booking['room_number']);
                                            
                                            // Format: "Room Name - Room Number" 
                                            // Only show room number if it exists and is not empty
                                            if (!empty($room_number) && $room_number != 'N/A' && $room_number != '' && $room_number != $room_name) {
                                                $service_display = '<strong>Room</strong><br><small>' . $room_name . '<br>Room ' . $room_number . '</small>';
                                            } else {
                                                $service_display = '<strong>Room</strong><br><small>' . $room_name . '</small>';
                                            }
                                            $service_badge_class = 'bg-primary';
                                        } elseif ($booking_type == 'food_order') {
                                            // Get food items for this order
                                            $food_items_query = "SELECT GROUP_CONCAT(item_name SEPARATOR ', ') as items 
                                                                FROM food_order_items 
                                                                WHERE order_id = (SELECT id FROM food_orders WHERE booking_id = {$booking['id']} LIMIT 1)";
                                            $food_result = $conn->query($food_items_query);
                                            $food_data = $food_result ? $food_result->fetch_assoc() : null;
                                            $items_text = $food_data['items'] ?? 'Food items';
                                            // Truncate if too long
                                            if (strlen($items_text) > 40) {
                                                $items_text = substr($items_text, 0, 37) . '...';
                                            }
                                            $service_display = '<strong>Food Order</strong><br><small>' . htmlspecialchars($items_text) . '</small>';
                                            $service_badge_class = 'bg-success';
                                        } elseif ($booking_type == 'spa_service') {
                                            $service_display = '<strong>Spa & Wellness</strong><br><small>' . htmlspecialchars($booking['service_name'] ?? 'Spa service') . '</small>';
                                            $service_badge_class = 'bg-info';
                                        } elseif ($booking_type == 'laundry_service') {
                                            $service_display = '<strong>Laundry Service</strong><br><small>' . htmlspecialchars($booking['service_name'] ?? 'Laundry') . '</small>';
                                            $service_badge_class = 'bg-warning text-dark';
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong class="text-primary"><?php echo htmlspecialchars($booking['booking_reference'] ?? ''); ?></strong>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($booking['guest_name'] ?? ''); ?></strong>
                                        </td>
                                        <td>
                                            <small>
                                                <i class="fas fa-envelope text-muted"></i> <?php echo htmlspecialchars($booking['email'] ?? ''); ?><br>
                                                <i class="fas fa-phone text-muted"></i> <?php echo htmlspecialchars($booking['phone'] ?? 'Not provided'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge service-type-badge <?php echo $service_badge_class; ?>">
                                                <?php echo $service_display; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $booking['nights'] ? $booking['nights'] . ' night(s)' : 'N/A'; ?></td>
                                        <td>
                                            <strong><?php echo format_currency($booking['total_price']); ?></strong>
                                            <?php if ($booking_type == 'room' && $booking['price'] > 0): ?>
                                            <br><small class="text-muted"><?php echo format_currency($booking['price']); ?>/night</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $payment_status = $booking['payment_status'] ?? 'pending';
                                            $verification_status = $booking['verification_status'] ?? 'pending';
                                            
                                            // Determine badge based on both payment_status and verification_status
                                            if ($payment_status == 'paid' || $verification_status == 'verified') {
                                                $badge_class = 'success';
                                                $status_text = '✅ Prepaid';
                                            } elseif ($verification_status == 'pending_verification') {
                                                $badge_class = 'info';
                                                $status_text = '⏳ Verifying';
                                            } else {
                                                $badge_class = 'warning';
                                                $status_text = '⏳ Payment Due';
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $badge_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                            <?php if ($booking['payment_method']): ?>
                                            <br><small class="text-muted"><?php echo ucfirst($booking['payment_method']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($booking['status'] == 'checked_in'): ?>
                                                <span class="badge bg-success" style="font-size: 0.9rem; padding: 0.5rem;">
                                                    <i class="fas fa-check-circle"></i> 
                                                    <?php echo $booking_type == 'room' ? 'Checked In' : 'Completed'; ?>
                                                </span>
                                                <?php if (!empty($booking['actual_checkin_time'])): ?>
                                                <br><small class="text-muted">
                                                    <?php echo date('g:i A', strtotime($booking['actual_checkin_time'])); ?>
                                                </small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php if ($booking_type == 'room'): ?>
                                                    <a href="receptionist-checkin.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-success btn-sm">
                                                        <i class="fas fa-key"></i> Check In
                                                    </a>
                                                <?php else: ?>
                                                    <span class="badge bg-info" style="font-size: 0.9rem; padding: 0.5rem;">
                                                        <i class="fas fa-clock"></i> Ready
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle"></i> No check-ins scheduled for today.
                            
                            <?php if (!empty($debug_bookings)): ?>
                            <hr>
                            <strong>Debug Info - Bookings for today (<?php echo $today; ?>):</strong>
                            <ul class="mt-2 mb-0">
                                <?php foreach ($debug_bookings as $db): ?>
                                <li>
                                    <strong><?php echo $db['booking_reference']; ?></strong> - <?php echo $db['guest_name']; ?><br>
                                    <small>
                                        Status: <span class="badge bg-secondary"><?php echo $db['status']; ?></span>
                                        Verification: <span class="badge bg-secondary"><?php echo $db['verification_status']; ?></span>
                                        Payment: <span class="badge bg-secondary"><?php echo $db['payment_status']; ?></span>
                                    </small>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <small class="text-muted">
                                <strong>Note:</strong> Bookings only appear in check-ins when Status=confirmed AND Verification=verified
                            </small>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Today's Check-outs -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="fas fa-sign-out-alt"></i> Today's Check-outs
                            <span class="badge bg-dark float-end"><?php echo $checkouts->num_rows; ?> Guest(s)</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($checkouts->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Booking Ref</th>
                                        <th>Guest Name</th>
                                        <th>Contact</th>
                                        <th>Room</th>
                                        <th>Stay Duration</th>
                                        <th>Total Amount</th>
                                        <th>Deposit</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($booking = $checkouts->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-warning"><?php echo htmlspecialchars($booking['booking_reference'] ?? ''); ?></strong>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($booking['guest_name'] ?? ''); ?></strong>
                                        </td>
                                        <td>
                                            <small>
                                                <i class="fas fa-envelope text-muted"></i> <?php echo htmlspecialchars($booking['email'] ?? ''); ?><br>
                                                <i class="fas fa-phone text-muted"></i> <?php echo htmlspecialchars($booking['phone'] ?? 'Not provided'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark">
                                                <?php echo htmlspecialchars($booking['room_name'] ?? ''); ?><br>
                                                Room <?php echo htmlspecialchars($booking['room_number'] ?? ''); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $booking['nights']; ?> night(s)<br>
                                            <small class="text-muted">
                                                <?php echo $booking['check_in_date'] ? date('M j', strtotime($booking['check_in_date'])) : 'N/A'; ?> - 
                                                <?php echo $booking['check_out_date'] ? date('M j', strtotime($booking['check_out_date'])) : 'N/A'; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <strong><?php echo format_currency($booking['total_price']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo format_currency($booking['incidental_deposit'] ?? 0); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="receptionist-checkout.php" class="btn btn-warning btn-sm">
                                                <i class="fas fa-receipt"></i> Check Out
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle"></i> No check-outs scheduled for today.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pending Bookings -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-clock"></i> Pending Bookings (Awaiting Confirmation)
                            <span class="badge bg-light text-info float-end"><?php echo $pending_bookings->num_rows; ?> Booking(s)</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($pending_bookings->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Booking Ref</th>
                                        <th>Guest Name</th>
                                        <th>Contact</th>
                                        <th>Service Type</th>
                                        <th>Check-in Date</th>
                                        <th>Nights</th>
                                        <th>Total</th>
                                        <th>Payment</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($booking = $pending_bookings->fetch_assoc()): 
                                        // Determine service type display
                                        $booking_type = $booking['booking_type'];
                                        $service_display = '';
                                        $service_badge_class = 'bg-secondary';
                                        
                                        if ($booking_type == 'room') {
                                            $room_name = htmlspecialchars($booking['room_name']);
                                            $room_number = htmlspecialchars($booking['room_number']);
                                            
                                            // Format: "Room Name - Room Number"
                                            // Only show room number if it exists and is not empty
                                            if (!empty($room_number) && $room_number != 'N/A' && $room_number != '' && $room_number != $room_name) {
                                                $service_display = '<strong>Room</strong><br><small>' . $room_name . '<br>Room ' . $room_number . '</small>';
                                            } else {
                                                $service_display = '<strong>Room</strong><br><small>' . $room_name . '</small>';
                                            }
                                            $service_badge_class = 'bg-primary';
                                        } elseif ($booking_type == 'food_order') {
                                            // Get food items for this order
                                            $food_items_query = "SELECT GROUP_CONCAT(item_name SEPARATOR ', ') as items 
                                                                FROM food_order_items 
                                                                WHERE order_id = (SELECT id FROM food_orders WHERE booking_id = {$booking['id']} LIMIT 1)";
                                            $food_result = $conn->query($food_items_query);
                                            $food_data = $food_result ? $food_result->fetch_assoc() : null;
                                            $items_text = $food_data['items'] ?? 'Food items';
                                            // Truncate if too long
                                            if (strlen($items_text) > 40) {
                                                $items_text = substr($items_text, 0, 37) . '...';
                                            }
                                            $service_display = '<strong>Food Order</strong><br><small>' . htmlspecialchars($items_text) . '</small>';
                                            $service_badge_class = 'bg-success';
                                        } elseif ($booking_type == 'spa_service') {
                                            $service_display = '<strong>Spa & Wellness</strong><br><small>' . htmlspecialchars($booking['service_name'] ?? 'Spa service') . '</small>';
                                            $service_badge_class = 'bg-info';
                                        } elseif ($booking_type == 'laundry_service') {
                                            $service_display = '<strong>Laundry Service</strong><br><small>' . htmlspecialchars($booking['service_name'] ?? 'Laundry') . '</small>';
                                            $service_badge_class = 'bg-warning text-dark';
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong class="text-info"><?php echo htmlspecialchars($booking['booking_reference'] ?? ''); ?></strong>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($booking['guest_name'] ?? ''); ?></strong>
                                        </td>
                                        <td>
                                            <small>
                                                <i class="fas fa-envelope text-muted"></i> <?php echo htmlspecialchars($booking['email'] ?? ''); ?><br>
                                                <i class="fas fa-phone text-muted"></i> <?php echo htmlspecialchars($booking['phone'] ?? 'Not provided'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge service-type-badge <?php echo $service_badge_class; ?>">
                                                <?php echo $service_display; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $booking['check_in_date'] ? date('M j, Y', strtotime($booking['check_in_date'])) : 'N/A'; ?></td>
                                        <td><?php echo $booking['nights'] ? $booking['nights'] . ' night(s)' : 'N/A'; ?></td>
                                        <td><strong><?php echo format_currency($booking['total_price']); ?></strong></td>
                                        <td>
                                            <?php 
                                            $payment_status = $booking['payment_status'] ?? 'pending';
                                            $verification_status = $booking['verification_status'] ?? 'pending';
                                            
                                            // Determine badge based on both payment_status and verification_status
                                            if ($payment_status == 'paid' || $verification_status == 'verified') {
                                                $badge_class = 'success';
                                                $status_text = 'Paid';
                                            } elseif ($verification_status == 'pending_verification') {
                                                $badge_class = 'info';
                                                $status_text = 'Verifying';
                                            } else {
                                                $badge_class = 'warning';
                                                $status_text = 'Pending';
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $badge_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-success" onclick="confirmBooking('<?php echo $booking['booking_reference']; ?>')">
                                                    <i class="fas fa-check"></i> Confirm
                                                </button>
                                                <button class="btn btn-danger" onclick="cancelBooking('<?php echo $booking['booking_reference']; ?>')">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle"></i> No pending bookings.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function checkIn(ref) {
            if (confirm('Check in guest with booking ' + ref + '?')) {
                fetch('receptionist.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=checkin&booking_ref=' + ref
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error: ' + error);
                });
            }
        }
        
        function checkOut(ref) {
            if (confirm('Check out guest with booking ' + ref + '?')) {
                fetch('receptionist.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=checkout&booking_ref=' + ref
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error: ' + error);
                });
            }
        }
        
        function confirmBooking(ref) {
            if (confirm('Confirm booking ' + ref + '?')) {
                fetch('receptionist.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=confirm_booking&booking_ref=' + ref
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error: ' + error);
                });
            }
        }
        
        function cancelBooking(ref) {
            if (confirm('Cancel booking ' + ref + '? This action cannot be undone.')) {
                fetch('receptionist.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=cancel_booking&booking_ref=' + ref
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error: ' + error);
                });
            }
        }
    </script>
</body>
</html>