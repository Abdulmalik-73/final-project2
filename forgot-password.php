<?php
// Suppress deprecation warnings in production
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

$message = '';
$error = '';
$step = 'email'; // email, sent, reset

// Handle email submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'send_reset') {
    $email = sanitize_input($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Please enter your email address';
    } elseif (!validate_email($email)) {
        $error = 'Please enter a valid email address';
    } else {
        // Check if user exists
        $query = "SELECT id, first_name, email FROM users WHERE email = ? AND status = 'active'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            // Store token in database
            $insert_query = "INSERT INTO password_resets (user_id, email, token, expires_at) 
                            VALUES (?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE 
                            token = VALUES(token), 
                            expires_at = VALUES(expires_at), 
                            created_at = NOW()";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("isss", $user['id'], $email, $token, $expires);
            
            if ($insert_stmt->execute()) {
                // Send reset email
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=" . $token;
                
                $subject = "Password Reset Request - Harar Ras Hotel";
                $message_body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                        .button { background: #667eea; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
                        .footer { text-align: center; padding: 20px; color: #666; font-size: 14px; }
                        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>Password Reset Request</h1>
                        </div>
                        <div class='content'>
                            <p>Hello " . htmlspecialchars($user['first_name']) . ",</p>
                            
                            <p>We received a request to reset your password for your Harar Ras Hotel account.</p>
                            
                            <p>Click the button below to reset your password:</p>
                            
                            <p style='text-align: center;'>
                                <a href='" . $reset_link . "' class='button'>Reset Password</a>
                            </p>
                            
                            <p>Or copy and paste this link into your browser:</p>
                            <p style='word-break: break-all; background: white; padding: 10px; border-radius: 5px;'>" . $reset_link . "</p>
                            
                            <div class='warning'>
                                <strong>⚠️ Important:</strong> This link will expire in 15 minutes for security reasons.
                            </div>
                            
                            <p><strong>If you didn't request this password reset, please ignore this email.</strong> Your password will remain unchanged.</p>
                            
                            <p>For security reasons:</p>
                            <ul>
                                <li>Never share your password with anyone</li>
                                <li>Use a strong, unique password</li>
                                <li>Don't use the same password across multiple sites</li>
                            </ul>
                            
                            <p>Best regards,<br>
                            <strong>Harar Ras Hotel Team</strong></p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated message. Please do not reply to this email.</p>
                            <p>&copy; " . date('Y') . " Harar Ras Hotel. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                $headers = "From: Harar Ras Hotel <noreply@hararrashotel.com>\r\n";
                $headers .= "Reply-To: support@hararrashotel.com\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                
                if (mail($email, $subject, $message_body, $headers)) {
                    $step = 'sent';
                    $_SESSION['reset_email'] = $email;
                } else {
                    $error = 'Failed to send reset email. Please try again or contact support.';
                }
            } else {
                $error = 'An error occurred. Please try again.';
            }
        } else {
            // For security, don't reveal if email exists or not
            $step = 'sent';
            $_SESSION['reset_email'] = $email;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .forgot-password-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 480px;
            width: 100%;
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .card-header i {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .card-header h2 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }
        
        .card-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 14px;
        }
        
        .card-body {
            padding: 40px 30px;
        }
        
        .alert {
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 25px;
            border: none;
            display: flex;
            align-items: flex-start;
        }
        
        .alert i {
            margin-right: 12px;
            font-size: 20px;
            margin-top: 2px;
        }
        
        .alert-danger {
            background: #fed7d7;
            color: #c53030;
        }
        
        .alert-success {
            background: #c6f6d5;
            color: #2f855a;
        }
        
        .alert-info {
            background: #bee3f8;
            color: #2c5282;
        }
        
        .form-label {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            color: #764ba2;
            transform: translateX(-3px);
        }
        
        .back-link i {
            margin-right: 8px;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: #c6f6d5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .success-icon i {
            font-size: 40px;
            color: #2f855a;
        }
        
        .info-box {
            background: #f7fafc;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .info-box p {
            margin: 0;
            font-size: 14px;
            color: #4a5568;
        }
    </style>
</head>
<body>
    <div class="forgot-password-card">
        <?php if ($step == 'email'): ?>
            <div class="card-header">
                <i class="fas fa-lock"></i>
                <h2>Forgot Password?</h2>
                <p>No worries, we'll send you reset instructions</p>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <div><?php echo $error; ?></div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="send_reset">
                    
                    <div class="mb-4">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" 
                               placeholder="Enter your email address" required autofocus>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i> Send Reset Link
                    </button>
                </form>
                
                <div class="text-center">
                    <a href="login.php" class="back-link">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </div>
                
                <div class="info-box">
                    <p><i class="fas fa-info-circle me-2"></i> Enter the email address associated with your account and we'll send you a link to reset your password.</p>
                </div>
            </div>
        <?php elseif ($step == 'sent'): ?>
            <div class="card-header">
                <i class="fas fa-envelope-open-text"></i>
                <h2>Check Your Email</h2>
                <p>We've sent you a password reset link</p>
            </div>
            <div class="card-body">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>Email sent successfully!</strong><br>
                        We've sent a password reset link to <strong><?php echo htmlspecialchars($_SESSION['reset_email'] ?? ''); ?></strong>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-clock"></i>
                    <div>
                        <strong>What's next?</strong><br>
                        1. Check your email inbox (and spam folder)<br>
                        2. Click the reset link in the email<br>
                        3. Create your new password<br>
                        <br>
                        <small>The link will expire in 15 minutes for security.</small>
                    </div>
                </div>
                
                <div class="text-center">
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-2"></i> Return to Login
                    </a>
                </div>
                
                <div class="text-center mt-3">
                    <p class="mb-0" style="font-size: 14px; color: #718096;">
                        Didn't receive the email? 
                        <a href="forgot-password.php" style="color: #667eea; text-decoration: none; font-weight: 600;">Try again</a>
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
