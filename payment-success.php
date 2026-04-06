<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

$booking_id = $_GET['booking'] ?? 0;
$tx_ref = $_GET['tx_ref'] ?? '';

if (!$booking_id) {
    header('Location: index.php');
    exit;
}

// Get booking details
$query = "SELECT b.*, 
          COALESCE(r.name, 'Food Order') as room_name,
          COALESCE(r.room_number, 'N/A') as room_number,
          u.email, u.first_name, u.last_name
          FROM bookings b
          LEFT JOIN rooms r ON b.room_id = r.id
          JOIN users u ON b.user_id = u.id
          WHERE b.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .success-animation {
            animation: scaleIn 0.5s ease-out;
        }
        
        @keyframes scaleIn {
            0% {
                transform: scale(0);
                opacity: 0;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        .success-icon {
            font-size: 80px;
            color: #28a745;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow success-animation">
                        <div class="card-body text-center py-5">
                            <div class="success-icon mb-4">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            
                            <h2 class="text-success mb-3">Payment Successful!</h2>
                            <p class="lead mb-4">Your payment has been verified and your booking is confirmed.</p>
                            
                            <div class="alert alert-success" role="alert">
                                <h5 class="alert-heading">Booking Confirmed</h5>
                                <hr>
                                <div class="row text-start">
                                    <div class="col-md-6">
                                        <p><strong>Booking Reference:</strong><br><?php echo $booking['booking_reference']; ?></p>
                                        <p><strong>Room:</strong><br><?php echo $booking['room_name']; ?> (<?php echo $booking['room_number']; ?>)</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Amount Paid:</strong><br><?php echo format_currency($booking['total_price']); ?></p>
                                        <p><strong>Transaction ID:</strong><br><?php echo $tx_ref; ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info" role="alert">
                                <i class="fas fa-info-circle"></i> 
                                A confirmation email has been sent to <strong><?php echo $booking['email']; ?></strong>
                            </div>
                            
                            <div class="d-flex gap-3 justify-content-center mt-4">
                                <a href="my-bookings.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-list"></i> View My Bookings
                                </a>
                                <a href="index.php" class="btn btn-outline-secondary btn-lg">
                                    <i class="fas fa-home"></i> Back to Home
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
