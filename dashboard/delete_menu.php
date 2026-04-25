<?php
session_start();
require_once __DIR__ . "/../validation.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$db   = new Database();
$conn = $db->connect();

$admin_id = $_SESSION['admin_id'];
$id       = $_GET['id'] ?? null;

if ($id) {
    $stmt = $conn->prepare("DELETE FROM menu_items WHERE menu_item_id = :id AND admin_id = :admin_id");
    $stmt->bindParam(":id",       $id);
    $stmt->bindParam(":admin_id", $admin_id);
    $stmt->execute();
}

header("Location: menu_list.php");
exit;