<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('receptionist', '../login.php');

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $hotel_name = sanitize_input($_POST['hotel_name']);
    $hotel_location = sanitize_input($_POST['hotel_location']);
    $check_in_date = sanitize_input($_POST['check_in_date']);
    $check_out_date = sanitize_input($_POST['check_out_date']);
    $guest_full_name = sanitize_input($_POST['guest_full_name']);
    $guest_date_of_birth = sanitize_input($_POST['guest_date_of_birth']);
    $guest_id_type = sanitize_input($_POST['guest_id_type']);
    $guest_id_number = sanitize_input($_POST['guest_id_number']);
    $guest_nationality = sanitize_input($_POST['guest_nationality']);
    $guest_home_address = sanitize_input($_POST['guest_home_address']);
    $guest_phone_number = sanitize_input($_POST['guest_phone_number']);
    $guest_email_address = sanitize_input($_POST['guest_email_address']);
    $room_type = sanitize_input($_POST['room_type']);
    $room_number = sanitize_input($_POST['room_number']);
    $nights_stay = (int)$_POST['nights_stay'];
    $number_of_guests = (int)$_POST['number_of_guests'];
    $rate_per_night = (float)$_POST['rate_per_night'];
    $payment_type = sanitize_input($_POST['payment_type']);
    $amount_paid = (float)$_POST['amount_paid'];
    $balance_due = (float)$_POST['balance_due'];
    $additional_requests = sanitize_input($_POST['additional_requests']);
    
    // Generate confirmation number
    $confirmation_number = 'CHK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    
    try {
        $conn->begin_transaction();
        
        // Insert into checkins table
        $checkin_query = "INSERT INTO checkins (
            customer_id, hotel_name, hotel_location, 
            check_in_date, check_out_date,
            guest_full_name, guest_date_of_birth, guest_id_type, guest_id_number, 
            guest_nationality, guest_home_address, guest_phone_number, guest_email_address,
            room_type, room_number, nights_stay, number_of_guests, rate_per_night,
            payment_type, amount_paid, balance_due, confirmation_number, 
            additional_requests, checked_in_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($checkin_query);
        $customer_id = 0; // Walk-in customer
        $checked_in_by = $_SESSION['user_id'];
        
        $stmt->bind_param("issssssssssssssiidsddssi",
            $customer_id, $hotel_name, $hotel_location,
            $check_in_date, $check_out_date,
            $guest_full_name, $guest_date_of_birth, $guest_id_type, $guest_id_number,
            $guest_nationality, $guest_home_address, $guest_phone_number, $guest_email_address,
            $room_type, $room_number, $nights_stay, $number_of_guests, $rate_per_night,
            $payment_type, $amount_paid, $balance_due, $confirmation_number,
            $additional_requests, $checked_in_by
        );
        
        if ($stmt->execute()) {
            $checkin_id = $conn->insert_id;
            
            // Update room status to occupied if room number provided
            if (!empty($room_number)) {
                $room_update = "UPDATE rooms SET status = 'occupied' WHERE room_number = ?";
                $room_stmt = $conn->prepare($room_update);
                $room_stmt->bind_param("s", $room_number);
                $room_stmt->execute();
            }
            
            $conn->commit();
            $message = "Customer checked in successfully! Confirmation Number: " . $confirmation_number;
            
            // Clear form data
            $_POST = array();
        } else {
            throw new Exception("Failed to create check-in record");
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Check-in failed: " . $e->getMessage();
    }
}

