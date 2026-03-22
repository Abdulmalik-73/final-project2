<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

$error = '';
$success = '';
$booking_data = null;

// Get booking reference from URL
$booking_ref = isset($_GET['booking_ref']) ? sanitize_input($_GET['booking_ref']) : '';
$payment_id = isset($_GET['payment_id']) ? sanitize_input($_GET['payment_id']) : '';

if (empty($booking_ref)) {
    header('Location: index.php');
    exit();
}

// Get booking details and verify payment success - works for both room and food orders
$booking_query = "SELECT b.*, 
                  COALESCE(r.name, 'Food Order') as room_name, 
                  COALESCE(r.room_number, 'N/A') as room_number, 
                  CONCAT(u.first_name, ' ', u.last_name) as customer_name, 
                  u.id as customer_id
                  FROM bookings b 
                  LEFT JOIN rooms r ON b.room_id = r.id 
                  LEFT JOIN users u ON b.user_id = u.id 
                  WHERE b.booking_reference = ? AND b.payment_status = 'paid'";
$stmt = $conn->prepare($booking_query);
$stmt->bind_param("s", $booking_ref);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: index.php');
    exit();
}

$booking_data = $result->fetch_assoc();

// Check if feedback already submitted for this booking
$feedback_check = "SELECT id FROM customer_feedback WHERE booking_id = ?";
$stmt = $conn->prepare($feedback_check);
$stmt->bind_param("i", $booking_data['id']);
$stmt->execute();
$existing_feedback = $stmt->get_result();

