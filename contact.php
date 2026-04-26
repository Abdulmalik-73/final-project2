<?php session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $subject = sanitize_input($_POST['subject']);
    $message = sanitize_input($_POST['message']);
    
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = __('contact.error_fields');
    } elseif (!validate_email($email)) {
        $error = __('contact.error_email');
    } else {
        // Save contact message to database
        $query = "INSERT INTO contact_messages (name, email, phone, subject, message, created_at) 
                  VALUES ('$name', '$email', '$phone', '$subject', '$message', NOW())";
        
        if ($conn->query($query)) {
            $success = 'Thank you for contacting us! We will get back to you soon.';
            
            // Optional: Send email notification to admin
            $admin_email = ADMIN_EMAIL;
            $email_subject = "New Contact Message: " . $subject;
            $email_body = "
                <h3>New Contact Message from Website</h3>
                <p><strong>Name:</strong> $name</p>
                <p><strong>Email:</strong> $email</p>
                <p><strong>Phone:</strong> $phone</p>
                <p><strong>Subject:</strong> $subject</p>
                <p><strong>Message:</strong></p>
                <p>$message</p>
                <p><strong>Submitted:</strong> " . date('Y-m-d H:i:s') . "</p>
            ";
            
            send_email($admin_email, $email_subject, $email_body);
        } else {
            $error = __('contact.error_send');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('contact.title'); ?> - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Page Header -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-auto">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> <?php echo __('contact.back_to_home'); ?>
                    </a>
                </div>
                <div class="col text-center">
                    <h1 class="display-4 fw-bold mb-3"><?php echo __('contact.title'); ?></h1>
                    <p class="lead text-muted"><?php echo __('contact.subtitle'); ?></p>
                </div>
                <div class="col-auto">
                    <!-- Spacer for centering -->
                </div>
            </div>
        </div>
    </section>

    <!-- How to Use Guide -->
    <section class="py-5" style="background: linear-gradient(135deg, #f8f6f0 0%, #fff9ee 100%);">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="text-center mb-4">
                        <span class="badge px-3 py-2 mb-3" style="background:#c9a84c; font-size:0.85rem; letter-spacing:1px;">HOW IT WORKS</span>
                        <h2 class="fw-bold" style="color:#1a1a2e;">How to Use the Harar Ras Hotel System</h2>
                    </div>
                    <div class="row g-4">
                        <!-- Paragraph 1 -->
                        <div class="col-md-6">
                            <div class="card h-100 border-0 shadow-sm p-1" style="border-left: 4px solid #c9a84c !important; border-radius: 12px;">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0"
                                             style="width:48px; height:48px; background:#c9a84c;">
                                            <i class="fas fa-user-plus text-white fs-5"></i>
                                        </div>
                                        <h5 class="fw-bold mb-0" style="color:#1a1a2e;">Creating Your Account &amp; Booking a Room</h5>
                                    </div>
                                    <p class="text-muted mb-0" style="line-height:1.8; font-size:0.97rem;">
                                        To get started, visit the hotel website and click the <strong>"Register"</strong> button at the top of the page. You will be asked to enter your full name, email address, phone number, and a password of your choice. Once you fill in all the details and click <strong>"Create Account"</strong>, your account is ready. After that, click <strong>"Login"</strong> and enter your email and password to sign in. To book a room, go to the <strong>"Rooms"</strong> section, browse the available rooms, choose the one you like, select your check-in and check-out dates, then click <strong>"Book Now"</strong>. You will then be asked to make a payment using TeleBirr, CBE, Abyssinia Bank, or Cooperative Bank. After sending the payment, upload a screenshot of your receipt on the website so hotel staff can confirm it. Once confirmed, your booking is complete and you will receive a confirmation message.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <!-- Paragraph 2 -->
                        <div class="col-md-6">
                            <div class="card h-100 border-0 shadow-sm p-1" style="border-left: 4px solid #c9a84c !important; border-radius: 12px;">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0"
                                             style="width:48px; height:48px; background:#c9a84c;">
                                            <i class="fas fa-concierge-bell text-white fs-5"></i>
                                        </div>
                                        <h5 class="fw-bold mb-0" style="color:#1a1a2e;">Using Other Services &amp; Managing Your Account</h5>
                                    </div>
                                    <p class="text-muted mb-0" style="line-height:1.8; font-size:0.97rem;">
                                        Besides booking rooms, you can also order food, book a spa session, or request laundry service — all from the same website after you log in. Simply go to the <strong>"Services"</strong> section, choose what you need, and follow the same steps as booking a room. To see all your bookings and orders, go to <strong>"My Bookings"</strong> in your account menu. If you want to update your name, phone number, or password, click on your profile at the top right corner and go to <strong>"Settings"</strong>. Your account keeps a full record of everything you have booked, paid, and ordered. If you ever forget your password, click <strong>"Forgot Password"</strong> on the login page and a reset link will be sent to your email. For any help, the hotel team is always ready to assist you.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <!-- Contact Form -->
                <div class="col-lg-7 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="mb-4"><?php echo __('contact.send_message'); ?></h3>
                            
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success"><?php echo $success; ?></div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><?php echo __('contact.your_name'); ?> *</label>
                                        <input type="text" name="name" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><?php echo __('contact.email_address'); ?> *</label>
                                        <input type="email" name="email" class="form-control" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><?php echo __('contact.phone_number'); ?></label>
                                        <input type="tel" name="phone" class="form-control">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><?php echo __('contact.subject'); ?> *</label>
                                        <input type="text" name="subject" class="form-control" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><?php echo __('contact.message'); ?> *</label>
                                    <textarea name="message" class="form-control" rows="5" required></textarea>
                                </div>
                                <button type="submit" class="btn btn-gold">
                                    <i class="fas fa-paper-plane"></i> <?php echo __('contact.send_btn'); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                


                <!-- Contact Information -->
                <div class="col-lg-5">
                    <div class="card shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h4 class="mb-4"><?php echo __('contact.contact_info'); ?></h4>
                            
                            <div class="mb-4">
                                <div class="d-flex mb-3">
                                    <div class="text-gold me-3">
                                        <i class="fas fa-map-marker-alt fa-lg"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1"><?php echo __('contact.address'); ?></h6>
                                        <p class="text-muted mb-0">Harar, Ethiopia<br>Near Jugol Walls</p>
                                    </div>
                                </div>
                                
                                <div class="d-flex mb-3">
                                    <div class="text-gold me-3">
                                        <i class="fas fa-phone fa-lg"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1"><?php echo __('contact.phone'); ?></h6>
                                        <p class="text-muted mb-0">+251 25 666 1234</p>
                                    </div>
                                </div>
                                
                                <div class="d-flex mb-3">
                                    <div class="text-gold me-3">
                                        <i class="fas fa-envelope fa-lg"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1"><?php echo __('contact.email'); ?></h6>
                                        <p class="text-muted mb-0">info@hararrashotel.com</p>
                                    </div>
                                </div>
                                
                                <div class="d-flex">
                                    <div class="text-gold me-3">
                                        <i class="fas fa-clock fa-lg"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1"><?php echo __('contact.reception_hours'); ?></h6>
                                        <p class="text-muted mb-0"><?php echo __('contact.available_24_7'); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            


                            <h6 class="mb-3"><?php echo __('contact.follow_us'); ?></h6>
                            <div class="social-links">
                                <a href="#" class="me-2"><i class="fab fa-facebook-f"></i></a>
                                <a href="#" class="me-2"><i class="fab fa-twitter"></i></a>
                                <a href="#" class="me-2"><i class="fab fa-instagram"></i></a>
                                <a href="#"><i class="fab fa-linkedin-in"></i></a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card shadow-sm">
                        <div class="card-body p-4">
                            <h5 class="mb-3"><?php echo __('contact.quick_booking'); ?></h5>
                            <p class="text-muted"><?php echo __('contact.ready_to_book'); ?></p>
                            <a href="booking.php" class="btn btn-gold w-100">
                                <i class="fas fa-calendar-check"></i> Book Now
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Map Section (Optional) -->
    <section class="py-5 bg-light">
        <div class="container">
            <h3 class="text-center mb-4"><?php echo __('contact.find_us'); ?></h3>
            <div class="ratio ratio-21x9">
                <iframe 
                    src="https://maps.google.com/maps?q=9.31130,42.11565&z=17&output=embed&hl=en" 
                    style="border:0;" 
                    allowfullscreen="" 
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
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
    </script>
</body>
</html>
