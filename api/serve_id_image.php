<?php
/**
 * Secure ID image server
 * Reads base64 from bookings.id_image (or temp_id_uploads) and outputs as image.
 * Only accessible by receptionist/manager/admin/super_admin.
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

// Try bookings table first
$stmt = $conn->prepare("SELECT id_image FROM bookings WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$data = trim($row['id_image'] ?? '');

// If not in bookings, check temp_id_uploads by user_id of this booking
if (empty($data)) {
    $stmt2 = $conn->prepare("
        SELECT t.image_data FROM temp_id_uploads t
        JOIN bookings b ON t.user_id = b.user_id
        WHERE b.id = ?
        ORDER BY t.created_at DESC LIMIT 1
    ");
    $stmt2->bind_param("i", $booking_id);
    $stmt2->execute();
    $row2 = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();
    $data = trim($row2['image_data'] ?? '');
}

// If data is a token (32 hex chars), look it up in temp_id_uploads
if (empty($data) || (strlen($data) === 32 && ctype_xdigit($data))) {
    $token = $data ?: '';
    if (!empty($token)) {
        $stmt3 = $conn->prepare("SELECT image_data FROM temp_id_uploads WHERE token = ? LIMIT 1");
        $stmt3->bind_param("s", $token);
        $stmt3->execute();
        $row3 = $stmt3->get_result()->fetch_assoc();
        $stmt3->close();
        $data = trim($row3['image_data'] ?? '');
    }
}

if (empty($data)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'No ID image on file for this booking';
    exit;
}

// Output base64 data URL as image
if (strpos($data, 'data:') === 0) {
    if (preg_match('/^data:(image\/(jpeg|png|jpg));base64,(.+)$/s', $data, $m)) {
        $mime    = ($m[2] === 'jpg') ? 'image/jpeg' : $m[1];
        $imgdata = base64_decode($m[3], true);

        if ($imgdata === false || strlen($imgdata) === 0) {
            http_response_code(500);
            header('Content-Type: text/plain');
            echo 'Failed to decode image';
            exit;
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($imgdata));
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        ob_end_clean();
        echo $imgdata;
        exit;
    }
}

// Legacy file path fallback
$path = realpath(__DIR__ . '/../' . ltrim($data, '/'));
$allowed = realpath(__DIR__ . '/../uploads/ids/');
if ($path && $allowed && strpos($path, $allowed) === 0 && is_file($path)) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $path);
    finfo_close($finfo);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    ob_end_clean();
    readfile($path);
    exit;
}

http_response_code(404);
header('Content-Type: text/plain');
echo 'Image not available';
exit;
