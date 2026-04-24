<?php 
// Start session only if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Add cache-busting headers to prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$selected_room_id = isset($_GET['room']) ? (int)$_GET['room'] : (isset($_GET['room_id']) ? (int)$_GET['room_id'] : null);
$selected_room = null;

if ($selected_room_id) {
    $selected_room = get_room_by_id($selected_room_id);
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!is_logged_in()) {
        $_SESSION['booking_data'] = $_POST;
        header('Location: login.php');
        exit();
    }
    
    $room_id = (int)$_POST['room_id'];
    $check_in = sanitize_input($_POST['check_in']);
    $check_out = sanitize_input($_POST['check_out']);
    $customers = (int)$_POST['customers'];
    $special_requests = sanitize_input($_POST['special_requests'] ?? '');
    
    $room = get_room_by_id($room_id);
    
    if (!$room) {
        $error = 'Invalid room selected';
    } else {
        // Create booking directly
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
        ];
        
        $result = create_booking($booking_data);
        
        if ($result['success']) {
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
            $update_stmt->execute();
            
            // Store booking reference in session for payment
            $_SESSION['pending_booking'] = $result['booking_reference'];
            $_SESSION['current_booking_id'] = $result['booking_id'];
            
            // Redirect to payment upload page
            header('Location: payment-upload.php?booking=' . $result['booking_id']);
            exit();
        } else {
            $error = 'Booking failed. Please try again. Error: ' . $result['message'];
        }
    }
}

