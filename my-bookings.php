<?php
/**
 * My Bookings Page - Protected
 * Requires: User authentication
 */

// session_start() MUST come before config.php (same as booking.php, index.php etc.)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Build absolute URL
    $proto = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    header("Location: $proto://$host/login.php");
    exit();
}

// Get user's bookings
$user_id = $_SESSION['user_id'];
$query = "SELECT b.*, 
          COALESCE(r.name, '') as room_name,
          COALESCE(r.room_number, '') as room_number, 
          COALESCE(r.image, '') as room_image,
          ref.refund_status,
          ref.final_refund as refund_final_amount,
          ref.refund_reference,
          ref.processed_at as refund_processed_date
          FROM bookings b 
          LEFT JOIN rooms r ON b.room_id = r.id 
          LEFT JOIN refunds ref ON b.id = ref.booking_id
          WHERE b.user_id = ? 
          ORDER BY b.created_at DESC";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);

// Collect booking IDs to fetch related food/service details in bulk
$booking_ids = array_column($bookings, 'id');
$food_orders_map    = [];
$service_booking_map = [];

if (!empty($booking_ids)) {
    $placeholders = implode(',', array_fill(0, count($booking_ids), '?'));
    $types = str_repeat('i', count($booking_ids));

    // Fetch food orders with their items
    $fo_stmt = $conn->prepare(
        "SELECT fo.*, 
                GROUP_CONCAT(foi.item_name ORDER BY foi.id SEPARATOR ', ') as items_list,
                GROUP_CONCAT(CONCAT(foi.quantity,'x ',foi.item_name) ORDER BY foi.id SEPARATOR ', ') as items_detail
         FROM food_orders fo
         LEFT JOIN food_order_items foi ON fo.id = foi.order_id
         WHERE fo.booking_id IN ($placeholders)
         GROUP BY fo.id"
    );
    if ($fo_stmt) {
        $fo_stmt->bind_param($types, ...$booking_ids);
        $fo_stmt->execute();
        foreach ($fo_stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $fo) {
            $food_orders_map[$fo['booking_id']] = $fo;
        }
    }

    // Fetch service bookings (spa / laundry)
    $sb_stmt = $conn->prepare(
        "SELECT * FROM service_bookings WHERE booking_id IN ($placeholders)"
    );
    if ($sb_stmt) {
        $sb_stmt->bind_param($types, ...$booking_ids);
        $sb_stmt->execute();
        foreach ($sb_stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $sb) {
            $service_booking_map[$sb['booking_id']] = $sb;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Harar Ras Hotel</title>
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
                        <i class="fas fa-arrow-left"></i> Back to Home
                    </a>
                </div>
                <div class="col text-center">
                    <h1 class="display-5 fw-bold mb-3">My Bookings</h1>
                    <p class="lead text-muted">View and manage your hotel reservations</p>
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
            <?php if (empty($bookings)): ?>
            <div class="text-center py-5">
                <i class="fas fa-calendar-times fa-4x text-muted mb-4"></i>
                <h3>No Bookings Found</h3>
                <p class="text-muted mb-4">You haven't made any bookings yet.</p>
                <div class="d-flex gap-3 justify-content-center">
                    <a href="rooms.php" class="btn btn-gold btn-lg">
                        <i class="fas fa-bed"></i> Browse Rooms
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="row">
                <?php foreach ($bookings as $booking):
                    $btype      = $booking['booking_type'] ?? 'room';
                    $food_order = $food_orders_map[$booking['id']] ?? null;
                    $svc_booking = $service_booking_map[$booking['id']] ?? null;

                    // ── Status badge ──────────────────────────────────────────
                    $status       = $booking['status'];
                    $status_class = 'secondary';
                    $status_text  = ucfirst(str_replace('_', ' ', $status));
                    if ($status === 'confirmed' || $status === 'verified') {
                        $status_class = 'success';
                    } elseif ($status === 'pending') {
                        $status_class = 'warning';
                    } elseif ($status === 'cancelled') {
                        $status_class = 'danger';
                    } elseif ($status === 'pending_cancellation' || $status === 'Pending Cancellation') {
                        $status_class = 'warning';
                        $status_text  = 'Pending Cancellation';
                    }

                    // ── Card header icon + title ──────────────────────────────
                    if ($btype === 'food_order') {
                        $card_icon  = 'fa-utensils';
                        $card_title = 'Food Order';
                    } elseif ($btype === 'spa_service') {
                        $card_icon  = 'fa-spa';
                        $card_title = 'Spa Service';
                    } elseif ($btype === 'laundry_service') {
                        $card_icon  = 'fa-tshirt';
                        $card_title = 'Laundry Service';
                    } else {
                        $card_icon  = 'fa-bed';
                        $card_title = $booking['room_name'] ?: 'Room Booking';
                    }

                    // ── Cancel eligibility ────────────────────────────────────
                    $can_cancel          = false;
                    $cancellation_pending = ($status === 'pending_cancellation' || $status === 'Pending Cancellation');
                    if (in_array($status, ['pending', 'confirmed', 'verified'])) {
                        if ($btype === 'room' && !empty($booking['check_in_date'])) {
                            $check_in_dt  = new DateTime($booking['check_in_date']);
                            $today        = new DateTime();
                            $today->setTime(0, 0, 0);
                            if ($today <= $check_in_dt) {
                                $can_cancel = true;
                            }
                        } elseif ($btype !== 'room') {
                            $can_cancel = true;
                        }
                    }
                ?>
                <div class="col-lg-6 mb-4">
                    <div class="card h-100 shadow-sm">
                        <!-- Card Header -->
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas <?php echo $card_icon; ?> text-gold me-1"></i>
                                <?php echo htmlspecialchars($card_title); ?>
                            </h5>
                            <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                        </div>

                        <div class="card-body">
                            <!-- Reference label varies by booking type -->
                            <?php
                            $ref_label = 'Booking Reference';
                            if ($btype === 'food_order')       $ref_label = 'Food Order Reference';
                            elseif ($btype === 'laundry_service') $ref_label = 'Laundry Service Reference';
                            elseif ($btype === 'spa_service')     $ref_label = 'Spa & Wellness Reference';
                            ?>
                            <div class="row mb-3">
                                <div class="col-12">
                                    <small class="text-muted"><?php echo $ref_label; ?></small>
                                    <div class="fw-bold"><?php echo htmlspecialchars($booking['booking_reference']); ?></div>
                                </div>
                            </div>

                            <?php if ($btype === 'room'): ?>
                            <!-- ══ ROOM BOOKING ══════════════════════════════ -->
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">Room Number</small>
                                    <div class="fw-bold"><?php echo $booking['room_number'] ?: 'N/A'; ?></div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Guests</small>
                                    <div><?php echo (int)$booking['customers']; ?> person(s)</div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">Check-in</small>
                                    <div><?php echo $booking['check_in_date'] ? date('M j, Y', strtotime($booking['check_in_date'])) : 'N/A'; ?></div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Check-out</small>
                                    <div><?php echo $booking['check_out_date'] ? date('M j, Y', strtotime($booking['check_out_date'])) : 'N/A'; ?></div>
                                </div>
                            </div>

                            <?php elseif ($btype === 'food_order'): ?>
                            <!-- ══ FOOD ORDER ════════════════════════════════ -->
                            <?php if ($food_order): ?>
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">Order Reference</small>
                                    <div class="fw-bold"><?php echo htmlspecialchars($food_order['order_reference']); ?></div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Guests</small>
                                    <div><?php echo (int)$food_order['guests']; ?> person(s)</div>
                                </div>
                            </div>
                            <?php if (!empty($food_order['items_detail'])): ?>
                            <div class="mb-3">
                                <small class="text-muted">Items Ordered</small>
                                <div><?php echo htmlspecialchars($food_order['items_detail']); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($food_order['table_reservation'] && $food_order['reservation_date']): ?>
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">Reservation Date</small>
                                    <div><?php echo date('M j, Y', strtotime($food_order['reservation_date'])); ?></div>
                                </div>
                                <?php if ($food_order['reservation_time']): ?>
                                <div class="col-6">
                                    <small class="text-muted">Reservation Time</small>
                                    <div><?php echo date('g:i A', strtotime($food_order['reservation_time'])); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <?php else: ?>
                            <div class="mb-3 text-muted"><small>Order details not available.</small></div>
                            <?php endif; ?>

                            <?php elseif ($btype === 'spa_service' || $btype === 'laundry_service'): ?>
                            <!-- ══ SPA / LAUNDRY SERVICE ══════════════════════ -->
                            <?php if ($svc_booking): ?>
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">Service</small>
                                    <div class="fw-bold"><?php echo htmlspecialchars($svc_booking['service_name']); ?></div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Category</small>
                                    <div><?php echo ucfirst($svc_booking['service_category']); ?></div>
                                </div>
                            </div>
                            <?php if ($svc_booking['service_date']): ?>
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">Service Date</small>
                                    <div><?php echo date('M j, Y', strtotime($svc_booking['service_date'])); ?></div>
                                </div>
                                <?php if ($svc_booking['service_time']): ?>
                                <div class="col-6">
                                    <small class="text-muted">Service Time</small>
                                    <div><?php echo date('g:i A', strtotime($svc_booking['service_time'])); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($svc_booking['quantity'] > 1): ?>
                            <div class="mb-3">
                                <small class="text-muted">Quantity</small>
                                <div><?php echo (int)$svc_booking['quantity']; ?></div>
                            </div>
                            <?php endif; ?>
                            <?php else: ?>
                            <div class="mb-3">
                                <small class="text-muted">Service Type</small>
                                <div><?php echo $btype === 'spa_service' ? 'Spa & Wellness' : 'Laundry'; ?></div>
                            </div>
                            <?php endif; ?>

                            <?php endif; // end booking type switch ?>

                            <!-- Total Amount (all types) -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <small class="text-muted">Total Amount</small>
                                    <div class="fw-bold text-success">ETB <?php echo number_format($booking['total_price'], 2); ?></div>
                                </div>
                            </div>

                            <!-- Pending Cancellation notice -->
                            <?php if ($booking['status'] === 'pending_cancellation' || $booking['status'] === 'Pending Cancellation'): ?>
                            <div class="alert alert-warning mb-3">
                                <i class="fas fa-clock me-2"></i>
                                <strong>Cancellation Requested</strong><br>
                                <small>Your cancellation request has been submitted and is waiting for manager approval.</small>
                            </div>
                            <?php endif; ?>

                            <!-- Refund info (cancelled bookings) -->
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
                                                if ($booking['refund_status'] === 'Pending')   $refund_badge_class = 'warning';
                                                elseif ($booking['refund_status'] === 'Processed') $refund_badge_class = 'success';
                                                elseif ($booking['refund_status'] === 'Rejected')  $refund_badge_class = 'danger';
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
                                        <div class="alert alert-success mt-2 mb-0">
                                            <i class="fas fa-check-circle me-2"></i>
                                            <strong>Refunded!</strong> Your refund of <strong>ETB <?php echo number_format($booking['refund_final_amount'], 2); ?></strong> has been processed.
                                        </div>
                                        <?php elseif ($booking['refund_status'] === 'Pending'): ?>
                                        <div class="alert alert-warning mt-2 mb-0">
                                            <i class="fas fa-clock me-2"></i>
                                            Your refund request is pending manager approval.
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Action buttons -->
                            <div class="d-flex gap-2 flex-wrap">
                                <!-- View Confirmation: shown for all booking types -->
                                <a href="payment-success.php?booking=<?php echo $booking['id']; ?>" class="btn btn-success btn-sm">
                                    <i class="fas fa-receipt"></i> View Confirmation
                                </a>
                                <button class="btn btn-outline-primary btn-sm" onclick="viewBookingDetails('<?php echo htmlspecialchars($booking['booking_reference']); ?>')">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                                <button class="btn btn-outline-secondary btn-sm" onclick="printBooking(<?php echo htmlspecialchars(json_encode($booking)); ?>)">
                                    <i class="fas fa-print"></i> Print
                                </button>
                                <?php if ($btype === 'room' && $can_cancel): ?>
                                <button class="btn btn-outline-danger btn-sm" onclick="cancelBooking('<?php echo htmlspecialchars($booking['booking_reference']); ?>')">
                                    <i class="fas fa-times-circle"></i> Cancel Booking
                                </button>
                                <?php elseif ($btype === 'room' && $cancellation_pending): ?>
                                <button class="btn btn-outline-warning btn-sm" disabled>
                                    <i class="fas fa-clock"></i> Cancellation Requested
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card-footer text-muted">
                            <small>
                                <i class="fas fa-clock"></i>
                                Booked on <?php echo date('M j, Y g:i A', strtotime($booking['created_at'])); ?>
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
    
    <!-- Cancellation Modal -->
    <div class="modal fade" id="cancelBookingModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white;">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Cancellation Policy
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="card border-0 bg-light mb-4">
                        <div class="card-body">
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Refund Schedule
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center p-3 bg-white rounded shadow-sm">
                                        <div class="flex-shrink-0">
                                            <div class="badge bg-success rounded-circle p-3" style="width:50px;height:50px;display:flex;align-items:center;justify-content:center;">
                                                <span class="fs-6 fw-bold">95%</span>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="fw-bold text-dark">7+ days before check-in</div>
                                            <small class="text-muted">95% refund minus 5% processing fee</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center p-3 bg-white rounded shadow-sm">
                                        <div class="flex-shrink-0">
                                            <div class="badge bg-info rounded-circle p-3" style="width:50px;height:50px;display:flex;align-items:center;justify-content:center;">
                                                <span class="fs-6 fw-bold">75%</span>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="fw-bold text-dark">3–6 days before check-in</div>
                                            <small class="text-muted">75% refund minus 5% processing fee</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center p-3 bg-white rounded shadow-sm">
                                        <div class="flex-shrink-0">
                                            <div class="badge bg-warning rounded-circle p-3" style="width:50px;height:50px;display:flex;align-items:center;justify-content:center;">
                                                <span class="fs-6 fw-bold">50%</span>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="fw-bold text-dark">1–2 days before check-in</div>
                                            <small class="text-muted">50% refund minus 5% processing fee</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center p-3 bg-white rounded shadow-sm">
                                        <div class="flex-shrink-0">
                                            <div class="badge bg-danger rounded-circle p-3" style="width:50px;height:50px;display:flex;align-items:center;justify-content:center;">
                                                <span class="fs-6 fw-bold">25%</span>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="fw-bold text-dark">Same day cancellation</div>
                                            <small class="text-muted">25% refund minus 5% processing fee</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex align-items-center p-3 bg-white rounded shadow-sm">
                                        <div class="flex-shrink-0">
                                            <div class="badge bg-dark rounded-circle p-3" style="width:50px;height:50px;display:flex;align-items:center;justify-content:center;">
                                                <span class="fs-6 fw-bold">0%</span>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="fw-bold text-dark">Past check-in date</div>
                                            <small class="text-muted">No refund available after check-in date has passed</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-warning mt-3 mb-0 py-2">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Important:</strong> All refunds are subject to a 5% processing fee deducted from the refund amount. Refunds processed within 5–7 business days after manager approval.
                            </div>
                        </div>
                    </div>
                    
                    <div id="refundCalculation" class="alert alert-info" style="display: none;">
                        <h6 class="alert-heading">Refund Calculation</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted">Total Amount:</small>
                                <div class="fw-bold" id="totalAmount">ETB 0.00</div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Refund Percentage:</small>
                                <div class="fw-bold" id="refundPercentage">0%</div>
                            </div>
                            <div class="col-md-6 mt-2">
                                <small class="text-muted">Refund Amount:</small>
                                <div class="fw-bold text-success" id="refundAmount">ETB 0.00</div>
                            </div>
                            <div class="col-md-6 mt-2">
                                <small class="text-muted">Processing Fee (5%):</small>
                                <div class="fw-bold text-danger" id="processingFee">ETB 0.00</div>
                            </div>
                            <div class="col-md-6 mt-2">
                                <small class="text-muted">Final Refund:</small>
                                <div class="fw-bold text-success fs-5" id="finalRefund">ETB 0.00</div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="cancelError" class="alert alert-danger" style="display: none;">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <span id="cancelErrorMessage"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" id="confirmCancelBtn" onclick="confirmCancellation()">
                        <i class="fas fa-check me-2"></i>Confirm Cancellation
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentBookingRef = '';
        let cancelModal = null;

        function cancelBooking(bookingRef) {
            currentBookingRef = bookingRef;
            document.getElementById('refundCalculation').style.display = 'none';
            document.getElementById('cancelError').style.display = 'none';

            const btn = document.getElementById('confirmCancelBtn');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check me-2"></i>Confirm Cancellation';

            // Calculate refund first
            fetch('api/cancel_booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ booking_reference: bookingRef, action: 'calculate' })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('totalAmount').textContent      = 'ETB ' + data.data.total_amount;
                    document.getElementById('refundPercentage').textContent = data.data.refund_percentage + '%';
                    document.getElementById('refundAmount').textContent     = 'ETB ' + data.data.refund_amount;
                    document.getElementById('processingFee').textContent    = 'ETB ' + data.data.processing_fee;
                    document.getElementById('finalRefund').textContent      = 'ETB ' + data.data.final_refund;
                    document.getElementById('refundCalculation').style.display = 'block';

                    cancelModal = new bootstrap.Modal(document.getElementById('cancelBookingModal'));
                    cancelModal.show();
                } else {
                    alert('Error: ' + (data.error || 'Unable to calculate refund'));
                }
            })
            .catch(() => {
                alert('Something went wrong. Please try again later.');
            });
        }

        function confirmCancellation() {
            const btn = document.getElementById('confirmCancelBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';

            document.getElementById('cancelError').style.display = 'none';

            fetch('api/cancel_booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ booking_reference: currentBookingRef, action: 'confirm' })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.text();
            })
            .then(text => {
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Response:', text);
                    throw new Error('Invalid server response. Please try again.');
                }

                if (data.success) {
                    // Close modal and show success message
                    if (cancelModal) cancelModal.hide();
                    btn.innerHTML = '<i class="fas fa-clock me-2"></i>Cancellation Requested';

                    // Show a friendly alert then reload
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-warning alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
                    alertDiv.style.zIndex = '9999';
                    alertDiv.style.minWidth = '400px';
                    alertDiv.innerHTML = `
                        <i class="fas fa-clock me-2"></i>
                        <strong>Waiting for manager approval</strong><br>
                        Your cancellation request has been submitted and is waiting for manager approval.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.body.appendChild(alertDiv);

                    setTimeout(() => location.reload(), 3000);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check me-2"></i>Confirm Cancellation';
                    document.getElementById('cancelErrorMessage').textContent = data.error || 'Cancellation failed. Please try again.';
                    document.getElementById('cancelError').style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check me-2"></i>Confirm Cancellation';
                document.getElementById('cancelErrorMessage').textContent = 'Something went wrong. Please try again later.';
                document.getElementById('cancelError').style.display = 'block';
            });
        }

        // ── helper functions ──────────────────────────────────────────────────
        function viewBookingDetails(ref) {
            window.location.href = 'booking-details.php?ref=' + encodeURIComponent(ref);
        }
        
        function printBooking(booking) {
            // Build type-specific detail rows
            let detailRows = '';
            const btype = booking.booking_type || 'room';

            if (btype === 'room') {
                detailRows = `
                    <tr><td>Room Number</td><td>${booking.room_number || 'N/A'}</td></tr>
                    <tr><td>Check-in</td><td>${booking.check_in_date ? formatDate(booking.check_in_date) : 'N/A'}</td></tr>
                    <tr><td>Check-out</td><td>${booking.check_out_date ? formatDate(booking.check_out_date) : 'N/A'}</td></tr>
                    <tr><td>Guests</td><td>${booking.customers || 1} person(s)</td></tr>`;
            } else if (btype === 'food_order') {
                detailRows = `
                    <tr><td>Order Type</td><td>Food Order</td></tr>
                    <tr><td>Order Date</td><td>${formatDate(booking.created_at)}</td></tr>`;
            } else if (btype === 'spa_service') {
                detailRows = `
                    <tr><td>Service Type</td><td>Spa &amp; Wellness</td></tr>
                    <tr><td>Date</td><td>${formatDate(booking.created_at)}</td></tr>`;
            } else if (btype === 'laundry_service') {
                detailRows = `
                    <tr><td>Service Type</td><td>Laundry Service</td></tr>
                    <tr><td>Date</td><td>${formatDate(booking.created_at)}</td></tr>`;
            }

            const statusColors = {confirmed:'#28a745', pending:'#ffc107', cancelled:'#dc3545', verified:'#28a745'};
            const statusColor = statusColors[booking.status] || '#6c757d';
            const statusText = (booking.status || '').replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase());
            const typeLabel = {room:'Room Booking', food_order:'Food Order', spa_service:'Spa Service', laundry_service:'Laundry Service'}[btype] || 'Booking';

            const receiptHtml = `<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Booking Receipt - ${booking.booking_reference}</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: Arial, sans-serif; font-size: 11pt; color: #222; background: #fff; padding: 20px; }
  .receipt { max-width: 480px; margin: 0 auto; border: 1px solid #ccc; border-radius: 6px; overflow: hidden; }
  .receipt-header { background: #1a1a2e; color: #fff; padding: 14px 18px; text-align: center; }
  .receipt-header .hotel-name { font-size: 16pt; font-weight: bold; letter-spacing: 1px; }
  .receipt-header .hotel-sub { font-size: 9pt; color: #ccc; margin-top: 2px; }
  .receipt-title { background: #f4f4f4; border-bottom: 1px solid #ddd; padding: 8px 18px; display: flex; justify-content: space-between; align-items: center; }
  .receipt-title .type { font-size: 12pt; font-weight: bold; }
  .receipt-title .status-badge { font-size: 9pt; font-weight: bold; color: #fff; background: ${statusColor}; padding: 3px 10px; border-radius: 12px; }
  .receipt-body { padding: 14px 18px; }
  table { width: 100%; border-collapse: collapse; }
  td { padding: 5px 4px; font-size: 10pt; vertical-align: top; border-bottom: 1px solid #f0f0f0; }
  td:first-child { color: #666; width: 42%; font-size: 9.5pt; }
  td:last-child { font-weight: 600; }
  .divider { border: none; border-top: 1px dashed #bbb; margin: 10px 0; }
  .total-row td { font-size: 12pt; font-weight: bold; color: #1a7a3c; border-bottom: none; padding-top: 8px; }
  .receipt-footer { background: #f9f9f9; border-top: 1px solid #ddd; padding: 10px 18px; text-align: center; font-size: 8.5pt; color: #888; }
  @media print {
    body { padding: 0; }
    .receipt { border: none; max-width: 100%; }
    .no-print { display: none !important; }
  }
</style>
</head>
<body>
<div class="receipt">
  <div class="receipt-header">
    <div class="hotel-name">&#127963; Harar Ras Hotel</div>
    <div class="hotel-sub">Booking Receipt</div>
  </div>
  <div class="receipt-title">
    <span class="type">${typeLabel}</span>
    <span class="status-badge">${statusText}</span>
  </div>
  <div class="receipt-body">
    <table>
      <tr><td>Reference</td><td>${booking.booking_reference}</td></tr>
      ${detailRows}
      <tr><td>Payment</td><td>${(booking.payment_status||'pending').replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase())}</td></tr>
      <tr><td>Booked On</td><td>${formatDate(booking.created_at)}</td></tr>
    </table>
    <hr class="divider">
    <table>
      <tr class="total-row"><td>Total Amount</td><td>ETB ${parseFloat(booking.total_price||0).toFixed(2)}</td></tr>
    </table>
  </div>
  <div class="receipt-footer">
    Thank you for choosing Harar Ras Hotel &bull; harar-ras-hotel-booking.onrender.com
  </div>
</div>
<div class="no-print" style="text-align:center;margin-top:16px;">
  <button onclick="window.print()" style="padding:8px 24px;background:#1a1a2e;color:#fff;border:none;border-radius:4px;font-size:11pt;cursor:pointer;">&#128424; Print</button>
  <button onclick="window.close()" style="padding:8px 18px;background:#eee;color:#333;border:1px solid #ccc;border-radius:4px;font-size:11pt;cursor:pointer;margin-left:8px;">Close</button>
</div>
<script>
  // Auto-trigger print after a short delay so styles load
  setTimeout(function(){ window.print(); }, 400);
<\/script>
</body>
</html>`;

            const w = window.open('', '_blank', 'width=560,height=700,scrollbars=yes');
            w.document.write(receiptHtml);
            w.document.close();
        }

        function formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            const d = new Date(dateStr);
            if (isNaN(d)) return dateStr;
            return d.toLocaleDateString('en-US', {year:'numeric', month:'short', day:'numeric'});
        }
    </script>
</body>
</html>
