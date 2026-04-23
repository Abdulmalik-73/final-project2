<?php
/**
 * ID Image Upload API — standalone, no config.php dependency
 * Saves image as base64 to temp_id_uploads table, returns a 32-char token.
 */

// ── 1. Start session using same settings as config.php ───────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.cookie_lifetime', 86400);
    ini_set('session.cookie_samesite', 'Lax');

    // Use same save path as config.php
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
// Fallback: accept uid from POST/GET if session is not available
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

// ── 4. Connect to DB directly (no config.php) ─────────────────────────────────
$env_file = __DIR__ . '/../.env';
if (file_exists($env_file)) {
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k); $v = trim($v, " \t\n\r\"'");
        if (!defined($k)) define($k, $v);
    }
}
// Also read from system env (Render sets these)
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

// ── 5. Validate uploaded file ─────────────────────────────────────────────────
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

    // ── 6. Encode as base64 ───────────────────────────────────────────────────
    $raw      = file_get_contents($file['tmp_name']);
    $mime_out = ($mime === 'image/jpg') ? 'image/jpeg' : $mime;
    $base64   = 'data:' . $mime_out . ';base64,' . base64_encode($raw);

    // ── 7. Ensure temp table exists ───────────────────────────────────────────
    $conn->query("CREATE TABLE IF NOT EXISTS `temp_id_uploads` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `token` VARCHAR(64) NOT NULL UNIQUE,
        `user_id` INT NOT NULL,
        `image_data` MEDIUMTEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_token` (`token`),
        INDEX `idx_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── 8. Save token + image ─────────────────────────────────────────────────
    $token = bin2hex(random_bytes(16));

    $del = $conn->prepare("DELETE FROM temp_id_uploads WHERE user_id = ?");
    $del->bind_param("i", $user_id);
    $del->execute();

    $ins = $conn->prepare("INSERT INTO temp_id_uploads (token, user_id, image_data) VALUES (?, ?, ?)");
    $ins->bind_param("sis", $token, $user_id, $base64);
    if (!$ins->execute()) {
        throw new Exception('Failed to save image: ' . $ins->error);
    }

    $_SESSION['pending_id_token'] = $token;

    echo json_encode([
        'success'   => true,
        'message'   => 'ID uploaded successfully.',
        'file_path' => $token,
        'file_name' => $file['name'],
        'file_size' => round($file['size'] / 1024, 1) . ' KB',
        'preview'   => $base64,
    ]);

} catch (Exception $e) {
    error_log("ID Upload Error (user=$user_id): " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
