<?php
/**
 * Secure ID image server
 * Serves base64 images stored in database
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

// Get base64 image from bookings table
$stmt = $conn->prepare("SELECT id_image FROM bookings WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$data = trim($row['id_image'] ?? '');

if (empty($data)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'No ID image on file for this booking';
    exit;
}

// If data is base64 data URL, extract and serve it
if (strpos($data, 'data:') === 0) {
    if (preg_match('/^data:(image\/(jpeg|png|jpg));base64,(.+)$/s', $data, $m)) {
        $mime    = ($m[2] === 'jpg') ? 'image/jpeg' : 'image/' . $m[2];
        $imgdata = base64_decode($m[3], true);

        if ($imgdata === false || strlen($imgdata) === 0) {
            http_response_code(500);
            header('Content-Type: text/plain');
            echo 'Failed to decode image data';
            exit;
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($imgdata));
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        ob_end_clean();
        echo $imgdata;
        exit;
    } else {
        http_response_code(400);
        header('Content-Type: text/plain');
        echo 'Invalid base64 format';
        exit;
    }
}

http_response_code(404);
header('Content-Type: text/plain');
echo 'Image data not in expected format';
exit;


