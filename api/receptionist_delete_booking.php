<?php
/**
 * Receptionist-only: Delete a booking that has no ID uploaded
 */
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Must be receptionist, manager, or admin
$role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (empty($_SESSION['user_id']) || !in_array($role, ['receptionist', 'manager', 'admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
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
    echo json_encode(['success' => false, 'error' => 'Cannot delete: this booking has an ID image uploaded. Use the Delete ID button first.']);
    exit;
}

// Delete the booking
$del = $conn->prepare("DELETE FROM bookings WHERE id = ? AND (id_image IS NULL OR id_image = '')");
$del->bind_param("i", $booking_id);
$del->execute();

if ($del->affected_rows > 0) {
    $del->close();
    echo json_encode(['success' => true, 'message' => 'Booking ' . $booking['booking_reference'] . ' deleted successfully']);
} else {
    $del->close();
    echo json_encode(['success' => false, 'error' => 'Could not delete booking. It may have an ID image attached.']);
}
?>