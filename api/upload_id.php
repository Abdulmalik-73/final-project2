<?php
/**
 * ID Image Upload API
 * Saves image as base64 to temp_id_uploads table, returns a 32-char token.
 */

// Buffer all output so config.php whitespace doesn't corrupt JSON
ob_start();

// Load config first — it handles session_start() with correct settings
require_once '../includes/config.php';

// Discard any output from config (whitespace, BOM, maintenance HTML check)
ob_clean();

// Always return JSON for this endpoint
header('Content-Type: application/json');

// Auth check — user must be logged in
$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    // Try alternative session key names
    $user_id = (int)($_SESSION['id'] ?? 0);
}
if ($user_id <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please refresh the page and try uploading again.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'error' => 'Database unavailable. Please try again.']);
    exit;
}

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
        throw new Exception($msgs[$file['error']] ?? 'Upload error code: ' . $file['error']);
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        throw new Exception('File too large. Maximum size is 2MB.');
    }
    if ($file['size'] === 0) {
        throw new Exception('Uploaded file is empty.');
    }

    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, ['image/jpeg', 'image/jpg', 'image/png'])) {
        throw new Exception('Invalid format. Only JPG, JPEG, PNG allowed.');
    }

    if (@getimagesize($file['tmp_name']) === false) {
        throw new Exception('File is not a valid image.');
    }

    // Encode as base64 data URL
    $raw      = file_get_contents($file['tmp_name']);
    $mime_out = ($mime === 'image/jpg') ? 'image/jpeg' : $mime;
    $base64   = 'data:' . $mime_out . ';base64,' . base64_encode($raw);

    // Ensure temp_id_uploads table exists
    $conn->query("CREATE TABLE IF NOT EXISTS `temp_id_uploads` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `token` VARCHAR(64) NOT NULL UNIQUE,
        `user_id` INT NOT NULL,
        `image_data` MEDIUMTEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_token` (`token`),
        INDEX `idx_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Generate unique 32-char token
    $token = bin2hex(random_bytes(16));

    // Remove any previous upload for this user
    $del = $conn->prepare("DELETE FROM temp_id_uploads WHERE user_id = ?");
    $del->bind_param("i", $user_id);
    $del->execute();

    // Save new upload
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
