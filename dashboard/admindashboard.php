<?php
session_start();
require_once __DIR__ . "/../validation.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$val  = new Validation();
$db   = new Database();
$conn = $db->connect();

$admin_id = $_SESSION['admin_id'];

/* =========================
   DEVICE COUNT (existing)
========================= */
$device_count = $val->countDevices($admin_id);

/* =========================
   TOTAL MENU ITEMS
========================= */
$total_menu = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM menu_items WHERE admin_id = :id");
    $stmt->bindParam(":id", $admin_id);
    $stmt->execute();
    $total_menu = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) { $total_menu = 0; }

/* =========================
   TOTAL ORDERS
========================= */
$total_orders = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE admin_id = :id");
    $stmt->bindParam(":id", $admin_id);
    $stmt->execute();
    $total_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) { $total_orders = 0; }

/* =========================
   PENDING / QUEUED ORDERS
========================= */
$pending_orders = 0;
try {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as total FROM orders
         WHERE admin_id = :id AND order_status IN ('Queued','Preparing')"
    );
    $stmt->bindParam(":id", $admin_id);
    $stmt->execute();
    $pending_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) { $pending_orders = 0; }

/* =========================
   TOTAL INCOME
========================= */
$total_income = 0.00;
try {
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(p.amount_paid), 0) as total
         FROM payments p
         JOIN orders o ON p.order_id = o.order_id
         WHERE o.admin_id = :id AND p.payment_status = 'Completed'"
    );
    $stmt->bindParam(":id", $admin_id);
    $stmt->execute();
    $total_income = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0.00;
} catch (Exception $e) { $total_income = 0.00; }

/* =========================
   TODAY'S INCOME
========================= */
$today_income = 0.00;
try {
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(p.amount_paid), 0) as total
         FROM payments p
         JOIN orders o ON p.order_id = o.order_id
         WHERE o.admin_id = :id
           AND p.payment_status = 'Completed'
           AND DATE(p.payment_date) = CURDATE()"
    );
    $stmt->bindParam(":id", $admin_id);
    $stmt->execute();
    $today_income = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0.00;
} catch (Exception $e) { $today_income = 0.00; }

/* =========================
   RECENT ORDERS (last 5)
========================= */
$recent_orders = [];
try {
    $stmt = $conn->prepare(
        "SELECT o.order_id, o.order_status, o.total_amount,
                o.queue_number, o.created_at,
                c.name AS customer_name, c.table_number
         FROM orders o
         LEFT JOIN customers c ON o.customer_id = c.customer_id
         WHERE o.admin_id = :id
         ORDER BY o.created_at DESC
         LIMIT 5"
    );
    $stmt->bindParam(":id", $admin_id);
    $stmt->execute();
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $recent_orders = []; }

/* =========================
   LOW STOCK (stock <= 5)
========================= */
$low_stock = [];
try {
    $stmt = $conn->prepare(
        "SELECT item_name, stock_quantity, category
         FROM menu_items
         WHERE admin_id = :id AND stock_quantity <= 5 AND is_available = 1
         ORDER BY stock_quantity ASC
         LIMIT 5"
    );
    $stmt->bindParam(":id", $admin_id);
    $stmt->execute();
    $low_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $low_stock = []; }

/* =========================
   TOP SELLING ITEMS (top 5)
========================= */
$top_items = [];
try {
    $stmt = $conn->prepare(
        "SELECT oi.item_name, SUM(oi.quantity) AS total_sold
         FROM order_items oi
         JOIN orders o ON oi.order_id = o.order_id
         WHERE o.admin_id = :id AND o.order_status != 'Cancelled'
         GROUP BY oi.item_name
         ORDER BY total_sold DESC
         LIMIT 5"
    );
    $stmt->bindParam(":id", $admin_id);
    $stmt->execute();
    $top_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $top_items = []; }
?>

