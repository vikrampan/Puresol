<?php
/**
 * Puresol Image Upload API
 *
 * POST /api/upload.php — Upload a product image (admin only)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(null, 405, 'Method not allowed.');
}

require_admin_auth();

if (!verify_csrf_token()) {
    json_response(null, 403, 'Invalid or missing CSRF token.');
}

// Check that a file was uploaded
if (empty($_FILES['image'])) {
    json_response(null, 400, 'No image file provided. Use form field name "image".');
}

$file = $_FILES['image'];

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $error_messages = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'Upload stopped by a PHP extension.',
    ];

    $msg = $error_messages[$file['error']] ?? 'Unknown upload error.';
    json_response(null, 400, $msg);
}

// Validate file size (max 5 MB)
$max_size = 5 * 1024 * 1024;
if ($file['size'] > $max_size) {
    json_response(null, 400, 'File size exceeds 5 MB limit.');
}

// Validate MIME type
$allowed_types = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

$finfo     = new finfo(FILEINFO_MIME_TYPE);
$mime_type = $finfo->file($file['tmp_name']);

if (!isset($allowed_types[$mime_type])) {
    json_response(null, 400, 'Invalid file type. Allowed: JPG, PNG, WebP.');
}

$extension = $allowed_types[$mime_type];

// Verify actual image dimensions (extra safety check)
$image_info = getimagesize($file['tmp_name']);
if ($image_info === false) {
    json_response(null, 400, 'Uploaded file is not a valid image.');
}

// Ensure upload directory exists
$upload_dir = UPLOAD_DIR;
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        json_response(null, 500, 'Failed to create upload directory.');
    }
}

// Generate unique filename
$unique_name = 'puresol_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
$dest_path   = $upload_dir . $unique_name;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
    json_response(null, 500, 'Failed to save uploaded file.');
}

$image_url = UPLOAD_URL . $unique_name;

log_activity('image_uploaded', "file={$unique_name}");

json_response([
    'filename'  => $unique_name,
    'url'       => $image_url,
    'size'      => $file['size'],
    'mime_type' => $mime_type,
    'width'     => $image_info[0],
    'height'    => $image_info[1],
], 201, 'Image uploaded successfully.');
