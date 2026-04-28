<?php
session_start();
require_once __DIR__ . "/helpers/admindashboard_helpers.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$db       = new Database();
$conn     = $db->connect();

/* ── FILTERS ── */
$period = $_GET['period'] ?? 'all';
$status = $_GET['status'] ?? 'all';

$date_condition = '';
switch ($period) {
    case 'today': $date_condition = "AND DATE(o.created_at) = CURDATE()"; break;
    case 'week':  $date_condition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"; break;
    case 'month': $date_condition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"; break;
}

$status_condition = '';
if ($status === 'completed')  $status_condition = "AND o.order_status = 'Served'";
if ($status === 'cancelled')  $status_condition = "AND o.order_status = 'Cancelled'";

/* ── FETCH ORDERS ── */
$stmt = $conn->prepare("
    SELECT o.order_id, o.queue_number, o.order_status, o.total_amount, o.created_at,
           c.table_number, c.name AS customer_name,
           p.receipt_number, p.amount_paid, p.change_given, p.payment_status,
           GROUP_CONCAT(oi.item_name, ' ×', oi.quantity ORDER BY oi.item_name SEPARATOR ', ') AS items_summary
    FROM orders o
    LEFT JOIN customers c    ON o.customer_id  = c.customer_id
    LEFT JOIN payments p     ON p.order_id     = o.order_id
    LEFT JOIN order_items oi ON oi.order_id    = o.order_id
    WHERE o.admin_id = :admin_id
    $date_condition
    $status_condition
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
");
$stmt->bindParam(":admin_id", $admin_id);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ── TOTAL INCOME (served orders only, filtered period) ── */
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(p.amount_paid), 0) AS total_income,
           COUNT(o.order_id) AS total_orders
    FROM orders o
    LEFT JOIN payments p ON p.order_id = o.order_id
    WHERE o.admin_id = :admin_id
      AND o.order_status = 'Served'
      AND p.payment_status = 'Completed'
    $date_condition
");
$stmt->bindParam(":admin_id", $admin_id);
$stmt->execute();
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

$sidebar = new SidebarRenderer($admin_id, $_SESSION['fastfood_name'] ?? '');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Orders History</title>
    <link rel="stylesheet" href="../design/admin.css">
</head>
<body>

<div class="dashboard">

    <?= $sidebar->render('history') ?>

    <div class="main">

        <div class="topbar">
            <h1>🧾 Orders History</h1>
            <p class="subtitle">All orders — including cancelled</p>
        </div>

        <!-- SUMMARY CARDS -->
        <div class="cards" style="margin-top:20px;">
            <div class="card income">
                <h3>Total Income</h3>
                <p>₱<?= number_format($summary['total_income'], 2) ?></p>
            </div>
            <div class="card">
                <h3>Completed Orders</h3>
                <p><?= $summary['total_orders'] ?></p>
            </div>
            <div class="card">
                <h3>Orders Shown</h3>
                <p><?= count($orders) ?></p>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="content-box" style="margin-top:20px; padding:16px 22px;">
            <form method="GET" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">

                <div style="display:flex; gap:6px;">
                    <?php $periods = ['all' => 'All Time', 'today' => 'Today', 'week' => 'This Week', 'month' => 'This Month']; ?>
                    <?php foreach ($periods as $val => $label): ?>
                        <button type="submit" name="period" value="<?= $val ?>"
                            class="filter-tab <?= $period === $val ? 'active' : '' ?>"
                            formaction="?" onclick="this.form.period.value='<?= $val ?>'">
                            <?= $label ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div style="display:flex; gap:6px;">
                    <?php $statuses = ['all' => 'All Orders', 'completed' => '✅ Completed', 'cancelled' => '✕ Cancelled']; ?>
                    <?php foreach ($statuses as $val => $label): ?>
                        <button type="submit" name="status" value="<?= $val ?>"
                            class="filter-tab <?= $status === $val ? 'active' : '' ?>">
                            <?= $label ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Keep the other filter when switching -->
                <input type="hidden" name="period" value="<?= htmlspecialchars($period) ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
            </form>
        </div>

        <!-- ORDERS TABLE -->
        <div class="content-box" style="margin-top:16px; padding:0; overflow:hidden;">
            <?php if (empty($orders)): ?>
                <p class="empty-state" style="padding:30px;">No orders found for the selected filters.</p>
            <?php else: ?>
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Queue #</th>
                            <th>Table</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Cash Paid</th>
                            <th>Change</th>
                            <th>Receipt</th>
                            <th>Status</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><strong>#<?= htmlspecialchars($order['queue_number']) ?></strong></td>
                            <td>Table <?= htmlspecialchars($order['table_number'] ?? '—') ?></td>
                            <td style="font-size:12px; max-width:200px;">
                                <?= htmlspecialchars($order['items_summary'] ?? '—') ?>
                            </td>
                            <td>₱<?= number_format($order['total_amount'], 2) ?></td>
                            <td>₱<?= number_format($order['amount_paid'] ?? 0, 2) ?></td>
                            <td>₱<?= number_format($order['change_given'] ?? 0, 2) ?></td>
                            <td style="font-size:11px; color:#888;">
                                <?= htmlspecialchars($order['receipt_number'] ?? '—') ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= strtolower($order['order_status']) ?>">
                                    <?= htmlspecialchars($order['order_status']) ?>
                                </span>
                            </td>
                            <td style="font-size:12px;">
                                <?= date('M d, Y h:i A', strtotime($order['created_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- ================= PIN MODALS (required by sidebar) ================= -->
<?php include __DIR__ . '/helpers/pin_modals.php'; ?>

</body>
</html>
