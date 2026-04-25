<?php
session_start();
require_once __DIR__ . "/../validation.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$db = new Database();
$conn = $db->connect();

$admin_id = $_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $sql = "INSERT INTO menu_items
        (admin_id, item_name, description, price, stock_quantity, category, is_available)
        VALUES (:admin_id, :name, :desc, :price, :stock_quantity, :category, :is_available)";

        $stmt = $conn->prepare($sql);

        $is_available = isset($_POST['is_available']) ? 1 : 0;

        $stmt->bindParam(":admin_id",      $admin_id);
        $stmt->bindParam(":name",          $_POST['item_name']);
        $stmt->bindParam(":desc",          $_POST['description']);
        $stmt->bindParam(":price",         $_POST['price']);
        $stmt->bindParam(":stock_quantity",$_POST['stock_quantity']);
        $stmt->bindParam(":category",      $_POST['category']);
        $stmt->bindParam(":is_available",  $is_available);

    $stmt->execute();

    header("Location: menu_list.php");
    exit;
}
?>

<h2>Add Menu Item</h2>

<form method="POST">
    <input name="item_name" placeholder="Item Name" required><br>
    <textarea name="description" placeholder="Description"></textarea><br>
    <input name="price" type="number" step="0.01" placeholder="Price" required><br>
    <input name="stock" type="number" placeholder="Stock" required><br>
    <input name="category" placeholder="Category"><br>
    <button type="submit">Add Item</button>
</form>