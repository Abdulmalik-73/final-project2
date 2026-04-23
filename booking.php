<?php session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/RoomLockManager.php';

// Add cache-busting headers to prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Initialize Room Lock Manager
$lockManager = new RoomLockManager($conn);

// Clear error session if user is starting fresh (no POST and no error parameter)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['error'])) {
    // Only clear if user clicked "Choose Another Room" or navigated normally
    if (isset($_GET['clear_error'])) {
        unset($_SESSION['room_not_available_error']);
        unset($_SESSION['duplicate_booking_error']);
        unset($_SESSION['max_booking_error']);
    }
}

$selected_room_id = isset($_GET['room']) ? (int)$_GET['room'] : (isset($_GET['room_id']) ? (int)$_GET['room_id'] : null);
$selected_room = null;

if ($selected_room_id) {
    $selected_room = get_room_by_id($selected_room_id);
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Debug: Log form submission
    error_log("Booking form submitted by user: " . ($_SESSION['user_id'] ?? 'not logged in'));
    error_log("Session user_id type: " . gettype($_SESSION['user_id'] ?? null));
    error_log("Session user_id value: " . var_export($_SESSION['user_id'] ?? null, true));
    error_log("POST data: " . print_r($_POST, true));
    
    if (!is_logged_in()) {
        error_log("User not logged in, storing booking data and redirecting");
        $_SESSION['booking_data'] = $_POST;
        header('Location: login.php');
        exit();
    }
    
    $room_id = (int)$_POST['room_id'];
    $check_in = sanitize_input($_POST['check_in']);
    $check_out = sanitize_input($_POST['check_out']);
    $customers = (int)$_POST['customers'];
    $special_requests = ''; // Removed - replaced by ID upload
    $id_image = sanitize_input($_POST['id_image_path'] ?? '');

    // Validate ID image was uploaded
    if (empty($id_image)) {
        $error = 'Please upload your ID before confirming booking.';
        goto skip_booking;
    }

    // Security: ensure path is within uploads/ids/ and is an image
    if (!preg_match('/^uploads\/ids\/id_\d+_\d+_[a-zA-Z0-9._]+\.(jpg|jpeg|png)$/i', $id_image)) {
        $error = 'Invalid ID image path. Please re-upload your ID.';
        goto skip_booking;
    }

    $room = get_room_by_id($room_id);
    
    if (!$room) {
        $error = 'Invalid room selected';
    } else {
        // Create booking directly - overlap check is done in create_booking()
        $nights = calculate_nights($check_in, $check_out);
        $total_price = $room['price'] * $nights;
        
        $booking_data = [
            'user_id' => $_SESSION['user_id'],
            'room_id' => $room_id,
            'check_in' => $check_in,
            'check_out' => $check_out,
            'customers' => $customers,
            'total_price' => $total_price,
            'special_requests' => $special_requests,
            'id_image' => $id_image,
        ];
        
        error_log("Booking data being passed to create_booking: " . print_r($booking_data, true));
        
        $result = create_booking($booking_data);
        
        if ($result['success']) {
            // Debug: Log successful booking creation
            error_log("Booking created successfully with ID: " . $result['booking_id']);
                
                // Save ID image path to booking
                if (!empty($id_image)) {
                    $id_upd = $conn->prepare("UPDATE bookings SET id_image = ? WHERE id = ?");
                    if ($id_upd) {
                        $id_upd->bind_param("si", $id_image, $result['booking_id']);
                        $id_upd->execute();
                    }
                    // Clear session pending ID
                    unset($_SESSION['pending_id_image']);
                }
                
                // Generate payment reference and set deadline
                $payment_ref = 'HRH-' . str_pad($result['booking_id'], 4, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(md5($result['booking_id'] . time()), 0, 6));
                $deadline = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                
                // Update booking with payment verification fields
                $update_query = "UPDATE bookings SET 
                                payment_reference = ?, 
                                payment_deadline = ?, 
                                verification_status = 'pending_payment' 
                                WHERE id = ?";
                
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("ssi", $payment_ref, $deadline, $result['booking_id']);
                
                if ($update_stmt->execute()) {
                    error_log("Payment reference updated successfully: " . $payment_ref);
                } else {
                    error_log("Failed to update payment reference: " . $update_stmt->error);
                }
                
                // Log user activity for booking
                log_user_activity($_SESSION['user_id'], 'booking', 'Room booking created: ' . $result['booking_reference'] . ' - Room ID: ' . $room_id, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
                
                // Log booking activity
                log_booking_activity($result['booking_id'], $_SESSION['user_id'], 'created', '', 'pending', 'Booking created by customer - awaiting payment', $_SESSION['user_id']);
                
                // Store booking reference in session for payment
                $_SESSION['pending_booking'] = $result['booking_reference'];
                $_SESSION['current_booking_id'] = $result['booking_id'];
                set_message('success', 'Booking created successfully! Please submit your transaction ID to confirm your reservation.');
                
                // Debug: Log redirect
                error_log("Redirecting to: payment-upload.php?booking=" . $result['booking_id']);
                
                // Redirect to payment upload page
                header('Location: payment-upload.php?booking=' . $result['booking_id']);
                exit();
            } else {
                // Booking failed - show error message
                
                // Check if this is a max booking limit error
                if (isset($result['error_code']) && $result['error_code'] === 'MAX_BOOKING_LIMIT' && isset($result['existing_bookings'])) {
                    $_SESSION['max_booking_error'] = [
                        'existing_bookings' => $result['existing_bookings'],
                        'booking_count' => $result['booking_count'],
                        'check_in_date' => $result['check_in_date'] ?? date('F j, Y', strtotime($check_in))
                    ];
                    $_SESSION['max_booking_error_time'] = time(); // Store timestamp
                    $error = 'MAX_BOOKING_LIMIT'; // Flag for display
                }
                // Check if this is a room not available error
                elseif (isset($result['error_code']) && $result['error_code'] === 'ROOM_NOT_AVAILABLE' && isset($result['blocking_booking'])) {
                    $blocking = $result['blocking_booking'];
                    $_SESSION['room_not_available_error'] = $blocking;
                    $error = 'ROOM_WAITING_STATE'; // Flag for display
                } 
                // Check if this is a duplicate booking error (legacy)
                elseif (isset($result['error_code']) && $result['error_code'] === 'DUPLICATE_BOOKING' && isset($result['existing_booking'])) {
                    $existing = $result['existing_booking'];
                    $_SESSION['duplicate_booking_error'] = $existing;
                    $error = 'OVERLAPPING_DATES'; // Flag for display
                } else {
                    $error = 'Booking failed. Please try again. Error: ' . $result['message'];
                }
            }
    }
    skip_booking: // goto target for early exit on validation failure
}

$rooms = get_all_rooms();

// Add timestamp for debugging
$page_load_time = date('Y-m-d H:i:s');
error_log("Booking page loaded at $page_load_time with " . count($rooms) . " rooms");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Now - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Top Guidance Banner for Non-Authenticated Users -->
    <?php if (!is_logged_in()): ?>
    <div class="alert alert-warning alert-dismissible fade show m-0 border-0 rounded-0" role="alert">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="alert-heading mb-2">
                        <i class="fas fa-exclamation-triangle"></i> Account Required to Book
                    </h5>
                    <p class="mb-0">
                        <strong>To proceed with booking, you must first create an account or sign in.</strong>
                        This ensures secure booking and allows you to manage your reservations.
                    </p>
                </div>
                <div class="col-md-4 text-md-end mt-2 mt-md-0">
                    <a href="register.php?redirect=booking" class="btn btn-success btn-sm me-2">
                        <i class="fas fa-user-plus"></i> Create Account
                    </a>
                    <a href="login.php?redirect=booking" class="btn btn-primary btn-sm">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Clear Page Identifier for Logged-in Users -->
    <div class="alert alert-info border-info m-0 border-0 rounded-0">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="alert-heading mb-2">
                        <i class="fas fa-bed"></i> Room Booking
                    </h5>
                    <p class="mb-0">
                        <strong><?php echo __('booking_auth.room_booking_page'); ?></strong> <?php echo __('booking_auth.select_room_dates'); ?>
                        <br><small>Looking to order food? <a href="food-booking.php" class="alert-link"><?php echo __('booking_auth.food_link'); ?></a></small>
                    </p>
                </div>
                <div class="col-md-4 text-md-end mt-2 mt-md-0">
                    <i class="fas fa-bed fa-3x text-info"></i>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <section class="py-5">
        <div class="container">
            <div class="row mb-4">
                <div class="col">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> <?php echo __('booking_auth.back_to_home'); ?>
                    </a>
                </div>
            </div>
            
            <?php if (!is_logged_in()): ?>
            <!-- Step-by-Step Guidance Section -->
            <div class="row justify-content-center mb-5">
                <div class="col-lg-10">
                    <div class="card border-danger shadow-lg">
                        <div class="card-header bg-danger text-white text-center">
                            <h3 class="mb-0">
                                <i class="fas fa-shield-alt"></i> <?php echo __('booking_auth.auth_required'); ?>
                            </h3>
                            <p class="mb-0 mt-2">Follow these simple steps to complete your booking</p>
                        </div>
                        <div class="card-body p-4">
                            <!-- Step-by-Step Instructions -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="text-danger mb-3">
                                        <i class="fas fa-list-ol"></i> <?php echo __('booking_auth.how_to_book'); ?>
                                    </h5>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <div class="text-center p-3 bg-light rounded">
                                                <div class="display-4 text-danger mb-2">1</div>
                                                <h6 class="text-danger"><?php echo __('booking_auth.step1_title'); ?></h6>
                                                <p class="small text-muted mb-0">Choose one of the options below to authenticate</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <div class="text-center p-3 bg-light rounded">
                                                <div class="display-4 text-warning mb-2">2</div>
                                                <h6 class="text-warning"><?php echo __('booking_auth.step2_title'); ?></h6>
                                                <p class="small text-muted mb-0">You'll be automatically redirected back here</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <div class="text-center p-3 bg-light rounded">
                                                <div class="display-4 text-success mb-2">3</div>
                                                <h6 class="text-success"><?php echo __('booking_auth.step3_title'); ?></h6>
                                                <p class="small text-muted mb-0">Fill out the form and confirm your reservation</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Authentication Options -->
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <div class="card bg-success text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-user-plus fa-3x mb-3"></i>
                                            <h5><?php echo __('booking_auth.new_customer'); ?></h5>
                                            <p class="mb-3">Create a free account in just 2 minutes</p>
                                            <ul class="list-unstyled text-start mb-3">
                                                <li><i class="fas fa-check me-2"></i> Secure booking process</li>
                                                <li><i class="fas fa-check me-2"></i> Track your reservations</li>
                                                <li><i class="fas fa-check me-2"></i> Special member offers</li>
                                                <li><i class="fas fa-check me-2"></i> Booking history</li>
                                            </ul>
                                            <a href="register.php<?php echo $selected_room_id ? '?redirect=booking&room=' . $selected_room_id : '?redirect=booking'; ?>" 
                                               class="btn btn-light btn-lg">
                                                <i class="fas fa-user-plus"></i> <?php echo __('booking_auth.create_account_now'); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="card bg-primary text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-sign-in-alt fa-3x mb-3"></i>
                                            <h5><?php echo __('booking_auth.existing_customer'); ?></h5>
                                            <p class="mb-3">Sign in to your account to continue</p>
                                            <ul class="list-unstyled text-start mb-3">
                                                <li><i class="fas fa-check me-2"></i> Access your profile</li>
                                                <li><i class="fas fa-check me-2"></i> View booking history</li>
                                                <li><i class="fas fa-check me-2"></i> Manage reservations</li>
                                                <li><i class="fas fa-check me-2"></i> Quick checkout</li>
                                            </ul>
                                            <a href="login.php<?php echo $selected_room_id ? '?redirect=booking&room=' . $selected_room_id : '?redirect=booking'; ?>" 
                                               class="btn btn-light btn-lg">
                                                <i class="fas fa-sign-in-alt"></i> <?php echo __('booking_auth.sign_in_now'); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Why Account Required -->
                            <div class="alert alert-info mt-4">
                                <h6 class="alert-heading">
                                    <i class="fas fa-info-circle"></i> <?php echo __('booking_auth.why_account'); ?>
                                </h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <ul class="mb-0">
                                            <li>Secure payment processing</li>
                                            <li>Booking confirmation emails</li>
                                            <li>Ability to modify/cancel reservations</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <ul class="mb-0">
                                            <li>Customer support assistance</li>
                                            <li>Loyalty program benefits</li>
                                            <li>Personalized service preferences</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow-sm <?php echo !is_logged_in() ? 'opacity-25' : ''; ?>">
                        <div class="card-header text-white" style="background: <?php echo !is_logged_in() ? '#6c757d' : 'linear-gradient(135deg, #1e88e5 0%, #1565c0 100%)'; ?>; padding: 1.5rem;">
                            <h3 class="mb-0 fw-bold" style="font-size: 1.75rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);">
                                <i class="fas fa-calendar-check me-2"></i> <?php echo __('booking.title'); ?>
                                <?php if (!is_logged_in()): ?>
                                <span class="badge bg-danger ms-2" style="font-size: 0.9rem;">
                                    <i class="fas fa-lock"></i> LOCKED - Authentication Required
                                </span>
                                <?php endif; ?>
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if ($error === 'MAX_BOOKING_LIMIT' && isset($_SESSION['max_booking_error'])): ?>
                                <?php 
                                $error_data = $_SESSION['max_booking_error']; 
                                $error_timestamp = $_SESSION['max_booking_error_time'] ?? time();
                                $time_elapsed = time() - $error_timestamp;
                                $min_display_time = 180; // 3 minutes in seconds
                                ?>
                                <div class="alert alert-danger" role="alert" id="maxBookingError" 
                                     style="position: sticky; top: 20px; z-index: 1000; animation: none !important;"
                                     data-timestamp="<?php echo $error_timestamp; ?>"
                                     data-min-time="<?php echo $min_display_time; ?>">
                                    <h5 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Maximum Booking Limit Reached for This Date</h5>
                                    <p class="mb-2"><strong>You have reached the maximum booking limit for <?php echo $error_data['check_in_date'] ?? 'this date'; ?>!</strong></p>
                                    <p class="mb-3">You can have up to <strong>3 bookings per day</strong> (same check-in date). You currently have <strong><?php echo $error_data['booking_count']; ?> bookings</strong> for this date.</p>
                                    <hr>
                                    <h6 class="mb-3">Your Existing Bookings for <?php echo $error_data['check_in_date'] ?? 'This Date'; ?>:</h6>
                                    <div class="row">
                                        <?php foreach ($error_data['existing_bookings'] as $index => $booking): ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="card border-warning">
                                                <div class="card-body">
                                                    <h6 class="card-title">Booking #<?php echo $index + 1; ?></h6>
                                                    <ul class="list-unstyled small mb-0">
                                                        <li><strong>Room:</strong> <?php echo htmlspecialchars($booking['room_name']); ?></li>
                                                        <li><strong>Room #:</strong> <?php echo htmlspecialchars($booking['room_number']); ?></li>
                                                        <li><strong>Check-in:</strong> <?php echo htmlspecialchars($booking['check_in_date']); ?></li>
                                                        <li><strong>Check-out:</strong> <?php echo htmlspecialchars($booking['check_out_date']); ?></li>
                                                        <li><strong>Reference:</strong> <?php echo htmlspecialchars($booking['reference']); ?></li>
                                                        <li><strong>Status:</strong> <span class="badge bg-warning"><?php echo htmlspecialchars($booking['status']); ?></span></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <hr>
                                    <div class="alert alert-info mb-3">
                                        <i class="fas fa-info-circle"></i> <strong>To make a new booking for this date:</strong>
                                        <ul class="mb-0 mt-2">
                                            <li>Cancel one of your existing bookings for this date, or</li>
                                            <li>Choose a different check-in date</li>
                                        </ul>
                                        <p class="mb-0 mt-2"><strong>Note:</strong> You can book up to 3 rooms per day. You can book different dates without any limit.</p>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center">
                                        <a href="my-bookings.php" class="btn btn-primary btn-sm">
                                            <i class="fas fa-list"></i> View My Bookings
                                        </a>
                                        <button type="button" class="btn btn-secondary btn-sm" id="dismissErrorBtn" disabled>
                                            <i class="fas fa-times"></i> Dismiss (<span id="countdown"><?php echo max(0, $min_display_time - $time_elapsed); ?></span>s)
                                        </button>
                                    </div>
                                </div>
                                <script>
                                    // Countdown timer for dismiss button
                                    (function() {
                                        const errorDiv = document.getElementById('maxBookingError');
                                        const dismissBtn = document.getElementById('dismissErrorBtn');
                                        const countdownSpan = document.getElementById('countdown');
                                        
                                        const timestamp = parseInt(errorDiv.dataset.timestamp);
                                        const minTime = parseInt(errorDiv.dataset.minTime);
                                        const currentTime = Math.floor(Date.now() / 1000);
                                        let timeElapsed = currentTime - timestamp;
                                        let remainingTime = Math.max(0, minTime - timeElapsed);
                                        
                                        // Update countdown every second
                                        const interval = setInterval(function() {
                                            remainingTime--;
                                            countdownSpan.textContent = remainingTime;
                                            
                                            if (remainingTime <= 0) {
                                                clearInterval(interval);
                                                dismissBtn.disabled = false;
                                                dismissBtn.innerHTML = '<i class="fas fa-times"></i> Dismiss';
                                                dismissBtn.onclick = function() {
                                                    // Clear session error via AJAX
                                                    fetch('api/clear_booking_error.php', {
                                                        method: 'POST'
                                                    }).then(() => {
                                                        errorDiv.style.display = 'none';
                                                    });
                                                };
                                            }
                                        }, 1000);
                                        
                                        // Prevent page refresh from resetting timer
                                        window.addEventListener('beforeunload', function() {
                                            // Timer continues server-side
                                        });
                                    })();
                                </script>
                            <?php elseif ($error === 'ROOM_WAITING_STATE' && isset($_SESSION['room_not_available_error'])): ?>
                                <?php $blocking = $_SESSION['room_not_available_error']; ?>
                                <div class="alert alert-warning" role="alert" id="roomWaitingAlert" style="position: sticky; top: 20px; z-index: 1000; animation: none !important;">
                                    <h5 class="alert-heading"><i class="fas fa-clock"></i> Room Under Waiting State</h5>
                                    <p class="mb-2"><strong>The Room is Under Waiting State, please book another Room!</strong></p>
                                    <p class="mb-3">This room has a pending booking that is awaiting receptionist approval.</p>
                                    <hr>
                                    <h6 class="mb-3">Blocking Booking Details:</h6>
                                    <ul class="mb-3">
                                        <li><strong>Room:</strong> <?php echo htmlspecialchars($blocking['room_name']); ?> (Room <?php echo htmlspecialchars($blocking['room_number']); ?>)</li>
                                        <li><strong>Check-in:</strong> <?php echo htmlspecialchars($blocking['check_in']); ?></li>
                                        <li><strong>Check-out:</strong> <?php echo htmlspecialchars($blocking['check_out']); ?></li>
                                        <li><strong>Status:</strong> <span class="badge bg-warning"><?php echo htmlspecialchars($blocking['status']); ?></span></li>
                                    </ul>
                                    <div class="d-flex gap-2">
                                        <a href="booking.php?clear_error=1" class="btn btn-primary btn-sm">
                                            <i class="fas fa-search"></i> Choose Another Room
                                        </a>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('roomWaitingAlert').style.display='none';">
                                            <i class="fas fa-times"></i> Dismiss
                                        </button>
                                    </div>
                                </div>
                            <?php elseif ($error === 'OVERLAPPING_DATES' && isset($_SESSION['duplicate_booking_error'])): ?>
                                <?php $existing = $_SESSION['duplicate_booking_error']; ?>
                                <div class="alert alert-danger" role="alert" style="position: sticky; top: 20px; z-index: 1000; animation: none !important;">
                                    <h5 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Booking Not Allowed</h5>
                                    <p class="mb-2"><strong>You already have an active booking for overlapping dates.</strong></p>
                                    <p class="mb-3">Please choose different dates or cancel your existing booking.</p>
                                    <hr>
                                    <h6 class="mb-3">Your Existing Booking:</h6>
                                    <ul class="mb-3">
                                        <li><strong>Room:</strong> <?php echo htmlspecialchars($existing['room_name']); ?> (Room <?php echo htmlspecialchars($existing['room_number']); ?>)</li>
                                        <li><strong>Check-in Date:</strong> <?php echo htmlspecialchars($existing['check_in_date']); ?></li>
                                        <li><strong>Booking Reference:</strong> <?php echo htmlspecialchars($existing['reference']); ?></li>
                                        <li><strong>Status:</strong> <span class="badge bg-warning"><?php echo htmlspecialchars($existing['status']); ?></span></li>
                                    </ul>
                                    <div class="d-flex gap-2">
                                        <a href="my-bookings.php" class="btn btn-primary btn-sm">
                                            <i class="fas fa-list"></i> View My Bookings
                                        </a>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="this.parentElement.parentElement.style.display='none'">
                                            <i class="fas fa-times"></i> Dismiss
                                        </button>
                                    </div>
                                </div>
                            <?php elseif ($error): ?>
                                <div class="alert alert-danger" role="alert" style="animation: none !important;">
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!is_logged_in()): ?>
                            <div class="alert alert-danger">
                                <h5 class="alert-heading">
                                    <i class="fas fa-exclamation-triangle"></i> Booking Form Disabled
                                </h5>
                                <p class="mb-3">
                                    <strong>This booking form is currently disabled because you are not signed in.</strong>
                                </p>
                                <p class="mb-3">
                                    To enable this form and proceed with your booking, you must:
                                </p>
                                <ol class="mb-3">
                                    <li><strong>Create a new account</strong> (recommended for new customers)</li>
                                    <li><strong>Sign in to your existing account</strong> (if you already have one)</li>
                                </ol>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="register.php?redirect=booking" class="btn btn-success">
                                        <i class="fas fa-user-plus"></i> Create Account First
                                    </a>
                                    <a href="login.php?redirect=booking" class="btn btn-primary">
                                        <i class="fas fa-sign-in-alt"></i> Sign In First
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="" id="bookingForm" <?php echo !is_logged_in() ? 'style="pointer-events: none;"' : ''; ?>>
                                <div class="mb-4">
                                    <label class="form-label fw-bold"><?php echo __('booking.select_room'); ?> *</label>
                                    <select name="room_id" id="roomSelect" class="form-select" required style="font-size: 14px;" data-timestamp="<?php echo time(); ?>">
                                        <option value=""><?php echo __('booking.select_room'); ?>...</option>
                                        
                                        <?php
                                        // Get all rooms from database and group by type
                                        // Force fresh data by clearing any potential caching
                                        $all_rooms = get_all_rooms();
                                        
                                        // Debug: Show total rooms found
                                        if (empty($all_rooms)) {
                                            echo '<option disabled>No active rooms found in database</option>';
                                        } else {
                                            $rooms_by_type = [];
                                            
                                            foreach ($all_rooms as $room) {
                                                $rooms_by_type[$room['name']][] = $room;
                                            }
                                            
                                            // Display rooms grouped by type
                                            foreach ($rooms_by_type as $room_type_name => $rooms_in_type):
                                                $first_room = $rooms_in_type[0];
                                                $price_formatted = number_format($first_room['price'], 2);
                                            ?>
                                            <optgroup label="<?php echo htmlspecialchars($room_type_name); ?> - ETB <?php echo $price_formatted; ?><?php echo __('booking_auth.per_night'); ?>">
                                                <?php foreach ($rooms_in_type as $room): ?>
                                                <option value="<?php echo $room['id']; ?>" 
                                                        data-price="<?php echo $room['price']; ?>" 
                                                        data-capacity="<?php echo $room['capacity']; ?>">
                                                    <?php echo htmlspecialchars($room['name']); ?> Number <?php echo $room['room_number']; ?> - ETB <?php echo number_format($room['price'], 2); ?><?php echo __('booking_auth.per_night'); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                            <?php 
                                            endforeach;
                                        }
                                        ?>
                                    </select>
                                    <!-- Debug info for admin users -->
                                    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                                    <small class="text-muted">
                                        Found <?php echo count($all_rooms); ?> active rooms in database. 
                                        Page loaded: <?php echo $page_load_time; ?>
                                        <?php 
                                        if (!empty($all_rooms)) {
                                            $latest_room = end($all_rooms);
                                            echo " | Latest room: " . $latest_room['name'] . " (#" . $latest_room['room_number'] . ")";
                                        }
                                        ?>
                                    </small>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold"><?php echo __('booking.check_in'); ?> *</label>
                                        <input type="date" name="check_in" id="checkIn" class="form-control" 
                                               min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold"><?php echo __('booking.check_out'); ?> *</label>
                                        <input type="date" name="check_out" id="checkOut" class="form-control" 
                                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold"><?php echo __('booking.guests'); ?> *</label>
                                    <input type="number" name="customers" id="customers" class="form-control" 
                                           min="1" max="10" value="1" required>
                                </div>
                                
                                <!-- ID Upload Section (replaces Special Requests) -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-id-card text-primary me-1"></i>
                                        Upload National ID / Passport / Driving License <span class="text-danger">*</span>
                                    </label>
                                    <div id="idUploadBox" class="border rounded p-3" style="background:#f8f9fa; border-style:dashed !important; border-color:#1e88e5 !important;">
                                        <!-- Upload controls -->
                                        <div id="idUploadControls">
                                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                                <label for="idFileInput" class="btn btn-outline-primary btn-sm mb-0" id="uploadIdBtn">
                                                    <i class="fas fa-upload me-1"></i> Upload ID
                                                </label>
                                                <input type="file" id="idFileInput" accept=".jpg,.jpeg,.png" class="d-none">
                                                <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="scanIdBtn">
                                                    <i class="fas fa-camera me-1"></i> Scan ID (Use Camera)
                                                </button>
                                                <small class="text-muted">JPG, JPEG, PNG only &bull; Max 2MB</small>
                                            </div>
                                            <div id="idUploadProgress" class="mt-2 d-none">
                                                <div class="progress" style="height:6px;">
                                                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width:100%"></div>
                                                </div>
                                                <small class="text-muted">Uploading...</small>
                                            </div>
                                        </div>

                                        <!-- Preview area (hidden until upload) -->
                                        <div id="idPreviewArea" class="mt-3 d-none">
                                            <div class="d-flex align-items-start gap-3">
                                                <img id="idPreviewImg" src="" alt="ID Preview"
                                                     class="rounded border"
                                                     style="width:120px; height:80px; object-fit:cover; cursor:pointer;"
                                                     onclick="document.getElementById('idEnlargeModal').style.display='flex'"
                                                     title="Click to enlarge">
                                                <div>
                                                    <div class="text-success fw-bold mb-1">
                                                        <i class="fas fa-check-circle me-1"></i> ID uploaded successfully
                                                    </div>
                                                    <div id="idFileName" class="text-muted small mb-2"></div>
                                                    <button type="button" class="btn btn-outline-danger btn-sm" id="removeIdBtn">
                                                        <i class="fas fa-times me-1"></i> Remove / Cancel Upload
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Error message -->
                                        <div id="idUploadError" class="mt-2 d-none">
                                            <small class="text-danger"><i class="fas fa-exclamation-circle me-1"></i><span id="idUploadErrorMsg"></span></small>
                                        </div>
                                    </div>
                                    <!-- Hidden field to carry path to PHP -->
                                    <input type="hidden" name="id_image_path" id="idImagePath" value="">
                                </div>

                                <!-- ID Enlarge Modal (pure CSS/JS, no Bootstrap modal needed) -->
                                <div id="idEnlargeModal" onclick="this.style.display='none'"
                                     style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.75); z-index:9999; align-items:center; justify-content:center; cursor:zoom-out;">
                                    <img id="idEnlargeImg" src="" alt="ID Full View"
                                         style="max-width:90vw; max-height:90vh; border-radius:8px; box-shadow:0 4px 32px rgba(0,0,0,.5);">
                                </div>
                                
                                <?php if (!is_logged_in()): ?>
                                <div class="alert alert-warning mb-4">
                                    <h6 class="alert-heading">
                                        <i class="fas fa-exclamation-triangle"></i> Cannot Proceed Without Authentication
                                    </h6>
                                    <p class="mb-2">You must be signed in to complete your booking.</p>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a href="register.php<?php echo $selected_room_id ? '?redirect=booking&room=' . $selected_room_id : '?redirect=booking'; ?>" 
                                           class="btn btn-success btn-sm">
                                            <i class="fas fa-user-plus"></i> Create Account
                                        </a>
                                        <a href="login.php<?php echo $selected_room_id ? '?redirect=booking&room=' . $selected_room_id : '?redirect=booking'; ?>" 
                                           class="btn btn-primary btn-sm">
                                            <i class="fas fa-sign-in-alt"></i> Sign In
                                        </a>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-secondary btn-lg w-100" disabled>
                                    <i class="fas fa-lock"></i> BOOKING DISABLED - Please Sign In or Create Account Above
                                </button>
                                <?php else: ?>
                                <button type="submit" class="btn btn-gold btn-lg w-100" id="confirmBookingBtn" disabled>
                                    <i class="fas fa-lock me-2"></i> Upload ID to Enable Booking
                                </button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card shadow-sm sticky-top" style="top: 100px;">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><?php echo __('booking.booking_summary'); ?></h5>
                        </div>
                        <div class="card-body">
                            <div id="bookingSummary">
                                <p class="text-muted text-center py-4">
                                    <i class="fas fa-info-circle"></i><br>
                                    <?php echo __('booking.select_room'); ?> <?php echo __('booking_auth.and_dates'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
    <script>
        // Override formatCurrency function to ensure ETB display
        function formatCurrency(amount) {
            return 'ETB ' + parseFloat(amount).toFixed(2);
        }
        
        // Add cache-busting for room data
        $(document).ready(function() {
            // Force refresh of room dropdown if needed
            if (window.location.search.includes('refresh_rooms=1')) {
                location.reload(true);
            }
        });
    </script>
    <script>
        $(document).ready(function() {
            function updateSummary() {
                const roomSelect = $('#roomSelect');
                const checkIn = $('#checkIn').val();
                const checkOut = $('#checkOut').val();
                const customers = $('#customers').val();
                
                if (roomSelect.val() && checkIn && checkOut) {
                    const selectedOption = roomSelect.find(':selected');
                    const roomName = selectedOption.text().split(' - ')[0];
                    const pricePerNight = parseFloat(selectedOption.data('price'));
                    const maxCapacity = parseInt(selectedOption.data('capacity'));
                    
                    const date1 = new Date(checkIn);
                    const date2 = new Date(checkOut);
                    const nights = Math.ceil((date2 - date1) / (1000 * 60 * 60 * 24));
                    
                    if (nights > 0) {
                        const totalPrice = pricePerNight * nights;
                        
                        let html = `
                            <div class="mb-3">
                                <strong>Room:</strong><br>
                                <span class="text-muted">${roomName}</span>
                            </div>
                            <div class="mb-3">
                                <strong>Check-in:</strong><br>
                                <span class="text-muted">${new Date(checkIn).toLocaleDateString()}</span>
                            </div>
                            <div class="mb-3">
                                <strong>Check-out:</strong><br>
                                <span class="text-muted">${new Date(checkOut).toLocaleDateString()}</span>
                            </div>
                            <div class="mb-3">
                                <strong>Customers:</strong><br>
                                <span class="text-muted">${customers} Customer(s)</span>
                            </div>
                            <hr>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between">
                                    <span>${formatCurrency(pricePerNight)} × ${nights} <?php echo __('booking_auth.nights'); ?></span>
                                    <span>${formatCurrency(totalPrice)}</span>
                                </div>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <strong><?php echo __('booking_auth.total'); ?>:</strong>
                                <strong class="text-gold fs-4">${formatCurrency(totalPrice)}</strong>
                            </div>
                        `;
                        
                        if (parseInt(customers) > maxCapacity) {
                            html += `<div class="alert alert-warning mt-3 mb-0">
                                <small><i class="fas fa-exclamation-triangle"></i> This room has a maximum capacity of ${maxCapacity} customers.</small>
                            </div>`;
                        }
                        
                        $('#bookingSummary').html(html);
                    }
                }
            }
            
            $('#roomSelect, #checkIn, #checkOut, #customers').on('change', updateSummary);
            
            // Set minimum checkout date based on checkin
            $('#checkIn').on('change', function() {
                const checkInDate = new Date($(this).val());
                checkInDate.setDate(checkInDate.getDate() + 1);
                $('#checkOut').attr('min', checkInDate.toISOString().split('T')[0]);
            });
            
            // Initial update if room is pre-selected
            <?php if ($selected_room): ?>
            // Auto-select the room in dropdown
            $('#roomSelect').val(<?php echo $selected_room_id; ?>);
            updateSummary();
            <?php endif; ?>
            
            // Handle form submission — require ID upload
            $('#bookingForm').on('submit', function(e) {
                const idPath = $('#idImagePath').val();
                if (!idPath) {
                    e.preventDefault();
                    alert('Please upload your ID before confirming booking.');
                    document.getElementById('idUploadBox').scrollIntoView({ behavior: 'smooth' });
                    return false;
                }
                const submitBtn = $(this).find('button[type="submit"]');
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> <?php echo __('booking_auth.processing'); ?>');
            });
        });
    </script>

    <!-- Camera Modal for Scan ID -->
    <div id="cameraModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.85); z-index:10000; align-items:center; justify-content:center; flex-direction:column;">
        <div style="background:#1a1a2e; border-radius:12px; padding:20px; max-width:520px; width:95%; box-shadow:0 8px 40px rgba(0,0,0,.6);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                <h5 style="color:#fff; margin:0;"><i class="fas fa-camera me-2" style="color:#f7931e;"></i>Scan ID with Camera</h5>
                <button id="closeCameraBtn" style="background:none; border:none; color:#aaa; font-size:22px; cursor:pointer; line-height:1;">&times;</button>
            </div>
            <!-- Live video feed -->
            <div style="position:relative; background:#000; border-radius:8px; overflow:hidden; margin-bottom:14px;">
                <video id="cameraVideo" autoplay playsinline muted style="width:100%; max-height:320px; display:block; object-fit:cover;"></video>
                <!-- Overlay guide frame -->
                <div style="position:absolute; inset:0; pointer-events:none; display:flex; align-items:center; justify-content:center;">
                    <div style="width:80%; height:55%; border:2px dashed rgba(247,147,30,.8); border-radius:8px; box-shadow:0 0 0 9999px rgba(0,0,0,.35);"></div>
                </div>
                <div style="position:absolute; bottom:8px; left:0; right:0; text-align:center;">
                    <small style="color:rgba(255,255,255,.8); background:rgba(0,0,0,.5); padding:3px 10px; border-radius:20px;">Position your ID inside the frame</small>
                </div>
            </div>
            <!-- Canvas (hidden, used for capture) -->
            <canvas id="cameraCanvas" style="display:none;"></canvas>
            <!-- Camera selector (shown if multiple cameras) -->
            <div id="cameraSelectorWrap" style="display:none; margin-bottom:12px;">
                <select id="cameraSelector" class="form-select form-select-sm">
                    <option value="">Select camera...</option>
                </select>
            </div>
            <!-- Error message inside modal -->
            <div id="cameraError" style="display:none; color:#ff6b6b; font-size:.875rem; margin-bottom:10px; text-align:center;"></div>
            <!-- Action buttons -->
            <div style="display:flex; gap:10px; justify-content:center;">
                <button id="captureBtn" class="btn btn-warning btn-lg px-4" style="font-weight:600;">
                    <i class="fas fa-circle me-2"></i>Capture Photo
                </button>
                <button id="switchCameraBtn" class="btn btn-outline-light btn-sm d-none" title="Switch camera">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- ID Upload Script -->
    <script>
    (function() {
        const fileInput   = document.getElementById('idFileInput');
        const previewArea = document.getElementById('idPreviewArea');
        const previewImg  = document.getElementById('idPreviewImg');
        const enlargeImg  = document.getElementById('idEnlargeImg');
        const fileNameEl  = document.getElementById('idFileName');
        const pathInput   = document.getElementById('idImagePath');
        const confirmBtn  = document.getElementById('confirmBookingBtn');
        const errorDiv    = document.getElementById('idUploadError');
        const errorMsg    = document.getElementById('idUploadErrorMsg');
        const progressDiv = document.getElementById('idUploadProgress');
        const removeBtn   = document.getElementById('removeIdBtn');
        const scanBtn     = document.getElementById('scanIdBtn');
        const uploadBox   = document.getElementById('idUploadBox');

        // Camera modal elements
        const cameraModal    = document.getElementById('cameraModal');
        const cameraVideo    = document.getElementById('cameraVideo');
        const cameraCanvas   = document.getElementById('cameraCanvas');
        const captureBtn     = document.getElementById('captureBtn');
        const closeCameraBtn = document.getElementById('closeCameraBtn');
        const switchCameraBtn= document.getElementById('switchCameraBtn');
        const cameraSelector = document.getElementById('cameraSelector');
        const cameraSelectorWrap = document.getElementById('cameraSelectorWrap');
        const cameraError    = document.getElementById('cameraError');

        if (!fileInput) return; // not logged in

        let cameraStream = null;
        let availableCameras = [];
        let currentCameraIndex = 0;

        // Show camera button only if getUserMedia is supported
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            scanBtn.classList.remove('d-none');
        }

        function showError(msg) {
            errorMsg.textContent = msg;
            errorDiv.classList.remove('d-none');
            setTimeout(() => errorDiv.classList.add('d-none'), 7000);
        }

        function setConfirmEnabled(enabled) {
            if (!confirmBtn) return;
            if (enabled) {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Confirm Booking';
            } else {
                confirmBtn.disabled = true;
                confirmBtn.innerHTML = '<i class="fas fa-lock me-2"></i> Upload ID to Enable Booking';
            }
        }

        function handleFile(file) {
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                showError('Invalid file format. Only JPG, JPEG, PNG allowed.');
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                showError('File too large. Maximum size is 2MB.');
                return;
            }
            progressDiv.classList.remove('d-none');
            errorDiv.classList.add('d-none');
            previewArea.classList.add('d-none');

            const formData = new FormData();
            formData.append('id_image', file);

            fetch('api/upload_id.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                progressDiv.classList.add('d-none');
                if (data.success) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImg.src = e.target.result;
                        enlargeImg.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                    fileNameEl.textContent = 'Camera capture (' + data.file_size + ')';
                    pathInput.value = data.file_path;
                    previewArea.classList.remove('d-none');
                    setConfirmEnabled(true);
                } else {
                    showError(data.error || 'ID upload failed. Please try again.');
                    setConfirmEnabled(false);
                }
            })
            .catch(() => {
                progressDiv.classList.add('d-none');
                showError('ID upload failed. Please try again.');
                setConfirmEnabled(false);
            });
        }

        // ── File input (Upload button) ────────────────────────────────────────
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) handleFile(this.files[0]);
        });

        // ── Remove button ─────────────────────────────────────────────────────
        removeBtn.addEventListener('click', function() {
            fileInput.value = '';
            pathInput.value = '';
            previewArea.classList.add('d-none');
            previewImg.src = '';
            enlargeImg.src = '';
            setConfirmEnabled(false);
        });

        // ── Camera: start stream ──────────────────────────────────────────────
        async function startCamera(deviceId) {
            stopCamera();
            cameraError.style.display = 'none';
            try {
                const constraints = {
                    video: deviceId
                        ? { deviceId: { exact: deviceId }, width: { ideal: 1280 }, height: { ideal: 720 } }
                        : { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
                    audio: false
                };
                cameraStream = await navigator.mediaDevices.getUserMedia(constraints);
                cameraVideo.srcObject = cameraStream;
                await cameraVideo.play();
            } catch (err) {
                let msg = 'Camera access denied.';
                if (err.name === 'NotAllowedError')  msg = 'Camera permission denied. Please allow camera access in your browser settings.';
                if (err.name === 'NotFoundError')    msg = 'No camera found on this device.';
                if (err.name === 'NotReadableError') msg = 'Camera is in use by another application.';
                cameraError.textContent = msg;
                cameraError.style.display = 'block';
            }
        }

        function stopCamera() {
            if (cameraStream) {
                cameraStream.getTracks().forEach(t => t.stop());
                cameraStream = null;
            }
            cameraVideo.srcObject = null;
        }

        async function openCameraModal() {
            cameraModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';

            // Enumerate cameras
            try {
                const devices = await navigator.mediaDevices.enumerateDevices();
                availableCameras = devices.filter(d => d.kind === 'videoinput');
                if (availableCameras.length > 1) {
                    switchCameraBtn.classList.remove('d-none');
                    cameraSelectorWrap.style.display = 'block';
                    cameraSelector.innerHTML = availableCameras.map((d, i) =>
                        `<option value="${d.deviceId}">${d.label || 'Camera ' + (i+1)}</option>`
                    ).join('');
                }
            } catch(e) {}

            await startCamera(availableCameras[currentCameraIndex]?.deviceId || null);
        }

        function closeCameraModal() {
            stopCamera();
            cameraModal.style.display = 'none';
            document.body.style.overflow = '';
        }

        // ── Scan ID button → open camera modal ───────────────────────────────
        scanBtn.addEventListener('click', openCameraModal);
        closeCameraBtn.addEventListener('click', closeCameraModal);

        // Close on backdrop click
        cameraModal.addEventListener('click', function(e) {
            if (e.target === cameraModal) closeCameraModal();
        });

        // Switch camera
        switchCameraBtn.addEventListener('click', async function() {
            currentCameraIndex = (currentCameraIndex + 1) % availableCameras.length;
            await startCamera(availableCameras[currentCameraIndex].deviceId);
        });

        // Camera selector dropdown
        cameraSelector.addEventListener('change', async function() {
            if (this.value) await startCamera(this.value);
        });

        // ── Capture photo ─────────────────────────────────────────────────────
        captureBtn.addEventListener('click', function() {
            if (!cameraStream) return;

            const video = cameraVideo;
            cameraCanvas.width  = video.videoWidth  || 1280;
            cameraCanvas.height = video.videoHeight || 720;
            const ctx = cameraCanvas.getContext('2d');
            ctx.drawImage(video, 0, 0, cameraCanvas.width, cameraCanvas.height);

            // Flash effect
            captureBtn.innerHTML = '<i class="fas fa-check me-2"></i>Captured!';
            captureBtn.classList.replace('btn-warning', 'btn-success');
            setTimeout(() => {
                captureBtn.innerHTML = '<i class="fas fa-circle me-2"></i>Capture Photo';
                captureBtn.classList.replace('btn-success', 'btn-warning');
            }, 1500);

            // Convert canvas to Blob and upload
            cameraCanvas.toBlob(function(blob) {
                if (!blob) { showError('Failed to capture image. Please try again.'); return; }
                const file = new File([blob], 'camera_capture_' + Date.now() + '.jpg', { type: 'image/jpeg' });
                closeCameraModal();
                handleFile(file);
            }, 'image/jpeg', 0.92);
        });

        // ── Drag & drop ───────────────────────────────────────────────────────
        uploadBox.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = '#1565c0';
            this.style.background = '#e3f2fd';
        });
        uploadBox.addEventListener('dragleave', function() {
            this.style.borderColor = '#1e88e5';
            this.style.background = '#f8f9fa';
        });
        uploadBox.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.borderColor = '#1e88e5';
            this.style.background = '#f8f9fa';
            const file = e.dataTransfer.files[0];
            if (file) handleFile(file);
        });
    })();
    </script>
</body>
</html>
