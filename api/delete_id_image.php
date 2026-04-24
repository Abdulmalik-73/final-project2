<?php
/**
 * Delete ID image from a booking
 * Deletes file from /uploads/ids/ and clears bookings.id_image
 * Only accessible by receptionist/manager/admin
 */

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
    session_start();
}

require_once '../includes/config.php';
header('Content-Type: application/json');

// Auth: staff only
$role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
if (!in_array($role, ['receptionist', 'manager', 'admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$booking_id = (int)($input['booking_id'] ?? 0);

if ($booking_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid booking ID.']);
    exit;
}

// Get filename before deleting
$stmt = $conn->prepare("SELECT id_image FROM bookings WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$filename = trim($row['id_image'] ?? '');

// Delete file from disk if it exists
if (!empty($filename) && preg_match('/^id_\d+_\d+_[a-f0-9]+\.(jpg|png)$/i', $filename)) {
    $filepath = __DIR__ . '/../uploads/ids/' . $filename;
    if (is_file($filepath)) {
        @unlink($filepath);
    }
}

// Clear from database
$stmt = $conn->prepare("UPDATE bookings SET id_image = NULL WHERE id = ?");
$stmt->bind_param("i", $booking_id);

if ($stmt->execute()) {
    error_log("ID image deleted for booking_id=$booking_id by user=" . ($_SESSION['user_id'] ?? '?'));
    echo json_encode(['success' => true, 'message' => 'ID image deleted successfully.']);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to delete: ' . $stmt->error]);
}

