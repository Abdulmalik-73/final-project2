<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/ensure_checkins_table.php';

// Enable error display for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_auth_role('receptionist', '../login.php');

// Ensure checkins table exists before processing any check-ins
ensure_checkins_table_exists($conn);

$message = '';
$error = '';
$booking_data = null;

// Check if this is a success redirect
if (isset($_GET['success']) && $_GET['success'] == '1' && isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}

// Check if booking reference is provided in URL (from receptionist dashboard)
if (isset($_GET['booking_ref']) && !empty($_GET['booking_ref'])) {
    $booking_ref = sanitize_input($_GET['booking_ref']);
    
    $search_query = "SELECT b.*, 
                     COALESCE(r.name, 'Food Order') as room_name, 
                     COALESCE(r.room_number, 'N/A') as room_number, 
                     COALESCE(r.price, 0) as price,
                     CONCAT(u.first_name, ' ', u.last_name) as guest_name, u.email, u.phone,
                     DATEDIFF(b.check_out_date, b.check_in_date) as nights,
                     b.payment_status, b.payment_method, b.id_image
                     FROM bookings b
                     LEFT JOIN rooms r ON b.room_id = r.id
                     JOIN users u ON b.user_id = u.id
                     WHERE b.booking_reference = ? AND b.status IN ('pending','confirmed','verified') AND b.booking_type = 'room'";
    
    $stmt = $conn->prepare($search_query);
    $stmt->bind_param("s", $booking_ref);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $booking_data = $result->fetch_assoc();
    } else {
        $error = 'Room booking not found, not pending, or already processed';
    }
}

// Get today's check-ins — show ALL pending room bookings for today regardless of verification
$today = date('Y-m-d');
$todays_checkins_query = "SELECT b.*, 
                          COALESCE(r.name, 'Food Order') as room_name, 
                          COALESCE(r.room_number, 'N/A') as room_number, 
                          COALESCE(r.price, 0) as price,
                          CONCAT(u.first_name, ' ', u.last_name) as guest_name, u.email, u.phone,
                          DATEDIFF(b.check_out_date, b.check_in_date) as nights,
                          b.payment_status, b.verification_status, b.id_image
                          FROM bookings b
                          LEFT JOIN rooms r ON b.room_id = r.id
                          JOIN users u ON b.user_id = u.id
                          WHERE DATE(b.check_in_date) = '$today' 
                          AND b.status IN ('pending','confirmed','verified')
                          AND b.booking_type = 'room'
                          ORDER BY b.created_at DESC";

$todays_checkins = $conn->query($todays_checkins_query);

// Get guests staying 2+ days
$staying_query = "SELECT b.*, 
                  COALESCE(r.name, 'Food Order') as room_name, 
                  COALESCE(r.room_number, 'N/A') as room_number, 
                  COALESCE(r.price, 0) as price,
                  CONCAT(u.first_name, ' ', u.last_name) as guest_name, u.email, u.phone,
                  DATEDIFF(b.check_out_date, b.check_in_date) as nights,
                  b.payment_status
                  FROM bookings b
                  LEFT JOIN rooms r ON b.room_id = r.id
                  JOIN users u ON b.user_id = u.id
                  WHERE b.status = 'checked_in'
                  AND DATE(b.check_in_date) < '$today'
                  AND DATEDIFF(b.check_out_date, b.check_in_date) >= 2
                  AND b.booking_type = 'room'
                  ORDER BY b.check_out_date ASC";

$staying_guests = $conn->query($staying_query);

// ── NEW: Get ALL bookings that have an ID image uploaded ──────────────────────
// Show regardless of verification status so receptionist can identify the person
$id_bookings_query = "SELECT b.id, b.booking_reference, b.check_in_date, b.check_out_date,
                       b.total_price, b.status, b.payment_status, b.verification_status,
                       b.id_image, b.created_at,
                       COALESCE(r.name, 'N/A') as room_name,
                       COALESCE(r.room_number, 'N/A') as room_number,
                       CONCAT(u.first_name, ' ', u.last_name) as guest_name,
                       u.email, u.phone
                       FROM bookings b
                       LEFT JOIN rooms r ON b.room_id = r.id
                       JOIN users u ON b.user_id = u.id
                       WHERE b.id_image IS NOT NULL
                         AND b.id_image != ''
                         AND b.booking_type = 'room'
                         AND b.status NOT IN ('checked_out','cancelled')
                       ORDER BY b.created_at DESC
                       LIMIT 50";
$id_bookings = $conn->query($id_bookings_query);
$id_bookings_count = $id_bookings ? $id_bookings->num_rows : 0;

