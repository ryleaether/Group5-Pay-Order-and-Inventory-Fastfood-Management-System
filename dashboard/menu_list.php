<?php
session_start();

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../validation.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$db = new Database();
$conn = $db->connect();

$admin_id = $_SESSION['admin_id'];

$stmt = $conn->prepare("
    SELECT * 
    FROM menu_items 
    WHERE admin_id = :id 
    ORDER BY created_at DESC
");
$stmt->bindParam(":id", $admin_id);
$stmt->execute();

$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Menu List</title>
    <link rel="stylesheet" href="../design/admin.css">
    <script src="../design/script.js"></script>
</head>

<body>

<div class="dashboard">

    <!-- SIDEBAR (same as admin dashboard) -->
    <div class="sidebar">
        <div class="logo">
            <h2>iPOS</h2>
            <p><?= htmlspecialchars($_SESSION['fastfood_name'] ?? '') ?></p>
        </div>

        <ul>
            <li>
                <a href="admindashboard.php" style="text-decoration:none; color:inherit;">
                    📊 Dashboard
                </a>
            </li>

            <li class="active">
                🍔 Menu List
            </li>

            <li>🧾 Orders History</li>
            <li>⏳ Order Queue</li>
            <li>📦 Inventory</li>
        </ul>

        <a class="logout" href="../login.php">Logout</a>
    </div>

    <!-- MAIN -->
    <div class="main">

        <div class="topbar">
            <h1>🍔 Menu Items</h1>
            <p class="subtitle">Manage your food items</p>

            <a href="#" class="btn-add" onclick="openModal()" style="
                display:inline-block;
                margin-top:15px;
                padding:10px 14px;
                background:linear-gradient(90deg,#ff0000,#ffcc00);
                color:#fff;
                border-radius:10px;
                text-decoration:none;
                font-weight:600;
            ">
                + Add New Item
            </a>
        </div>

        <!-- MENU GRID -->
        <div class="menu-grid">

            <?php if (!empty($items)): ?>
                <?php foreach ($items as $item): ?>

                    <div class="menu-card">

                        <!-- HEADER -->
                        <div class="menu-header">
                            <h3><?= htmlspecialchars($item['item_name']) ?></h3>

                            <span class="status <?= $item['is_available'] ? 'available' : 'unavailable' ?>">
                                <?= $item['is_available'] ? 'Available' : 'Unavailable' ?>
                            </span>
                        </div>

                        <!-- CATEGORY -->
                        <div class="category">
                            <?= htmlspecialchars($item['category']) ?>
                        </div>

                        <!-- PRICE -->
                        <div class="price">
                            ₱<?= number_format($item['price'], 2) ?>
                        </div>

                        <!-- STOCK -->
                        <div class="stock">
                            Stock: <?= $item['stock_quantity'] ?>
                        </div>

                        <!-- ACTIONS -->
                        <div class="actions">
                            <a class="btn edit" href="edit_menu.php?id=<?= $item['menu_item_id'] ?>">
                                Edit
                            </a>

                            <a class="btn delete"
                               href="delete_menu.php?id=<?= $item['menu_item_id'] ?>"
                               onclick="return confirm('Delete this item?')">
                                Delete
                            </a>
                        </div>

                    </div>

                <?php endforeach; ?>
            <?php else: ?>

                <p style="color:#888;">No menu items found.</p>

            <?php endif; ?>

        </div>

    </div>

</div>
<!-- ================= ADD MENU MODAL ================= -->
<div id="menuModal" class="modal">

    <div class="modal-content">

        <span class="close" onclick="closeModal()">&times;</span>

        <h2>🍔 Add New Menu Item</h2>

        <form action="add_menu.php" method="POST">

            <input type="text" name="item_name" placeholder="Item Name" required>

            <textarea name="description" placeholder="Description"></textarea>

            <input type="number" step="0.01" name="price" placeholder="Price" required>

            <input type="number" name="stock_quantity" placeholder="Stock Quantity" required>

            <input type="text" name="category" placeholder="Category" required>

            <label style="display:block; margin-top:10px;">
                <input type="checkbox" name="is_available" checked>
                Available
            </label>

            <button type="submit" class="btn-save">Save Item</button>

        </form>

    </div>

</div>
</body>
</html>