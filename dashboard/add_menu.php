<?php
session_start();
require_once __DIR__ . "/../validation.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$db       = new Database();
$conn     = $db->connect();
$admin_id = $_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $is_available = isset($_POST['is_available']) ? 1 : 0;

    /* image_url comes from the hidden field populated by the JS uploader */
    $image_url = !empty($_POST['image_url']) ? trim($_POST['image_url']) : null;

    $sql = "INSERT INTO menu_items
            (admin_id, item_name, description, price, stock_quantity, category, is_available, image_url)
            VALUES (:admin_id, :name, :desc, :price, :stock_quantity, :category, :is_available, :image_url)";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(":admin_id",      $admin_id);
    $stmt->bindParam(":name",          $_POST['item_name']);
    $stmt->bindParam(":desc",          $_POST['description']);
    $stmt->bindParam(":price",         $_POST['price']);
    $stmt->bindParam(":stock_quantity",$_POST['stock_quantity']);
    $stmt->bindParam(":category",      $_POST['category']);
    $stmt->bindParam(":is_available",  $is_available);
    $stmt->bindParam(":image_url",     $image_url);
    $stmt->execute();

    header("Location: menu_list.php");
    exit;
}
