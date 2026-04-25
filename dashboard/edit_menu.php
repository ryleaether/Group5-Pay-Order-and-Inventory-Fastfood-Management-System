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

/* ── GET: return item data as JSON for the edit modal ── */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {

    header('Content-Type: application/json');

    $stmt = $conn->prepare("SELECT * FROM menu_items WHERE menu_item_id = :id AND admin_id = :admin_id");
    $stmt->bindParam(":id",       $_GET['id']);
    $stmt->bindParam(":admin_id", $admin_id);
    $stmt->execute();
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item) {
        echo json_encode(['success' => true, 'item' => $item]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Item not found or access denied.']);
    }
    exit;
}

/* ── POST: save the updated item ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id           = $_POST['menu_item_id'] ?? null;
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    $image_url    = !empty($_POST['image_url']) ? trim($_POST['image_url']) : null;

    /* If no new image was uploaded, keep the existing one */
    if (empty($image_url)) {
        $stmt = $conn->prepare("SELECT image_url FROM menu_items WHERE menu_item_id = :id AND admin_id = :admin_id");
        $stmt->bindParam(":id",       $id);
        $stmt->bindParam(":admin_id", $admin_id);
        $stmt->execute();
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        $image_url = $existing['image_url'] ?? null;
    }

    $sql = "UPDATE menu_items SET
            item_name      = :name,
            description    = :desc,
            price          = :price,
            stock_quantity = :stock_quantity,
            category       = :category,
            is_available   = :is_available,
            image_url      = :image_url
            WHERE menu_item_id = :id AND admin_id = :admin_id";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(":name",           $_POST['item_name']);
    $stmt->bindParam(":desc",           $_POST['description']);
    $stmt->bindParam(":price",          $_POST['price']);
    $stmt->bindParam(":stock_quantity", $_POST['stock_quantity']);
    $stmt->bindParam(":category",       $_POST['category']);
    $stmt->bindParam(":is_available",   $is_available);
    $stmt->bindParam(":image_url",      $image_url);
    $stmt->bindParam(":id",             $id);
    $stmt->bindParam(":admin_id",       $admin_id);
    $stmt->execute();

    header("Location: menu_list.php");
    exit;
}
