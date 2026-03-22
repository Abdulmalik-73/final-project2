<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Get redirect parameters
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '';
$room_id = isset($_GET['room']) ? (int)$_GET['room'] : null;

if (is_logged_in()) {
    if ($redirect == 'booking') {
        $redirect_url = 'booking.php' . ($room_id ? '?room=' . $room_id : '');
        header("Location: $redirect_url");
    } elseif ($redirect == 'food-booking') {
        header("Location: food-booking.php");
    } else {
        header('Location: index.php');
    }
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = sanitize_input($_POST['full_name']);
    $email = sanitize_input($_POST['email']);
    $phone = isset($_POST['phone']) ? sanitize_input($_POST['phone']) : null;
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($full_name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif (strlen($full_name) < 2) {
        $error = 'Full name must be at least 2 characters';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        // Check if email already exists
        $check_query = "SELECT id FROM users WHERE email = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = 'Email already exists. Please use a different email or login.';
        } else {
            // All public signups are customers only
            $hashed_password = hash_password($password);
            $role = 'customer';
            $status = 'active';
            
            // Split full name into first and last name
            $name_parts = explode(' ', trim($full_name), 2);
            $first_name = $name_parts[0];
            $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
            
            // Generate unique username from email (part before @)
            $base_username = explode('@', $email)[0];
            $username = $base_username;
            $counter = 1;
            
            // Check if username exists and make it unique
            while (true) {
                $check_username_query = "SELECT id FROM users WHERE username = ?";
                $check_username_stmt = $conn->prepare($check_username_query);
                $check_username_stmt->bind_param("s", $username);
                $check_username_stmt->execute();
                $check_username_result = $check_username_stmt->get_result();
                
                if ($check_username_result->num_rows == 0) {
                    // Username is available
                    break;
                } else {
                    // Username exists, try with a number suffix
                    $username = $base_username . $counter;
                    $counter++;
                }
            }
            
            // Create customer account
            $query = "INSERT INTO users (first_name, last_name, username, email, phone, password, role, status, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssssss", $first_name, $last_name, $username, $email, $phone, $hashed_password, $role, $status);
            
            if ($stmt->execute()) {
                // Get the newly created user ID
                $new_user_id = $stmt->insert_id;
                
                // Log user registration activity
                log_user_activity($new_user_id, 'registration', 'New customer account created', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
                
                // Automatically log in the user after successful registration
                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['user_name'] = trim($first_name . ' ' . $last_name);
                $_SESSION['user_email'] = $email;
                $_SESSION['user_role'] = $role;
                
                // Update last login timestamp
                $update_query = "UPDATE users SET last_login = NOW() WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("i", $new_user_id);
                $update_stmt->execute();
                
                // Log auto-login after registration
                log_user_activity($new_user_id, 'login', 'Auto-login after registration', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
                
                // Redirect based on redirect parameter or to home page
                if ($redirect == 'booking') {
                    $redirect_url = 'booking.php' . ($room_id ? '?room=' . $room_id : '');
                    header("Location: $redirect_url");
                } elseif ($redirect == 'food-booking') {
                    header("Location: food-booking.php");
                } else {
                    header("Location: index.php?welcome=1");
                }
                exit();
            } else {
                $error = 'Registration failed. Please try again. Error: ' . $stmt->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signup - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-image: url('assets/images/hotel/exterior/hotel-main.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 15px;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.85) 0%, rgba(118, 75, 162, 0.85) 100%);
            z-index: -1;
        }
        
        .signup-wrapper {
            width: 100%;
            max-width: 450px;
            position: relative;
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            color: white;
            text-decoration: none;
            margin-bottom: 15px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 6px 10px;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .back-button:hover {
            color: white;
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(-3px);
        }
        
        .back-button i {
            margin-right: 8px;
            font-size: 12px;
        }
        
        .signup-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
            padding: 20px 25px;
            width: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .signup-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .signup-header {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .signup-header h1 {
            font-size: 22px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 4px;
        }
        
        .signup-header p {
            color: #718096;
            font-size: 13px;
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 12px;
        }
        
        .form-label {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 4px;
            display: block;
            font-size: 12px;
        }
        
        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 9px 12px;
            font-size: 13px;
            width: 100%;
            transition: all 0.3s ease;
            background: #f8fafc;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
            background: white;
        }
        
        .form-control.error {
            border-color: #e53e3e;
            background-color: #fed7d7;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-signup {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            flex: 1;
        }
        
        .btn-signup:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-cancel {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            flex: 1;
        }
        
        .btn-cancel:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.3);
            text-decoration: none;
        }
        
        .alert {
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 12px;
            font-size: 12px;
            border: none;
            display: flex;
            align-items: center;
        }
        
        .alert i {
            margin-right: 8px;
            font-size: 14px;
        }
        
        .alert-danger {
            background: #fed7d7;
            color: #c53030;
        }
        
        .alert-success {
            background: #c6f6d5;
            color: #2f855a;
        }
        
        .login-link {
            text-align: center;
            margin-top: 15px;
            font-size: 12px;
            color: #718096;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .field-error {
            color: #e53e3e;
            font-size: 11px;
            margin-top: 4px;
            display: block;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .signup-container {
                padding: 25px 20px;
                border-radius: 14px;
            }
            
            .signup-header h1 {
                font-size: 22px;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .back-button {
                font-size: 13px;
                padding: 5px 8px;
            }
        }
        
        @media (max-width: 480px) {
            .signup-container {
                padding: 20px 16px;
                border-radius: 12px;
            }
            
            .signup-header h1 {
                font-size: 20px;
            }
            
            .signup-header p {
                font-size: 13px;
            }
        }
        
        /* Animation for form elements */
        .form-group {
            animation: slideUp 0.6s ease-out;
        }
        
        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }
        .form-group:nth-child(4) { animation-delay: 0.4s; }
        .form-group:nth-child(5) { animation-delay: 0.5s; }
        .button-group { animation: slideUp 0.6s ease-out 0.6s both; }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Focus states for accessibility */
        .back-button:focus,
        .form-control:focus,
        .btn-signup:focus,
        .btn-cancel:focus {
            outline: 2px solid #667eea;
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <div class="signup-wrapper">
        <a href="index.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
            Back to Home
        </a>
        
        <div class="signup-container">
            <div class="signup-header">
                <h1>Create New Account</h1>
                <p>Join us today! Please fill in your details.</p>
            </div>
            
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
            
            <form method="POST" action="" id="signupForm">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" id="full_name" class="form-control" required 
                           placeholder="Enter your full name"
                           value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                    <div id="fullname-error" class="field-error" style="display: none;"></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input type="email" name="email" id="email" class="form-control" required 
                           placeholder="Enter your email address"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <div id="email-error" class="field-error" style="display: none;"></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone Number (Optional)</label>
                    <input type="tel" name="phone" id="phone" class="form-control" 
                           placeholder="Enter your phone number (e.g., +251-911-234-567)"
                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    <small class="text-muted" style="font-size: 11px;">Optional - for booking notifications</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" required minlength="6"
                           placeholder="Enter password (min. 6 characters)">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Confirm Password *</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="6"
                           placeholder="Re-enter your password">
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn-signup">Create Account</button>
                    <a href="index.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
            
            <div class="login-link">
                Already have an account? 
                <a href="login.php<?php echo $redirect ? '?redirect=' . urlencode($redirect) . ($room_id ? '&room=' . $room_id : '') : ''; ?>">
                    Login
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fullNameInput = document.getElementById('full_name');
            const emailInput = document.getElementById('email');
            const passwordInput = document.querySelector('input[name="password"]');
            const confirmPasswordInput = document.querySelector('input[name="confirm_password"]');
            const fullNameError = document.getElementById('fullname-error');
            const emailError = document.getElementById('email-error');
            const signupForm = document.getElementById('signupForm');
            
            // Function to show error
            function showError(element, errorDiv, message) {
                element.classList.add('error');
                errorDiv.textContent = message;
                errorDiv.style.display = 'block';
            }
            
            // Function to clear error
            function clearError(element, errorDiv) {
                element.classList.remove('error');
                errorDiv.textContent = '';
                errorDiv.style.display = 'none';
            }
            
            // Clear errors when user types
            fullNameInput.addEventListener('input', function() {
                clearError(fullNameInput, fullNameError);
            });
            
            emailInput.addEventListener('input', function() {
                clearError(emailInput, emailError);
            });
            
            // Form submission validation
            signupForm.addEventListener('submit', function(e) {
                // Clear all previous errors
                clearError(fullNameInput, fullNameError);
                clearError(emailInput, emailError);
                
                const fullName = fullNameInput.value.trim();
                const email = emailInput.value.trim();
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                let hasError = false;
                
                // Validate full name
                if (!fullName) {
                    showError(fullNameInput, fullNameError, 'Full name is required.');
                    hasError = true;
                } else if (fullName.length < 2) {
                    showError(fullNameInput, fullNameError, 'Full name must be at least 2 characters.');
                    hasError = true;
                } else if (!/^[a-zA-Z\s]+$/.test(fullName)) {
                    showError(fullNameInput, fullNameError, 'Full name can only contain letters and spaces.');
                    hasError = true;
                }
                
                // Validate email
                if (!email) {
                    showError(emailInput, emailError, 'Email address is required.');
                    hasError = true;
                } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    showError(emailInput, emailError, 'Please enter a valid email address.');
                    hasError = true;
                }
                
                // Validate password match
                if (password !== confirmPassword) {
                    // Show error on confirm password field
                    const confirmPasswordError = document.createElement('div');
                    confirmPasswordError.className = 'field-error';
                    confirmPasswordError.textContent = 'Passwords do not match.';
                    confirmPasswordInput.classList.add('error');
                    confirmPasswordInput.parentNode.appendChild(confirmPasswordError);
                    hasError = true;
                }
                
                // Validate password length
                if (password.length < 6) {
                    const passwordError = document.createElement('div');
                    passwordError.className = 'field-error';
                    passwordError.textContent = 'Password must be at least 6 characters.';
                    passwordInput.classList.add('error');
                    passwordInput.parentNode.appendChild(passwordError);
                    hasError = true;
                }
                
                if (hasError) {
                    e.preventDefault();
                    fullNameInput.focus();
                    return false;
                }
            });
        });
    </script>
</body>
</html>
