<?php
/**
 * Enhanced Session Security Functions
 * Prevents session hijacking and ensures proper logout
 */

/**
 * Secure session validation for dashboard pages
 */
function validate_dashboard_session($required_role = null) {
    global $conn;
    
    // Start session if not started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Prevent caching
    header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        force_logout('session_missing');
        return false;
    }
    
    // Check if role is required and matches
    if ($required_role && (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role)) {
        force_logout('role_mismatch');
        return false;
    }
    
    // Validate user still exists and is active
    $user_id = (int)$_SESSION['user_id'];
    $query = "SELECT id, role, status FROM users WHERE id = ? AND status = 'active'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        force_logout('account_inactive');
        return false;
    }
    
    $user = $result->fetch_assoc();
    
    // Double-check role matches database
    if ($required_role && $user['role'] !== $required_role) {
        force_logout('role_changed');
        return false;
    }
    
    return true;
}

/**
 * Force logout and redirect
 */
function force_logout($reason = 'security') {
    // Clear session data
    $_SESSION = array();
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
    
    // Clear additional cookies
    $cookies_to_clear = ['remember_token', 'auth_token', 'user_session'];
    foreach ($cookies_to_clear as $cookie) {
        if (isset($_COOKIE[$cookie])) {
            setcookie($cookie, '', time() - 3600, '/');
        }
    }
    
    // Redirect to login with reason
    $redirect_url = '../login.php?logout=forced&reason=' . urlencode($reason);
    header('Location: ' . $redirect_url);
    exit();
}

/**
 * Check if session is expired (optional timeout feature)
 */
function check_session_timeout($timeout_minutes = 120) {
    if (isset($_SESSION['last_activity'])) {
        $inactive_time = time() - $_SESSION['last_activity'];
        if ($inactive_time > ($timeout_minutes * 60)) {
            force_logout('timeout');
            return false;
        }
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    return true;
}
?>