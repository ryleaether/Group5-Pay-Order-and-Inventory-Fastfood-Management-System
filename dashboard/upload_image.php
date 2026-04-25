<?php
session_start();
require_once __DIR__ . "/../validation.php";

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
    exit;
}

$allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
$ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowed_extensions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, WEBP allowed.']);
    exit;
}

if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File too large. Max 2MB.']);
    exit;
}

$upload_dir = __DIR__ . "/../uploads/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$ext       = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
$filename  = 'item_' . $_SESSION['admin_id'] . '_' . time() . '.' . $ext;
$filepath  = $upload_dir . $filename;

if (move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
    echo json_encode([
        'success'  => true,
        'filename' => $filename,
        'url'      => '../uploads/' . $filename
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save file.']);
}
