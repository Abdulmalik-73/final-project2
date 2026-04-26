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

// Load database configuration from environment variables
$env_file = __DIR__ . '/../.env';
if (file_exists($env_file)) {
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k); $v = trim($v, " \t\n\r\"'");
        if (!defined($k)) define($k, $v);
    }
}

// Get database credentials from environment variables (Render uses these)
$db_host = getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : 'localhost');
$db_user = getenv('DB_USER') ?: (defined('DB_USER') ? DB_USER : 'root');
$db_pass = getenv('DB_PASS') ?: (defined('DB_PASS') ? DB_PASS : '');
$db_name = getenv('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : '');
$db_port = getenv('DB_PORT') ?: (defined('DB_PORT') ? DB_PORT : '3306');

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($db_host, $db_user, $db_pass, $db_name, (int)$db_port);

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

    // Create uploads/ids directory if it doesn't exist
    $upload_dir = __DIR__ . '/../uploads/ids/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception('Failed to create upload directory.');
        }
    }

    // Generate unique filename
    $extension = ($mime === 'image/jpg' || $mime === 'image/jpeg') ? 'jpg' : 'png';
    $timestamp = time();
    $random = bin2hex(random_bytes(4)); // 8 characters
    $filename = "id_{$user_id}_{$timestamp}_{$random}.{$extension}";
    $filepath = $upload_dir . $filename;

    // Move uploaded file to permanent location
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to save uploaded file.');
    }

    // Read file and convert to base64 for database storage
    // (Render doesn't persist files between deployments, so we store base64 in DB)
    $raw = file_get_contents($filepath);
    if ($raw === false) {
        @unlink($filepath);
        throw new Exception('Failed to read uploaded file.');
    }
    $mime_out = ($mime === 'image/jpg' || $mime === 'image/jpeg') ? 'image/jpeg' : 'image/png';
    $base64_data = 'data:' . $mime_out . ';base64,' . base64_encode($raw);

    // Delete the file after converting to base64 (not needed on disk)
    @unlink($filepath);

    // Store file path temporarily for this user (will be moved to booking when created)
    // First, ensure temp_id_uploads table exists
    $create_table_sql = "
        CREATE TABLE IF NOT EXISTS temp_id_uploads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(32) NOT NULL UNIQUE,
            image_data MEDIUMTEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_token (token),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $conn->query($create_table_sql);
    
    // Clean up any old temporary uploads for this user and system-wide old entries
    $cleanup_stmt = $conn->prepare("DELETE FROM temp_id_uploads WHERE user_id = ? OR created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    if ($cleanup_stmt) {
        $cleanup_stmt->bind_param("i", $user_id);
        $cleanup_stmt->execute();
        $cleanup_stmt->close();
    }

    // Generate a unique token for this upload
    $token = bin2hex(random_bytes(16)); // 32-character hex string

    // Store base64 data in database (not filename)
    $temp_stmt = $conn->prepare("INSERT INTO temp_id_uploads (user_id, token, image_data, created_at) VALUES (?, ?, ?, NOW())");
    if (!$temp_stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    $temp_stmt->bind_param("iss", $user_id, $token, $base64_data);
    if (!$temp_stmt->execute()) {
        throw new Exception('Failed to save image: ' . $temp_stmt->error);
    }
    $temp_stmt->close();

    // Use the base64 data as preview
    $base64 = $base64_data;

    echo json_encode([
        'success'   => true,
        'message'   => 'ID uploaded successfully.',
        'file_path' => $token,  // Return the token
        'file_name' => $file['name'],
        'file_size' => round($file['size'] / 1024, 1) . ' KB',
        'preview'   => $base64,  // Keep preview for display
    ]);

} catch (Exception $e) {
    error_log("ID Upload Error (user=$user_id): " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


