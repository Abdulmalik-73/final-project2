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

// Get user's bookings
$user_id = $_SESSION['user_id'];
$query = "SELECT b.*, 
          COALESCE(r.name, 'Room') as room_name,
          COALESCE(r.room_number, 'N/A') as room_number, 
          COALESCE(r.image, '') as room_image,
          ref.refund_status,
          ref.final_refund as refund_final_amount,
          ref.refund_reference,
          ref.processed_date as refund_processed_date
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
                <?php foreach ($bookings as $booking): ?>
                <div class="col-lg-6 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-bed text-gold"></i>
                                <?php echo htmlspecialchars($booking['room_name']); ?>
                            </h5>
                            <?php
                            $status = $booking['status'];
                            $status_class = 'secondary';
                            $status_text = ucfirst(str_replace('_', ' ', $status));
                            
                            if ($status === 'confirmed' || $status === 'verified') {
                                $status_class = 'success';
                            } elseif ($status === 'pending') {
                                $status_class = 'warning';
                            } elseif ($status === 'cancelled') {
                                $status_class = 'danger';
                            }
                            ?>
                            <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">Booking Reference</small>
                                    <div class="fw-bold"><?php echo $booking['booking_reference']; ?></div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Room Number</small>
                                    <div class="fw-bold"><?php echo $booking['room_number']; ?></div>
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
                            
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">Guests</small>
                                    <div><?php echo $booking['customers']; ?> person(s)</div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Total Amount</small>
                                    <div class="fw-bold text-success">ETB <?php echo number_format($booking['total_price'], 2); ?></div>
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
                            
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn btn-outline-primary btn-sm" onclick="viewBookingDetails('<?php echo $booking['booking_reference']; ?>')">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                                
                                <button class="btn btn-outline-secondary btn-sm" onclick="printBooking(<?php echo htmlspecialchars(json_encode($booking)); ?>)">
                                    <i class="fas fa-print"></i> Print
                                </button>
                                
                                <?php 
                                // Check if booking can be cancelled
                                $can_cancel = false;
                                $cancellable_statuses = ['pending', 'confirmed', 'verified'];
                                $booking_status = $booking['status'];
                                
                                if (in_array($booking_status, $cancellable_statuses) && !empty($booking['check_in_date'])) {
                                    $check_in_date = new DateTime($booking['check_in_date']);
                                    $current_date = new DateTime();
                                    $current_date->setTime(0, 0, 0);
                                    if ($current_date <= $check_in_date) {
                                        $can_cancel = true;
                                    }
                                }
                                
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
                                            <small class="text-muted">75% refund minus 5% fee</small>
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
                                            <small class="text-muted">50% refund minus 5% fee</small>
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
                                            <small class="text-muted">25% refund minus 5% fee</small>
                                        </div>
                                    </div>
                                </div>
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
        
        function cancelBooking(bookingRef) {
            currentBookingRef = bookingRef;
            document.getElementById('refundCalculation').style.display = 'none';
            document.getElementById('cancelError').style.display = 'none';
            
            // Calculate refund
            fetch('api/cancel_booking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    booking_reference: bookingRef,
                    action: 'calculate'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('totalAmount').textContent = 'ETB ' + data.data.total_amount;
                    document.getElementById('refundPercentage').textContent = data.data.refund_percentage + '%';
                    document.getElementById('refundAmount').textContent = 'ETB ' + data.data.refund_amount;
                    document.getElementById('processingFee').textContent = 'ETB ' + data.data.processing_fee;
                    document.getElementById('finalRefund').textContent = 'ETB ' + data.data.final_refund;
                    document.getElementById('refundCalculation').style.display = 'block';
                    
                    const modal = new bootstrap.Modal(document.getElementById('cancelBookingModal'));
                    modal.show();
                } else {
                    alert('Error: ' + (data.error || 'Unable to calculate refund'));
                }
            })
            .catch(error => {
                alert('Network error. Please try again.');
            });
        }
        
        function confirmCancellation() {
            const btn = document.getElementById('confirmCancelBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            
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
                    alert('Booking cancelled successfully! Your refund will be processed within 5-7 business days.');
                    location.reload();
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check me-2"></i>Confirm Cancellation';
                    document.getElementById('cancelErrorMessage').textContent = data.error || 'Cancellation failed';
                    document.getElementById('cancelError').style.display = 'block';
                }
            })
            .catch(error => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check me-2"></i>Confirm Cancellation';
                document.getElementById('cancelErrorMessage').textContent = 'Network error. Please try again.';
                document.getElementById('cancelError').style.display = 'block';
            });
        }
        
        function viewBookingDetails(ref) {
            alert('Booking Reference: ' + ref);
        }
        
        function printBooking(booking) {
            window.print();
        }
    </script>
</body>
</html>