<!DOCTYPE html>
<html>
<head>
    <title>iPOS Admin Dashboard</title>
    <link rel="stylesheet" href="../design/admin.css">
    <style>
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #222;
            margin-bottom: 14px;
            padding-bottom: 8px;
            border-bottom: 2px solid #ffcc00;
            display: inline-block;
        }
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
            margin-top: 25px;
        }
        .dash-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .dash-table th {
            background: linear-gradient(90deg, #ff0000, #ffcc00);
            color: white;
            padding: 9px 12px;
            text-align: left;
            font-weight: 500;
        }
        .dash-table td {
            padding: 9px 12px;
            border-bottom: 1px solid #f1f1f1;
            color: #444;
        }
        .dash-table tr:hover td { background: #fff8e6; }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-queued    { background: #fff3cd; color: #856404; }
        .badge-preparing { background: #cff4fc; color: #0c5460; }
        .badge-served    { background: #d1e7dd; color: #0a3622; }
        .badge-cancelled { background: #f8d7da; color: #842029; }
        .stock-low  { color: #dc3545; font-weight: 700; }
        .stock-zero { color: #dc3545; font-weight: 700; }
        .rank-num {
            display: inline-block;
            width: 24px; height: 24px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ff0000, #ffcc00);
            color: white;
            text-align: center;
            line-height: 24px;
            font-size: 12px;
            font-weight: 700;
            margin-right: 6px;
        }
        .empty-state {
            text-align: center;
            color: #aaa;
            padding: 20px;
            font-size: 13px;
        }
        .card.today {
            background: linear-gradient(135deg, #1a1a1a, #333);
            color: white;
            border: none;
        }
        .card.today h3,
        .card.today p { color: white; }
        @media (max-width: 900px) {
            .content-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>

<div class="dashboard">

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="logo">
            <h2>iPOS</h2>
            <p><?= htmlspecialchars($_SESSION['fastfood_name'] ?? '') ?></p>
        </div>
        <ul>
            <li class="active">📊 Dashboard</li>
           <li>
                    <a href="menu_list.php" style="text-decoration:none; color:inherit;">
                        🍔 Menu List
                    </a>
                </li>
            <li>🧾 Orders History</li>
            <li>⏳ Order Queue</li>
            <li>📦 Inventory</li>
            <li>🔄 Switch to User Dashboard</li>
        </ul>
        <a class="logout" href="../logout.php">Logout</a>
    </div>

    <!-- MAIN -->
    <div class="main">

        <!-- TOPBAR -->
        <div class="topbar">
            <h1>Welcome back, <?= htmlspecialchars($_SESSION['username'] ?? '') ?> 👋</h1>
            <p class="subtitle"><?= htmlspecialchars($_SESSION['fastfood_name'] ?? '') ?> · Admin Dashboard</p>
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
                <p>&#8369;<?= number_format($total_income, 2) ?></p>
            </div>

            <div class="card today">
                <h3>Today's Income</h3>
                <p>&#8369;<?= number_format($today_income, 2) ?></p>
            </div>

        </div>

        <!-- CONTENT GRID -->
        <div class="content-grid">

            <!-- RECENT ORDERS (spans full width) -->
            <div class="content-box" style="grid-column: 1 / -1;">
                <span class="section-title">🧾 Recent Orders</span>
                <?php if (!empty($recent_orders)): ?>
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Queue #</th>
                                <th>Customer</th>
                                <th>Table</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recent_orders as $order): ?>
                            <tr>
                                <td><strong>#<?= htmlspecialchars($order['queue_number']) ?></strong></td>
                                <td><?= htmlspecialchars($order['customer_name'] ?: 'Guest') ?></td>
                                <td><?= htmlspecialchars($order['table_number'] ?: '—') ?></td>
                                <td>&#8369;<?= number_format($order['total_amount'], 2) ?></td>
                                <td>
                                    <span class="badge badge-<?= strtolower(htmlspecialchars($order['order_status'])) ?>">
                                        <?= htmlspecialchars($order['order_status']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars(date('M d, h:i A', strtotime($order['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="empty-state">No orders yet. Orders will appear here once customers start placing them.</p>
                <?php endif; ?>
            </div>

            <!-- LOW STOCK ALERT -->
            <div class="content-box">
                <span class="section-title">⚠️ Low Stock Alert</span>
                <?php if (!empty($low_stock)): ?>
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Remaining</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($low_stock as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['item_name']) ?></td>
                                <td><?= htmlspecialchars($item['category']) ?></td>
                                <td class="<?= $item['stock_quantity'] == 0 ? 'stock-zero' : 'stock-low' ?>">
                                    <?= $item['stock_quantity'] == 0 ? 'OUT OF STOCK' : $item['stock_quantity'] . ' left' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="empty-state">&#10003; All items are well-stocked.</p>
                <?php endif; ?>
            </div>

            <!-- TOP SELLING ITEMS -->
            <div class="content-box">
                <span class="section-title">🏆 Top Selling Items</span>
                <?php if (!empty($top_items)): ?>
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Item</th>
                                <th>Units Sold</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($top_items as $i => $item): ?>
                            <tr>
                                <td><span class="rank-num"><?= $i + 1 ?></span></td>
                                <td><?= htmlspecialchars($item['item_name']) ?></td>
                                <td><strong><?= $item['total_sold'] ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="empty-state">No sales data yet.</p>
                <?php endif; ?>
            </div>

        </div><!-- end content-grid -->

    </div><!-- end .main -->

</div><!-- end .dashboard -->

</body>
</html>
