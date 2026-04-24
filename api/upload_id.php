<?php
/**
 * ID Image Upload API
 * Saves image files to /uploads/ids/ directory and stores filename in database
 */

// ── 1. Start session ──────────────────────────────────────────────────────────
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

// ── 2. Always output JSON ─────────────────────────────────────────────────────
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

// ── 3. Auth check ─────────────────────────────────────────────────────────────
$user_id = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
if ($user_id <= 0) {
    $user_id = (int)($_POST['uid'] ?? $_GET['uid'] ?? 0);
}
if ($user_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Session not found. Please log out and log in again, then try uploading.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

// ── 4. Connect to DB ──────────────────────────────────────────────────────────
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
    echo json_encode(['success' => false, 'error' => 'Database unavailable. Please try again in a moment.']);
    exit;
}
$conn->set_charset('utf8mb4');

// ── 5. Create uploads directory ───────────────────────────────────────────────
$uploads_dir = __DIR__ . '/../uploads/ids';
if (!is_dir($uploads_dir)) {
    if (!@mkdir($uploads_dir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Failed to create upload directory.']);
        exit;
    }
}

// ── 6. Validate uploaded file ─────────────────────────────────────────────────
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
        throw new Exception($msgs[$file['error']] ?? 'Upload error: ' . $file['error']);
    }

    if ($file['size'] > 2 * 1024 * 1024) throw new Exception('File too large. Maximum 2MB.');
    if ($file['size'] === 0)              throw new Exception('Uploaded file is empty.');

    // Validate MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, ['image/jpeg', 'image/jpg', 'image/png'])) {
        throw new Exception('Invalid format. Only JPG, JPEG, PNG allowed.');
    }
    if (@getimagesize($file['tmp_name']) === false) {
        throw new Exception('File is not a valid image.');
    }

    // ── 7. Generate unique filename ───────────────────────────────────────────
    $ext = ($mime === 'image/png') ? 'png' : 'jpg';
    $filename = 'id_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $filepath = $uploads_dir . '/' . $filename;

    // ── 8. Move uploaded file ─────────────────────────────────────────────────
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to save file to disk.');
    }

    // ── 9. Create base64 preview for immediate display ────────────────────────
    $raw      = file_get_contents($filepath);
    $mime_out = ($mime === 'image/jpg' || $mime === 'image/jpeg') ? 'image/jpeg' : 'image/png';
    $preview  = 'data:' . $mime_out . ';base64,' . base64_encode($raw);

    // ── 10. Store filename in database ────────────────────────────────────────
    $stmt = $conn->prepare("UPDATE bookings SET id_image = ? WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("si", $filename, $user_id);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode([
        'success'   => true,
        'message'   => 'ID uploaded successfully.',
        'file_path' => $filename,
        'file_name' => $file['name'],
        'file_size' => round($file['size'] / 1024, 1) . ' KB',
        'preview'   => $preview,
    ]);

} catch (Exception $e) {
    error_log("ID Upload Error (user=$user_id): " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

