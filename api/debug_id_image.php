<?php
/**
 * Debug ID image storage
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/config.php';
header('Content-Type: application/json');

$role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
if (!in_array($role, ['receptionist', 'manager', 'admin', 'super_admin'])) {
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$booking_id = (int)($_GET['booking_id'] ?? 0);
if ($booking_id <= 0) {
    echo json_encode(['error' => 'Invalid booking ID']);
    exit;
}

$stmt = $conn->prepare("SELECT id, user_id, id_image FROM bookings WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['error' => 'Booking not found']);
    exit;
}

$id_image = $row['id_image'] ?? '';

echo json_encode([
    'booking_id' => $row['id'],
    'user_id' => $row['user_id'],
    'id_image_exists' => !empty($id_image),
    'id_image_length' => strlen($id_image),
    'id_image_starts_with' => substr($id_image, 0, 50),
    'is_base64_format' => strpos($id_image, 'data:') === 0,
    'base64_regex_match' => preg_match('/^data:(image\/(jpeg|png|jpg));base64,(.+)$/s', $id_image) ? 'YES' : 'NO',
]);
