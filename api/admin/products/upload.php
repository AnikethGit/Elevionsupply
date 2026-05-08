<?php
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/admin.php';
require_admin();

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);

// CSRF via header (JS fetch)
verify_csrf_api();

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    json_error('No file received or upload error: ' . ($_FILES['image']['error'] ?? 'none'));
}

$file     = $_FILES['image'];
$maxBytes = 5 * 1024 * 1024; // 5 MB

if ($file['size'] > $maxBytes) json_error('File too large. Maximum size is 5 MB.');

// Validate MIME type (don't trust extension alone)
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mime     = $finfo->file($file['tmp_name']);
$allowed  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
if (!isset($allowed[$mime])) json_error('Invalid file type. Allowed: JPG, PNG, GIF, WEBP.');

$ext      = $allowed[$mime];
$safeName = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_POST['slug'] ?? 'product')));
$safeName = substr($safeName, 0, 40) ?: 'product';
$filename = $safeName . '-' . uniqid() . '.' . $ext;
$destDir  = dirname(__DIR__, 3) . '/uploads/products/';
$destPath = $destDir . $filename;

if (!is_dir($destDir)) {
    mkdir($destDir, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    json_error('Failed to save file. Check server permissions on /uploads/products/.');
}

json_success(['path' => 'uploads/products/' . $filename], 'Image uploaded.');
