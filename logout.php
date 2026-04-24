<?php
// Professional logout functionality
session_start();

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Log the logout (optional)
if (isset($_SESSION['user_id'])) {
    error_log("User logout: ID " . $_SESSION['user_id'] . " at " . date('Y-m-d H:i:s'));
}

// Complete session destruction
$_SESSION = array();

// Remove session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Force redirect with JavaScript backup
?>
<!DOCTYPE html>
<html>
<head>
    <title>Logging out...</title>
    <meta http-equiv="refresh" content="0;url=login.php?logout=success">
</head>
<body>
    <script>
        window.location.replace('login.php?logout=success');
    </script>
    <p>Logging out... <a href="login.php?logout=success">Click here if not redirected</a></p>
</body>
</html>