<?php
// Suppress deprecation warnings in production
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

$message = '';
$error = '';
$token = $_GET['token'] ?? '';
$valid_token = false;
$user_data = null;

// Validate token
if (!empty($token)) {
    $query = "SELECT pr.*, u.id as user_id, u.first_name, u.email 
              FROM password_resets pr
              JOIN users u ON pr.user_id = u.id
              WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $valid_token = true;
        $user_data = $result->fetch_assoc();
    } else {
        $error = 'This password reset link is invalid or has expired. Please request a new one.';
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'reset_password') {
    $token = sanitize_input($_POST['token'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all fields';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $error = 'Password must contain at least one uppercase letter';
    } elseif (!preg_match('/[a-z]/', $new_password)) {
        $error = 'Password must contain at least one lowercase letter';
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $error = 'Password must contain at least one number';
    } else {
        // Verify token again
        $query = "SELECT pr.*, u.id as user_id 
                  FROM password_resets pr
                  JOIN users u ON pr.user_id = u.id
                  WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = 0";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $reset_data = $result->fetch_assoc();
            
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password
            $update_query = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("si", $hashed_password, $reset_data['user_id']);
            
            if ($update_stmt->execute()) {
                // Mark token as used
                $mark_used = "UPDATE password_resets SET used = 1 WHERE token = ?";
                $mark_stmt = $conn->prepare($mark_used);
                $mark_stmt->bind_param("s", $token);
                $mark_stmt->execute();
                
                // Set success message
                $_SESSION['password_reset_success'] = true;
                header('Location: login.php?reset=success');
                exit();
            } else {
                $error = 'Failed to update password. Please try again.';
            }
        } else {
            $error = 'This password reset link is invalid or has expired.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Harar Ras Hotel</title>
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
        
        .reset-password-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
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
        
        .form-label {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 50px 14px 16px;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #a0aec0;
            transition: color 0.3s ease;
            font-size: 18px;
        }
        
        .password-toggle:hover {
            color: #667eea;
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
        
        .password-requirements {
            background: #f7fafc;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .password-requirements h6 {
            font-size: 13px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
        }
        
        .password-requirements ul {
            margin: 0;
            padding-left: 20px;
            font-size: 13px;
            color: #4a5568;
        }
        
        .password-requirements li {
            margin-bottom: 5px;
        }
        
        .password-strength {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background: #f56565; width: 33%; }
        .strength-medium { background: #ed8936; width: 66%; }
        .strength-strong { background: #48bb78; width: 100%; }
        
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
        }
        
        .back-link i {
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="reset-password-card">
        <?php if (!$valid_token && empty($token)): ?>
            <div class="card-header">
                <i class="fas fa-exclamation-triangle"></i>
                <h2>Invalid Link</h2>
                <p>No reset token provided</p>
            </div>
            <div class="card-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong>Invalid reset link</strong><br>
                        Please use the link from your email or request a new password reset.
                    </div>
                </div>
                <div class="text-center">
                    <a href="forgot-password.php" class="btn btn-primary">
                        <i class="fas fa-redo me-2"></i> Request New Reset Link
                    </a>
                </div>
            </div>
        <?php elseif (!$valid_token): ?>
            <div class="card-header">
                <i class="fas fa-clock"></i>
                <h2>Link Expired</h2>
                <p>This reset link is no longer valid</p>
            </div>
            <div class="card-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo $error; ?></div>
                </div>
                <div class="text-center">
                    <a href="forgot-password.php" class="btn btn-primary">
                        <i class="fas fa-redo me-2"></i> Request New Reset Link
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="card-header">
                <i class="fas fa-key"></i>
                <h2>Reset Your Password</h2>
                <p>Create a new secure password</p>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <div><?php echo $error; ?></div>
                    </div>
                <?php endif; ?>
                
                <div class="alert alert-success">
                    <i class="fas fa-user"></i>
                    <div>
                        <strong>Resetting password for:</strong><br>
                        <?php echo htmlspecialchars($user_data['first_name']); ?> (<?php echo htmlspecialchars($user_data['email']); ?>)
                    </div>
                </div>
                
                <form method="POST" action="" id="resetForm">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="new_password" id="new_password" class="form-control" 
                                   placeholder="Enter new password" required autofocus>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('new_password')"></i>
                        </div>
                        <div class="password-strength">
                            <div class="password-strength-bar" id="strengthBar"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" 
                                   placeholder="Confirm new password" required>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password')"></i>
                        </div>
                    </div>
                    
                    <div class="password-requirements">
                        <h6><i class="fas fa-shield-alt me-2"></i>Password Requirements:</h6>
                        <ul>
                            <li>At least 8 characters long</li>
                            <li>Contains uppercase letter (A-Z)</li>
                            <li>Contains lowercase letter (a-z)</li>
                            <li>Contains number (0-9)</li>
                        </ul>
                    </div>
                    
                    <button type="submit" class="btn btn-primary mt-4">
                        <i class="fas fa-check me-2"></i> Reset Password
                    </button>
                </form>
                
                <div class="text-center">
                    <a href="login.php" class="back-link">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.parentElement.querySelector('.password-toggle');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Password strength indicator
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            strengthBar.className = 'password-strength-bar';
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength <= 3) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        });
        
        // Form validation
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
            
            if (!/[A-Z]/.test(newPassword)) {
                e.preventDefault();
                alert('Password must contain at least one uppercase letter!');
                return false;
            }
            
            if (!/[a-z]/.test(newPassword)) {
                e.preventDefault();
                alert('Password must contain at least one lowercase letter!');
                return false;
            }
            
            if (!/[0-9]/.test(newPassword)) {
                e.preventDefault();
                alert('Password must contain at least one number!');
                return false;
            }
        });
    </script>
</body>
</html>
