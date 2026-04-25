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

/* GET ITEM */
$stmt = $conn->prepare("SELECT * FROM menu_items WHERE menu_item_id = :id AND admin_id = :admin_id");
$stmt->bindParam(":id", $id);
$stmt->bindParam(":admin_id", $admin_id);
$stmt->execute();
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    die("Item not found or access denied.");
}

/* UPDATE */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $is_available = isset($_POST['is_available']) ? 1 : 0;

        $sql = "UPDATE menu_items SET
                item_name      = :name,
                description    = :desc,
                price          = :price,
                stock_quantity = :stock_quantity,
                category       = :category,
                is_available   = :is_available
                WHERE menu_item_id = :id AND admin_id = :admin_id";

        $stmt = $conn->prepare($sql);

        $stmt->bindParam(":name",           $_POST['item_name']);
        $stmt->bindParam(":desc",           $_POST['description']);
        $stmt->bindParam(":price",          $_POST['price']);
        $stmt->bindParam(":stock_quantity", $_POST['stock_quantity']);
        $stmt->bindParam(":category",       $_POST['category']);
        $stmt->bindParam(":is_available",   $is_available);
        $stmt->bindParam(":id",             $id);
        $stmt->bindParam(":admin_id",       $admin_id);

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
    <input name="stock_quantity" value="<?= htmlspecialchars($item['stock_quantity']) ?>"><br>
    <input name="category" value="<?= htmlspecialchars($item['category']) ?>"><br>
    <label>
        <input type="checkbox" name="is_available" <?= $item['is_available'] ? 'checked' : '' ?>>
        Available (visible to customers)
    </label><br>
    <button>Update</button>
</form>