// Handle check-in form submission
if ($_POST && isset($_POST['action'])) {
    error_log("POST action received: " . $_POST['action']);
    
    if ($_POST['action'] == 'search_booking') {
        $search_type = sanitize_input($_POST['search_type']);
        $search_value = sanitize_input($_POST['search_value']);
        
        $search_query = "SELECT b.*, 
                         COALESCE(r.name, 'Food Order') as room_name, 
                         COALESCE(r.room_number, 'N/A') as room_number, 
                         COALESCE(r.price, 0) as price,
                         CONCAT(u.first_name, ' ', u.last_name) as guest_name, u.email, u.phone,
                         DATEDIFF(b.check_out_date, b.check_in_date) as nights,
                         b.payment_status, b.payment_method, b.id_image
                         FROM bookings b
                         LEFT JOIN rooms r ON b.room_id = r.id
                         JOIN users u ON b.user_id = u.id
                         WHERE b.status IN ('pending','confirmed','verified') AND b.booking_type = 'room'";
        
        if ($search_type == 'reference') {
            $search_query .= " AND b.booking_reference = '$search_value'";
        } elseif ($search_type == 'name') {
            $search_query .= " AND (u.first_name LIKE '%$search_value%' OR u.last_name LIKE '%$search_value%')";
        } elseif ($search_type == 'phone') {
            $search_query .= " AND u.phone LIKE '%$search_value%'";
        }
        
        $result = $conn->query($search_query);
        if ($result && $result->num_rows > 0) {
            $booking_data = $result->fetch_assoc();
        } else {
            $error = 'Room booking not found, not pending, or not confirmed';
        }
    } elseif ($_POST['action'] == 'process_checkin') {
        error_log("=== CHECK-IN FORM SUBMITTED ===");
        error_log("POST data: " . json_encode($_POST));
        
        $booking_id = (int)$_POST['booking_id'];
        $room_id = (int)$_POST['room_id'];
        $customer_name = sanitize_input($_POST['customer_name']);
        $customer_email = sanitize_input($_POST['customer_email']);
        $customer_phone = sanitize_input($_POST['customer_phone']);
        $id_type = sanitize_input($_POST['id_type']);
        $id_number = sanitize_input($_POST['id_number']);
        $room_key_number = sanitize_input($_POST['room_key_number']);
        $deposit_amount = (float)$_POST['deposit_amount'];
        $deposit_payment_method = sanitize_input($_POST['deposit_payment_method']);
        $payment_collected = (float)$_POST['payment_collected'];
        $payment_method = sanitize_input($_POST['payment_method']);
        $notes = sanitize_input($_POST['notes']);
        
        error_log("Booking ID: $booking_id, Room ID: $room_id, Customer: $customer_name");
        
        $conn->begin_transaction();
        
        try {
            // Update booking with check-in details - change from pending to checked_in
            $update_query = "UPDATE bookings SET 
                            status = 'checked_in',
                            customer_name = ?,
                            customer_email = ?,
                            customer_phone = ?,
                            id_type = ?,
                            id_number = ?,
                            room_key_number = ?,
                            incidental_deposit = ?,
                            deposit_payment_method = ?,
                            actual_checkin_time = NOW(),
                            checked_in_by = ?,
                            verified_at = NOW(),
                            verified_by = ?
                            WHERE id = ?";
            
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ssssssdsiii", $customer_name, $customer_email, $customer_phone, 
                             $id_type, $id_number, $room_key_number, $deposit_amount, 
                             $deposit_payment_method, $_SESSION['user_id'], $_SESSION['user_id'], $booking_id);
            $stmt->execute();
            
            // Update room status to 'occupied' when checked in
            if (!empty($room_id)) {
                $room_occupied_query = "UPDATE rooms SET status = 'occupied' WHERE id = ?";
                $room_occupied_stmt = $conn->prepare($room_occupied_query);
                if ($room_occupied_stmt) {
                    $room_occupied_stmt->bind_param("i", $room_id);
                    if (!$room_occupied_stmt->execute()) {
                        error_log("Failed to update room status: " . $room_occupied_stmt->error);
                    }
                } else {
                    error_log("Failed to prepare room status update: " . $conn->error);
                }
            } else {
                error_log("Room ID is empty - cannot update room status. Room ID: " . var_export($room_id, true));
            }
            
            // Issue room key
            if (!empty($room_key_number)) {
                $key_query = "INSERT INTO room_keys (booking_id, room_id, key_number, issued_by, status) 
                             VALUES (?, ?, ?, ?, 'issued')";
                $key_stmt = $conn->prepare($key_query);
                $key_stmt->bind_param("iisi", $booking_id, $room_id, $room_key_number, $_SESSION['user_id']);
                $key_stmt->execute();
            }
            
            // Log check-in action
            $log_query = "INSERT INTO checkin_checkout_log 
                         (booking_id, action_type, performed_by, payment_collected, payment_method, 
                          deposit_amount, id_verified, id_type, id_number, notes, ip_address) 
                         VALUES (?, 'check_in', ?, ?, ?, ?, 1, ?, ?, ?, ?)";
            $log_stmt = $conn->prepare($log_query);
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $log_stmt->bind_param("iidsdssss", $booking_id, $_SESSION['user_id'], $payment_collected, 
                                 $payment_method, $deposit_amount, $id_type, $id_number, $notes, $ip_address);
            $log_stmt->execute();
            
            // Log booking activity
            $booking_query = "SELECT user_id FROM bookings WHERE id = $booking_id";
            $booking_result = $conn->query($booking_query);
            if ($booking_result && $booking = $booking_result->fetch_assoc()) {
                log_booking_activity($booking_id, $booking['user_id'], 'checked_in', 'pending', 'checked_in', 
                                    'Customer checked in by receptionist with detailed form', $_SESSION['user_id']);
            }
            
            // Create detailed checkin record
            $booking_details_query = "SELECT b.*, r.name as room_name, r.room_number, 
                                     CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                                     u.email, u.phone
                                     FROM bookings b
                                     LEFT JOIN rooms r ON b.room_id = r.id
                                     JOIN users u ON b.user_id = u.id
                                     WHERE b.id = ?";
            $details_stmt = $conn->prepare($booking_details_query);
            $details_stmt->bind_param("i", $booking_id);
            $details_stmt->execute();
            $booking_details = $details_stmt->get_result()->fetch_assoc();
            
            $confirmation_number = 'CHK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            
            // Only create checkin record if booking details exist and checkins table exists
            if ($booking_details) {
                // Check if checkins table exists before inserting
                $table_check = $conn->query("SHOW TABLES LIKE 'checkins'");
                if ($table_check && $table_check->num_rows > 0) {
                    try {
                        // Create checkin record
                        $nights = (int)((strtotime($booking_details['check_out_date']) - strtotime($booking_details['check_in_date'])) / (60 * 60 * 24));
                        
                        $checkin_insert = $conn->prepare("
                            INSERT INTO checkins (
                                customer_id, booking_id, hotel_name, hotel_location, 
                                check_in_date, check_out_date,
                                guest_full_name, guest_date_of_birth, guest_id_type, guest_id_number, 
                                guest_nationality, guest_home_address, guest_phone_number, guest_email_address,
                                room_type, room_number, nights_stay, number_of_guests, rate_per_night,
                                payment_type, amount_paid, balance_due, confirmation_number, 
                                additional_requests, checked_in_by
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        if (!$checkin_insert) {
                            throw new Exception("Prepare failed: " . $conn->error);
                        }
                        
                        $hotel_name = 'Harar Ras Hotel';
                        $hotel_location = 'Jugol Street, Harar, Ethiopia';
                        $guest_dob = '1990-01-01';
                        $nationality = 'Ethiopian';
                        $address = $notes ?? 'N/A';
                        $payment_type = $payment_method ?? 'cash';
                        $amount_paid = (float)($payment_collected ?? $booking_details['total_price']);
                        $balance_due = 0.00;
                        $number_of_guests = (int)($booking_details['customers'] ?? 1);
                        $rate_per_night = (float)($booking_details['price'] ?? 0);
                        $user_id = (int)$booking_details['user_id'];
                        $checked_in_by = (int)$_SESSION['user_id'];
                        
                        $room_name = $booking_details['room_name'] ?? 'Standard Room';
                        $room_number = $booking_details['room_number'] ?? 'N/A';
                        
                        // Ensure id_type is valid ENUM value
                        $valid_id_types = ['passport', 'drivers_license', 'national_id'];
                        if (!in_array($id_type, $valid_id_types)) {
                            $id_type = 'national_id'; // Default to national_id if invalid
                        }
                        
                        // Ensure payment_type is valid ENUM value
                        $valid_payment_types = ['cash', 'credit_card', 'debit_card', 'bank_transfer', 'mobile_payment'];
                        if (!in_array($payment_type, $valid_payment_types)) {
                            $payment_type = 'cash'; // Default to cash if invalid
                        }
                        
                        // Ensure all required fields have values
                        $customer_name = $customer_name ?? 'Guest';
                        $customer_email = $customer_email ?? 'noemail@example.com';
                        $customer_phone = $customer_phone ?? 'N/A';
                        $id_number = $id_number ?? 'N/A';
                        $address = $address ?? 'N/A';
                        $notes = $notes ?? '';
                        $nights = max(1, $nights); // Ensure at least 1 night
                        
                        error_log("=== CHECKINS INSERT DEBUG ===");
                        error_log("user_id: $user_id");
                        error_log("booking_id: $booking_id");
                        error_log("customer_name: $customer_name");
                        error_log("customer_email: $customer_email");
                        error_log("customer_phone: $customer_phone");
                        error_log("id_type: $id_type");
                        error_log("id_number: $id_number");
                        error_log("room_name: $room_name");
                        error_log("room_number: $room_number");
                        error_log("nights: $nights");
                        error_log("number_of_guests: $number_of_guests");
                        error_log("rate_per_night: $rate_per_night");
                        error_log("payment_type: $payment_type");
                        error_log("amount_paid: $amount_paid");
                        error_log("balance_due: $balance_due");
                        error_log("confirmation_number: $confirmation_number");
                        error_log("checked_in_by: $checked_in_by");
                        error_log("=== END DEBUG ===");
                        
                        $checkin_insert->bind_param(
                            "iissssssssssssssiidsddsis",
                            $user_id, $booking_id, $hotel_name, $hotel_location,
                            $booking_details['check_in_date'], $booking_details['check_out_date'],
                            $customer_name, $guest_dob, $id_type, $id_number,
                            $nationality, $address, $customer_phone, $customer_email,
                            $room_name, $room_number, $nights, $number_of_guests,
                            $rate_per_night, $payment_type, $amount_paid, $balance_due,
                            $confirmation_number, $notes, $checked_in_by
                        );
                        
                        if (!$checkin_insert->execute()) {
                            throw new Exception("Checkins INSERT failed: " . $checkin_insert->error);
                        }
                        error_log("✅ Checkins record created successfully");
                    } catch (Exception $checkin_error) {
                        // Log the error but don't fail the entire transaction
                        error_log("❌ Checkin record creation failed: " . $checkin_error->getMessage());
                    }
                }
            }
            
            $conn->commit();
            error_log("✅ CHECK-IN SUCCESSFUL - Booking ID: $booking_id, Customer: $customer_name");
            $message = '✅ Check-in Successful! Customer ' . htmlspecialchars($customer_name) . ' has been checked in to room ' . htmlspecialchars($booking_data['room_number']) . '. Room status updated to OCCUPIED.';
            
            // Redirect to same page to prevent form resubmission and show success message
            header("Location: receptionist-checkin.php?success=1&message=" . urlencode($message));
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("❌ CHECK-IN FAILED: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            $error = 'Check-in failed: ' . $e->getMessage();
            
            // Clear any booking data to show search form again
            $booking_data = null;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Check-in - Receptionist Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/print.css">
    <style>
        .navbar-receptionist {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%) !important;
        }
        .navbar-receptionist .navbar-brand {
            color: white !important;
            font-weight: bold;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            margin: 0.25rem 0;
            border-radius: 0.5rem;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }
        .booking-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-receptionist">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-hotel text-warning"></i> 
                <span class="text-white fw-bold">Harar Ras Hotel - Receptionist Dashboard</span>
            </a>
            <div class="ms-auto">
                
                <span class="text-white me-3">
                    <i class="fas fa-user-tie"></i> Receptionist
                </span>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar d-flex flex-column p-3">
                    <h4 class="text-white mb-4">
                        <i class="fas fa-concierge-bell"></i> Reception Panel
                    </h4>
                    
                    <nav class="nav flex-column">
                        <a href="receptionist.php" class="nav-link">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard Overview
                        </a>
                        <a href="verify-id.php" class="nav-link">
                            <i class="fas fa-id-card me-2"></i> Verify ID
                        </a>
                        <a href="receptionist-checkout.php" class="nav-link">
                            <i class="fas fa-minus-circle me-2"></i> Process Check-out
                        </a>
                        <a href="receptionist-rooms.php" class="nav-link">
                            <i class="fas fa-bed me-2"></i> Manage Rooms
                        </a>
                        <a href="../generate_bill.php" class="nav-link">
                            <i class="fas fa-file-invoice-dollar me-2"></i> Generate Bill
                        </a>
                        </nav>
                    
                    <div class="mt-auto">
                        <a href="../logout.php" class="nav-link text-white">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content p-4 single-page-print">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <a href="receptionist.php" class="btn btn-outline-secondary me-3">
                                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                            </a>
                            <h2 class="d-inline"><i class="fas fa-plus-circle me-2"></i> New Check-in</h2>
                        </div>
                    </div>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$booking_data): ?>
                    
                    <!-- Today's Check-in List - APPEARS FIRST -->
                    <?php if ($todays_checkins && $todays_checkins->num_rows > 0): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-calendar-day me-2"></i> Today's Check-ins (<?php echo $todays_checkins->num_rows; ?>)</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                These guests are scheduled to check in today. Click the "Check-in" button to process their arrival.
                            </p>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Booking Ref</th>
                                            <th>Customer Name</th>
                                            <th>Room</th>
                                            <th>Phone</th>
                                            <th>Nights</th>
                                            <th>ID Document</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $todays_checkins->data_seek(0);
                                        while ($checkin = $todays_checkins->fetch_assoc()): 
                                        ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($checkin['booking_reference']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($checkin['guest_name']); ?></td>
                                            <td><?php echo htmlspecialchars($checkin['room_name']); ?> (<?php echo $checkin['room_number']; ?>)</td>
                                            <td><?php echo htmlspecialchars($checkin['phone'] ?? 'N/A'); ?></td>
                                            <td><span class="badge bg-primary"><?php echo $checkin['nights']; ?> nights</span></td>
                                            <td>
                                                <?php if (!empty($checkin['id_image'])): 
                                                    $thumb_url = '../api/serve_id_image.php?booking_id=' . (int)$checkin['id'];
                                                ?>
                                                    <img src="<?php echo htmlspecialchars($thumb_url); ?>"
                                                         alt="Customer ID"
                                                         style="width:60px; height:40px; object-fit:cover; border-radius:4px; cursor:pointer; border:1px solid #dee2e6;"
                                                         onclick="openIdModal('<?php echo htmlspecialchars($thumb_url); ?>')"
                                                         title="Click to view full ID"
                                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                                                    <span style="display:none;" class="badge bg-secondary">No preview</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="fas fa-exclamation-triangle me-1"></i>Not uploaded
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($checkin['status'] == 'checked_in'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check-circle me-1"></i> Checked In
                                                    </span>
                                                <?php elseif ($checkin['verification_status'] == 'verified' || $checkin['payment_status'] == 'paid'): ?>
                                                    <span class="badge bg-info">
                                                        <i class="fas fa-check me-1"></i> Verified
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Modern Search Section -->
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-search me-2"></i> Search Booking for Check-in</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-4">
                                <i class="fas fa-info-circle me-2"></i>
                                Good morning/afternoon! Welcome to Harar Ras Hotel. Please search for the guest's booking using any of the options below.
                            </p>
                            
                            <form method="POST">
                                <input type="hidden" name="action" value="search_booking">
                                
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">Search By:</label>
                                        <select name="search_type" id="searchType" class="form-select form-select-lg" required>
                                            <option value="reference">Booking Reference</option>
                                            <option value="name">Customer Last Name</option>
                                            <option value="phone">Mobile Number</option>
                                        </select>
                                    </div>
                                    <div class="col-md-7">
                                        <label class="form-label fw-bold">Enter Details:</label>
                                        <input type="text" name="search_value" id="searchValue" class="form-control form-control-lg" 
                                               placeholder="Enter booking reference (e.g., HRH20241011)" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" class="btn btn-primary btn-lg w-100">
                                            <i class="fas fa-search me-2"></i> Search
                                        </button>
                                    </div>
                                </div>
                            </form>
                            
                            <div class="alert alert-info">
                                <h6 class="alert-heading"><i class="fas fa-lightbulb me-2"></i> Quick Tips:</h6>
                                <ul class="mb-0 small">
                                    <li>Booking references start with "HRH" followed by date and number</li>
                                    <li>For name search, enter the customer's last name</li>
                                    <li>Phone numbers can be searched with or without country code</li>
                                    <li>Only pending room bookings with verified payments can be checked in</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- ══ ID-VERIFIED BOOKINGS SECTION ══════════════════════════════════ -->
                    <?php if ($id_bookings_count > 0): ?>
                    <div class="card mt-4 border-0 shadow-sm">
                        <div class="card-header text-white d-flex justify-content-between align-items-center"
                             style="background: linear-gradient(135deg, #1e88e5 0%, #1565c0 100%);">
                            <h5 class="mb-0">
                                <i class="fas fa-id-card me-2"></i>
                                Customers with Uploaded ID
                                <span class="badge bg-white text-primary ms-2"><?php echo $id_bookings_count; ?></span>
                            </h5>
                            <small class="opacity-75">Verify identity before issuing room key</small>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:110px;">ID Document</th>
                                            <th>Customer Name</th>
                                            <th>Booking Ref</th>
                                            <th>Room</th>
                                            <th>Check-in</th>
                                            <th>Check-out</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    $id_bookings->data_seek(0);
                                    while ($ib = $id_bookings->fetch_assoc()):
                                        // Use secure DB-based image server with booking_id
                                        $img_url = '../api/serve_id_image.php?booking_id=' . (int)$ib['id'];
                                        // Status badge
                                        $st = strtolower($ib['status']);
                                        $st_class = 'secondary';
                                        $st_label = ucfirst(str_replace('_',' ',$ib['status']));
                                        if ($st === 'pending')   { $st_class = 'warning';  $st_label = 'Pending'; }
                                        if ($st === 'confirmed') { $st_class = 'success';  }
                                        if ($st === 'verified')  { $st_class = 'info';     }
                                        if ($st === 'checked_in'){ $st_class = 'primary';  $st_label = 'Checked In'; }
                                        // Payment badge
                                        $pay = strtolower($ib['payment_status'] ?? 'pending');
                                        $pay_class = ($pay === 'paid') ? 'success' : (($pay === 'refund_pending') ? 'warning' : 'secondary');
                                    ?>
                                    <tr>
                                        <!-- ID Thumbnail -->
                                        <td>
                                            <div style="position:relative; display:inline-block;">
                                                <img src="<?php echo htmlspecialchars($img_url); ?>"
                                                     alt="ID of <?php echo htmlspecialchars($ib['guest_name']); ?>"
                                                     style="width:90px; height:60px; object-fit:cover; border-radius:6px; border:2px solid #1e88e5; cursor:pointer; display:block;"
                                                     onclick="openIdModal('<?php echo htmlspecialchars($img_url); ?>','<?php echo htmlspecialchars($ib['guest_name']); ?>','<?php echo htmlspecialchars($ib['booking_reference']); ?>')"
                                                     title="Click to view full ID"
                                                     onerror="this.parentElement.innerHTML='<span class=\'badge bg-danger\'>Image error</span>';">
                                                <span style="position:absolute; bottom:2px; right:2px; background:rgba(30,136,229,.85); color:#fff; font-size:9px; padding:1px 5px; border-radius:3px;">
                                                    <i class="fas fa-search-plus"></i>
                                                </span>
                                            </div>
                                        </td>
                                        <!-- Customer -->
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($ib['guest_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($ib['email']); ?></small><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($ib['phone'] ?? ''); ?></small>
                                        </td>
                                        <td><span class="badge bg-primary"><?php echo htmlspecialchars($ib['booking_reference']); ?></span></td>
                                        <td>
                                            <div><?php echo htmlspecialchars($ib['room_name']); ?></div>
                                            <small class="text-muted">Room <?php echo htmlspecialchars($ib['room_number']); ?></small>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($ib['check_in_date'])); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($ib['check_out_date'])); ?></td>
                                        <td><strong>ETB <?php echo number_format($ib['total_price'], 2); ?></strong></td>
                                        <td>
                                            <span class="badge bg-<?php echo $st_class; ?>"><?php echo $st_label; ?></span><br>
                                            <span class="badge bg-<?php echo $pay_class; ?> mt-1"><?php echo ucfirst($pay); ?></span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary"
                                                    onclick="openIdModal('<?php echo htmlspecialchars($img_url); ?>','<?php echo htmlspecialchars($ib['guest_name']); ?>','<?php echo htmlspecialchars($ib['booking_reference']); ?>')">
                                                <i class="fas fa-id-card me-1"></i>View ID
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="card mt-4 border-0 shadow-sm">
                        <div class="card-header text-white"
                             style="background: linear-gradient(135deg, #1e88e5 0%, #1565c0 100%);">
                            <h5 class="mb-0">
                                <i class="fas fa-id-card me-2"></i> Customers with Uploaded ID
                            </h5>
                        </div>
                        <div class="card-body text-center py-4 text-muted">
                            <i class="fas fa-id-card fa-3x mb-3 opacity-25"></i>
                            <p class="mb-0">No customers have uploaded their ID yet.<br>
                            <small>IDs appear here as soon as a customer uploads during booking.</small></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <script>
                        document.getElementById('searchType').addEventListener('change', function() {
                            const searchValue = document.getElementById('searchValue');
                            if (this.value === 'reference') {
                                searchValue.placeholder = 'Enter booking reference (e.g., HRH20241011)';
                            } else if (this.value === 'name') {
                                searchValue.placeholder = 'Enter customer last name';
                            } else if (this.value === 'phone') {
                                searchValue.placeholder = 'Enter mobile number';
                            }
                        });
                    </script>
                    
                    <!-- Guests Staying 2+ Days -->
                    <?php if ($staying_guests && $staying_guests->num_rows > 0): ?>
                    <div class="card mt-4">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fas fa-users me-2"></i> Guests Staying 2+ Days (<?php echo $staying_guests->num_rows; ?>)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Booking Ref</th>
                                            <th>Customer Name</th>
                                            <th>Room</th>
                                            <th>Phone</th>
                                            <th>Nights</th>
                                            <th>Check-out</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        while ($guest = $staying_guests->fetch_assoc()): 
                                        ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($guest['booking_reference']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($guest['guest_name']); ?></td>
                                            <td><?php echo htmlspecialchars($guest['room_name']); ?> (<?php echo $guest['room_number']; ?>)</td>
                                            <td><?php echo htmlspecialchars($guest['phone'] ?? 'N/A'); ?></td>
                                            <td><span class="badge bg-info"><?php echo $guest['nights']; ?> nights</span></td>
                                            <td><?php echo date('M j, Y', strtotime($guest['check_out_date'])); ?></td>
                                            <td>
                                                <a href="receptionist-checkout.php" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-sign-out-alt me-1"></i> Checkout
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <!-- Check-in Processing Section -->
                    <div class="row">
                        <div class="col-md-8">
                            <!-- Modern Booking Information Display -->
                            <div class="card mb-4">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-check-circle me-2"></i> Booking Found
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="booking-info">
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <h6 class="text-primary mb-3"><i class="fas fa-user me-2"></i> Customer Information</h6>
                                                        <p class="mb-2"><strong>Name:</strong> <?php echo htmlspecialchars($booking_data['guest_name'] ?? ''); ?></p>
                                                        <p class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($booking_data['email'] ?? ''); ?></p>
                                                        <p class="mb-2"><strong>Phone:</strong> <?php echo htmlspecialchars($booking_data['phone'] ?? 'Not provided'); ?></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <h6 class="text-primary mb-3"><i class="fas fa-bed me-2"></i> Room Details</h6>
                                                        <p class="mb-2"><strong>Room:</strong> <?php echo htmlspecialchars($booking_data['room_name'] ?? ''); ?></p>
                                                        <p class="mb-2"><strong>Room Number:</strong> <?php echo htmlspecialchars($booking_data['room_number'] ?? ''); ?></p>
                                                        <p class="mb-2"><strong>Booking Ref:</strong> <span class="badge bg-primary"><?php echo htmlspecialchars($booking_data['booking_reference'] ?? ''); ?></span></p>
                                                    </div>
                                                </div>

                                                <?php
                                                // Show customer ID image if uploaded
                                                $id_img_path = $booking_data['id_image'] ?? '';
                                                if (!empty($id_img_path)):
                                                    $id_img_url = '../api/serve_id_image.php?booking_id=' . (int)$booking_data['id'];
                                                ?>
                                                <div class="mb-3 p-3 bg-white rounded border">
                                                    <h6 class="text-success mb-2">
                                                        <i class="fas fa-id-card me-2"></i> Customer ID Document
                                                        <span class="badge bg-success ms-2">Uploaded</span>
                                                    </h6>
                                                    <div class="d-flex align-items-start gap-3">
                                                        <img src="<?php echo htmlspecialchars($id_img_url); ?>"
                                                             alt="Customer ID"
                                                             class="rounded border"
                                                             style="width:140px; height:90px; object-fit:cover; cursor:pointer;"
                                                             onclick="openIdModal('<?php echo htmlspecialchars($id_img_url); ?>')"
                                                             title="Click to enlarge"
                                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                                        <div style="display:none;" class="text-muted small">
                                                            <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                                                            Image not accessible. Path: <?php echo htmlspecialchars($id_img_path); ?>
                                                        </div>
                                                        <div>
                                                            <p class="mb-1 small text-muted">
                                                                <i class="fas fa-info-circle me-1"></i>
                                                                ID submitted by customer during booking
                                                            </p>
                                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                                    onclick="openIdModal('<?php echo htmlspecialchars($id_img_url); ?>')">
                                                                <i class="fas fa-search-plus me-1"></i> View Full Size
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php else: ?>
                                                <div class="mb-3 p-3 bg-light rounded border border-warning">
                                                    <h6 class="text-warning mb-1">
                                                        <i class="fas fa-exclamation-triangle me-2"></i> No ID Image on File
                                                    </h6>
                                                    <small class="text-muted">Customer did not upload an ID during booking. Please verify ID manually.</small>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <hr>
                                                
                                                <div class="row">
                                                    <div class="col-md-3">
                                                        <p class="mb-1"><strong>Check-in:</strong></p>
                                                        <p class="text-success fw-bold"><?php echo date('M j, Y', strtotime($booking_data['check_in_date'])); ?></p>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <p class="mb-1"><strong>Check-out:</strong></p>
                                                        <p class="text-danger fw-bold"><?php echo date('M j, Y', strtotime($booking_data['check_out_date'])); ?></p>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <p class="mb-1"><strong>Nights:</strong></p>
                                                        <p class="fw-bold"><?php echo $booking_data['nights']; ?> night(s)</p>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <p class="mb-1"><strong>Rate/Night:</strong></p>
                                                        <p class="fw-bold"><?php echo format_currency($booking_data['price']); ?></p>
                                                    </div>
                                                </div>
                                                
                                                <hr>
                                                
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <p class="mb-1"><strong>Total Amount:</strong></p>
                                                        <h4 class="text-primary mb-0"><?php echo format_currency($booking_data['total_price']); ?></h4>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <p class="mb-1"><strong>Payment Status:</strong></p>
                                                        <?php 
                                                        $payment_status = $booking_data['payment_status'] ?? 'pending';
                                                        $status_class = $payment_status == 'paid' ? 'success' : 'warning';
                                                        $status_icon = $payment_status == 'paid' ? 'check-circle' : 'clock';
                                                        ?>
                                                        <h5><span class="badge bg-<?php echo $status_class; ?>">
                                                            <i class="fas fa-<?php echo $status_icon; ?> me-1"></i>
                                                            <?php echo $payment_status == 'paid' ? '✅ Prepaid' : '⏳ Payment Due'; ?>
                                                        </span></h5>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <p class="mb-1"><strong>Balance Due:</strong></p>
                                                        <h4 class="<?php echo $payment_status == 'paid' ? 'text-success' : 'text-danger'; ?> mb-0">
                                                            <?php echo $payment_status == 'paid' ? format_currency(0) : format_currency($booking_data['total_price']); ?>
                                                        </h4>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <div class="alert alert-warning">
                                                <h6 class="alert-heading"><i class="fas fa-id-card me-2"></i> ID Verification Required</h6>
                                                <p class="small mb-0">Please verify guest identity with a government-issued photo ID before proceeding.</p>
                                            </div>
                                            
                                            <div class="d-grid gap-2">
                                                <button type="button" class="btn btn-outline-primary" onclick="scrollToForm()">
                                                    <i class="fas fa-arrow-down me-2"></i> Proceed to Check-in
                                                </button>
                                                <a href="receptionist-checkin.php" class="btn btn-outline-secondary">
                                                    <i class="fas fa-search me-2"></i> New Search
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <script>
                                function scrollToForm() {
                                    document.querySelector('.card:last-of-type').scrollIntoView({ behavior: 'smooth' });
                                }
                            </script>
                            
                            <!-- Modern Check-in Form -->
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i> Complete Check-in Process</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="process_checkin">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking_data['id']; ?>">
                                        <input type="hidden" name="room_id" value="<?php echo $booking_data['room_id']; ?>">
                                        
                                        <!-- Step 1: Guest Verification -->
                                        <div class="border rounded p-4 mb-4 bg-light">
                                            <h6 class="text-primary mb-3">
                                                <span class="badge bg-primary me-2">Step 1</span>
                                                <i class="fas fa-user-check me-2"></i> Guest Verification & ID Scan
                                            </h6>
                                            <p class="text-muted small mb-3">
                                                <i class="fas fa-info-circle me-1"></i>
                                                "I found your booking, <?php echo explode(' ', $booking_data['guest_name'])[0]; ?>. Could I please see a government-issued photo ID for verification?"
                                            </p>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                                                    <input type="text" name="customer_name" class="form-control" 
                                                           value="<?php echo htmlspecialchars($booking_data['guest_name'] ?? ''); ?>" required>
                                                    <small class="text-muted">Verify name matches ID</small>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label fw-bold">Email <span class="text-danger">*</span></label>
                                                    <input type="email" name="customer_email" class="form-control" 
                                                           value="<?php echo htmlspecialchars($booking_data['email'] ?? ''); ?>" required>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label fw-bold">Phone Number <span class="text-danger">*</span></label>
                                                    <input type="text" name="customer_phone" class="form-control" 
                                                           value="<?php echo htmlspecialchars($booking_data['phone'] ?? ''); ?>" required>
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label fw-bold">ID Type <span class="text-danger">*</span></label>
                                                    <select name="id_type" class="form-select">
                                                        <option value="">Select ID Type</option>
                                                        <option value="passport">Passport</option>
                                                        <option value="national_id">National ID</option>
                                                        <option value="driving_license">Driving License</option>
                                                        <option value="other">Other Government ID</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label fw-bold">ID Number <span class="text-danger">*</span></label>
                                                    <input type="text" name="id_number" class="form-control" 
                                                           placeholder="Enter ID number">
                                                </div>
                                            </div>
                                            
                                            <div class="alert alert-success mb-0">
                                                <i class="fas fa-check-circle me-2"></i>
                                                <strong>Verification Script:</strong> "Perfect, everything matches. I see you've <?php echo ($booking_data['payment_status'] ?? 'pending') == 'paid' ? 'prepaid your stay online' : 'a balance due for your stay'; ?>."
                                            </div>
                                        </div>
                                        
                                        <!-- Step 2: Payment & Incidentals -->
                                        <div class="border rounded p-4 mb-4 bg-light">
                                            <h6 class="text-primary mb-3">
                                                <span class="badge bg-primary me-2">Step 2</span>
                                                <i class="fas fa-money-bill-wave me-2"></i> Payment & Incidental Deposit
                                            </h6>
                                            
                                            <?php if (($booking_data['payment_status'] ?? 'pending') == 'paid'): ?>
                                            <div class="alert alert-info mb-3">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <strong>Prepaid Guest Script:</strong> "Your room is fully paid. For incidentals during your stay (room service, minibar, etc.), I'll need to authorize a deposit on your card."
                                            </div>
                                            <?php else: ?>
                                            <div class="alert alert-warning mb-3">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                <strong>Payment Due Script:</strong> "The total for your <?php echo $booking_data['nights']; ?>-night stay is <?php echo format_currency($booking_data['total_price']); ?>. How would you like to pay today?"
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label fw-bold">Payment Collected (ETB)</label>
                                                    <input type="number" name="payment_collected" class="form-control" 
                                                           step="0.01" value="<?php echo $booking_data['total_price']; ?>" min="0">
                                                    <small class="text-muted">Amount paid at check-in</small>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label fw-bold">Payment Method</label>
                                                    <select name="payment_method" class="form-select">
                                                        <option value="">Select Method</option>
                                                        <option value="cash">💵 Cash</option>
                                                        <option value="card">💳 Credit/Debit Card</option>
                                                        <option value="bank_transfer">🏦 Bank Transfer</option>
                                                        <option value="mobile_money">📱 Mobile Money</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label fw-bold">Incidental Deposit (ETB)</label>
                                                    <input type="number" name="deposit_amount" class="form-control" 
                                                           step="0.01" value="500.00" min="0">
                                                    <small class="text-muted">Recommended: 500-1000 ETB for incidentals</small>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label fw-bold">Deposit Payment Method</label>
                                                    <select name="deposit_payment_method" class="form-select">
                                                        <option value="">Select Method</option>
                                                        <option value="cash">💵 Cash</option>
                                                        <option value="card">💳 Credit/Debit Card (Hold)</option>
                                                        <option value="bank_transfer">🏦 Bank Transfer</option>
                                                        <option value="mobile_money">📱 Mobile Money</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Step 3: Room Key & Digital Options -->
                                        <div class="border rounded p-4 mb-4 bg-light">
                                            <h6 class="text-primary mb-3">
                                                <span class="badge bg-primary me-2">Step 3</span>
                                                <i class="fas fa-key me-2"></i> Room Key & Digital Services
                                            </h6>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label fw-bold">Room Key Number <span class="text-danger">*</span></label>
                                                    <input type="text" name="room_key_number" class="form-control" 
                                                           placeholder="e.g., KEY-<?php echo $booking_data['room_number']; ?>" 
                                                           value="KEY-<?php echo $booking_data['room_number']; ?>" required>
                                                    <small class="text-muted">Physical key card number</small>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label fw-bold">Key Preference</label>
                                                    <select class="form-select">
                                                        <option value="physical">🔑 Physical Key Card</option>
                                                        <option value="mobile">📱 Mobile Key (via App)</option>
                                                        <option value="both">🔑📱 Both Options</option>
                                                    </select>
                                                    <small class="text-muted">Modern contactless options</small>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-12 mb-3">
                                                    <label class="form-label fw-bold">Digital Services</label>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="emailReceipt" checked>
                                                        <label class="form-check-label" for="emailReceipt">
                                                            📧 Send digital receipt to email
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="smsConfirm" checked>
                                                        <label class="form-check-label" for="smsConfirm">
                                                            📱 Send SMS confirmation with room details
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="mobileKey">
                                                        <label class="form-check-label" for="mobileKey">
                                                            🔐 Setup mobile key access
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="alert alert-info mb-0">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <strong>Final Script:</strong> "All set! I've sent your digital receipt to <?php echo htmlspecialchars($booking_data['email'] ?? ''); ?>. Your room is <?php echo $booking_data['room_number'] ?? ''; ?> on the <?php echo floor(($booking_data['room_number'] ?? 0) / 100); ?>nd floor. Breakfast is served from 6:30-10 AM in the main restaurant. Is there anything else I can assist you with?"
                                            </div>
                                        </div>
                                        
                                        <!-- Step 4: Additional Notes -->
                                        <div class="border rounded p-4 mb-4 bg-light">
                                            <h6 class="text-primary mb-3">
                                                <span class="badge bg-primary me-2">Step 4</span>
                                                <i class="fas fa-sticky-note me-2"></i> Check-in Notes & Special Requests
                                            </h6>
                                            
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Check-in Notes</label>
                                                <textarea name="notes" class="form-control" rows="3" 
                                                          placeholder="Any special notes, requests, observations, or guest preferences..."></textarea>
                                                <small class="text-muted">Document any special requests, allergies, or important information</small>
                                            </div>
                                        </div>
                                        
                                        <!-- Action Buttons -->
                                        <div class="d-flex gap-3 justify-content-center">
                                            <button type="submit" class="btn btn-success btn-lg px-5" id="checkinBtn">
                                                <i class="fas fa-check-circle me-2"></i> Complete Check-in
                                            </button>
                                            <a href="receptionist-checkin.php" class="btn btn-secondary btn-lg px-4">
                                                <i class="fas fa-times me-2"></i> Cancel
                                            </a>
                                            <button type="button" class="btn btn-info btn-lg px-4" onclick="window.print()">
                                                <i class="fas fa-print me-2"></i> Print Details
                                            </button>
                                        </div>
                                    </form>
                                    
                                    <script>
                                    // Add form submission handling with loading state
                                    document.querySelector('form').addEventListener('submit', function(e) {
                                        const submitBtn = document.getElementById('checkinBtn');
                                        const originalText = submitBtn.innerHTML;
                                        
                                        // Show loading state
                                        submitBtn.disabled = true;
                                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing Check-in...';
                                        
                                        // Set a timeout to prevent infinite loading
                                        setTimeout(function() {
                                            if (submitBtn.disabled) {
                                                submitBtn.disabled = false;
                                                submitBtn.innerHTML = originalText;
                                                alert('Check-in is taking longer than expected. Please try again or contact IT support.');
                                            }
                                        }, 30000); // 30 second timeout
                                    });
                                    </script>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <!-- Check-in Instructions -->
                            <div class="card mb-3">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0"><i class="fas fa-clipboard-list me-2"></i> Check-in Checklist</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled mb-0">
                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Verify guest identity with ID</li>
                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Confirm booking details</li>
                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Collect payment if due</li>
                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Collect incidental deposit</li>
                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Issue room key</li>
                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Explain hotel facilities</li>
                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Note special requests</li>
                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Provide WiFi password</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <!-- Quick Info -->
                            <div class="card">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i> Important Notes</h6>
                                </div>
                                <div class="card-body">
                                    <p class="small mb-2"><strong>Incidental Deposit:</strong> Recommended 500-1000 ETB for room damages or minibar charges</p>
                                    <p class="small mb-2"><strong>ID Verification:</strong> Always verify customer ID matches booking name</p>
                                    <p class="small mb-0"><strong>Room Key:</strong> Record key number for tracking</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- ID Image Full-Screen Viewer Modal (NO download — view only) -->
    <div id="idViewModal"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.92); z-index:9999;
                align-items:center; justify-content:center; flex-direction:column;">

        <!-- Header bar -->
        <div style="width:100%; max-width:860px; display:flex; justify-content:space-between;
                    align-items:center; padding:12px 16px; color:#fff;">
            <div id="idViewInfo" style="font-size:1rem;">
                <i class="fas fa-id-card me-2" style="color:#f7931e;"></i>
                <span id="idViewGuestName" style="font-weight:600;"></span>
                <span id="idViewRef" style="color:#aaa; margin-left:10px; font-size:.9rem;"></span>
            </div>
            <button onclick="closeIdModal()"
                    style="background:rgba(255,255,255,.15); border:none; color:#fff;
                           width:36px; height:36px; border-radius:50%; font-size:18px;
                           cursor:pointer; display:flex; align-items:center; justify-content:center;">
                &times;
            </button>
        </div>

        <!-- Image container -->
        <div style="flex:1; display:flex; align-items:center; justify-content:center;
                    padding:0 16px; max-width:860px; width:100%;">
            <div style="position:relative; background:#111; border-radius:10px;
                        overflow:hidden; box-shadow:0 8px 40px rgba(0,0,0,.7);
                        max-width:100%; max-height:75vh; display:flex;
                        align-items:center; justify-content:center;">
                <img id="idViewImg" src="" alt="Customer ID Document"
                     style="max-width:100%; max-height:72vh; display:block;
                            object-fit:contain; border-radius:10px;">
                <!-- Loading spinner shown while image loads -->
                <div id="idViewSpinner"
                     style="position:absolute; inset:0; display:flex; align-items:center;
                            justify-content:center; background:#111; border-radius:10px;">
                    <div style="text-align:center; color:#aaa;">
                        <i class="fas fa-spinner fa-spin fa-2x mb-2"></i><br>
                        <small>Loading ID image...</small>
                    </div>
                </div>
                <!-- Error state -->
                <div id="idViewError"
                     style="display:none; position:absolute; inset:0; align-items:center;
                            justify-content:center; background:#1a1a2e; border-radius:10px;
                            flex-direction:column; color:#fff; text-align:center; padding:30px;">
                    <i class="fas fa-exclamation-triangle fa-3x mb-3" style="color:#f7931e;"></i>
                    <h5>Image Not Available</h5>
                    <p class="text-muted small mb-0">The ID image could not be loaded.<br>
                    The file may have been uploaded on a different server instance.</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div style="padding:14px 16px; color:#aaa; font-size:.8rem; text-align:center;">
            <i class="fas fa-shield-alt me-1" style="color:#f7931e;"></i>
            Confidential — For identity verification only. Do not share or distribute.
            &nbsp;&nbsp;
            <button onclick="closeIdModal()" class="btn btn-outline-light btn-sm ms-3">
                <i class="fas fa-times me-1"></i> Close
            </button>
        </div>
    </div>

    <script>
    function openIdModal(src, guestName, bookingRef) {
        const modal   = document.getElementById('idViewModal');
        const img     = document.getElementById('idViewImg');
        const spinner = document.getElementById('idViewSpinner');
        const errDiv  = document.getElementById('idViewError');
        const nameEl  = document.getElementById('idViewGuestName');
        const refEl   = document.getElementById('idViewRef');

        // Set guest info
        nameEl.textContent = guestName || 'Customer ID';
        refEl.textContent  = bookingRef ? ('Ref: ' + bookingRef) : '';

        // Reset: hide image, show spinner, hide error
        img.style.display    = 'none';
        spinner.style.display = 'flex';
        errDiv.style.display  = 'none';
        img.src = '';

        // Show modal first so user sees spinner immediately
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        // Then load image
        const tempImg = new Image();
        tempImg.onload = function() {
            img.src = src;
            spinner.style.display = 'none';
            img.style.display = 'block';
        };
        tempImg.onerror = function() {
            spinner.style.display = 'none';
            errDiv.style.display  = 'flex';
        };
        tempImg.src = src;
    }

    function closeIdModal() {
        document.getElementById('idViewModal').style.display = 'none';
        document.getElementById('idViewImg').src = '';
        document.body.style.overflow = '';
    }

    document.getElementById('idViewModal').addEventListener('click', function(e) {
        if (e.target === this) closeIdModal();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeIdModal();
    });
    </script>
</body>
</html>