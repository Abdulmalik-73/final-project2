<?php
/**
 * Delete ID image from a booking
 * Clears bookings.id_image (base64 data)
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

// First get the current filename to delete the file
$get_stmt = $conn->prepare("SELECT id_image FROM bookings WHERE id = ? LIMIT 1");
$get_stmt->bind_param("i", $booking_id);
$get_stmt->execute();
$booking = $get_stmt->get_result()->fetch_assoc();
$get_stmt->close();

$filename = $booking['id_image'] ?? '';

// Start transaction
$conn->begin_transaction();

try {
    // Update database to remove image reference
    $stmt = $conn->prepare("UPDATE bookings SET id_image = NULL WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $stmt->close();

    // Delete the actual file if it's a file-based image (not base64)
    if (!empty($filename) && strpos($filename, 'data:') !== 0) {
        // This is a file-based image, delete it
        $filepath = __DIR__ . '/../uploads/ids/' . $filename;
        
        // Validate filename format for security
        if (preg_match('/^id_\d+_\d+_[a-f0-9]+\.(jpg|jpeg|png)$/i', $filename)) {
            if (file_exists($filepath)) {
                if (!unlink($filepath)) {
                    error_log("Failed to delete ID file: $filepath for booking_id=$booking_id");
                    // Continue anyway - database is updated, file cleanup can be manual
                } else {
                    error_log("Successfully deleted ID file: $filepath for booking_id=$booking_id");
                }
            } else {
                error_log("ID file not found for deletion: $filepath for booking_id=$booking_id");
            }
        } else {
            error_log("Invalid filename format for deletion: $filename for booking_id=$booking_id");
        }
    }

    $conn->commit();
    error_log("ID image deleted for booking_id=$booking_id by user=" . ($_SESSION['user_id'] ?? '?'));
    echo json_encode(['success' => true, 'message' => 'ID image deleted successfully.']);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error deleting ID image for booking_id=$booking_id: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to delete: ' . $e->getMessage()]);
}


