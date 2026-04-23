<?php
/**
 * Secure ID image server for receptionist panel
 * Only accessible by receptionist/manager/admin roles
 * Serves the image directly from disk with correct headers
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/config.php';

// Auth: only staff roles
$role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
if (!in_array($role, ['receptionist', 'manager', 'admin', 'super_admin'])) {
    http_response_code(403);
    exit('Access denied');
}

$file = $_GET['file'] ?? '';

// Strict validation — only allow filenames from uploads/ids/
if (empty($file) || !preg_match('/^id_\d+_\d+_[a-zA-Z0-9._]+\.(jpg|jpeg|png)$/i', $file)) {
    http_response_code(400);
    exit('Invalid file');
}

$path = __DIR__ . '/../uploads/ids/' . $file;

if (!file_exists($path) || !is_file($path)) {
    http_response_code(404);
    exit('File not found');
}

// Detect MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $path);
finfo_close($finfo);

if (!in_array($mime, ['image/jpeg', 'image/png', 'image/jpg'])) {
    http_response_code(415);
    exit('Invalid file type');
}

// Serve the image
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