// Get available rooms
$rooms_query = "SELECT id, name, room_number, price FROM rooms WHERE status = 'active' ORDER BY room_number";
$rooms_result = $conn->query($rooms_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Check-in - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-hotel text-gold"></i> Harar Ras Hotel - Reception
            </a>
            <div class="navbar-nav ms-auto d-flex align-items-center">
                <span class="navbar-text me-3">
                    <i class="fas fa-user-tie"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?> (Receptionist)
                </span>
                <a href="../logout.php" class="btn btn-outline-light btn-sm" 
                   onclick="return confirm('Are you sure you want to logout?')">
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
                        <a href="receptionist.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-tachometer-alt"></i> Dashboard Overview
                        </a>
                        <a href="customer-checkin.php" class="list-group-item list-group-item-action active">
                            <i class="fas fa-user-plus"></i> Customer Check-in
                        </a>
                        <a href="verify-id.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-id-card"></i> Verify ID
                        </a>
                        <a href="receptionist-checkout.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-minus-circle"></i> Process Check-out
                        </a>
                        <a href="receptionist-rooms.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-bed"></i> Manage Rooms
                        </a>
                        <a href="receptionist-services.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-utensils"></i> Manage Foods & Services
                        </a>
                        <a href="../generate_bill.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-file-invoice-dollar"></i> Generate Bill
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-user-plus text-primary"></i> Customer Check-in</h2>
                    <a href="receptionist.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-clipboard-check"></i> New Customer Check-in Form</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="checkinForm">
                            <!-- Hotel Information -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="text-primary border-bottom pb-2 mb-3">
                                        <i class="fas fa-hotel"></i> Hotel Information
                                    </h6>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Hotel Name *</label>
                                    <input type="text" name="hotel_name" class="form-control" 
                                           value="Harar Ras Hotel" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Hotel Location *</label>
                                    <input type="text" name="hotel_location" class="form-control" 
                                           value="Jugol Street, Harar, Ethiopia" required>
                                </div>
                            </div>

                            <!-- Stay Information -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="text-primary border-bottom pb-2 mb-3">
                                        <i class="fas fa-calendar"></i> Stay Information
                                    </h6>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Check-in Date *</label>
                                    <input type="date" name="check_in_date" class="form-control" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Check-out Date *</label>
                                    <input type="date" name="check_out_date" class="form-control" 
                                           value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Nights Stay *</label>
                                    <input type="number" name="nights_stay" class="form-control" 
                                           value="1" min="1" required readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Number of Guests *</label>
                                    <input type="number" name="number_of_guests" class="form-control" 
                                           value="1" min="1" max="4" required>
                                </div>
                            </div>

                            <!-- Guest Information -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="text-primary border-bottom pb-2 mb-3">
                                        <i class="fas fa-user"></i> Guest Information
                                    </h6>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Full Name *</label>
                                    <input type="text" name="guest_full_name" class="form-control" 
                                           placeholder="Enter guest full name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Date of Birth</label>
                                    <input type="date" name="guest_date_of_birth" class="form-control">
                                </div>
                                <div class="col-md-4 mt-3">
                                    <label class="form-label fw-bold">ID Type *</label>
                                    <select name="guest_id_type" class="form-select" required>
                                        <option value="">Select ID Type</option>
                                        <option value="passport">Passport</option>
                                        <option value="national_id">National ID</option>
                                        <option value="driving_license">Driving License</option>
                                        <option value="other">Other Government ID</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mt-3">
                                    <label class="form-label fw-bold">ID Number *</label>
                                    <input type="text" name="guest_id_number" class="form-control" 
                                           placeholder="Enter ID number" required>
                                </div>
                                <div class="col-md-4 mt-3">
                                    <label class="form-label fw-bold">Nationality</label>
                                    <input type="text" name="guest_nationality" class="form-control" 
                                           value="Ethiopian" placeholder="Enter nationality">
                                </div>
                                <div class="col-md-12 mt-3">
                                    <label class="form-label fw-bold">Home Address</label>
                                    <textarea name="guest_home_address" class="form-control" rows="2" 
                                              placeholder="Enter home address"></textarea>
                                </div>
                                <div class="col-md-6 mt-3">
                                    <label class="form-label fw-bold">Phone Number *</label>
                                    <input type="tel" name="guest_phone_number" class="form-control" 
                                           placeholder="Enter phone number" required>
                                </div>
                                <div class="col-md-6 mt-3">
                                    <label class="form-label fw-bold">Email Address *</label>
                                    <input type="email" name="guest_email_address" class="form-control" 
                                           placeholder="Enter email address" required>
                                </div>
                            </div>

                            <!-- Room Information -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="text-primary border-bottom pb-2 mb-3">
                                        <i class="fas fa-bed"></i> Room Information
                                    </h6>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Room Type *</label>
                                    <select name="room_type" class="form-select" required>
                                        <option value="">Select Room Type</option>
                                        <option value="Standard Single Room">Standard Single Room</option>
                                        <option value="Executive Suite">Executive Suite</option>
                                        <option value="Presidential Suite">Presidential Suite</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Room Number *</label>
                                    <select name="room_number" class="form-select" required>
                                        <option value="">Select Room</option>
                                        <?php if ($rooms_result && $rooms_result->num_rows > 0): ?>
                                            <?php while ($room = $rooms_result->fetch_assoc()): ?>
                                                <option value="<?php echo htmlspecialchars($room['room_number']); ?>" 
                                                        data-price="<?php echo $room['price']; ?>">
                                                    Room <?php echo htmlspecialchars($room['room_number']); ?> - <?php echo htmlspecialchars($room['name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Rate per Night (ETB) *</label>
                                    <input type="number" name="rate_per_night" class="form-control" 
                                           step="0.01" min="0" required>
                                </div>
                            </div>

                            <!-- Payment Information -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="text-primary border-bottom pb-2 mb-3">
                                        <i class="fas fa-money-bill"></i> Payment Information
                                    </h6>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Payment Type *</label>
                                    <select name="payment_type" class="form-select" required>
                                        <option value="">Select Payment Type</option>
                                        <option value="cash">Cash</option>
                                        <option value="credit_card">Credit Card</option>
                                        <option value="debit_card">Debit Card</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Amount Paid (ETB) *</label>
                                    <input type="number" name="amount_paid" class="form-control" 
                                           step="0.01" min="0" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Balance Due (ETB)</label>
                                    <input type="number" name="balance_due" class="form-control" 
                                           step="0.01" min="0" value="0">
                                </div>
                            </div>

                            <!-- Additional Information -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="text-primary border-bottom pb-2 mb-3">
                                        <i class="fas fa-sticky-note"></i> Additional Information
                                    </h6>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Special Requests / Notes</label>
                                    <textarea name="additional_requests" class="form-control" rows="3" 
                                              placeholder="Enter any special requests or notes"></textarea>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="reset" class="btn btn-outline-secondary me-md-2">
                                    <i class="fas fa-undo"></i> Reset Form
                                </button>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-check-circle"></i> Complete Check-in
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Calculate nights automatically
        function calculateNights() {
            const checkIn = document.querySelector('input[name="check_in_date"]').value;
            const checkOut = document.querySelector('input[name="check_out_date"]').value;
            
            if (checkIn && checkOut) {
                const checkInDate = new Date(checkIn);
                const checkOutDate = new Date(checkOut);
                const nights = Math.ceil((checkOutDate - checkInDate) / (1000 * 60 * 60 * 24));
                
                if (nights > 0) {
                    document.querySelector('input[name="nights_stay"]').value = nights;
                    calculateTotal();
                }
            }
        }

        // Calculate total amount
        function calculateTotal() {
            const nights = parseInt(document.querySelector('input[name="nights_stay"]').value) || 0;
            const rate = parseFloat(document.querySelector('input[name="rate_per_night"]').value) || 0;
            const total = nights * rate;
            
            document.querySelector('input[name="amount_paid"]').value = total.toFixed(2);
        }

        // Update rate when room is selected
        document.querySelector('select[name="room_number"]').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const price = selectedOption.dataset.price;
            if (price) {
                document.querySelector('input[name="rate_per_night"]').value = price;
                calculateTotal();
            }
        });

        // Add event listeners
        document.querySelector('input[name="check_in_date"]').addEventListener('change', calculateNights);
        document.querySelector('input[name="check_out_date"]').addEventListener('change', calculateNights);
        document.querySelector('input[name="rate_per_night"]').addEventListener('input', calculateTotal);
    </script>
</body>
</html>