if ($existing_feedback->num_rows > 0) {
    // Feedback already submitted, redirect to confirmation
    header("Location: booking-confirmation.php?booking_ref=" . urlencode($booking_ref));
    exit();
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'submit_feedback') {
            $overall_rating = (int)$_POST['overall_rating'];
            $quality_rating = (int)$_POST['quality_rating'];
            $value_rating = (int)$_POST['value_rating'];
            $comments = sanitize_input($_POST['comments']);
            
            // Validate ratings (must be between 1 and 5)
            if ($overall_rating < 1 || $overall_rating > 5) {
                $error = 'Overall rating must be between 1 and 5 stars';
            } elseif ($quality_rating < 1 || $quality_rating > 5) {
                $error = 'Quality rating must be between 1 and 5 stars';
            } elseif ($value_rating < 1 || $value_rating > 5) {
                $error = 'Cleanliness rating must be between 1 and 5 stars';
            } else {
                // Insert feedback
                $insert_query = "INSERT INTO customer_feedback 
                                (booking_id, customer_id, payment_id, overall_rating, service_quality, 
                                 cleanliness, comments, booking_type, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($insert_query);
                $booking_type = $booking_data['booking_type'];
                
                // Map ratings to correct columns
                $stmt->bind_param("iisiiiis", 
                    $booking_data['id'], 
                    $booking_data['customer_id'], 
                    $payment_id, 
                    $overall_rating, 
                    $quality_rating, 
                    $value_rating, 
                    $comments,
                    $booking_type
                );
                
                if ($stmt->execute()) {
                    $success = 'Thank you for your valuable feedback!';
                    
                    // Log the feedback submission
                    error_log("Feedback submitted - Booking: {$booking_data['booking_reference']}, Overall: $overall_rating, Quality: $quality_rating, Cleanliness: $value_rating");
                    
                    // Redirect to confirmation after 2 seconds
                    header("refresh:2;url=booking-confirmation.php?booking_ref=" . urlencode($booking_ref));
                } else {
                    $error = 'Failed to submit feedback. Please try again.';
                    error_log("Feedback submission failed: " . $conn->error);
                }
            }
        } elseif ($_POST['action'] == 'skip_feedback') {
            // Skip feedback and go to confirmation
            header("Location: booking-confirmation.php?booking_ref=" . urlencode($booking_ref));
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Feedback - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .feedback-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .feedback-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 700px;
            width: 100%;
            animation: slideUp 0.6s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .feedback-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .feedback-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin: 0 0 15px 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .feedback-message {
            background: rgba(255,255,255,0.2);
            padding: 20px;
            border-radius: 15px;
            margin: 20px 0;
            backdrop-filter: blur(10px);
        }
        
        .feedback-message h4 {
            margin: 0 0 10px 0;
            font-size: 1.3rem;
        }
        
        .feedback-message p {
            margin: 0;
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .form-section {
            padding: 40px;
        }
        
        .rating-group {
            margin-bottom: 30px;
        }
        
        .rating-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .rating-label i {
            color: #667eea;
            width: 20px;
        }
        
        .star-rating {
            display: flex;
            gap: 5px;
            margin-bottom: 10px;
        }
        
        .star {
            font-size: 2rem;
            color: #ddd;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .star:hover,
        .star.active {
            color: #ffc107;
            transform: scale(1.1);
        }
        
        .rating-text {
            font-size: 0.9rem;
            color: #666;
            margin-top: 5px;
        }
        
        .form-control {
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            padding: 15px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 15px 40px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            border: none;
            padding: 15px 40px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .booking-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .booking-info h5 {
            color: #667eea;
            margin-bottom: 15px;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
            margin: 20px 0;
        }
        
        .success-message {
            text-align: center;
            padding: 40px;
        }
        
        .success-message i {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .feedback-header {
                padding: 20px;
            }
            
            .feedback-header h1 {
                font-size: 1.8rem;
            }
            
            .form-section {
                padding: 20px;
            }
            
            .star {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="feedback-container">
        <div class="feedback-card">
            <div class="feedback-header">
                <h1><i class="fas fa-star me-3"></i>Customer Feedback</h1>
                <div class="feedback-message">
                    <h4>Give us your ideas</h4>
                    <p>Your ideas support and help us improve our service.</p>
                </div>
            </div>
            
            <div class="form-section">
                <?php if ($success): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <h3><?php echo $success; ?></h3>
                        <p>Redirecting to your booking confirmation...</p>
                    </div>
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="booking-info">
                        <h5><i class="fas fa-info-circle me-2"></i><?php echo $booking_data['booking_type'] === 'food_order' ? 'Your Food Order Details' : 'Your Booking Details'; ?></h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong><?php echo $booking_data['booking_type'] === 'food_order' ? 'Order Reference:' : 'Booking Reference:'; ?></strong> <?php echo htmlspecialchars($booking_data['booking_reference']); ?></p>
                                <p><strong>Guest Name:</strong> <?php echo htmlspecialchars($booking_data['customer_name']); ?></p>
                                <?php if ($booking_data['booking_type'] !== 'food_order'): ?>
                                <p><strong>Room:</strong> <?php echo htmlspecialchars($booking_data['room_name']); ?> (<?php echo htmlspecialchars($booking_data['room_number']); ?>)</p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <?php if ($booking_data['booking_type'] === 'food_order'): ?>
                                <p><strong>Order Type:</strong> Food Order</p>
                                <p><strong>Order Date:</strong> <?php echo date('M j, Y', strtotime($booking_data['created_at'])); ?></p>
                                <?php else: ?>
                                <p><strong>Check-in:</strong> <?php echo date('M j, Y', strtotime($booking_data['check_in_date'])); ?></p>
                                <p><strong>Check-out:</strong> <?php echo date('M j, Y', strtotime($booking_data['check_out_date'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" action="">
                        <div class="rating-group">
                            <div class="rating-label">
                                <i class="fas fa-heart"></i>
                                Overall Experience
                            </div>
                            <div class="star-rating" data-rating="overall_rating">
                                <span class="star" data-value="1">★</span>
                                <span class="star" data-value="2">★</span>
                                <span class="star" data-value="3">★</span>
                                <span class="star" data-value="4">★</span>
                                <span class="star" data-value="5">★</span>
                            </div>
                            <div class="rating-text" id="overall_text">Click to rate your overall experience</div>
                            <input type="hidden" name="overall_rating" id="overall_rating" required>
                        </div>
                        
                        <div class="rating-group">
                            <div class="rating-label">
                                <i class="fas fa-concierge-bell"></i>
                                <?php echo $booking_data['booking_type'] === 'food_order' ? 'Food Quality & Taste' : 'Service Quality'; ?>
                            </div>
                            <div class="star-rating" data-rating="quality_rating">
                                <span class="star" data-value="1">★</span>
                                <span class="star" data-value="2">★</span>
                                <span class="star" data-value="3">★</span>
                                <span class="star" data-value="4">★</span>
                                <span class="star" data-value="5">★</span>
                            </div>
                            <div class="rating-text" id="quality_text">Rate the <?php echo $booking_data['booking_type'] === 'food_order' ? 'food quality' : 'service quality'; ?></div>
                            <input type="hidden" name="quality_rating" id="quality_rating" required>
                        </div>
                        
                        <div class="rating-group">
                            <div class="rating-label">
                                <i class="fas fa-broom"></i>
                                <?php echo $booking_data['booking_type'] === 'food_order' ? 'Cleanliness & Presentation' : 'Cleanliness'; ?>
                            </div>
                            <div class="star-rating" data-rating="value_rating">
                                <span class="star" data-value="1">★</span>
                                <span class="star" data-value="2">★</span>
                                <span class="star" data-value="3">★</span>
                                <span class="star" data-value="4">★</span>
                                <span class="star" data-value="5">★</span>
                            </div>
                            <div class="rating-text" id="value_text">Rate the <?php echo $booking_data['booking_type'] === 'food_order' ? 'cleanliness and presentation' : 'cleanliness'; ?></div>
                            <input type="hidden" name="value_rating" id="value_rating" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="rating-label">
                                <i class="fas fa-comment"></i>
                                Comments & Suggestions (Optional)
                            </label>
                            <textarea name="comments" class="form-control" rows="4" 
                                      placeholder="Share your thoughts, suggestions, or ideas to help us improve..."></textarea>
                        </div>
                        
                        <div class="d-flex gap-3 justify-content-center">
                            <button type="submit" name="action" value="submit_feedback" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Submit Feedback
                            </button>
                            <button type="submit" name="action" value="skip_feedback" class="btn btn-secondary">
                                <i class="fas fa-forward me-2"></i>Skip
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/star-rating.js?v=<?php echo time(); ?>"></script>
    <script>
        // Form validation before submit
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const action = e.submitter?.value;
            
            if (action === 'submit_feedback') {
                const overallRating = parseInt(document.getElementById('overall_rating').value);
                const qualityRating = parseInt(document.getElementById('quality_rating').value);
                const valueRating = parseInt(document.getElementById('value_rating').value);
                
                if (!overallRating || overallRating < 1 || overallRating > 5) {
                    e.preventDefault();
                    alert('Please rate your overall experience (1-5 stars)');
                    return false;
                }
                
                if (!qualityRating || qualityRating < 1 || qualityRating > 5) {
                    e.preventDefault();
                    alert('Please rate the service/food quality (1-5 stars)');
                    return false;
                }
                
                if (!valueRating || valueRating < 1 || valueRating > 5) {
                    e.preventDefault();
                    alert('Please rate the cleanliness (1-5 stars)');
                    return false;
                }
            }
            
            return true;
        });
    </script>
</body>
</html>