$rooms = get_all_rooms();
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
                        <strong>This is the ROOM BOOKING page.</strong> Select your room and dates below.
                        <br><small>Looking to order food? <a href="food-booking.php" class="alert-link">Click here for Food Ordering</a></small>
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
                        <i class="fas fa-arrow-left"></i> Back to Home
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
                                <i class="fas fa-shield-alt"></i> Authentication Required
                            </h3>
                            <p class="mb-0 mt-2">Follow these simple steps to complete your booking</p>
                        </div>
                        <div class="card-body p-4">
                            <!-- Authentication Options -->
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <div class="card bg-success text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-user-plus fa-3x mb-3"></i>
                                            <h5>New Customer?</h5>
                                            <p class="mb-3">Create a free account in just 2 minutes</p>
                                            <a href="register.php<?php echo $selected_room_id ? '?redirect=booking&room=' . $selected_room_id : '?redirect=booking'; ?>" 
                                               class="btn btn-light btn-lg">
                                                <i class="fas fa-user-plus"></i> Create Account Now
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="card bg-primary text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-sign-in-alt fa-3x mb-3"></i>
                                            <h5>Existing Customer?</h5>
                                            <p class="mb-3">Sign in to your account to continue</p>
                                            <a href="login.php<?php echo $selected_room_id ? '?redirect=booking&room=' . $selected_room_id : '?redirect=booking'; ?>" 
                                               class="btn btn-light btn-lg">
                                                <i class="fas fa-sign-in-alt"></i> Sign In Now
                                            </a>
                                        </div>
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
                                <i class="fas fa-calendar-check me-2"></i> Book Your Stay
                                <?php if (!is_logged_in()): ?>
                                <span class="badge bg-danger ms-2" style="font-size: 0.9rem;">
                                    <i class="fas fa-lock"></i> LOCKED - Authentication Required
                                </span>
                                <?php endif; ?>
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i>
                                    <?php echo htmlspecialchars($success); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!is_logged_in()): ?>
                            <div class="alert alert-danger">
                                <h5 class="alert-heading">
                                    <i class="fas fa-lock"></i> Login Required
                                </h5>
                                <p class="mb-0">You must be logged in to make a booking. Please create an account or sign in above.</p>
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="" id="bookingForm" <?php echo !is_logged_in() ? 'style="pointer-events: none;"' : ''; ?>>
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Select Room *</label>
                                    <select name="room_id" class="form-select form-select-lg" required id="roomSelect">
                                        <option value="">Choose your room...</option>
                                        <?php 
                                        $rooms_by_type = [];
                                        foreach ($rooms as $room) {
                                            $type = $room['room_type'] ?? 'Standard';
                                            if (!isset($rooms_by_type[$type])) {
                                                $rooms_by_type[$type] = [];
                                            }
                                            $rooms_by_type[$type][] = $room;
                                        }
                                        
                                        foreach ($rooms_by_type as $room_type_name => $rooms_in_type):
                                            $first_room = $rooms_in_type[0];
                                            $price_formatted = number_format($first_room['price'], 2);
                                        ?>
                                        <optgroup label="<?php echo htmlspecialchars($room_type_name); ?> - ETB <?php echo $price_formatted; ?>/night">
                                            <?php foreach ($rooms_in_type as $room): ?>
                                            <option value="<?php echo $room['id']; ?>" 
                                                    data-price="<?php echo $room['price']; ?>" 
                                                    data-capacity="<?php echo $room['capacity']; ?>"
                                                    <?php echo ($selected_room_id == $room['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($room['name']); ?> Number <?php echo $room['room_number']; ?> - ETB <?php echo number_format($room['price'], 2); ?>/night
                                            </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Check-in Date *</label>
                                        <input type="date" name="check_in" class="form-control form-control-lg" required 
                                               min="<?php echo date('Y-m-d'); ?>" id="checkInDate">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Check-out Date *</label>
                                        <input type="date" name="check_out" class="form-control form-control-lg" required 
                                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" id="checkOutDate">
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-bold">Number of Customers *</label>
                                    <select name="customers" class="form-select form-select-lg" required id="customersSelect">
                                        <option value="">Select number of customers...</option>
                                        <option value="1">1 Customer</option>
                                        <option value="2">2 Customers</option>
                                        <option value="3">3 Customers</option>
                                        <option value="4">4 Customers</option>
                                        <option value="5">5 Customers</option>
                                        <option value="6">6 Customers</option>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-bold">Special Requests</label>
                                    <textarea name="special_requests" class="form-control" rows="3" 
                                              placeholder="Any special requests or preferences..."></textarea>
                                </div>

                                <?php if (is_logged_in()): ?>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg" id="confirmBookingBtn">
                                        <i class="fas fa-check-circle"></i> Confirm Booking
                                    </button>
                                </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-calculator"></i> Booking Summary</h5>
                        </div>
                        <div class="card-body">
                            <div id="bookingSummary">
                                <p class="text-muted text-center py-4">
                                    <i class="fas fa-info-circle"></i><br>
                                    Select room and dates to see pricing
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Simple booking calculation
        function updateBookingSummary() {
            const roomSelect = document.getElementById('roomSelect');
            const checkIn = document.getElementById('checkInDate');
            const checkOut = document.getElementById('checkOutDate');
            const customers = document.getElementById('customersSelect');
            const summaryDiv = document.getElementById('bookingSummary');
            
            if (roomSelect.value && checkIn.value && checkOut.value && customers.value) {
                const selectedOption = roomSelect.options[roomSelect.selectedIndex];
                const price = parseFloat(selectedOption.dataset.price);
                const capacity = parseInt(selectedOption.dataset.capacity);
                
                const checkInDate = new Date(checkIn.value);
                const checkOutDate = new Date(checkOut.value);
                const nights = Math.ceil((checkOutDate - checkInDate) / (1000 * 60 * 60 * 24));
                
                if (nights > 0) {
                    const totalPrice = price * nights;
                    
                    summaryDiv.innerHTML = `
                        <div class="mb-2">
                            <strong>Room:</strong> ${selectedOption.text.split(' - ETB')[0]}
                        </div>
                        <div class="mb-2">
                            <strong>Dates:</strong> ${checkIn.value} to ${checkOut.value}
                        </div>
                        <div class="mb-2">
                            <strong>Nights:</strong> ${nights}
                        </div>
                        <div class="mb-2">
                            <strong>Customers:</strong> ${customers.value}
                        </div>
                        <hr>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between">
                                <span>ETB ${price.toFixed(2)} × ${nights} nights</span>
                                <span>ETB ${totalPrice.toFixed(2)}</span>
                            </div>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <strong>Total:</strong>
                            <strong class="text-primary fs-4">ETB ${totalPrice.toFixed(2)}</strong>
                        </div>
                    `;
                }
            }
        }
        
        // Add event listeners
        document.getElementById('roomSelect').addEventListener('change', updateBookingSummary);
        document.getElementById('checkInDate').addEventListener('change', updateBookingSummary);
        document.getElementById('checkOutDate').addEventListener('change', updateBookingSummary);
        document.getElementById('customersSelect').addEventListener('change', updateBookingSummary);
        
        // Update check-out minimum date when check-in changes
        document.getElementById('checkInDate').addEventListener('change', function() {
            const checkInDate = new Date(this.value);
            checkInDate.setDate(checkInDate.getDate() + 1);
            document.getElementById('checkOutDate').min = checkInDate.toISOString().split('T')[0];
        });
    </script>
</body>
</html>