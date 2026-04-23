<?php
/**
 * ID Image Upload API
 * Handles secure upload of customer ID images (National ID / Passport / Driving License)
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

// Auth check
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login first.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

try {
    // ── Validate file was sent ────────────────────────────────────────────────
    if (!isset($_FILES['id_image']) || $_FILES['id_image']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception('No file uploaded.');
    }

    $file = $_FILES['id_image'];

    // ── Check for upload errors ───────────────────────────────────────────────
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

    // ── Validate file size (max 2MB) ──────────────────────────────────────────
    $max_size = 2 * 1024 * 1024; // 2MB in bytes
    if ($file['size'] > $max_size) {
        throw new Exception('File too large. Maximum size is 2MB.');
    }

    if ($file['size'] === 0) {
        throw new Exception('Uploaded file is empty.');
    }

    // ── Validate MIME type (server-side, not just extension) ──────────────────
    $allowed_mime = ['image/jpeg', 'image/jpg', 'image/png'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detected_mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($detected_mime, $allowed_mime)) {
        throw new Exception('Invalid file format. Only JPG, JPEG, PNG allowed.');
    }

    // ── Validate file extension ───────────────────────────────────────────────
    $original_name = $file['name'];
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png'];

    if (!in_array($ext, $allowed_ext)) {
        throw new Exception('Invalid file format. Only JPG, JPEG, PNG allowed.');
    }

    // ── Double-check: verify it's actually an image ───────────────────────────
    $image_info = @getimagesize($file['tmp_name']);
    if ($image_info === false) {
        throw new Exception('Uploaded file is not a valid image.');
    }

    // ── Ensure upload directory exists ────────────────────────────────────────
    $upload_dir = __DIR__ . '/../uploads/ids/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception('Failed to create upload directory.');
        }
    }

    // ── Generate unique filename ──────────────────────────────────────────────
    $user_id   = (int)$_SESSION['user_id'];
    $timestamp = time();
    $unique_id = uniqid('id_', true);
    $new_filename = 'id_' . $user_id . '_' . $timestamp . '_' . $unique_id . '.' . $ext;
    $destination = $upload_dir . $new_filename;

    // ── Move uploaded file ────────────────────────────────────────────────────
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Failed to save uploaded file. Please try again.');
    }

    // ── Store relative path (for DB and display) ──────────────────────────────
    $relative_path = 'uploads/ids/' . $new_filename;

    // Store in session so booking form can use it on submit
    $_SESSION['pending_id_image'] = $relative_path;

    echo json_encode([
        'success'   => true,
        'message'   => 'ID uploaded successfully.',
        'file_path' => $relative_path,
        'file_name' => $new_filename,
        'file_size' => round($file['size'] / 1024, 1) . ' KB',
    ]);

} catch (Exception $e) {
    error_log("ID Upload Error (user {$_SESSION['user_id']}): " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
}
