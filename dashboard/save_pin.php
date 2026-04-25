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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = trim($_POST['pin'] ?? '');

    if (strlen($pin) !== 4 || !ctype_digit($pin)) {
        echo json_encode(['success' => false, 'message' => 'PIN must be exactly 4 digits.']);
        exit;
    }

    $hashed = password_hash($pin, PASSWORD_BCRYPT);

    $stmt = $conn->prepare("UPDATE admins SET dashboard_pin = :pin WHERE admin_id = :id");
    $stmt->bindParam(":pin", $hashed);
    $stmt->bindParam(":id",  $admin_id);
    $stmt->execute();

    echo json_encode(['success' => true]);
    exit;
}
