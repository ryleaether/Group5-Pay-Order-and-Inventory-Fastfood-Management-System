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

/* Fetch active orders oldest first */
$stmt = $conn->prepare("
    SELECT o.order_id, o.queue_number, o.order_status, o.total_amount, o.created_at,
           c.table_number, c.name AS customer_name,
           GROUP_CONCAT(oi.item_name, ' x', oi.quantity ORDER BY oi.item_name SEPARATOR ', ') AS items_summary
    FROM orders o
    LEFT JOIN customers c   ON o.customer_id = c.customer_id
    LEFT JOIN order_items oi ON o.order_id   = oi.order_id
    WHERE o.admin_id = :admin_id
      AND o.order_status IN ('Queued', 'Preparing')
    GROUP BY o.order_id
    ORDER BY o.created_at ASC
");
$stmt->bindParam(":admin_id", $admin_id);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sidebar = new SidebarRenderer($admin_id, $_SESSION['fastfood_name'] ?? '');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Order Queue</title>
    <link rel="stylesheet" href="../design/admin.css">
</head>
<body>

<div class="dashboard">

    <?= $sidebar->render('queue') ?>

    <div class="main">

        <div class="topbar">
            <h1>⏳ Order Queue</h1>
            <p class="subtitle">Active orders — oldest first</p>
            <button class="btn-add" onclick="location.reload()" style="margin-top:12px;">
                🔄 Refresh
            </button>
        </div>

        <?php if (empty($orders)): ?>
            <div class="content-box" style="text-align:center; margin-top:30px;">
                <p class="empty-state">No active orders right now. Orders will appear here when customers place them.</p>
            </div>
        <?php else: ?>

            <div class="queue-grid">
                <?php foreach ($orders as $order): ?>
                    <div class="queue-card <?= strtolower($order['order_status']) ?>" id="order-<?= $order['order_id'] ?>">

                        <div class="queue-card-header">
                            <div class="queue-num">#<?= $order['queue_number'] ?></div>
                            <span class="badge badge-<?= strtolower($order['order_status']) ?>">
                                <?= $order['order_status'] ?>
                            </span>
                        </div>

                        <div class="queue-table">
                            Table <?= htmlspecialchars($order['table_number'] ?? '—') ?>
                        </div>

                        <div class="queue-items">
                            <?= htmlspecialchars($order['items_summary']) ?>
                        </div>

                        <div class="queue-total">
                            ₱<?= number_format($order['total_amount'], 2) ?>
                        </div>

                        <div class="queue-time">
                            🕐 <?= date('h:i A', strtotime($order['created_at'])) ?>
                        </div>

                        <div class="queue-actions">
                            <?php if ($order['order_status'] === 'Queued'): ?>
                                <button class="btn-preparing"
                                        onclick="updateOrder(<?= $order['order_id'] ?>, 'Preparing')">
                                    🍳 Start Preparing
                                </button>
                            <?php endif; ?>

                            <?php if ($order['order_status'] === 'Preparing'): ?>
                                <button class="btn-serve"
                                        onclick="updateOrder(<?= $order['order_id'] ?>, 'Served')">
                                    ✅ Mark as Served
                                </button>
                            <?php endif; ?>

                            <button class="btn-cancel-order"
                                    onclick="updateOrder(<?= $order['order_id'] ?>, 'Cancelled')">
                                ✕ Cancel
                            </button>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </div>
</div>

<!-- ================= PIN MODALS (required by sidebar) ================= -->
<?php include __DIR__ . '/helpers/pin_modals.php'; ?>

<script>
function updateOrder(orderId, status) {
    const confirmMsg = status === 'Cancelled'
        ? 'Cancel this order? Stock will be restored.'
        : (status === 'Served' ? 'Mark this order as served?' : 'Start preparing this order?');

    if (!confirm(confirmMsg)) return;

    fetch('helpers/admindashboard_helpers.php?action=update_order', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    'order_id=' + orderId + '&status=' + encodeURIComponent(status)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const card = document.getElementById('order-' + orderId);
            if (card) {
                card.style.opacity = '0';
                card.style.transition = '0.4s';
                setTimeout(() => card.remove(), 400);
            }
        } else {
            alert('Error: ' + data.message);
        }
    });
}

/* Auto-refresh every 30 seconds */
setTimeout(() => location.reload(), 30000);
</script>

</body>
</html>
