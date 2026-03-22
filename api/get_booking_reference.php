<?php
session_start();
require_once '../includes/config.php';

// Simple API to get booking reference by ID
header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['error' => 'Invalid booking ID']);
    exit();
}

$booking_id = (int)$_GET['id'];

$query = "SELECT booking_reference, payment_reference FROM bookings WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($booking = $result->fetch_assoc()) {
    echo json_encode([
        'booking_reference' => $booking['booking_reference'],
        'payment_reference' => $booking['payment_reference']
    ]);
} else {
    echo json_encode(['error' => 'Booking not found']);
}
?>