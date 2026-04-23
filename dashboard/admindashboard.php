<?php
session_start();
require_once "validation.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$val = new Validation();

/*SAFE DASHBOARD DATA */

/* ALWAYS SAFE DEVICE COUNT */
$device_count = $val->countDevices($_SESSION['admin_id'] ?? 0);

/* PLACEHOLDERS (NO TABLES YET) */
$total_menu = 0;
$total_orders = 0;
$pending_orders = 0;
$total_income = 0.00;
?>

<!DOCTYPE html>
<html>
<head>
    <title>iPOS Admin Dashboard</title>
    <link rel="stylesheet" href="../design/admin.css">
</head>

<body>

<div class="dashboard">

    <!-- SIDEBAR -->
    <div class="sidebar">

        <div class="logo">
            <h2>iPOS</h2>
            <p><?= $_SESSION['fastfood_name']; ?></p>
        </div>

        <ul>
            <li class="active">📊 Dashboard</li>
            <li>🍔 Menu List</li>
            <li>🧾 Orders History</li>
            <li>⏳ Order Queue</li>
            <li>📦 Inventory</li>
            <li>🔄 Switch to User Dashboard</li>
        </ul>

        <a class="logout" href="logout.php">Logout</a>

    </div>

    <!-- MAIN -->
    <div class="main">

        <!-- TOP -->
        <div class="topbar">
            <h1>Welcome back, <?= $_SESSION['username']; ?> 👋</h1>
            <p class="subtitle"><?= $_SESSION['fastfood_name']; ?> Dashboard</p>
        </div>

        <!-- STATS CARDS -->
        <div class="cards">

            <div class="card">
                <h3>Active Devices</h3>
                <p><?= $device_count ?></p>
            </div>

            <div class="card">
                <h3>Total Menu Items</h3>
                <p><?= $total_menu ?></p>
            </div>

            <div class="card">
                <h3>Total Orders</h3>
                <p><?= $total_orders ?></p>
            </div>

            <div class="card">
                <h3>Pending Orders</h3>
                <p><?= $pending_orders ?></p>
            </div>

            <div class="card income">
                <h3>Total Income</h3>
                <p>₱<?= number_format($total_income, 2) ?></p>
            </div>

        </div>

        <!-- CONTENT -->
        <div class="content-box">
            <h2>Dashboard Overview</h2>
            <p>Manage menu, orders, inventory, and sales in real time.</p>
        </div>

    </div>

</div>

</body>
</html>