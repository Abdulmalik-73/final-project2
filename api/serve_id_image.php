<?php
/**
 * Secure ID image server
 * Serves image files from /uploads/ids/ directory
 * Only accessible by receptionist/manager/admin/super_admin
 */
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/config.php';
ob_clean();

$role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
if (!in_array($role, ['receptionist', 'manager', 'admin', 'super_admin'])) {
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

// Get filename from bookings table
$stmt = $conn->prepare("SELECT id_image FROM bookings WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$filename = trim($row['id_image'] ?? '');

if (empty($filename)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'No ID image on file for this booking';
    exit;
}

// Validate filename format (prevent directory traversal)
if (!preg_match('/^id_\d+_\d+_[a-f0-9]+\.(jpg|png)$/i', $filename)) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Invalid filename format';
    exit;
}

// Build safe file path
$filepath = realpath(__DIR__ . '/../uploads/ids/' . $filename);
$allowed_dir = realpath(__DIR__ . '/../uploads/ids');

// Security check: ensure file is in the allowed directory
if (!$filepath || !$allowed_dir || strpos($filepath, $allowed_dir) !== 0) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Access denied';
    exit;
}

// Check if file exists
if (!is_file($filepath)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Image file not found on server';
    exit;
}

// Serve the file
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $filepath);
finfo_close($finfo);

if (!in_array($mime, ['image/jpeg', 'image/png'])) {
    $mime = 'image/jpeg'; // default
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
ob_end_clean();
readfile($filepath);
exit;

