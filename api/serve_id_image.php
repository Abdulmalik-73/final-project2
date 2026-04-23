<?php
/**
 * Secure ID image server — reads base64 from database, outputs as image
 * Only accessible by receptionist/manager/admin roles
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/config.php';

$role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
if (!in_array($role, ['receptionist', 'manager', 'admin', 'super_admin'])) {
    http_response_code(403);
    exit('Access denied');
}

$booking_id = (int)($_GET['booking_id'] ?? 0);
if ($booking_id <= 0) {
    http_response_code(400);
    exit('Invalid booking ID');
}

$stmt = $conn->prepare("SELECT id_image FROM bookings WHERE id = ?");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row || empty($row['id_image'])) {
    http_response_code(404);
    exit('Image not found');
}

$data = $row['id_image'];

// Handle base64 data URL format: data:image/jpeg;base64,....
if (strpos($data, 'data:') === 0) {
    // Parse: data:image/jpeg;base64,<data>
    if (preg_match('/^data:(image\/[a-z]+);base64,(.+)$/s', $data, $matches)) {
        $mime    = $matches[1];
        $imgdata = base64_decode($matches[2]);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($imgdata));
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        echo $imgdata;
        exit;
    }
}

// Legacy: file path stored (old uploads)
$path = __DIR__ . '/../' . ltrim($data, '/');
if (file_exists($path)) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $path);
    finfo_close($finfo);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

http_response_code(404);
exit('Image not available');
