<?php
/**
 * Receptionist-only: Delete a booking that has no ID uploaded
 */

// Start session with same settings as config.php — BEFORE including config
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

header('Content-Type: application/json');

// Check role — same keys as delete_id_image.php
$role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($user_id <= 0 || !in_array($role, ['receptionist', 'manager', 'admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$booking_id = (int)($input['booking_id'] ?? 0);

if ($booking_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid booking ID']);
    exit;
}

// Verify the booking exists and has NO id_image
$stmt = $conn->prepare("SELECT id, booking_reference, id_image FROM bookings WHERE id = ?");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    echo json_encode(['success' => false, 'error' => 'Booking not found']);
    exit;
}

if (!empty($booking['id_image'])) {
    echo json_encode(['success' => false, 'error' => 'Cannot delete: this booking has an ID image. Use the Delete ID button first.']);
    exit;
}

// Delete the booking
$del = $conn->prepare("DELETE FROM bookings WHERE id = ?");
$del->bind_param("i", $booking_id);
$del->execute();

if ($del->affected_rows > 0) {
    $del->close();
    echo json_encode(['success' => true, 'message' => 'Booking ' . $booking['booking_reference'] . ' deleted successfully']);
} else {
    $del->close();
    echo json_encode(['success' => false, 'error' => 'Could not delete booking']);
}
?>