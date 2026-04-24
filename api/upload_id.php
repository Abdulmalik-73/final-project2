<?php
/**
 * ID Image Upload API
 * Saves image as base64 in database (Render doesn't persist files)
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.cookie_lifetime', 86400);
    ini_set('session.cookie_samesite', 'Lax');

    $session_path = sys_get_temp_dir() . '/php_sessions';
    if (!is_dir($session_path)) {
        @mkdir($session_path, 0777, true);
    }
    ini_set('session.save_path', $session_path);

    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    ini_set('session.cookie_secure', $is_https ? 1 : 0);

    session_start();
}

header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

$user_id = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
if ($user_id <= 0) {
    $user_id = (int)($_POST['uid'] ?? $_GET['uid'] ?? 0);
}
if ($user_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Session not found. Please log out and log in again.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$env_file = __DIR__ . '/../.env';
if (file_exists($env_file)) {
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k); $v = trim($v, " \t\n\r\"'");
        if (!defined($k)) define($k, $v);
    }
}
foreach (['DB_HOST','DB_PORT','DB_USER','DB_PASS','DB_NAME'] as $k) {
    $v = getenv($k);
    if ($v !== false && !defined($k)) define($k, $v);
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli(
    defined('DB_HOST') ? DB_HOST : 'localhost',
    defined('DB_USER') ? DB_USER : 'root',
    defined('DB_PASS') ? DB_PASS : '',
    defined('DB_NAME') ? DB_NAME : '',
    defined('DB_PORT') ? (int)DB_PORT : 3306
);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database unavailable.']);
    exit;
}
$conn->set_charset('utf8mb4');

try {
    if (!isset($_FILES['id_image']) || $_FILES['id_image']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception('No file uploaded.');
    }

    $file = $_FILES['id_image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msgs = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form limit.',
            UPLOAD_ERR_PARTIAL    => 'File only partially uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by server.',
        ];
        throw new Exception($msgs[$file['error']] ?? 'Upload error');
    }

    if ($file['size'] > 2 * 1024 * 1024) throw new Exception('File too large. Maximum 2MB.');
    if ($file['size'] === 0) throw new Exception('Uploaded file is empty.');

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, ['image/jpeg', 'image/jpg', 'image/png'])) {
        throw new Exception('Invalid format. Only JPG, JPEG, PNG allowed.');
    }
    if (@getimagesize($file['tmp_name']) === false) {
        throw new Exception('File is not a valid image.');
    }

    // Read file and encode as base64
    $raw      = file_get_contents($file['tmp_name']);
    $mime_out = ($mime === 'image/jpg' || $mime === 'image/jpeg') ? 'image/jpeg' : 'image/png';
    $base64   = 'data:' . $mime_out . ';base64,' . base64_encode($raw);

    // Find the most recent pending booking for this user
    $find_booking = $conn->prepare("
        SELECT id FROM bookings 
        WHERE user_id = ? 
        AND status IN ('pending', 'confirmed', 'checked_in')
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    if (!$find_booking) {
        throw new Exception('Database error: ' . $conn->error);
    }
    $find_booking->bind_param("i", $user_id);
    $find_booking->execute();
    $booking_result = $find_booking->get_result();
    $booking_row = $booking_result->fetch_assoc();
    $find_booking->close();

    if (!$booking_row) {
        throw new Exception('No active booking found for this user. Please create a booking first.');
    }

    $booking_id = $booking_row['id'];

    // Update the booking with the base64 image
    $update_stmt = $conn->prepare("UPDATE bookings SET id_image = ? WHERE id = ?");
    if (!$update_stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    $update_stmt->bind_param("si", $base64, $booking_id);
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to save image: ' . $update_stmt->error);
    }
    $update_stmt->close();

    echo json_encode([
        'success'   => true,
        'message'   => 'ID uploaded successfully.',
        'file_path' => 'base64',
        'file_name' => $file['name'],
        'file_size' => round($file['size'] / 1024, 1) . ' KB',
        'preview'   => $base64,
    ]);

} catch (Exception $e) {
    error_log("ID Upload Error (user=$user_id): " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


