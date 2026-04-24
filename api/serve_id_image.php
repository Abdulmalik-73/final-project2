<?php
/**
 * Secure ID image server
 * Serves base64 images stored in database
 * Only accessible by receptionist/manager/admin/super_admin
 */
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.cookie_lifetime', 86400);
    ini_set('session.cookie_samesite', 'Lax');
    $sp = sys_get_temp_dir() . '/php_sessions';
    if (!is_dir($sp)) @mkdir($sp, 0777, true);
    ini_set('session.save_path', $sp);
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    ini_set('session.cookie_secure', $is_https ? 1 : 0);
    session_name('HARAR_RAS_SESSION');
    session_start();
}

require_once '../includes/config.php';
ob_clean();

// Check user role - try multiple session keys
$role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? $_SESSION['staff_role'] ?? '';
$user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;

// Allow if user is logged in and has a role (receptionist dashboard access)
$allowed_roles = ['receptionist', 'manager', 'admin', 'super_admin', 'staff'];
$is_authorized = !empty($role) && in_array(strtolower($role), $allowed_roles);

// Also allow if user is logged in and accessing their own booking
if (!$is_authorized && $user_id > 0) {
    // Check if this is the user's own booking
    $booking_id = (int)($_GET['booking_id'] ?? 0);
    if ($booking_id > 0) {
        $check = $conn->prepare("SELECT user_id FROM bookings WHERE id = ? LIMIT 1");
        $check->bind_param("i", $booking_id);
        $check->execute();
        $check_row = $check->get_result()->fetch_assoc();
        $check->close();
        if ($check_row && $check_row['user_id'] == $user_id) {
            $is_authorized = true;
        }
    }
}

if (!$is_authorized) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Access denied';
    exit;
}

$booking_id = (int)($_GET['booking_id'] ?? 0);
if ($booking_id <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Invalid booking ID';
    exit;
}

// Get base64 image from bookings table
$stmt = $conn->prepare("SELECT id_image FROM bookings WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$data = trim($row['id_image'] ?? '');

if (empty($data)) {
    error_log("serve_id_image: No image data for booking_id=$booking_id");
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'No ID image on file for this booking';
    exit;
}

error_log("serve_id_image: Found image data for booking_id=$booking_id, length=" . strlen($data));

// If data is base64 data URL, extract and serve it
if (strpos($data, 'data:') === 0) {
    if (preg_match('/^data:(image\/(jpeg|png|jpg));base64,(.+)$/s', $data, $m)) {
        $mime    = ($m[2] === 'jpg') ? 'image/jpeg' : 'image/' . $m[2];
        $imgdata = base64_decode($m[3], true);

        if ($imgdata === false || strlen($imgdata) === 0) {
            error_log("serve_id_image: Failed to decode base64 for booking_id=$booking_id");
            http_response_code(500);
            header('Content-Type: text/plain');
            echo 'Failed to decode image data';
            exit;
        }

        error_log("serve_id_image: Successfully serving image for booking_id=$booking_id, mime=$mime, size=" . strlen($imgdata));
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($imgdata));
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        ob_end_clean();
        echo $imgdata;
        exit;
    } else {
        error_log("serve_id_image: Base64 regex mismatch for booking_id=$booking_id, data starts with: " . substr($data, 0, 100));
        http_response_code(400);
        header('Content-Type: text/plain');
        echo 'Invalid base64 format';
        exit;
    }
}

error_log("serve_id_image: Data not in base64 format for booking_id=$booking_id, starts with: " . substr($data, 0, 50));
http_response_code(404);
header('Content-Type: text/plain');
echo 'Image data not in expected format';
exit;



