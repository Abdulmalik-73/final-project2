<?php
/**
 * Secure ID image server
 * Reads base64 data URL from database and outputs as image.
 * Only accessible by receptionist/manager/admin/super_admin.
 */

// Buffer all output so headers can be sent cleanly
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load DB connection only — avoid full config.php which may output HTML
require_once '../includes/config.php';

// Discard any buffered output from config (whitespace, BOM, etc.)
ob_clean();

// Auth check
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

// Fetch id_image from database
$stmt = $conn->prepare("SELECT id_image FROM bookings WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || empty($row['id_image'])) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'No ID image on file for this booking';
    exit;
}

$data = trim($row['id_image']);

// ── Case 1: base64 data URL (new format) ─────────────────────────────────────
if (strpos($data, 'data:') === 0) {
    if (preg_match('/^data:(image\/(jpeg|png|jpg));base64,(.+)$/s', $data, $m)) {
        $mime    = ($m[2] === 'jpg') ? 'image/jpeg' : $m[1];
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
        // Explicitly NO Content-Disposition — browser displays inline
        ob_end_clean();
        echo $imgdata;
        exit;
    }
}

// ── Case 2: legacy file path ──────────────────────────────────────────────────
$path = realpath(__DIR__ . '/../' . ltrim($data, '/'));
$allowed_dir = realpath(__DIR__ . '/../uploads/ids/');

if ($path && $allowed_dir && strpos($path, $allowed_dir) === 0 && is_file($path)) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $path);
    finfo_close($finfo);

    if (!in_array($mime, ['image/jpeg', 'image/png'])) {
        http_response_code(415);
        header('Content-Type: text/plain');
        echo 'Invalid file type';
        exit;
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    ob_end_clean();
    readfile($path);
    exit;
}

http_response_code(404);
header('Content-Type: text/plain');
echo 'Image file not found on server';
exit;
