<?php
session_start();
require_once __DIR__ . "/../validation.php";

$db = new Database();
$conn = $db->connect();

$id = $_GET['id'] ?? null;

/* GET ITEM */
$stmt = $conn->prepare("SELECT * FROM menu_items WHERE menu_item_id = :id");
$stmt->bindParam(":id", $id);
$stmt->execute();
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    die("Item not found");
}

/* UPDATE */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $sql = "UPDATE menu_items SET
            item_name = :name,
            description = :desc,
            price = :price,
            stock_quantity = :stock,
            category = :category
            WHERE menu_item_id = :id";

    $stmt = $conn->prepare($sql);

    $stmt->bindParam(":name", $_POST['item_name']);
    $stmt->bindParam(":desc", $_POST['description']);
    $stmt->bindParam(":price", $_POST['price']);
    $stmt->bindParam(":stock", $_POST['stock']);
    $stmt->bindParam(":category", $_POST['category']);
    $stmt->bindParam(":id", $id);

    $stmt->execute();

    header("Location: menu_list.php");
    exit;
}
?>

<h2>Edit Menu Item</h2>

<form method="POST">
    <input name="item_name" value="<?= $item['item_name'] ?>"><br>
    <textarea name="description"><?= $item['description'] ?></textarea><br>
    <input name="price" value="<?= $item['price'] ?>"><br>
    <input name="stock" value="<?= $item['stock_quantity'] ?>"><br>
    <input name="category" value="<?= $item['category'] ?>"><br>
    <button>Update</button>
</form>