<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Redirect if not logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

// Get booking ID from URL
$booking_id = isset($_GET['booking']) ? (int)$_GET['booking'] : 0;

if (!$booking_id) {
    header('Location: dashboard.php');
    exit();
}

// Fetch booking details
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT 
        b.id,
        b.booking_reference,
        b.total_price,
        b.status,
        b.payment_status,
        b.booking_type,
        b.check_in_date,
        b.check_out_date,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        r.name as room_name,
        r.room_type
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    LEFT JOIN rooms r ON b.room_id = r.id
    WHERE b.id = ? AND b.user_id = ?
");
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: dashboard.php');
    exit();
}

$booking = $result->fetch_assoc();

// Check if already paid
if ($booking['payment_status'] === 'paid') {
    $success_message = 'This booking has already been paid.';
}

// Get user's phone number
$user_phone = $booking['phone'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>M-Pesa Payment - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .payment-card {
            max-width: 600px;
            margin: 50px auto;
        }
        .mpesa-logo {
            width: 150px;
            margin: 20px auto;
            display: block;
        }
        .status-pending { color: #ffc107; }
        .status-processing { color: #17a2b8; }
        .status-completed { color: #28a745; }
        .status-failed { color: #dc3545; }
        .spinner-border-sm { width: 1rem; height: 1rem; }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container">
        <div class="payment-card">
            <div class="card shadow">
                <div class="card-header bg-success text-white text-center">
                    <h4><i class="fas fa-mobile-alt"></i> M-Pesa Payment</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                        </div>
                        <div class="text-center">
                            <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                        </div>
                    <?php else: ?>
                        <!-- Booking Details -->
                        <div class="mb-4">
                            <h5 class="border-bottom pb-2">Booking Details</h5>
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Booking Reference:</strong></td>
                                    <td><?php echo htmlspecialchars($booking['booking_reference']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Type:</strong></td>
                                    <td><?php echo ucfirst($booking['booking_type']); ?></td>
                                </tr>
                                <?php if ($booking['room_name']): ?>
                                <tr>
                                    <td><strong>Room:</strong></td>
                                    <td><?php echo htmlspecialchars($booking['room_name']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td><strong>Amount:</strong></td>
                                    <td class="fs-5 text-success"><strong>ETB <?php echo number_format($booking['total_price'], 2); ?></strong></td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Payment Form -->
                        <div id="paymentForm">
                            <h5 class="border-bottom pb-2">Payment Information</h5>
                            <form id="mpesaPaymentForm">
                                <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                                <input type="hidden" name="amount" value="<?php echo $booking['total_price']; ?>">
                                
                                <div class="mb-3">
                                    <label for="phone_number" class="form-label">
                                        <i class="fas fa-phone"></i> M-Pesa Phone Number
                                    </label>
                                    <input type="tel" 
                                           class="form-control" 
                                           id="phone_number" 
                                           name="phone_number" 
                                           value="<?php echo htmlspecialchars($user_phone); ?>"
                                           placeholder="0973409026 or 251973409026"
                                           required>
                                    <small class="form-text text-muted">
                                        Enter your Safaricom Ethiopia M-Pesa number
                                    </small>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> 
                                    <strong>How it works:</strong>
                                    <ol class="mb-0 mt-2">
                                        <li>Click "Pay with M-Pesa" button below</li>
                                        <li>You'll receive a payment request on your phone</li>
                                        <li>Enter your M-Pesa PIN to complete payment</li>
                                        <li>Payment confirmation will appear automatically</li>
                                    </ol>
                                </div>
                                
                                <div id="errorMessage" class="alert alert-danger d-none"></div>
                                <div id="successMessage" class="alert alert-success d-none"></div>
                                
                                <button type="submit" class="btn btn-success btn-lg w-100" id="payButton">
                                    <i class="fas fa-mobile-alt"></i> Pay ETB <?php echo number_format($booking['total_price'], 2); ?> with M-Pesa
                                </button>
                            </form>
                        </div>
                        
                        <!-- Payment Status -->
                        <div id="paymentStatus" class="d-none mt-4">
                            <h5 class="border-bottom pb-2">Payment Status</h5>
                            <div class="text-center p-4">
                                <div class="spinner-border text-primary mb-3" role="status" id="statusSpinner">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p id="statusMessage" class="mb-0">Waiting for payment confirmation...</p>
                                <small id="statusDetails" class="text-muted"></small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let transactionId = null;
        let statusCheckInterval = null;
        
        document.getElementById('mpesaPaymentForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const payButton = document.getElementById('payButton');
            const errorMessage = document.getElementById('errorMessage');
            const successMessage = document.getElementById('successMessage');
            
            // Disable button and show loading
            payButton.disabled = true;
            payButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
            errorMessage.classList.add('d-none');
            successMessage.classList.add('d-none');
            
            try {
                const formData = new FormData(this);
                const data = {
                    booking_id: formData.get('booking_id'),
                    phone_number: formData.get('phone_number'),
                    amount: formData.get('amount')
                };
                
                const response = await fetch('api/mpesa/initiate_payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    transactionId = result.data.transaction_id;
                    
                    // Show success message
                    successMessage.textContent = result.data.customer_message;
                    successMessage.classList.remove('d-none');
                    
                    // Hide form and show status
                    document.getElementById('paymentForm').classList.add('d-none');
                    document.getElementById('paymentStatus').classList.remove('d-none');
                    
                    // Start checking payment status
                    startStatusCheck();
                } else {
                    throw new Error(result.error || 'Payment initiation failed');
                }
            } catch (error) {
                errorMessage.textContent = error.message;
                errorMessage.classList.remove('d-none');
                
                // Re-enable button
                payButton.disabled = false;
                payButton.innerHTML = '<i class="fas fa-mobile-alt"></i> Pay ETB <?php echo number_format($booking['total_price'], 2); ?> with M-Pesa';
            }
        });
        
        function startStatusCheck() {
            // Check status every 3 seconds
            statusCheckInterval = setInterval(checkPaymentStatus, 3000);
            
            // Stop checking after 5 minutes
            setTimeout(() => {
                if (statusCheckInterval) {
                    clearInterval(statusCheckInterval);
                    document.getElementById('statusMessage').textContent = 'Payment verification timed out. Please check your booking status.';
                    document.getElementById('statusSpinner').classList.add('d-none');
                }
            }, 300000); // 5 minutes
        }
        
        async function checkPaymentStatus() {
            if (!transactionId) return;
            
            try {
                const response = await fetch(`api/mpesa/check_status.php?transaction_id=${transactionId}`);
                const result = await response.json();
                
                if (result.success) {
                    const data = result.data;
                    const statusMessage = document.getElementById('statusMessage');
                    const statusDetails = document.getElementById('statusDetails');
                    const statusSpinner = document.getElementById('statusSpinner');
                    
                    statusMessage.textContent = data.status_message;
                    statusDetails.textContent = `Status: ${data.status}`;
                    
                    // Update status color
                    statusMessage.className = `mb-0 status-${data.status}`;
                    
                    // If completed or failed, stop checking
                    if (data.status === 'completed') {
                        clearInterval(statusCheckInterval);
                        statusSpinner.classList.add('d-none');
                        statusMessage.innerHTML = '<i class="fas fa-check-circle text-success"></i> ' + data.status_message;
                        
                        // Show receipt number
                        if (data.mpesa_receipt) {
                            statusDetails.textContent = `M-Pesa Receipt: ${data.mpesa_receipt}`;
                        }
                        
                        // Redirect to dashboard after 3 seconds
                        setTimeout(() => {
                            window.location.href = 'dashboard.php';
                        }, 3000);
                    } else if (data.status === 'failed' || data.status === 'cancelled' || data.status === 'timeout') {
                        clearInterval(statusCheckInterval);
                        statusSpinner.classList.add('d-none');
                        statusMessage.innerHTML = '<i class="fas fa-times-circle text-danger"></i> ' + data.status_message;
                        
                        // Show retry button
                        setTimeout(() => {
                            location.reload();
                        }, 5000);
                    }
                }
            } catch (error) {
                console.error('Status check error:', error);
            }
        }
    </script>
</body>
</html>
