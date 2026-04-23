<?php
/**
 * ID Image Upload API
 * Stores image as base64 data URL in the database — survives server restarts/redeploys.
 * Accepts: JPG, JPEG, PNG — Max 2MB
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/upload_id_errors.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login first.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

try {
    require_once '../includes/config.php';

    if (!isset($_FILES['id_image']) || $_FILES['id_image']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception('No file uploaded.');
    }

    $file = $_FILES['id_image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension.',
        ];
        throw new Exception($upload_errors[$file['error']] ?? 'Unknown upload error.');
    }

    // Max 2MB
    $max_size = 2 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        throw new Exception('File too large. Maximum size is 2MB.');
    }
    if ($file['size'] === 0) {
        throw new Exception('Uploaded file is empty.');
    }

    // Validate MIME type
    $allowed_mime = ['image/jpeg', 'image/jpg', 'image/png'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detected_mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($detected_mime, $allowed_mime)) {
        throw new Exception('Invalid file format. Only JPG, JPEG, PNG allowed.');
    }

    // Validate it's actually an image
    $image_info = @getimagesize($file['tmp_name']);
    if ($image_info === false) {
        throw new Exception('Uploaded file is not a valid image.');
    }

    // Read raw bytes and encode as base64 data URL
    $raw_data  = file_get_contents($file['tmp_name']);
    $mime_type = $detected_mime === 'image/jpg' ? 'image/jpeg' : $detected_mime;
    $base64    = 'data:' . $mime_type . ';base64,' . base64_encode($raw_data);

    // Ensure id_image column exists and is large enough (MEDIUMTEXT = 16MB)
    $conn->query("ALTER TABLE bookings MODIFY COLUMN id_image MEDIUMTEXT DEFAULT NULL");

    // Store base64 in session for the booking form to use
    $_SESSION['pending_id_image'] = $base64;

    $file_size_kb = round($file['size'] / 1024, 1);

    echo json_encode([
        'success'        => true,
        'message'        => 'ID uploaded successfully.',
        'file_path'      => $base64,   // returned to JS, stored in hidden field
        'file_name'      => $file['name'],
        'file_size'      => $file_size_kb . ' KB',
        'preview_base64' => $base64,   // JS uses this for thumbnail preview
    ]);

} catch (Exception $e) {
    error_log("ID Upload Error (user " . ($_SESSION['user_id'] ?? 'unknown') . "): " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
}
