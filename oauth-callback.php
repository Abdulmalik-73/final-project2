<?php
// AGGRESSIVE cache prevention - MUST be first
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/services/GoogleOAuthService.php';

$error = '';
$redirect_url = 'index.php';

// Get redirect parameters from state
$state = $_GET['state'] ?? '';
$redirect_params = [];
if ($state) {
    parse_str(base64_decode($state), $redirect_params);
    if (isset($redirect_params['redirect'])) {
        if ($redirect_params['redirect'] == 'booking') {
            $redirect_url = 'booking.php' . (isset($redirect_params['room']) ? '?room=' . $redirect_params['room'] : '');
        } elseif ($redirect_params['redirect'] == 'food-booking') {
            $redirect_url = 'food-booking.php';
        }
    }
}

// Check if user is already logged in
if (is_logged_in()) {
    header("Location: $redirect_url");
    exit();
}

// Handle OAuth callback
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    
    try {
        $oauth_service = new GoogleOAuthService($conn);
        
        if (!$oauth_service->isConfigured()) {
            $error = 'Google OAuth is not properly configured. Please contact support.';
        } else {
            // Exchange code for access token
            $token_data = $oauth_service->getAccessToken($code);
            
            if ($token_data && isset($token_data['access_token'])) {
                // Get user info from Google
                $google_user = $oauth_service->getUserInfo($token_data['access_token']);
                
                if ($google_user) {
                    // Create or update user in database
                    $user = $oauth_service->createOrUpdateUser($google_user);
                    
                    if ($user) {
                        // REGENERATE SESSION ID for security
                        session_regenerate_id(true);
                        
                        // Set session variables - CRITICAL
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['last_activity'] = time(); // Activity tracking
                        
                        // Update last login timestamp
                        $update_query = "UPDATE users SET last_login = NOW() WHERE id = ?";
                        $update_stmt = $conn->prepare($update_query);
                        $update_stmt->bind_param("i", $user['id']);
                        $update_stmt->execute();
                        
                        // Log successful OAuth login
                        log_user_activity($user['id'], 'login', 'User logged in via Google OAuth', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
                        
                        // Redirect based on role
                        if ($user['role'] === 'admin') {
                            header("Location: dashboard/admin.php");
                        } elseif ($user['role'] === 'manager') {
                            header("Location: dashboard/manager.php");
                        } elseif ($user['role'] === 'receptionist') {
                            header("Location: dashboard/receptionist.php");
                        } else {
                            // Customer - redirect to dashboard or home
                            header("Location: index.php");
                        }
                        exit();
                    } else {
                        $error = 'Failed to create or update user account. Please try again.';
                    }
                } else {
                    $error = 'Failed to get user information from Google. Please try again.';
                }
            } else {
                $error = 'Failed to get access token from Google. Please try again.';
            }
        }
    } catch (Exception $e) {
        error_log("OAuth callback error: " . $e->getMessage());
        $error = 'An error occurred during authentication. Please try again.';
    }
} elseif (isset($_GET['error'])) {
    // Handle OAuth errors
    $oauth_error = $_GET['error'];
    $error_description = $_GET['error_description'] ?? '';
    
    if ($oauth_error === 'access_denied') {
        $error = 'Access denied. You cancelled the Google authentication.';
    } else {
        $error = 'Authentication error: ' . htmlspecialchars($error_description ?: $oauth_error);
    }
} else {
    $error = 'Invalid OAuth callback. Missing authorization code.';
}

// If we reach here, there was an error
$_SESSION['oauth_error'] = $error;
header("Location: login.php" . ($redirect_params ? '?' . http_build_query($redirect_params) : ''));
exit();
?>