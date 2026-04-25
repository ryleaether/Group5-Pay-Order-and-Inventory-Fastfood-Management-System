<?php
session_start();
require_once __DIR__ . "/../validation.php";

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db       = new Database();
$conn     = $db->connect();
$admin_id = $_SESSION['admin_id'];

/* GET — check if admin has a PIN set */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $conn->prepare("SELECT dashboard_pin FROM admins WHERE admin_id = :id");
    $stmt->bindParam(":id", $admin_id);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['has_pin' => !empty($row['dashboard_pin'])]);
    exit;
}

/* POST — verify the entered PIN */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered = trim($_POST['pin'] ?? '');

    $stmt = $conn->prepare("SELECT dashboard_pin FROM admins WHERE admin_id = :id");
    $stmt->bindParam(":id", $admin_id);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && password_verify($entered, $row['dashboard_pin'])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}
