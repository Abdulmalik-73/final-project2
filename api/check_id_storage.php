<?php
/**
 * Check what's actually stored in the database for ID images
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/config.php';
header('Content-Type: application/json');

$role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
if (!in_array(strtolower($role), ['receptionist', 'manager', 'admin', 'super_admin'])) {
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Get all bookings with ID images
$query = "SELECT 
    id, 
    user_id, 
    booking_reference,
    id_image,
    LENGTH(id_image) as image_size,
    SUBSTRING(id_image, 1, 100) as image_preview,
    CASE 
        WHEN id_image IS NULL THEN 'NULL'
        WHEN id_image = '' THEN 'EMPTY'
        WHEN SUBSTRING(id_image, 1, 5) = 'data:' THEN 'BASE64'
        ELSE 'OTHER'
    END as image_type
FROM bookings 
WHERE id_image IS NOT NULL AND id_image != ''
ORDER BY created_at DESC
LIMIT 10";

$result = $conn->query($query);
$bookings = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $bookings[] = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'booking_reference' => $row['booking_reference'],
            'image_type' => $row['image_type'],
            'image_size' => $row['image_size'],
            'image_preview' => $row['image_preview'],
            'is_valid_base64' => preg_match('/^data:(image\/(jpeg|png|jpg));base64,(.+)$/s', $row['id_image']) ? 'YES' : 'NO',
        ];
    }
}

echo json_encode([
    'total_with_images' => count($bookings),
    'bookings' => $bookings,
    'database_connection' => 'OK',
    'timestamp' => date('Y-m-d H:i:s'),
]);
