<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->connect();
try {
    $conn->exec('ALTER TABLE admins ADD COLUMN theme_data JSON NULL');
    echo 'theme_data column added successfully.';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>