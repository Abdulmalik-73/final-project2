<?php
/**
 * My Bookings Page - Protected
 * Requires: User authentication
 */

session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Require authentication and prevent caching
require_auth('login.php');

// Get user's bookings with payment verification status (including food orders)
$user_id = $_SESSION['user_id'];
$query = "SELECT b.*, 
          CASE 
              WHEN b.booking_type = 'spa_service' THEN 'Spa & Wellness'
              WHEN b.booking_type = 'laundry_service' THEN 'Laundry Service'
              WHEN b.booking_type = 'food_order' THEN 'Food Order'
              ELSE COALESCE(r.name, 'Room Booking')
          END as room_name,
          COALESCE(r.room_number, 'N/A') as room_number, 
          COALESCE(r.image, '') as room_image,
          CASE 
              WHEN b.verification_status = 'pending_payment' AND b.payment_deadline < NOW() THEN 'expired'
              ELSE b.verification_status
          END as current_verification_status,
          CONCAT(verifier.first_name, ' ', verifier.last_name) as verified_by_name,
          pmi.method_name as payment_method_name,
          pmi.bank_name,
          fo.table_reservation,
          fo.reservation_date,
          fo.reservation_time,
          fo.guests as food_guests,
          sb.service_name,
          sb.service_date,
          sb.service_time,
          sb.quantity as service_quantity,
          ref.refund_status,
          ref.final_refund as refund_final_amount,
          ref.refund_reference,
          ref.processed_date as refund_processed_date
          FROM bookings b 
          LEFT JOIN rooms r ON b.room_id = r.id 
          LEFT JOIN users verifier ON b.verified_by = verifier.id
          LEFT JOIN payment_method_instructions pmi ON b.payment_method = pmi.method_code
          LEFT JOIN food_orders fo ON b.id = fo.booking_id
          LEFT JOIN service_bookings sb ON b.id = sb.booking_id
          LEFT JOIN refunds ref ON b.id = ref.booking_id
          WHERE b.user_id = ? 
          ORDER BY b.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('my_bookings.title'); ?> - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Page Header -->
    <section class="py-4 bg-light">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-auto">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> <?php echo __('my_bookings.back_to_home'); ?>
                    </a>
                </div>
                <div class="col text-center">
                    <h1 class="display-5 fw-bold mb-3"><?php echo __('my_bookings.title'); ?></h1>
                    <p class="lead text-muted"><?php echo __('my_bookings.subtitle'); ?></p>
                </div>
                <div class="col-auto">
                    <!-- Spacer for centering -->
                </div>
            </div>
        </div>
    </section>
    
    <!-- Bookings Section -->
    <section class="py-5">
        <div class="container">
            <!-- Error Message Display -->
            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php 
                echo htmlspecialchars($_SESSION['error_message']); 
                unset($_SESSION['error_message']); // Clear the message after displaying
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if (empty($bookings)): ?>
            <div class="text-center py-5">
                <i class="fas fa-calendar-times fa-4x text-muted mb-4"></i>
                <h3><?php echo __('my_bookings.no_bookings'); ?></h3>
                <p class="text-muted mb-4"><?php echo __('my_bookings.no_bookings_text'); ?></p>
                <div class="d-flex gap-3 justify-content-center">
                    <a href="rooms.php" class="btn btn-gold btn-lg">
                        <i class="fas fa-bed"></i> <?php echo __('my_bookings.browse_rooms'); ?>
                    </a>
                    <a href="food-booking.php" class="btn btn-outline-gold btn-lg">
                        <i class="fas fa-utensils"></i> Order Food
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="row">
                <?php foreach ($bookings as $booking): ?>
                <div class="col-lg-6 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <?php if ($booking['booking_type'] == 'food_order'): ?>
                                    <i class="fas fa-utensils text-gold"></i>
                                    Food Order
                                <?php elseif ($booking['booking_type'] == 'spa_service'): ?>
                                    <i class="fas fa-spa text-gold"></i>
                                    Spa & Wellness
                                <?php elseif ($booking['booking_type'] == 'laundry_service'): ?>
                                    <i class="fas fa-tshirt text-gold"></i>
                                    Laundry Service
                                <?php else: ?>
                                    <i class="fas fa-bed text-gold"></i>
                                    <?php echo htmlspecialchars($booking['room_name']); ?>
                                <?php endif; ?>
                            </h5>
                            <?php
                            $status_class = 'secondary';
                            $vs = $booking['current_verification_status'] ?? $booking['status'];
                            $status_map = [
                                'pending'              => __('my_bookings.status_pending'),
                                'pending_payment'      => __('my_bookings.status_pending'),
                                'pending_verification' => __('my_bookings.status_pending_verification'),
                                'verified'             => __('my_bookings.status_verified'),
                                'confirmed'            => __('my_bookings.status_confirmed'),
                                'rejected'             => __('my_bookings.status_rejected'),
                                'cancelled'            => __('my_bookings.status_cancelled'),
                                'expired'              => __('my_bookings.status_expired'),
                                'checked_in'           => __('my_bookings.status_checked_in'),
                                'checked_out'          => __('my_bookings.status_checked_out'),
                            ];
                            $status_text = $status_map[$vs] ?? ucfirst(str_replace('_', ' ', $vs));
                            
                            switch($vs) {
                                case 'pending':
                                case 'pending_payment':
                                    $status_class = 'warning';
                                    break;
                                case 'pending_verification':
                                    $status_class = 'info';
                                    break;
                                case 'verified':
                                case 'confirmed':
                                    $status_class = 'success';
                                    break;
                                case 'rejected':
                                case 'cancelled':
                                    $status_class = 'danger';
                                    break;
                                case 'expired':
                                    $status_class = 'dark';
                                    break;
                                case 'checked_in':
                                    $status_class = 'primary';
                                    break;
                                case 'checked_out':
                                    $status_class = 'secondary';
                                    break;
                            }
                            ?>
                            <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">
                                        <?php 
                                        if ($booking['booking_type'] == 'food_order') {
                                            echo __('my_bookings.order_reference');
                                        } elseif (in_array($booking['booking_type'], ['spa_service', 'laundry_service'])) {
                                            echo __('my_bookings.service_reference');
                                        } else {
                                            echo __('my_bookings.booking_reference');
                                        }
                                        ?>
                                    </small>
                                    <div class="fw-bold"><?php echo $booking['booking_reference']; ?></div>
                                </div>
                                <div class="col-6">
                                    <?php if ($booking['booking_type'] == 'food_order'): ?>
                                        <small class="text-muted"><?php echo __('my_bookings.table_reserved'); ?></small>
                                        <div class="fw-bold"><?php echo $booking['table_reservation'] ? __('my_bookings.yes') : __('my_bookings.no_takeaway'); ?></div>
                                    <?php elseif (in_array($booking['booking_type'], ['spa_service', 'laundry_service'])): ?>
                                        <small class="text-muted"><?php echo __('my_bookings.service_name'); ?></small>
                                        <div class="fw-bold"><?php echo htmlspecialchars($booking['service_name'] ?? 'Service'); ?></div>
                                    <?php else: ?>
                                        <small class="text-muted"><?php echo __('my_bookings.room_number'); ?></small>
                                        <div class="fw-bold"><?php echo $booking['room_number']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <?php if ($booking['booking_type'] == 'food_order'): ?>
                                    <div class="col-6">
                                        <small class="text-muted"><?php echo __('my_bookings.guests'); ?></small>
                                        <div><?php echo $booking['food_guests']; ?> <?php echo __('my_bookings.guest_count'); ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted"><?php echo __('my_bookings.reservation'); ?></small>
                                        <div>
                                            <?php if ($booking['table_reservation'] && $booking['reservation_date']): ?>
                                                <?php echo date('M j, Y', strtotime($booking['reservation_date'])); ?>
                                                <?php if ($booking['reservation_time']): ?>
                                                    <br><small><?php echo date('g:i A', strtotime($booking['reservation_time'])); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                Not specified
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php elseif (in_array($booking['booking_type'], ['spa_service', 'laundry_service'])): ?>
                                    <div class="col-6">
                                        <small class="text-muted"><?php echo __('my_bookings.service_date'); ?></small>
                                        <div><?php echo !empty($booking['service_date']) ? date('M j, Y', strtotime($booking['service_date'])) : __('my_bookings.na'); ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted"><?php echo __('my_bookings.service_time'); ?></small>
                                        <div><?php echo !empty($booking['service_time']) ? date('g:i A', strtotime($booking['service_time'])) : __('my_bookings.na'); ?></div>
                                    </div>
                                <?php else: ?>
                                    <div class="col-6">
                                        <small class="text-muted"><?php echo __('my_bookings.check_in'); ?></small>
                                        <div><?php echo $booking['check_in_date'] ? date('M j, Y', strtotime($booking['check_in_date'])) : __('my_bookings.na'); ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted"><?php echo __('my_bookings.check_out'); ?></small>
                                        <div><?php echo $booking['check_out_date'] ? date('M j, Y', strtotime($booking['check_out_date'])) : __('my_bookings.na'); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">
                                        <?php 
                                        if ($booking['booking_type'] == 'food_order') {
                                            echo __('my_bookings.order_type');
                                        } elseif (in_array($booking['booking_type'], ['spa_service', 'laundry_service'])) {
                                            echo __('my_bookings.quantity');
                                        } else {
                                            echo __('my_bookings.room_guests');
                                        }
                                        ?>
                                    </small>
                                    <div>
                                        <?php if ($booking['booking_type'] == 'food_order'): ?>
                                            <?php echo $booking['table_reservation'] ? __('my_bookings.dine_in') : __('my_bookings.takeaway'); ?>
                                        <?php elseif (in_array($booking['booking_type'], ['spa_service', 'laundry_service'])): ?>
                                            <?php echo ($booking['service_quantity'] ?? 1) . ' ' . __('my_bookings.sessions'); ?>
                                        <?php else: ?>
                                            <?php echo $booking['customers']; ?> <?php echo __('my_bookings.guest_count'); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted"><?php echo __('my_bookings.total_amount'); ?></small>
                                    <div class="fw-bold text-success"><?php echo format_currency($booking['total_price']); ?></div>
                                </div>
                            </div>
                            
                            <?php if ($booking['status'] === 'cancelled' && !empty($booking['refund_status'])): ?>
                            <div class="alert alert-info mb-3">
                                <div class="d-flex align-items-start">
                                    <i class="fas fa-info-circle fs-4 me-3"></i>
                                    <div class="flex-grow-1">
                                        <h6 class="alert-heading mb-2">
                                            <i class="fas fa-undo-alt me-2"></i>Refund Status
                                        </h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <small class="text-muted d-block">Status:</small>
                                                <?php
                                                $refund_badge_class = 'secondary';
                                                if ($booking['refund_status'] === 'Pending') {
                                                    $refund_badge_class = 'warning';
                                                } elseif ($booking['refund_status'] === 'Processed') {
                                                    $refund_badge_class = 'success';
                                                } elseif ($booking['refund_status'] === 'Rejected') {
                                                    $refund_badge_class = 'danger';
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $refund_badge_class; ?> fs-6">
                                                    <?php echo $booking['refund_status']; ?>
                                                </span>
                                            </div>
                                            <div class="col-md-6">
                                                <small class="text-muted d-block">Refund Amount:</small>
                                                <strong class="text-success fs-5">
                                                    ETB <?php echo number_format($booking['refund_final_amount'], 2); ?>
                                                </strong>
                                            </div>
                                        </div>
                                        <?php if ($booking['refund_status'] === 'Processed'): ?>
                                        <div class="mt-2">
                                            <small class="text-muted d-block">Processed Date:</small>
                                            <strong><?php echo date('M j, Y g:i A', strtotime($booking['refund_processed_date'])); ?></strong>
                                        </div>
                                        <div class="alert alert-success mt-2 mb-0">
                                            <i class="fas fa-check-circle me-2"></i>
                                            <strong>Refunded!</strong> Your refund of <strong>ETB <?php echo number_format($booking['refund_final_amount'], 2); ?></strong> has been processed and will be credited to your original payment method within 5-7 business days.
                                        </div>
                                        <?php elseif ($booking['refund_status'] === 'Pending'): ?>
                                        <div class="alert alert-warning mt-2 mb-0">
                                            <i class="fas fa-clock me-2"></i>
                                            Your refund request is pending manager approval. You will be notified once it's processed.
                                        </div>
                                        <?php elseif ($booking['refund_status'] === 'Rejected'): ?>
                                        <div class="alert alert-danger mt-2 mb-0">
                                            <i class="fas fa-times-circle me-2"></i>
                                            Your refund request was rejected. Please contact hotel management for more information.
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($booking['refund_reference'])): ?>
                                        <div class="mt-2">
                                            <small class="text-muted">Refund Reference:</small>
                                            <code><?php echo htmlspecialchars($booking['refund_reference']); ?></code>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($booking['special_requests']): ?>
                            <div class="mb-3">
                                <small class="text-muted"><?php echo __('my_bookings.special_requests'); ?></small>
                                <div class="small"><?php echo htmlspecialchars($booking['special_requests']); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="d-flex gap-2 flex-wrap">
                                <?php if (in_array($booking['current_verification_status'] ?? $booking['status'], ['pending_payment', 'rejected'])): ?>
                                <a href="payment-upload.php?booking=<?php echo $booking['id']; ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-upload"></i> <?php echo __('my_bookings.upload_payment'); ?>
                                </a>
                                <?php endif; ?>
                                
                                <?php if (($booking['current_verification_status'] ?? $booking['status']) == 'verified'): ?>
                                <a href="booking-confirmation.php?ref=<?php echo $booking['booking_reference']; ?>" class="btn btn-success btn-sm">
                                    <i class="fas fa-file-alt"></i> <?php echo __('my_bookings.view_confirmation'); ?>
                                </a>
                                <?php endif; ?>
                                
                                <button class="btn btn-outline-primary btn-sm" onclick="viewBookingDetails('<?php echo $booking['booking_reference']; ?>')">
                                    <i class="fas fa-eye"></i> <?php echo __('my_bookings.view_details'); ?>
                                </button>
                                
                                <button class="btn btn-outline-secondary btn-sm" onclick="printBooking(<?php echo htmlspecialchars(json_encode($booking)); ?>)">
                                    <i class="fas fa-print"></i> <?php echo __('my_bookings.print'); ?>
                                </button>
                                
                                <?php 
                                $booking_status = $booking['current_verification_status'] ?? $booking['status'];
                                
                                // Check if booking can be cancelled
                                $can_cancel = false;
                                
                                // Must be in cancellable status
                                $cancellable_statuses = ['pending', 'confirmed', 'verified', 'pending_payment', 'pending_verification'];
                                
                                // Must not be food order
                                $is_not_food_order = $booking['booking_type'] != 'food_order';
                                
                                // Must not be already cancelled or completed
                                $not_cancelled_or_completed = !in_array($booking_status, ['cancelled', 'checked_out', 'no_show']);
                                
                                // Check-in date must be in the future (allow cancellation until check-in date)
                                $check_in_future = false;
                                if (!empty($booking['check_in_date'])) {
                                    $check_in_date = new DateTime($booking['check_in_date']);
                                    $current_date = new DateTime();
                                    $current_date->setTime(0, 0, 0); // Set to start of day
                                    $check_in_future = $current_date <= $check_in_date;
                                }
                                
                                // Final cancellation check
                                $can_cancel = in_array($booking_status, $cancellable_statuses) && 
                                              $is_not_food_order && 
                                              $not_cancelled_or_completed && 
                                              $check_in_future;
                                
                                if ($can_cancel): 
                                ?>
                                <button class="btn btn-outline-danger btn-sm" onclick="cancelBooking('<?php echo $booking['booking_reference']; ?>')">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer text-muted">
                            <small>
                                <i class="fas fa-clock"></i>
                                <?php echo __('my_bookings.booked_on'); ?> <?php echo date('M j, Y g:i A', strtotime($booking['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>
    
    <?php include 'includes/footer.php'; ?>
    
    <!-- Modern Cancellation Modal -->
    <div class="modal fade" id="cancelBookingModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white;">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Harar Ras Hotel - Cancellation Policy
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Cancellation Policy Card -->
                    <div class="card border-0 bg-light mb-4" id="policyCard">
                        <div class="card-body">
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Refund Schedule
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center p-3 bg-white rounded shadow-sm">
                                        <div class="flex-shrink-0">
                                            <div class="badge bg-success rounded-circle p-3" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                                <span class="fs-6 fw-bold">95%</span>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="fw-bold text-dark">7+ days before arrival</div>
                                            <small class="text-muted">Full refund minus 5% fee</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center p-3 bg-white rounded shadow-sm">
                                        <div class="flex-shrink-0">
                                            <div class="badge bg-info rounded-circle p-3" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                                <span class="fs-6 fw-bold">75%</span>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="fw-bold text-dark">3-6 days before arrival</div>
                                            <small class="text-muted">Partial refund</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center p-3 bg-white rounded shadow-sm">
                                        <div class="flex-shrink-0">
                                            <div class="badge bg-warning rounded-circle p-3" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                                <span class="fs-6 fw-bold">50%</span>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="fw-bold text-dark">1-2 days before arrival</div>
                                            <small class="text-muted">Half refund</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center p-3 bg-white rounded shadow-sm">
                                        <div class="flex-shrink-0">
                                            <div class="badge bg-danger rounded-circle p-3" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                                <span class="fs-6 fw-bold">25%</span>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="fw-bold text-dark">Same day cancellation</div>
                                            <small class="text-muted">Minimal refund</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-warning mt-3 mb-0">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <small><strong>Note:</strong> All refunds are subject to a 5% processing fee</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Loading State -->
                    <div id="cancelLoading" class="text-center py-5" style="display:none;">
                        <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <h6 class="text-muted">Calculating your refund...</h6>
                        <p class="small text-muted mb-0">Please wait while we process your request</p>
                    </div>
                    
                    <!-- Refund Calculation Results -->
                    <div id="refundCalculation" style="display:none;">
                        <div class="card border-primary mb-3">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="fas fa-calculator me-2"></i>Your Refund Calculation</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="border-start border-primary border-4 ps-3">
                                            <small class="text-muted d-block">Booking Reference</small>
                                            <strong id="cancel_booking_ref" class="text-dark"></strong>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="border-start border-primary border-4 ps-3">
                                            <small class="text-muted d-block">Room/Service</small>
                                            <strong id="cancel_room_name" class="text-dark"></strong>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="border-start border-info border-4 ps-3">
                                            <small class="text-muted d-block">Check-in Date</small>
                                            <strong id="cancel_checkin_date" class="text-dark"></strong>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="border-start border-info border-4 ps-3">
                                            <small class="text-muted d-block">Days Before Check-in</small>
                                            <span id="cancel_days_before" class="badge bg-info fs-6"></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <hr class="my-3">
                                
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                            <span class="text-muted">Total Amount Paid</span>
                                            <strong class="fs-5">ETB <span id="cancel_total_amount"></span></strong>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                            <span class="text-muted">Refund Percentage</span>
                                            <span id="cancel_refund_percentage" class="badge bg-success fs-6"></span>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                            <span class="text-muted">Refund Amount</span>
                                            <strong class="text-success">ETB <span id="cancel_refund_amount"></span></strong>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                            <span class="text-muted">Processing Fee (5%)</span>
                                            <strong class="text-danger">- ETB <span id="cancel_processing_fee"></span></strong>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center p-4 rounded" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
                                            <span class="fs-5 fw-bold">Final Refund Amount</span>
                                            <strong class="fs-3">ETB <span id="cancel_final_refund"></span></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info border-0 shadow-sm">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-clock fs-4 me-3 mt-1"></i>
                                <div>
                                    <h6 class="alert-heading mb-2">Refund Processing Time</h6>
                                    <p class="mb-0 small">Your refund will be processed within <strong>5-7 business days</strong> and credited to your original payment method.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Error Display -->
                    <div id="cancelError" class="alert alert-danger border-0 shadow-sm" style="display:none;">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-exclamation-triangle fs-4 me-3 mt-1"></i>
                            <div id="cancelErrorMessage"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Close
                    </button>
                    <button type="button" class="btn btn-danger px-4" id="confirmCancelBtn" onclick="confirmCancellation()" style="display:none;">
                        <i class="fas fa-check me-2"></i>Confirm Cancellation
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
    <script>
        let currentBookingRef = '';
        
        function viewBookingDetails(reference) {
            window.location.href = 'booking-details.php?ref=' + reference;
        }
        
        function cancelBooking(reference) {
            currentBookingRef = reference;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('cancelBookingModal'));
            modal.show();
            
            // Reset modal state - show policy card initially
            document.getElementById('policyCard').style.display = 'block';
            document.getElementById('cancelLoading').style.display = 'none';
            document.getElementById('refundCalculation').style.display = 'none';
            document.getElementById('cancelError').style.display = 'none';
            document.getElementById('confirmCancelBtn').style.display = 'none';
            
            // Auto-calculate refund after 1 second
            setTimeout(() => {
                // Hide policy card and show loading
                document.getElementById('policyCard').style.display = 'none';
                document.getElementById('cancelLoading').style.display = 'block';
                
                // Calculate refund
                fetch('api/cancel_booking.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        booking_reference: reference,
                        action: 'calculate'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('cancelLoading').style.display = 'none';
                    
                    if (data.success) {
                        // Display refund calculation
                        document.getElementById('cancel_booking_ref').textContent = data.data.booking_reference;
                        document.getElementById('cancel_room_name').textContent = data.data.room_name;
                        document.getElementById('cancel_checkin_date').textContent = data.data.check_in_date;
                        document.getElementById('cancel_days_before').textContent = data.data.days_before_checkin + ' days';
                        document.getElementById('cancel_total_amount').textContent = data.data.total_amount;
                        document.getElementById('cancel_refund_percentage').textContent = data.data.refund_percentage + '%';
                        document.getElementById('cancel_refund_amount').textContent = data.data.refund_amount;
                        document.getElementById('cancel_processing_fee').textContent = data.data.processing_fee;
                        document.getElementById('cancel_final_refund').textContent = data.data.final_refund;
                        
                        document.getElementById('refundCalculation').style.display = 'block';
                        document.getElementById('confirmCancelBtn').style.display = 'inline-block';
                    } else {
                        document.getElementById('cancelErrorMessage').textContent = data.error;
                        document.getElementById('cancelError').style.display = 'block';
                        document.getElementById('confirmCancelBtn').style.display = 'none';
                    }
                })
                .catch(error => {
                    document.getElementById('cancelLoading').style.display = 'none';
                    document.getElementById('cancelErrorMessage').textContent = 'Failed to calculate refund. Please try again.';
                    document.getElementById('cancelError').style.display = 'block';
                    document.getElementById('confirmCancelBtn').style.display = 'none';
                });
            }, 1500);
        }
        
        function confirmCancellation() {
            // Show confirmation dialog
            if (!confirm('⚠️ Are you sure you want to cancel this booking?\n\nThis action cannot be undone. Your refund will be processed according to our cancellation policy.')) {
                return;
            }
            
            // Disable button and show loading state
            const btn = document.getElementById('confirmCancelBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing Cancellation...';
            
            // Hide error if visible
            document.getElementById('cancelError').style.display = 'none';
            
            fetch('api/cancel_booking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    booking_reference: currentBookingRef,
                    action: 'confirm'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const successHtml = `
                        <div class="alert alert-success border-0 shadow-lg">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-check-circle fs-1 me-3 text-success"></i>
                                <div>
                                    <h5 class="alert-heading mb-2">Booking Cancelled Successfully!</h5>
                                    <p class="mb-2">${data.message}</p>
                                    <hr>
                                    <div class="mb-0">
                                        <strong>Refund Reference:</strong> ${data.data.refund_reference}<br>
                                        <strong>Refund Amount:</strong> <span class="text-success fs-5">ETB ${data.data.final_refund}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Replace modal body with success message
                    document.querySelector('#cancelBookingModal .modal-body').innerHTML = successHtml;
                    
                    // Update footer
                    document.querySelector('#cancelBookingModal .modal-footer').innerHTML = `
                        <button type="button" class="btn btn-success px-4" onclick="location.reload()">
                            <i class="fas fa-sync me-2"></i>Refresh Page
                        </button>
                    `;
                    
                    // Auto-reload after 3 seconds
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                } else {
                    // Show error
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check me-2"></i>Confirm Cancellation';
                    document.getElementById('cancelErrorMessage').textContent = data.error;
                    document.getElementById('cancelError').style.display = 'block';
                }
            })
            .catch(error => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check me-2"></i>Confirm Cancellation';
                document.getElementById('cancelErrorMessage').textContent = 'Network error. Please check your connection and try again.';
                document.getElementById('cancelError').style.display = 'block';
            });
        }
        
        // Override formatCurrency function to ensure ETB display
        function formatCurrency(amount) {
            return 'ETB ' + parseFloat(amount).toFixed(2);
        }
        
        // Print booking information
        function printBooking(booking) {
            const printWindow = window.open('', '_blank');
            
            // Determine booking type display
            let bookingTypeIcon = '<i class="fas fa-bed"></i>';
            let bookingTypeText = 'Room Booking';
            
            if (booking.booking_type === 'food_order') {
                bookingTypeIcon = '<i class="fas fa-utensils"></i>';
                bookingTypeText = 'Food Order';
            } else if (booking.booking_type === 'spa_service') {
                bookingTypeIcon = '<i class="fas fa-spa"></i>';
                bookingTypeText = 'Spa & Wellness Service';
            } else if (booking.booking_type === 'laundry_service') {
                bookingTypeIcon = '<i class="fas fa-tshirt"></i>';
                bookingTypeText = 'Laundry Service';
            }
            
            // Format dates
            const checkInDate = booking.check_in_date ? new Date(booking.check_in_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';
            const checkOutDate = booking.check_out_date ? new Date(booking.check_out_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';
            const createdDate = new Date(booking.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
            
            // Status badge color
            let statusColor = '#6c757d';
            const status = booking.current_verification_status || booking.status;
            
            switch(status) {
                case 'pending':
                case 'pending_payment':
                    statusColor = '#ffc107';
                    break;
                case 'pending_verification':
                    statusColor = '#0dcaf0';
                    break;
                case 'verified':
                case 'confirmed':
                    statusColor = '#198754';
                    break;
                case 'rejected':
                case 'cancelled':
                    statusColor = '#dc3545';
                    break;
                case 'checked_in':
                    statusColor = '#0d6efd';
                    break;
            }
            
            const printContent = `
                <html>
                <head>
                    <title>Booking Information - ${booking.booking_reference}</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            padding: 20px;
                            max-width: 800px;
                            margin: 0 auto;
                            color: #333;
                        }
                        .header {
                            text-align: center;
                            border-bottom: 3px solid #d4af37;
                            padding-bottom: 20px;
                            margin-bottom: 30px;
                        }
                        .header h1 {
                            margin: 0;
                            color: #d4af37;
                            font-size: 2em;
                        }
                        .header p {
                            margin: 5px 0;
                            color: #666;
                        }
                        .booking-type {
                            background: #f8f9fa;
                            padding: 15px;
                            border-radius: 5px;
                            text-align: center;
                            margin-bottom: 20px;
                            font-size: 1.2em;
                            font-weight: bold;
                        }
                        .status-badge {
                            display: inline-block;
                            padding: 5px 15px;
                            border-radius: 20px;
                            color: white;
                            background: ${statusColor};
                            font-size: 0.9em;
                            margin-left: 10px;
                        }
                        .section {
                            margin-bottom: 25px;
                        }
                        .section h2 {
                            background: #f8f9fa;
                            padding: 10px 15px;
                            border-left: 4px solid #d4af37;
                            margin-bottom: 15px;
                            font-size: 1.1em;
                        }
                        .info-grid {
                            display: grid;
                            grid-template-columns: 1fr 1fr;
                            gap: 15px;
                        }
                        .info-item {
                            padding: 10px;
                            border-bottom: 1px solid #eee;
                        }
                        .info-label {
                            font-weight: bold;
                            color: #555;
                            font-size: 0.9em;
                            display: block;
                            margin-bottom: 5px;
                        }
                        .info-value {
                            color: #333;
                            font-size: 1em;
                        }
                        .price-box {
                            background: #d4af37;
                            color: white;
                            padding: 20px;
                            border-radius: 5px;
                            text-align: center;
                            margin: 20px 0;
                        }
                        .price-box .amount {
                            font-size: 2em;
                            font-weight: bold;
                        }
                        .footer {
                            margin-top: 50px;
                            padding-top: 20px;
                            border-top: 2px solid #eee;
                            text-align: center;
                            color: #666;
                            font-size: 0.9em;
                        }
                        @media print {
                            body { padding: 0; }
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Harar Ras Hotel</h1>
                        <p>Booking Information Document</p>
                        <p>Printed: ${new Date().toLocaleString()}</p>
                    </div>
                    
                    <div class="booking-type">
                        ${bookingTypeText}
                        <span class="status-badge">${status.replace(/_/g, ' ').toUpperCase()}</span>
                    </div>
                    
                    <div class="section">
                        <h2>Booking Details</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Booking Reference</span>
                                <span class="info-value">${booking.booking_reference}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Booking Date</span>
                                <span class="info-value">${createdDate}</span>
                            </div>
                            ${booking.booking_type !== 'food_order' ? `
                            <div class="info-item">
                                <span class="info-label">Room</span>
                                <span class="info-value">${booking.room_name} (${booking.room_number})</span>
                            </div>
                            ` : ''}
                            <div class="info-item">
                                <span class="info-label">Number of Guests</span>
                                <span class="info-value">${booking.customers || booking.food_guests || 1}</span>
                            </div>
                        </div>
                    </div>
                    
                    ${booking.booking_type !== 'food_order' ? `
                    <div class="section">
                        <h2>Stay Information</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Check-in Date</span>
                                <span class="info-value">${checkInDate}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Check-out Date</span>
                                <span class="info-value">${checkOutDate}</span>
                            </div>
                        </div>
                    </div>
                    ` : ''}
                    
                    ${booking.table_reservation ? `
                    <div class="section">
                        <h2>Reservation Details</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Reservation Date</span>
                                <span class="info-value">${new Date(booking.reservation_date).toLocaleDateString()}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Reservation Time</span>
                                <span class="info-value">${booking.reservation_time}</span>
                            </div>
                        </div>
                    </div>
                    ` : ''}
                    
                    ${booking.payment_method_name ? `
                    <div class="section">
                        <h2>Payment Information</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Payment Method</span>
                                <span class="info-value">${booking.payment_method_name}</span>
                            </div>
                            ${booking.bank_name ? `
                            <div class="info-item">
                                <span class="info-label">Bank</span>
                                <span class="info-value">${booking.bank_name}</span>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    ` : ''}
                    
                    <div class="price-box">
                        <div style="font-size: 1.2em; margin-bottom: 10px;">Total Amount</div>
                        <div class="amount">ETB ${parseFloat(booking.total_price).toFixed(2)}</div>
                    </div>
                    
                    ${booking.special_requests ? `
                    <div class="section">
                        <h2>Special Requests</h2>
                        <p style="padding: 10px; background: #f8f9fa; border-radius: 5px;">${booking.special_requests}</p>
                    </div>
                    ` : ''}
                    
                    <div class="footer">
                        <p><strong>Harar Ras Hotel</strong></p>
                        <p>For inquiries, please contact: support@hararrashotel.com</p>
                        <p>This is an official booking document. Please keep it for your records.</p>
                    </div>
                </body>
                </html>
            `;
            
            printWindow.document.write(printContent);
            printWindow.document.close();
            
            // Wait for content to load then print
            printWindow.onload = function() {
                printWindow.print();
            };
        }
    </script>
</body>
</html>