<?php
/**
 * Security Functions
 * Enhanced session security and protection against common attacks
 */

/**
 * Validate session security
 * Checks for session hijacking, expiration, and other security issues
 * 
 * @return bool True if session is valid, false otherwise
 */
function validate_session_security() {
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return false;
    }
    
    // Check user agent (prevent session hijacking)
    if (isset($_SESSION['user_agent'])) {
        $current_user_agent = md5($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($_SESSION['user_agent'] !== $current_user_agent) {
            // User agent mismatch - possible session hijacking
            error_log("Session hijacking attempt detected for user {$_SESSION['user_id']}");
            return false;
        }
    }
    
    // Check session expiration (24 hours of inactivity)
    if (isset($_SESSION['last_activity'])) {
        $inactive_time = time() - $_SESSION['last_activity'];
        if ($inactive_time > 86400) { // 24 hours
            error_log("Session expired for user {$_SESSION['user_id']} after {$inactive_time} seconds of inactivity");
            return false;
        }
    }
    
    // Check absolute session timeout (7 days from login)
    if (isset($_SESSION['login_time'])) {
        $session_age = time() - $_SESSION['login_time'];
        if ($session_age > 604800) { // 7 days
            error_log("Session absolute timeout for user {$_SESSION['user_id']} after {$session_age} seconds");
            return false;
        }
    }
    
    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
    
    return true;
}

/**
 * Require secure authentication
 * Use this at the top of all protected pages
 * 
 * @param string $redirect_url URL to redirect to if not authenticated
 */
function require_secure_auth($redirect_url = 'login.php') {
    // Start session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Prevent caching of protected pages
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    
    // Validate session security
    if (!validate_session_security()) {
        // Invalid session - destroy and redirect
        $error_reason = '';
        
        if (!isset($_SESSION['user_id'])) {
            $error_reason = 'not_logged_in';
        } elseif (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== md5($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            $error_reason = 'session_hijack';
        } elseif (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 86400)) {
            $error_reason = 'session_expired';
        } elseif (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 604800)) {
            $error_reason = 'session_timeout';
        }
        
        // Destroy session
        session_destroy();
        
        // Build absolute URL
        $proto = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
        $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Redirect to login with error message
        $redirect_path = $redirect_url;
        if ($error_reason) {
            $redirect_path .= (strpos($redirect_url, '?') !== false ? '&' : '?') . 'error=' . $error_reason;
        }
        
        header("Location: $proto://$host/$redirect_path");
        exit();
    }
}

/**
 * Regenerate session ID to prevent session fixation
 * Call this after successful login
 */
function regenerate_session() {
    if (session_status() == PHP_SESSION_ACTIVE) {
        // Store session data
        $session_data = $_SESSION;
        
        // Regenerate session ID
        session_regenerate_id(true);
        
        // Restore session data
        $_SESSION = $session_data;
    }
}

/**
 * Check if request is from same origin (CSRF protection)
 * 
 * @return bool True if same origin, false otherwise
 */
function verify_same_origin() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    
    // If no origin or referer, allow (direct access)
    if (empty($origin) && empty($referer)) {
        return true;
    }
    
    // Check origin
    if (!empty($origin)) {
        $origin_host = parse_url($origin, PHP_URL_HOST);
        if ($origin_host !== $host) {
            return false;
        }
    }
    
    // Check referer
    if (!empty($referer)) {
        $referer_host = parse_url($referer, PHP_URL_HOST);
        if ($referer_host !== $host) {
            return false;
        }
    }
    
    return true;
}

/**
 * Generate CSRF token
 * 
 * @return string CSRF token
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * 
 * @param string $token Token to verify
 * @return bool True if valid, false otherwise
 */
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input to prevent XSS
 * 
 * @param string $input Input to sanitize
 * @return string Sanitized input
 */
function sanitize_input($input) {
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

/**
 * Log security event
 * 
 * @param string $event_type Type of security event
 * @param string $description Description of the event
 * @param int $user_id User ID (if applicable)
 */
function log_security_event($event_type, $description, $user_id = null) {
    $log_file = __DIR__ . '/../logs/security.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $log_entry = sprintf(
        "[%s] %s | User: %s | IP: %s | UA: %s | %s\n",
        $timestamp,
        $event_type,
        $user_id ?? 'guest',
        $ip,
        $user_agent,
        $description
    );
    
    @file_put_contents($log_file, $log_entry, FILE_APPEND);
}
?>
