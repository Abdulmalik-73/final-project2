<?php
/**
 * Secure ID Image Viewer
 * Serves ID images from uploads/ids/ directory with proper security
 * Only accessible by receptionist/manager/admin/super_admin or booking owner
 */
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/config.php';
require_once 'includes/functions.php';

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

// Get ID image filename from bookings table
$stmt = $conn->prepare("SELECT id_image FROM bookings WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$filename = trim($row['id_image'] ?? '');

if (empty($filename)) {
    error_log("view-id: No filename for booking_id=$booking_id");
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'No ID image on file for this booking';
    exit;
}

error_log("view-id: Found filename for booking_id=$booking_id: $filename");

// Handle both base64 (legacy) and file-based (new) storage
if (strpos($filename, 'data:') === 0) {
    // Legacy base64 format
    if (preg_match('/^data:(image\/(jpeg|png|jpg));base64,(.+)$/s', $filename, $m)) {
        $mime    = ($m[2] === 'jpg') ? 'image/jpeg' : 'image/' . $m[2];
        $imgdata = base64_decode($m[3], true);

        if ($imgdata === false || strlen($imgdata) === 0) {
            error_log("view-id: Failed to decode base64 for booking_id=$booking_id");
            http_response_code(500);
            header('Content-Type: text/plain');
            echo 'Failed to decode image data';
            exit;
        }

        error_log("view-id: Successfully serving base64 image for booking_id=$booking_id, mime=$mime, size=" . strlen($imgdata));
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($imgdata));
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        ob_end_clean();
        echo $imgdata;
        exit;
    } else {
        error_log("view-id: Base64 regex mismatch for booking_id=$booking_id, filename starts with: " . substr($filename, 0, 100));
        http_response_code(400);
        header('Content-Type: text/plain');
        echo 'Invalid base64 format';
        exit;
    }
} else {
    // New file-based storage
    $filepath = __DIR__ . '/uploads/ids/' . $filename;
    
    // Validate filename format for security
    if (!preg_match('/^id_\d+_\d+_[a-f0-9]+\.(jpg|jpeg|png)$/i', $filename)) {
        error_log("view-id: Invalid filename format for booking_id=$booking_id: $filename");
        http_response_code(400);
        header('Content-Type: text/plain');
        echo 'Invalid filename format';
        exit;
    }
    
    // Check if file exists
    if (!file_exists($filepath)) {
        error_log("view-id: File not found for booking_id=$booking_id: $filepath");
        http_response_code(404);
        header('Content-Type: text/plain');
        echo 'Image file not found';
        exit;
    }
    
    // Get file info
    $fileinfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($fileinfo, $filepath);
    finfo_close($fileinfo);
    
    if (!in_array($mime, ['image/jpeg', 'image/jpg', 'image/png'])) {
        error_log("view-id: Invalid file type for booking_id=$booking_id: $mime");
        http_response_code(400);
        header('Content-Type: text/plain');
        echo 'Invalid file type';
        exit;
    }
    
    $filesize = filesize($filepath);
    if ($filesize === false || $filesize === 0) {
        error_log("view-id: Invalid file size for booking_id=$booking_id");
        http_response_code(500);
        header('Content-Type: text/plain');
        echo 'Invalid file size';
        exit;
    }
    
    error_log("view-id: Successfully serving file for booking_id=$booking_id, mime=$mime, size=$filesize");
    
    // Serve the file
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . $filesize);
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: inline; filename="id_' . $booking_id . '_' . basename($filepath) . '"');
    ob_end_clean();
    readfile($filepath);
    exit;
}

// Fallback
error_log("view-id: Unexpected error for booking_id=$booking_id");
http_response_code(500);
header('Content-Type: text/plain');
echo 'Unexpected error';
?>
